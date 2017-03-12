<?php

namespace RGilyov\CsvImporter\Test\Jobs;

use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use RGilyov\CsvImporter\Test\CsvImporters\AsyncCsvImporter;
use Illuminate\Support\Facades\Cache;

class TestImportJob extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * @var string
     */
    protected $cacheDriver;

    /**
     * TestImportJob constructor.
     * @param $driver
     */
    public function __construct($driver)
    {
        $this->cacheDriver = $driver;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (config('cache.default') != $this->cacheDriver) {
            dispatch(new TestImportJob($this->cacheDriver));
            return;
        }

        try {
            Cache::forever(
                'csv_importer_response',
                (new AsyncCsvImporter())->setCsvFile(__DIR__.'/../files/guitars.csv')->setAsyncMode(true)->run()
            );
        } catch (\Exception $e) {
            Cache::forever('csv_importer_response', $e->getMessage());
        }
    }
}
