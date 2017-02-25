<?php

namespace RGilyov\CsvImporter\Test;

use Orchestra\Testbench\TestCase;

class RedisMutexFunctionalityTest extends MutexFunctionality
{
    /**
     * @var string
     */
    protected $cacheDriver = 'redis';
}