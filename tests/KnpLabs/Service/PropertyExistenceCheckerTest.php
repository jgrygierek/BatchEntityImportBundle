<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\KnpLabs\Service;

use Generator;
use JG\BatchEntityImportBundle\Service\PropertyExistenceChecker;
use JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Entity\TranslatableEntity;
use JG\BatchEntityImportBundle\Tests\SkippedTestsTrait;
use PHPUnit\Framework\TestCase;

class PropertyExistenceCheckerTest extends TestCase
{
    use SkippedTestsTrait;

    private PropertyExistenceChecker $checkerEntity;

    protected function setUp(): void
    {
        $this->markKnpLabsTestAsSkipped();

        $this->checkerEntity = new PropertyExistenceChecker(TranslatableEntity::class);
    }

    /**
     * @dataProvider dataProviderValidProperty
     */
    public function testEntityWithTranslationsHasProperty(string $property): void
    {
        self::assertTrue($this->checkerEntity->propertyExists($property));
    }

    public static function dataProviderValidProperty(): Generator
    {
        yield ['test_private_property'];
        yield ['testPrivateProperty'];
        yield ['test_public_property'];
        yield ['testPublicProperty'];
        yield ['test_translation_property:pl'];
        yield ['testTranslationProperty:en'];
    }

    /**
     * @dataProvider dataProviderWrongProperty
     */
    public function testEntityWithTranslationsWithoutProperty(string $property): void
    {
        self::assertFalse($this->checkerEntity->propertyExists($property));
    }

    public static function dataProviderWrongProperty(): Generator
    {
        yield ['wrong_property'];
        yield ['wrongProperty'];
        yield ['test_private_property_no_setter'];
        yield ['testPrivatePropertyNoSetter'];
        yield ['test_translation_property'];
        yield ['testTranslationProperty'];
    }
}
