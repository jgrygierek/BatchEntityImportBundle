<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Form\Type;

use Doctrine\Persistence\ObjectManager;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Base type for fields with related entity. Value is a scalar (e.g. id, name, slug)
 * and it is resolved to entity object during import.
 */
abstract class AbstractEntityLookupType extends AbstractType
{
    public function __construct(protected readonly RelatedEntityFinder $relatedEntityFinder)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('class')
            ->setAllowedTypes('class', 'string')
            ->setDefaults([
                'lookup_field' => RelatedEntityFinder::DEFAULT_LOOKUP_FIELD,
                'required' => false,
                'trim' => true,
            ])
            ->setAllowedTypes('lookup_field', 'string')
            ->setNormalizer('lookup_field', function (Options $options, string $lookupField): string {
                /** @var string $class guaranteed by setAllowedTypes() */
                $class = $options['class'];
                // fails early, when form is built, if class or lookup field is invalid
                $this->getManager($class, $lookupField);

                return $lookupField;
            });
    }

    /**
     * @throws InvalidOptionsException
     */
    protected function getManager(string $class, string $lookupField): ObjectManager
    {
        try {
            return $this->relatedEntityFinder->getManager($class, $lookupField);
        } catch (InvalidArgumentException $e) {
            throw new InvalidOptionsException($e->getMessage(), 0, $e);
        }
    }
}
