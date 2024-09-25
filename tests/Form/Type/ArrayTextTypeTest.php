<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Form\Type;

use Generator;
use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;

class ArrayTextTypeTest extends TypeTestCase
{
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator
            ->method('trans')
            ->willReturnCallback(static function ($key, $params, $domain) {
                return sprintf('separator : "%s"', $params['%separator%'] ?? '');
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

    /**
     * @dataProvider transformDataProvider
     */
    public function testTransform(string $expected, mixed $data): void
    {
        $type = new ArrayTextType($this->translator);
        $type->buildForm($this->createMock(FormBuilderInterface::class), ['separator' => '|', 'empty_data' => '']);

        $this->assertEquals($expected, $type->transform($data));
    }

    public static function transformDataProvider(): Generator
    {
        yield ['', null];
        yield ['', ''];
        yield ['', []];
        yield ['', ['']];
        yield [' ', [' ']];
        yield ['', [null]];
        yield ['a|b|c', ['a', 'b', 'c']];
        yield ['a|b|', ['a', 'b', null]];
        yield ['a||c', ['a', '', 'c']];
        yield ['a||c', ['a', null, 'c']];
        yield ['a||c', ['a', '', 'c']];
        yield ['|b|c', [null, 'b', 'c']];
        yield ['|b|c', ['', 'b', 'c']];
    }

    /**
     * @dataProvider reverseTransformDataProvider
     */
    public function testReverseTransform(?string $data, mixed $expected): void
    {
        $type = new ArrayTextType($this->translator);
        $type->buildForm($this->createMock(FormBuilderInterface::class), ['separator' => '|', 'empty_data' => '']);

        $this->assertEquals($expected, $type->reverseTransform($data));
    }

    public static function reverseTransformDataProvider(): Generator
    {
        yield ['', []];
        yield [' ', [' ']];
        yield ['a|b|c', ['a', 'b', 'c']];
        yield ['a|b|', ['a', 'b', null]];
        yield ['a||c', ['a', '', 'c']];
        yield ['a||c', ['a', null, 'c']];
        yield ['a||c', ['a', '', 'c']];
        yield ['|b|c', [null, 'b', 'c']];
        yield ['|b|c', ['', 'b', 'c']];
    }

    public function testReverseTransformWrongValueException(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Only strings are allowed');

        $type = new ArrayTextType($this->translator);
        $type->buildForm($this->createMock(FormBuilderInterface::class), ['separator' => '|', 'empty_data' => '']);

        $type->reverseTransform([]);
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
