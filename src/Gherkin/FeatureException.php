<?php

namespace Peridot\Plugin\Gherkin;

class FeatureException extends \Exception
{
    protected $class;
    protected $trace;

    public function __construct($message, $code, $file, $line, $class, $trace)
    {
        parent::__construct($message, $code);

        $this->file = $file;
        $this->line = $line;
        $this->class = $class;
        $this->trace = $trace;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function getTrueTrace()
    {
        return $this->trace;
    }
}
