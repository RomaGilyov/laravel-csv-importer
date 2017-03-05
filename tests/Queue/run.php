<?php

require_once __DIR__.'/../../vendor/autoload.php';

if (!is_dir(\RGilyov\CsvImporter\Test\Queue\AppSetUp::$cachePath)) {
    mkdir(\RGilyov\CsvImporter\Test\Queue\AppSetUp::$cachePath);
}

if (!is_dir(\RGilyov\CsvImporter\Test\Queue\AppSetUp::$filesPath)) {
    mkdir(\RGilyov\CsvImporter\Test\Queue\AppSetUp::$filesPath);
}

exec('php run-queue-app-with-file-cache-driver.php &');
exec('php run-queue-app-with-redis-cache-driver.php &');
exec('php run-queue-app-with-memcached-cache-driver.php &');