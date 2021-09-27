<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Utils;

class ColumnNameHelper
{
    public static function underscoreToPascalCase(string $value): string
    {
        $value = self::removeTranslationSuffix($value);

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));
    }

    public static function underscoreToCamelCase(string $value): string
    {
        $value = self::removeTranslationSuffix($value);

        return lcfirst(self::underscoreToPascalCase($value));
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
        return 'set' . self::underscoreToPascalCase($name);
    }
}
