<?php

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
        }
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



function __scenario($title, $pending, $focused, $isolated, ...$args)
{
    $scenario = "\n    Scenario: $title";

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
                    'fn' => null
                ];
            }

            $description = $arg;
        } elseif (is_callable($arg)) {
            if ($description === null) {
                continue;
            }

            // now that we have a function,
            // update any previous "tests" that are pending to pass
            $testCount = count($tests);
            for ($i = 0; $i < $testCount; $i++) {
                if ($tests[$i]['fn'] === null) {
                    $tests[$i]['fn'] = function () {
                        // noop to make the "test" pass
                    };
                }
            }

            $tests[] = [
                'description' => $description,
                'fn' => $arg
            ];

            $description = null;
        }
    }

    if (isset($description)) {
        $tests[] = [
            'description' => $description,
            'fn' => null
        ];
    }

    $fn = function () use ($tests) {
        foreach ($tests as $test) {
            Context::getInstance()->addTest($test['description'], $test['fn']);
        }
    };

    return function () use ($scenario, $fn, $pending, $focused, $isolated) {
        $suite = Context::getInstance()->addSuite(
            $scenario,
            $fn,
            $pending,
            $focused
        );

        $suite->isolated = $isolated;
    };
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
