<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Configuration;

/**
 * Separate interface, because adding new method to ImportConfigurationInterface would break
 * configurations which implement it directly. It will be merged with ImportConfigurationInterface in next major version.
 */
interface AllowedColumnsConfigurationInterface
{
    /**
     * Use this method to limit columns which can be imported.
     *
     * Without it every column of file is imported, if entity has public property or setter with the same name.
     * So user who prepares the file can set any of such properties, also these which should not be changed
     * by import (e.g. roles or password of user).
     *
     * Columns which are not on the list are treated the same way as unknown columns:
     * they are marked in preview and skipped during import.
     *
     * Names are compared the same way as names of columns with names of properties, so "first_name", "first-name",
     * "first name" and "firstName" mean the same column. Locale of translated column is ignored,
     * so "name" allows also "name:en" and "name:pl".
     *
     * @return array<string> list of names of columns, empty list means that all columns are allowed
     */
    public function getAllowedColumns(): array;
}
