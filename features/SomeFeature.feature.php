<?php

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
        function () {
            /* setup function */
        },
        function () {
            /* optional teardown function */
        }
    ),
    scenario(
        'Some scenario',
        '',
        'Given something',
        'When something happens',
        function () {
        },
        'Then something else happens',
        function () {
        }
    )
);
