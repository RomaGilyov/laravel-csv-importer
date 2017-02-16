<?php

namespace RGilyov\CsvImporter\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeCsvImporter extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:csv-importer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Csv importer class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Importer';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/../stubs/importer.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\CsvImporters';
    }
}
