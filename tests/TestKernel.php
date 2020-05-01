<?php

namespace JG\BatchEntityImportBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use JG\BatchEntityImportBundle\BatchEntityImportBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    private array $configs = [];

    public function registerBundles(): array
    {
        return [new BatchEntityImportBundle(), new DoctrineBundle(), new FrameworkBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/config_test.yaml');

        foreach ($this->configs as $config) {
            $loader->load($config);
        }
    }

    public function setConfigs(array $configs): void
    {
        $this->configs = $configs;
    }
}
