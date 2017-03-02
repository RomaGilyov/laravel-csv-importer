<?php

namespace RGilyov\CsvImporter\Test\CsvImporters\ValidationFilters;

use RGilyov\CsvImporter\BaseValidationFilter;

class MyValidationFilter extends BaseValidationFilter
{
    /**
     * @var string
     */
    protected $name = 'bad_word_validation';

    /**
     * @param array $csvItem
     * @return bool
     */
    public function filter(array $csvItem)
    {
        if (strpos($csvItem['title'], 'bad_word') !== false) {
            return false;
        }

        return true;
    }
}
