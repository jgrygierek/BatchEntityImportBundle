<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Controller;

use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;

interface ImportConfigurationAutoInjectInterface
{
    /**
     * @param array|ImportConfigurationInterface[] $importConfigurations
     */
    public function setImportConfiguration(array $importConfigurations): void;
}
