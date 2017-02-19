<?php namespace RGilyov\CsvImporter;

use \Illuminate\Config\Repository;

trait CsvImporterConfigurationTrait
{
    /**
     * Get the Csv importer config
     *
     * @param string $key the configuration key
     * @return array configuration
     */
    public function getBaseConfig($key = 'csv-importer')
    {
        if (function_exists('config')) {
            // Get config helper for Laravel 5.1+
            $configHelper = config();
        } elseif (function_exists('app')) {
            // Get config helper for Laravel 4 & Laravel 5.1
            $configHelper = app('config');
        } else {
            $configHelper = $this->getConfigHelper();
        }

        return $configHelper->get($key);
    }

    /**
     * Inject given config file into an instance of Laravel's config
     *
     * @throws \Exception when the configuration file is not found
     * @return \Illuminate\Config\Repository configuration repository
     */
    protected function getConfigHelper()
    {
        $configFile = $this->getConfigFile();

        if (!file_exists($configFile)) {
            throw new \Exception('Config file not found.');
        }

        return new Repository(['csv-importer' => require $configFile]);
    }

    /**
     * Get the config path and file name
     *
     * @return string config file path
     */
    protected function getConfigFile()
    {
        return __DIR__ . '/config/csv-importer.php';
    }
}