<?php

namespace JG\BatchEntityImportBundle\Model\Configuration;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Utils\StringHelper;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;

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

    public function prepareRecord(MatrixRecord $record): void
    {
        $entity = $this->getEntity($record);
        $data   = $record->getData();

        foreach ($data as $name => $value) {
            $fieldName = StringHelper::underscoreToPascalCase($name);
            $entity->{'set' . $fieldName}($value);
        }

        if ($entity instanceof TranslatableInterface) {
            $entity->mergeNewTranslations();
        }

        $this->em->persist($entity);
    }

    public function save(): void
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
