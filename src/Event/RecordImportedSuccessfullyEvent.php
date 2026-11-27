<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Event;

use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;

/**
 * Dispatched for each record, after all records are saved in database.
 * Data is already saved, so exception thrown by listener does not revert import.
 */
final readonly class RecordImportedSuccessfullyEvent
{
    /**
     * @param object $entity imported entity
     * @param class-string|string $class class of imported entity
     * @param string|int $id identifier of imported entity, integer identifier is passed without casting
     */
    public function __construct(
        public ImportConfigurationInterface $configuration,
        public object $entity,
        public string $class,
        public string|int $id,
    ) {
    }
}
