<?php

namespace JG\BatchEntityImportBundle\Tests\Form\Type;

use JG\BatchEntityImportBundle\Form\Type\FileImportType;
use JG\BatchEntityImportBundle\Model\FileImport;
use Symfony\Component\Form\Test\TypeTestCase;

class FileImportTypeTest extends TypeTestCase
{
    public function testForm(): void
    {
        $formData = [];
        $fileImport = new FileImport();
        $form = $this->factory->create(FileImportType::class, $fileImport);

        $form->submit($formData);
        $this->assertTrue($form->isSynchronized());
        $this->assertSame($fileImport, $form->getData());
    }
}
