<?php namespace RGilyov\CsvImporter;

abstract class BaseCastFilter
{
    /**
     * @param $value
     * @return mixed
     */
    abstract public function filter($value);
}