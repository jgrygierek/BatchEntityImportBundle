# UPGRADE TO 3.12

## Validation constraints

- `MatrixRecordUnique` and `DatabaseEntityUnique` constraints can be configured with named arguments. It is recommended way now:

  ```php
  new MatrixRecordUnique(fields: ['field_name']),
  new DatabaseEntityUnique(entityClassName: $this->getEntityClassName(), fields: ['field_name'], message: 'custom message'),
  ```

- Array of options passed as first argument is still supported. Options `message`, `groups` and `payload` passed this way were ignored on Symfony 8, now they work again.
- Methods `getDefaultOption()` and `getRequiredOptions()` were removed from both constraints. Unknown option throws `InvalidOptionsException`.
- `DatabaseEntityUnique` no longer keeps results between validations and checks record overriding an entity separately from a new record with the same values.

## Import

- When import fails before saving (e.g. `MatrixRecordInvalidDataTypeException`), changes made to entities so far are reverted: new entities are detached and overridden entities are refreshed from database. If you want to change this behaviour, override `AbstractImportConfiguration::revertChanges()`.
- Field definition is now used during import also for translated columns (e.g. definition `roles` for column `roles:en`), the same way as in edit form.
- Request with number of fields reaching PHP `max_input_vars` limit is rejected with error message, because PHP silently drops fields above this limit. To import bigger files, increase `max_input_vars`. Check can be changed by overriding `isRequestDataTruncated()` method in controller.

# UPGRADE TO 3.10

## Import relations

- If your entity has a `ManyToOne` or `OneToOne` relation, it can be imported by a value of any field of related entity (for example `id`, `name` or `slug`).
- There are two new field types:
  - `EntityLookupType` - renders a select with all related entities. Recommended for small tables.
  - `EntityLookupTextType` - renders a text input, value is validated by new `RelatedEntityExists` constraint. Recommended for big tables.
- Empty value sets relation to `null`.
- If related entity is not found during import, `MatrixRecordRelatedEntityNotFoundException` is thrown.
- `AbstractImportConfiguration` constructor has new optional argument `?RelatedEntityFinder $relatedEntityFinder = null`.
  - It is injected automatically if your configuration is autowired.
  - If you override constructor and call `parent::__construct($em, $eventDispatcher)`, nothing has to be changed - finder based on passed entity manager will be used.

  ```php
  use JG\BatchEntityImportBundle\Form\Type\EntityLookupTextType;
  use JG\BatchEntityImportBundle\Form\Type\EntityLookupType;
  use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;

  public function getFieldsDefinitions(): array
  {
      return [
          'category' => new FormFieldDefinition(
              EntityLookupType::class,
              [
                  'class' => Category::class,
                  'lookup_field' => 'id', // default: 'id'
                  'label_field' => 'name', // optional, default: __toString() method
              ]
          ),
          'manufacturer' => new FormFieldDefinition(
              EntityLookupTextType::class,
              [
                  'class' => Manufacturer::class,
                  'lookup_field' => 'slug',
              ]
          ),
      ];
  }
  ```

# UPGRADE TO 3.8

## RecordImportedSuccessfullyEvent

- For each successfully processed record, event `RecordImportedSuccessfullyEvent` is dispatched.

# UPGRADE TO 3.7

## Preset entity id before updating
- If you want to update your entities automatically, you can do it in your import file:
    - Add `entity_id` in header and:
      - Add entity ID to row
      - Leave it empty (if you want to set it manually or import it as new record)
- Otherwise, you can still update it manually in next step.

# UPGRADE TO 3.6

## Allowed file extensions

- By default, allowed file extensions are set to `'csv', 'xls', 'xlsx', 'ods'`.
- You can override it in your import configuration:

  ```php
  public function getAllowedFileExtensions(): array
  {
      return ['csv', 'xls', 'xlsx', 'ods'];
  }
  ```

# UPGRADE TO 3.5

## Import data to array

- If your entity has an array field, and you want to import data from CSV file to it, it is now possible:

  ```php
  use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
  use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
  
  public function getFieldsDefinitions(): array
  {
      return [
          'roles' => new FormFieldDefinition(
              ArrayTextType::class,
              [
                  'separator' => '&',
              ]
          ),
      ];
  }
  ```

# UPGRADE TO 3.1

## CSV File
- Now CSV file can contain spaces and dashes as a header name, for example "my column name" or "my-column-name".

## Import Configuration class
- When header name contains spaces we should use underscores instead of spaces when defining fields names in fields definitions and in constraints.


# UPGRADE TO 3.0

## Controller
* Passing configuration class by `getSubscribedServices()` was removed. Now it is only possible by autoconfiguration.


# UPGRADE TO 2.5

## Import Configuration class
- Added new validator to check matrix record data uniqueness in database.

  ```php
  use JG\BatchEntityImportBundle\Validator\Constraints\DatabaseEntityUnique;
  
  public function getMatrixConstraints(): array
  {
      return [
          new DatabaseEntityUnique(['entityClassName' => $this->getEntityClassName(), 'fields' => ['field_name']]),
      ];
  }
  ```

# UPGRADE TO 2.4

## Import Configuration class
- Added new validator to check matrix record data uniqueness.

  ```php
  use JG\BatchEntityImportBundle\Validator\Constraints\MatrixRecordUnique;
  
  public function getMatrixConstraints(): array
  {
      return [
          new MatrixRecordUnique(['fields' => ['field_name']]),
      ];
  }
  ```

## Controller
- List of options passed to form in `createMatrixForm()` method, should contain new `constraints` element:
`'constraints' => $importConfiguration->getMatrixConstraints()`

# UPGRADE TO 2.3

## Controller
- Passing configuration class by `getSubscribedServices()` method is not needed anymore and will be removed in the future.
- To make sure that configuration class will be injected automatically:
  - Interface `JG\BatchEntityImportBundle\Controller\ImportConfigurationAutoInjectInterface` should be implemented.
  - Trait `JG\BatchEntityImportBundle\Controller\ImportConfigurationAutoInjectTrait` should be used to add needed methods.


# UPGRADE TO 2.2

## Import Configuration class
- Now configuration class should be always registered as a service:

  ```yaml
  services:
      App\Model\ImportConfiguration\UserImportConfiguration: ~
  ```

## Controller
- Entity Manager is no longer passed as an argument of actions.
