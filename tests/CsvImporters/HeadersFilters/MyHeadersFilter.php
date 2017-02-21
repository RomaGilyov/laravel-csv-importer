<?php

namespace RGilyov\CsvImporter\Test\CsvImporters\HeadersFilters;

use RGilyov\CsvImporter\BaseHeadersFilter;

class MyHeadersFilter extends BaseHeadersFilter
{
    /**
     * Specify error message
     * 
     * @var string
     */
    public $errorMessage = 'The csv must contain either `name` field either `first_name` and `last_name` fields';

    /**
     * @param array $csvHeaders
     * @return bool
     */
    public function filter(array $csvHeaders)
    {
        if (isset($csvHeaders['name']) || (isset($csvHeaders['first_name']) && isset($csvHeaders['last_name']))) {
            return true;
        }

        return false;
    }
}
