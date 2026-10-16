<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use Symfony\Component\Validator\Constraint;

class RelatedEntityExists extends Constraint
{
    public string $message = 'validation.entity.related.not_found';

    /**
     * @param class-string|string $class
     * @param array<string>|null $groups
     */
    public function __construct(
        public string $class,
        public string $lookupField = RelatedEntityFinder::DEFAULT_LOOKUP_FIELD,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
