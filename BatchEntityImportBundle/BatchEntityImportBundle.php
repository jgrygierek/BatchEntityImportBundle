<?php

namespace JG\BatchEntityImportBundle;

use JG\BatchEntityImportBundle\DependencyInjection\Compiler\AutoConfigureCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BatchEntityImportBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new AutoConfigureCompilerPass());
    }
}
