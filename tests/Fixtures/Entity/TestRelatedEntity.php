<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Entity]
class TestRelatedEntity extends AbstractEntity implements Stringable
{
    #[ORM\Column(type: 'string', unique: true)]
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return 'Label ' . $this->name;
    }
}
