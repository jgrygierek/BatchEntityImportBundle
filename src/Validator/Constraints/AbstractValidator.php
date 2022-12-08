<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use InvalidArgumentException;
use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use Symfony\Component\Validator\ConstraintValidator;

abstract class AbstractValidator extends ConstraintValidator
{
    protected function validateArguments(Matrix $value, MatrixRecordUnique|DatabaseEntityUnique $constraint): void
    {
        $header = $value->getHeader();
        if (!empty(array_diff($constraint->fields, $header))) {
            throw new InvalidArgumentException('Option "fields" contains invalid data. Allowed fields: ' . implode(', ', $header));
        }
    }

    protected function prepareContext(): void
    {
        $this->context->setNode($this->context->getValue(), $this->context->getObject(), $this->context->getMetadata(), '');
    }

    protected function addErrorToMatrixRecord(MatrixRecord $record, MatrixRecordUnique|DatabaseEntityUnique $constraint, int $index, array $fields): void
    {
        $this->context
            ->buildViolation($constraint->message, ['%fields%' => implode(', ', $fields)])
            ->atPath("children[records][$index][{$constraint->fields[0]}]")
            ->setInvalidValue($record)
            ->addViolation();
    }

    protected function getHash(array $data): string
    {
        return md5(implode('--', $data));
    }
}
