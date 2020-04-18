<?php

namespace JG\BatchEntityImportBundle\Model\Form;

class FormFieldDefinition
{
    private string $name;
    private string $class;
    private array  $options;

    public function __construct(string $name, string $class, array $options = [])
    {
        $this->name    = $name;
        $this->class   = $class;
        $this->options = $options;
    }

    public function getName(): string
    {
        return $this->name;
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
