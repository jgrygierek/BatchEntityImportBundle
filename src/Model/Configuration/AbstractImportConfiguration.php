<?php

namespace JG\BatchEntityImportBundle\Model\Configuration;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Throwable;

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
        foreach ($matrix->getRecords() as $record) {
            $this->prepareRecord($record);
        }

        $this->save();
    }

    protected function prepareRecord(MatrixRecord $record): void
    {
        $entity = $this->getEntity($record);
        $data   = $record->getData();

        foreach ($data as $name => $value) {
            $locale    = ColumnNameHelper::getLocale($name);
            $fieldName = ColumnNameHelper::underscoreToPascalCase($name);

            try {
                if ($entity instanceof TranslatableInterface && $locale) {
                    $entity->translate($locale)->{'set' . $fieldName}($value);
                } elseif (!$locale) {
                    $entity->{'set' . $fieldName}($value);
                }
            } catch (Throwable $e) {
            }
        }

        if ($entity instanceof TranslatableInterface) {
            $entity->mergeNewTranslations();
        }

        $this->em->persist($entity);
    }

    protected function save(): void
    {
        $this->em->flush();
    }

    protected function getEntity(MatrixRecord $record): object
    {
        return $record->getEntity() ?: $this->getNewEntity($record);
    }

    /**
     * Creates new entity object. Uses default constructor without any arguments.
     * To use constructor with arguments, please override this method.
     *
     * @param MatrixRecord $record
     *
     * @return object
     */
    protected function getNewEntity(MatrixRecord $record): object
    {
        $class = $this->getEntityClassName();

        return new $class;
    }
}
