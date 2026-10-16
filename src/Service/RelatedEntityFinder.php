<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Service;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use InvalidArgumentException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Finds related entities by value of lookup field (e.g. id, name, slug).
 * Results are cached, so values repeated in many records are queried only once.
 */
class RelatedEntityFinder implements ResetInterface
{
    public const DEFAULT_LOOKUP_FIELD = 'id';

    /** @var array<string, array<string, object|null>> */
    private array $cache = [];
    /** @var array<string, ObjectManager> */
    private array $managersCache = [];

    /**
     * @param ManagerRegistry|ObjectManager $managers registry, or single manager used for all classes
     */
    public function __construct(private readonly ManagerRegistry|ObjectManager $managers)
    {
    }

    /**
     * Resolving manager is not cheap (reflection, mapping driver) and it is needed for every value, so it is cached.
     *
     * @throws InvalidArgumentException
     */
    public function getManager(string $class, string $lookupField): ObjectManager
    {
        return $this->managersCache[$class . '::' . $lookupField] ??= $this->resolveManager($class, $lookupField);
    }

    /**
     * Result depends on cache and database state, so repeated calls with the same arguments may return different results.
     *
     * @phpstan-impure
     *
     * @throws InvalidArgumentException
     */
    public function find(string $class, string $lookupField, string|int|float|bool $value): ?object
    {
        $manager = $this->getManager($class, $lookupField);
        $cacheKey = $class . '::' . $lookupField;
        $valueKey = (string) $value;

        if (\array_key_exists($valueKey, $this->cache[$cacheKey] ?? [])) {
            $entity = $this->cache[$cacheKey][$valueKey];
            // entity cached before EntityManager::clear() is detached and can't be used in relation
            if (null === $entity || $manager->contains($entity)) {
                return $entity;
            }
        }

        /** @var class-string $class */
        $entity = $manager->getRepository($class)->findOneBy([$lookupField => $value]);

        return $this->cache[$cacheKey][$valueKey] = $entity;
    }

    /**
     * Called between requests in long-running processes, so cached results are not stale.
     */
    public function reset(): void
    {
        $this->cache = [];
        $this->managersCache = [];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resolveManager(string $class, string $lookupField): ObjectManager
    {
        // checked before asking registry, which would throw ReflectionException for not existing class
        if (!\class_exists($class) || !$manager = $this->getManagerForClass($class)) {
            throw new InvalidArgumentException(\sprintf('Class "%s" is not a managed Doctrine entity.', $class));
        }

        if (!$manager->getClassMetadata($class)->hasField($lookupField)) {
            throw new InvalidArgumentException(\sprintf('Field "%s" does not exist in entity "%s".', $lookupField, $class));
        }

        return $manager;
    }

    /**
     * @param class-string $class
     */
    private function getManagerForClass(string $class): ?ObjectManager
    {
        if ($this->managers instanceof ManagerRegistry) {
            return $this->managers->getManagerForClass($class);
        }

        return $this->managers->getMetadataFactory()->isTransient($class) ? null : $this->managers;
    }
}
