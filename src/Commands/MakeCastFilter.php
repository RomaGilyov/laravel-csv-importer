<?php

namespace RGilyov\CsvImporter\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeCastFilter extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:csv-importer-cast-filter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Csv importer cast filter class';

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
        return __DIR__.'/../stubs/cast_filter.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\CsvImporters\CastFilters';
    }
}
