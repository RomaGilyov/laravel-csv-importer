<?php

use Orchestra\Testbench\TestCase;
use RGilyov\CsvImporter\Test\CsvImporters\MyCsvImporter;

class MainImportFunctionalityTest extends BaseTestCase
{
    use \Illuminate\Foundation\Bus\DispatchesJobs;

    /**
     * @var MyCsvImporter
     */
    protected $importer;

    public function setUp()
    {
        parent::setUp();

        $this->importer = (new MyCsvImporter())->setFile(__DIR__.'/files/guitars.csv');
    }

    /** @test */
    public function it_can_lock_import_process()
    {
        $this->dispatch(new \RGilyov\CsvImporter\Test\Jobs\TestImportJob());

        echo "test done" . PHP_EOL;
    }
}