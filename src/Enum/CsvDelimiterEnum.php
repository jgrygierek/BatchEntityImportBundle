<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Enum;

/**
 * Delimiters recognized in CSV files, used by CsvDelimiterDetector.
 *
 * @internal
 */
enum CsvDelimiterEnum: string
{
    case SEMICOLON = ';';
    case COMMA = ',';
}
