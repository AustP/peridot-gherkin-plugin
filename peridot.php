<?php

require('vendor/autoload.php');

use Peridot\Plugin\GherkinPlugin;

return function ($emitter) {
    new GherkinPlugin($emitter);
};
