<?php

namespace RGilyov\CsvImporter;

use Illuminate\Support\ServiceProvider;

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
            __DIR__.'/../config/csv-importer.php' => config_path('csv-importer.php')
        ], 'config');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/csv-importer.php', 'csv-importer');
    }
}
