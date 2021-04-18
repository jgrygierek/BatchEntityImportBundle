<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Form\Type\MatrixType;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

class MatrixTypeTest extends TypeTestCase
{
    private ImportConfigurationInterface $baseConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseConfig = new BaseConfiguration($this->createMock(EntityManagerInterface::class));
    }

    public function testValidForm(): void
    {
        $formData = [];
        $matrix = new Matrix();
        $form = $this->factory->create(MatrixType::class, $matrix, ['configuration' => $this->baseConfig]);

        $form->submit($formData);
        self::assertTrue($form->isSynchronized());
    }

    public function testInvalidFormWithoutConfiguration(): void
    {
        $this->expectException(MissingOptionsException::class);

        $matrix = new Matrix();
        $this->factory->create(MatrixType::class, $matrix);
    }
}
