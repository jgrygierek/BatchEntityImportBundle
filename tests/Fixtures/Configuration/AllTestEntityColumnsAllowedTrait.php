<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Configuration;

/**
 * All columns of TestEntity are allowed, also these which can't be imported for other reasons
 * (e.g. property without setter), so tests check these reasons, not the list of allowed columns.
 */
trait AllTestEntityColumnsAllowedTrait
{
    public function getAllowedColumns(): array
    {
        return [
            'test_public_property',
            'test_private_property',
            'test_private_property2',
            'test_private_property_no_setter',
            'test_array_field',
            'test_related_entity',
            'test_second_related_entity',
        ];
    }
}
