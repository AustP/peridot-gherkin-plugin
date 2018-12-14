<?php

namespace Peridot\Plugin;

class GherkinPlugin
{
    public function __construct($emitter)
    {
        $emitter->on('peridot.start', function ($env) {
            $env->getDefinition()->getArgument('path')->setDefault('features');
        });

        $emitter->on('peridot.configure', function ($config) {
            $config->setDSL(dirname(__DIR__) . '/dsl.php');
            $config->setGrep('*.feature.php');
        });

        $emitter->on('peridot.reporters', function ($input, $reporters) {
            $reporters->register(
                'spec',
                'hierarchical spec list',
                'Peridot\Plugin\Gherkin\SpecReporter'
            );
        });

        $emitter->on('suite.start', function ($suite) use ($emitter) {
            $isolated = $suite->isolated ?? false;
            if ($isolated) {
                $sockets = [];
                if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets)) {
                    die('could not create sockets');
                }

                $pid = pcntl_fork();
                if ($pid === -1) {
                    socket_close($sockets[0]);
                    socket_close($sockets[1]);
                    die('could not fork');
                } elseif ($pid === 0) {
                    // child
                    $exceptions = [];

                    Gherkin\SpecReporter::beQuiet();

                    $tests = $suite->getTests();
                    $suite->setTests([]);

                    foreach ($tests as $i => $test) {
                        $fn = function () use (&$exceptions, $i, $test) {
                            $exception = null;
                            try {
                                $test->getDefinition()();
                            } catch (\Throwable $e) {
                                $exception = $e;
                            } catch (\Exception $e) {
                                $exception = $e;
                            }

                            if ($exception !== null) {
                                $exceptions[$i] = [
                                    'class' => get_class($exception),
                                    'code' => $exception->getCode(),
                                    'file' => $exception->getFile(),
                                    'line' => $exception->getLine(),
                                    'message' => $exception->getMessage(),
                                    'trace' => $exception->getTrace()
                                ];
                            }
                        };

                        $newTest = new \Peridot\Core\Test(
                            $test->getDescription(),
                            $fn
                        );

                        $suite->addTest($newTest);
                    }

                    $emitter->on(
                        'suite.end',
                        function ($endingSuite) use (
                            &$exceptions,
                            $sockets,
                            $suite
                        ) {
                            if ($suite == $endingSuite) {
                                socket_write(
                                    $sockets[0],
                                    json_encode($exceptions)
                                );

                                socket_close($sockets[0]);
                                socket_close($sockets[1]);

                                // kill the child process when the suite ends
                                die();
                            }
                        }
                    );
                } else {
                    // parent
                    $status = null;
                    pcntl_wait($status);

                    socket_set_nonblock($sockets[1]);

                    $json = '';
                    $data = '';
                    do {
                        $data = socket_read($sockets[1], 256);
                        $json .= $data;
                    } while (strlen($data) === 256);

                    socket_close($sockets[0]);
                    socket_close($sockets[1]);

                    $exceptions = json_decode($json, true);

                    $tests = $suite->getTests();
                    $suite->setTests([]);

                    $noop = function () {
                        //
                    };

                    foreach ($tests as $i => $test) {
                        $exception = $exceptions[$i] ?? null;
                        $fn = isset($exception) ? function () use ($exception) {
                            throw new Gherkin\FeatureException(
                                $exception['message'],
                                $exception['code'],
                                $exception['file'],
                                $exception['line'],
                                $exception['class'],
                                $exception['trace']
                            );
                        } : $noop;

                        $newTest = new \Peridot\Core\Test(
                            $test->getDescription(),
                            $fn
                        );

                        $suite->addTest($newTest);
                    }
                }
            }
        });
    }
}
