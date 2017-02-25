<?php

namespace RGilyov\CsvImporter\Test;

use Orchestra\Testbench\TestCase;

class FileMutexFunctionalityTest extends MutexFunctionality
{
    /**
     * @var string
     */
    protected $cacheDriver = 'file';
}