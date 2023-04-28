<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DatabaseEntityUniqueValidator extends AbstractValidator
{
    private EntityManager $entityManager;
    private array $duplicatedRecords = [];
    private array $correctRecords = [];

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Matrix               $value
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

    private function getMatrixRecordDataToCompare(MatrixRecord $matrixRecord, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $matrixRecord->$field;
        }

        return $data;
    }

    private function isDuplicate(array $matrixDataToCompare): bool
    {
        return array_key_exists($this->getHash($matrixDataToCompare), $this->duplicatedRecords);
    }

    private function isCorrectRecord(array $matrixDataToCompare): bool
    {
        return array_key_exists($this->getHash($matrixDataToCompare), $this->correctRecords);
    }

    private function addDuplicate(array $matrixDataToCompare): void
    {
        $this->duplicatedRecords[$this->getHash($matrixDataToCompare)] = true;
    }

    private function markAsCorrectRecord(array $matrixDataToCompare): void
    {
        $this->correctRecords[$this->getHash($matrixDataToCompare)] = true;
    }

    private function buildCriteria(MatrixRecord $matrixRecord, array $matrixDataToCompare): array
    {
        $criteria = [];
        foreach ($matrixDataToCompare as $fieldName => $value) {
            $criteria[ColumnNameHelper::underscoreToCamelCase($fieldName)][] = ['=', $value];
        }

        $entityToOverride = $matrixRecord->getEntity();
        if ($entityToOverride) {
            $this->addCriteriaToOmitEntity($criteria, $entityToOverride);
        }

        return $criteria;
    }

    private function addCriteriaToOmitEntity(array &$criteria, object $entityToOverride): void
    {
        $primaryKeyData = $this->entityManager->getUnitOfWork()->getEntityIdentifier($entityToOverride);

        foreach ($primaryKeyData as $primaryKeyName => $primaryValue) {
            $criteria[$primaryKeyName][] = ['!=', $primaryValue];
        }
    }

    private function isRecordDuplicatedInDatabase(EntityManager $em, string $class, array $criteria): bool
    {
        $query = $em->createQuery($this->buildDQL($class, $criteria));
        $this->passParametersToQuery($query, $criteria);

        return !empty($query->getArrayResult());
    }

    private function buildDQL(string $class, array $criteria): string
    {
        $sql = /* @lang DQL */
            "SELECT c FROM $class c";

        $nmb = 0;
        foreach ($criteria as $fieldName => $data) {
            foreach ($data as [$operator, $value]) {
                $sql .= $nmb > 0 ? ' AND' : ' WHERE';
                $sql .= " c.$fieldName $operator :param_" . $nmb++;
            }
        }

        return $sql;
    }

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
