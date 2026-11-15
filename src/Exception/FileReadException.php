<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when uploaded file can't be read, e.g. it is corrupted or its content does not match the extension.
 */
class FileReadException extends RuntimeException implements BatchEntityImportExceptionInterface
{
    public function __construct(string $message = 'exception.file.read', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
