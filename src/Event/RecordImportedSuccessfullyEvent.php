<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Event;

readonly class RecordImportedSuccessfullyEvent
{
    public function __construct(public string $class, public string $id)
    {
    }
}
