<?php

namespace RGilyov\CsvImporter\Test;

use Orchestra\Testbench\TestCase;
use RGilyov\CsvImporter\Exceptions\CsvImporterException;
use RGilyov\CsvImporter\Test\CsvImporters\CsvImporter;

class StandardFiltersTest extends BaseTestCase
{
    /** @test */
    public function required_header()
    {
        $this->expectException(CsvImporterException::class);

        (new CsvImporter())->setCsvFile(__DIR__.'/files/invalid_guitars.csv')->run();
    }

    /** @test */
    public function validation_filters()
    {
        $invalidEntities = $this->getResultCsv($this->importCsv()['files']['invalid_entities']);

        $this->assertEquals('not_numeric', $invalidEntities[1][0]);
        $this->assertEquals('invalid_email', $invalidEntities[2][3]);
        $this->assertEquals('', $invalidEntities[3][2]);
    }

    /** @test */
    public function cast_date_filters()
    {
        /*
         * only date cast functionality will be tested coz usual php cast system works pretty straight forward
         */

        $entities = $this->getResultCsv($this->importCsv()['files']['valid_entities']);

        $this->assertEquals('2017-02-26', $entities[1][4]);
        $this->assertEquals('2017-02-26 10:00:12', $entities[1][5]);
        $this->assertEquals('2007-02-26', $entities[2][4]);
        $this->assertEquals('0001-01-01 00:00:00', $entities[2][5]);

        $importer = (new CsvImporter(true))->setCsvFile(__DIR__.'/files/guitars.csv');

        $importer->run();

        $entities = $this->getResultCsv($importer->finish()['files']['valid_entities']);

        $this->assertEquals('2017-02-26', $entities[1][4]);
        $this->assertEquals('2017-02-26 10:00:12', $entities[1][5]);
        $this->assertEquals('2007-02-26', $entities[2][4]);
        $this->assertEquals('0001-01-01 00:00:00', $entities[2][5]);

        $importer = (new CsvImporter())->setCsvFile(__DIR__.'/files/guitars.csv');

        $importer->setCsvDateFormat('Y-m-d')->run();

        $entities = $this->getResultCsv($importer->finish()['files']['valid_entities']);

        $this->assertEquals('0001-01-01', $entities[1][4]);
        $this->assertEquals('0001-01-01 00:00:00', $entities[1][5]);
        $this->assertEquals('0007-02-26', $entities[2][4]);
        $this->assertEquals('0001-01-01 00:00:00', $entities[2][5]);
    }
}