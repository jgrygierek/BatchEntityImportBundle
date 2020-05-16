<?php

namespace JG\BatchEntityImportBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use UnexpectedValueException;

class TemplateExtension extends AbstractExtension
{
    private array  $availableTemplates;

    public function __construct(array $availableTemplates = [])
    {
        $this->availableTemplates = $availableTemplates;
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
