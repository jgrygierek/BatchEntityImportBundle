<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Validator\Constraints;

use Symfony\Component\Validator\Exception\InvalidOptionsException;

/**
 * Since Symfony 8 base Constraint class no longer reads array of options,
 * so options passed in legacy way have to be handled by constraint itself.
 *
 * @internal
 */
trait LegacyOptionsTrait
{
    /**
     * @param string[] $allowedOptions
     *
     * @return array<string, mixed>
     *
     * @throws InvalidOptionsException
     */
    private function normalizeLegacyOptions(mixed $options, array $allowedOptions): array
    {
        if (!\is_array($options)) {
            return [];
        }

        $normalizedOptions = [];
        $invalidOptions = [];
        foreach ($options as $name => $value) {
            if (\is_string($name) && \in_array($name, $allowedOptions, true)) {
                $normalizedOptions[$name] = $value;
            } else {
                $invalidOptions[] = (string) $name;
            }
        }

        if ($invalidOptions) {
            throw new InvalidOptionsException(\sprintf('The options "%s" do not exist in constraint "%s".', implode('", "', $invalidOptions), static::class), $invalidOptions);
        }

        return $normalizedOptions;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws InvalidOptionsException
     */
    private function getStringOption(array $options, string $name): ?string
    {
        $value = $options[$name] ?? null;
        if (null !== $value && !\is_string($value)) {
            throw new InvalidOptionsException(\sprintf('The option "%s" of constraint "%s" should be a string.', $name, static::class), [$name]);
        }

        return $value;
    }

    /**
     * Single string is accepted too, the same way as it was done by base Constraint class.
     *
     * @param array<string, mixed> $options
     *
     * @return string[]|null
     *
     * @throws InvalidOptionsException
     */
    private function getStringListOption(array $options, string $name): ?array
    {
        $value = $options[$name] ?? null;
        if (null === $value) {
            return null;
        }

        $list = [];
        foreach (\is_array($value) ? $value : [$value] as $item) {
            if (!\is_string($item)) {
                throw new InvalidOptionsException(\sprintf('The option "%s" of constraint "%s" should be a string or an array of strings.', $name, static::class), [$name]);
            }

            $list[] = $item;
        }

        return $list;
    }
}
