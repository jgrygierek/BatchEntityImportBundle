<?php

namespace JG\BatchEntityImportBundle\Tests\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Form\Type\MatrixRecordType;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\BaseConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Configuration\FieldsTypeConfiguration;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

class MatrixRecordTypeTest extends WebTestCase
{
    private ?FormFactory $factory;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::$kernel->getContainer();
        $this->factory = $container->get('form.factory');
    }

    public function testValidFormWithBaseConfig(): void
    {
        $data = $this->getRecordData();
        $configuration = new BaseConfiguration($this->createMock(EntityManagerInterface::class));
        $matrixRecord = new MatrixRecord($data);

        $form = $this->factory->create(MatrixRecordType::class, $matrixRecord, ['configuration' => $configuration]);
        $form->submit($data);

        $this->assertTrue($form->isSynchronized());

        $this->assertSame(TestEntity::class, $form->get('entity')->getConfig()->getOption('class'));
        $this->assertInstanceOf(TextType::class, $form->get('age')->getConfig()->getType()->getInnerType());
        $this->assertInstanceOf(TextType::class, $form->get('name')->getConfig()->getType()->getInnerType());
        $this->assertInstanceOf(TextType::class, $form->get('description')->getConfig()->getType()->getInnerType());
    }

    public function testValidFormWithFieldsConfig(): void
    {
        $data = $this->getRecordData();
        $configuration = new FieldsTypeConfiguration($this->createMock(EntityManagerInterface::class));
        $matrixRecord = new MatrixRecord($data);

        $form = $this->factory->create(MatrixRecordType::class, $matrixRecord, ['configuration' => $configuration]);
        $form->submit($data);

        $this->assertTrue($form->isSynchronized());

        $this->assertSame(TestEntity::class, $form->get('entity')->getConfig()->getOption('class'));
        $this->assertInstanceOf(IntegerType::class, $form->get('age')->getConfig()->getType()->getInnerType());
        $this->assertInstanceOf(TextType::class, $form->get('name')->getConfig()->getType()->getInnerType());
        $this->assertInstanceOf(TextareaType::class, $form->get('description')->getConfig()->getType()->getInnerType());
    }

    public function testInvalidFormWithoutConfiguration(): void
    {
        $this->expectException(MissingOptionsException::class);

        $matrixRecord = new MatrixRecord();
        $this->factory->create(MatrixRecordType::class, $matrixRecord);
    }

    private function getRecordData(): array
    {
        return [
            'age' => 12,
            'name' => 'John Doe',
            'description' => 'Lorem Ipsum',
        ];
    }
}
