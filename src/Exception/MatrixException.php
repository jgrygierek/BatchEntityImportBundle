<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Exception;

use Exception;

/**
 * General exception thrown when matrix record has invalid data.
 */
class MatrixException extends Exception implements BatchEntityImportExceptionInterface
{
    /**
     * @var array<string, string|int>
     */
    protected array $translationParameters = [];

    /**
     * Message of exception is a translation key, these parameters are used to translate it.
     *
     * @return array<string, string|int>
     */
    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }
}
