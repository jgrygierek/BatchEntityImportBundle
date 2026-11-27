<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Form\Type;

use JG\BatchEntityImportBundle\Form\Type\MatrixType;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

// mock of event dispatcher is created by TypeTestCase
#[AllowMockObjectsWithoutExpectations]
class MatrixTypeTest extends TypeTestCase
{
    private ImportConfigurationInterface $baseConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseConfig = new BaseConfiguration();
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
