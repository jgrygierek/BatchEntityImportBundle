<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Service;

use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use ReflectionClass;
use ReflectionException;

class PropertyExistenceChecker
{
    private ReflectionClass $reflectionClass;
    private ?ReflectionClass $translationReflectionClass = null;

    /**
     * @throws ReflectionException
     */
    public function __construct(string $entityClass)
    {
        $this->reflectionClass = new ReflectionClass($entityClass);
        if (is_subclass_of($entityClass, TranslatableInterface::class)) {
            $this->translationReflectionClass = new ReflectionClass($this->reflectionClass->newInstanceWithoutConstructor()->translate());
        }
    }

    public function propertyExists(string $name): bool
    {
        $locale = ColumnNameHelper::getLocale($name);
        $name = ColumnNameHelper::toCamelCase($name);

        return $locale
            ? $this->translationPropertyExists($name)
            : $this->isPropertyWritable($this->reflectionClass, $name);
    }

    private function translationPropertyExists(string $name): bool
    {
        return $this->translationReflectionClass && $this->isPropertyWritable($this->translationReflectionClass, $name);
    }

    private function isPropertyWritable(ReflectionClass $entity, string $name): bool
    {
        $setterName = ColumnNameHelper::getSetterName($name);

        return ($entity->hasProperty($name) && $entity->getProperty($name)->isPublic())
            || ($entity->hasMethod($setterName) && $entity->getMethod($setterName)->isPublic());
    }
}
