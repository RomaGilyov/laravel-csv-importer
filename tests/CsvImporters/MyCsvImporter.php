<?php

namespace RGilyov\CsvImporter\Test\CsvImporters;

use RGilyov\CsvImporter\BaseCsvImporter;

class MyCsvImporter extends BaseCsvImporter
{
    /**
     * We need to provide some time to test
     *
     * @var bool
     */
    protected $asyncMode = false;

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
}
