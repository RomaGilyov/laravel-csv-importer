<?php

namespace RGilyov\CsvImporter\Test\CsvImporters;

use Illuminate\Support\Facades\Cache;
use RGilyov\CsvImporter\BaseCsvImporter;

class AsyncCsvImporter extends BaseCsvImporter
{
    /**
     * We need to provide some time to test
     *
     * @var bool
     */
    protected $asyncMode = false;

    /**
     * @var string
     */
    public static $cacheInfoKey = 'csv_importer_response';

    /**
     * @var string
     */
    public static $cacheStartedKey = 'import_has_been_started';

    /**
     * @var string
     */
    public static $cacheInitFinishedKey = 'import_initialization_finished';

    /**
     * @var string
     */
    public static $cacheFinalStageStartedKey = 'import_final_stage_started';

    /**
     *  Specify mappings and rules for our csv, we also may create csv files when we can write csv entities
     *
     * @return array
     */
    public function csvConfigurations()
    {
        return [
            'mappings' => [
                'serial_number' => ['required', 'validation' => ['numeric'], 'cast' => 'string'],
                'title'         => ['validation' => ['required'], 'cast' => ['string', 'lowercase']],
                'company'       => ['validation' => ['string'], 'cast' => 'super_caster']
            ],
            'csv_files' => [
                'valid_entities'   => '/valid_entities.csv',
                'invalid_entities' => '/invalid_entities.csv',
            ]
        ];
    }

    /**
     * @param $mode
     * @return $this
     */
    public function setAsyncMode($mode)
    {
        $this->asyncMode = $mode;

        return $this;
    }

    /**
     *  Will be executed for a csv line if it passes validation
     *
     * @param $item
     * @throws \RGilyov\CsvImporter\Exceptions\CsvImporterException
     * @return void
     */
    public function handle($item)
    {
        if ($this->asyncMode) {
            sleep(1);
            echo "I'm running";
        }

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

    /**
     * @return void
     */
    public function before()
    {
        Cache::forever(AsyncCsvImporter::$cacheStartedKey, true);

        sleep(5);

        Cache::forever(AsyncCsvImporter::$cacheInitFinishedKey, true);
    }

    /**
     * @return void
     */
    public function after()
    {
        Cache::forever(AsyncCsvImporter::$cacheFinalStageStartedKey, true);

        sleep(5);
    }

    /**
     * @return void
     */
    public static function flushAsyncInfo()
    {
        Cache::forget(AsyncCsvImporter::$cacheInfoKey);
        Cache::forget(AsyncCsvImporter::$cacheStartedKey);
        Cache::forget(AsyncCsvImporter::$cacheInitFinishedKey);
        Cache::forget(AsyncCsvImporter::$cacheFinalStageStartedKey);
    }
}
