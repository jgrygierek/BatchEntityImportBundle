<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Form;

readonly class FormFieldDefinition
{
    public function __construct(private string $class, private array $options = [])
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
