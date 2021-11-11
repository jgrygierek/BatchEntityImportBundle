<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class FileExtensionValidator extends ConstraintValidator
{
    /**
     * @param UploadedFile|null        $value
     * @param Constraint|FileExtension $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof FileExtension) {
            throw new UnexpectedTypeException($constraint, FileExtension::class);
        }

        $extensions = array_map('strtolower', $constraint->extensions);

        if ($value && !$this->hasValidExtension($value, $extensions)) {
            $this->context->buildViolation($constraint->message, ['%extensions' => implode(', ', $extensions)])->addViolation();
        }
    }

    private function hasValidExtension(UploadedFile $file, array $extensions): bool
    {
        return in_array(strtolower($file->getClientOriginalExtension()), $extensions, true);
    }
}
