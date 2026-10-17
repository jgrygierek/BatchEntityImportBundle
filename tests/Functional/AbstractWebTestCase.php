<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional;

use JG\BatchEntityImportBundle\Tests\Fixtures\Event\TestableEventDispatcher;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbstractWebTestCase extends WebTestCase
{
    protected static function bootKernel(array $options = []): KernelInterface
    {
        $kernel = parent::bootKernel($options);

        restore_exception_handler();

        return $kernel;
    }

    /**
     * Returns service from test container with proper type.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected static function getService(string $class, ?string $id = null): object
    {
        $service = self::getContainer()->get($id ?? $class);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    /**
     * In debug mode TestableEventDispatcher is decorated by TraceableEventDispatcher,
     * which forwards calls of not existing methods to it by __call().
     *
     * @return TestableEventDispatcher
     */
    protected static function getTestableEventDispatcher(): EventDispatcherInterface
    {
        /** @var TestableEventDispatcher $eventDispatcher */
        $eventDispatcher = self::getService(EventDispatcherInterface::class, 'event_dispatcher');

        return $eventDispatcher;
    }

    protected function createCustomClient(KernelInterface $kernel): KernelBrowser
    {
        return new class($kernel) extends KernelBrowser {
            public function request(
                string $method,
                string $uri,
                array $parameters = [],
                array $files = [],
                array $server = [],
                ?string $content = null,
                bool $changeHistory = true,
            ): Crawler {
                $response = parent::request($method, $uri, $parameters, $files, $server, $content);

                restore_exception_handler();

                return $response;
            }
        };
    }
}
