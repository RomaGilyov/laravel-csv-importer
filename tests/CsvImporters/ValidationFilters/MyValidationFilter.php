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
     * @param mixed $value
     * @return bool
     */
    public function filter($value)
    {
        if (strpos($value, 'bad_word') !== false) {
            return false;
        }

        return true;
    }
}
