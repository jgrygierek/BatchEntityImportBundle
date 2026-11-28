<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Configuration;

use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\Validator\Constraint;

/**
 * Default values of configuration. Class of entity and list of allowed columns have to be defined,
 * there is no safe default value for them.
 * Configuration only describes import, data is saved by the bundle, so configuration has no dependencies
 * and its constructor can be used to inject any services.
 */
abstract class AbstractImportConfiguration implements ImportConfigurationInterface
{
    public function getFieldsDefinitions(): array
    {
        return [];
    }

    public function getEntityTranslationRelationName(): ?string
    {
        return null;
    }

    public function getOverrideEntityFieldDefinition(): ?FormFieldDefinition
    {
        return null;
    }

    /**
     * @return array<int, Constraint>
     */
    public function getMatrixConstraints(): array
    {
        return [];
    }

    /**
     * Uses default constructor without any arguments.
     * To use constructor with arguments, please override this method.
     */
    public function getNewEntity(MatrixRecord $record): object
    {
        $class = $this->getEntityClassName();

        return new $class();
    }

    /**
     * By default records are always imported as new entities. Updating existing entities has to be enabled explicitly,
     * because user who prepares the file can select any entity in the preview.
     */
    public function allowOverrideEntity(): bool
    {
        return false;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedFileExtensions(): array
    {
        return ['csv', 'xls', 'xlsx', 'ods'];
    }
}
