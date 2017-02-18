<?php namespace RGilyov\CsvImporter;

abstract class BaseCastFilter
{
    use NameableTrait;
    
    /**
     * @param $value
     * @return mixed
     */
    abstract public function filter($value);
}