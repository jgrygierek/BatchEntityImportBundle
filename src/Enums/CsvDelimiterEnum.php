<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Enums;

enum CsvDelimiterEnum: string
{
    case SEMICOLON = ';';
    case COMMA = ',';

    public static function asValues(): array
    {
        return [
            self::SEMICOLON->value,
            self::COMMA->value,
        ];
    }
}
