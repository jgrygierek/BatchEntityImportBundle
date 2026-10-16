<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Stringable;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Renders related entity as a select with all entities.
 * For big tables use EntityLookupTextType instead.
 */
class EntityLookupType extends AbstractEntityLookupType implements ResetInterface
{
    /** @var array<string, array<string, string|int>> */
    private array $choicesCache = [];

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults([
                'label_field' => null,
                'placeholder' => '---',
                'translation_domain' => false,
                'choices' => function (Options $options): array {
                    /** @var class-string $class validated by RelatedEntityFinder in lookup_field normalizer */
                    $class = $options['class'];
                    /** @var string $lookupField */
                    $lookupField = $options['lookup_field'];
                    /** @var string|null $labelField */
                    $labelField = $options['label_field'];

                    return $this->getChoices($class, $lookupField, $labelField);
                },
            ])
            ->setAllowedTypes('label_field', ['null', 'string']);
    }

    /**
     * Called between requests in long-running processes, so new entities are visible.
     */
    public function reset(): void
    {
        $this->choicesCache = [];
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'entity_lookup';
    }

    /**
     * @param class-string $class
     *
     * @return array<string, string|int>
     */
    private function getChoices(string $class, string $lookupField, ?string $labelField): array
    {
        $cacheKey = $class . '::' . $lookupField . '::' . $labelField;
        if (isset($this->choicesCache[$cacheKey])) {
            return $this->choicesCache[$cacheKey];
        }

        $manager = $this->getManager($class, $lookupField);
        if (null !== $labelField && !$manager->getClassMetadata($class)->hasField($labelField)) {
            throw new InvalidOptionsException(\sprintf('Field "%s" does not exist in entity "%s".', $labelField, $class));
        }

        $choices = [];
        foreach ($this->loadValuesAndLabels($manager, $class, $lookupField, $labelField) as [$value, $label]) {
            if (null === $value || '' === $value) {
                continue;
            }

            $label ??= (string) $value;
            $choices[$this->getUniqueLabel($choices, $label, $value)] = $value;
        }

        return $this->choicesCache[$cacheKey] = $choices;
    }

    /**
     * Entities are hydrated only when label is taken from __toString() method.
     * Otherwise only needed fields are selected, which is much lighter for bigger tables.
     *
     * @param class-string $class
     *
     * @return iterable<array{0: string|int|null, 1: string|null}>
     */
    private function loadValuesAndLabels(ObjectManager $manager, string $class, string $lookupField, ?string $labelField): iterable
    {
        if (null === $labelField && is_a($class, Stringable::class, true)) {
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            /** @var class-string<Stringable> $class */
            $entities = $manager->getRepository($class)->findAll();
            foreach ($entities as $entity) {
                /** @var string|int|null $value */
                $value = $propertyAccessor->getValue($entity, $lookupField);

                yield [$value, (string) $entity];
            }

            return;
        }

        // bundle supports only Doctrine ORM, see AbstractImportConfiguration
        \assert($manager instanceof EntityManagerInterface);
        $queryBuilder = $manager->createQueryBuilder()
            ->select('e.' . $lookupField . ' AS value')
            ->from($class, 'e');
        if (null !== $labelField) {
            $queryBuilder->addSelect('e.' . $labelField . ' AS label');
        }

        /** @var array{value: string|int|null, label?: scalar|null} $row */
        foreach ($queryBuilder->getQuery()->getArrayResult() as $row) {
            yield [$row['value'], isset($row['label']) ? (string) $row['label'] : null];
        }
    }

    /**
     * Choice labels are array keys, so different entities with the same label would overwrite each other.
     *
     * @param array<string, string|int> $choices
     */
    private function getUniqueLabel(array $choices, string $label, string|int $value): string
    {
        return isset($choices[$label]) && $choices[$label] !== $value
            ? \sprintf('%s (%s)', $label, $value)
            : $label;
    }
}
