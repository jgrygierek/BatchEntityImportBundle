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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private array $configs = [];

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

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/config.yaml');
        if (\class_exists(DoctrineBehaviorsBundle::class)) {
            $loader->load(__DIR__ . '/KnpLabs/config/config.yaml');
        }

        foreach ($this->configs as $config) {
            $loader->load($config);
        }

        $loader->load(function (ContainerBuilder $container) use ($loader): void {
            $container->loadFromExtension('framework', [
                'router' => [
                    'resource' => 'kernel::loadRoutes',
                    'type' => 'service',
                ],
            ]);

            if (!$container->hasDefinition('kernel')) {
                $container
                    ->register('kernel', static::class)
                    ->setSynthetic(true)
                    ->setPublic(true);
            }

            $kernelDefinition = $container->getDefinition('kernel');
            $kernelDefinition->addTag('routing.route_loader');

            if ($this instanceof EventSubscriberInterface) {
                $kernelDefinition->addTag('kernel.event_subscriber');
            }

            $this->configureContainer($container, $loader);

            $container->addObjectResource($this);
        });
    }

    public function setConfigs(array $configs): void
    {
        $this->configs = $configs;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__ . '/config/routes.yaml');
        if (\class_exists(DoctrineBehaviorsBundle::class)) {
            $routes->import(__DIR__ . '/KnpLabs/config/routes.yaml');
        }
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
    }
}
