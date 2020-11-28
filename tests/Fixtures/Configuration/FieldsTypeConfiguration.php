<?php

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Configuration;

use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class FieldsTypeConfiguration extends AbstractImportConfiguration
{
    public function getEntityClassName(): string
    {
        return TestEntity::class;
    }

    public function getFieldsDefinitions(): array
    {
        return [
            'age' => new FormFieldDefinition(IntegerType::class),
            'name' => new FormFieldDefinition(TextType::class),
            'description' => new FormFieldDefinition(TextareaType::class),
        ];
    }
}
