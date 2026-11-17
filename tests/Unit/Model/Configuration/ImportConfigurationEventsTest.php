<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Model\Configuration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Generator;
use JG\BatchEntityImportBundle\Event\RecordImportedSuccessfullyEvent;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ImportConfigurationEventsTest extends TestCase
{
    /**
     * @param array<string, mixed> $identifier
     */
    #[DataProvider('identifierProvider')]
    public function testIdentifierOfEntityInEvent(array $identifier, string|int $expectedId): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityIdentifier')->willReturn($identifier);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $events = [];
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (object $event) use (&$events): object {
            $events[] = $event;

            return $event;
        });

        $configuration = new class($entityManager, $eventDispatcher, $this->createStub(RelatedEntityFinder::class)) extends BaseConfiguration {
            public function dispatchEventsFor(object $entity): void
            {
                $this->updatedEntities = [$entity];
                $this->dispatchEvents();
            }
        };
        $configuration->dispatchEventsFor(new TestEntity());

        self::assertEquals([new RecordImportedSuccessfullyEvent(TestEntity::class, $expectedId)], $events);
        self::assertSame($expectedId, $events[0]->id);
    }

    public static function identifierProvider(): Generator
    {
        yield 'integer' => [['id' => 5], 5];
        yield 'string' => [['id' => 'abc'], 'abc'];
        yield 'numeric string' => [['id' => '5'], '5'];
        yield 'stringable object, e.g. UUID' => [['id' => new class implements Stringable {
            public function __toString(): string
            {
                return '0193a0e2-7b1c-7c3e-9f4a-1d2e3f4a5b6c';
            }
        }], '0193a0e2-7b1c-7c3e-9f4a-1d2e3f4a5b6c'];
        yield 'composite identifier, first value is used' => [['first' => 1, 'second' => 'b'], 1];
        yield 'not supported identifier' => [['id' => new stdClass()], ''];
        yield 'no identifier' => [[], ''];
    }
}
