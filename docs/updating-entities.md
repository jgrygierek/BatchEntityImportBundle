# Updating entities

Updating entities is enabled by default (see `allowOverrideEntity()` in [Configuration class](configuration.md#show--hide-entity-override-column)).
In the preview every record has additional column, where entity which should be updated by this record can be selected.

To select entities already in your import file:
  - Add `entity_id` in header and:
    - Add entity ID to row
    - Leave it empty (if you want to set it manually or import it as new record)
  - Or if you don't want to add `entity_id` header, you can still manually set each entity to override.

## CSV file

```csv
entity_id,user_name
2,user_1
,user_2
10,user_3
```

## Field used to select entity

By default entity is selected in the preview by a select with all entities. It is repeated in every row,
so for bigger tables it is better to use a text field, where entity is found by value of any of its fields (e.g. `id` or `email`).
Value of `entity_id` column in file is used as value of this field.

```php
use JG\BatchEntityImportBundle\Form\Type\EntityLookupTextType;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;

public function getOverrideEntityFieldDefinition(): ?FormFieldDefinition
{
    return new FormFieldDefinition(EntityLookupTextType::class, ['lookup_field' => 'email']);
}
```

```csv
entity_id,user_name
john@example.com,user_1
,user_2
```

- `EntityLookupTextType` renders a text field, value is validated if entity exists.
- `EntityLookupType` renders a select, but only fields set in `lookup_field` and `label_field` options are loaded from database, instead of whole entities.
- Option `class` is set automatically to class of imported entity. Other options are the same as in [Importing relations](relations.md).

[Back to README](../README.md)
