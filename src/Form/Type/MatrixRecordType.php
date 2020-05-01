<?php

namespace JG\BatchEntityImportBundle\Form\Type;

use JG\BatchEntityImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchEntityImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchEntityImportBundle\Model\Matrix\MatrixRecord;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UnexpectedValueException;

class MatrixRecordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var ImportConfigurationInterface $configuration */
        $configuration    = $options['configuration'];
        $fieldDefinitions = $configuration->getFieldsDefinitions();

        $this->addEntityField($builder, $configuration->getEntityClassName());

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($fieldDefinitions) {
                /** @var MatrixRecord $record */
                $record = $event->getData();
                if ($record) {
                    foreach ($record->getData() as $columnName => $value) {
                        $this->addField($fieldDefinitions, $columnName, $event);
                    }
                }
            }
        );
    }

    /**
     * @param OptionsResolver $resolver
     *
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

    private function addEntityField(FormBuilderInterface $builder, string $entityClassName): void
    {
        $builder
            ->add(
                'entity',
                EntityType::class,
                [
                    'class'              => $entityClassName,
                    'label'              => false,
                    'placeholder'        => '---',
                    'translation_domain' => false,
                    'required'           => false,
                ]
            );
    }

    /**
     * @param array|FormFieldDefinition[] $fieldDefinitions
     * @param string                      $columnName
     * @param FormEvent                   $event
     *
     * @throws AlreadySubmittedException
     * @throws LogicException
     * @throws UnexpectedTypeException
     */
    private function addField(array $fieldDefinitions, string $columnName, FormEvent $event): void
    {
        if (!$columnName) {
            throw new UnexpectedValueException('Column name can\'t be empty.');
        }

        $definition = $fieldDefinitions[ColumnNameHelper::removeTranslationSuffix($columnName)] ?? null;

        $definition
            ? $event->getForm()->add($columnName, $definition->getClass(), $definition->getOptions())
            : $event->getForm()->add($columnName, TextType::class);
    }
}
