<?php

namespace JG\BatchImportBundle\Model;

use UnexpectedValueException;

class MatrixRecord
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
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
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        throw new UnexpectedValueException("Column $name does not exist.");
    }
}
