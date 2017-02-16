<?php

namespace RGilyov\CsvImporter\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeValidationFilter extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:csv-importer-validation-filter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Csv importer validation filter class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Filter';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/../stubs/validation_filter.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\CsvImporters\ValidationFilters';
    }
}
