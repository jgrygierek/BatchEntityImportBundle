<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Validator;

use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestRelatedEntity;
use JG\BatchEntityImportBundle\Validator\Constraints\RelatedEntityExists;
use JG\BatchEntityImportBundle\Validator\Constraints\RelatedEntityExistsValidator;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<RelatedEntityExistsValidator>
 */
class RelatedEntityExistsValidatorTest extends ConstraintValidatorTestCase
{
    private MockObject $finderMock;

    protected function createValidator(): RelatedEntityExistsValidator
    {
        $this->finderMock = $this->createMock(RelatedEntityFinder::class);

        return new RelatedEntityExistsValidator($this->finderMock);
    }

    public function testEntityExists(): void
    {
        $this->finderMock
            ->expects(self::once())
            ->method('find')
            ->with(TestRelatedEntity::class, 'name', 'first')
            ->willReturn(new TestRelatedEntity('first'));

        $this->validator->validate('first', $this->createConstraint());

        $this->assertNoViolation();
    }

    public function testEntityNotFound(): void
    {
        $this->finderMock->method('find')->willReturn(null);

        $constraint = $this->createConstraint();
        $this->validator->validate('unknown', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('%value%', 'unknown')
            ->assertRaised();
    }

    public function testEmptyValueIsSkipped(): void
    {
        $this->finderMock->expects(self::never())->method('find');

        $this->validator->validate(null, $this->createConstraint());
        $this->validator->validate('', $this->createConstraint());

        $this->assertNoViolation();
    }

    public function testInvalidConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('first', new Blank());
    }

    public function testInvalidValue(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new stdClass(), $this->createConstraint());
    }

    private function createConstraint(): RelatedEntityExists
    {
        return new RelatedEntityExists(class: TestRelatedEntity::class, lookupField: 'name');
    }
}
