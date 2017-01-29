<?php namespace RGilyov\CsvImporter;

abstract class BaseFieldFilter
{
    /**
     * Specify error message for the field filter
     *
     * @var string
     */
    public $errorMessage = 'Field error occurred';

    /**
     * @param array $cavItem
     * @return bool
     */
    abstract public function filter(array $cavItem);
}