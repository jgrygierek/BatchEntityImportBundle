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

    /**
     * @param array<string> $fields
     */
    protected function addErrorToMatrixRecord(MatrixRecord $record, MatrixRecordUnique|DatabaseEntityUnique $constraint, int $index, array $fields): void
    {
        $this->context
            ->buildViolation($constraint->message, ['%fields%' => implode(', ', $fields)])
            ->atPath(sprintf('children[records][%d][%s]', $index, $constraint->fields[0]))
            ->setInvalidValue($record)
            ->addViolation();
    }

    /**
     * @param array<scalar|null> $data
     */
    protected function getHash(array $data): string
    {
        // Values are serialized separately, so e.g. ['a--b', 'c'] and ['a', 'b--c'] are not treated as the same data.
        // Serialization is binary safe, so values with invalid UTF-8 (e.g. file in other encoding) are still distinguished.
        return md5(serialize(array_map(strval(...), array_values($data))));
    }
}
