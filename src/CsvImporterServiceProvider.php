<?php

namespace RGilyov\CsvImporter;

use Illuminate\Support\ServiceProvider;
use RGilyov\CsvImporter\Commands\MakeCastFilter;
use RGilyov\CsvImporter\Commands\MakeCsvImporter;
use RGilyov\CsvImporter\Commands\MakeHeadersFilter;
use RGilyov\CsvImporter\Commands\MakeValidationFilter;

class CsvImporterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/csv-importer.php' => config_path('csv-importer.php')
        ], 'config');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/csv-importer.php', 'csv-importer');

        if (method_exists($this, 'commands')) {
            $this->commands([
                MakeCsvImporter::class,
                MakeHeadersFilter::class,
                MakeValidationFilter::class,
                MakeCastFilter::class
            ]);
        }
    }
}
