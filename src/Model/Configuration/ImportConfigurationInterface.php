<?php

namespace JG\BatchImportBundle\Model\Configuration;

use JG\BatchImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchImportBundle\Model\Matrix\MatrixRecord;

interface ImportConfigurationInterface
{
    /**
     * Class of entity used during import process.
     *
     * @return string
     */
    public function getEntityClassName(): string;

    /**
     * Used to define field definitions used during process of data editing.
     * If definition for field will not be defined, default definition will be used.
     *
     * @return array|FormFieldDefinition[]
     */
    public function getFieldsDefinitions(): array;

    /**
     * Used to prepare data from one record and make update/insert query.
     *
     * @param MatrixRecord $record
     */
    public function prepareRecord(MatrixRecord $record): void;

    /**
     * Saves data from matrix to database.
     */
    public function save(): void;
}
