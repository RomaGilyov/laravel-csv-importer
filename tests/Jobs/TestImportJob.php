<?php

namespace RGilyov\CsvImporter\Test\Jobs;

use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        sleep(10);

        echo "job done" . PHP_EOL;
    }
}
