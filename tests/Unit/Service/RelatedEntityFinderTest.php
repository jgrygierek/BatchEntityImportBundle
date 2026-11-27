<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Service;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestRelatedEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use stdClass;

class RelatedEntityFinderTest extends TestCase
{
    private ObjectManager&Stub $manager;
    /** @var ObjectRepository<TestRelatedEntity>&Stub */
    private ObjectRepository&Stub $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ObjectRepository::class);
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('hasField')->willReturnCallback(static fn (string $field): bool => \in_array($field, ['id', 'name'], true));

        $this->manager = $this->createStub(ObjectManager::class);
        // repository is taken from property, so test can replace it with mock
        $this->manager->method('getRepository')->willReturnCallback(fn (): ObjectRepository => $this->repository);
        $this->manager->method('getClassMetadata')->willReturn($metadata);
    }

    public function testFindWithRegistry(): void
    {
        $entity = new TestRelatedEntity('first');
        $this->mockRepository()->expects(self::once())->method('findOneBy')->with(['name' => 'first'])->willReturn($entity);

        self::assertSame($entity, $this->createFinderWithRegistry()->find(TestRelatedEntity::class, 'name', 'first'));
    }

    public function testFindWithSingleManager(): void
    {
        $metadataFactory = $this->createStub(ClassMetadataFactory::class);
        $metadataFactory->method('isTransient')->willReturnCallback(static fn (string $class): bool => TestRelatedEntity::class !== $class);
        $this->manager->method('getMetadataFactory')->willReturn($metadataFactory);

        $entity = new TestRelatedEntity('first');
        $this->repository->method('findOneBy')->willReturn($entity);

        $finder = new RelatedEntityFinder($this->manager);
        self::assertSame($entity, $finder->find(TestRelatedEntity::class, 'name', 'first'));

        $this->expectException(InvalidArgumentException::class);
        $finder->find(stdClass::class, 'name', 'first');
    }

    public function testNotManagedClass(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Class "stdClass" is not a managed Doctrine entity.');

        new RelatedEntityFinder($registry)->getManager(stdClass::class, 'id');
    }

    public function testNotExistingClassWithSingleManager(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RelatedEntityFinder($this->manager)->getManager('App\NotExistingClass', 'id');
    }

    public function testNotExistingField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs(\sprintf('Field "unknown" does not exist in entity "%s".', TestRelatedEntity::class));

        $this->createFinderWithRegistry()->getManager(TestRelatedEntity::class, 'unknown');
    }

    public function testResultsAreCached(): void
    {
        $this->manager->method('contains')->willReturn(true);
        $this->mockRepository()
            ->expects(self::exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(static fn (array $criteria): ?TestRelatedEntity => 'first' === $criteria['name'] ? new TestRelatedEntity('first') : null);

        $finder = $this->createFinderWithRegistry();
        foreach (['first', 'unknown', 'first', 'unknown', 'first'] as $value) {
            $finder->find(TestRelatedEntity::class, 'name', $value);
        }
    }

    public function testCacheKeyContainsLookupField(): void
    {
        $this->manager->method('contains')->willReturn(true);
        $this->mockRepository()->expects(self::exactly(2))->method('findOneBy')->willReturn(new TestRelatedEntity('1'));

        $finder = $this->createFinderWithRegistry();
        $finder->find(TestRelatedEntity::class, 'name', '1');
        $finder->find(TestRelatedEntity::class, 'id', '1');
    }

    public function testDetachedEntityIsFetchedAgain(): void
    {
        $detachedEntity = new TestRelatedEntity('first');
        $managedEntity = new TestRelatedEntity('first');
        $this->manager->method('contains')->willReturnCallback(static fn (object $entity): bool => $entity === $managedEntity);
        $this->repository->method('findOneBy')->willReturnOnConsecutiveCalls($detachedEntity, $managedEntity);

        $finder = $this->createFinderWithRegistry();
        self::assertSame($detachedEntity, $finder->find(TestRelatedEntity::class, 'name', 'first'));
        self::assertSame($managedEntity, $finder->find(TestRelatedEntity::class, 'name', 'first'));
        self::assertSame($managedEntity, $finder->find(TestRelatedEntity::class, 'name', 'first'));
    }

    public function testResetClearsCache(): void
    {
        $this->manager->method('contains')->willReturn(true);
        $this->mockRepository()->expects(self::exactly(2))->method('findOneBy')->willReturn(new TestRelatedEntity('first'));

        $finder = $this->createFinderWithRegistry();
        $finder->find(TestRelatedEntity::class, 'name', 'first');
        $finder->find(TestRelatedEntity::class, 'name', 'first');
        $finder->reset();
        $finder->find(TestRelatedEntity::class, 'name', 'first');
    }

    public function testManagerIsResolvedOnce(): void
    {
        $registryMock = $this->createMock(ManagerRegistry::class);
        $registryMock->expects(self::once())->method('getManagerForClass')->willReturn($this->manager);
        $this->repository->method('findOneBy')->willReturn(null);

        $finder = new RelatedEntityFinder($registryMock);
        foreach (['first', 'second', 'third'] as $value) {
            $finder->find(TestRelatedEntity::class, 'name', $value);
        }
    }

    private function createFinderWithRegistry(): RelatedEntityFinder
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->manager);

        return new RelatedEntityFinder($registry);
    }

    /**
     * Replaces repository returned by manager with mock, for tests which check calls of repository.
     *
     * @return ObjectRepository<TestRelatedEntity>&MockObject
     */
    private function mockRepository(): ObjectRepository&MockObject
    {
        $repository = $this->createMock(ObjectRepository::class);
        $this->repository = $repository;

        return $repository;
    }
}
