<?php

namespace JG\BatchImportBundle\Form\Type;

use JG\BatchImportBundle\Model\Form\FormConfigurationInterface;
use JG\BatchImportBundle\Model\Form\FormFieldDefinition;
use JG\BatchImportBundle\Model\MatrixRecord;
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

class MatrixRecordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var FormConfigurationInterface $configuration */
        $configuration    = $options['configuration'];
        $fieldDefinitions = $configuration->getFieldsDefinitions();

        $builder
            ->add(
                'entity',
                EntityType::class,
                [
                    'class'              => $configuration->getEntityClassName(),
                    'mapped'             => false,
                    'label'              => false,
                    'placeholder'        => '---',
                    'translation_domain' => false,
                    'required'           => false,
                ]
            );

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($fieldDefinitions) {
                /** @var MatrixRecord $record */
                $record = $event->getData();
                if ($record) {
                    foreach ($record->getData() as $columnName => $value) {
                        $this->addField($fieldDefinitions, $columnName, $event);
                    };
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
            ->addAllowedTypes('configuration', FormConfigurationInterface::class);
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
        if ($columnName === 'entity') {
            return;
        }

        if (!isset($fieldDefinitions[$columnName])) {
            $event->getForm()->add($columnName, TextType::class);
        } else {
            $definition = $fieldDefinitions[$columnName];
            $event->getForm()->add($columnName, $definition->getClass(), $definition->getOptions());
        }
    }
}
