<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AbstractValidationTestCase extends TestCase
{
    protected ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
    }

    protected function getErrors(object $entity): array
    {
        $errors = $this->validator->validate($entity);

        return iterator_to_array($errors);
    }
}
