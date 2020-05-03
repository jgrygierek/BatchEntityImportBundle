<?php

namespace JG\BatchEntityImportBundle\Model\Matrix;

class MatrixRecord
{
    private ?object $entity = null;
    private array   $data;

    public function __construct(array $data = [])
    {
        unset($data['entity']);
        $this->data = $data;
    }

    public function getEntity(): ?object
    {
        return $this->entity;
    }

    public function setEntity(?object $entity): void
    {
        $this->entity = $entity;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function __isset($name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __get($name)
    {
        return $this->data[$name];
    }
}
