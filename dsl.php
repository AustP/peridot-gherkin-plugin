<?php

use Peridot\Plugin\GherkinPlugin;
use Peridot\Runner\Context;

function background(...$args)
{
    $setup = null;
    $teardown = null;

    $descriptions = [];
    foreach ($args as $arg) {
        if (is_string($arg)) {
            if (count($descriptions) === 0) {
                $descriptions[] = '';
                $descriptions[] = 'Background:';
            }

            $descriptions[] = '  ' . $arg;
        } elseif (is_callable($arg)) {
            if ($setup === null) {
                $setup = $arg;
            } elseif ($teardown === null) {
                $teardown = $arg;
            }
        }
    }

    return [
        'descriptions' => $descriptions,
        'fn' => function () use ($setup, $teardown) {
            if (isset($setup)) {
                Context::getInstance()->addSetupFunction($setup);
            }

            if (isset($teardown)) {
                Context::getInstance()->addTearDownFunction($teardown);
            }
        },
        'setup' => $setup,
        'teardown' => $teardown
    ];
}



function __feature($name, $pending, $focused, ...$args)
{
    $fns = [];
    $descriptions = [];
    foreach ($args as $arg) {
        if (is_string($arg)) {
            $descriptions[] = $arg;
        } elseif (is_callable($arg)) {
            $fns[] = $arg;
        } elseif (is_array($arg)) {
            foreach ($arg['descriptions'] as $description) {
                $descriptions[] = $description;
            }

            $fns[] = $arg['fn'];
        }
    }

    $fn = function () use ($fns) {
        foreach ($fns as $fn) {
            if (is_callable($fn)) {
                $fn();
            }
        }
    };

    $indent = '    ';
    $feature = 'Feature: ' . $name;

    foreach ($descriptions as $i => $description) {
        if ($i === 1 && $description === '') {
            continue;
        }

        if ($i !== 0) {
            $feature .= PHP_EOL . $indent;
        }

        $feature .= $description;
    }

    Context::getInstance()->addSuite($feature, $fn, $pending, $focused);
}

function feature($name, ...$args)
{
    return __feature($name, null, false, ...$args);
}

function ffeature($name, ...$args)
{
    return __feature($name, null, true, ...$args);
}

function xfeature($name, ...$args)
{
    return __feature($name, true, false, ...$args);
}



function __suite($title, $pending, $focused, $isolated, ...$args)
{
    $background = null;
    $focusedTest = false;
    $pendingTest = null;

    $tests = [];
    $description = null;
    foreach ($args as $arg) {
        if (is_string($arg)) {
            if ($arg === '') {
                continue;
            }

            if (isset($description)) {
                $tests[] = [
                    'description' => $description,
                    'fn' => null,
                    'pending' => null,
                    'focused' => false
                ];
            }

            $description = $arg;
        } elseif (is_callable($arg)) {
            if ($description === null) {
                continue;
            }

            // now that we have a function,
            // update any previous "tests" that are pending to pass
            if (strpos($title, 'Scenario:') === 0) {
                $testCount = count($tests);
                for ($i = 0; $i < $testCount; $i++) {
                    if ($tests[$i]['fn'] === null) {
                        $tests[$i]['fn'] = function () {
                            // noop to make the "test" pass
                        };
                    }
                }
            }

            $tests[] = [
                'description' => $description,
                'fn' => $arg,
                'pending' => $pendingTest,
                'focused' => $focusedTest
            ];

            $description = null;
            $pendingTest = null;
            $focusedTest = false;
        } elseif (is_array($arg)) {
            if (count($arg) === 1) {
                if ($arg[0] === 'focus') {
                    $focusedTest = true;
                } elseif ($arg[0] === 'skip') {
                    $pendingTest = true;
                }
            } else {
                foreach ($arg['descriptions'] as $i => $desc) {
                    if ($i <= 1) {
                        continue;
                    }

                    $tests[] = [
                        'description' => 'Background: ' . trim($desc),
                        'fn' => function () {
                        },
                        'pending' => null,
                        'focused' => false
                    ];
                }

                $background = $arg;
            }
        }
    }

    if (isset($description)) {
        $tests[] = [
            'description' => $description,
            'fn' => null,
            'pending' => $pendingTest,
            'focused' => $focusedTest
        ];

        $pendingTest = null;
        $focusedTest = false;
    }

    $fn = function () use ($background, $tests) {
        foreach ($tests as $test) {
            $fn = $test['fn'];
            if (is_array($background)) {
                $fn = function () use ($background, $fn) {
                    if (is_callable($background['setup'])) {
                        $background['setup']();
                    }

                    $exception = null;
                    try {
                        $fn();
                    } catch (\Throwable $e) {
                        $exception = $e;
                    }

                    if (is_callable($background['teardown'])) {
                        $background['teardown']();
                    }

                    if (isset($exception)) {
                        throw $exception;
                    }
                };
            }

            Context::getInstance()->addTest(
                $test['description'],
                $fn,
                $test['pending'],
                $test['focused']
            );
        }
    };

    return function () use ($title, $fn, $pending, $focused, $isolated) {
        $suite = Context::getInstance()->addSuite(
            $title,
            $fn,
            $pending,
            $focused
        );

        $suite->isolated = $isolated;
    };
}



function __scenario($title, $pending, $focused, $isolated, ...$args)
{
    return __suite("Scenario: $title", $pending, $focused, $isolated, ...$args);
}

function scenario($title, ...$args)
{
    return __scenario($title, null, false, false, ...$args);
}

function fscenario($title, ...$args)
{
    return __scenario($title, null, true, false, ...$args);
}

function xscenario($title, ...$args)
{
    return __scenario($title, true, false, false, ...$args);
}

function isolatedScenario($title, ...$args)
{
    return __scenario($title, null, false, true, ...$args);
}

function fisolatedScenario($title, ...$args)
{
    return __scenario($title, null, true, true, ...$args);
}

function xisolatedScenario($title, ...$args)
{
    return __scenario($title, true, false, true, ...$args);
}



function __stories($pending, $focused, $isolated, ...$args)
{
    return __suite('Stories:', $pending, $focused, $isolated, ...$args);
}

function stories(...$args)
{
    return __stories(null, false, false, ...$args);
}

function fstories(...$args)
{
    return __stories(null, true, false, ...$args);
}

function xstories(...$args)
{
    return __stories(true, false, false, ...$args);
}

function isolatedStories(...$args)
{
    return __stories(null, false, true, ...$args);
}

function fisolatedStories(...$args)
{
    return __stories(null, true, true, ...$args);
}

function xisolatedStories(...$args)
{
    return __stories(true, false, true, ...$args);
}



function focusNextStory()
{
    return ['focus'];
}

function skipNextStory()
{
    return ['skip'];
}
