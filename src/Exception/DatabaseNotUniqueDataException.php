<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

/**
 * Exception thrown when data of record cannot be saved to database because is not unique.
 */
class DatabaseNotUniqueDataException extends DatabaseException
{
    public function __construct(string $message = 'exception.database.data.not_unique')
    {
        parent::__construct($message);
    }
}
