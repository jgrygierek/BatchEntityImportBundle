<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractValidationTestCase extends TestCase
{
    use SkippedTestsTrait;

    protected ValidatorInterface $validator;

    protected function setUp(): void
    {
        $builder = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->addDefaultDoctrineAnnotationReader();

        $this->validator = $builder->getValidator();
    }

    protected function getErrors(object $entity): array
    {
        $errors = $this->validator->validate($entity);

        return iterator_to_array($errors);
    }
}
