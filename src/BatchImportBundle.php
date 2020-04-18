<?php

namespace JG\BatchImportBundle;

use JG\BatchImportBundle\DependencyInjection\Compiler\AutoConfigureCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BatchImportBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new AutoConfigureCompilerPass());
    }
}
