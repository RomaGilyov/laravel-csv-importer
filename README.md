# laravel-csv-importer
Flexible and reliable way to import, parse, validate and transform your csv files with laravel

## Installation ##

```php
composer rgilyov/laravel-csv-importer 1.0.0
```

Works with laravel 5 and above, hhvm are supported.

## Requirements ##

Each created importer will have build in `mutex` functionality, to make imports secure and avoid possible data
incompatibilities, it's important especially in cases when > 100k csv files will be imported, due to that a
laravel application should have `file`, `redis` or `memcached` cache driver set in the `.env` file

## Basic usage ##

To create new csv importer, a class should extends `RGilyov\CsvImporter\BaseCsvImporter` abstract class
or the `php artisan make:csv-importer MyImporter` console command can be used, after execution new importer with name
`MyImporter` will be placed inside `app/CsvImporters/` directory.

```php
composer rgilyov/laravel-csv-importer
```

