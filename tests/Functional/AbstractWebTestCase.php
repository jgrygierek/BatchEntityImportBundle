<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional;

use JG\BatchEntityImportBundle\Tests\Fixtures\Event\ImportEventRecorder;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
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
     * Events of the bundle dispatched so far (in all requests of test client).
     */
    protected static function getEventRecorder(): ImportEventRecorder
    {
        return self::getService(ImportEventRecorder::class);
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
