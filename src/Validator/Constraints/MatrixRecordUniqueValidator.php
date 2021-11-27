<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use InvalidArgumentException;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class MatrixRecordUniqueValidator extends ConstraintValidator
{
    /**
     * @param Matrix                        $value
     * @param Constraint|MatrixRecordUnique $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        $this->validateArguments($value, $constraint);
        $this->context->setNode($this->context->getValue(), $this->context->getObject(), $this->context->getMetadata(), '');
        $uniqueIds = array_keys(array_unique($this->getHashedMatrixRecordsDataForDuplicationCheck($value->getRecords(), $constraint->fields)));

        foreach ($value->getRecords() as $index => $record) {
            if (in_array($index, $uniqueIds, true)) {
                continue;
            }

            $this->context
                ->buildViolation($constraint->message, ['%fields%' => implode(', ', $constraint->fields)])
                ->atPath("children[records][$index][{$constraint->fields[0]}]")
                ->setInvalidValue($record)
                ->addViolation();
        }
    }

    private function validateArguments($value, Constraint $constraint): void
    {
        if (!$constraint instanceof MatrixRecordUnique) {
            throw new UnexpectedTypeException($constraint, MatrixRecordUnique::class);
        }

        if (!$value instanceof Matrix) {
            throw new UnexpectedTypeException($value, Matrix::class);
        }

        if (!empty(array_diff($constraint->fields, $value->getHeader()))) {
            throw new InvalidArgumentException('Option "fields" contains invalid data.');
        }
    }

    private function getHashedMatrixRecordsDataForDuplicationCheck(array $records, array $fieldsUsedInDuplicationCheck): array
    {
        return array_map(
            fn (MatrixRecord $record) => $this->getHash($this->getMatrixRecordDataForDuplicationCheck($record, $fieldsUsedInDuplicationCheck)),
            $records
        );
    }

    private function getMatrixRecordDataForDuplicationCheck(MatrixRecord $matrixRecord, array $fieldsUsedInDuplicationCheck): array
    {
        return array_intersect_key($matrixRecord->getData(), array_flip($fieldsUsedInDuplicationCheck));
    }

    private function getHash(array $data): string
    {
        return md5(implode('--', $data));
    }
}
