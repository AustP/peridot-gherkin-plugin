<?php // @codingStandardsIgnoreLine

feature(
    'Yet another feature',
    scenario(
        'Another scenario',
        'When something else happens',
        function () {
            // including SomeClass here mocks loading it
            class SomeClass // @codingStandardsIgnoreLine
            {
                public static function makeSomethingElseHappen()
                {
                    return true;
                }
            }

            $itHappened = SomeClass::makeSomethingElseHappen();
            $this->itHappened = $itHappened;
        },
        'Then something else should have happened',
        function () {
            assert($this->itHappened === true);
        }
    )
);
