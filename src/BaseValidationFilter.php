<?php namespace RGilyov\CsvImporter;

abstract class BaseValidationFilter
{
    use NameableTrait;

    /**
     * @param array $csvItem
     * @return bool
     */
    abstract public function filter(array $csvItem);
}