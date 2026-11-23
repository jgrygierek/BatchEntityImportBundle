<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Model;

/**
 * Properties which can and can't be written from outside of the class.
 * It is not mapped by Doctrine, it is used only to check which columns are imported.
 */
class PropertyAccessEntity
{
    public static string $staticProperty = '';

    public string $publicProperty = '';
    public readonly string $readonlyProperty;
    public private(set) string $privateSetProperty = '';
    public protected(set) string $protectedSetProperty = '';
    // virtual property with "set" hook
    public string $upperCaseProperty {
        get => $this->upperCaseValue;
        set {
            $this->upperCaseValue = strtoupper($value);
        }
    }
    // virtual property without "set" hook
    public string $getOnlyProperty {
        get => 'get only';
    }
    // value is set directly, because setter is not public
    public string $propertyWithProtectedSetter = '';
    private string $upperCaseValue = '';
    private string $privatePropertyWithProtectedSetter = '';

    public function __construct()
    {
        $this->readonlyProperty = 'readonly';
    }

    public function getPrivatePropertyWithProtectedSetter(): string
    {
        return $this->privatePropertyWithProtectedSetter;
    }

    protected function setPropertyWithProtectedSetter(string $value): void
    {
        $this->propertyWithProtectedSetter = 'set by setter: ' . $value;
    }

    protected function setPrivatePropertyWithProtectedSetter(string $value): void
    {
        $this->privatePropertyWithProtectedSetter = $value;
    }
}
