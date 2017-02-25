<?php

require_once __DIR__.'/../../vendor/autoload.php';

(new \RGilyov\CsvImporter\Test\Queue\AppSetUp())->setUp('memcached');

\Illuminate\Support\Facades\Artisan::call('queue:work');