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

        if (!is_dir(static::$cachePath)) {
            mkdir(static::$cachePath);
        }

        if (!is_dir(static::$filesPath)) {
            mkdir(static::$filesPath);
        }
        
        parent::setUp();
    }
}