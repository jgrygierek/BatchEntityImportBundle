<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Utils;

use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Entity\TranslatableEntity;
use JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Entity\TranslatableEntityTranslation;
use JG\BatchEntityImportBundle\Tests\SkippedTestsTrait;
use JG\BatchEntityImportBundle\Utils\EntityTranslationHelper;
use PHPUnit\Framework\TestCase;

class EntityTranslationHelperTest extends TestCase
{
    use SkippedTestsTrait;

    public function testNotTranslatableEntityIsIgnored(): void
    {
        $entity = new TestEntity();

        self::assertNull(EntityTranslationHelper::getTranslationClass(TestEntity::class));
        self::assertNull(EntityTranslationHelper::getTranslation($entity, 'en'));
        self::assertSame([], EntityTranslationHelper::clearNewTranslations($entity));

        EntityTranslationHelper::mergeNewTranslations($entity);
        self::assertEquals(new TestEntity(), $entity);
    }

    public function testTranslationClass(): void
    {
        $this->markKnpLabsTestAsSkipped();

        self::assertSame(TranslatableEntityTranslation::class, EntityTranslationHelper::getTranslationClass(TranslatableEntity::class));
    }

    public function testTranslationIsCreatedAndMerged(): void
    {
        $this->markKnpLabsTestAsSkipped();

        $entity = new TranslatableEntity();
        $translation = EntityTranslationHelper::getTranslation($entity, 'pl');

        self::assertInstanceOf(TranslatableEntityTranslation::class, $translation);
        self::assertSame($translation, EntityTranslationHelper::getTranslation($entity, 'pl'));
        self::assertCount(1, $entity->getNewTranslations());

        // empty translation would not be merged
        $translation->setTestTranslationProperty('test');
        EntityTranslationHelper::mergeNewTranslations($entity);

        self::assertCount(0, $entity->getNewTranslations());
        self::assertSame([$translation], EntityTranslationHelper::clearNewTranslations($entity));
    }

    public function testNotMergedTranslationsAreCleared(): void
    {
        $this->markKnpLabsTestAsSkipped();

        $entity = new TranslatableEntity();
        $merged = EntityTranslationHelper::getTranslation($entity, 'pl');
        self::assertInstanceOf(TranslatableEntityTranslation::class, $merged);
        $merged->setTestTranslationProperty('test');
        EntityTranslationHelper::mergeNewTranslations($entity);
        EntityTranslationHelper::getTranslation($entity, 'en');

        self::assertSame([$merged], EntityTranslationHelper::clearNewTranslations($entity));
        self::assertCount(0, $entity->getNewTranslations());
    }
}
