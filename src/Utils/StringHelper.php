<?php

namespace JG\BatchImportBundle\Utils;

class StringHelper
{
    public static function underscoreToPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));
    }
}
