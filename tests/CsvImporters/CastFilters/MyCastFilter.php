<?php

namespace RGilyov\CsvImporter\Test\CsvImporters\CastFilters;

use RGilyov\CsvImporter\BaseCastFilter;

class MyCastFilter extends BaseCastFilter
{
    protected $name = 'lowercase';

    /**
     * @param $value
     * @return mixed
     */
    public function filter($value)
    {
        return strtolower($value);
    }
}
