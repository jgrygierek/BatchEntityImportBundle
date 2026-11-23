<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Event;

use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;

/**
 * Dispatched for each record, after all values are set to entity and before entity is persisted.
 * Listener can change the entity, e.g. set values which are not imported from file.
 *
 * Exception thrown by listener stops import and changes made so far are reverted.
 */
final readonly class RecordPreparedEvent
{
    /**
     * @param int $rowNumber number of record, starting from 1, as in the preview
     */
    public function __construct(
        public ImportConfigurationInterface $configuration,
        public MatrixRecord $record,
        public object $entity,
        public int $rowNumber,
    ) {
    }
}
