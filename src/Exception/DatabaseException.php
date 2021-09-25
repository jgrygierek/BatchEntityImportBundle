<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

use Exception;

/**
 * General exception thrown when data of record cannot be saved to database because of PDO Exception.
 */
class DatabaseException extends Exception implements BatchEntityImportExceptionInterface
{
    public function __construct(string $message = 'exception.database.save')
    {
        parent::__construct($message);
    }
}
