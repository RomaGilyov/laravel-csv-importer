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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Cache::forever(
                'csv_importer_response',
                (new AsyncCsvImporter())->setFile(__DIR__.'/../files/guitars.csv')->setAsyncMode(true)->run()
            );
        } catch (\Exception $e) {
            Cache::forever('csv_importer_response', $e->getMessage());
        }
    }
}
