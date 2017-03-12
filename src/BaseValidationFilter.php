<?php namespace RGilyov\CsvImporter;

abstract class BaseValidationFilter
{
    use NameableTrait;

    /**
     * No need to attach the filter to any fields, since it will receive full csv array line if true
     *
     * @var bool
     */
    public $global = false;

    /**
     * @param mixed $value
     * @return bool
     */
    abstract public function filter($value);
}