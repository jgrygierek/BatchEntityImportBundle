<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Form;

class FormFieldDefinition
{
    private string $class;
    private array $options;

    public function __construct(string $class, array $options = [])
    {
        $this->class = $class;
        $this->options = $options;
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
