<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Configuration;

use TypeError;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Exception\DatabaseException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use JG\BatchEntityImportBundle\Exception\DatabaseNotUniqueDataException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;

abstract class AbstractImportConfiguration implements ImportConfigurationInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getFieldsDefinitions(): array
    {
        return [];
    }

    public function getEntityTranslationRelationName(): ?string
    {
        return null;
    }

    public function getMatrixConstraints(): array
    {
        return [];
    }

    public function import(Matrix $matrix): void
    {
        $headerInfo = $matrix->getHeaderInfo($this->getEntityClassName());
        foreach ($matrix->getRecords() as $record) {
            $this->prepareRecord($record, $headerInfo);
        }

        $this->save();
    }

    protected function prepareRecord(MatrixRecord $record, array $headerInfo): void
    {
        $entity = $this->getEntity($record);
        $data = $record->getData();
        $fieldDefinitions = $this->getFieldsDefinitions();

        foreach ($data as $name => $value) {
            if (empty($headerInfo[$name])) {
                continue;
            }

            $locale = ColumnNameHelper::getLocale($name);
            $propertyName = ColumnNameHelper::toCamelCase($name);
            $setterName = ColumnNameHelper::getSetterName($name);

            try {
                if (\interface_exists(TranslatableInterface::class) && $entity instanceof TranslatableInterface && $locale) {
                    $translatedEntity = $entity->translate($locale, false);
                    if (method_exists($translatedEntity, $setterName)) {
                        $translatedEntity->$setterName($value);
                    } else {
                        $translatedEntity->$propertyName = $value;
                    }
                } elseif (!$locale) {
                    if (method_exists($entity, $setterName)) {
                        
                        $reflection = new \ReflectionMethod($entity, $setterName);
                        $params = $reflection->getParameters();

                        if (!empty($params)) {
                            if (isset($fieldDefinitions[$name])) {
                                
                                $fieldDefinition = $fieldDefinitions[$name];
                                $fieldType = $fieldDefinition->getClass();
                                $fieldOptions = $fieldDefinition->getOptions();

                                if ($fieldType === ArrayTextType::class) {
                                    $separator = $fieldOptions['separator'];
                                    $value = explode($separator, $value);
                                }
                            }
                        }
                        $entity->$setterName($value);
                    } else {
                        $entity->$propertyName = $value;
                    }
                }
            } catch (TypeError) {
                throw new MatrixRecordInvalidDataTypeException();
            }
        }

        $this->em->persist($entity);

        if (\interface_exists(TranslatableInterface::class) && $entity instanceof TranslatableInterface) {
            $entity->mergeNewTranslations();
        }
    }

    protected function save(): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new DatabaseNotUniqueDataException();
        } catch (Exception) {
            throw new DatabaseException();
        }
    }

    protected function getEntity(MatrixRecord $record): object
    {
        return $record->getEntity() ?: $this->getNewEntity($record);
    }

    /**
     * Creates new entity object. Uses default constructor without any arguments.
     * To use constructor with arguments, please override this method.
     */
    protected function getNewEntity(MatrixRecord $record): object
    {
        $class = $this->getEntityClassName();

        return new $class();
    }

    public function allowOverrideEntity(): bool
    {
        return true;
    }
}
