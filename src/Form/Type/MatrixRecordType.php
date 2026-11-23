<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Form\Type;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Service\RelatedEntityFinder;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Form used by the bundle in import process, use form type extension to change it.
 *
 * @internal
 */
final class MatrixRecordType extends AbstractType
{
    private const string ENTITY_FIELD = 'entity';

    public function __construct(private readonly RelatedEntityFinder $relatedEntityFinder)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ImportConfigurationInterface $configuration */
        $configuration = $options['configuration'];
        $fieldDefinitions = $configuration->getFieldsDefinitions();

        if ($configuration->allowOverrideEntity()) {
            $entityFieldDefinition = $configuration->getOverrideEntityFieldDefinition();
            null === $entityFieldDefinition
                ? $this->addEntityField($builder, $configuration->getEntityClassName(), $configuration->getEntityTranslationRelationName())
                : $this->addEntityLookupField($builder, $configuration->getEntityClassName(), $entityFieldDefinition);
        }

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($fieldDefinitions): void {
                $record = $event->getData();
                if ($record instanceof MatrixRecord) {
                    foreach ($record->getData() as $columnName => $value) {
                        $this->addField($fieldDefinitions, $columnName, $event);
                    }
                }
            },
        );
    }

    /**
     * @throws AccessException
     * @throws UndefinedOptionsException
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults(['data_class' => MatrixRecord::class])
            ->setRequired('configuration')
            ->addAllowedTypes('configuration', ImportConfigurationInterface::class);
    }

    private function addEntityField(FormBuilderInterface $builder, string $entityClassName, ?string $entityTranslationRelationName): void
    {
        $builder
            ->add(
                self::ENTITY_FIELD,
                EntityType::class,
                [
                    'class' => $entityClassName,
                    'label' => false,
                    'placeholder' => '---',
                    'translation_domain' => false,
                    'required' => false,
                    'query_builder' => static function (EntityRepository $er) use ($entityTranslationRelationName): QueryBuilder {
                        $qb = $er->createQueryBuilder('qb')->select('qb');
                        if ($entityTranslationRelationName) {
                            $qb->addSelect(['t'])->leftJoin('qb.' . $entityTranslationRelationName, 't');
                        }

                        return $qb;
                    },
                ],
            );
    }

    /**
     * Entity is found by value of "lookup_field", so only this value is rendered in the preview, not all entities.
     *
     * @throws InvalidArgumentException when definition does not use one of entity lookup types
     */
    private function addEntityLookupField(FormBuilderInterface $builder, string $entityClassName, FormFieldDefinition $definition): void
    {
        if (!is_a($definition->getClass(), AbstractEntityLookupType::class, true)) {
            throw new InvalidArgumentException(\sprintf('Field used to override entity has to be "%s" or "%s", "%s" given.', EntityLookupType::class, EntityLookupTextType::class, $definition->getClass()));
        }

        $builder->add(self::ENTITY_FIELD, $definition->getClass(), [
            ...$definition->getOptions(),
            'class' => $entityClassName,
            'label' => false,
            'required' => false,
            // value of field is a scalar, entity is set to record after submit
            'mapped' => false,
        ]);

        // after data of record is mapped to fields, otherwise not mapped field would be cleared
        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event): void {
            $record = $event->getData();
            if ($record instanceof MatrixRecord) {
                $field = $event->getForm()->get(self::ENTITY_FIELD);
                $field->setData(self::getLookupValue($record, self::getLookupField($field)));
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($entityClassName): void {
            $record = $event->getData();
            // always set after submit, because of "data_class" option
            \assert($record instanceof MatrixRecord);

            $field = $event->getForm()->get(self::ENTITY_FIELD);
            $value = $field->getData();
            // entity which does not exist is reported by validation of field, record is imported as new entity then
            $record->setEntity(
                \is_scalar($value) && '' !== $value ? $this->relatedEntityFinder->find($entityClassName, self::getLookupField($field), $value) : null,
            );
        });
    }

    private static function getLookupField(FormInterface $field): string
    {
        $lookupField = $field->getConfig()->getOption('lookup_field');

        return \is_string($lookupField) ? $lookupField : RelatedEntityFinder::DEFAULT_LOOKUP_FIELD;
    }

    /**
     * Value of "entity_id" column in file is treated as value of lookup field.
     */
    private static function getLookupValue(MatrixRecord $record, string $lookupField): string|int|null
    {
        $entity = $record->getEntity();
        if (null === $entity) {
            return $record->entityId;
        }

        $value = PropertyAccess::createPropertyAccessor()->getValue($entity, $lookupField);

        return \is_int($value) || \is_string($value) ? $value : null;
    }

    /**
     * @param array|FormFieldDefinition[] $fieldDefinitions
     *
     * @throws AlreadySubmittedException
     * @throws LogicException
     * @throws UnexpectedTypeException
     */
    private function addField(array $fieldDefinitions, string $columnName, FormEvent $event): void
    {
        $definition = $fieldDefinitions[ColumnNameHelper::removeTranslationSuffix($columnName)] ?? null;

        $definition
            ? $event->getForm()->add($columnName, $definition->getClass(), $definition->getOptions())
            : $event->getForm()->add($columnName, TextType::class);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $record = $form->getData();
        if (!$record instanceof MatrixRecord || null === $record->entityId || !isset($view['entity'])) {
            return;
        }

        // values of choices are always strings, identifier of entity can be also an integer
        $selectedValue = (string) $record->entityId;
        $choices = $view['entity']->vars['choices'] ?? [];
        foreach (is_iterable($choices) ? $choices : [] as $choice) {
            if ($choice instanceof ChoiceView && $choice->value === $selectedValue) {
                $choice->attr['selected'] = 'selected';
            }
        }
    }
}
