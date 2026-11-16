<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Configuration;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Event\RecordImportedSuccessfullyEvent;
use JG\BatchEntityImportBundle\Exception\DatabaseException;
use JG\BatchEntityImportBundle\Exception\DatabaseNotUniqueDataException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordInvalidDataTypeException;
use JG\BatchEntityImportBundle\Exception\MatrixRecordRelatedEntityNotFoundException;
use JG\BatchEntityImportBundle\Form\Type\AbstractEntityLookupType;
use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use JG\BatchEntityImportBundle\Utils\EntityTranslationHelper;
use Stringable;
use Symfony\Component\Validator\Constraint;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TypeError;

abstract class AbstractImportConfiguration implements ImportConfigurationInterface
{
    /** @var object[] */
    protected array $updatedEntities = [];
    /** @var array<int, object> entities touched during import, indexed by object id */
    private array $touchedEntities = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RelatedEntityFinder $relatedEntityFinder,
    ) {
    }

    public function getFieldsDefinitions(): array
    {
        return [];
    }

    /**
     * By default all columns are allowed. It is recommended to override this method,
     * especially when file is prepared by user who should not be able to change every property of entity.
     */
    public function getAllowedColumns(): array
    {
        return [];
    }

    public function getEntityTranslationRelationName(): ?string
    {
        return null;
    }

    /**
     * @return array<int, Constraint>
     */
    public function getMatrixConstraints(): array
    {
        return [];
    }

    public function import(Matrix $matrix): void
    {
        $this->updatedEntities = [];
        $this->touchedEntities = [];

        $headerInfo = $matrix->getHeaderInfo($this->getEntityClassName(), $this->getAllowedColumns());
        try {
            foreach (array_values($matrix->getRecords()) as $index => $record) {
                $this->prepareRecord($record, $headerInfo, $index + 1);
            }
        } catch (Throwable $throwable) {
            // not only exceptions of this bundle, incomplete import can't be left in entity manager in any case
            $this->revertChanges();

            throw $throwable;
        }

        $this->touchedEntities = [];
        $this->save();
    }

    /**
     * @param array<string, bool> $headerInfo
     * @param int $rowNumber number of record, starting from 1, as in the preview, used in error messages
     */
    protected function prepareRecord(MatrixRecord $record, array $headerInfo, int $rowNumber): void
    {
        $entity = $this->getEntity($record);
        $this->touchedEntities[spl_object_id($entity)] = $entity;
        $data = $record->getData();
        $fieldDefinitions = $this->getFieldsDefinitions();

        /** @var string|null $value */
        foreach ($data as $name => $value) {
            if (empty($headerInfo[$name])) {
                continue;
            }

            $locale = ColumnNameHelper::getLocale($name);
            $propertyName = ColumnNameHelper::toCamelCase($name);
            $setterName = ColumnNameHelper::getSetterName($name);

            // the same key as in MatrixRecordType, so definition is used also for translated columns, e.g. "name:en"
            $fieldDefinition = $fieldDefinitions[$name] ?? $fieldDefinitions[ColumnNameHelper::removeTranslationSuffix($name)] ?? null;
            if ($fieldDefinition) {
                if (ArrayTextType::class === $fieldDefinition->getClass()) {
                    $value = $this->parseValueForArrayType($fieldDefinition, $value);
                } elseif (is_a($fieldDefinition->getClass(), AbstractEntityLookupType::class, true)) {
                    $value = $this->findRelatedEntity($fieldDefinition, $value, $name, $rowNumber);
                }
            }

            try {
                // translated column is skipped, when entity is not translatable
                $target = $locale ? EntityTranslationHelper::getTranslation($entity, $locale) : $entity;
                if ($target && method_exists($target, $setterName)) {
                    $target->$setterName($value);
                } elseif ($target) {
                    $target->$propertyName = $value;
                }
            } catch (TypeError $error) {
                throw new MatrixRecordInvalidDataTypeException($rowNumber, $name, $error);
            }
        }

        $this->em->persist($entity);

        EntityTranslationHelper::mergeNewTranslations($entity);

        $this->updatedEntities[] = $entity;
    }

    /**
     * When import fails in the middle, records prepared so far are already persisted or modified.
     * Without reverting them, any later flush in the same request would save incomplete import.
     */
    protected function revertChanges(): void
    {
        $unitOfWork = $this->em->getUnitOfWork();
        $entities = $this->touchedEntities;
        foreach ($this->updatedEntities as $entity) {
            $entities[spl_object_id($entity)] = $entity;
        }

        foreach ($entities as $entity) {
            foreach (EntityTranslationHelper::clearNewTranslations($entity) as $translation) {
                if ($this->em->contains($translation) && !$unitOfWork->isScheduledForInsert($translation)) {
                    $this->em->refresh($translation);
                }
            }

            if ($unitOfWork->isScheduledForInsert($entity)) {
                $this->em->detach($entity);
            } elseif ($this->em->contains($entity)) {
                $this->em->refresh($entity);
            }
        }

        $this->updatedEntities = [];
        $this->touchedEntities = [];
    }

    protected function save(): void
    {
        try {
            $this->em->flush();
            $this->dispatchEvents();
        } catch (UniqueConstraintViolationException) {
            throw new DatabaseNotUniqueDataException();
        } catch (Exception) {
            throw new DatabaseException();
        }
    }

    protected function dispatchEvents(): void
    {
        foreach ($this->updatedEntities as $entity) {
            $identifierValues = $this->em->getUnitOfWork()->getEntityIdentifier($entity);
            $identifier = \reset($identifierValues);

            $this->eventDispatcher->dispatch(
                new RecordImportedSuccessfullyEvent(
                    $this->getEntityClassName(),
                    \is_scalar($identifier) || $identifier instanceof Stringable ? (string) $identifier : '',
                ),
            );
        }
    }

    protected function getEntity(MatrixRecord $record): object
    {
        return $record->getEntity() ?: $this->getNewEntity($record);
    }

    /**
     * Creates new entity object. Uses default constructor without any arguments.
     * To use constructor with arguments, please override this method.
     */
    protected function getNewEntity(MatrixRecord $record): object
    {
        $class = $this->getEntityClassName();

        return new $class();
    }

    public function allowOverrideEntity(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedFileExtensions(): array
    {
        return ['csv', 'xls', 'xlsx', 'ods'];
    }

    /**
     * @return array<string>
     */
    private function parseValueForArrayType(FormFieldDefinition $fieldDefinition, ?string $value): array
    {
        return $value
            ? explode($this->getSeparator($fieldDefinition), $value)
            : [];
    }

    /**
     * @throws MatrixRecordRelatedEntityNotFoundException
     */
    private function findRelatedEntity(FormFieldDefinition $fieldDefinition, string|int|float|bool|null $value, string $columnName, int $rowNumber): ?object
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $options = $fieldDefinition->getOptions();
        /** @var string $class */
        $class = $options['class'];
        /** @var string $lookupField */
        $lookupField = $options['lookup_field'] ?? RelatedEntityFinder::DEFAULT_LOOKUP_FIELD;

        $entity = $this->relatedEntityFinder->find($class, $lookupField, $value);
        if (!$entity) {
            throw new MatrixRecordRelatedEntityNotFoundException($rowNumber, $columnName);
        }

        return $entity;
    }

    /**
     * @phpstan-return non-empty-string
     */
    private function getSeparator(FormFieldDefinition $fieldDefinition): string
    {
        $options = $fieldDefinition->getOptions();
        $separator = $options['separator'] ?? null;

        if (!is_string($separator) || '' === $separator) {
            return ArrayTextType::DEFAULT_SEPARATOR;
        }

        return $separator;
    }
}
