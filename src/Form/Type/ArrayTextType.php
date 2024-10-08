<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;

class ArrayTextType extends AbstractType implements DataTransformerInterface
{
    public const DEFAULT_SEPARATOR = '|';
    private string $separator = self::DEFAULT_SEPARATOR;

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

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->separator = $options['separator'] ?? self::DEFAULT_SEPARATOR;
    }

    public function transform(mixed $value): string
    {
        if (!\is_array($value)) {
            throw new UnexpectedValueException('Only arrays are allowed');
        }

        return implode($this->separator, $value);
    }

    public function reverseTransform(mixed $value): array
    {
        if (!is_string($value)) {
            throw new UnexpectedValueException('Only strings are allowed');
        }

        return $value
            ? explode($this->separator ?: self::DEFAULT_SEPARATOR, $value)
            : [];
    }

    public function getBlockPrefix(): string
    {
        return 'array_text';
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['help'] = $this->translator->trans(
            'form.separator',
            ['%separator%' => $options['separator']],
            'BatchEntityImportBundle',
        );
    }
}
