<?php

namespace RGilyov\CsvImporter\Test\CsvImporters;

use RGilyov\CsvImporter\BaseCsvImporter;

class CsvImporter extends BaseCsvImporter
{
    protected $configDate;

    public function __construct($configDate = false)
    {
        parent::__construct();

        $this->configDate = ($configDate) ? 'Y-m-d' : null;
    }

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
                'company'       => ['validation' => ['string'], 'cast' => 'super_caster'],
                'some_field_1'  => ['cast' => 'string'],
                'some_field_2'  => [],
                'email'         => ['validation' => 'email'],
                'date'          => ['cast' => 'date'],
                'date_time'     => ['cast' => 'date_time']
            ],
            'csv_files' => [
                'valid_entities'   => '/valid_entities.csv',
                'invalid_entities' => '/invalid_entities.csv',
            ],
            'config' => [
                'csv_date_format' => $this->configDate
            ]
        ];
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
