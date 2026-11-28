<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Event;

use JG\BatchEntityImportBundle\Event\AfterImportEvent;
use JG\BatchEntityImportBundle\Event\BeforeImportEvent;
use JG\BatchEntityImportBundle\Event\RecordImportedSuccessfullyEvent;
use JG\BatchEntityImportBundle\Event\RecordPreparedEvent;
use JG\BatchEntityImportBundle\Event\RecordValueEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Records events of the bundle, so tests can check them.
 *
 * It is a listener, not a decorator of event dispatcher, so it does not depend on internals of dispatcher
 * (e.g. since Symfony 8.2 TraceableEventDispatcher calls listeners itself, instead of calling decorated dispatcher).
 * Events are stored statically, because kernel is rebooted between requests of test client.
 */
#[AsEventListener(event: BeforeImportEvent::class, method: 'record')]
#[AsEventListener(event: RecordValueEvent::class, method: 'record')]
#[AsEventListener(event: RecordPreparedEvent::class, method: 'record')]
#[AsEventListener(event: RecordImportedSuccessfullyEvent::class, method: 'record')]
#[AsEventListener(event: AfterImportEvent::class, method: 'record')]
final class ImportEventRecorder
{
    /** @var array<class-string, list<object>> */
    private static array $events = [];

    public function record(object $event): void
    {
        self::$events[$event::class][] = $event;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function getEventsFor(string $class): array
    {
        /* @var list<T> */
        return self::$events[$class] ?? [];
    }

    /**
     * @param class-string $class
     */
    public function hasEvent(string $class): bool
    {
        return [] !== $this->getEventsFor($class);
    }

    public function reset(): void
    {
        self::$events = [];
    }
}
