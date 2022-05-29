<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Service;

use Generator;
use JG\BatchEntityImportBundle\Service\PropertyExistenceChecker;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;
use PHPUnit\Framework\TestCase;

class PropertyExistenceCheckerTest extends TestCase
{
    private PropertyExistenceChecker $checkerEntity;
    private PropertyExistenceChecker $checkerEntityWithTranslations;

    protected function setUp(): void
    {
        $this->checkerEntity = new PropertyExistenceChecker(TestEntity::class);
        $this->checkerEntityWithTranslations = new PropertyExistenceChecker(TranslatableEntity::class);
    }

    /**
     * @dataProvider dataProviderCommonProperty
     */
    public function testEntityWithoutTranslationsHasProperty(string $property): void
    {
        self::assertTrue($this->checkerEntity->propertyExists($property));
    }

    /**
     * @dataProvider dataProviderEntityWithTranslationsProperty
     * @dataProvider dataProviderCommonProperty
     */
    public function testEntityWithTranslationsHasProperty(string $property): void
    {
        self::assertTrue($this->checkerEntityWithTranslations->propertyExists($property));
    }

    public function dataProviderCommonProperty(): Generator
    {
        yield ['test_private_property'];
        yield ['testPrivateProperty'];
        yield ['test_public_property'];
        yield ['testPublicProperty'];
    }

    public function dataProviderEntityWithTranslationsProperty(): Generator
    {
        yield ['test_translation_property:pl'];
        yield ['testTranslationProperty:en'];
    }

    /**
     * @dataProvider dataProviderEntityWithoutTranslationsWrongProperty
     * @dataProvider dataProviderCommonWrongProperty
     */
    public function testEntityWithoutTranslationsWithoutProperty(string $property): void
    {
        self::assertFalse($this->checkerEntity->propertyExists($property));
    }

    /**
     * @dataProvider dataProviderEntityWithTranslationsWrongProperty
     * @dataProvider dataProviderCommonWrongProperty
     */
    public function testEntityWithTranslationsWithoutProperty(string $property): void
    {
        self::assertFalse($this->checkerEntityWithTranslations->propertyExists($property));
    }

    public function dataProviderCommonWrongProperty(): Generator
    {
        yield ['wrong_property'];
        yield ['wrongProperty'];
        yield ['test_private_property_no_setter'];
        yield ['testPrivatePropertyNoSetter'];
    }

    public function dataProviderEntityWithoutTranslationsWrongProperty(): Generator
    {
        yield ['test_private_property:pl'];
        yield ['testPrivateProperty:en'];
    }

    public function dataProviderEntityWithTranslationsWrongProperty(): Generator
    {
        yield ['test_translation_property'];
        yield ['testTranslationProperty'];
    }
}
