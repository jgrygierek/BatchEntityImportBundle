<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use JG\BatchEntityImportBundle\Utils\EntityManagerHelper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class DatabaseEntityUniqueValidator extends AbstractValidator
{
    public function __construct(private readonly ManagerRegistry $managerRegistry)
    {
    }

    /**
     * @param Matrix $value
     * @param DatabaseEntityUnique $constraint
     */
    public function validate($value, $constraint): void
    {
        $this->validateArguments($value, $constraint);
        $this->prepareContext();

        // Results are cached only for single validation. Validator is a shared service,
        // so results kept longer would be reused for other constraints, fields and requests.
        $duplicationResults = [];
        $entityManager = EntityManagerHelper::getEntityManager($this->managerRegistry, $constraint->entityClassName);

        foreach ($value->getRecords() as $index => $record) {
            $matrixDataToCompare = $this->getMatrixRecordDataToCompare($record, $constraint->fields);
            $criteria = $this->buildCriteria($entityManager, $record, $matrixDataToCompare);
            // criteria contain identifier of overridden entity, so the same values
            // are checked separately for new record and for record which overrides entity
            $cacheKey = $this->getCriteriaHash($criteria);

            $duplicationResults[$cacheKey] ??= $this->isRecordDuplicatedInDatabase($entityManager, $constraint->entityClassName, $criteria);
            if ($duplicationResults[$cacheKey]) {
                $this->addErrorToMatrixRecord($record, $constraint, $index, $constraint->fields);
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
     * @param array<string, array<int, array{0: string, 1: scalar|null}>> $criteria
     */
    private function getCriteriaHash(array $criteria): string
    {
        $data = [];
        foreach ($criteria as $fieldName => $conditions) {
            foreach ($conditions as [$operator, $value]) {
                $data[] = [$fieldName, $operator, (string) $value];
            }
        }

        return md5(serialize($data));
    }

    /**
     * @param array<string, scalar|null> $matrixDataToCompare
     *
     * @return array<string, array<int, array{0: string, 1: scalar|null}>>
     */
    private function buildCriteria(EntityManagerInterface $entityManager, MatrixRecord $matrixRecord, array $matrixDataToCompare): array
    {
        $criteria = [];
        foreach ($matrixDataToCompare as $fieldName => $value) {
            $criteria[ColumnNameHelper::toCamelCase($fieldName)][] = ['=', $value];
        }

        $entityToOverride = $matrixRecord->getEntity();

        return null === $entityToOverride
            ? $criteria
            : $this->addCriteriaToOmitEntity($entityManager, $criteria, $entityToOverride);
    }

    /**
     * @param array<string, array<int, array{0: string, 1: scalar|null}>> $criteria
     *
     * @return array<string, array<int, array{0: string, 1: scalar|null}>>
     */
    private function addCriteriaToOmitEntity(EntityManagerInterface $entityManager, array $criteria, object $entityToOverride): array
    {
        /** @var array<string, scalar> $primaryKeyData */
        $primaryKeyData = $entityManager->getUnitOfWork()->getEntityIdentifier($entityToOverride);

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
