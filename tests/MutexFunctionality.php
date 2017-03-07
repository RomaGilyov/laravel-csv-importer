<?php

namespace RGilyov\CsvImporter\Test;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Cache;
use \RGilyov\CsvImporter\Test\Jobs\TestImportJob;
use RGilyov\CsvImporter\Test\CsvImporters\AsyncCsvImporter;

class MutexFunctionality extends BaseTestCase
{
    /**
     * @var AsyncCsvImporter
     */
    protected $importer;

    public function setUp()
    {
        parent::setUp();

        $this->importer = (new AsyncCsvImporter())->setCsvFile(__DIR__.'/files/guitars.csv');

        $this->importer->clearSession();
        $this->importer->flushAsyncInfo();

        if ($this->runTests()) {
            dispatch(new TestImportJob($this->cacheDriver));

            /*
             * We need to wait till queue start import in the separated system process
             */
            $this->waitUntilStart();
        }
    }

    protected function runTests()
    {
        return function_exists('dispatch');
    }

    public function tearDown()
    {
        if ($this->runTests()) {
            /*
             * Make sure the import is finished before next test
             */
            $this->checkImportFinalResponse();
        }

        parent::tearDown();
    }

    /** @test */
    public function it_can_import_and_lock_csv()
    {
        if (!$this->runTests()) {
            return;
        }

        $initProgress         = $this->importer->getProgress();

        $this->waitUntilEndOfInitialization();

        $progress             = $this->importer->getProgress();

        /*
         * Instead of execution we will get progress information from import which is queued 
         * and running in the another system process
         */
        $preventedRunResponse = $this->importer->run();

        $this->waitUntilFinalStage();

        $finalStageProgress   = $this->importer->getProgress();

        $this->waitUntilCustomProgressBar();

        $customProgress       = $this->importer->getProgress();
        
        $finishedMessage      = $this->checkImportFinalResponse();

        $finalInformation     = $this->importer->finish();

        $this->assertEquals('Initialization', $initProgress['data']['message']);
        $this->assertFalse($initProgress['meta']['finished']);
        $this->assertTrue($initProgress['meta']['init']);
        $this->assertTrue($initProgress['meta']['running']);

        $this->assertEquals('Import process is running', $progress['data']['message']);
        $this->assertEquals('Sup Mello?', $progress['data']['details']);
        $this->assertEquals('integer', gettype($progress['meta']['processed']));
        $this->assertEquals('integer', gettype($progress['meta']['remains']));
        $this->assertEquals('double', gettype($progress['meta']['percentage']));
        $this->assertFalse($progress['meta']['finished']);
        $this->assertFalse($progress['meta']['init']);
        $this->assertTrue($progress['meta']['running']);

        $this->assertEquals('Import process is running', $preventedRunResponse['data']['message']);
        $this->assertEquals('integer', gettype($preventedRunResponse['meta']['processed']));
        $this->assertEquals('integer', gettype($preventedRunResponse['meta']['remains']));
        $this->assertEquals('double', gettype($preventedRunResponse['meta']['percentage']));
        $this->assertFalse($preventedRunResponse['meta']['finished']);
        $this->assertFalse($preventedRunResponse['meta']['init']);
        $this->assertTrue($preventedRunResponse['meta']['running']);

        $this->assertEquals("Final stage", $finalStageProgress['data']['message']);
        $this->assertFalse($finalStageProgress['meta']['finished']);
        $this->assertFalse($finalStageProgress['meta']['init']);
        $this->assertTrue($finalStageProgress['meta']['running']);

        $this->assertEquals('Custom progress bar', $customProgress['data']['message']);
        $this->assertEquals('Sup Mello?', $customProgress['data']['details']);
        $this->assertEquals('integer', gettype($customProgress['meta']['processed']));
        $this->assertEquals('integer', gettype($customProgress['meta']['remains']));
        $this->assertEquals('double', gettype($customProgress['meta']['percentage']));
        $this->assertFalse($customProgress['meta']['finished']);
        $this->assertFalse($customProgress['meta']['init']);
        $this->assertTrue($customProgress['meta']['running']);

        $this->assertEquals(
            "Almost done, please click to the `finish` button to proceed",
            $finishedMessage['data']['message']
        );
        $this->assertTrue($finishedMessage['meta']['finished']);
        $this->assertFalse($finishedMessage['meta']['init']);
        $this->assertFalse($finishedMessage['meta']['running']);

        $this->assertEquals("The import process successfully finished!", $finalInformation['data']['message']);
        $this->assertEquals("Buzz me Mulatto", $finalInformation['data']['details']);
        $this->assertTrue($finalInformation['meta']['finished']);
        $this->assertFalse($finalInformation['meta']['init']);
        $this->assertFalse($finalInformation['meta']['running']);
        $this->assertTrue(strpos($finalInformation['files']['valid_entities'], "valid_entities") !== false);
        $this->assertTrue(strpos($finalInformation['files']['invalid_entities'], "invalid_entities") !== false);
    }

    /** @test */
    public function it_can_cancel_import_process()
    {
        if (!$this->runTests()) {
            return;
        }

        $this->importer->cancel();

        $finishedMessage = $this->checkImportFinalResponse();

        $this->assertEquals("Importing had canceled", json_decode($finishedMessage, true)['message']);
        $this->assertEquals("Hey there!", Cache::get(AsyncCsvImporter::$cacheOnCancelKey));
    }

    /** @test */
    public function it_can_concatenate_import_lock_key()
    {
        if (!$this->runTests()) {
            return;
        }

        $finishedMessage  = $this->importer->concatMutexKey('unrelated_guitars')->run();
        $finalInformation = $this->importer->finish();

        $this->assertEquals(
            "Almost done, please click to the `finish` button to proceed",
            $finishedMessage['data']['message']
        );
        $this->assertTrue($finishedMessage['meta']['finished']);
        $this->assertFalse($finishedMessage['meta']['init']);
        $this->assertFalse($finishedMessage['meta']['running']);

        $this->assertEquals("The import process successfully finished!", $finalInformation['data']['message']);
        $this->assertTrue($finalInformation['meta']['finished']);
        $this->assertFalse($finalInformation['meta']['init']);
        $this->assertFalse($finalInformation['meta']['running']);
        $this->assertTrue(strpos($finalInformation['files']['valid_entities'], "valid_entities") !== false);
        $this->assertTrue(strpos($finalInformation['files']['invalid_entities'], "invalid_entities") !== false);
    }
    
    /////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @param int $counter
     * @return mixed
     * @throws \Exception
     */
    protected function checkImportFinalResponse($counter = 0)
    {
        if ($info = Cache::get(AsyncCsvImporter::$cacheInfoKey)) {
            usleep(200); // reduce possibility of race condition
            return $info;
        }

        $this->fuse($counter, AsyncCsvImporter::$cacheInfoKey);

        return $this->checkImportFinalResponse(++$counter);
    }

    /**
     * @param int $counter
     * @return bool|mixed
     * @throws \Exception
     */
    protected function waitUntilStart($counter = 0)
    {
        if (Cache::get(AsyncCsvImporter::$cacheStartedKey)) {
            usleep(200); // reduce possibility of race condition
            return true;
        }

        $this->fuse($counter, AsyncCsvImporter::$cacheStartedKey);

        return $this->waitUntilStart(++$counter);
    }

    /**
     * @param int $counter
     * @return bool|mixed
     * @throws \Exception
     */
    protected function waitUntilCustomProgressBar($counter = 0)
    {
        if (Cache::get(AsyncCsvImporter::$cacheCustomProgressBarKey)) {
            usleep(200); // reduce possibility of race condition
            return true;
        }

        $this->fuse($counter, AsyncCsvImporter::$cacheCustomProgressBarKey);

        return $this->waitUntilCustomProgressBar(++$counter);
    }

    /**
     * @param int $counter
     * @return bool|mixed
     * @throws \Exception
     */
    protected function waitUntilEndOfInitialization($counter = 0)
    {
        if (Cache::get(AsyncCsvImporter::$cacheInitFinishedKey)) {
            usleep(200); // reduce possibility of race condition
            return true;
        }

        $this->fuse($counter, AsyncCsvImporter::$cacheInitFinishedKey);

        return $this->waitUntilEndOfInitialization(++$counter);
    }

    /**
     * @param int $counter
     * @return bool|mixed
     * @throws \Exception
     */
    protected function waitUntilFinalStage($counter = 0)
    {
        if (Cache::get(AsyncCsvImporter::$cacheFinalStageStartedKey)) {
            usleep(200); // reduce possibility of race condition
            return true;
        }

        $this->fuse($counter, AsyncCsvImporter::$cacheFinalStageStartedKey);

        return $this->waitUntilFinalStage(++$counter);
    }

    /**
     * @param $counter
     * @param $key
     * @throws \Exception
     */
    protected function fuse($counter, $key)
    {
        if ($counter > 25) {
            throw new \PHPUnit_Framework_ExpectationFailedException(
                "Timeout error. Check your queue. Key: '" . $key . "'. Cache driver: '" . $this->cacheDriver . "'."
            );
        }

        sleep(1);
    }
}