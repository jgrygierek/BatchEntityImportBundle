<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Form\Type;

use JG\BatchEntityImportBundle\Validator\Constraints\RelatedEntityExists;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;

/**
 * Renders related entity as a text field with value validated against database.
 * Recommended for big tables, when rendering all entities as a select would be too heavy.
 */
class EntityLookupTextType extends AbstractEntityLookupType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setNormalizer(
            'constraints',
            static function (Options $options, mixed $constraints): array {
                /** @var string $class guaranteed by setAllowedTypes() */
                $class = $options['class'];
                /** @var string $lookupField */
                $lookupField = $options['lookup_field'];

                /** @var Constraint[] $constraints */
                $constraints = \is_array($constraints) ? $constraints : [$constraints];
                $constraints[] = new RelatedEntityExists(class: $class, lookupField: $lookupField);

                return $constraints;
            },
        );
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'entity_lookup_text';
    }
}
