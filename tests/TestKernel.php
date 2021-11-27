<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use JG\BatchEntityImportBundle\BatchEntityImportBundle;
use Knp\DoctrineBehaviors\DoctrineBehaviorsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\RouteCollectionBuilder;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private array $configs = [];

    public function registerBundles(): array
    {
        return [
            new SecurityBundle(),
            new BatchEntityImportBundle(),
            new DoctrineBundle(),
            new FrameworkBundle(),
            new TwigBundle(),
            new DoctrineBehaviorsBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/config_test.yaml');

        foreach ($this->configs as $config) {
            $loader->load($config);
        }

        $loader->load(function (ContainerBuilder $container) use ($loader): void {
            $container->loadFromExtension('framework', [
                'router' => [
                    'resource' => 'kernel::loadRoutes',
                    'type' => 'service',
                ],
                'session' => property_exists(WebTestCase::class, 'container')
                    ? ['storage_id' => 'session.storage.mock_file']
                    : ['storage_factory_id' => 'session.storage.factory.mock_file'],
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

    /**
     * @param RoutingConfigurator|RouteCollectionBuilder $routes
     */
    protected function configureRoutes($routes): void
    {
        $routes->import(__DIR__ . '/config/routes.yaml');
    }

    protected function configureContainer(ContainerBuilder $containerBuilder, LoaderInterface $loader): void
    {
    }
}
