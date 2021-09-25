<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

/**
 * Exception thrown when data of record have invalid type.
 * It should be thrown in case of insufficient data validation.
 */
class MatrixRecordInvalidDataTypeException extends MatrixException
{
    public function __construct(string $message = 'exception.matrix.record.data.invalid_type')
    {
        parent::__construct($message);
    }
}
