<?php

namespace RGilyov\CsvImporter\Test\Queue;

use Orchestra\Testbench\TestCase;
use RGilyov\CsvImporter\Test\BaseTestCase;

class AppSetUp extends BaseTestCase
{
    /*
     * Make setUp method public
     */
    public function setUp($driver = null)
    {
        $this->cacheDriver = $driver ?: 'file';

        parent::setUp();
    }
}