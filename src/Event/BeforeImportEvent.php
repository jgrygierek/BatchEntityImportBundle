<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Event;

use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;

/**
 * Dispatched once, before any record is imported.
 * Exception thrown by listener stops import, nothing is saved.
 */
final readonly class BeforeImportEvent
{
    public function __construct(
        public ImportConfigurationInterface $configuration,
        public Matrix $matrix,
    ) {
    }
}
