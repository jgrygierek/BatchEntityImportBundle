<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Service;

use Generator;
use JG\BatchEntityImportBundle\Service\PropertyExistenceChecker;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\Fixtures\Model\PropertyAccessEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PropertyExistenceCheckerTest extends TestCase
{
    private PropertyExistenceChecker $checkerEntity;

    protected function setUp(): void
    {
        $this->checkerEntity = new PropertyExistenceChecker(TestEntity::class);
    }

    #[DataProvider('dataProviderValidProperty')]
    public function testEntityHasProperty(string $property): void
    {
        self::assertTrue($this->checkerEntity->propertyExists($property));
    }

    public static function dataProviderValidProperty(): Generator
    {
        yield ['test_private_property'];
        yield ['testPrivateProperty'];
        yield ['test_public_property'];
        yield ['testPublicProperty'];
    }

    #[DataProvider('dataProviderWrongProperty')]
    public function testEntityWithoutProperty(string $property): void
    {
        self::assertFalse($this->checkerEntity->propertyExists($property));
    }

    public static function dataProviderWrongProperty(): Generator
    {
        yield ['wrong_property'];
        yield ['wrongProperty'];
        yield ['test_private_property_no_setter'];
        yield ['testPrivatePropertyNoSetter'];
    }

    #[DataProvider('propertyAccessProvider')]
    public function testOnlyPropertiesWritableFromOutsideExist(string $property, bool $expected): void
    {
        self::assertSame($expected, new PropertyExistenceChecker(PropertyAccessEntity::class)->propertyExists($property));
    }

    public static function propertyAccessProvider(): Generator
    {
        yield 'public property' => ['public_property', true];
        yield 'virtual property with set hook' => ['upper_case_property', true];
        yield 'public property with protected setter' => ['property_with_protected_setter', true];
        yield 'static property' => ['static_property', false];
        yield 'readonly property' => ['readonly_property', false];
        yield 'private(set) property' => ['private_set_property', false];
        yield 'protected(set) property' => ['protected_set_property', false];
        yield 'virtual property without set hook' => ['get_only_property', false];
        yield 'private property with protected setter' => ['private_property_with_protected_setter', false];
    }
}
