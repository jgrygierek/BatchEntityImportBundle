<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Service;

use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use JG\BatchEntityImportBundle\Utils\EntityTranslationHelper;
use PropertyHookType;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

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
     * Value is set by public setter, or directly to property, which can be written from outside of the class
     * (the same rules are used by EntityImporter). Otherwise column is skipped, instead of error during import.
     *
     * @param ReflectionClass<covariant object> $entity
     */
    private function isPropertyWritable(ReflectionClass $entity, string $name): bool
    {
        $setterName = ColumnNameHelper::getSetterName($name);
        if ($entity->hasMethod($setterName) && $entity->getMethod($setterName)->isPublic()) {
            return true;
        }

        return $entity->hasProperty($name) && self::isWritableFromOutside($entity->getProperty($name));
    }

    /**
     * Public property can't be written from outside of the class, when it is readonly, has asymmetric visibility
     * (e.g. "public private(set)"), or it is virtual property with hooks, but without "set" hook.
     */
    private static function isWritableFromOutside(ReflectionProperty $property): bool
    {
        if (!$property->isPublic() || $property->isStatic() || $property->isReadOnly() || $property->isPrivateSet() || $property->isProtectedSet()) {
            return false;
        }

        return !$property->isVirtual() || $property->hasHook(PropertyHookType::Set);
    }
}
