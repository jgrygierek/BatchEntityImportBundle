<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\KnpLabs\Model\Matrix;

use JG\BatchEntityImportBundle\Model\Matrix\Matrix;
use JG\BatchEntityImportBundle\Tests\AbstractValidationTestCase;
use JG\BatchEntityImportBundle\Tests\KnpLabs\Fixtures\Entity\TranslatableEntity;

class MatrixTest extends AbstractValidationTestCase
{
    public function testHeaderInfoForTranslatableEntity(): void
    {
        $this->markKnpLabsTestAsSkipped();

        $expected = [
            'unknown_column_name' => false,
            'test_public_property' => true,
            'test_private_property' => true,
            'test_private_property_no_setter' => false,
            'test_private_property:en' => false,
            'test_translation_property' => false,
            'test-translation-property:en' => true,
        ];

        $matrix = new Matrix(array_keys($expected));

        self::assertSame($expected, $matrix->getHeaderInfo(TranslatableEntity::class));
    }
}
