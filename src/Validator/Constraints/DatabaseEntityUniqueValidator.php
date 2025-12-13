<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DatabaseEntityUniqueValidator extends AbstractValidator
{
    private array $duplicatedRecords = [];
    private array $correctRecords = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param Matrix $value
     * @param DatabaseEntityUnique $constraint
     */
    public function validate($value, $constraint): void
    {
        $this->duplicatedRecords = [];
        $this->validateArguments($value, $constraint);
        $this->prepareContext();

        foreach ($value->getRecords() as $index => $record) {
            $matrixDataToCompare = $this->getMatrixRecordDataToCompare($record, $constraint->fields);
            if ($this->isCorrectRecord($matrixDataToCompare)) {
                continue;
            }

            if ($this->isDuplicate($matrixDataToCompare)) {
                $this->addErrorToMatrixRecord($record, $constraint, $index, $constraint->fields);

                continue;
            }

            $criteria = $this->buildCriteria($record, $matrixDataToCompare);
            if ($this->isRecordDuplicatedInDatabase($this->entityManager, $constraint->entityClassName, $criteria)) {
                $this->addErrorToMatrixRecord($record, $constraint, $index, $constraint->fields);
                $this->addDuplicate($matrixDataToCompare);
            } else {
                $this->markAsCorrectRecord($matrixDataToCompare);
            }
        }
    }

    protected function validateArguments(Matrix $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DatabaseEntityUnique) {
            throw new UnexpectedTypeException($constraint, DatabaseEntityUnique::class);
        }

        parent::validateArguments($value, $constraint);
    }

    /**
     * @param array<string> $fields
     *
     * @return array<string, scalar|null>
     */
    private function getMatrixRecordDataToCompare(MatrixRecord $matrixRecord, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            /** @var scalar|null $value */
            $value = $matrixRecord->$field;
            $data[$field] = $value;
        }

        return $data;
    }

    /**
     * @param array<string, scalar|null> $matrixDataToCompare
     */
    private function isDuplicate(array $matrixDataToCompare): bool
    {
        return array_key_exists($this->getHash($matrixDataToCompare), $this->duplicatedRecords);
    }

    /**
     * @param array<string, scalar|null> $matrixDataToCompare
     */
    private function isCorrectRecord(array $matrixDataToCompare): bool
    {
        return array_key_exists($this->getHash($matrixDataToCompare), $this->correctRecords);
    }

    /**
     * @param array<string, scalar|null> $matrixDataToCompare
     */
    private function addDuplicate(array $matrixDataToCompare): void
    {
        $this->duplicatedRecords[$this->getHash($matrixDataToCompare)] = true;
    }

    /**
     * @param array<string, scalar|null> $matrixDataToCompare
     */
    private function markAsCorrectRecord(array $matrixDataToCompare): void
    {
        $this->correctRecords[$this->getHash($matrixDataToCompare)] = true;
    }

    /**
     * @param array<string, scalar|null> $matrixDataToCompare
     *
     * @return array<string, array<int, array{0: string, 1: scalar|null}>>
     */
    private function buildCriteria(MatrixRecord $matrixRecord, array $matrixDataToCompare): array
    {
        $criteria = [];
        foreach ($matrixDataToCompare as $fieldName => $value) {
            $criteria[ColumnNameHelper::toCamelCase($fieldName)][] = ['=', $value];
        }

        $entityToOverride = $matrixRecord->getEntity();

        return null === $entityToOverride
            ? $criteria
            : $this->addCriteriaToOmitEntity($criteria, $entityToOverride);
    }

    /**
     * @param array<string, array<int, array{0: string, 1: scalar|null}>> $criteria
     *
     * @return array<string, array<int, array{0: string, 1: scalar|null}>>
     */
    private function addCriteriaToOmitEntity(array $criteria, object $entityToOverride): array
    {
        /** @var array<string, scalar> $primaryKeyData */
        $primaryKeyData = $this->entityManager->getUnitOfWork()->getEntityIdentifier($entityToOverride);

        foreach ($primaryKeyData as $primaryKeyName => $primaryValue) {
            $criteria[$primaryKeyName][] = ['!=', $primaryValue];
        }

        return $criteria;
    }

    /**
     * @param array<string, array<int, array{0: string, 1: scalar|null}>> $criteria
     */
    private function isRecordDuplicatedInDatabase(EntityManagerInterface $em, string $class, array $criteria): bool
    {
        $query = $em->createQuery($this->buildDQL($class, $criteria));
        $this->passParametersToQuery($query, $criteria);

        return !empty($query->getArrayResult());
    }

    /**
     * @param array<string, array<int, array{0: string, 1: scalar|null}>> $criteria
     */
    private function buildDQL(string $class, array $criteria): string
    {
        $sql = /* @lang DQL */
            sprintf('SELECT c FROM %s c', $class);

        $nmb = 0;
        foreach ($criteria as $fieldName => $data) {
            foreach ($data as [$operator, $value]) {
                $sql .= $nmb > 0 ? ' AND' : ' WHERE';
                $sql .= sprintf(' c.%s %s :param_', $fieldName, $operator) . $nmb++;
            }
        }

        return $sql;
    }

    /**
     * @param array<string, array<int, array{0: string, 1: scalar|null}>> $criteria
     */
    private function passParametersToQuery(AbstractQuery $query, array $criteria): void
    {
        $nmb = 0;
        foreach ($criteria as $data) {
            foreach ($data as [$operator, $value]) {
                $query->setParameter('param_' . $nmb++, $value);
            }
        }
    }
}
