<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class RelatedEntityExistsValidator extends ConstraintValidator
{
    public function __construct(private readonly RelatedEntityFinder $relatedEntityFinder)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RelatedEntityExists) {
            throw new UnexpectedTypeException($constraint, RelatedEntityExists::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value)) {
            throw new UnexpectedValueException($value, 'scalar');
        }

        if (!$this->relatedEntityFinder->find($constraint->class, $constraint->lookupField, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('%value%', (string) $value)
                ->addViolation();
        }
    }
}
