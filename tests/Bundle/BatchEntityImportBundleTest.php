<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Bundle;

use JG\BatchEntityImportBundle\BatchEntityImportBundle;
use JG\BatchEntityImportBundle\DependencyInjection\Compiler\AutoConfigureCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BatchEntityImportBundleTest extends TestCase
{
    private BatchEntityImportBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new BatchEntityImportBundle();
    }

    public function testBundle(): void
    {
        self::assertInstanceOf(Bundle::class, $this->bundle);
    }

    public function testBundleBuild(): void
    {
        $containerBuilder = $this->createMock(ContainerBuilder::class);

        $containerBuilder
            ->expects(self::once())
            ->method('addCompilerPass')
            ->with(new AutoConfigureCompilerPass());

        $this->bundle->build($containerBuilder);
    }
}
