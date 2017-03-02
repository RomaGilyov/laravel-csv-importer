<?php

namespace RGilyov\CsvImporter\Test;

use Orchestra\Testbench\TestCase;
use RGilyov\CsvImporter\ClosureHeadersFilter;
use RGilyov\CsvImporter\ClosureValidationFilter;
use RGilyov\CsvImporter\Exceptions\CsvImporterException;
use RGilyov\CsvImporter\Test\CsvImporters\AsyncCsvImporter;
use RGilyov\CsvImporter\Test\CsvImporters\CsvImporter;
use RGilyov\CsvImporter\Test\CsvImporters\CustomValidationImporter;
use RGilyov\CsvImporter\Test\CsvImporters\HeadersFilters\MyHeadersFilter;
use RGilyov\CsvImporter\Test\CsvImporters\ValidationFilters\MyValidationFilter;

class CustomFiltersTest extends BaseTestCase
{
    public function tearDown()
    {
        CsvImporter::flushCastFilters();
        CsvImporter::flushRequiredFilters();
        CsvImporter::flushValidationFilters();

        CustomValidationImporter::flushValidationFilters();

        parent::tearDown();
    }

    /** @test */
    public function it_can_add_required_headers_filters()
    {
        CsvImporter::addRequiredFilter(new MyHeadersFilter());

        /*
         * Run another importer to make sure that added filter doesn't have any impact on another importers
         */
        (new AsyncCsvImporter())->setCsvFile(__DIR__.'/files/guitars.csv')->run();

        $this->expectException(CsvImporterException::class);
        $this->expectExceptionMessage(
            '{"quantity":1,"Headers error:":["The csv must contain either `name` field either `first_name` and `last_name` fields"]}'
        );

        (new CsvImporter())->setCsvFile(__DIR__.'/files/guitars.csv')->run();
    }

    /** @test */
    public function it_can_add_required_headers_filters_from_closure()
    {
        CsvImporter::addRequiredFilter(function ($item) {
            if (isset($item['name']) || isset($item['some_another'])) {
                return true;
            }

            return false;
        });

        $this->expectException(CsvImporterException::class);
        $this->expectExceptionMessage('{"quantity":1,"Headers error:":["Headers error occurred"]}');

        (new CsvImporter())->setCsvFile(__DIR__.'/files/guitars.csv')->run();
    }

    /** @test */
    public function getters_and_setters_for_required_filters()
    {
        CsvImporter::addRequiredFilters(function ($item) {}, new MyHeadersFilter());

        $this->assertTrue(CsvImporter::requiredFilterExists('filter'));
        $this->assertTrue(CsvImporter::requiredFilterExists('MyHeadersFilter'));
        $this->assertTrue(CsvImporter::getRequiredFilter('filter') instanceof ClosureHeadersFilter);
        $this->assertTrue(CsvImporter::getRequiredFilter('MyHeadersFilter') instanceof MyHeadersFilter);

        $filters = CsvImporter::getRequiredFilters();

        $this->assertTrue($filters['filter'] instanceof ClosureHeadersFilter);
        $this->assertTrue($filters['MyHeadersFilter'] instanceof MyHeadersFilter);

        CsvImporter::flushRequiredFilters();

        $this->assertFalse(CsvImporter::requiredFilterExists('filter'));
        $this->assertFalse(CsvImporter::requiredFilterExists('MyHeadersFilter'));
    }

    /** @test */
    public function it_can_add_validation_filters()
    {
        CustomValidationImporter::addValidationFilter(new MyValidationFilter());

        $importer = (new CustomValidationImporter())->setCsvFile(__DIR__.'/files/bad_word.csv');

        $importer->run();

        $entities = $this->getResultCsv($importer->finish()['files']['invalid_entities']);

        $this->assertEquals('bad_word', $entities[1][2]);
    }

    /** @test */
    public function it_can_add_validation_filters_from_closure()
    {
        CustomValidationImporter::addValidationFilter(function ($item) {
            if (strpos($item['title'], 'bad_word') !== false) {
                return false;
            }

            return true;
        }, 'bad_word_validation');

        $importer = (new CustomValidationImporter())->setCsvFile(__DIR__.'/files/bad_word.csv');

        $importer->run();

        $entities = $this->getResultCsv($importer->finish()['files']['invalid_entities']);

        $this->assertEquals('bad_word', $entities[1][2]);
    }

    /** @test */
    public function it_validation_filter_does_not_exists()
    {
        $this->expectException(CsvImporterException::class);
        $this->expectExceptionMessage('{"message":"Method [validateBadWordValidation] does not exist."}');

        $importer = (new CustomValidationImporter())->setCsvFile(__DIR__.'/files/bad_word.csv');

        $importer->run();
    }

    /** @test */
    public function getters_and_setters_for_validation_filters()
    {
        CsvImporter::addValidationFilters(function ($item) {}, new MyValidationFilter());

        $this->assertTrue(CsvImporter::validationFilterExists('filter'));
        $this->assertTrue(CsvImporter::validationFilterExists('bad_word_validation'));
        $this->assertTrue(CsvImporter::getValidationFilter('filter') instanceof ClosureValidationFilter);
        $this->assertTrue(CsvImporter::getValidationFilter('bad_word_validation') instanceof MyValidationFilter);

        $filters = CsvImporter::getValidationFilters();

        $this->assertTrue($filters['filter'] instanceof ClosureValidationFilter);
        $this->assertTrue($filters['bad_word_validation'] instanceof MyValidationFilter);

        CsvImporter::flushValidationFilters();

        $this->assertFalse(CsvImporter::validationFilterExists('filter'));
        $this->assertFalse(CsvImporter::validationFilterExists('bad_word_validation'));
    }
}