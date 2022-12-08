<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class MatrixRecordUniqueValidator extends AbstractValidator
{
    /**
     * @param Matrix             $value
     * @param MatrixRecordUnique $constraint
     */
    public function validate($value, $constraint): void
    {
        $this->validateArguments($value, $constraint);
        $this->prepareContext();

        $uniqueIds = array_keys(array_unique($this->getHashedMatrixRecordsDataForDuplicationCheck($value->getRecords(), $constraint->fields)));

        foreach ($value->getRecords() as $index => $record) {
            if (!in_array($index, $uniqueIds, true)) {
                $this->addErrorToMatrixRecord($record, $constraint, $index, $constraint->fields);
            }
        }
    }

    protected function validateArguments(Matrix $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MatrixRecordUnique) {
            throw new UnexpectedTypeException($constraint, MatrixRecordUnique::class);
        }

        parent::validateArguments($value, $constraint);
    }

    private function getHashedMatrixRecordsDataForDuplicationCheck(array $records, array $fieldsUsedInDuplicationCheck): array
    {
        return array_map(
            fn (MatrixRecord $record): string => $this->getHash($this->getMatrixRecordDataForDuplicationCheck($record, $fieldsUsedInDuplicationCheck)),
            $records
        );
    }

    private function getMatrixRecordDataForDuplicationCheck(MatrixRecord $matrixRecord, array $fieldsUsedInDuplicationCheck): array
    {
        return array_intersect_key($matrixRecord->getData(), array_flip($fieldsUsedInDuplicationCheck));
    }
}
