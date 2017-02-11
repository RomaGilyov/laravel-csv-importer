<?php namespace RGilyov\CsvImporter;

abstract class BaseImportantFilter
{
    /**
     * @param array $csvItem
     * @return bool
     */
    abstract public function filter(array $csvItem);
}