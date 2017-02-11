<?php namespace RGilyov\CsvImporter;

abstract class BaseRequiredFilter
{
    /**
     * Specify error message for the header filter
     *
     * @var string
     */
    public $errorMessage = 'Headers error occurred';

    /**
     * @param array $csvHeaders
     * @return bool
     */
    abstract protected function filter(array $csvHeaders);

    /**
     * @param array $csvHeaders
     * @return object
     */
    final public function executeFilter(array $csvHeaders)
    {
        if ($this->filter($csvHeaders)) {
            return (object)['error' => false];
        }

        return (object)['error' => true, 'message' => $this->errorMessage];
    }
}