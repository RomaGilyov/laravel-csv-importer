<?php

namespace App\CsvImporter\Exceptions;

/**
 * Class CsvImporterException
 * @package App\CsvImporter\Exceptions
 */
class CsvImporterException extends \Exception
{
    /**
     * CsvImporterException constructor.
     * @param array $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message = [], $code = 401, \Exception $previous = null)
    {
        parent::__construct(json_encode($message), $code, $previous);
    }
}
