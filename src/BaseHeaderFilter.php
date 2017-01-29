<?php namespace RGilyov\CsvImporter;

abstract class BaseHeaderFilter
{
    /**
     * Specify error message for the header filter
     *
     * @var string
     */
    public $errorMessage = 'Headers error occurred';

    /**
     * @param array $cavHeaders
     * @return bool
     */
    abstract protected function filter(array $cavHeaders);

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