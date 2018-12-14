<?php

namespace Peridot\Plugin\Gherkin;

class SpecReporter extends \Peridot\Reporter\SpecReporter
{
    protected $lastFeature;
    protected $lastScenario;

    protected static $instance;
    protected static $quiet = false;

    public function init(...$args)
    {
        static::$instance = $this;
        return parent::init(...$args);
    }

    public static function beQuiet()
    {
        static::$instance->output->setVerbosity(
            \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_QUIET
        );
    }

    /**
     * Output a test failure.
     *
     * @param int $errorNumber
     * @param TestInterface $test
     * @param $exception
     */
    protected function outputError($errorNumber, $test, $exception)
    {
        $feature = null;
        $scenario = null;
        $testDescription = null;

        $node = $test;
        while ($node !== null) {
            $class = get_class($node);
            $description = str_replace(
                "\n    ",
                "\n   ",
                $node->getDescription()
            );

            if ($description === '') {
                $node = $node->getParent();
                continue;
            }

            if ($class === 'Peridot\Core\Test') {
                $testDescription = $description;
            } elseif ($class === 'Peridot\Core\Suite') {
                if (strpos($description, 'Feature:') === 0) {
                    $feature = $description;
                } else {
                    $scenario = trim($description);
                }
            }

            $node = $node->getParent();
        }

        if ($this->lastFeature !== $feature) {
            $this->output->writeln("  " . $feature . "\n");
            $this->lastFeature = $feature;
            $this->lastScenario = null;
        }

        if ($this->lastScenario !== $scenario) {
            $this->output->writeln("   " . $scenario . "\n");
            $this->lastScenario = $scenario;
        }

        $this->output->writeln(
            $this->color(
                'error',
                sprintf("    %d) %s", $errorNumber, $testDescription)
            )
        );

        $message = sprintf(
            "       %s",
            str_replace("\n", "\n      ", $exception->getMessage())
        );
        $this->output->writeln($this->color('pending', $message));

        $class = method_exists($exception, 'getClass') ?
            $exception->getClass() :
            get_class($exception);

        $trace = method_exists($exception, 'getTrueTrace') ?
            $exception->getTrueTrace() :
            $exception->getTrace();

        array_unshift($trace, [
            'function' => $class . ' thrown',
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);

        $this->outputTrace($trace);
    }
}