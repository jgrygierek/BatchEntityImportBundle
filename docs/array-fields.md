# Importing data to array field

If your entity has an array field, and you want to import data from CSV file to it, this is how you can do it.

- Default separator is set to `|`.
- Only one-dimensional arrays are allowed.
- Keys are not allowed.
- **IMPORTANT!** There are limitations:
  - There is no possibility to import array with one empty element, for example:
    - ['']
    - [null]
  - But arrays with at least 2 such elements are allowed:
    - ['', '']
    - [null, null]
    - ['', null]
  - It is due of mapping CSV data to array:
    - Empty value in CSV is equal to `[]`
    - Value `0` in CSV is equal to `['0']`
    - If we have default separator, `|` value in CSV is equal to `['', '']`

## Entity

- Allowed entity field types:
  - `json`
  - `array`
  - `simple_array`

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
    #[ORM\Column(type: 'json')]
    private array $roles = [];
}
```

## Import configuration

* You have to add a field definition with a custom `ArrayTextType` type. If you skip this configuration, `TextType` will be used as default.
* You can set here your own separator.

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

## CSV file

```csv
user_name,roles
user_1,USER&ADMIN&SUPER_ADMIN
user_2,USER
user_3,SUPER_ADMIN
```

[Back to README](../README.md)
