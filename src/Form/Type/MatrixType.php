<?php

namespace JG\BatchImportBundle\Form\Type;

use JG\BatchImportBundle\Model\Configuration\ImportConfigurationInterface;
use JG\BatchImportBundle\Model\Matrix\Matrix;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MatrixType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'records',
                CollectionType::class,
                [
                    'entry_type'    => MatrixRecordType::class,
                    'entry_options' => [
                        'configuration' => $options['configuration'],
                        'label'         => false,
                    ],
                    'label'         => false,
                ]
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
            ->setDefaults(['data_class' => Matrix::class])
            ->setRequired('configuration')
            ->addAllowedTypes('configuration', ImportConfigurationInterface::class);
    }
}
