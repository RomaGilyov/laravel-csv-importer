<?php

namespace RGilyov\CsvImporter\Test\CsvImporters;

use Illuminate\Support\Facades\Cache;
use RGilyov\CsvImporter\BaseCsvImporter;

class AsyncCsvImporter extends BaseCsvImporter
{
    /**
     * We need to provide some time to tests
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
     * @var string
     */
    public static $cacheOnCancelKey = 'import_on_cancel';

    /**
     * @var string
     */
    public static $cacheCustomProgressBarKey = 'import_custom_progress_bar';

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
    protected function handle($item)
    {
        if ($this->asyncMode) {
            sleep(1);
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
    protected function invalid($item)
    {
        $this->insertTo('invalid_entities', $item);
    }

    /**
     * @return void
     */
    protected function before()
    {
        if ($this->asyncMode) {
            Cache::forever(AsyncCsvImporter::$cacheStartedKey, true);

            sleep(5);

            Cache::forever(AsyncCsvImporter::$cacheInitFinishedKey, true);   
        }
    }

    /**
     * @return void
     */
    protected function after()
    {
        if ($this->asyncMode) {
            Cache::forever(AsyncCsvImporter::$cacheFinalStageStartedKey, true);
            
            $this->setFinalDetails('Buzz me Mulatto');

            sleep(5);

            Cache::forever(AsyncCsvImporter::$cacheCustomProgressBarKey, true);

            $this->initProgressBar('Custom progress bar', 5);

            for ($i = 0; $i < 5; $i++) {
                sleep(1);
                $this->incrementProgress();
            }   
        }
    }

    /**
     * @return void
     */
    protected function onCancel()
    {
        if ($this->asyncMode) {
            Cache::forever(AsyncCsvImporter::$cacheOnCancelKey, 'Hey there!');
        }
    }

    /**
     * @return string
     */
    public function progressBarDetails()
    {
        return 'Sup Mello?';
    }

    //////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @return void
     */
    public static function flushAsyncInfo()
    {
        Cache::forget(AsyncCsvImporter::$cacheInfoKey);
        Cache::forget(AsyncCsvImporter::$cacheStartedKey);
        Cache::forget(AsyncCsvImporter::$cacheInitFinishedKey);
        Cache::forget(AsyncCsvImporter::$cacheFinalStageStartedKey);
        Cache::forget(AsyncCsvImporter::$cacheOnCancelKey);
        Cache::forget(AsyncCsvImporter::$cacheCustomProgressBarKey);
    }
}
