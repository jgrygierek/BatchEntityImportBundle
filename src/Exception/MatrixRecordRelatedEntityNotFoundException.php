<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

/**
 * Exception thrown when related entity can't be found by value from record.
 * It should be thrown in case of insufficient data validation.
 */
class MatrixRecordRelatedEntityNotFoundException extends MatrixException
{
    public function __construct(string $message = 'exception.matrix.record.related_entity.not_found')
    {
        parent::__construct($message);
    }
}
