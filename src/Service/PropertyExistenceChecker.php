<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Service;

use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use ReflectionClass;

class PropertyExistenceChecker
{
    private ReflectionClass  $reflectionClass;
    private ?ReflectionClass $translationReflectionClass = null;

    public function __construct($entity)
    {
        $this->reflectionClass = new ReflectionClass($entity);
        if (is_subclass_of($entity, TranslatableInterface::class)) {
            $this->translationReflectionClass = new ReflectionClass($this->reflectionClass->newInstanceWithoutConstructor()->translate());
        }
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
