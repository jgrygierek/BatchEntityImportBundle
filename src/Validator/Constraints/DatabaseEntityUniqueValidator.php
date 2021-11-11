<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DatabaseEntityUniqueValidator extends ConstraintValidator
{
    private EntityManager $entityManager;
    private static array $duplicates = [];

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param int|string                      $value
     * @param Constraint|DatabaseEntityUnique $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof DatabaseEntityUnique) {
            throw new UnexpectedTypeException($constraint, DatabaseEntityUnique::class);
        }

        /** @var Form $object */
        $object = $this->context->getObject();
        /** @var MatrixRecord $matrixRecord */
        $matrixRecord = $object->getParent()->getData();

        $matrixDataToCompare = $this->getMatrixRecordDataToCompare($matrixRecord, $constraint->fields);
        if ($this->isDuplicate($matrixDataToCompare)) {
            $this->addViolation($constraint);

            return;
        }

        $criteria = $this->buildCriteria($matrixRecord, $matrixDataToCompare);
        $repository = $this->entityManager->getRepository($constraint->entityClassName);

        if (!$repository->matching($criteria)->isEmpty()) {
            $this->addViolation($constraint);
            $this->addDuplicate($matrixDataToCompare);
        }
    }

    private function addViolation(DatabaseEntityUnique $constraint): void
    {
        $this->context->buildViolation($constraint->message, ['%fields%' => implode(',', $constraint->fields)])->addViolation();
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
        return in_array($this->getHash($matrixDataToCompare), self::$duplicates, true);
    }

    private function addDuplicate(array $matrixDataToCompare): void
    {
        self::$duplicates[] = $this->getHash($matrixDataToCompare);
    }

    private function getHash(array $matrixDataToCompare): string
    {
        return md5(implode('--', $matrixDataToCompare));
    }

    private function buildCriteria(MatrixRecord $matrixRecord, array $matrixDataToCompare): Criteria
    {
        $criteria = new Criteria();

        $entityToOverride = $matrixRecord->getEntity();
        if ($entityToOverride) {
            $this->addCriteriaToOmitEntity($criteria, $entityToOverride);
        }

        foreach ($matrixDataToCompare as $fieldName => $value) {
            $criteria->andWhere(Criteria::expr()->eq($fieldName, $value));
        }

        return $criteria;
    }

    private function addCriteriaToOmitEntity(Criteria $criteria, object $entityToOverride): void
    {
        $primaryKeyData = $this->getPrimaryKeyData($entityToOverride);
        foreach ($primaryKeyData as $primaryKeyName => $primaryValue) {
            $criteria->andWhere(Criteria::expr()->neq($primaryKeyName, $primaryValue));
        }
    }

    private function getPrimaryKeyData(object $entity): array
    {
        return $this->entityManager->getUnitOfWork()->getEntityIdentifier($entity);
    }
}
