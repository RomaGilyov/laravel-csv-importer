<?php

namespace RGilyov\CsvImporter\Test;

use Orchestra\Testbench\TestCase;
use \RGilyov\CsvImporter\CsvImporterServiceProvider;
use \Illuminate\Support\Facades\File;
use RGilyov\CsvImporter\Test\CsvImporters\CsvImporter;

abstract class BaseTestCase extends TestCase
{
    protected $cachePath = __DIR__ . DIRECTORY_SEPARATOR .'files' . DIRECTORY_SEPARATOR . 'cache';

    protected $filesPath = __DIR__ . DIRECTORY_SEPARATOR .'files' . DIRECTORY_SEPARATOR . 'import';

    protected $cacheDriver = 'file';

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            CsvImporterServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.default', $this->cacheDriver);
        $app['config']->set('queue.default', 'redis');
        $app['config']->set('cache.stores.file', [
            'driver' => 'file',
            'path'   => $this->cachePath,
        ]);
        $app['config']->set('filesystems.default', 'local');
        $app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root'   => $this->filesPath,
        ]);
    }

    public function tearDown()
    {
        File::deleteDirectory($this->cachePath, true);
        File::deleteDirectory($this->filesPath, true);

        parent::tearDown();
    }

    //////////////////////////////////////////////////////////

    /**
     * @param $path
     * @return array
     */
    protected function getResultCsv($path)
    {
        $res = fopen($path, 'r');

        $csvEntities = [];
        while ($entity = fgetcsv($res, 1000)) {
            $csvEntities[] = $entity;
        }

        return $csvEntities;
    }

    /**
     * @param null $path
     * @return array
     */
    protected function importCsv($path = null)
    {
        $importer = (new CsvImporter())->setCsvFile(($path) ? $path : __DIR__.'/files/guitars.csv');

        $importer->run();

        return $importer->finish();
    }
}