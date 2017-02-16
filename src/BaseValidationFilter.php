<?php namespace RGilyov\CsvImporter;

abstract class BaseValidationFilter
{
    /**
     * @param array $csvItem
     * @return bool
     */
    abstract public function filter(array $csvItem);
}