<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle;

use JG\BatchEntityImportBundle\DependencyInjection\Compiler\AutoConfigureControllerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BatchEntityImportBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AutoConfigureControllerCompilerPass());
    }
}
