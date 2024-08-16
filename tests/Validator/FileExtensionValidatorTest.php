<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Validator;

use Generator;
use JG\BatchEntityImportBundle\Validator\Constraints\FileExtension;
use JG\BatchEntityImportBundle\Validator\Constraints\FileExtensionValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class FileExtensionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): FileExtensionValidator
    {
        return new FileExtensionValidator();
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSuccess(string $fileExtension): void
    {
        $extensions = ['csv', 'xls', 'xlsx', 'ods'];
        $constraint = new FileExtension(['extensions' => $extensions]);
        $this->validator->validate($this->getUploadedFileMock($fileExtension), $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFailure(string $fileExtension): void
    {
        $extensions = ['png', 'jpg', 'pdf'];
        $constraint = new FileExtension(['extensions' => $extensions]);
        $this->validator->validate($this->getUploadedFileMock($fileExtension), $constraint);

        $this
            ->buildViolation('validation.file.extension')
            ->setParameter('%extensions', implode(', ', $extensions))
            ->assertRaised();
    }

    public static function dataProvider(): Generator
    {
        yield ['csv'];
        yield ['xls'];
        yield ['xlsx'];
        yield ['ods'];
        yield ['CSV'];
        yield ['XLS'];
        yield ['XLSX'];
        yield ['ODS'];
    }

    public function testValidatorConstraintException(): void
    {
        $constraint = new Blank();
        $this->expectExceptionObject(new UnexpectedTypeException($constraint, FileExtension::class));
        $this->validator->validate($this->getUploadedFileMock('csv'), new Blank());
    }

    private function getUploadedFileMock(string $fileExtension): UploadedFile
    {
        $mock = $this->createMock(UploadedFile::class);
        $mock
            ->method('getClientOriginalExtension')
            ->willReturn($fileExtension);

        return $mock;
    }
}
