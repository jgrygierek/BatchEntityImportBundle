<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Validator;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Doctrine\ORM\UnitOfWork;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixFactory;
use JG\BatchEntityImportBundle\Tests\Fixtures\Entity\TestEntity;
use JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Entity\TranslatableEntity;
use JG\BatchEntityImportBundle\Validator\Constraints\DatabaseEntityUnique;
use JG\BatchEntityImportBundle\Validator\Constraints\DatabaseEntityUniqueValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use TypeError;

class DatabaseEntityUniqueValidatorTest extends ConstraintValidatorTestCase
{
    private MockObject $entityManagerMock;

    protected function createValidator(): DatabaseEntityUniqueValidator
    {
        $this->entityManagerMock = $this->createMock(EntityManager::class);

        return new DatabaseEntityUniqueValidator($this->entityManagerMock);
    }

    public function testNoDuplication(): void
    {
        $queryMock = $this->createStub(Query::class);
        $this->entityManagerMock->method('createQuery')->willReturn($queryMock);

        $constraint = new DatabaseEntityUnique(fields: ['field_1'], entityClassName: TestEntity::class);
        $this->validator->validate(
            MatrixFactory::createFromPostData([
                [
                    'field_1' => 1,
                    'field_2' => 100,
                ],
                [
                    'field_1' => 2,
                    'field_2' => 101,
                ],
            ]),
            $constraint,
        );
        $this->assertNoViolation();
    }

    public function testDuplications(): void
    {
        $queryMock = $this->createMock(Query::class);
        $queryMock->method('getArrayResult')->willReturn(['']);
        $this->entityManagerMock->method('createQuery')->willReturn($queryMock);

        $matrix = MatrixFactory::createFromPostData([
            [
                'field_1' => 0,
                'field_2' => 100,
            ],
            [
                'field_1' => 0,
                'field_2' => 100,
            ],
        ]);
        $constraint = new DatabaseEntityUnique(fields: ['field_1'], entityClassName: TestEntity::class);
        $this->validator->validate($matrix, $constraint);
        $violations = $this->context->getViolations();
        $this->assertCount(2, $violations);
        $this->assertSame($matrix->getRecords()[0], $violations->get(0)->getInvalidValue());
    }

    public function testResultsAreNotSharedBetweenValidations(): void
    {
        $queryMock = $this->createMock(Query::class);
        $queryMock->expects($this->exactly(2))->method('getArrayResult')->willReturnOnConsecutiveCalls([], ['']);
        $this->entityManagerMock->method('createQuery')->willReturn($queryMock);

        $matrix = MatrixFactory::createFromPostData([['field_1' => 'abc', 'field_2' => 'abc']]);

        $this->validator->validate($matrix, new DatabaseEntityUnique(entityClassName: TestEntity::class, fields: ['field_1']));
        $this->assertCount(0, $this->context->getViolations());

        // the same value, but other field - result of first validation can't be reused
        $this->validator->validate($matrix, new DatabaseEntityUnique(entityClassName: TestEntity::class, fields: ['field_2']));
        $this->assertCount(1, $this->context->getViolations());
    }

    public function testTheSameValuesAreQueriedOnce(): void
    {
        $queryMock = $this->createMock(Query::class);
        $queryMock->expects($this->once())->method('getArrayResult')->willReturn([]);
        $this->entityManagerMock->method('createQuery')->willReturn($queryMock);

        $matrix = MatrixFactory::createFromPostData([['field_1' => 'abc'], ['field_1' => 'abc']]);

        $this->validator->validate($matrix, new DatabaseEntityUnique(entityClassName: TestEntity::class, fields: ['field_1']));
        $this->assertNoViolation();
    }

    public function testValuesWithInvalidUtf8AreQueriedSeparately(): void
    {
        $queryMock = $this->createMock(Query::class);
        $queryMock->expects($this->exactly(2))->method('getArrayResult')->willReturnOnConsecutiveCalls([], ['']);
        $this->entityManagerMock->method('createQuery')->willReturn($queryMock);

        // e.g. CSV file in Windows-1250 encoding: "\xA3" is "Ł" and "\xAF" is "Ż"
        $matrix = MatrixFactory::createFromPostData([['field_1' => "\xA3ak"], ['field_1' => "\xAFak"]]);

        $this->validator->validate($matrix, new DatabaseEntityUnique(entityClassName: TestEntity::class, fields: ['field_1']));
        $this->assertCount(1, $this->context->getViolations());
    }

    public function testOverriddenEntityIsCheckedSeparatelyFromNewRecord(): void
    {
        $overriddenEntity = new TestEntity();
        $unitOfWorkMock = $this->createMock(UnitOfWork::class);
        $unitOfWorkMock->method('getEntityIdentifier')->with($overriddenEntity)->willReturn(['id' => 5]);
        $this->entityManagerMock->method('getUnitOfWork')->willReturn($unitOfWorkMock);

        // record which overrides entity is not duplicated, but new record with the same value is
        $queryMock = $this->createMock(Query::class);
        $queryMock->expects($this->exactly(2))->method('getArrayResult')->willReturnOnConsecutiveCalls([], ['']);
        $dqls = [];
        $this->entityManagerMock->method('createQuery')->willReturnCallback(static function (string $dql) use (&$dqls, $queryMock): Query {
            $dqls[] = $dql;

            return $queryMock;
        });

        $matrix = MatrixFactory::createFromPostData([['field_1' => 'abc'], ['field_1' => 'abc']]);
        $matrix->getRecords()[0]->setEntity($overriddenEntity);

        $this->validator->validate($matrix, new DatabaseEntityUnique(entityClassName: TestEntity::class, fields: ['field_1']));

        $violations = $this->context->getViolations();
        $this->assertCount(1, $violations);
        $this->assertSame($matrix->getRecords()[1], $violations->get(0)->getInvalidValue());
        $this->assertStringContainsString('c.id != :param_1', $dqls[0]);
        $this->assertStringNotContainsString('c.id', $dqls[1]);
    }

    public function testNamedArguments(): void
    {
        $constraint = new DatabaseEntityUnique(entityClassName: TestEntity::class, fields: ['field_1'], message: 'custom', groups: ['group_1']);

        $this->assertSame(TestEntity::class, $constraint->entityClassName);
        $this->assertSame(['field_1'], $constraint->fields);
        $this->assertSame('custom', $constraint->message);
        $this->assertSame(['group_1'], $constraint->groups);
    }

    public function testInvalidFieldNameException(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Option "fields" contains invalid data. Allowed fields: field_1, field_2'));
        $matrix = MatrixFactory::createFromPostData([
            [
                'field_1' => 0,
                'field_2' => 100,
            ],
        ]);
        $constraint = new DatabaseEntityUnique(fields: ['field_3'], entityClassName: 'test');
        $this->validator->validate($matrix, $constraint);
    }

    public function testMissingEntityClassNameOptionException(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Option "entityClassName" should not be empty.'));
        new DatabaseEntityUnique(fields: ['abcd'], entityClassName: '');
    }

    public function testMissingFieldsOptionException(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('Option "fields" should not be empty.'));
        new DatabaseEntityUnique(fields: [], entityClassName: 'abcd');
    }

    public function testValidatedValueException(): void
    {
        $this->expectException(TypeError::class);
        $this->validator->validate('qwerty', new DatabaseEntityUnique(fields: ['abcd'], entityClassName: TranslatableEntity::class));
    }

    public function testValidatorConstraintException(): void
    {
        $constraint = new Blank();
        $this->expectExceptionObject(new UnexpectedTypeException($constraint, DatabaseEntityUnique::class));
        $this->validator->validate(MatrixFactory::createFromPostData([]), $constraint);
    }
}
