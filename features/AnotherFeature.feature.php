<?php // @codingStandardsIgnoreLine

function makeSomethingHappen()
{
    SomeClass::makeSomethingElseHappen();
}

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
