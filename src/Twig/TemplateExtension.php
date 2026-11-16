<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Twig;

use Twig\Attribute\AsTwigFunction;
use UnexpectedValueException;

final readonly class TemplateExtension
{
    /**
     * @param array<string, string> $availableTemplates
     */
    public function __construct(private array $availableTemplates = [])
    {
    }

    #[AsTwigFunction(name: 'batch_entity_import_template')]
    public function getTemplate(string $name): string
    {
        if (array_key_exists($name, $this->availableTemplates)) {
            return $this->availableTemplates[$name];
        }

        throw new UnexpectedValueException(sprintf('Template %s not found.', $name));
    }
}
