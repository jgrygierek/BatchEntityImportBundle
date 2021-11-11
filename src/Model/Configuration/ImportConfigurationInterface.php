<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Configuration;

use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;

interface ImportConfigurationInterface
{
    /**
     * Class of entity used during import process.
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
     * @return array|FormFieldDefinition[]
     */
    public function getFieldsDefinitions(): array;

    /**
     * Use this method to pass a relation name to entity translation.
     */
    public function getEntityTranslationRelationName(): ?string;

    /**
     * Use this method to pass constraints to the main Matrix form.
     */
    public function getMatrixConstraints(): array;

    /**
     * Import data from matrix to database.
     */
    public function import(Matrix $matrix): void;
}
