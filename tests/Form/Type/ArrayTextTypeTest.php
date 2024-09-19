<?php

namespace JG\BatchEntityImportBundle\Tests\Form\Type;

use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;

class ArrayTextTypeTest extends TypeTestCase
{
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')
            ->willReturnCallback(function ($key, $params, $domain) {
                return 'separator';
            });

        parent::setUp();
    }

    protected function getExtensions(): array
    {
        $type = new ArrayTextType($this->translator);

        return [
            new PreloadedExtension([$type], []),
        ];
    }

    public function testConfigureOptions(): void
    {
        $type = new ArrayTextType($this->translator);
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve([]);

        $this->assertFalse($options['compound']);
        $this->assertEquals('|', $options['separator']);
    }

    public function testSubmitValidDataBeforeTransform(): void
    {
        $formData = 'a|b|c';
        $form = $this->factory->create(ArrayTextType::class, null, ['separator' => '|']);
        $form->submit($formData);
        $this->assertTrue($form->isSynchronized());
        $this->assertEquals('a|b|c', $form->getData());
    }

    public function testTransform(): void
    {
        $type = new ArrayTextType($this->translator);
        $type->buildForm($this->createMock(FormBuilderInterface::class), ['separator' => '|', 'empty_data' => '']);

        $this->assertEquals('a|b|c', $type->transform(['a', 'b', 'c']));
        $this->assertNull($type->transform(null));
    }

    public function testReverseTransform(): void
    {
        $type = new ArrayTextType($this->translator);
        $type->buildForm($this->createMock(FormBuilderInterface::class), ['separator' => '|', 'empty_data' => '']);

        $this->assertEquals(['a', 'b', 'c'], $type->reverseTransform('a|b|c'));
        $this->assertEquals('', $type->reverseTransform(null));
    }

    public function testBuildView(): void
    {
        $type = new ArrayTextType($this->translator);
        $view = new FormView();
        $form = $this->createMock(FormInterface::class);

        $options = ['separator' => ';'];
        $type->buildView($view, $form, $options);

        $this->assertArrayHasKey('help', $view->vars);
        $this->assertEquals('separator : ";"', $view->vars['help']);
    }
}
