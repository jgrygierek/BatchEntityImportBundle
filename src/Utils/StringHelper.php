<?php

namespace JG\BatchEntityImportBundle\Utils;

class StringHelper
{
    public static function underscoreToPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));
    }

    public static function underscoreToCamelCase(string $value): string
    {
        return lcfirst(self::underscoreToPascalCase($value));
    }
}
