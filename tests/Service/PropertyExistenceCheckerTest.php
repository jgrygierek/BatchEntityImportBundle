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
     * @dataProvider dataProviderEntity
     *
     * @param string $property
     */
    public function testEntityWithoutTranslationsHasProperty(string $property): void
    {
        $this->assertTrue($this->checkerEntity->propertyExists($property));
    }

    public function dataProviderEntity(): Generator
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
        yield ['test_translation_private'];
        yield ['testTranslationPrivate'];
        yield ['test_translation_protected'];
        yield ['testTranslationProtected'];
        yield ['test_translation_public'];
        yield ['testTranslationPublic'];
    }

    /**
     * @dataProvider dataProviderEntityWrongProperty
     *
     * @param string $property
     */
    public function testEntityWithoutTranslationsWithoutProperty(string $property): void
    {
        $this->assertFalse($this->checkerEntity->propertyExists($property));
    }

    /**
     * @dataProvider dataProviderEntityWrongProperty
     *
     * @param string $property
     */
    public function testEntityWithTranslationsWithoutProperty(string $property): void
    {
        $this->assertFalse($this->checkerEntityWithTranslations->propertyExists($property));
    }

    public function dataProviderEntityWrongProperty(): Generator
    {
        yield ['wrong_property'];
        yield ['wrongProperty'];
    }
}
