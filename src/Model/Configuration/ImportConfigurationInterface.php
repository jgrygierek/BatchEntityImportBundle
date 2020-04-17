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
     * Defines fields definitions used during process of data editing.
     * If definition for field will not be defined, default definition will be used.
     *
     * @return array|FormFieldDefinition[]
     */
    public function getFieldsDefinitions(): array;

    /**
     * Prepares data from one record and updates existing or inserts new record.
     *
     * @param MatrixRecord $record
     */
    public function prepareRecord(MatrixRecord $record): void;

    /**
     * Saves all prepared data to database.
     */
    public function save(): void;
}
