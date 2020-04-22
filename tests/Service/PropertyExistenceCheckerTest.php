<?php

namespace JG\BatchEntityImportBundle\Tests\Service;

use Generator;
use JG\BatchEntityImportBundle\Service\PropertyExistenceChecker;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity;
use JG\BatchEntityImportBundle\Tests\Fixtures\TranslatableEntity;
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

        $this->checkerEntity                 = new PropertyExistenceChecker(new Entity());
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
        yield ['test_property_private'];
        yield ['testPropertyPrivate'];
        yield ['test_property_protected'];
        yield ['testPropertyProtected'];
        yield ['test_property_public'];
        yield ['testPropertyPublic'];
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
        yield ['test_property_private'];
        yield ['testPropertyPrivate'];
        yield ['test_property_protected'];
        yield ['testPropertyProtected'];
        yield ['test_property_public'];
        yield ['testPropertyPublic'];
        yield ['test_translation_protected:ru'];
        yield ['testTranslationProtected:hu'];
        yield ['test_translation_public:hu'];
        yield ['testTranslationPublic:ru'];
        yield ['test_translation_private:pl'];
        yield ['testTranslationPrivate:en'];
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
        yield ['test_property_public:pl'];
        yield ['testPropertyPublic:en'];
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
        yield ['test_translation_public'];
        yield ['testTranslationPublic'];
    }
}
