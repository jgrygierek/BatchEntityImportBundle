<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Utils;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;

/**
 * @internal
 */
final class EntityManagerHelper
{
    /**
     * Manager is taken from registry, so entities managed by other manager than the default one are supported too.
     *
     * @throws InvalidArgumentException when class is not an entity managed by Doctrine ORM
     */
    public static function getEntityManager(ManagerRegistry $managerRegistry, string $class): EntityManagerInterface
    {
        // checked before asking registry, which would throw ReflectionException for not existing class
        $manager = \class_exists($class) ? $managerRegistry->getManagerForClass($class) : null;
        if (!$manager instanceof EntityManagerInterface) {
            throw new InvalidArgumentException(\sprintf('Class "%s" is not a managed Doctrine entity.', $class));
        }

        return $manager;
    }
}
