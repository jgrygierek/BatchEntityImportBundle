<?php

namespace JG\BatchEntityImportBundle\Service;

use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use ReflectionClass;
use ReflectionException;

class PropertyExistenceChecker
{
    private ReflectionClass  $reflectionClass;
    private ?ReflectionClass $translationReflectionClass;

    /**
     * @throws ReflectionException
     */
    public function __construct(object $entity)
    {
        $this->reflectionClass = new ReflectionClass($entity);
        $this->translationReflectionClass = $entity instanceof TranslatableInterface ? new ReflectionClass($entity->translate()) : null;
    }

    public function propertyExists(string $name): bool
    {
        $locale = ColumnNameHelper::getLocale($name);
        $name = ColumnNameHelper::underscoreToCamelCase($name);

        return $locale
            ? $this->translationPropertyExists($name)
            : $this->reflectionClass->hasProperty($name);
    }

    private function translationPropertyExists(string $name): bool
    {
        return $this->translationReflectionClass && $this->translationReflectionClass->hasProperty($name);
    }
}
