<?php

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Configuration;

use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity;

class BaseConfiguration extends AbstractImportConfiguration
{
    public function getEntityClassName(): string
    {
        return Entity::class;
    }
}
