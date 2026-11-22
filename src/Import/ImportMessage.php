<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Import;

/**
 * Translated message for user, e.g. to be shown as flash message.
 *
 * @internal used by ImportControllerTrait, it is not covered by backward compatibility promise yet
 */
final readonly class ImportMessage
{
    public const string TYPE_SUCCESS = 'success';
    public const string TYPE_ERROR = 'error';

    /**
     * @param self::TYPE_* $type
     */
    private function __construct(public string $type, public string $message)
    {
    }

    public static function success(string $message): self
    {
        return new self(self::TYPE_SUCCESS, $message);
    }

    public static function error(string $message): self
    {
        return new self(self::TYPE_ERROR, $message);
    }
}
