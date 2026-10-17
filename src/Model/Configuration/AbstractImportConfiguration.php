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
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Knp\DoctrineBehaviors\Contract\Entity\TranslationInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use TypeError;

abstract class AbstractImportConfiguration implements ImportConfigurationInterface
{
    /** @var object[] */
    protected array $updatedEntities = [];
    private readonly RelatedEntityFinder $relatedEntityFinder;

    /**
     * @param RelatedEntityFinder|null $relatedEntityFinder optional for backward compatibility, if not passed, finder based on $em is used
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $eventDispatcher,
        ?RelatedEntityFinder $relatedEntityFinder = null,
    ) {
        $this->relatedEntityFinder = $relatedEntityFinder ?? new RelatedEntityFinder($em);
    }

    public function getFieldsDefinitions(): array
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

        $headerInfo = $matrix->getHeaderInfo($this->getEntityClassName());
        foreach ($matrix->getRecords() as $record) {
            $this->prepareRecord($record, $headerInfo);
        }

        $this->save();
    }

    /**
     * @param array<string, bool> $headerInfo
     */
    protected function prepareRecord(MatrixRecord $record, array $headerInfo): void
    {
        $entity = $this->getEntity($record);
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

            if (isset($fieldDefinitions[$name])) {
                $fieldDefinition = $fieldDefinitions[$name];
                if (ArrayTextType::class === $fieldDefinition->getClass()) {
                    $value = $this->parseValueForArrayType($fieldDefinition, $value);
                } elseif (is_a($fieldDefinition->getClass(), AbstractEntityLookupType::class, true)) {
                    $value = $this->findRelatedEntity($fieldDefinition, $value);
                }
            }

            try {
                if (
                    \interface_exists(TranslationInterface::class)
                    && \interface_exists(TranslatableInterface::class)
                    && $entity instanceof TranslatableInterface && $locale
                ) {
                    /** @var TranslationInterface $translatedEntity */
                    $translatedEntity = $entity->translate($locale, false);
                    if (method_exists($translatedEntity, $setterName)) {
                        $translatedEntity->$setterName($value);
                    } else {
                        $translatedEntity->$propertyName = $value;
                    }
                } elseif (!$locale) {
                    if (method_exists($entity, $setterName)) {
                        $entity->$setterName($value);
                    } else {
                        $entity->$propertyName = $value;
                    }
                }
            } catch (TypeError) {
                throw new MatrixRecordInvalidDataTypeException();
            }
        }

        $this->em->persist($entity);

        if (\interface_exists(TranslatableInterface::class) && $entity instanceof TranslatableInterface) {
            $entity->mergeNewTranslations();
        }

        $this->updatedEntities[] = $entity;
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

            $this->eventDispatcher->dispatch(
                new RecordImportedSuccessfullyEvent($this->getEntityClassName(), (string) \reset($identifierValues)),
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
    private function findRelatedEntity(FormFieldDefinition $fieldDefinition, string|int|float|bool|null $value): ?object
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
            throw new MatrixRecordRelatedEntityNotFoundException();
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
