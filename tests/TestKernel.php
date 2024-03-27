<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use JG\BatchEntityImportBundle\BatchEntityImportBundle;
use Knp\DoctrineBehaviors\DoctrineBehaviorsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): array
    {
        $bundles = [
            new SecurityBundle(),
            new BatchEntityImportBundle(),
            new DoctrineBundle(),
            new FrameworkBundle(),
            new TwigBundle(),
        ];

        if (\class_exists(DoctrineBehaviorsBundle::class)) {
            $bundles[] = new DoctrineBehaviorsBundle();
        }

        return $bundles;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/config/routes.yaml');
        if (\class_exists(DoctrineBehaviorsBundle::class)) {
            $routes->import(__DIR__ . '/KnpLabs/config/routes.yaml');
        }
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/config.yaml');
        if (\class_exists(DoctrineBehaviorsBundle::class)) {
            $loader->load(__DIR__ . '/KnpLabs/config/config.yaml');
        }

        if (self::MAJOR_VERSION === 7) {
            $container->extension('framework', [
                'validation' => ['enable_attributes' => true],
            ]);
        }
    }
}
