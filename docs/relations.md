# Importing relations

If your entity has a `ManyToOne` or `OneToOne` relation, you can import it by a value of any field of related entity (for example `id`, `name` or `slug`).

- There are two field types:
  - `EntityLookupType` - field is rendered as a select with all related entities.
    - Label is taken from field set in `label_field` option. If it is not set, `__toString()` method is used if related entity implements it. Otherwise lookup value is used.
    - Using `label_field` is recommended, because only needed fields are selected from database, instead of loading whole entities.
    - Recommended for small tables (e.g. categories, statuses).
  - `EntityLookupTextType` - field is rendered as a text input. Value is validated if related entity exists.
    - Recommended for big tables, because select with thousands of options would be repeated in every row of preview.
    - Lookup value is visible in preview, so it is better to use human-readable field (e.g. `name`, `slug`) instead of `id`.
- During import, value is replaced by entity found by `lookup_field`.
- Empty value sets relation to `null`.
- **IMPORTANT!** Values of lookup field should be unique, otherwise first found entity will be used.
- **IMPORTANT!** Values are compared with case sensitivity in `EntityLookupType`. In `EntityLookupTextType` it depends on database collation (e.g. MySQL is usually case-insensitive, PostgreSQL and SQLite are case-sensitive).
- Leading and trailing whitespaces are trimmed.

## Entity

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Product
{
    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?Category $category = null;

    public function setCategory(?Category $category): void
    {
        $this->category = $category;
    }
}
```

## Import configuration

* You have to add a field definition with a custom `EntityLookupType` or `EntityLookupTextType` type.
* `class` option is required.
* `lookup_field` option is optional. Default value is `id`.
* `label_field` option is optional and available only for `EntityLookupType`.

```php
use App\Entity\Category;
use App\Entity\Manufacturer;
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
                'lookup_field' => 'slug',
                'label_field' => 'name',
            ]
        ),
        // or for big tables:
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

## CSV file

```csv
name,price,category,manufacturer
Phone,999,electronics,acme
T-shirt,20,clothes,cotton-company
Gift card,50,,
```

[Back to README](../README.md)
