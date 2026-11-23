# Events

Events are dispatched during import, so you can react to it or change imported data.
Each event, except `RecordImportedSuccessfullyEvent`, contains import configuration, so listener can check which import is running.

| Event | When | Exception thrown by listener |
|---|---|---|
| `BeforeImportEvent` | once, before any record is imported | stops import, nothing is saved |
| `RecordValueEvent` | for each imported value, before it is set to entity | stops import, changes are reverted |
| `RecordPreparedEvent` | for each record, after all values are set, before entity is persisted | stops import, changes are reverted |
| `RecordImportedSuccessfullyEvent` | for each record, after data is saved in database | data is already saved |
| `AfterImportEvent` | once, after all `RecordImportedSuccessfullyEvent` events | data is already saved |

All events are in `JG\BatchEntityImportBundle\Event` namespace.

## Converting values

Values from file are strings (or other scalars), only values of [array fields](array-fields.md) and [relations](relations.md) are converted by the bundle.
If setter of entity expects other type (e.g. date or enum), convert the value in `RecordValueEvent` listener:

```php
use App\Model\ImportConfiguration\UserImportConfiguration;
use JG\BatchEntityImportBundle\Event\RecordValueEvent;
use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class UserImportValueListener
{
    public function __invoke(RecordValueEvent $event): void
    {
        if (!$event->configuration instanceof UserImportConfiguration || 'birth_date' !== $event->columnName) {
            return;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $event->getValue());
        if (false === $date) {
            // message with number of row and name of column is shown to user, and nothing is imported
            throw new MatrixRecordInvalidDataTypeException($event->rowNumber, $event->columnName);
        }

        $event->setValue($date);
    }
}
```

`RecordValueEvent` contains:
- `configuration` - import configuration,
- `record` - record from matrix,
- `entity` - entity to which value is set (or its translation, for translated column),
- `columnName` - name of column, e.g. `birth_date` or `name:en`,
- `rowNumber` - number of record, starting from 1, as in the preview,
- `getValue()` and `setValue()` - value which will be set to entity.

## Changing entities

`RecordPreparedEvent` is dispatched when all values from record are set to entity. It can be used to set values which are not imported from file:

```php
#[AsEventListener]
public function onRecordPrepared(RecordPreparedEvent $event): void
{
    if ($event->entity instanceof User) {
        $event->entity->setImportedAt(new \DateTimeImmutable());
    }
}
```

`RecordPreparedEvent` contains `configuration`, `record`, `entity` and `rowNumber`.

## Before and after import

- `BeforeImportEvent` contains `configuration` and `matrix` with all records.
- `AfterImportEvent` contains `configuration` and `entities` - list of imported entities, one for each record.

## RecordImportedSuccessfullyEvent

For each successfully saved record, event `RecordImportedSuccessfullyEvent` is dispatched. This event contains two fields:
- `class` - entity class name,
- `id` - entity id: integer identifier is passed as it is, other identifiers (e.g. UUID object) are cast to string. For composite identifier only its first value is used.

[Back to README](../README.md)
