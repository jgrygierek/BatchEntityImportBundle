<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Utils;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Generator;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Utils\EntityManagerHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntityManagerHelperTest extends TestCase
{
    public function testManagerOfEntityIsReturned(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);

        self::assertSame($entityManager, EntityManagerHelper::getEntityManager($this->createRegistry($entityManager), TestEntity::class));
    }

    #[DataProvider('notManagedClassProvider')]
    public function testNotManagedEntityException(string $class, bool $isOrmManager): void
    {
        $manager = $isOrmManager ? $this->createStub(EntityManagerInterface::class) : $this->createStub(ObjectManager::class);

        $this->expectExceptionObject(new InvalidArgumentException(\sprintf('Class "%s" is not a managed Doctrine entity.', $class)));
        EntityManagerHelper::getEntityManager($this->createRegistry($manager), $class);
    }

    public static function notManagedClassProvider(): Generator
    {
        yield 'class without manager' => [self::class, true];
        yield 'not existing class' => ['App\Entity\NotExisting', true];
        yield 'manager other than Doctrine ORM' => [TestEntity::class, false];
    }

    private function createRegistry(ObjectManager $manager): ManagerRegistry
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(
            static fn (string $class): ?ObjectManager => TestEntity::class === $class ? $manager : null,
        );

        return $registry;
    }
}
