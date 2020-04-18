<?php

namespace JG\BatchImportBundle\DependencyInjection;

use Exception;
use JG\BatchImportBundle\Controller\ImportControllerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class BatchImportExtension extends Extension
{
    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     *
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container
            ->registerForAutoconfiguration(ImportControllerInterface::class)
            ->addTag('batch_import.controller');
    }
}
