<?php

namespace RGilyov\CsvImporter;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RGilyov\CsvImporter\Exceptions\CsvImporterException;
use \Carbon\Carbon;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\MemcachedStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use League\Csv\Writer;
use League\Csv\Reader;
use NinjaMutex\Lock\FlockLock;
use NinjaMutex\Lock\MemcachedLock;
use NinjaMutex\Lock\PredisRedisLock;
use \Predis\Client as PredisClient;
use NinjaMutex\Mutex;

abstract class BaseCsvImporter
{
    use CsvImporterConfigurationTrait, NameableTrait;

    /**
     * If a header has `validation` array inside configuration array
     * the value will be checked according to given validation rules
     * 
     * @var string
     */
    const VALIDATION = 'validation';

    /**
     * @var array
     */
    protected static $validationFilters = [];

    /**
     * If a header has `required` value inside configuration array
     * and the header won't be in the csv's headers, an error will be thrown
     *
     * @var string
     */
    const REQUIRED = 'required';

    /**
     * @var array
     */
    protected static $requiredFilters = [];

    /**
     * Cast given value either to native php type either to some custom format
     *
     * @var string
     */
    const CAST = 'cast';

    /**
     * @var array
     */
    protected static $castFilters = [];

    /**
     * Csv importer base configurations, from /config/csv-importer.php
     *
     * @var array
     */
    protected $baseConfig;

    /**
     * Csv mappings and rules
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
    protected $delimiter;

    /**
     * @var string
     */
    protected $enclosure;

    /**
     * @var string
     */
    protected $escape;

    /**
     * @var string
     */
    protected $newline;

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
    protected $mutexLockTime;

    /**
     * @var Cache
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
    protected $inputEncoding;

    /**
     * @var string
     */
    protected $outputEncoding;

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
    protected $progressFinalDetailsKey;

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
     * @var bool
     */
    protected $csvDateFormat;

    /**
     * BaseCsvImporter constructor.
     */
    public function __construct()
    {
        $this->baseConfig     = $this->getBaseConfig();
        $this->importLockKey  = $this->filterMutexLockKey();

        $this->mutexLockTime  = $this->getConfigProperty('mutex_lock_time', 300, 'integer');
        $this->inputEncoding  = $this->getConfigProperty('input_encoding', 'UTF-8', 'string');
        $this->outputEncoding = $this->getConfigProperty('output_encoding', 'UTF-8', 'string');

        $this->delimiter      = $this->getConfigProperty('delimiter', ',');
        $this->enclosure      = $this->getConfigProperty('enclosure', '"');
        $this->escape         = $this->getConfigProperty('escape', '\\');
        $this->newline        = $this->getConfigProperty('newline', '');
        $this->csvDateFormat  = $this->getConfigProperty('csv_date_format', null, 'string');

        $this->config         = $this->csvConfigurations();
        $this->csvWriters     = collect([]);
        $this->cache          = app()['cache.store'];

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
        if (isset($this->config['config'][$property]) && ($value = $this->config['config'][$property])) {
            return (is_string($cast)) ? $this->castField($value, $cast) : $value;
        }
        
        if (isset($this->baseConfig[$property]) && ($value = $this->baseConfig[$property])) {
            return (is_string($cast)) ? $this->castField($value, $cast) : $value;
        }

        return $default;
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
        ini_set('memory_limit', $this->getConfigProperty('memory_limit', 128, 'integer') . 'M');

        /*
         * Make sure the script will run as long as we set mutex lock time
         */
        ini_set('max_execution_time', $this->mutexLockTime * 60);
    }

    /**
     * Always reset keys after mutes key concatenation or key changes
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
        $this->progressFinalDetailsKey      = $this->importLockKey . '_final_details';
        $this->progressFinishedKey          = $this->importLockKey . '_finished';
        $this->setMutex();
    }

    /**
     * @return mixed
     */
    protected function filterMutexLockKey()
    {
        return Str::slug(($key = $this->setMutexLockKey()) ? $key : (string)($this), '_');
    }

    /**
     * @return mixed
     */
    protected function setMutexLockKey()
    {
        return static::class;
    }

    /*
    |--------------------------------------------------------------------------
    | Methods for import customization
    |--------------------------------------------------------------------------
    */

    /**
     * Specify csv mappings and rules here
     *
     * @return array
     */
    public function csvConfigurations()
    {
        return [];
    }

    /**
     * If a csv line is valid, the method will be executed on it
     *
     * @param $item
     * @return array
     */
    protected function handle($item)
    {

    }

    /**
     * If a csv line will not pass `validation` filters, the method will be executed on the line
     *
     * @param $item
     * @return array
     */
    protected function invalid($item)
    {

    }

    /**
     * Will be executed before importing
     *
     * @return void
     */
    protected function before()
    {

    }

    /**
     * Will be executed after importing
     *
     * @return void
     */
    protected function after()
    {

    }

    /**
     * You may change mutex key with concatenation,
     * useful when you have multiple imports for one import class at the same time
     *
     * @param $concat
     * @return static
     */
    public function concatMutexKey($concat)
    {
        $this->importLockKey = $this->importLockKey . '_' . $concat;

        /*
         * Important to reset all keys after concatenation due to all keys depends on `importLockKey`
         */
        $this->setKeys();
        
        return $this;
    }

    /**
     * Initialize new progress bar
     *
     * @param $message
     * @param $quantity
     */
    protected function initProgressBar($message, $quantity)
    {
        $this->dropProgress();
        $this->setProgressMessage($message);
        $this->setProgressQuantity($quantity);
    }

    /**
     *  Adjust additional information to progress bar during import process
     *
     * @return null|string|array
     */
    public function progressBarDetails()
    {
        return null;
    }

    /**
     * Set final details to your importer, which a user will see at the end of the import process
     *
     * @param $details
     */
    public function setFinalDetails($details)
    {
        $this->cache->forever($this->progressFinalDetailsKey, $details);
    }

    /**
     * @return mixed
     */
    public function getFinalDetails()
    {
        return $this->cache->get($this->progressFinalDetailsKey);
    }

    /**
     *  Will be executed during the import process canceling
     */
    protected function onCancel()
    {

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
    public function setCsvFile($file)
    {
        $this->csvFile = $file;
        $this->setReader();

        return $this;
    }

    /**
     * @param string $delimiter
     * @return $this
     */
    public function setDelimiter($delimiter)
    {
        $this->delimiter = $delimiter;
        $this->resetReader();

        return $this;
    }

    /**
     * @param string $enclosure
     * @return $this
     */
    public function setEnclosure($enclosure)
    {
        $this->enclosure = $enclosure;
        $this->resetReader();

        return $this;
    }

    /**
     * @param string $escape
     * @return $this
     */
    public function setEscape($escape)
    {
        $this->escape = $escape;
        $this->resetReader();

        return $this;
    }

    /**
     * @param string $newline
     * @return $this
     */
    public function setNewline($newline)
    {
        $this->newline = $newline;
        $this->resetReader();

        return $this;
    }

    /**
     * Specify encoding of your file, UTF-8 by default
     *
     * @param string $encoding
     * @return $this
     */
    public function setInputEncoding($encoding)
    {
        $this->inputEncoding = $encoding;

        return $this;
    }

    /**
     * Specify encoding of your file, UTF-8 by default
     *
     * @param string $encoding
     * @return $this
     */
    public function setOutputEncoding($encoding)
    {
        $this->outputEncoding = $encoding;

        return $this;
    }

    /**
     * Specify date format that contains your csv file, `Y-m-d` by default
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
    | Getter
    |--------------------------------------------------------------------------
    */

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if (strpos($name, 'get') !== false) {
            $name = str_replace('get', '', $name);
        }

        $name = Str::camel($name);

        return (property_exists($this, $name)) ? $this->{$name} : null;
    }

    /**
     * @param $name
     * @param $get
     * @return mixed
     */
    public function __call($name, $get = null)
    {
        return (isset($get[0]) && is_string($get[0])) ? $this->__get($get[0]) : $this->__get($name);
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
            $value = (isset($item[$property]) && $item[$property]) ? $item[$property] : false;
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

        /*
         * -- to exclude headers line
         */
        return --$quantity;
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
     * Check if import process is finished
     *
     * @return bool
     */
    public function isFinished()
    {
        return ( bool )$this->cache->get($this->progressFinishedKey);
    }

    /**
     * Finish import, unlock mutex and get final information
     *
     * @return array
     */
    public function finish()
    {
        if ($this->isLocked() && !$this->isFinished()) {
            return $this->progressBar();
        }

        /*
         * We need to get data before mutex unlocked and session cleared
         */
        $data = $this->finalProgressDetails();

        $this->unlock();

        return $data;
    }

    /**
     * Cancel your import process in any time
     *
     * @return bool
     */
    public function cancel()
    {
        return $this->cache->put($this->progressCancelKey, true, $this->mutexLockTime);
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

    /**
     * @return bool
     */
    public function exists()
    {
        return ( bool )$this->csvFile;
    }

    /**
     * Insert an item (csv line) to a file from `csv_files` configuration array
     *
     * @param $fileName
     * @param $item
     * @return mixed
     * @throws CsvImporterException
     */
    public function insertTo($fileName, $item)
    {
        try {
            return $this->csvWriters[$fileName]->insertOne($item);
        } catch (\Exception $e) {
            throw new CsvImporterException(
                ['message' => $fileName . ' file was not found, please check `csv_files` paths inside your configurations'],
                400
            );
        }
    }

    /**
     * Extract any array values to given csv headers
     *
     * @param array $data
     * @return array
     */
    public function toConfiguredHeaders(array $data)
    {
        if (!$this->exists()) {
            return null;
        }

        $headers = array_fill_keys($this->headers, null);

        return array_merge($headers, array_intersect_key($data, $headers));
    }
    /**
     * Extract fields which is were specified inside `mappings` array in the configurations, from the given csv line
     *
     * @param array $item
     * @return array
     */
    public function extractDefinedFields(array $item)
    {
        return ($this->configMappingsExists()) ? array_intersect_key($item, $this->config['mappings']) : [];
    }


    /*
    |--------------------------------------------------------------------------
    | Main functionality
    |--------------------------------------------------------------------------
    */

    /**
     * @return array
     */
    private function tryStart()
    {
        if (!$this->isLocked() && !$this->isFinished()) {
            if (!$this->exists()) {
                return false;
            }

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
    private function initialize()
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
            $this->getConfigProperty('progress', '', 'string'),
            $this->countCsv()
        );

        $this->hasErrors();
        $this->isCanceled();
    }

    /**
     * @return void
     */
    private function finalStage()
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
        $this->cache->forget($this->progressFinalDetailsKey);
        $this->cache->forget($this->progressCancelKey);
        $this->cache->forget($this->progressFinishedKey);
        $this->cache->forget($this->importPathsKey);
    }

    /**
     * @return void
     */
    protected function process()
    {
        $this->each(function ($item) {
            $this->isCanceled();
            ($this->validateItem($item)) ? $this->handle($item) : $this->invalid($item);
            $this->incrementProgress();
        });
    }

    /**
     * @param array $item
     * @return array
     */
    protected function checkEncoding(array $item)
    {
        if (strcasecmp($this->inputEncoding, $this->outputEncoding) !== 0) {
            foreach ($item as $key => $value) {
                if (is_string($value) && !is_numeric($value)) {
                    $item[$key] = iconv($this->inputEncoding, $this->outputEncoding, $value);
                }
            }
        }

        return $item;
    }

    /**
     * @return void
     */
    protected function setReader()
    {
        $this->csvReader = Reader::createFromPath($this->csvFile)
            ->setDelimiter($this->delimiter)
            ->setEnclosure($this->enclosure)
            ->setEscape($this->escape)
            ->setNewline($this->newline);

        $this->headers = array_map('strtolower', $this->csvReader->fetchOne());
    }

    /**
     * @return void
     */
    protected function resetReader()
    {
        if ($this->exists()) {
            $this->setReader();
        }
    }

    /**
     * @return void
     */
    protected function setWriters()
    {
        if (isset($this->config['csv_files']) && is_array($this->config['csv_files'])) {
            $paths = [];
            foreach ($this->config['csv_files'] as $csvFileKeyName => $path) {
                if (Storage::exists($path)) {
                    $time = "_" . Carbon::now()->format('Y_M_D_\a\t_H_i') . "_";
                    $path = (substr_count($path, '.') === 1) ? str_replace(".", $time.'.', $path) : ($path . $time);
                }

                Storage::put($path, '');

                $path = $this->concatenatePath(Storage::disk()->getDriver()->getAdapter()->getPathPrefix(), $path);

                $writer = Writer::createFromPath($path)
                    ->setDelimiter($this->delimiter)
                    ->setEnclosure($this->enclosure)
                    ->setEscape($this->escape)
                    ->setNewline($this->newline)
                    ->insertOne(implode($this->delimiter, $this->headers));

                $this->csvWriters->put($csvFileKeyName, $writer);

                $paths[$csvFileKeyName] = $path;
            }

            $this->cache->forever($this->importPathsKey, $paths);
        }
    }

    /**
     * @param $firstPart
     * @param $lastPart
     * @return string
     */
    protected function concatenatePath($firstPart, $lastPart)
    {
        return rtrim($firstPart, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($lastPart, DIRECTORY_SEPARATOR);
    }

    /**
     * @throws CsvImporterException
     */
    protected function isCanceled()
    {
        if ($this->cache->get($this->progressCancelKey)) {
            $this->onCancel();
            $this->unlock();
            throw new CsvImporterException(['message' => 'Importing had canceled'], 200);
        }
    }

    /**
     * @param array $item
     * @return array
     * @throws CsvImporterException
     */
    protected function castFields(array $item)
    {
        if ($this->configMappingsExists()) {
            foreach ($this->config['mappings'] as $field => $rules) {
                if (isset($rules[self::CAST]) && isset($item[$field])) {
                    
                    $castFilter = $rules[self::CAST];
                    
                    if (is_array($castFilter)) {
                        foreach ($castFilter as $cast) {
                            $item[$field] = $this->performCastOnValue($item[$field], $cast);
                        }
                    } elseif (is_string($castFilter)) {
                        $item[$field] = $this->performCastOnValue($item[$field], $castFilter);
                    }
                }
            }
        }

        return $item;
    }

    /**
     * @param $value
     * @param $cast
     * @return mixed
     */
    protected function performCastOnValue($value, $cast)
    {
        if ($filter = static::getCastFilter($cast)) {
            return $filter->filter($value);
        }

        return $this->castField($value, $cast);
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
                return $this->toDate($value);
            case 'datetime':
            case 'date_time':
                return $this->toDateTime($value);
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
    public function toDateTime($date)
    {
        return $this->formatDate($date)->toDateTimeString();
    }

    /**
     * @param $date
     * @return string
     */
    public function toDate($date)
    {
        return $this->formatDate($date)->toDateString();
    }

    /**
     * @param $date
     * @return Carbon
     */
    public function formatDate($date)
    {
        return ($this->csvDateFormat) ? $this->withDateFormat($date) : $this->withoutDateFormat($date);
    }

    /**
     * @param $date
     * @return Carbon
     */
    protected function withDateFormat($date)
    {
        try {
            return Carbon::createFromFormat($this->csvDateFormat, $date);
        } catch (\Exception $e) {
            return $this->dummyCarbonDate();
        }
    }

    /**
     * @param $date
     * @return Carbon
     */
    protected function withoutDateFormat($date)
    {
        try {
            return Carbon::parse(trim(preg_replace('/(\/|\\\|\||\.|\,)/', '-', $date)));
        } catch (\Exception $e) {
            return $this->dummyCarbonDate();
        }
    }

    /**
     * @return Carbon
     */
    protected function dummyCarbonDate()
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', '0001-01-01 00:00:00');
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param array $item
     * @return bool
     */
    protected function validateItem(array $item)
    {
        if ($this->configMappingsExists()) {
            $validationRules = [];

            foreach ($this->config['mappings'] as $field => $rules) {
                if (isset($rules[self::VALIDATION]) && isset($item[$field])) {
                    $validationRules[$field] = $rules[self::VALIDATION];
                }
            }

            if (!empty($validationRules) && !$this->passes($item, $validationRules)) {
                return false;
            }
        }

        return $this->executeValidationFilters($item);
    }

    /**
     * @param array $item
     * @param array $validationRules
     * @return bool
     */
    protected function passes(array $item, array $validationRules)
    {
        return Validator::make($item, $validationRules)->passes();
    }
    

    /**
     * @param array $item
     * @return bool
     */
    protected function executeValidationFilters(array $item)
    {
        foreach (static::getValidationFilters() as $filter) {
            if ($filter instanceof BaseValidationFilter) {
                if (!$filter->filter($item)) {
                    return false;
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
        foreach ($this->getRequiredHeaders() as $field) {
            if (array_search($field, $this->headers) === false) {
                $this->setError('Required fields not found:', 'The "' . $field . '" field is required');
            }
        }

        $this->executeHeadersFilters();
    }

    /**
     * @return void
     */
    protected function executeHeadersFilters()
    {
        foreach (static::getRequiredFilters() as $filter) {
            if ($filter instanceof BaseHeadersFilter) {
                $result = $filter->executeFilter($this->headers);
                if ($result->error) {
                    $this->setError('Headers error:', $result->message);
                }
            }
        }
    }

    /**
     * @parameters BaseHeaderFilter
     * @return array
     */
    public static function addRequiredFilters()
    {
        return static::addFilters(self::REQUIRED, func_get_args());
    }

    /**
     * @parameters BaseHeaderFilter
     * @return array
     */
    public static function addValidationFilters()
    {
        return static::addFilters(self::VALIDATION, func_get_args());
    }

    /**
     * @parameters BaseHeaderFilter
     * @return array
     */
    public static function addCastFilters()
    {
        return static::addFilters(self::CAST, func_get_args());
    }

    /**
     * @param $filter
     * @param null $name
     * @return bool|ClosureCastFilter|ClosureValidationFilter|ClosureHeadersFilter
     */
    public static function addRequiredFilter($filter, $name = null)
    {
        return static::addFilter(self::REQUIRED, $filter, $name);
    }

    /**
     * @param $filter
     * @param null $name
     * @return bool|ClosureCastFilter|ClosureValidationFilter|ClosureHeadersFilter
     */
    public static function addValidationFilter($filter, $name = null)
    {
        return static::addFilter(self::VALIDATION, $filter, $name);
    }

    /**
     * @param $filter
     * @param null $name
     * @return bool|ClosureCastFilter|ClosureValidationFilter|ClosureHeadersFilter
     */
    public static function addCastFilter($filter, $name = null)
    {
        return static::addFilter(self::CAST, $filter, $name);
    }

    /**
     * @return array
     */
    public static function getRequiredFilters()
    {
        return static::getFilters(self::REQUIRED);
    }

    /**
     * @return array
     */
    public static function getValidationFilters()
    {
        return static::getFilters(self::VALIDATION);
    }

    /**
     * @return array
     */
    public static function getCastFilters()
    {
        return static::getFilters(self::CAST);
    }

    /**
     * @param $type
     * @return mixed
     */
    protected static function getFilters($type)
    {
        return Arr::get(static::${$type . 'Filters'}, static::class, []);
    }

    /**
     * @param $name
     * @return mixed
     */
    public static function getRequiredFilter($name)
    {
        return static::getFilter(self::REQUIRED, $name);
    }

    /**
     * @param $name
     * @return mixed
     */
    public static function getValidationFilter($name)
    {
        return static::getFilter(self::VALIDATION, $name);
    }

    /**
     * @param $name
     * @return mixed
     */
    public static function getCastFilter($name)
    {
        return static::getFilter(self::CAST, $name);
    }

    /**
     * @param $type
     * @param $name
     * @return null
     */
    protected static function getFilter($type, $name)
    {
        return (static::filterExists($type, $name)) ? static::${$type . 'Filters'}[static::class][$name] : null;
    }

    /**
     * @return array
     */
    public static function flushRequiredFilters()
    {
        return static::flushFilters(self::REQUIRED);
    }

    /**
     * @return array
     */
    public static function flushValidationFilters()
    {
        return static::flushFilters(self::VALIDATION);
    }

    /**
     * @return array
     */
    public static function flushCastFilters()
    {
        return static::flushFilters(self::CAST);
    }

    /**
     * @param $type
     * @return array
     */
    protected static function flushFilters($type)
    {
        return Arr::set(static::${$type . 'Filters'}, static::class, []);
    }

    /**
     * @param $name
     * @return bool
     */
    public static function requiredFilterExists($name)
    {
        return static::filterExists(self::REQUIRED, $name);
    }
    
    /**
     * @param $name
     * @return bool
     */
    public static function validationFilterExists($name)
    {
        return static::filterExists(self::VALIDATION, $name);
    }

    /**
     * @param $name
     * @return bool
     */
    public static function castFilterExists($name)
    {
        return static::filterExists(self::CAST, $name);
    }

    /**
     * @param $type
     * @param $name
     * @return bool
     */
    protected static function filterExists($type, $name)
    {
        return isset(static::${$type . 'Filters'}[static::class][$name]);
    }

    /**
     * @param $type
     * @param array $filters
     * @return mixed
     */
    private static function addFilters($type, array $filters)
    {
        foreach ($filters as $filter) {
            if (is_array($filter)) {
                call_user_func_array([static::class, "add" . ucfirst($type) . "Filters"], $filter);
            } else {
                static::addFilter($type, $filter);
            }
        }

        return static::${$type . 'Filters'}[static::class];
    }

    /**
     * @param $type
     * @param $filter
     * @param null $name
     * @return bool|ClosureCastFilter|ClosureValidationFilter|ClosureHeadersFilter
     */
    private static function addFilter($type, $filter, $name = null)
    {
        $resolved = false;

        switch ($type) {
            case self::REQUIRED:
                $resolved = (static::isClosure($filter)) ? new ClosureHeadersFilter($filter) : static::checkRequiredFilter($filter);
                break;
            case self::VALIDATION:
                $resolved = (static::isClosure($filter)) ? new ClosureValidationFilter($filter) : static::checkValidationFilter($filter);
                break;
            case self::CAST:
                $resolved = (static::isClosure($filter)) ? new ClosureCastFilter($filter) : static::checkCastFilter($filter);
                break;
        }

        if ($resolved) {
            return static::${$type . 'Filters'}[static::class][static::filterName($type, $resolved, $name)] = $resolved;
        }

        return false;
    }

    /**
     * @param $type
     * @param $filter
     * @param $name
     * @return string
     */
    protected static function filterName($type, $filter, $name)
    {
        return static::checkFilterName($type, (is_string($name)) ? $name : (string)$filter);
    }

    /**
     * @param string $type
     * @param null $name
     * @param int $counter
     * @return string
     */
    protected static function checkFilterName($type, $name, $counter = 1)
    {
        $filterName = $name . (($counter > 1) ? '_' . $counter : '');

        if (call_user_func([static::class, $type . 'FilterExists'], $filterName)) {
            return static::checkFilterName($type, $name, ++$counter);
        }

        return $filterName;
    }

    /**
     * @param $filter
     * @return bool
     */
    public static function checkRequiredFilter($filter)
    {
        return ($filter instanceof BaseHeadersFilter) ? $filter : false;
    }

    /**
     * @param $filter
     * @return bool
     */
    public static function checkValidationFilter($filter)
    {
        return ($filter instanceof BaseValidationFilter) ? $filter : false;
    }

    /**
     * @param $filter
     * @return bool
     */
    public static function checkCastFilter($filter)
    {
        return ($filter instanceof BaseCastFilter) ? $filter : false;
    }

    /**
     * @param $filter
     * @return bool
     */
    protected static function isClosure($filter)
    {
        return ($filter instanceof \Closure);
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
     * @return array
     */
    protected function getRequiredHeaders()
    {
        $fieldsWithRules = [];

        if ($this->configMappingsExists()) {
            foreach ($this->config['mappings'] as $field => $rules) {
                if (array_search(self::REQUIRED, $rules) !== false) {
                    $fieldsWithRules[] = $field;
                }
            }
        }

        return $fieldsWithRules;
    }

    /**
     * @return bool
     */
    protected function configMappingsExists()
    {
        return (isset($this->config['mappings']) && is_array($this->config['mappings']));
    }

    /*
    |--------------------------------------------------------------------------
    | Mutex functionality
    |--------------------------------------------------------------------------
    */

    /**
     * @return Mutex
     * @throws CsvImporterException
     */
    protected function setMutex()
    {
        $cacheStore = $this->cache->getStore();

        if ($cacheStore instanceof RedisStore) {
            $client = (($client = $cacheStore->connection()) instanceof PredisClient) ? $client : $client->client(null);
            return $this->initMutex(new PredisRedisLock($client));
        }

        if ($cacheStore instanceof MemcachedStore) {
            return $this->initMutex(new MemcachedLock($cacheStore->getMemcached()));
        }

        if ($cacheStore instanceof FileStore) {
            return $this->initMutex(new FlockLock($cacheStore->getDirectory()));
        }

        throw new CsvImporterException(
            ['message' => 'Csv importer supports only: file, memcached and redis cache drivers'],
            400
        );
    }

    /**
     * @param $driver
     * @return Mutex
     */
    protected function initMutex($driver)
    {
        return $this->mutex = new Mutex($this->importLockKey, $driver);
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
     * Drop progress quantity
     *
     * @return void
     */
    protected function dropProgress()
    {
        $this->cache->put($this->progressCacheKey, 0, $this->mutexLockTime);
    }

    /**
     * Specify custom progress message during import process
     *
     * @param string $message
     */
    protected function setProgressMessage($message)
    {
        $this->cache->put($this->progressMessageKey, $message, $this->mutexLockTime);
    }

    /**
     * @return array
     */
    private function progressBar()
    {
        $progress = $this->getProgressDetails();

        if ($progress->finished) {
            return [
                'data' => ["message"  => $this->getConfigProperty('finished', "", 'string')],
                'meta' => ["finished" => true, 'init' => false, 'running' => false]
            ];
        } elseif (!$progress->quantity && !$this->isLocked()) {
            return [
                'data' => [
                    "message"  => $this->getConfigProperty('does_not_running', "", 'string')
                ],
                'meta' => ["finished" => false, 'init' => false, 'running' => false]
            ];
        } elseif (!$progress->quantity && $this->isLocked()) {
                return [
                    'data' => ["message" => $this->getConfigProperty('initialization', "", 'string')],
                    'meta' => ["finished" => false, 'init' => true, 'running' => true]
                ];
        } elseif (($progress->quantity == $progress->processed) && $this->isLocked()) {
            return [
                'data' => ["message"  => $this->getConfigProperty('final_stage', "", 'string')],
                'meta' => ["finished" => false, 'init' => false, 'running' => true]
            ];
        } else {
            $data = [
                'data' => ["message"  => $progress->message],
                'meta' => [
                    'processed'  => ( int )$progress->processed,
                    'remains'    => ( int )$progress->quantity - $progress->processed,
                    'percentage' => floor(($progress->processed / ($progress->quantity / 100))),
                    'finished'   => false,
                    'init'       => false,
                    'running'    => true
                ]
            ];

            if ($details = $this->progressBarDetails()) {
                $data['data']['details'] = $details;
            }

            return $data;
        }
    }

    /**
     * @return array
     * @throws CsvImporterException
     */
    private function finalProgressDetails()
    {
        $progress = $this->getProgressDetails();

        $data = [
            'data' => [
                "message"  => $this->getConfigProperty('final', '', 'string')
            ],
            'meta' => ["finished" => true, 'init' => false, 'running' => false]
        ];

        if ($progress->final_details) {
            $data['data']['details'] = $progress->final_details;
        }

        if ($progress->paths) {
            $data['files'] = $progress->paths;
        }

        return $data;
    }

    /**
     * @return object
     */
    private function getProgressDetails()
    {
        return ( object ) [
            'processed'          => $this->cache->get($this->progressCacheKey),
            'quantity'           => $this->cache->get($this->csvCountCacheKey),
            'message'            => $this->cache->get($this->progressMessageKey),
            'finished'           => $this->cache->get($this->progressFinishedKey),
            'details'            => $this->cache->get($this->progressDetailsKey),
            'final_details'      => $this->cache->get($this->progressFinalDetailsKey),
            'paths'              => $this->cache->get($this->importPathsKey),
        ];
    }
}
