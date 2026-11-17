<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Event;

readonly class RecordImportedSuccessfullyEvent
{
    /**
     * @param class-string|string $class class of imported entity
     * @param string|int $id identifier of imported entity, integer identifier is passed without casting
     */
    public function __construct(public string $class, public string|int $id)
    {
    }
}
