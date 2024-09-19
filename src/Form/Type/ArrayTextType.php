<?php

namespace JG\BatchEntityImportBundle\Form\Type;

use Symfony\Component\Form\FormView;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ArrayTextType extends AbstractType implements DataTransformerInterface
{
    private ?string $separator = null;

    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'compound' => false,
            'separator' => "|",
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->separator = $options['separator'];

        if ('' === $options['empty_data']) {
            $builder->addViewTransformer($this);
        }
    }

    public function transform(mixed $data): mixed
    {
        if (is_array($data)) {
            return implode($this->separator, $data);
        }

        return $data;
    }

    public function reverseTransform(mixed $data): mixed
    {
        if (is_string($data)) {
            return explode($this->separator, $data);
        }

        return $data ?? '';
    }

    public function getBlockPrefix(): string
    {
        return 'array_text';
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['help'] = $this->translator->trans(
            'form.separator',
            [],
            'BatchEntityImportBundle'
        ) . ' : "' . $options['separator'] . '"';
    }
}
