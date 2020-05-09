<?php

namespace JG\BatchEntityImportBundle\Model\Configuration;

use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;

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
     * Import data from matrix to database.
     *
     * @param Matrix $matrix
     */
    public function import(Matrix $matrix): void;
}
