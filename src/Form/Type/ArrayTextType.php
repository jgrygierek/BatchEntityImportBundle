<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Value with separator, e.g. "USER|ADMIN". It is a text in the preview, and it is split into array during import.
 */
class ArrayTextType extends AbstractType
{
    public const string DEFAULT_SEPARATOR = '|';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
            'separator' => self::DEFAULT_SEPARATOR,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'array_text';
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['help'] = $this->translator->trans(
            'form.separator',
            ['%separator%' => self::getSeparator($options)],
            'BatchEntityImportBundle',
        );
    }

    /**
     * Separator from options of field, the same one is shown in the preview and used during import.
     *
     * @param array<mixed> $options options of field, resolved or not
     *
     * @return non-empty-string
     */
    public static function getSeparator(array $options): string
    {
        $separator = $options['separator'] ?? null;

        return \is_string($separator) && '' !== $separator ? $separator : self::DEFAULT_SEPARATOR;
    }
}
