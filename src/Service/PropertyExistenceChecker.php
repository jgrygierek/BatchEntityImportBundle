<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Service;

use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Knp\DoctrineBehaviors\Contract\Entity\TranslationInterface;
use ReflectionClass;
use ReflectionException;

class PropertyExistenceChecker
{
    private readonly ReflectionClass $reflectionClass;
    private ?ReflectionClass $translationReflectionClass = null;

    /**
     * @param class-string $entityClass
     *
     * @throws ReflectionException
     */
    public function __construct(string $entityClass)
    {
        $this->reflectionClass = new ReflectionClass($entityClass);
        if (
            \interface_exists(TranslationInterface::class)
            && \interface_exists(TranslatableInterface::class)
            && is_subclass_of($entityClass, TranslatableInterface::class)
        ) {
            /** @var TranslatableInterface $instance */
            $instance = $this->reflectionClass->newInstanceWithoutConstructor();
            /** @var TranslationInterface $translationInstance */
            $translationInstance = $instance->translate();
            $this->translationReflectionClass = new ReflectionClass($translationInstance::class);
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
        return $this->translationReflectionClass instanceof ReflectionClass && $this->isPropertyWritable($this->translationReflectionClass, $name);
    }

    /**
     * @param ReflectionClass<object> $entity
     */
    private function isPropertyWritable(ReflectionClass $entity, string $name): bool
    {
        $setterName = ColumnNameHelper::getSetterName($name);

        return ($entity->hasProperty($name) && $entity->getProperty($name)->isPublic())
            || ($entity->hasMethod($setterName) && $entity->getMethod($setterName)->isPublic());
    }
}
