<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Form;

class FormFieldDefinition
{
    public function __construct(private readonly string $class, private readonly array $options = [])
    {
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
