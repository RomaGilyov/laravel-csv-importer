# laravel-csv-importer
Flexible and reliable way to import, parse, validate and transform your csv files with laravel

## Installation ##

```php
composer rgilyov/laravel-csv-importer 1.0.0

// after installation you may publish default configuration file
php artisan vendor:publish --tag=config
```

Works with laravel 5 and above, hhvm are supported.

## Requirements ##

Each created importer will have build in `mutex` functionality to make imports secure and avoid possible data
incompatibilities, it's important especially in cases when > 100k lines csv files will be imported, due to that a
laravel application should have `file`, `redis` or `memcached` cache driver set in the `.env` file

## Basic usage ##

To create new csv importer, a class should extends `RGilyov\CsvImporter\BaseCsvImporter` abstract class
or the `php artisan make:csv-importer MyImporter` console command can be used, after execution new file with name
`MyImporter.php` and basic importer set up will be placed inside `app/CsvImporters/` directory.

```php
    <?php

    namespace app\CsvImporters;

    use RGilyov\CsvImporter\BaseCsvImporter;

    class MyImporter extends BaseCsvImporter
    {
        /**
         *  Specify mappings and rules for the csv importer, we also may create csv files when we can write csv entities
         *
         * @return array
         */
        public function csvConfigurations()
        {
            return [
                'mappings' => [
                    'serial_number' => ['required', 'validation' => ['numeric'], 'cast' => 'string'],
                    'title'         => ['validation' => ['required'], 'cast' => ['string']],
                    'company'       => ['validation' => ['string']]
                ],
                'csv_files' => [
                    'valid_entities'   => '/valid_entities.csv',
                    'invalid_entities' => '/invalid_entities.csv',
                ]
            ];
        }

        /**
         *  Will be executed for a csv line if it passed validation
         *
         * @param $item
         * @throws \RGilyov\CsvImporter\Exceptions\CsvImporterException
         * @return void
         */
        public function handle($item)
        {
            $this->insertTo('valid_entities', $item);
        }

        /**
         *  Will be executed if a csv line did not pass validation
         *
         * @param $item
         * @throws \RGilyov\CsvImporter\Exceptions\CsvImporterException
         * @return void
         */
        public function invalid($item)
        {
            $this->insertTo('invalid_entities', $item);
        }
    }
```

There are 3 methods:

1. `csvConfigurations` which returns configurations for that type of csv, configurations has 3 parts:
    a) `'mappings'`: you may specify fields which you expect the given csv has and attach rules to each field, there are
       3 types of rules(filters) which you can specify:
            1) To make a field(header) mandatory for import you need to set `required` parameter for the field(header)
               `'name' => ['required']`, so if the given csv file won't have the field(header) `name` an error will be
               thrown.
            2) You may set validation to each field(header), the importer uses laravel validation, so you can use any rules
               from there https://laravel.com/docs/5.4/validation#available-validation-rules to check csv values
               `'email' => ['required', validation => ['email']]`, so if a value won't be a valid email the csv line
               which contains the email will be put inside `invalid($item)` method otherwise inside `handle($item)`
            3) You can cast csv values in any native php type and format date which csv contains
               `'name' => ['required', 'cast' => 'string']` or `'birth_date' => ['cast' => ['string', 'date']]`
               cast will work before validation
    b) `'csv_files'`: files specified inside the key will be created, you can write csv lines inside each file,
       with `$this->insertTo('csv_file_name', $item);` method, for example you can separate invalid csv lines from valid
    c) `'config'`: you may overwrite global `config/csv-importer.php` configurations hear for the given csv importer
