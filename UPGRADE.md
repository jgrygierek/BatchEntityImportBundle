# UPGRADE TO 4.0

## Requirements

- PHP 8.3 is no longer supported. Minimal required version is PHP 8.4.
- Symfony 6.4 and versions 7.0 - 7.3 are no longer supported. Supported versions are Symfony 7.4 and 8.
- Doctrine ORM 2 is no longer supported. Minimal required version is `doctrine/orm` 3.3.
- Doctrine DBAL 2 and 3 are no longer supported. Minimal required version is `doctrine/dbal` 4.0.
- Minimal versions of other packages:
  - `doctrine/collections`: 2.2,
  - `doctrine/persistence`: 3.3.1,
  - `symfony/event-dispatcher-contracts`, `symfony/service-contracts` and `symfony/translation-contracts`: 3.0.
- `doctrine/doctrine-bundle` and `symfony/yaml` are now listed in requirements of bundle, because its services depend on them. Both are installed in typical Symfony application, so usually nothing has to be changed in your project.

## Twig

- `TemplateExtension` no longer extends `Twig\Extension\AbstractExtension`, function `batch_entity_import_template` is registered by `#[AsTwigFunction]` attribute. Nothing changes in templates. Method `getFunctions()` was removed.

## Import configuration

- Method `getAllowedColumns()` was moved to `ImportConfigurationInterface`, and `AllowedColumnsConfigurationInterface` was removed. If your configuration extends `AbstractImportConfiguration`, only remove the interface from `implements`, if you added it there. If your configuration implements `ImportConfigurationInterface` directly, add the method (empty array means that all columns are allowed):

  ```php
  public function getAllowedColumns(): array
  {
      return [];
  }
  ```

- Argument `RelatedEntityFinder $relatedEntityFinder` of `AbstractImportConfiguration` constructor is required. If your configuration does not override constructor, nothing has to be changed. Otherwise pass the finder to parent constructor:

  ```php
  // before
  public function __construct(EntityManagerInterface $em, EventDispatcherInterface $eventDispatcher, TestService $service)
  {
      parent::__construct($em, $eventDispatcher);
  }

  // after
  public function __construct(
      EntityManagerInterface $em,
      EventDispatcherInterface $eventDispatcher,
      RelatedEntityFinder $relatedEntityFinder,
      TestService $service,
  ) {
      parent::__construct($em, $eventDispatcher, $relatedEntityFinder);
  }
  ```

- Method `AbstractImportConfiguration::prepareRecord()` has new required argument `int $rowNumber` (number of record, starting from 1, used in error messages). If you override this method or call it in overridden `import()`, add the argument:

  ```php
  // before
  protected function prepareRecord(MatrixRecord $record, array $headerInfo): void

  // after
  protected function prepareRecord(MatrixRecord $record, array $headerInfo, int $rowNumber): void
  ```

- Argument `array $allowedColumns` of `Matrix::getHeaderInfo()` is required. If you override `prepareMatrixEditView()` in your controller, pass allowed columns to it:

  ```php
  $matrix->getHeaderInfo($configuration->getEntityClassName(), $configuration->getAllowedColumns())
  ```

## Controller

- Import configuration is passed to controller by new `#[AsImportController]` attribute. `ImportConfigurationAutoInjectInterface`, `ImportConfigurationAutoInjectTrait` and method `getImportConfigurationClassName()` were removed:

  ```php
  // before
  class ImportController extends AbstractController implements ImportConfigurationAutoInjectInterface
  {
      use ImportControllerTrait;
      use ImportConfigurationAutoInjectTrait;

      protected function getImportConfigurationClassName(): string
      {
          return UserImportConfiguration::class;
      }
  }

  // after
  use JG\BatchEntityImportBundle\Attribute\AsImportController;

  #[AsImportController(configuration: UserImportConfiguration::class)]
  class ImportController extends AbstractController
  {
      use ImportControllerTrait;
  }
  ```

- Controller has to be registered as autoconfigured service (default in Symfony), the same as before. The attribute has to be added to class of controller itself, it is not inherited from parent class.
- Configuration is checked when container is built. If class used in the attribute is not registered as a service, container can not be built. Previously `ServiceNotFoundException` was thrown when controller was used.
- Controller gets now the same instance of configuration as other services. Previously separate instances of all configurations were created for each controller.
- Controller without the attribute throws `LogicException` when configuration is used, instead of `ServiceNotFoundException`.
- Controller has new public method `setImportConfiguration(ImportConfigurationInterface $importConfiguration)`, added by `ImportControllerTrait`. If your controller already has method with this name, rename it. If your controller is not autoconfigured, call this method in definition of service.
- Tags `batch_entity_import.configuration` and `batch_entity_import.controller` are no longer added to import configurations and controllers, and `AutoConfigureControllerCompilerPass` was removed.
- Configuration can not be selected in runtime by `getImportConfigurationClassName()` anymore. If one controller was used for many imports, create separate controller for each configuration.

## Validation constraints

- `MatrixRecordUnique` and `DatabaseEntityUnique` constraints no longer accept array of options, use named arguments:

  ```php
  // before
  new MatrixRecordUnique(['fields' => ['field_name']]),
  new DatabaseEntityUnique(['entityClassName' => $this->getEntityClassName(), 'fields' => ['field_name']]),

  // after
  new MatrixRecordUnique(fields: ['field_name']),
  new DatabaseEntityUnique(entityClassName: $this->getEntityClassName(), fields: ['field_name']),
  ```

- Order of constructor arguments was changed, so they should be always passed by name:
  - `MatrixRecordUnique(array $fields, ?string $message = null, ?array $groups = null, mixed $payload = null)`,
  - `DatabaseEntityUnique(string $entityClassName, array $fields, ?string $message = null, ?array $groups = null, mixed $payload = null)`.

## Exceptions

- `MatrixRecordInvalidDataTypeException` and `MatrixRecordRelatedEntityNotFoundException` always contain number of row and name of column. Their constructors were changed to `__construct(int $rowNumber, string $columnName, ?Throwable $previous = null)`, and static method `forField()` was removed.
- Translation keys `exception.matrix.record.data.invalid_type` and `exception.matrix.record.related_entity.not_found` were removed. Only keys with position are used:
  - `exception.matrix.record.data.invalid_type_in_field`,
  - `exception.matrix.record.related_entity.not_found_in_field`.

# UPGRADE TO 3.14

## PHP

- PHP 8.2 is no longer supported. Minimal required version is PHP 8.3.

# UPGRADE TO 3.13

## Allowed columns

- List of columns which can be imported can be defined in import configuration. By default nothing changes and all columns are allowed, but it is recommended to define the list, because without it user who prepares the file can set every property of entity which has a setter or is public.

  ```php
  public function getAllowedColumns(): array
  {
      return ['user_name', 'age', 'email'];
  }
  ```

- Columns which are not on the list are marked in preview and skipped during import, the same way as unknown columns.
- Method is defined in new `AllowedColumnsConfigurationInterface`, which is implemented by `AbstractImportConfiguration`. If your configuration implements only `ImportConfigurationInterface`, nothing changes until you implement the new interface too.
- `Matrix::getHeaderInfo()` has new optional argument `array $allowedColumns = []`. If you override `prepareMatrixEditView()` in your controller, pass allowed columns to it, otherwise not allowed columns will not be marked in preview (they are skipped during import anyway).

## Position of invalid data in error messages

- When value can't be set to entity (`MatrixRecordInvalidDataTypeException`) or related entity is not found (`MatrixRecordRelatedEntityNotFoundException`), error message contains number of row and name of column. Rows are numbered from 1 in the same order as in preview, header is not counted.
- These messages use new translation keys with `%row%` and `%column%` parameters:
  - `exception.matrix.record.data.invalid_type_in_field`,
  - `exception.matrix.record.related_entity.not_found_in_field`.

  If you override translations of `exception.matrix.record.data.invalid_type` or `exception.matrix.record.related_entity.not_found`, override new keys too. Old keys are still used by exceptions created without position.
- `MatrixException::getTranslationParameters()` returns parameters for translation of exception message. If you catch exceptions of this bundle in your own code and translate their messages, pass these parameters to translator.
- Number of row is known only during `AbstractImportConfiguration::import()`. If you override this method and call `prepareRecord()` on your own, messages have no position.

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

## Reading files

- Rows without any value are skipped. Previously empty lines in CSV file were imported as empty records.
- CSV delimiter is detected only in header line and outside of quoted values, so delimiters used in text of records do not affect the result.
- Encoding of CSV file is never guessed. File encoded in Unicode (with BOM or with valid UTF-8 content) is read as before. Other files are rejected with error message, because their encoding can't be recognized reliably and wrong guess would silently break imported data. Previously such files were read with invalid characters.
- If your files use other encoding, set it in bundle configuration:

  ```yaml
  batch_entity_import:
      csv_fallback_encoding: CP1250
  ```

  or override `getCsvFallbackEncoding()` method in single controller:

  ```php
  protected function getCsvFallbackEncoding(): ?string
  {
      return 'CP1250';
  }
  ```

- `MatrixFactory::createFromUploadedFile()` throws `FileReadException` when file can't be read (e.g. it is corrupted), instead of exception of PhpSpreadsheet, and `FileEncodingException` when encoding of CSV file is not recognized. Controller shows an error message in such case.

## Header

- Names of columns have to be unique, file with duplicated names is rejected. Previously value of first column was silently lost.
- Names of columns are trimmed. Name `0` is a valid name now.
- Name has to start with letter, digit or underscore.
- Reserved names `entity` and `entity_id` are recognized also when written with spaces, e.g. column `entity id` is used as identifier of entity to update, the same way as `entity_id`.

## Saving data

- Request with invalid structure of data (e.g. modified manually) is rejected with "Data not found" message instead of error 500.
- Entity selected in `entity_id` column is preselected in preview also when its identifier is passed as integer (e.g. when matrix is created in your code). Previously only identifiers passed as strings were preselected.

## Dependencies

- Packages used directly by bundle are now listed in its requirements: `doctrine/collections`, `doctrine/dbal`, `doctrine/persistence`, `symfony/event-dispatcher-contracts`, `symfony/http-foundation`, `symfony/http-kernel`, `symfony/options-resolver`, `symfony/service-contracts` and `symfony/translation-contracts`. All of them were already installed as dependencies of other required packages, and their allowed versions are not narrowed, so nothing has to be changed in your project.
- Minimal version of `symfony/translation` is `6.4.3` for Symfony 6.4 and `7.0.3` for Symfony 7.

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
