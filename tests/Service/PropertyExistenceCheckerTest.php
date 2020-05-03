<?php

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

        $this->checkerEntity                 = new PropertyExistenceChecker(new TestEntity());
        $this->checkerEntityWithTranslations = new PropertyExistenceChecker(new TranslatableEntity());
    }

    /**
     * @dataProvider dataProviderEntityWithoutTranslations
     *
     * @param string $property
     */
    public function testEntityWithoutTranslationsHasProperty(string $property): void
    {
        $this->assertTrue($this->checkerEntity->propertyExists($property));
    }

    public function dataProviderEntityWithoutTranslations(): Generator
    {
        yield ['test_property'];
        yield ['testProperty'];
    }

    /**
     * @dataProvider dataProviderEntityWithTranslations
     *
     * @param string $property
     */
    public function testEntityWithTranslationsHasProperty(string $property): void
    {
        $this->assertTrue($this->checkerEntityWithTranslations->propertyExists($property));
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
     *
     * @param string $property
     */
    public function testEntityWithoutTranslationsWithoutProperty(string $property): void
    {
        $this->assertFalse($this->checkerEntity->propertyExists($property));
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
     *
     * @param string $property
     */
    public function testEntityWithTranslationsWithoutProperty(string $property): void
    {
        $this->assertFalse($this->checkerEntityWithTranslations->propertyExists($property));
    }

    public function dataProviderEntityWithTranslationsWrongProperty(): Generator
    {
        yield ['wrong_property'];
        yield ['wrongProperty'];
        yield ['test_translation_property'];
        yield ['testTranslationProperty'];
    }
}
