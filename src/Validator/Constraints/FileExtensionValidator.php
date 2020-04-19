<?php

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class FileExtensionValidator extends ConstraintValidator
{
    /**
     * @param UploadedFile|null        $value
     * @param Constraint|FileExtension $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        $extensions = $constraint->extensions;

        if ($value && !$this->hasValidExtension($value, $extensions)) {
            $this->context->buildViolation($constraint->message, ['%extensions' => implode(', ', $extensions)])->addViolation();
        }
    }

    private function hasValidExtension(UploadedFile $file, array $extensions): bool
    {
        return in_array($file->getClientOriginalExtension(), $extensions, true);
    }
}
