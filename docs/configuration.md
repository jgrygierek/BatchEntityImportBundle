# Configuration class

To define how the import function should work, you need to create a configuration class.

## Basic configuration class

In the simplest case it will contain only class of used entity.

```php
namespace App\Model\ImportConfiguration;

use App\Entity\User;
use JG\BatchEntityImportBundle\Model\Configuration\AbstractImportConfiguration;

class UserImportConfiguration extends AbstractImportConfiguration
{
    public function getEntityClassName(): string
    {
        return User::class;
    }
}
```

Then register it as a service:

```yaml
services:
  App\Model\ImportConfiguration\UserImportConfiguration: ~
```

## Fields definitions

If you want to change types of rendered fields, instead of using default ones,
you have to override method in your import configuration. If name of field contains spaces, you should use underscores instead.

To avoid errors during data import, you can add here validation rules.

```php

use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;

public function getFieldsDefinitions(): array
{
    return [
        'age' => new FormFieldDefinition(
            IntegerType::class,
            [
                'attr' => [
                    'min' => 0,
                    'max' => 999,
                ],
            ]
        ),
        'name' => new FormFieldDefinition(TextType::class),
        'description' => new FormFieldDefinition(
            TextareaType::class,
            [
                'attr' => [
                    'rows' => 2,
                ],
                'constraints' => [new Length(max: 255)],
            ]
        ),
    ];
}
```

There are also special types of fields, see [Importing data to array field](array-fields.md) and [Importing relations](relations.md).

## Matrix validation

This bundle provides two new validators.

1) **DatabaseEntityUnique** validator can be used to check if record data does not exist yet in database.
2) **MatrixRecordUnique** validator can be used to check duplication without checking database, just only matrix records values.

Names of fields should be the same as names of columns in your uploaded file. With one exception! If name contains spaces, you should use underscores instead.

```php
use JG\BatchEntityImportBundle\Validator\Constraints\DatabaseEntityUnique;
use JG\BatchEntityImportBundle\Validator\Constraints\MatrixRecordUnique;

public function getMatrixConstraints(): array
{
    return [
        new MatrixRecordUnique(fields: ['field_name']),
        new DatabaseEntityUnique(entityClassName: $this->getEntityClassName(), fields: ['field_name']),
    ];
}
```

## Passing services to configuration class

If you want to pass some additional services to your configuration, just override constructor.

```php
public function __construct(EntityManagerInterface $em, EventDispatcherInterface $eventDispatcher, TestService $service)
{
    parent::__construct($em, $eventDispatcher);

    $this->testService = $service;
}
```

## Show & hide entity override column

If you want to hide/show an entity column that allows you to override entity `default: true`,
you have to override this method in your import configuration.

```php
public function allowOverrideEntity(): bool
{
    return true;
}
```

## Set allowed file extensions

By default, allowed file extensions are set to `'csv', 'xls', 'xlsx', 'ods'`.
However, if you want to change it, you can override this method in your import configuration.

```php
  public function getAllowedFileExtensions(): array
  {
      return ['csv', 'xls', 'xlsx', 'ods'];
  }
```

## Optimizing queries

If you use **KnpLabs Translatable** extension for your entity, probably you will notice increased number of queries, because of Lazy Loading.

To optimize this, you can use `getEntityTranslationRelationName()` method to pass the relation name to the translation.

```php
public function getEntityTranslationRelationName(): ?string
{
    return 'translations';
}
```

[Back to README](../README.md)
