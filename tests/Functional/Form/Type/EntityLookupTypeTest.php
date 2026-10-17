<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Functional\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Form\Type\EntityLookupType;
use JG\BatchEntityImportBundle\Tests\DatabaseLoader;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestRelatedEntity;
use JG\BatchEntityImportBundle\Tests\Functional\AbstractWebTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Contracts\Service\ResetInterface;

class EntityLookupTypeTest extends AbstractWebTestCase
{
    private FormFactoryInterface $factory;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->factory = self::getService(FormFactoryInterface::class, 'form.factory');
        self::getService(DatabaseLoader::class)->reload();

        $this->entityManager = self::getService(EntityManagerInterface::class, 'doctrine.orm.entity_manager');
        $this->entityManager->persist(new TestRelatedEntity('first'));
        $this->entityManager->persist(new TestRelatedEntity('second'));
        $this->entityManager->flush();
    }

    public function testChoicesWithDefaultLookupField(): void
    {
        $form = $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class]);

        self::assertSame(['Label first' => 1, 'Label second' => 2], $form->getConfig()->getOption('choices'));
    }

    public function testChoicesWithCustomLookupField(): void
    {
        $form = $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class, 'lookup_field' => 'name']);

        self::assertSame(['Label first' => 'first', 'Label second' => 'second'], $form->getConfig()->getOption('choices'));
    }

    public function testSubmitValidValue(): void
    {
        $form = $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class, 'lookup_field' => 'name']);
        $form->submit('second');

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('second', $form->getData());
    }

    public function testSubmitIdValue(): void
    {
        $form = $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class]);
        $form->submit('2');

        self::assertTrue($form->isValid());
        self::assertSame(2, $form->getData());
    }

    public function testSubmitEmptyValue(): void
    {
        $form = $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class]);
        $form->submit('');

        self::assertTrue($form->isValid());
        self::assertNull($form->getData());
    }

    public function testSubmitUnknownValue(): void
    {
        $form = $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class, 'lookup_field' => 'name']);
        $form->submit('unknown');

        self::assertFalse($form->isSynchronized());
        self::assertFalse($form->isValid());
    }

    public function testMissingClassOption(): void
    {
        $this->expectException(MissingOptionsException::class);

        $this->factory->create(EntityLookupType::class);
    }

    public function testInvalidLookupField(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class, 'lookup_field' => 'unknown']);
    }

    public function testNotManagedClass(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(EntityLookupType::class, null, ['class' => self::class]);
    }

    public function testSubmitValueWithWhitespaces(): void
    {
        $form = $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class, 'lookup_field' => 'name']);
        $form->submit(' second ');

        self::assertTrue($form->isValid());
        self::assertSame('second', $form->getData());
    }

    public function testChoicesCacheIsResetBetweenRequests(): void
    {
        $this->entityManager->persist(new TestRelatedEntity('third'));
        $this->entityManager->flush();

        $options = ['class' => TestRelatedEntity::class, 'lookup_field' => 'name'];
        $this->getChoices($options);
        $this->entityManager->persist(new TestRelatedEntity('fourth'));
        $this->entityManager->flush();

        self::assertNotContains('fourth', $this->getChoices($options));

        self::getService(ResetInterface::class, 'services_resetter')->reset();

        self::assertContains('fourth', $this->getChoices($options));
    }

    public function testChoicesWithLabelField(): void
    {
        $form = $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class, 'label_field' => 'name']);

        self::assertSame(['first' => 1, 'second' => 2], $form->getConfig()->getOption('choices'));
    }

    public function testEntitiesAreNotHydratedWithLabelField(): void
    {
        $this->entityManager->clear();

        $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class, 'label_field' => 'name']);
        self::assertSame(0, $this->entityManager->getUnitOfWork()->size());

        // label from __toString() needs hydrated entities
        $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class]);
        self::assertSame(2, $this->entityManager->getUnitOfWork()->size());
    }

    public function testInvalidLabelField(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage(\sprintf('Field "unknown" does not exist in entity "%s".', TestRelatedEntity::class));

        $this->factory->create(EntityLookupType::class, null, ['class' => TestRelatedEntity::class, 'label_field' => 'unknown']);
    }

    public function testEntitiesWithEmptyLookupValueAreSkipped(): void
    {
        $this->persistTestEntities(['value', '', 'value_2']);

        $form = $this->factory->create(EntityLookupType::class, null, ['class' => TestEntity::class, 'lookup_field' => 'testPublicProperty']);

        self::assertSame(['1' => 'value', '3' => 'value_2'], $form->getConfig()->getOption('choices'));
    }

    public function testDuplicatedLabelsAreUnique(): void
    {
        $this->persistTestEntities(['same', 'same', 'other']);

        $form = $this->factory->create(EntityLookupType::class, null, ['class' => TestEntity::class, 'label_field' => 'testPublicProperty']);

        self::assertSame(['same' => 1, 'same (2)' => 2, 'other' => 3], $form->getConfig()->getOption('choices'));
    }

    public function testNotExistingClass(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('Class "App\\NotExistingClass" is not a managed Doctrine entity.');

        $this->factory->create(EntityLookupType::class, null, ['class' => 'App\\NotExistingClass']);
    }

    /**
     * @param array<string> $publicPropertyValues
     */
    private function persistTestEntities(array $publicPropertyValues): void
    {
        foreach ($publicPropertyValues as $index => $publicPropertyValue) {
            $entity = new TestEntity();
            $entity->setTestPrivateProperty('private_' . $index);
            $entity->testPublicProperty = $publicPropertyValue;
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, string|int>
     */
    private function getChoices(array $options): array
    {
        /** @var array<string, string|int> $choices */
        $choices = $this->factory->create(EntityLookupType::class, null, $options)->getConfig()->getOption('choices');

        return $choices;
    }
}
