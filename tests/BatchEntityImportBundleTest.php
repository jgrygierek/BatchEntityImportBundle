<?php

namespace JG\BatchEntityImportBundle\Tests;

use JG\BatchEntityImportBundle\BatchEntityImportBundle;
use Nyholm\BundleTest\BaseBundleTestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BatchEntityImportBundleTest extends BaseBundleTestCase
{
    protected function getBundleClass(): string
    {
        return BatchEntityImportBundle::class;
    }

    public function testBundle(): void
    {
        $class = $this->getBundleClass();
        $this->assertInstanceOf(Bundle::class, new $class);
    }
}
