<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Import;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Event\AfterImportEvent;
use JG\BatchEntityImportBundle\Event\BeforeImportEvent;
use JG\BatchEntityImportBundle\Event\RecordImportedSuccessfullyEvent;
use JG\BatchEntityImportBundle\Event\RecordPreparedEvent;
use JG\BatchEntityImportBundle\Event\RecordValueEvent;
use JG\BatchEntityImportBundle\Exception\BatchEntityImportExceptionInterface;
use JG\BatchEntityImportBundle\Exception\DatabaseException;
use JG\BatchEntityImportBundle\Exception\DatabaseNotUniqueDataException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordRelatedEntityNotFoundException;
use JG\BatchEntityImportBundle\Form\Type\AbstractEntityLookupType;
use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use JG\BatchEntityImportBundle\Utils\EntityManagerHelper;
use JG\BatchEntityImportBundle\Utils\EntityTranslationHelper;
use ReflectionMethod;
use RuntimeException;
use Stringable;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TypeError;

/**
 * Saves data from matrix in database, according to import configuration.
 * Service is stateless, so one instance is used for all configurations and imports.
 *
 * @internal used by ImportHandler, it is not covered by backward compatibility promise yet
 */
final readonly class EntityImporter
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private EventDispatcherInterface $eventDispatcher,
        private RelatedEntityFinder $relatedEntityFinder,
        private FormRegistryInterface $formRegistry,
    ) {
    }

    /**
     * Imports all records or nothing: when any record is invalid, changes made so far are reverted.
     *
     * @throws MatrixRecordInvalidDataTypeException when value can't be set to entity
     * @throws MatrixRecordRelatedEntityNotFoundException when related entity is not found
     * @throws DatabaseNotUniqueDataException when unique constraint in database is violated
     * @throws DatabaseException when data can't be saved in database
     * @throws InvalidArgumentException when entity is not managed by Doctrine ORM
     * @throws RuntimeException when listener throws exception of this bundle after data is saved
     */
    public function import(Matrix $matrix, ImportConfigurationInterface $configuration): void
    {
        $entityClass = $configuration->getEntityClassName();
        $entityManager = EntityManagerHelper::getEntityManager($this->managerRegistry, $entityClass);
        $columns = $this->getImportedColumns($matrix, $configuration);

        /** @var list<object> $importedEntities one entity for each record, the same entity can be overridden by more records */
        $importedEntities = [];
        /** @var array<int, object> $touchedEntities entities changed so far, indexed by object id */
        $touchedEntities = [];

        $this->eventDispatcher->dispatch(new BeforeImportEvent($configuration, $matrix));

        try {
            foreach (array_values($matrix->getRecords()) as $index => $record) {
                $entity = $record->getEntity() ?? $configuration->getNewEntity($record);
                $touchedEntities[spl_object_id($entity)] = $entity;

                // number of record, starting from 1, as in the preview, used in error messages
                $rowNumber = $index + 1;
                $this->setValues($configuration, $entity, $record, $columns, $rowNumber);
                $this->eventDispatcher->dispatch(new RecordPreparedEvent($configuration, $record, $entity, $rowNumber));

                $entityManager->persist($entity);
                EntityTranslationHelper::mergeNewTranslations($entity);
                $importedEntities[] = $entity;
            }
        } catch (Throwable $throwable) {
            // not only exceptions of this bundle, incomplete import can't be left in entity manager in any case
            $this->revertChanges($entityManager, $touchedEntities);

            throw $throwable;
        }

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new DatabaseNotUniqueDataException();
        } catch (DBALException) {
            throw new DatabaseException();
        }

        // outside of the block above, data is already saved, so exception thrown by listener is not reported as error of database
        try {
            $this->dispatchEvents($entityManager, $entityClass, $importedEntities);
            $this->eventDispatcher->dispatch(new AfterImportEvent($configuration, $importedEntities));
        } catch (BatchEntityImportExceptionInterface $batchEntityImportException) {
            // Exceptions of this bundle are shown to user as errors of import, together with the preview to correct data.
            // Data is already saved, so the same data could be saved twice. Other exceptions are thrown as they are.
            throw new RuntimeException(\sprintf('Data is already saved, so exception "%s" thrown by listener after saving can not be reported as error of import: %s', $batchEntityImportException::class, $batchEntityImportException->getMessage()), 0, $batchEntityImportException);
        }
    }

    /**
     * Everything which depends only on column is prepared once, not for each value.
     *
     * @return array<string, ImportedColumn> columns which are imported, indexed by name
     */
    private function getImportedColumns(Matrix $matrix, ImportConfigurationInterface $configuration): array
    {
        $fieldDefinitions = $configuration->getFieldsDefinitions();
        $columns = [];

        foreach ($matrix->getHeaderInfo($configuration->getEntityClassName(), $configuration->getAllowedColumns()) as $name => $isImported) {
            if (!$isImported) {
                continue;
            }

            // the same key as in MatrixRecordType, so definition is used also for translated columns, e.g. "name:en"
            $fieldDefinition = $fieldDefinitions[$name] ?? $fieldDefinitions[ColumnNameHelper::removeTranslationSuffix($name)] ?? null;
            $columns[$name] = new ImportedColumn(
                name: $name,
                propertyName: ColumnNameHelper::toCamelCase($name),
                setterName: ColumnNameHelper::getSetterName($name),
                locale: ColumnNameHelper::getLocale($name),
                fieldDefinition: $fieldDefinition,
                arraySeparator: $this->getArraySeparator($fieldDefinition),
            );
        }

        return $columns;
    }

    /**
     * Options of field are resolved by its form type, the same way as in the preview,
     * so default separator set in configureOptions() of ArrayTextType subclass is used too.
     *
     * @return non-empty-string|null null when field is not an array field
     */
    private function getArraySeparator(?FormFieldDefinition $definition): ?string
    {
        if (null === $definition || !is_a($definition->getClass(), ArrayTextType::class, true)) {
            return null;
        }

        $options = $this->formRegistry->getType($definition->getClass())->getOptionsResolver()->resolve($definition->getOptions());

        return ArrayTextType::getSeparator($options);
    }

    /**
     * @param array<string, ImportedColumn> $columns
     *
     * @throws MatrixRecordInvalidDataTypeException
     * @throws MatrixRecordRelatedEntityNotFoundException
     */
    private function setValues(ImportConfigurationInterface $configuration, object $entity, MatrixRecord $record, array $columns, int $rowNumber): void
    {
        foreach ($record->getData() as $name => $value) {
            $column = $columns[$name] ?? null;
            if (null === $column) {
                continue;
            }

            $value = $this->convertValue($column, $value, $rowNumber);

            // translated column is imported only to translatable entity, otherwise it is not imported at all (see Matrix::getHeaderInfo())
            $target = null !== $column->locale ? EntityTranslationHelper::getTranslation($entity, $column->locale) : $entity;
            if (null !== $target) {
                $event = $this->eventDispatcher->dispatch(new RecordValueEvent($configuration, $record, $target, $name, $rowNumber, $value));
                $this->setValue($target, $column, $event->getValue(), $rowNumber);
            }
        }
    }

    /**
     * @param object $target entity or its translation
     *
     * @throws MatrixRecordInvalidDataTypeException
     */
    private function setValue(object $target, ImportedColumn $column, mixed $value, int $rowNumber): void
    {
        try {
            // the same rules as in PropertyExistenceChecker: not public setter is skipped, and value is set to public property
            if (self::hasPublicMethod($target, $column->setterName)) {
                $target->{$column->setterName}($value);
            } else {
                $target->{$column->propertyName} = $value;
            }
        } catch (TypeError $typeError) {
            throw new MatrixRecordInvalidDataTypeException($rowNumber, $column->name, $typeError);
        }
    }

    /**
     * is_callable() can't be used, because it returns true for any method of class with __call() method
     * (e.g. translatable entities of "knplabs/doctrine-behaviors").
     */
    private static function hasPublicMethod(object $target, string $method): bool
    {
        return method_exists($target, $method) && new ReflectionMethod($target, $method)->isPublic();
    }

    /**
     * @throws MatrixRecordRelatedEntityNotFoundException
     */
    private function convertValue(ImportedColumn $column, string|int|float|bool|null $value, int $rowNumber): mixed
    {
        $definition = $column->fieldDefinition;

        return match (true) {
            null === $definition => $value,
            null !== $column->arraySeparator => $this->parseArrayValue($column->arraySeparator, $value),
            is_a($definition->getClass(), AbstractEntityLookupType::class, true) => $this->findRelatedEntity($definition, $value, $column->name, $rowNumber),
            default => $value,
        };
    }

    /**
     * @param non-empty-string $separator
     *
     * @return array<string>
     */
    private function parseArrayValue(string $separator, string|int|float|bool|null $value): array
    {
        $value = (string) $value;

        // only empty value means empty array, "0" is a value like any other
        return '' === $value ? [] : explode($separator, $value);
    }

    /**
     * @throws MatrixRecordRelatedEntityNotFoundException
     */
    private function findRelatedEntity(FormFieldDefinition $definition, string|int|float|bool|null $value, string $columnName, int $rowNumber): ?object
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $options = $definition->getOptions();
        /** @var string $class */
        $class = $options['class'];
        /** @var string $lookupField */
        $lookupField = $options['lookup_field'] ?? RelatedEntityFinder::DEFAULT_LOOKUP_FIELD;

        return $this->relatedEntityFinder->find($class, $lookupField, $value)
            ?? throw new MatrixRecordRelatedEntityNotFoundException($rowNumber, $columnName);
    }

    /**
     * When import fails in the middle, records prepared so far are already persisted or modified.
     * Without reverting them, any later flush in the same request would save incomplete import.
     *
     * @param array<int, object> $entities
     */
    private function revertChanges(EntityManagerInterface $entityManager, array $entities): void
    {
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($entities as $entity) {
            foreach (EntityTranslationHelper::clearNewTranslations($entity) as $translation) {
                if ($entityManager->contains($translation) && !$unitOfWork->isScheduledForInsert($translation)) {
                    $entityManager->refresh($translation);
                }
            }

            if ($unitOfWork->isScheduledForInsert($entity)) {
                $entityManager->detach($entity);
            } elseif ($entityManager->contains($entity)) {
                $entityManager->refresh($entity);
            }
        }
    }

    /**
     * @param list<object> $entities
     */
    private function dispatchEvents(EntityManagerInterface $entityManager, string $entityClass, array $entities): void
    {
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($entities as $entity) {
            $identifierValues = $unitOfWork->getEntityIdentifier($entity);

            $this->eventDispatcher->dispatch(
                new RecordImportedSuccessfullyEvent($entityClass, $this->normalizeIdentifier(\reset($identifierValues))),
            );
        }
    }

    /**
     * Integer is passed as it is, other identifiers (e.g. UUID object) are cast to string.
     */
    private function normalizeIdentifier(mixed $identifier): string|int
    {
        return match (true) {
            \is_int($identifier) => $identifier,
            \is_scalar($identifier), $identifier instanceof Stringable => (string) $identifier,
            default => '',
        };
    }
}
