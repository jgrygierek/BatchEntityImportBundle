<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Service;

use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use JG\BatchEntityImportBundle\Utils\EntityTranslationHelper;
use ReflectionClass;
use ReflectionException;

/**
 * @internal
 */
final readonly class PropertyExistenceChecker
{
    /** @var ReflectionClass<object> */
    private ReflectionClass $reflectionClass;
    /** @var ReflectionClass<object>|null */
    private ?ReflectionClass $translationReflectionClass;

    /**
     * @param class-string $entityClass
     *
     * @throws ReflectionException
     */
    public function __construct(string $entityClass)
    {
        $this->reflectionClass = new ReflectionClass($entityClass);
        $translationClass = EntityTranslationHelper::getTranslationClass($entityClass);
        $this->translationReflectionClass = null !== $translationClass ? new ReflectionClass($translationClass) : null;
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
        return $this->translationReflectionClass instanceof ReflectionClass && $this->isPropertyWritable($this->translationReflectionClass, $name);
    }

    /**
     * @param ReflectionClass<covariant object> $entity
     */
    private function isPropertyWritable(ReflectionClass $entity, string $name): bool
    {
        $setterName = ColumnNameHelper::getSetterName($name);

        return ($entity->hasProperty($name) && $entity->getProperty($name)->isPublic())
            || ($entity->hasMethod($setterName) && $entity->getMethod($setterName)->isPublic());
    }
}
