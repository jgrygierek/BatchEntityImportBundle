<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Service;

use Generator;
use JG\BatchEntityImportBundle\Service\PropertyExistenceChecker;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TranslatableEntity;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class PropertyExistenceCheckerTest extends TestCase
{
    private PropertyExistenceChecker $checkerEntity;
    private PropertyExistenceChecker $checkerEntityWithTranslations;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->checkerEntity = new PropertyExistenceChecker(TestEntity::class);
        $this->checkerEntityWithTranslations = new PropertyExistenceChecker(TranslatableEntity::class);
    }

    /**
     * @dataProvider dataProviderEntityWithoutTranslations
     */
    public function testEntityWithoutTranslationsHasProperty(string $property): void
    {
        self::assertTrue($this->checkerEntity->propertyExists($property));
    }

    public function dataProviderEntityWithoutTranslations(): Generator
    {
        yield ['test_property'];
        yield ['testProperty'];
    }

    /**
     * @dataProvider dataProviderEntityWithTranslations
     */
    public function testEntityWithTranslationsHasProperty(string $property): void
    {
        self::assertTrue($this->checkerEntityWithTranslations->propertyExists($property));
    }

    public function dataProviderEntityWithTranslations(): Generator
    {
        yield ['test_property'];
        yield ['testProperty'];
        yield ['test_translation_property:pl'];
        yield ['testTranslationProperty:en'];
    }

    /**
     * @dataProvider dataProviderEntityWithoutTranslationsWrongProperty
     */
    public function testEntityWithoutTranslationsWithoutProperty(string $property): void
    {
        self::assertFalse($this->checkerEntity->propertyExists($property));
    }

    public function dataProviderEntityWithoutTranslationsWrongProperty(): Generator
    {
        yield ['test_property:pl'];
        yield ['testProperty:en'];
        yield ['wrong_property'];
        yield ['wrongProperty'];
    }

    /**
     * @dataProvider dataProviderEntityWithTranslationsWrongProperty
     */
    public function testEntityWithTranslationsWithoutProperty(string $property): void
    {
        self::assertFalse($this->checkerEntityWithTranslations->propertyExists($property));
    }

    public function dataProviderEntityWithTranslationsWrongProperty(): Generator
    {
        yield ['wrong_property'];
        yield ['wrongProperty'];
        yield ['test_translation_property'];
        yield ['testTranslationProperty'];
    }
}
