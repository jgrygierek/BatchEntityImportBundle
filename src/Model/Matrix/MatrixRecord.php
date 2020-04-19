<?php

namespace JG\BatchEntityImportBundle\Model\Matrix;

use UnexpectedValueException;

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

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function __isset($name): bool
    {
        return isset($this->data[$name]);
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        throw new UnexpectedValueException("Column $name does not exist.");
    }
}
