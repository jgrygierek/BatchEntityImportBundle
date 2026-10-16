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
use PHPUnit\Framework\TestCase;
use stdClass;

class RelatedEntityFinderTest extends TestCase
{
    private MockObject&ObjectManager $managerMock;
    /** @var MockObject&ObjectRepository<TestRelatedEntity> */
    private MockObject&ObjectRepository $repositoryMock;
    /** @var MockObject&ClassMetadata<TestRelatedEntity> */
    private MockObject&ClassMetadata $metadataMock;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(ObjectRepository::class);
        $this->metadataMock = $this->createMock(ClassMetadata::class);
        $this->metadataMock->method('hasField')->willReturnCallback(static fn (string $field): bool => \in_array($field, ['id', 'name'], true));

        $this->managerMock = $this->createMock(ObjectManager::class);
        $this->managerMock->method('getRepository')->willReturn($this->repositoryMock);
        $this->managerMock->method('getClassMetadata')->willReturn($this->metadataMock);
    }

    public function testFindWithRegistry(): void
    {
        $entity = new TestRelatedEntity('first');
        $this->repositoryMock->expects(self::once())->method('findOneBy')->with(['name' => 'first'])->willReturn($entity);

        self::assertSame($entity, $this->createFinderWithRegistry()->find(TestRelatedEntity::class, 'name', 'first'));
    }

    public function testFindWithSingleManager(): void
    {
        $metadataFactoryMock = $this->createMock(ClassMetadataFactory::class);
        $metadataFactoryMock->method('isTransient')->willReturnCallback(static fn (string $class): bool => TestRelatedEntity::class !== $class);
        $this->managerMock->method('getMetadataFactory')->willReturn($metadataFactoryMock);

        $entity = new TestRelatedEntity('first');
        $this->repositoryMock->method('findOneBy')->willReturn($entity);

        $finder = new RelatedEntityFinder($this->managerMock);
        self::assertSame($entity, $finder->find(TestRelatedEntity::class, 'name', 'first'));

        $this->expectException(InvalidArgumentException::class);
        $finder->find(stdClass::class, 'name', 'first');
    }

    public function testNotManagedClass(): void
    {
        $registryMock = $this->createMock(ManagerRegistry::class);
        $registryMock->method('getManagerForClass')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "stdClass" is not a managed Doctrine entity.');

        (new RelatedEntityFinder($registryMock))->getManager(stdClass::class, 'id');
    }

    public function testNotExistingClassWithSingleManager(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RelatedEntityFinder($this->managerMock))->getManager('App\NotExistingClass', 'id');
    }

    public function testNotExistingField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Field "unknown" does not exist in entity "%s".', TestRelatedEntity::class));

        $this->createFinderWithRegistry()->getManager(TestRelatedEntity::class, 'unknown');
    }

    public function testResultsAreCached(): void
    {
        $this->managerMock->method('contains')->willReturn(true);
        $this->repositoryMock
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
        $this->managerMock->method('contains')->willReturn(true);
        $this->repositoryMock->expects(self::exactly(2))->method('findOneBy')->willReturn(new TestRelatedEntity('1'));

        $finder = $this->createFinderWithRegistry();
        $finder->find(TestRelatedEntity::class, 'name', '1');
        $finder->find(TestRelatedEntity::class, 'id', '1');
    }

    public function testDetachedEntityIsFetchedAgain(): void
    {
        $detachedEntity = new TestRelatedEntity('first');
        $managedEntity = new TestRelatedEntity('first');
        $this->managerMock->method('contains')->willReturnCallback(static fn (object $entity): bool => $entity === $managedEntity);
        $this->repositoryMock->method('findOneBy')->willReturnOnConsecutiveCalls($detachedEntity, $managedEntity);

        $finder = $this->createFinderWithRegistry();
        self::assertSame($detachedEntity, $finder->find(TestRelatedEntity::class, 'name', 'first'));
        self::assertSame($managedEntity, $finder->find(TestRelatedEntity::class, 'name', 'first'));
        self::assertSame($managedEntity, $finder->find(TestRelatedEntity::class, 'name', 'first'));
    }

    public function testResetClearsCache(): void
    {
        $this->managerMock->method('contains')->willReturn(true);
        $this->repositoryMock->expects(self::exactly(2))->method('findOneBy')->willReturn(new TestRelatedEntity('first'));

        $finder = $this->createFinderWithRegistry();
        $finder->find(TestRelatedEntity::class, 'name', 'first');
        $finder->find(TestRelatedEntity::class, 'name', 'first');
        $finder->reset();
        $finder->find(TestRelatedEntity::class, 'name', 'first');
    }

    private function createFinderWithRegistry(): RelatedEntityFinder
    {
        $registryMock = $this->createMock(ManagerRegistry::class);
        $registryMock->method('getManagerForClass')->willReturn($this->managerMock);

        return new RelatedEntityFinder($registryMock);
    }

    public function testManagerIsResolvedOnce(): void
    {
        $registryMock = $this->createMock(ManagerRegistry::class);
        $registryMock->expects(self::once())->method('getManagerForClass')->willReturn($this->managerMock);
        $this->repositoryMock->method('findOneBy')->willReturn(null);

        $finder = new RelatedEntityFinder($registryMock);
        foreach (['first', 'second', 'third'] as $value) {
            $finder->find(TestRelatedEntity::class, 'name', $value);
        }
    }
}
