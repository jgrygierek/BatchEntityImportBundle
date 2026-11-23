# Import files

Supported formats: **CSV, XLS, XLSX, ODS**. Only active sheet of spreadsheet file is imported (the one which was opened when file was saved).

## Header

First row of file is a header with names of columns.

- Names are required. Column without name is removed from loaded data.
- Name can contain only letters without diacritics, digits, underscores, hyphens and spaces, and it has to start with letter, digit or underscore.
- Leading and trailing whitespaces are removed, and spaces are replaced with underscores. So in configuration class (fields definitions, constraints) use `first_name` for column `first name`.
- Names have to be unique. File with duplicated names is rejected.
- Name can be suffixed with locale (e.g. `name:en`), see [Translatable columns](#translatable-columns).
- Names `entity` and `entity_id` are reserved, see [Updating entities](updating-entities.md).
- Column is imported when entity has public property or setter with the same name (`first_name`, `first-name`, `firstName` and `FirstName` are all mapped to `firstName` property and `setFirstName()` method). Other columns are marked in preview as unknown and they are skipped during import. The same applies to columns which are not on the list of [allowed columns](configuration.md#allowed-columns).

## Rows

- Every row is imported as one record.
- Rows without any value are skipped.

## CSV files

### Delimiter

Comma (`,`) and semicolon (`;`) are supported. Delimiter is detected automatically from header line.

### Encoding

Encoding of CSV file is never guessed. File encoded in Unicode (with BOM or with valid UTF-8 content) is always read correctly.
Other files are rejected with error message, because their encoding can't be recognized reliably and wrong guess would silently break imported data.

If your files use other encoding (for example `CP1250` for Central European languages), set it in bundle configuration:

```yaml
batch_entity_import:
    csv_fallback_encoding: CP1250
```

or override this method in single controller:

```php
protected function getCsvFallbackEncoding(): ?string
{
    return 'CP1250';
}
```

CSV file encoded in UTF-16 has to contain BOM, otherwise it can not be read.

## Size of file

Preview of data is a form, where every value is a separate field. PHP silently drops all fields above `max_input_vars` limit (1000 by default),
so when number of sent fields reaches this limit, data is not saved and error message is shown.

Number of fields is about `number of rows * (number of columns + 1)`. To import bigger files, increase `max_input_vars` in PHP configuration.

## Translatable columns

This bundle supports KnpLabs Translatable behavior.

To use this feature, every column with translatable values should be suffixed with locale, for example:
* `name:en`
* `description:pl`
* `title:ru`

If suffix will be added to non-translatable entity, field will be skipped.

If suffix will be added to translatable entity, but field will not be found in translation class, field will be skipped.

## Changing the way of reading files

Files are read by `MatrixFactory` service. To change it (e.g. to import other sheet than active one, or to rename columns),
decorate this service. Example below renames columns, so file can have headers in other language than names of properties:

```php
namespace App\Import;

use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[AsDecorator(MatrixFactory::class)]
final class HeaderAliasesMatrixFactory extends MatrixFactory
{
    private const array ALIASES = ['imie' => 'first_name', 'nazwisko' => 'last_name'];

    public function __construct(#[AutowireDecorated] private readonly MatrixFactory $inner)
    {
        parent::__construct();
    }

    public function createFromUploadedFile(UploadedFile $file, ?string $csvFallbackEncoding = null): Matrix
    {
        $matrix = $this->inner->createFromUploadedFile($file, $csvFallbackEncoding);
        $rename = static fn (string $name): string => self::ALIASES[$name] ?? $name;

        return new Matrix(
            array_map($rename, $matrix->getHeader()),
            array_map(
                static fn (MatrixRecord $record): array => [
                    ...array_combine(array_map($rename, array_keys($record->getData())), $record->getData()),
                    // identifier of entity is not a part of data, so it has to be passed again
                    'entity_id' => $record->entityId,
                ],
                $matrix->getRecords(),
            ),
        );
    }
}
```

Data sent from the preview is read by `createFromPostData()` method, it has names of columns which are already changed.

## Full example of CSV file

```csv
entity_id,user_name,age,email,roles,country:en,name:pl
1,user_1,21,user_1@test.com,USER&ADMIN&SUPER_ADMIN,Poland,Polska
3, user_2,34,user_2@test.com,USER,England,Anglia
,user_3,56,user_3@test.com,SUPER_ADMIN,Germany,Niemcy
```

[Back to README](../README.md)
