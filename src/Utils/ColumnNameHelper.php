<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Utils;

class ColumnNameHelper
{
    /**
     * Name of column used in matrix: without surrounding whitespaces and with spaces replaced by underscores.
     */
    public static function normalize(string|int|null $value): string
    {
        return str_replace(' ', '_', trim((string) $value));
    }

    public static function toPascalCase(string $value): string
    {
        $value = self::removeTranslationSuffix($value);

        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }

    public static function toCamelCase(string $value): string
    {
        $value = self::removeTranslationSuffix($value);

        return lcfirst(self::toPascalCase($value));
    }

    public static function removeTranslationSuffix(string $value): string
    {
        return explode(':', $value)[0];
    }

    public static function getLocale(string $value): ?string
    {
        return explode(':', $value)[1] ?? null;
    }

    public static function getSetterName(string $name): string
    {
        return 'set' . self::toPascalCase($name);
    }
}
