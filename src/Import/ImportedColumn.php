<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Import;

use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;

/**
 * Column of matrix which is imported to entity, prepared once for all records.
 *
 * @internal used by EntityImporter
 */
final readonly class ImportedColumn
{
    public function __construct(
        public string $name,
        public string $propertyName,
        public string $setterName,
        public ?string $locale,
        public ?FormFieldDefinition $fieldDefinition,
        /**
         * @var non-empty-string|null separator of array field (ArrayTextType or its subclass), null for other columns
         */
        public ?string $arraySeparator = null,
    ) {
    }
}
