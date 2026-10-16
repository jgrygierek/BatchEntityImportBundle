<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Form\Type\EntityLookupTextType;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestRelatedEntity;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use JG\BatchEntityImportBundle\Validator\Constraints\RelatedEntityExists;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;

class EntityLookupTextTypeTest extends AbstractWebTestCase
{
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->factory = self::getService(FormFactoryInterface::class, 'form.factory');
        self::getService(DatabaseLoader::class)->reload();

        $entityManager = self::getService(EntityManagerInterface::class, 'doctrine.orm.entity_manager');
        $entityManager->persist(new TestRelatedEntity('first'));
        $entityManager->persist(new TestRelatedEntity('second'));
        $entityManager->flush();
    }

    public function testConstraintAdded(): void
    {
        $form = $this->factory->create(EntityLookupTextType::class, null, ['class' => TestRelatedEntity::class, 'lookup_field' => 'name']);

        $constraints = $this->getConstraints($form);
        self::assertCount(1, $constraints);
        self::assertInstanceOf(RelatedEntityExists::class, $constraints[0]);
        self::assertSame(TestRelatedEntity::class, $constraints[0]->class);
        self::assertSame('name', $constraints[0]->lookupField);
    }

    public function testCustomConstraintsPreserved(): void
    {
        $form = $this->factory->create(
            EntityLookupTextType::class,
            null,
            ['class' => TestRelatedEntity::class, 'constraints' => [new Length(max: 10)]],
        );

        $constraints = $this->getConstraints($form);
        self::assertCount(2, $constraints);
        self::assertInstanceOf(Length::class, $constraints[0]);
        self::assertInstanceOf(RelatedEntityExists::class, $constraints[1]);
    }

    public function testSubmitValidValue(): void
    {
        $form = $this->factory->create(EntityLookupTextType::class, null, ['class' => TestRelatedEntity::class, 'lookup_field' => 'name']);
        $form->submit('second');

        self::assertTrue($form->isValid());
        self::assertSame('second', $form->getData());
    }

    public function testSubmitIdValue(): void
    {
        $form = $this->factory->create(EntityLookupTextType::class, null, ['class' => TestRelatedEntity::class]);
        $form->submit('2');

        self::assertTrue($form->isValid());
    }

    public function testSubmitEmptyValue(): void
    {
        $form = $this->factory->create(EntityLookupTextType::class, null, ['class' => TestRelatedEntity::class]);
        $form->submit('');

        self::assertTrue($form->isValid());
        self::assertNull($form->getData());
    }

    public function testSubmitUnknownValue(): void
    {
        $form = $this->factory->create(EntityLookupTextType::class, null, ['class' => TestRelatedEntity::class, 'lookup_field' => 'name']);
        $form->submit('unknown');

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        $error = $form->getErrors()[0];
        self::assertInstanceOf(FormError::class, $error);
        self::assertSame('Related entity "unknown" not found.', $error->getMessage());
    }

    public function testMissingClassOption(): void
    {
        $this->expectException(MissingOptionsException::class);

        $this->factory->create(EntityLookupTextType::class);
    }

    public function testInvalidLookupField(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(EntityLookupTextType::class, null, ['class' => TestRelatedEntity::class, 'lookup_field' => 'unknown']);
    }

    public function testNotManagedClass(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(EntityLookupTextType::class, null, ['class' => self::class]);
    }

    public function testSubmitValueWithWhitespaces(): void
    {
        $form = $this->factory->create(EntityLookupTextType::class, null, ['class' => TestRelatedEntity::class, 'lookup_field' => 'name']);
        $form->submit(' second ');

        self::assertTrue($form->isValid());
        self::assertSame('second', $form->getData());
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return array<int, Constraint>
     */
    private function getConstraints(FormInterface $form): array
    {
        /** @var array<int, Constraint> $constraints */
        $constraints = $form->getConfig()->getOption('constraints');

        return $constraints;
    }
}
