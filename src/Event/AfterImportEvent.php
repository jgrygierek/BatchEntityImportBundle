<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Event;

use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;

/**
 * Dispatched once, after all records are saved in database and RecordImportedSuccessfullyEvent is dispatched for each of them.
 * Data is already saved, so exception thrown by listener does not revert import.
 */
final readonly class AfterImportEvent
{
    /**
     * @param list<object> $entities imported entities, one for each record
     */
    public function __construct(
        public ImportConfigurationInterface $configuration,
        public array $entities,
    ) {
    }
}
