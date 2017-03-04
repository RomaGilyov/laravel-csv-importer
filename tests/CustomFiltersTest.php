<?php

namespace RGilyov\CsvImporter\Test;

use Orchestra\Testbench\TestCase;
use RGilyov\CsvImporter\ClosureCastFilter;
use RGilyov\CsvImporter\ClosureHeadersFilter;
use RGilyov\CsvImporter\ClosureValidationFilter;
use RGilyov\CsvImporter\Exceptions\CsvImporterException;
use RGilyov\CsvImporter\Exceptions\ImportValidationException;
use RGilyov\CsvImporter\Test\CsvImporters\AsyncCsvImporter;
use RGilyov\CsvImporter\Test\CsvImporters\CastFilters\MyCastFilter;
use RGilyov\CsvImporter\Test\CsvImporters\CsvImporter;
use RGilyov\CsvImporter\Test\CsvImporters\CustomValidationImporter;
use RGilyov\CsvImporter\Test\CsvImporters\HeadersFilters\MyHeadersFilter;
use RGilyov\CsvImporter\Test\CsvImporters\ValidationFilters\MyValidationFilter;

class CustomFiltersTest extends BaseTestCase
{
    public function tearDown()
    {
        CsvImporter::flushCastFilters();
        CsvImporter::flushHeadersFilters();
        CsvImporter::flushValidationFilters();

        CustomValidationImporter::flushValidationFilters();

        parent::tearDown();
    }

    /** @test */
    public function it_can_add_required_headers_filters()
    {
        CsvImporter::addHeadersFilter(new MyHeadersFilter());

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
        CsvImporter::addHeadersFilter(function ($item) {
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
        CsvImporter::addHeadersFilters(function ($item) {}, new MyHeadersFilter());

        $this->assertTrue(CsvImporter::headersFilterExists('filter'));
        $this->assertTrue(CsvImporter::headersFilterExists('MyHeadersFilter'));
        $this->assertTrue(CsvImporter::getHeadersFilter('filter') instanceof ClosureHeadersFilter);
        $this->assertTrue(CsvImporter::getHeadersFilter('MyHeadersFilter') instanceof MyHeadersFilter);

        $filters = CsvImporter::getHeadersFilters();

        $this->assertTrue($filters['filter'] instanceof ClosureHeadersFilter);
        $this->assertTrue($filters['MyHeadersFilter'] instanceof MyHeadersFilter);

        CsvImporter::flushHeadersFilters();

        $this->assertFalse(CsvImporter::headersFilterExists('filter'));
        $this->assertFalse(CsvImporter::headersFilterExists('MyHeadersFilter'));

        CsvImporter::addHeadersFilters($filters);

        $this->assertTrue(CsvImporter::headersFilterExists('filter'));
        $this->assertTrue(CsvImporter::headersFilterExists('MyHeadersFilter'));
        $this->assertTrue(CsvImporter::getHeadersFilter('filter') instanceof ClosureHeadersFilter);
        $this->assertTrue(CsvImporter::getHeadersFilter('MyHeadersFilter') instanceof MyHeadersFilter);
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
    public function if_validation_filter_does_not_exists()
    {
        $this->expectException(ImportValidationException::class);
        $this->expectExceptionMessage("Method [validateBadWordValidation] does not exist.");

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

        CsvImporter::addValidationFilters($filters);

        $this->assertTrue(CsvImporter::validationFilterExists('filter'));
        $this->assertTrue(CsvImporter::validationFilterExists('bad_word_validation'));
        $this->assertTrue(CsvImporter::getValidationFilter('filter') instanceof ClosureValidationFilter);
        $this->assertTrue(CsvImporter::getValidationFilter('bad_word_validation') instanceof MyValidationFilter);
    }

    /** @test */
    public function it_can_add_cast_filters()
    {
        CsvImporter::addCastFilter(new MyCastFilter());

        $invalidEntities = $this->getResultCsv($this->importCsv()['files']['invalid_entities']);
        $validEntities = $this->getResultCsv($this->importCsv()['files']['valid_entities']);

        $this->assertTrue(strcmp('misha mansoor juggernaut ht6', $invalidEntities[1][2]) === 0);
        $this->assertTrue(strcmp('tam100 tosin abasi signature', $validEntities[3][2]) === 0);
    }

    /** @test */
    public function it_can_add_cast_filters_from_closure()
    {
        CsvImporter::addCastFilter(function ($value) {
            return strtolower($value);
        }, 'lowercase');

        $invalidEntities = $this->getResultCsv($this->importCsv()['files']['invalid_entities']);
        $validEntities = $this->getResultCsv($this->importCsv()['files']['valid_entities']);

        $this->assertTrue(strcmp('misha mansoor juggernaut ht6', $invalidEntities[1][2]) === 0);
        $this->assertTrue(strcmp('tam100 tosin abasi signature', $validEntities[3][2]) === 0);
    }

    /** @test */
    public function getters_and_setters_for_cast_filters()
    {
        CsvImporter::addCastFilters(function ($item) {}, new MyCastFilter());

        $this->assertTrue(CsvImporter::castFilterExists('filter'));
        $this->assertTrue(CsvImporter::castFilterExists('lowercase'));
        $this->assertTrue(CsvImporter::getCastFilter('filter') instanceof ClosureCastFilter);
        $this->assertTrue(CsvImporter::getCastFilter('lowercase') instanceof MyCastFilter);

        $filters = CsvImporter::getCastFilters();

        $this->assertTrue($filters['filter'] instanceof ClosureCastFilter);
        $this->assertTrue($filters['lowercase'] instanceof MyCastFilter);

        CsvImporter::flushCastFilters();

        $this->assertFalse(CsvImporter::castFilterExists('filter'));
        $this->assertFalse(CsvImporter::castFilterExists('lowercase'));

        CsvImporter::addCastFilters($filters);

        $this->assertTrue(CsvImporter::castFilterExists('filter'));
        $this->assertTrue(CsvImporter::castFilterExists('lowercase'));
        $this->assertTrue(CsvImporter::getCastFilter('filter') instanceof ClosureCastFilter);
        $this->assertTrue(CsvImporter::getCastFilter('lowercase') instanceof MyCastFilter);
    }
}