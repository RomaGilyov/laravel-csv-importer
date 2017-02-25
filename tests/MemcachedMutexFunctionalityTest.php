<?php

namespace RGilyov\CsvImporter\Test;

use Orchestra\Testbench\TestCase;

class MemcachedMutexFunctionalityTest extends MutexFunctionality
{
    /**
     * @var string
     */
    protected $cacheDriver = 'memcached';
}