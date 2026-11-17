<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Utils;

use Doctrine\Common\Collections\Collection;
use ReflectionClass;
use ReflectionException;

/**
 * Support of translatable entities from optional package "knplabs/doctrine-behaviors".
 * It is the only place which depends on this package, so every method works also when it is not installed.
 *
 * Types of the package are not used here (interface is checked by name and its methods are called dynamically),
 * so code can be analysed statically also without the package.
 *
 * @internal
 */
final class EntityTranslationHelper
{
    // name is a string on purpose (not ::class), because package is optional and the interface may not exist
    private const string TRANSLATABLE_INTERFACE = 'Knp\\DoctrineBehaviors\\Contract\\Entity\\TranslatableInterface';

    /**
     * @param object|class-string $entity
     */
    public static function isTranslatable(object|string $entity): bool
    {
        return \interface_exists(self::TRANSLATABLE_INTERFACE) && is_a($entity, self::TRANSLATABLE_INTERFACE, true);
    }

    /**
     * @param class-string $entityClass
     *
     * @return class-string|null class of entity with translations, null when entity is not translatable
     *
     * @throws ReflectionException
     */
    public static function getTranslationClass(string $entityClass): ?string
    {
        if (!self::isTranslatable($entityClass)) {
            return null;
        }

        $translation = self::call(new ReflectionClass($entityClass)->newInstanceWithoutConstructor(), 'translate');

        return \is_object($translation) ? $translation::class : null;
    }

    /**
     * @return object|null translation of entity in given locale (created when it does not exist), null when entity is not translatable
     */
    public static function getTranslation(object $entity, string $locale): ?object
    {
        $translation = self::isTranslatable($entity) ? self::call($entity, 'translate', $locale, false) : null;

        return \is_object($translation) ? $translation : null;
    }

    public static function mergeNewTranslations(object $entity): void
    {
        if (self::isTranslatable($entity)) {
            self::call($entity, 'mergeNewTranslations');
        }
    }

    /**
     * Removes translations which were created, but not merged yet.
     *
     * @return array<object> translations which are left in entity
     */
    public static function clearNewTranslations(object $entity): array
    {
        if (!self::isTranslatable($entity)) {
            return [];
        }

        $newTranslations = self::call($entity, 'getNewTranslations');
        if ($newTranslations instanceof Collection) {
            $newTranslations->clear();
        }

        $translations = self::call($entity, 'getTranslations');

        return array_values(array_filter(is_iterable($translations) ? [...$translations] : [], \is_object(...)));
    }

    private static function call(object $entity, string $method, mixed ...$arguments): mixed
    {
        $callable = [$entity, $method];

        return \is_callable($callable) ? $callable(...$arguments) : null;
    }
}
