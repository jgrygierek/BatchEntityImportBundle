# Configuration class

To define how the import function should work, you need to create a configuration class.

## Basic configuration class

In the simplest case it will contain only class of used entity and list of columns which can be imported
(see [Allowed columns](#allowed-columns)). There is no default value for them.

Configuration should extend `AbstractImportConfiguration`, which provides default values of all other options.
Implementing `ImportConfigurationInterface` directly is not recommended, because new methods (new options of import)
can be added to this interface in minor versions, without default implementation in your class.

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

    public function getAllowedColumns(): array
    {
        return ['user_name', 'age', 'email'];
    }
}
```

Configuration has to be registered as a service. With default `config/services.yaml` of Symfony application
(all classes from `src/` are registered) nothing has to be done. Otherwise register it manually:

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

`DatabaseEntityUnique` uses entity manager which manages given class of entity, so it works also with entities which are not managed by the default entity manager.

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

Configuration only describes import, data is saved by the bundle. So configuration has no dependencies,
and you can pass any services to it by constructor:

```php
public function __construct(private readonly TestService $service)
{
}
```

## Creating new entities

By default new entity is created by constructor without arguments. If your entity needs some arguments,
override this method in your import configuration. Values from record are set to entity after it is created.

```php
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;

public function getNewEntity(MatrixRecord $record): object
{
    return new User($this->service->getDefaultGroup());
}
```

## Updating existing entities

By default records are always imported as new entities, and file with values in `entity_id` column is rejected.
To allow updating existing entities (see [Updating entities](updating-entities.md)), override this method in your import configuration:

```php
public function allowOverrideEntity(): bool
{
    return true;
}
```

Then user who prepares the file can update any entity of imported class, and the default select in the preview
shows all of them. Allow it only for users who should be able to do it.

## Allowed columns

Every configuration has to define list of columns which can be imported. Column is imported only when it is on this list,
and entity has public property or setter with the same name. So user who prepares the file can't set properties
which should not be changed by import (e.g. roles or password of user).

```php
public function getAllowedColumns(): array
{
    return ['user_name', 'age', 'email'];
}
```

- Columns which are not on the list are treated the same way as unknown columns: they are marked in preview and skipped during import.
- Names are compared the same way as names of columns with names of properties, so `first_name`, `first-name`, `first name` and `firstName` mean the same column.
- Locale of translated column is ignored, so `name` allows also `name:en` and `name:pl`.
- Empty array means that no column can be imported. File without any column which can be imported is rejected, so new entities are never created without values from file.

## Set allowed file extensions

By default, allowed file extensions are set to `'csv', 'xls', 'xlsx', 'ods'`.
However, if you want to change it, you can override this method in your import configuration.
File is read by PhpSpreadsheet reader with the same name as extension (e.g. `Csv` for `csv`), so extension without such reader (e.g. `txt`)
causes exception when file is uploaded. To read other files, see [Changing the way of reading files](files.md#changing-the-way-of-reading-files).

```php
  public function getAllowedFileExtensions(): array
  {
      return ['csv', 'xls', 'xlsx', 'ods'];
  }
```

## Optimizing queries

If you use **KnpLabs Translatable** extension for your entity, probably you will notice increased number of queries, because of Lazy Loading.

To optimize this, you can use `getEntityTranslationRelationName()` method to pass the relation name to the translation.
It is used by the default select of overridden entity (see [Updating entities](updating-entities.md#field-used-to-select-entity)).

```php
public function getEntityTranslationRelationName(): ?string
{
    return 'translations';
}
```

[Back to README](../README.md)
