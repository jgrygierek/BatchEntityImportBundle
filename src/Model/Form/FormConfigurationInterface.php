<?php

namespace JG\BatchImportBundle\Model\Form;

interface FormConfigurationInterface
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
}
