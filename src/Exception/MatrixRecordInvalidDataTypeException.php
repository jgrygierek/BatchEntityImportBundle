<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

use Throwable;

/**
 * Exception thrown when data of record have invalid type.
 * It should be thrown in case of insufficient data validation.
 */
class MatrixRecordInvalidDataTypeException extends MatrixException
{
    /**
     * @param int $rowNumber number of record, starting from 1, as in the preview
     */
    public function __construct(int $rowNumber, string $columnName, ?Throwable $previous = null)
    {
        parent::__construct('exception.matrix.record.data.invalid_type_in_field', 0, $previous);
        $this->translationParameters = ['%row%' => $rowNumber, '%column%' => $columnName];
    }
}
