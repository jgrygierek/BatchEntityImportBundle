<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Matrix;

class MatrixRecord
{
    private ?object $entity = null;
    private array $data = [];

    public function __construct(array $data = [])
    {
        foreach ($data as $name => $value) {
            if (!empty(\trim((string) $name))) {
                $this->data[\str_replace(' ', '_', (string) $name)] = $value;
            }
        }
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

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * @param string|int|null $value
     */
    public function __set(string $name, $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * @return string|int|null
     */
    public function __get(string $name)
    {
        return $this->data[$name];
    }
}
