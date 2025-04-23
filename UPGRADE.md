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
