# peridot-gherkin-plugin

A [Peridot](https://github.com/peridot-php/peridot) plugin that adds a Gherkin style DSL.

## Gherkin Style Tests

```(gherkin)
Feature: Some Feature
  In order to ...
  As a ...
  I want to ...

  Additional text...

  Background:
    Given something...
    And something else...

  Scenario: Some Scenario
    Given something...
    When something happens...
    Then something else happens...
```

This is an example of a feature written in the Gherkin language. It looks nice but how do you convert this into a PHP test? This plugin will help you do that.

This plugin adds a DSL wrapper around Peridot so you can write your tests in a PHP-version of the Gherkin language. Here is an example for the above feature:

```(php)
feature(
    'Some Feature',
    '',
    'In order to...',
    'As a ...',
    'I want to...',
    '',
    'Additional text...',
    background(
        'Given something',
        'And something else',
        function () { /* setup function */ },
        function () { /* optional teardown function */ }
    ),
    scenario(
        'Some scenario',
        '',
        'Given something',
        'When something happens',
        function () {},
        'Then something else happens',
        function () {}
    )
);
```

This is the output for the above test:

```(text)
  Feature: Some Feature
    In order to...
    As a ...
    I want to...

    Additional text...

    Background:
      Given something
      And something else

    Scenario: Some scenario
      ✓ Given something
      ✓ When something happens
      ✓ Then something else happens


  3 passing (2 ms)
```

## DSL

This plugin adds 6 primary functions: `feature`, `background`, `scenario`, `isolatedScenario`, `story`, and `isolatedStory`.

### feature

- The first argument is the name of the feature.
- Any `''` arguments are rendered as newlines.
- Any other string arguments are rendered as-is.
- Can have the return value of any of the other 3 functions as an argument.

Other available methods:  
`xfeature` - Marks the feature as pending.  
`ffeature` - Focuses the feature.

### background

- Any `''` arguments are rendered as newlines.
- Any other string arguments are rendered as-is.
- The first callable argument encountered will be the setup function.
- The second callable argument encountered will be the teardown function.

Note: It is possible to call `background` without supplying any strings to just add setup / teardown functions.

### scenario

- The first argument is the title of the scenario.
- Any `''` arguments are ignored.
- Any other string arguments will be used as test descriptions.
- Any callable arguments will be used as the test functions.

The scenario function is what determines what actually gets tested. Any string followed by a callable will be a "test". So in the above example, we had 2 tests. `When something happens` and `Then something else happens` because they were followed by a callable.

Notice that in the output, `Given something` had a check by it. This plugin assumes that if no callable is given after a string, the logic for the string will be handled in the next callable. (You could have a callable after the `Given something` argument, but oftentimes the logic is just a variable assignment which is untestable.)

If you fail to include a callable after including string arguments, they will be marked as pending tests.

Other available methods:  
`xscenario` - Marks the scenario as pending.  
`fscenario` - Focuses the scenario.

### isolatedScenario

This behaves exactly as `scenario` except for one key difference. This scenario will run in a separate blocking process. This allows the tests defined in the scenario to be isolated from the rest of the tests.

An example use case for using `isolatedScenario` is when using [Mockery](https://github.com/mockery/mockery) to mock/spy a static method. The only way to accomplish this, is by making an alias. As long as the class hasn't been autoloaded yet, Mockery will load a fake class.

If you need access to the real class later on in your tests, there is no way to load it because Mockery has already loaded a class with the same name. But by running your tests in isolation, Mockery will load the fake class in a separate process. This leaves you free to load the real class when the time comes.

Here is an example to illustrate this use case. Assume we have a class named `SomeClass` that has a static method named `makeSomethingElseHappen`:

```(php)
feature(
    'Another Feature',
    isolatedScenario(
        'Some scenario',
        'When something happens',
        function () {
            $this->spy = Mockery::spy('alias:SomeClass');
            makeSomethingHappen();
        },
        'Then something else happens',
        function () {
            $this->spy->shouldHaveReceived('makeSomethingElseHappen');
        }
    )
);

feature(
    'Yet another feature',
    isolatedScenario(
        'Another scenario',
        'When something else happens',
        function () {
            $itHappened = SomeClass::makeSomethingElseHappen();
            $this->itHappened = $itHappened;
        },
        'Then something else should have happened',
        function () {
            assert($this->itHappened === true);
        }
    )
);
```

In the above example, the first scenario is run in isolation. This allows us to test the `makeSomethingElseHappen` method later on in our tests. (Note: Each `feature` call should be in its own file).

Other available methods:  
`xisolatedScenario` - Marks the scenario as pending.  
`fisolatedScenario` - Focuses the scenario.

### story

- Any string arguments will be used as the test description
- The first callable argument will be used as the test function

Although they don't fit in as nicely into the Gherkin language as scenarios do, sometimes it is helpful to define tests as stories rather than scenarios. This function will help you do that. The following two calls to `story` are equivalent:

```(php)
feature(
    'Story Feature',
    story(
        'As a someone',
        'I want something',
        'So that something',
        function () {}
    ),
    story(
        'As a someone I want something So that something',
        function () {}
    )
);
```

Other available methods:  
`xstory` - Marks the story as pending.  
`fstory` - Focuses the story.

### isolatedStory

This behaves exactly as `story` except it will run in a separate process. See `isolatedScenario` for more details about running in a separate process.

Other available methods:  
`xisolatedStory` - Marks the story as pending.  
`fisolatedStory` - Focuses the story.

## Installation / Setup

To install:

```(bash)
composer require --dev austp/peridot-gherkin-plugin
```

In your `peridot.php` file:

```(php)
<?php

require('vendor/autoload.php');

use Peridot\Plugin\GherkinPlugin;

return function ($emitter) {
    new GherkinPlugin($emitter);
};
```

This will configure Peridot to:

- Use the described DSL
- Look for tests in the `features/` directory
- Look for tests in PHP files that end in `.feature.php`
- Use a spec reporter built specifically for Gherkin
