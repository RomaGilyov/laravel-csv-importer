# laravel-csv-importer
Flexible and reliable way to import, parse, validate and transform your csv files with laravel

## Installation ##

```php
composer require rgilyov/laravel-csv-importer
```

Register \RGilyov\CsvImporter\CsvImporterServiceProvider inside `config/app.php`
```php
    'providers' => [
        //...
        \RGilyov\CsvImporter\CsvImporterServiceProvider::class,
    ];
```

After installation you may publish default configuration file
```
php artisan vendor:publish --tag=config
```

Works with laravel 5 and above, hhvm are supported.

## Requirements ##

Each created importer will have built in `mutex` functionality to make imports secure and avoid possible data
incompatibilities, it's important especially in cases when > 100k lines csv files will be imported, due to that a
laravel application should have `file`, `redis` or `memcached` cache driver set in the `.env` file

## Basic usage ##

To create new csv importer, a class should extends `\RGilyov\CsvImporter\BaseCsvImporter` abstract class
or the `php artisan make:csv-importer MyImporter` console command can be used, after execution new file with name
`MyImporter.php` and basic importer set up will be placed inside `app/CsvImporters/` directory.

```php
    <?php

    namespace App\CsvImporters;

    use RGilyov\CsvImporter\BaseCsvImporter;

    class MyImporter extends BaseCsvImporter
    {
        /**
         *  Specify mappings and rules for the csv importer, you also may create csv files to write csv entities
         *  and overwrite global configurations
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

There are 3 main methods:

- `csvConfigurations` which returns configurations for the given type of csv, configurations has 3 parts:

  * `'mappings'`: you may specify fields(headers) which you expect the given csv has and attach
    rules to each field(header), there are 3 types of rules(filters) which you can specify:

      * To make a field(header) mandatory for the import you need to set `required` parameter for the field(header)
         `'name' => ['required']`, so if the given csv file won't have the field(header) `name` an error will be
         thrown.

      * You may set validation to each field(header), the importer uses laravel validation, so you can use any rules
         from there https://laravel.com/docs/5.4/validation#available-validation-rules to check csv values
         `'email' => ['required', validation => ['email']]`, so if a value won't be a valid email, the csv line
         which contains the email will be put inside `invalid($item)` method otherwise inside `handle($item)`.

      * You can cast csv values in any native php type and format date which csv contains
         `'name' => ['required', 'cast' => 'string']` or `'birth_date' => ['cast' => ['string', 'date']]`
         cast will work before validation.

  * `'csv_files'`: files specified inside the key will be created, you can write csv lines inside each file,
     with `$this->insertTo('csv_file_name', $item);` method, for example you can separate invalid csv lines from valid,
     it uses laravel filesystem `\Storage` support class https://laravel.com/docs/5.4/filesystem.

  * `'config'`: you may overwrite global `config/csv-importer.php` configurations hear for the given csv importer.

- `handle`: will be executed for csv lines which passed validation.
- `invalid`: will be executed for csv lines which didn't pass validation.

Let's finally import a csv:

```php
    $importer = (new \App\CsvImporters\MyImporter())->setCsvFile('my_huge_csv_with_1000k_lines.csv');
    $importer->run();

    // progress information will be here, due to the import process already started above
    $result = $importer->run();
```

After the import had started you won't be able to start another import until the first one finished.

During the import though you may want to know progress of the running process:

```php
    $progress = $importer->getProgress();

    /*
        [
            'data' => ["message"  => 'The import process is running'],
            'meta' => [
                'processed'  => 250000,
                'remains'    => 750000,
                'percentage' => 25,
                'finished'   => false,
                'init'       => false,
                'running'    => true
            ]
        ]
    */
```

At the end of the import you will have key `finished => true` inside `meta` data.
So you will need to finish your csv import:

```php
    $finishDetails = $importer->finish();

    /*
        [
            [
                'data' => [
                    "message" => 'The import process successfully finished.'
                ],
                'meta' => ["finished" => true, 'init' => false, 'running' => false],
                'csv_files' => [
                    'valid_entities.csv',
                    'invalid_entities.csv'
                ]
            ]
        ]
    */
```

If something went wrong, you can cancel the current import process:

```php
    $importer->cancel();
```

## Importer customization ##

Besides methods above the importer also has a list of methods which can help you to easily
expand your functionality for some particular cases:

- `before` - will be executed before start of an import process
- `after` - will be executed after an import process finished
- `onCancel` - will be executed before abortion of an import process
- `initProgressBar` - will initialize new progress bar
- `progressBarDetails` - additional information for progress bar
- `setFinalDetails` - set additional information after `finish()` of an import process
- `setError` - add an error to the list of errors which will be thrown (if exists)

```php
    <?php

    namespace App\CsvImporters;

    use RGilyov\CsvImporter\BaseCsvImporter;

    class MyImporter extends BaseCsvImporter
    {
        //...

        /**
         * Will be executed before importing
         *
         * @return void
         */
        protected function before()
        {
            // do something before the import start
            if (! $this->checkSomething()) {
                $this->setError('Oops', 'something went wrong.');
            };
        }

        /**
         *  Adjust additional information to progress bar during import process
         *
         * @return null|string|array
         */
        public function progressBarDetails()
        {
            return "I'm a csv importer and I'm running :)";
        }

        /**
         * Will be executed after importing
         *
         * @return void
         */
        protected function after()
        {
            // do something after the import finished
            $entities = \App\CsvEntity::all(); // just a demo, in real life you don't want to do it ;)
            $this->initProgressBar('Something running.', $entities->count());

            $entities->each(function ($entity) {
                // do something
                $this->incrementProgress();
            });

            $this->setFinalDetails('Final details.');
        }

        /**
         *  Will be executed during the import process canceling
         */
        protected function onCancel()
        {
            \DB::rollBack();
        }
    }
```

## Basic csv aggregations ##

If a csv file is set to an import class you can `count` it, get `distinct` values from it or loop through the csv:

```php
    $importer = (new \App\CsvImporters\MyImporter())->setCsvFile('my_huge_csv_with_1000k_lines.csv');

    $quantity = $importer->countCsv(); // returns amount of csv lines without headers
    $distinctNames = $importer->distinct('name'); // returns array with distinct names

    $importer->each(function ($item) { // encoded and casted csv line
        // do something
    });
```

All methods above returns `false` if a csv file wasn't set.

## Configurations ##

There are 3 layers of configurations:

- 1) global `config/csv-importer.php` configuration file, which has default parameters applied for all csv importers
- 2) local configurations, which `csvConfigurations()` method returns, overwrites global configurations
- 3) manual configuration customization with setters, overwrites `global` and `local` configurations

1) Global configurations:

```php
        /*
        |--------------------------------------------------------------------------
        | Main csv import configurations
        |--------------------------------------------------------------------------
        |
        | `cache_driver` - keeps all progress and final information, it also allows
        |   the mutex functionality to work, there are only 3 cache drivers supported:
        |   redis, file and memcached
        |
        | `mutex_lock_time` - how long script will be executed and how long
        |   the import process will be locked, another words if we will import
        |   list of electric guitars we won't be able to run another import of electric
        |   guitars at the same time, to avoid duplicates and different sorts of
        |   incompatibilities. The value set in minutes.
        |
        | `memory_limit` - if you want store all csv values in memory or something like that,
        |   you may increase amount of memory for the script
        |
        | `encoding` - which encoding we have, UTF-8 by default
        |
        */
        'cache_driver' => env('CACHE_DRIVER', 'file'),
        
        'mutex_lock_time' => 300,

        'memory_limit' => 128,

        /*
         * An import class's short name (without namespace) by default
         */
        'mutex_lock_key' => null,

        /*
         * Encoding of given csv file
         */
        'input_encoding' => 'UTF-8',

        /*
         * Encoding of processed csv values
         */
        'output_encoding' => 'UTF-8',

        /*
         * Specify which date format the given csv file has
         * to use `date` ('Y-m-d') and `datetime` ('Y-m-d H:i:s') casters,
         * if the parameter will be set to `null` `date` caster will replace
         * `/` and `\` and `|` and `.` and `,` on `-` and will assume that
         * the given csv file has `Y-m-d` or `d-m-Y` date format
         */
        'csv_date_format' => null,

        'delimiter' => ',',

        'enclosure' => '"',

        /*
         * Warning: The library depends on PHP SplFileObject class.
         * Since this class exhibits a reported bug (https://bugs.php.net/bug.php?id=55413),
         * Data using the escape character are correctly
         * escaped but the escape character is not removed from the CSV content.
         */
        'escape' => '\\',

        'newline' => "\n",

        /*
        |--------------------------------------------------------------------------
        | Progress bar messages
        |--------------------------------------------------------------------------
        */

        'does_not_running' => 'Import process does not run',
        'initialization'   => 'Initialization',
        'progress'         => 'Import process is running',
        'final_stage'      => 'Final stage',
        'finished'         => 'Almost done, please click to the `finish` button to proceed',
        'final'            => 'The import process successfully finished!'
```

2) Local configurations:

```php
    <?php

    namespace App\CsvImporters;

    use RGilyov\CsvImporter\BaseCsvImporter;

    class MyImporter extends BaseCsvImporter
    {
        /**
         *  Specify mappings and rules for the csv importer, you also may create csv files to write csv entities
         *  and overwrite global configurations
         *
         * @return array
         */
        public function csvConfigurations()
        {
            return [
                'mappings' => [//...],
                'csv_files' => [//...],
                'config' => [
                    'mutex_lock_time' => 500,
                    'memory_limit' => 256,
                    'mutex_lock_key' => 'my-key',
                    'input_encoding' => 'cp1252',
                    'output_encoding' => 'UTF-8',
                    'csv_date_format' => 'm/d/Y',
                    'delimiter' => ';',
                    'enclosure' => '\'',
                    'escape' => '\\',
                    'newline' => "\n",

                    /*
                    |--------------------------------------------------------------------------
                    | Progress bar messages
                    |--------------------------------------------------------------------------
                    */

                    'does_not_running' => 'Something does not run',
                    'initialization'   => 'Init',
                    'progress'         => 'Something running',
                    'final_stage'      => 'After the import had finished',
                    'finished'         => 'Please click to the `finish` button to proceed',
                    'final'            => 'Something successfully finished!'
                ]
            ];
        }
    }
```

3) Configurations with setters:

```php
    (new \App\CsvImporters\MyImporter())
                ->setCsvFile('my_huge_csv_with_1000k_lines.csv')
                ->setCsvDateFormat('y-m-d')
                ->setDelimiter('d')
                ->setEnclosure('e')
                ->setEscape("x")
                ->setInputEncoding('cp1252')
                ->setOutputEncoding('UTF-8')
                ->setNewline('newline')
                ->run();
```

## From csv headers to defined mappings and reverse array transformation ##

Sounds awful, better just show how it works:

Suppose we have a csv file with this structure:

```
name,some_weird_header
John,some_weird_data
```

And we make an import class and define `mappings`, in this case we interested only in `name` field(header):

```php
    class GuitarsCsvImporter extends BaseCsvImporter
    {
        /**
         *  Specify mappings and rules for the csv importer, you also may create csv files to write csv entities
         *  and overwrite global configurations
         *
         * @return array
         */
        public function csvConfigurations()
        {
            return [
                'mappings' => [
                    'name' => [] // <- defined mappings, we only need data from this column
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
            /*
                $item contains ['name' => 'John', 'some_weird_header' => 'some_weird_data']
                so the $item will have all columns inside, so we need extract only columns we need, which was defined
                inside csv configurations mappings array
            */

            $dataOnlyFromDefinedFields = $this->extractDefinedFields($item); // will return ['name' => 'John']

            /*
                Assume we need to do some manipulations with the $item
                array and then write it to the `valid_entities.csv`
                we need to make sure that data inside formatted array are
                match headers inside the csv, we can do it with this `toCsvHeaders($item)` method:
            */

            // will return ['name' => 'John', 'some_weird_header' => null]
            $csvHeadersData = $this->toCsvHeaders($dataOnlyFromDefinedFields);

            $this->insertTo('valid_entities', $csvHeadersData);
        }
    }
```

## Mutex key concatenation ##

There are cases when you need to be able to run several similar import processes at the same time, for example you have
`guitars` and `guitar_companies` tables in your db and two csv files `ltd_guitars.csv` and `black_machine_guitars.csv`
and you use the same import class for both csvs, but since import process is locked you not able to import both at the
same time, in this case use mutex key concatenation, to have different mutex key for each `guitar company`:

```php
    class GuitarsCsvImporter extends BaseCsvImporter
    {
        //..

        protected $guitarCompany;

        public function setCompany(\App\GuitarCompany $guitarCompany)
        {
            $this->guitarCompany = $guitarCompany;
            $this->concatMutexKey($guitarCompany->id);

            return $this;
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
            \App\Guitars::create(
                array_merge(['guitar_company_id' => $this->guitarCompany->id], $this->extractDefinedFields($item))
            );
        }
    }
```

Now you can run the importer for each company in the same time. But not for the same company.

## Custom filters ##

As mentioned above in the `Basic usage` chapter, the csv importer has 3 types of filters, which you can specify for each
csv field(header), but some times you need to do something more sophisticated, for example:

- if a given csv has field(header) `A` `OR` field(header) `B` and if both are missing throw headers validation error,
  in this case parameter `required` will be insufficient for the task due to the csv import will check each field(header)
  which has the parameter which is `AND` logic, in this case you need to create `headers filter`.

- Or for example you need to make white list check for all values in a particular field(header), which is new
  `validation rule(filter)`.

- Or you may want to perform some advanced transformation for csv values, in this situation you will need to create
  `cast filter`.

## Custom headers filters ##

To make custom headers filter you need to make a class which will extends `\RGilyov\CsvImporter\BaseHeadersFilter`
or just run `php artisan make:csv-importer-headers-filter MyHeadersFilter` which will make `MyHeadersFilter.php`
file with basic set up inside `app/CsvImporters/HeadersFilters/` folder:

```php
    <?php

    namespace App\CsvImporters\HeadersFilters;

    use RGilyov\CsvImporter\BaseHeadersFilter;

    class MyHeadersFilter extends BaseHeadersFilter
    {
        /**
         * Specify error message
         *
         * @var string
         */
        public $errorMessage = 'The csv must contain either `name` field either `first_name` and `last_name` fields';

        /**
         * @param array $csvHeaders
         * @return bool
         */
        public function filter(array $csvHeaders)
        {
            if (isset($csvHeaders['name']) || (isset($csvHeaders['first_name']) && isset($csvHeaders['last_name']))) {
                return true;
            }

            return false;
        }
    }
```
The filter has property `errorMessage` where you can specify error message which will be thrown if the method `filter`
will return `false`, after start of an import process. You may also specify name of the filter to return it, or check
if it exists, or unset it and etc, to do so you need to specify `protected $name` property and set whatever name you
want, short name of the class will be by default, in this case `MyHeadersFilter`

To register your new header filter for a importer you need to use `addHeadersFilter()` static function:

```php
    \App\CsvImporters\MyImporter::addHeadersFilter(new \App\CsvImporters\HeadersFilters\MyHeadersFilter());

    // you may also use closure, you can specify name by passing second argument, otherwise it will be called `filter`
    \App\CsvImporters\MyImporter::addHeadersFilter(function ($csvHeaders) {
        if (isset($csvHeaders['A']) || isset($csvHeaders['B'])) {
            return true;
        }

        return false;
    }, 'ab-filter');

    // you may add multiple filters with one method
    $ABFilter = function ($csvHeaders) {
                    if (isset($csvHeaders['A']) || isset($csvHeaders['B'])) {
                        return true;
                    }

                    return false;
                }

    $myHeadersFilter = new \App\CsvImporters\HeadersFilters\MyHeadersFilter();

    \App\CsvImporters\MyImporter::addHeadersFilters($ABFilter, $myHeadersFilter);
```

Of course you can flush, get, unset and check filters during an import process:

```php
    \App\CsvImporters\MyImporter::headersFilterExists('MyHeadersFilter'); // will return `true`
    \App\CsvImporters\MyImporter::getHeadersFilter('MyHeadersFilter'); // will return the filter object
    \App\CsvImporters\MyImporter::getHeadersFilters(); // will return array with all filter objects
    \App\CsvImporters\MyImporter::unsetHeadersFilter('MyHeadersFilter'); // will return `true`
    \App\CsvImporters\MyImporter::flushHeadersFilters(); // will return empty array

    // example case
    if ($request->get('without_filters')) {
        \App\CsvImporters\MyImporter::flushHeadersFilters();
    }
```

## Custom validation filters ##

To make custom validation filter you need to make a class which will extends `\RGilyov\CsvImporter\BaseValidationFilter`
or just run `php artisan make:csv-importer-validation-filter MyValidationFilter` which will make
`MyValidationFilter.php` file with basic set up inside `app/CsvImporters/ValidationFilters/` folder:

```php
    <?php

    namespace App\CsvImporters\ValidationFilters;

    use RGilyov\CsvImporter\BaseValidationFilter;

    class MyValidationFilter extends BaseValidationFilter
    {
        /**
         * @var string
         */
        protected $name = 'bad_word_validation';

        /**
         * @param mixed $value
         * @return bool
         */
        public function filter($value)
        {
            if (strpos($value, 'bad_word') !== false) {
                return false;
            }

            return true;
        }
    }
```

If for headers filters property `name` not so important, for validation filters it's quit useful to have specified
because to use it, we will need to set it inside `validation` property which contains in `csvConfigurations()` method
for a field(header): `'name' => ['validation' => 'bad_word_validation']`

But there are cases when you'd like to make a global sort of speak validation filter, to be able validate whole csv
entity array, for example a csv line is valid only if it has not empty user name or not empty user first name and last
name, to get array with all csv columns instead of just a value you need to set `public $global = true`,
of course no need to specify such validation filter inside an import class csv configurations

```php
    <?php

    namespace App\CsvImporters\ValidationFilters;

    use RGilyov\CsvImporter\BaseValidationFilter;

    class MyGlobalValidationFilter extends BaseValidationFilter
    {
        /**
         * @var string
         */
        protected $name = 'global_validation';

        /**
         * @var string
         */
        public $global = true;

        /**
         * @param mixed $value
         * @return bool
         */
        public function filter($value) // we will get array here, coz $this->global set to `true`
        {
            if (empty($value['name']) || (empty($value['first_name']) && empty($value['last_name']))) {
                return false;
            }

            return true;
        }
    }
```

All filters manipulation is similar to what was described in `Custom headers filters` chapter:

```php
    \App\CsvImporters\MyImporter::addValidationFilter(new \App\CsvImporters\ValidationFilters\MyValidationFilter());

    // closure validation filters are global
    \App\CsvImporters\MyImporter::addValidationFilter(function ($item) {
        if (!empty($csvHeaders['A']) || !empty($csvHeaders['B'])) {
            return true;
        }

        return false;
    }, 'not-empty');

    // you may add multiple filters with one method
    $notEmpty = function ($item) {
                    if (!empty($item['A']) || !empty($item['B'])) {
                        return true;
                    }

                    return false;
                }

    $myValidationFilter       = new \App\CsvImporters\ValidationFilters\MyValidationFilter();
    $myGlobalValidationFilter = new \App\CsvImporters\ValidationFilters\MyGlobalValidationFilter();

    \App\CsvImporters\MyImporter::addValidationFilters($notEmpty, $myValidationFilter, $myGlobalValidationFilter);

    /////////////////////////////////////////////////////////////////////////////

    \App\CsvImporters\MyImporter::validationFilterExists('bad_word_validation'); // will return `true`
    \App\CsvImporters\MyImporter::getValidationFilter('global_validation'); // will return instance of MyGlobalValidationFilter class
    \App\CsvImporters\MyImporter::getValidationFilters(); // will return array with all filter objects
    \App\CsvImporters\MyImporter::unsetValidationFilter('bad_word_validation'); // will return `true`
    \App\CsvImporters\MyImporter::flushValidationFilters(); // will return empty array

    // example case
    if ($request->get('without_filters')) {
        \App\CsvImporters\MyImporter::flushValidationFilters();
    }
```

```
    WARNING!!!
    All closure validation filters are global.
```

```
    WARNING!!!
    If you will set not related validation rule(filter) for a field(header) and not specify and register
    a custom validation filter for that the RGilyov\CsvImporter\Exceptions\ImportValidationException will be thrown
```

## Custom cast filters ##

To make custom cast filter you need to make a class which will extends `\RGilyov\CsvImporter\BaseCastFilter`
or just run `php artisan make:csv-importer-cast-filter MyCastFilter` which will make `MyCastFilter.php`
file with basic set up inside `app/CsvImporters/CastFilters/` folder:

```php
    <?php

    namespace App\CsvImporters\CastFilters;

    use RGilyov\CsvImporter\BaseCastFilter;

    class MyCastFilter extends BaseCastFilter
    {
        protected $name = 'lowercase';

        /**
         * @param $value
         * @return mixed
         */
        public function filter($value)
        {
            return strtolower($value);
        }
    }
```

`name` as important as for validation filters, due to we will set it inside `csvConfigurations()` mappings, for a field
(header): `'field' => ['cast' => 'lowercase']`

All filters manipulation is similar to what was described in `Custom headers filters` and `Custom validation filters`
chapters:

```php
    \App\CsvImporters\MyImporter::addCastFilter(new \App\CsvImporters\CastFilters\MyCastFilter());

    \App\CsvImporters\MyImporter::addCastFilter(function ($value) {
        return htmlspecialchars($value);
    });

    $htmlentities = function ($value) {
                    return htmlentities($value);
                }

    $myCastFilter = new \App\CsvImporters\CastFilters\MyCastFilter();

    \App\CsvImporters\MyImporter::addCastFilters($htmlentities, $myCastFilter);

    /////////////////////////////////////////////////////////////////////////////

    \App\CsvImporters\MyImporter::castFilterExists('lowercase'); // will return `true`
    \App\CsvImporters\MyImporter::getCastFilter('lowercase'); // will return instance of MyCastFilter class
    \App\CsvImporters\MyImporter::getCastFilters(); // will return array with all filter objects
    \App\CsvImporters\MyImporter::unsetCastFilter('lowercase'); // will return `true`
    \App\CsvImporters\MyImporter::flushCastFilters(); // will return empty array

    // example case
    if ($request->get('without_filters')) {
        \App\CsvImporters\MyImporter::flushCastFilters();
    }
```

## The best way to register your custom filters ##

I think the best way to register custom filters is to use a service provider: https://laravel.com/docs/5.4/providers
