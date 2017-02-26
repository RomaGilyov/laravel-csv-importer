<?php

namespace RGilyov\CsvImporter\Test;

use Orchestra\Testbench\TestCase;
use RGilyov\CsvImporter\Test\CsvImporters\CsvImporter;

class CsvManipulationsTest extends BaseTestCase
{
    /**
     * @var CsvImporter
     */
    protected $importer;

    public function setUp()
    {
        parent::setUp();

        $this->importer = (new CsvImporter())->setCsvFile(__DIR__.'/files/guitars.csv');
    }

    /** @test */
    public function it_can_count_the_csv()
    {
        $quantity = $this->importer->countCsv();

        $this->assertEquals(12, $quantity);
    }

    /** @test */
    public function it_can_extract_distinct_values_from_the_given_csv()
    {
        $distinct = $this->importer->distinct('title');

        $this->assertEquals('TAM100 Tosin Abasi Signature', $distinct[1]);
        $this->assertEquals(10, count($distinct));
    }

    /** @test */
    public function it_can_iterate_the_given_csv()
    {
        $companies = [];

        $this->importer->each(function ($item) use (&$companies) {
            $companies[] = $item['company'];
        });

        $this->assertEquals('ESP', $companies[0]);
        $this->assertEquals(12, count($companies));
    }
}