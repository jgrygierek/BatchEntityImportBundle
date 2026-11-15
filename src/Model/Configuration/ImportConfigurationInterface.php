<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Configuration;

use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use Symfony\Component\Validator\Constraint;

interface ImportConfigurationInterface
{
    /**
     * Class of entity used during import process.
     *
     * @return class-string
     */
    public function getEntityClassName(): string;

    /**
     * Allow to override entity in the edit view.
     */
    public function allowOverrideEntity(): bool;

    /**
     * Defines fields definitions used during process of data editing.
     * If definition for field will not be defined, default definition will be used.
     *
     * @return array<string, FormFieldDefinition>
     */
    public function getFieldsDefinitions(): array;

    /**
     * Use this method to pass a relation name to entity translation.
     */
    public function getEntityTranslationRelationName(): ?string;

    /**
     * Use this method to pass constraints to the main Matrix form.
     *
     * @return array<int, Constraint>
     */
    public function getMatrixConstraints(): array;

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

    /**
     * Use this method to set allowed file extensions.
     *
     * @return array<string>
     */
    public function getAllowedFileExtensions(): array;

    /**
     * Import data from matrix to database.
     */
    public function import(Matrix $matrix): void;
}
