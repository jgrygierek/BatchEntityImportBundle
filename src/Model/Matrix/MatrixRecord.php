<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Matrix;

class MatrixRecord
{
    private ?object $entity = null;
    /**
     * @var array<string, scalar|null>
     */
    private array $data = [];

    /**
     * @param array<string, scalar|null> $data
     */
    public function __construct(array $data = [], public readonly int|string|null $entityId = null)
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

    /**
     * @return array<string, scalar|null>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function __set(string $name, int|string|float|bool|null $value): void
    {
        $this->data[$name] = $value;
    }

    public function __get(string $name): int|string|float|bool|null
    {
        return $this->data[$name];
    }
}
