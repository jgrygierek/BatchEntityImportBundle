<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Utils;

use ErrorException;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use Throwable;

class EncodingHelper
{
    private const string SAMPLE = 'a';

    /**
     * Checks if content in given encoding can be converted to UTF-8.
     * The same conversion is used by PhpSpreadsheet when file is read.
     *
     * Sample is converted to given encoding first, because single byte is not a valid character
     * in every encoding (e.g. UTF-16), so it can't be used directly as a content in this encoding.
     */
    public static function isSupported(string $encoding): bool
    {
        if ('' === $encoding) {
            return false;
        }

        // Unknown encoding is reported by warning or by exception, depending on used extension.
        // Warning is changed to exception, so both cases are handled the same way and nothing is silenced.
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });

        try {
            $encoded = StringHelper::convertEncoding(self::SAMPLE, $encoding, 'UTF-8');

            return '' !== $encoded && self::SAMPLE === StringHelper::convertEncoding($encoded, 'UTF-8', $encoding);
        } catch (Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }
}
