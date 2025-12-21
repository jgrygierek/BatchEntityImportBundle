<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional;

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
