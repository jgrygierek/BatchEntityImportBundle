<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

use Throwable;

/**
 * Exception thrown when related entity can't be found by value from record.
 * It should be thrown in case of insufficient data validation.
 */
class MatrixRecordRelatedEntityNotFoundException extends MatrixException
{
    /**
     * @param int $rowNumber number of record, starting from 1, as in the preview
     */
    public function __construct(int $rowNumber, string $columnName, ?Throwable $previous = null)
    {
        parent::__construct('exception.matrix.record.related_entity.not_found_in_field', 0, $previous);
        $this->translationParameters = ['%row%' => $rowNumber, '%column%' => $columnName];
    }
}
