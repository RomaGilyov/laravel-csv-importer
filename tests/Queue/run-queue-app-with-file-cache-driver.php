<?php

include __DIR__.'/../../vendor/autoload.php';

(new \RGilyov\CsvImporter\Test\Queue\AppSetUp())->setCacheDriver('file')->setUp();

\Illuminate\Support\Facades\Artisan::call('queue:work');