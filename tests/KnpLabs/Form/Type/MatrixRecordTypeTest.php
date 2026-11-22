<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\KnpLabs\Form\Type;

use JG\BatchEntityImportBundle\Form\Type\MatrixRecordType;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Configuration\TranslatableEntityConfiguration;
use JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Entity\TranslatableEntity;
use JG\BatchEntityImportBundle\Tests\SkippedTestsTrait;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactory;

class MatrixRecordTypeTest extends AbstractWebTestCase
{
    use SkippedTestsTrait;

    private FormFactory $factory;

    protected function setUp(): void
    {
        $this->markKnpLabsTestAsSkipped();

        self::bootKernel();

        $this->factory = self::getContainer()->get('form.factory');
    }

    public function testValidFormWithTranslatableField(): void
    {
        $data = ['description:pl' => 'Lorem Ipsum'];
        $configuration = new TranslatableEntityConfiguration();
        $matrixRecord = new MatrixRecord($data);

        $form = $this->factory->create(MatrixRecordType::class, $matrixRecord, ['configuration' => $configuration]);
        $form->submit($data);

        self::assertTrue($form->isSynchronized());

        self::assertSame(TranslatableEntity::class, $form->get('entity')->getConfig()->getOption('class'));
        self::assertInstanceOf(TextType::class, $form->get('description:pl')->getConfig()->getType()->getInnerType());
    }
}
