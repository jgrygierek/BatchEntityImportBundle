<?php

namespace JG\BatchEntityImportBundle\Tests\Model;

use Generator;
use JG\BatchEntityImportBundle\Model\FileImport;
use JG\BatchEntityImportBundle\Tests\AbstractValidationTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileImportTest extends AbstractValidationTestCase
{
    private FileImport $fileImport;
    private ?string    $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileImport = new FileImport();
        $this->path       = tempnam(sys_get_temp_dir(), 'upl');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->path)) {
            unlink($this->path);
        }

        $this->path = null;
    }

    /**
     * @dataProvider validExtensionsProvider
     *
     * @param string $extension
     */
    public function testValidFile(string $extension): void
    {
        $this->setUploadedFile($extension);
        $this->assertEmpty($this->getErrors($this->fileImport));
    }

    public function validExtensionsProvider(): Generator
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

    public function testEmptyFileError(): void
    {
        $this->setUploadedFile('csv', false);
        $this->assertNotEmpty($this->getErrors($this->fileImport));
    }

    /**
     * @dataProvider invalidExtensionsProvider
     *
     * @param string $extension
     */
    public function testInvalidExtensionError(string $extension): void
    {
        $this->setUploadedFile($extension);
        $this->assertNotEmpty($this->getErrors($this->fileImport));
    }

    public function invalidExtensionsProvider(): Generator
    {
        yield ['jpg'];
        yield ['pdf'];
        yield ['txt'];
        yield [''];
    }

    public function testEmptyContentError(): void
    {
        $this->fileImport->setFile($this->createUploadedFile('csv', false));

        $this->assertNotEmpty($this->getErrors($this->fileImport));
    }

    private function setUploadedFile(string $fileExtension, bool $withContent = true): void
    {
        $this->fileImport->setFile($this->createUploadedFile($fileExtension, $withContent));
    }

    private function createUploadedFile(string $fileExtension, bool $withContent = true): UploadedFile
    {
        $name = 'test_file' . ($fileExtension ? '.' . $fileExtension : '');

        if ($withContent) {
            file_put_contents($this->path, 'test_content');
        }

        return new UploadedFile($this->path, $name, null, null, true);
    }
}
