<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Configuration;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Exception\DatabaseException;
use JG\BatchEntityImportBundle\Exception\DatabaseNotUniqueDataException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use TypeError;

abstract class AbstractImportConfiguration implements ImportConfigurationInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getFieldsDefinitions(): array
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

        foreach ($data as $name => $value) {
            if (empty($headerInfo[$name])) {
                continue;
            }

            $locale = ColumnNameHelper::getLocale($name);
            $propertyName = ColumnNameHelper::underscoreToCamelCase($name);
            $setterName = ColumnNameHelper::getSetterName($name);

            try {
                if ($entity instanceof TranslatableInterface && $locale) {
                    $translatedEntity = $entity->translate($locale);
                    if (method_exists($translatedEntity, $setterName)) {
                        $translatedEntity->$setterName($value);
                    } else {
                        $translatedEntity->$propertyName = $value;
                    }
                } elseif (!$locale) {
                    if (method_exists($entity, $setterName)) {
                        $entity->$setterName($value);
                    } else {
                        $entity->$propertyName = $value;
                    }
                }
            } catch (TypeError $e) {
                throw new MatrixRecordInvalidDataTypeException();
            }
        }

        $this->em->persist($entity);

        if ($entity instanceof TranslatableInterface) {
            $entity->mergeNewTranslations();
        }
    }

    protected function save(): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new DatabaseNotUniqueDataException();
        } catch (Exception $e) {
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
