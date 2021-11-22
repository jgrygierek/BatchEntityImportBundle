<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Controller;

use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @property ImportConfigurationInterface $importConfiguration
 */
trait ImportConfigurationAutoInjectTrait
{
    public function setImportConfiguration(array $importConfigurations): void
    {
        $class = $this->getImportConfigurationClassName();
        if (!isset($importConfigurations[$class])) {
            throw new ServiceNotFoundException($class);
        }

        $this->importConfiguration = $importConfigurations[$class];
    }
}
