<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Twig;

use JG\BatchEntityImportBundle\Twig\TemplateExtension;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TemplateExtensionTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testValidTemplates(): void
    {
        /** @var TemplateExtension $extension */
        $extension = self::$kernel->getContainer()->get(TemplateExtension::class);

        self::assertNotEmpty($extension->getTemplate('select_file'));
        self::assertNotEmpty($extension->getTemplate('edit_matrix'));
        self::assertNotEmpty($extension->getTemplate('layout'));
    }

    public function testWrongTemplateException(): void
    {
        $this->expectExceptionMessage('Template wrong_template not found.');

        /** @var TemplateExtension $extension */
        $extension = self::$kernel->getContainer()->get(TemplateExtension::class);
        $extension->getTemplate('wrong_template');
    }
}
