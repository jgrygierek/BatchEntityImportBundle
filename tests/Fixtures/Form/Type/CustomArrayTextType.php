<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Fixtures\Form\Type;

use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Field type extended in application, with its own default separator.
 * Its value has to be imported as array too, split by the same separator as shown in the preview.
 */
class CustomArrayTextType extends ArrayTextType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('separator', ';');
    }

    public function getBlockPrefix(): string
    {
        return 'custom_array_text';
    }
}
