<?php

namespace App\CsvImporter;

use App\CsvImporter\Exceptions\CsvImporterException;
use \Carbon\Carbon;
use Illuminate\Support\Collection;
use League\Csv\Writer;
use League\Csv\Reader;
use NinjaMutex\Lock\PredisRedisLock;
use NinjaMutex\Mutex;
use RGilyov\CsvImporter\CsvImporterConfigurationTrait;
use Symfony\Component\HttpKernel\Exception\HttpException;
use \Predis\Client as PredisClient;

abstract class BaseCsvImporter
{
    use CsvImporterConfigurationTrait;

    /**
     *  If a filed has important value then data will be validated before handle method
     *  so if a field are empty then an item won't be handled, and invalid method will be executed,
     *  no errors will be displayed
     *
     * @var string
     */
    const IMPORTANT = 'important';

    /**
     * If a field has required value and the field won't be in the csv headers, an error will be shown
     *
     * @var string
     */
    const REQUIRED = 'required';

    /**
     * If a field has cast key the name of function in the value will be executed on the field
     *
     * @var string
     */
    const CAST = 'cast';

    /**
     * Csv importer base configurations, from /config/csv-importer.php
     *
     * @var array
     */
    protected $baseConfig;

    /**
     * Csv mapping and rules
     *
     * @var array
     */
    protected $config;

    /**
     * Path string or SplPathInfo
     *
     * @var mixed
     */
    protected $csvFile = null;

    /**
     * @var Reader
     */
    protected $csvReader;

    /**
     * @var Collection|Writer
     */
    protected $csvWriters;

    /**
     * @var string
     */
    protected $delimiter = ',';

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var Mutex
     */
    protected $mutex;

    /**
     * @var int
     */
    protected $mutexLockTime = 300;

    /**
     * @var \Cache
     */
    protected $cache;

    /**
     * @var array
     */
    protected $errors = [
        'quantity' => 0
    ];

    /**
     * @var string
     */
    protected $progressCacheKey;

    /**
     * @var string
     */
    protected $fileEncoding;

    /**
     * @var string
     */
    protected $ourEncoding;

    /**
     * @var string
     */
    protected $progressMessageKey;

    /**
     * @var string
     */
    protected $progressCancelKey;

    /**
     * @var string
     */
    protected $progressDetailsKey;

    /**
     * @var string
     */
    protected $csvCountCacheKey;

    /**
     * @var string
     */
    protected $importLockKey;

    /**
     * @var string
     */
    protected $importPathsKey;

    /**
     * @var string
     */
    protected $progressFinishedKey;

    /**
     * @var array
     */
    protected $artifacts = [];

    /**
     * @var bool
     */
    protected $csvDateFormat = false;

    /**
     * BaseCsvImporter constructor.
     */
    public function __construct()
    {
        $this->baseConfig    = $this->getBaseConfig();

        $this->mutexLockTime = $this->getConfigProperty('mutex_lock_time', 300, 'integer');
        $this->artifacts     = $this->getConfigProperty('artifacts', [], 'array');
        $this->importLockKey = $this->getConfigProperty('import_lock_key', static::class, 'string');
        $this->ourEncoding   = $this->getConfigProperty('encoding', 'UTF-8', 'string');
        $this->fileEncoding  = $this->getConfigProperty('encoding', 'UTF-8', 'string');

        $this->config        = $this->getConfig();
        $this->csvWriters    = collect([]);
        $this->cache         = app()['cache.store'];

        $this->setKeys();
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration methods
    |--------------------------------------------------------------------------
    */

    /**
     * @param $property
     * @param $default
     * @param bool $cast
     * @return mixed
     */
    protected function getConfigProperty($property, $default, $cast = null)
    {
        if (isset($this->baseConfig[$property]) && $this->baseConfig[$property]) {
            return (is_string($cast)) ? $this->castField($this->baseConfig[$property], $cast) : $this->baseConfig[$property];
        } else {
            return $default;
        }
    }

    /**
     * @return void
     */
    protected function systemSettings()
    {
        /*
         * Make sure that import will run even if a user will close the import page
         */
        ignore_user_abort(true);

        /*
         * Make sure we have enough memory for the import
         */
        ini_set('memory_limit', $this->getConfigProperty('memory_limit', 256, 'integer') . 'M');

        /*
         * Make sure the script will run as long as we set mutex lock time
         */
        ini_set('max_execution_time', $this->mutexLockTime * 60);
    }

    /**
     * We need to always reset keys after mutes key concatenation or key changes
     *
     * @return void
     */
    protected function setKeys()
    {
        $this->importPathsKey               = $this->importLockKey . '_paths';
        $this->csvCountCacheKey             = $this->importLockKey . '_quantity';
        $this->progressCacheKey             = $this->importLockKey . '_processed';
        $this->progressMessageKey           = $this->importLockKey . '_message';
        $this->progressCancelKey            = $this->importLockKey . '_cancel';
        $this->progressDetailsKey           = $this->importLockKey . '_details';
        $this->progressFinishedKey          = $this->importLockKey . '_finished';
        $this->setMutex();
    }

    /*
    |--------------------------------------------------------------------------
    | Methods for import customization
    |--------------------------------------------------------------------------
    */

    /**
     * We should specify csv mappings and rules here
     *
     * @return array
     */
    abstract public function getConfig();

    /**
     * If a csv line are valid, the method will be executed on it
     *
     * @param $item
     * @return array
     */
    abstract public function handle($item);

    /**
     * If a `important` filter will fail for a csv line, the method will be executed on the line
     *
     * @param $item
     * @return array
     */
    abstract public function invalid($item);

    /**
     * Will be executed before importing, useful to check mappings
     *
     * @return void
     */
    public function before()
    {

    }

    /**
     * Will be executed after importing, useful to check mappings
     *
     * @return void
     */
    public function after()
    {

    }

    /**
     * You may specify custom progress message during import process
     *
     * @param string $message
     */
    public function setProgressMessage($message)
    {
        $this->cache->put($this->progressMessageKey, $message, $this->mutexLockTime);
    }

    /**
     * You may change mutex key with concatenation,
     * important when you have multiple imports for one import class at the same time
     *
     * @param $concat
     * @return void
     */
    public function concatMutexKey($concat)
    {
        $this->importLockKey = $this->importLockKey . $concat;
        $this->setKeys();
    }

    /**
     * You may drop progress bar
     *
     * @return void
     */
    public function dropProgress()
    {
        $this->cache->put($this->progressCacheKey, 0, $this->mutexLockTime);
    }

    /**
     * You may re initialize progress bar
     *
     * @param $message
     * @param $quantity
     */
    public function initProgressBar($message, $quantity)
    {
        $this->dropProgress();
        $this->setProgressMessage($message);
        $this->setProgressQuantity($quantity);
    }

    /**
     *  You may adjust additional information to progress bar during import process
     *
     * @return null|string|array
     */
    public function progressBarDetails()
    {
        return null;
    }

    /**
     * You may also set final details to your importer, which a user will see at the end of import process
     *
     * @param $details
     */
    public function setFinishDetails($details)
    {
        $this->cache->forever($this->progressDetailsKey, $details);
    }

    /**
     *  Will be executed when current import process will be canceled
     */
    public function onCancel()
    {

    }

    /**
     * You may to transform any array to given csv headers
     *
     * @param array $data
     * @return array
     */
    public function toCsvData(array $data)
    {
        $csvData = [];
        foreach ($this->headers as $value) {
            $csvData[$value] = (isset($data[$value])) ? $data[$value] : '';
        }

        return $csvData;
    }

    /*
    |--------------------------------------------------------------------------
    | Setters
    |--------------------------------------------------------------------------
    */

    /**
     * @param string|\SplFileInfo $file
     * @return static
     */
    public function setFile($file)
    {
        $this->csvFile = $file;
        $this->setReader();

        return $this;
    }

    /**
     * `,` by default
     *
     * @param string $delimiter
     * @return $this
     */
    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * You may specify encoding of your file, UTF-8 by default
     *
     * @param string $encoding
     * @return $this
     */
    public function setFileEncoding($encoding)
    {
        $this->fileEncoding = $encoding;

        return $this;
    }

    /**
     * You may specify date format that contains your csv file, dd-mm-yyyy by default
     *
     * @param $format
     * @return $this
     */
    public function setCsvDateFormat($format)
    {
        $this->csvDateFormat = $format;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Basic csv manipulation methods
    |--------------------------------------------------------------------------
    */

    /**
     * @param \Closure $callable
     * @return bool
     */
    public function each(\Closure $callable)
    {
        if (!$this->exists()) {
            return false;
        }

        foreach ($this->csvReader->setOffset(1)->fetchAssoc($this->headers) as $item) {
            $callable($this->castFields($this->checkEncoding($item)));
        }

        return true;
    }

    /**
     * @param string $property
     * @return Collection
     */
    public function distinct($property)
    {
        if (!$this->exists()) {
            return false;
        }

        $distinct = collect([]);

        $this->each(function ($item) use ($distinct, $property) {
            $value = ($item[$property]) ? $item[$property] : false;
            if (false !== $value && !$distinct->offsetExists($value)) {
                $distinct->put($value, true);
            }
        });

        return $distinct->keys();
    }

    /**
     * @return int
     */
    public function countCsv()
    {
        if (!$this->exists()) {
            return false;
        }

        $quantity = $this->csvReader->each(function () {
            return true;
        });

        return $quantity;
    }

    /**
     * @return bool
     */
    public function exists()
    {
        return ( bool )$this->csvFile;
    }

    /**
     * Check if import process is finished
     *
     * @return bool
     */
    public function isFinished()
    {
        return ( bool )$this->cache->get($this->progressFinishedKey);
    }

    /*
    |--------------------------------------------------------------------------
    | Api methods
    |--------------------------------------------------------------------------
    */

    /**
     * Run import
     *
     * @return array
     */
    public function run()
    {
        return $this->tryStart();
    }

    /**
     * Finish import, unlock mutex and get final information
     *
     * @return array
     */
    public function finish()
    {
        if (!$this->isFinished()) {
            return $this->progressBar();
        }

        /*
         * We need to get data before mutex unlock and session cleaning
         */
        $data = $this->finishProgressDetails();

        $this->unlock();

        return $data;
    }

    /**
     * You can cancel your import process in any time
     *
     * @return void
     */
    public function cancel()
    {
        $this->cache->put($this->progressCancelKey, true, $this->mutexLockTime);
    }

    /**
     * Get progress bar
     *
     * @return array
     */
    public function getProgress()
    {
        return $this->progressBar();
    }

    /*
    |--------------------------------------------------------------------------
    | Main functionality
    |--------------------------------------------------------------------------
    */

    /**
     * @return array
     */
    protected function tryStart()
    {
        if (!$this->exists()) {
            return false;
        }

        if (!$this->isLocked() || !$this->getProgressDetails()->finished) {
            $this->lock();

            $this->initialize();
            $this->process();
            $this->finalStage();

            $this->setAsFinished();
            $this->mutex->releaseLock();
        }

        return $this->progressBar();
    }

    /**
     * @return void
     */
    protected function initialize()
    {
        $this->isCanceled();

        $this->systemSettings();
        $this->setWriters();
        $this->validateHeaders();
        $this->checkHeadersDuplicates();

        $this->hasErrors();
        $this->isCanceled();

        $this->before();
        $this->initProgressBar(
            $this->getConfigProperty('progress', 'Import process is running', 'string'),
            $this->countCsv()
        );

        $this->hasErrors();
        $this->isCanceled();
    }

    /**
     * @return void
     */
    protected function finalStage()
    {
        $this->isCanceled();
        $this->after();
        $this->hasErrors();
    }

    /**
     * @return void
     */
    public function clearSession()
    {
        $this->cache->forget($this->progressCacheKey);
        $this->cache->forget($this->csvCountCacheKey);
        $this->cache->forget($this->progressMessageKey);
        $this->cache->forget($this->progressDetailsKey);
        $this->cache->forget($this->progressCancelKey);
        $this->cache->forget($this->progressFinishedKey);
        $this->cache->forget($this->importPathsKey);
    }

    /**
     * @return void
     */
    protected function process()
    {
        \DB::transaction(function () {
            $this->each(function ($item) {
                $this->isCanceled();
                ($this->validateItem($item)) ? $this->handle($item) : $this->invalid($item);
                $this->incrementProgress();
            });
        });
    }

    /**
     * @param array $item
     * @return array
     */
    protected function checkEncoding(array $item)
    {
        $fileEncoding    = strtolower($this->fileEncoding);
        $defaultEncoding = strtolower($this->ourEncoding);

        foreach ($item as $key => &$value) {
            if (is_string($value) && !is_numeric($value)) {
                if ($defaultEncoding != $fileEncoding) {
                    $item[$key] = $this->trimArtifacts(iconv($this->fileEncoding, $this->ourEncoding, $value));
                } else {
                    $item[$key] = $this->trimArtifacts($value);
                }
            }
        }

        return $item;
    }

    /**
     * @param $value
     * @return string
     */
    protected function trimArtifacts($value)
    {
        return trim(urldecode(preg_replace('/(' . implode('|', $this->artifacts) . ')/', ' ', urlencode($value))));
    }

    /**
     * @return void
     */
    protected function setReader()
    {
        $this->csvReader = Reader::createFromPath($this->csvFile)->setDelimiter($this->delimiter);
        $this->headers   = array_map('strtolower', $this->csvReader->fetchOne());
    }

    /**
     * @return void
     */
    protected function setWriters()
    {
        if (isset($this->config['csv_paths'])) {
            $paths = [];
            foreach ($this->config['csv_paths'] as $key => $csvPath) {
                if (\Storage::exists($csvPath)) {
                    $now     = Carbon::now();
                    $time    = "_" . $now->hour . "_" . $now->minute . "_" . $now->second . "_.";
                    $csvPath = str_replace(".", $time, $csvPath);
                }

                \Storage::put($csvPath, '');

                $this->csvWriters->put(
                    $key,
                    Writer::createFromPath($csvPath)
                        ->setDelimiter($this->delimiter)
                        ->insertOne(implode($this->delimiter, $this->headers))
                );

                $paths[$key] = $csvPath;
            }

            $this->cache->forever($this->importPathsKey, $paths);
        }
    }

    /**
     * Inside the `invalid` or `handle` methods you may get fields that was specified in the configurations
     *
     * @param array $item
     * @return array
     */
    protected function getDefinedFields(array $item)
    {
        $configMappings = [];
        foreach ($this->config['mappings'] as $key => $value) {
            if (isset($item[$key])) {
                $configMappings[$key] = $item[$key];
            }
        }

        return $configMappings;
    }

    /**
     * @throws HttpException
     */
    protected function isCanceled()
    {
        if ($this->cache->get($this->progressCancelKey)) {
            $this->onCancel();
            $this->unlock();
            throw new HttpException(200, 'Importing has been canceled');
        }
    }

    /**
     * @param array $item
     * @return array
     * @throws CsvImporterException
     */
    protected function castFields(array $item)
    {
        foreach ($this->config['mappings'] as $field => $rules) {
            if (isset($rules[self::CAST]) && isset($item[$field])) {
                if (method_exists($this, $rules[self::CAST])) {
                    $item[$field] = $this->{$rules[self::CAST]}($item[$field]);
                } else {
                    $item[$field] = $this->castField($item[$field], $rules[self::CAST]);
                }
            }
        }

        return $item;
    }

    /**
     * Cast a field to a native PHP type.
     *
     * @param  string  $type
     * @param  mixed  $value
     * @return mixed
     */
    protected function castField($value, $type)
    {
        if (is_null($value)) {
            return $value;
        }

        switch ($type) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'date':
            case 'datetime':
                return $this->toDate($value);
            case 'array':
                return (array)$value;
            default:
                return $value;
        }
    }

    /**
     * @param $date
     * @return string
     */
    public function toDate($date)
    {
        if ($this->csvDateFormat) {
            return $this->withDateFormat($date);
        }

        return $this->withoutDateFormat($date);
    }

    /**
     * @param $date
     * @return string
     */
    protected function withDateFormat($date)
    {
        try {
            return Carbon::createFromFormat($this->csvDateFormat, $date)->toDateString();
        } catch (\Exception $e) {
            return '0000-00-00';
        }
    }

    /**
     * @param $date
     * @return string
     */
    protected function withoutDateFormat($date)
    {
        $date = trim(preg_replace('/(\/|\\\|\||\.|\,)/', '-', $date));

        $zeroDate = '0000-00-00';

        if (!$date || ($date == $zeroDate)) {
            return $zeroDate;
        }

        try {
            $formattedDate = Carbon::parse($date)->toDateString();
        } catch (\Exception $e) {
            $formattedDate = $zeroDate;
        }

        return $formattedDate;
    }

    /**
     * @return array
     */
    protected function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param array $item
     * @return bool
     */
    protected function validateItem(array $item)
    {
        foreach ($this->getFieldsWithRules(self::IMPORTANT) as $field) {
            if (isset($item[$field])) {
                if (null === $item[$field] || '' === $item[$field]) {
                    return false;
                }
            }
        }

        return $this->executeImportantFilters($item);
    }

    /**
     * @param array $item
     * @return bool
     */
    protected function executeImportantFilters(array $item)
    {
        if (isset($this->config['important_filters'])) {
            foreach ($this->config['important_filters'] as $filter) {
                if (method_exists($this, $filter)) {
                    return $this->{$filter}($item);
                }
            }
        }
        return true;
    }

    /**
     * @throws CsvImporterException
     */
    protected function validateHeaders()
    {
        foreach ($this->getFieldsWithRules(self::REQUIRED) as $field) {
            if (array_search($field, $this->headers) === false) {
                $this->setError('Required fields not found:', 'The "' . $field . '" field is required');
            }
        }

        $this->executeRequiredFilters();
    }

    /**
     * @return void
     */
    protected function executeRequiredFilters()
    {
        if (isset($this->config['required_filters'])) {
            foreach ($this->config['required_filters'] as $filter) {
                if (method_exists($this, $filter)) {
                    $result = $this->{$filter}($this->headers);
                    if ($result->error) {
                        $this->setError('Required fields errors:', $result->message);
                    }
                }
            }
        }
    }

    /**
     * @return void
     */
    protected function checkHeadersDuplicates()
    {
        $duplicates = array_diff_assoc($this->headers, array_unique($this->headers));

        if (!empty($duplicates)) {
            foreach ($duplicates as $value) {
                $this->setError('Duplicated values:', 'Csv headers has duplicated fields "' . $value . '"');
            }
        }
    }

    /**
     * @param string $error
     * @param string $message
     */
    protected function setError($error, $message)
    {
        $this->errors[$error][] = $message;
        $this->errors['quantity'] += 1;
    }

    /**
     * @throws CsvImporterException
     */
    protected function hasErrors()
    {
        if (0 !== $this->errors['quantity']) {
            $this->unlock();
            throw new CsvImporterException($this->getErrors());
        }
    }

    /**
     * @param string $rule
     * @return array
     */
    protected function getFieldsWithRules($rule)
    {
        $fieldsWithRules = [];
        foreach ($this->config['mappings'] as $field => $rules) {
            if (array_search($rule, $rules) !== false) {
                $fieldsWithRules[] = $field;
            }
        }

        return $fieldsWithRules;
    }

    /*
    |--------------------------------------------------------------------------
    | Mutex functionality
    |--------------------------------------------------------------------------
    */

    /**
     * @return void
     */
    protected function setMutex()
    {
        $cacheClient = $this->cache->connection();

        if ($cacheClient instanceof PredisClient) {
            $this->mutex = new Mutex($this->importLockKey, new PredisRedisLock($cacheClient));
        }
    }

    /**
     * @return bool
     */
    public function lock()
    {
        return $this->mutex->acquireLock($this->mutexLockTime);
    }

    /**
     * @return bool
     */
    public function unlock()
    {
        $this->clearSession();
        return $this->mutex->releaseLock();
    }

    /**
     * @return bool
     */
    public function isLocked()
    {
        return $this->mutex->isLocked();
    }

    /*
    |--------------------------------------------------------------------------
    | Progress bar functionality
    |--------------------------------------------------------------------------
    */

    /**
     * @param $quantity
     */
    protected function setProgressQuantity($quantity)
    {
        $this->cache->put($this->csvCountCacheKey, $quantity, $this->mutexLockTime);
    }

    /**
     * @return void
     */
    protected function incrementProgress()
    {
        $this->cache->increment($this->progressCacheKey);
    }

    /**
     * return void
     */
    protected function setAsFinished()
    {
        $this->cache->forever($this->progressFinishedKey, true);
    }

    /**
     * @return array
     */
    protected function progressBar()
    {
        $progress = $this->getProgressDetails();

        if ($progress->finished) {
            return [
                'data' => ["message"  => $this->getConfigProperty('finished', "Successfully finished", 'string')],
                'meta' => ["finished" => true, 'init' => false, 'running' => false]
            ];
        } elseif (!$progress->quantity && !$this->isLocked()) {
            return [
                'data' => [
                    "message"  => $this->getConfigProperty('does_not_running', "Import process doesn't run", 'string')
                ],
                'meta' => ["finished" => false, 'init' => false, 'running' => false]
            ];
        } elseif (!$progress->quantity && $this->isLocked()) {
                return [
                    'data' => ["message" => $this->getConfigProperty('initialization', "Initialization", 'string')],
                    'meta' => ["finished" => false, 'init' => true, 'running' => true]
                ];
        } elseif (($progress->quantity == $progress->processed) && $this->isLocked()) {
            return [
                'data' => ["message"  => $this->getConfigProperty('final_stage', "Final stage", 'string')],
                'meta' => ["finished" => false, 'init' => false, 'running' => true]
            ];
        } else {

            $data = [
                'data' => ["message"  => $progress->message],
                'meta' => [
                    'processed'  => $progress->processed,
                    'remains'    => $progress->quantity - $progress->processed,
                    'percentage' => floor(($progress->processed / ($progress->quantity / 100))),
                    'finished'   => false,
                    'init'       => false,
                    'running'    => true
                ]
            ];

            if ($this->progressBarDetails()) {
                $data['data']['details'] = $this->progressBarDetails();
            }

            return $data;
        }
    }

    /**
     * @return array
     * @throws CsvImporterException
     */
    protected function finishProgressDetails()
    {
        $progress = $this->getProgressDetails();

        $data = [
            'data' => [
                "message"  => $this->getConfigProperty('final', 'The import process successfully finished!', 'string')
            ],
            'meta' => ["finished" => true, 'init' => false, 'running' => false]
        ];

        if ($progress->details) {
            $data['details'] = $progress->details;
        }

        if ($progress->paths) {
            $data['files'] = $progress->path;
        }

        return $data;
    }

    /**
     * @return object
     */
    protected function getProgressDetails()
    {
        return ( object ) [
            'processed'          => $this->cache->get($this->progressCacheKey),
            'quantity'           => $this->cache->get($this->csvCountCacheKey),
            'message'            => $this->cache->get($this->progressMessageKey),
            'finished'           => $this->cache->get($this->progressFinishedKey),
            'details'            => $this->cache->get($this->progressDetailsKey),
            'paths'              => $this->cache->get($this->importPathsKey),
        ];
    }
}
