<?php

namespace RGilyov\CsvImporter\Test\CsvImporters\ValidationFilters;

use RGilyov\CsvImporter\BaseValidationFilter;

class MyValidationFilter extends BaseValidationFilter
{
    /**
     * @param array $csvItem
     * @return bool
     */
    public function filter(array $csvItem)
    {
        if (strpos($csvItem['name'], 'some bad word') !== false) {
            return false;
        }

        return true;
    }
}
