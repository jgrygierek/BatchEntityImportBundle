<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use UnexpectedValueException;

class TemplateExtension extends AbstractExtension
{
    public function __construct(private readonly array $availableTemplates = [])
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('batch_entity_import_template', [$this, 'getTemplate']),
        ];
    }

    public function getTemplate(string $name): string
    {
        if (array_key_exists($name, $this->availableTemplates)) {
            return $this->availableTemplates[$name];
        }

        throw new UnexpectedValueException("Template $name not found.");
    }
}
