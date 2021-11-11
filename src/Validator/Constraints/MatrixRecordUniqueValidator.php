<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

class MatrixRecordUniqueValidator extends ConstraintValidator
{
    private TranslatorInterface $translator;
    private static array $duplicates = [];

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param Matrix                        $value
     * @param Constraint|MatrixRecordUnique $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        $errorMessage = $this->translator->trans($constraint->message, ['%fields%' => implode(', ', $constraint->fields)], 'validators');
        $uniqueIds = array_keys(array_unique($this->getHashedMatrixRecordsDataForDuplicationCheck($value->getRecords(), $constraint->fields)));

        foreach ($value->getRecords() as $index => $record) {
            if (in_array($index, $uniqueIds, true)) {
                continue;
            }

            $this->context
                ->getRoot()
                ->get('records')
                ->get((string) $index)
                ->get($constraint->fields[0])
                ->addError(new FormError($errorMessage));
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
