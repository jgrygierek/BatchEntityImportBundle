<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Tests\Unit\Form\Type;

use Generator;
use JG\BatchEntityImportBundle\Form\Type\ArrayTextType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Splitting value into array during import is tested in EntityImporterTest.
 */
// mock of event dispatcher is created by TypeTestCase
#[AllowMockObjectsWithoutExpectations]
class ArrayTextTypeTest extends TypeTestCase
{
    private Stub&TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->translator = $this->createStub(TranslatorInterface::class);
        $this->translator
            ->method('trans')
            ->willReturnCallback(static fn ($key, $params): string => sprintf('separator: "%s"', $params['%separator%'] ?? ''));

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

        $options = $resolver->resolve();

        $this->assertFalse($options['compound']);
        $this->assertEquals('|', $options['separator']);
    }

    /**
     * Value is a text in the preview, it is split into array only during import.
     */
    public function testSubmittedValueIsText(): void
    {
        $form = $this->factory->create(ArrayTextType::class, null, ['separator' => ';']);
        $form->submit('a;b;c');

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('a;b;c', $form->getData());
    }

    #[DataProvider('separatorProvider')]
    public function testBuildView(mixed $separator, string $expected): void
    {
        $type = new ArrayTextType($this->translator);
        $view = new FormView();
        $form = $this->createStub(FormInterface::class);

        $type->buildView($view, $form, ['separator' => $separator]);

        $this->assertArrayHasKey('help', $view->vars);
        $this->assertEquals(\sprintf('separator: "%s"', $expected), $view->vars['help']);
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('separatorOptionsProvider')]
    public function testGetSeparator(array $options, string $expected): void
    {
        $this->assertSame($expected, ArrayTextType::getSeparator($options));
    }

    public static function separatorProvider(): Generator
    {
        yield 'custom separator' => [';', ';'];
        // separator which can't be used is replaced by default one, the same way as during import
        yield 'empty separator' => ['', '|'];
        yield 'null' => [null, '|'];
    }

    public static function separatorOptionsProvider(): Generator
    {
        yield 'custom separator' => [['separator' => ';'], ';'];
        yield 'empty separator' => [['separator' => ''], '|'];
        yield 'null' => [['separator' => null], '|'];
        yield 'not a string' => [['separator' => 1], '|'];
        yield 'not set' => [[], '|'];
    }
}
