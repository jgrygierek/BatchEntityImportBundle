<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

/**
 * Exception thrown when encoding of CSV file is not recognized and fallback encoding is not configured.
 * Encoding is never guessed, because wrong guess would silently break imported data.
 */
class FileEncodingException extends FileReadException
{
    public function __construct(string $message = 'exception.file.encoding')
    {
        parent::__construct($message);
    }
}
