# Importing data to array field

If your entity has an array field, and you want to import data from CSV file to it, this is how you can do it.

- Default separator is set to `|`.
- Only one-dimensional arrays are allowed.
- Keys are not allowed.
- Elements of array are always strings.
- **IMPORTANT!** Array with one empty element (`['']`) can't be imported, because value is split by separator:
  - Empty value is imported as `[]`.
  - Value `0` is imported as `['0']`.
  - Value `|` (with default separator) is imported as `['', '']`, so arrays with at least 2 empty elements are possible.

## Entity

- Allowed entity field types:
  - `json`
  - `simple_array` (values can't contain comma, because it is used as separator in database)
- Entity needs public setter (or public property), the same as for other columns.

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }
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
