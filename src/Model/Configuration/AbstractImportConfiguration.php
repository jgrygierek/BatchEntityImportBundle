<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Configuration;

use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\Validator\Constraint;

/**
 * Default values of configuration, only class of entity has to be defined.
 * Configuration only describes import, data is saved by the bundle, so configuration has no dependencies
 * and its constructor can be used to inject any services.
 */
abstract class AbstractImportConfiguration implements ImportConfigurationInterface
{
    public function getFieldsDefinitions(): array
    {
        return [];
    }

    /**
     * By default all columns are allowed. It is recommended to override this method,
     * especially when file is prepared by user who should not be able to change every property of entity.
     */
    public function getAllowedColumns(): array
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

    public function allowOverrideEntity(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedFileExtensions(): array
    {
        return ['csv', 'xls', 'xlsx', 'ods'];
    }
}
