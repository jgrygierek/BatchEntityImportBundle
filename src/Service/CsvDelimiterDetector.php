<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Service;

use JG\BatchEntityImportBundle\Enums\CsvDelimiterEnum;

/**
 * @internal
 */
final class CsvDelimiterDetector
{
    /**
     * Delimiter is detected only in header line and only outside of quoted values,
     * so delimiters used in text of records do not affect the result.
     */
    public function detect(string $csvContent): CsvDelimiterEnum
    {
        $header = $this->removeQuotedValues($this->getFirstLine($csvContent));

        $detectedDelimiter = CsvDelimiterEnum::SEMICOLON;
        $maxCount = 0;
        foreach (CsvDelimiterEnum::cases() as $delimiter) {
            $count = substr_count($header, $delimiter->value);
            if ($count > $maxCount) {
                $maxCount = $count;
                $detectedDelimiter = $delimiter;
            }
        }

        return $detectedDelimiter;
    }

    /**
     * New line inside of quoted value does not end the line.
     */
    private function getFirstLine(string $csvContent): string
    {
        $isQuoted = false;
        $length = \strlen($csvContent);
        for ($i = 0; $i < $length; ++$i) {
            $char = $csvContent[$i];
            if ('"' === $char) {
                $isQuoted = !$isQuoted;
            } elseif (!$isQuoted && ("\n" === $char || "\r" === $char)) {
                return substr($csvContent, 0, $i);
            }
        }

        return $csvContent;
    }

    private function removeQuotedValues(string $line): string
    {
        return preg_replace('/"(?:[^"]|"")*"/', '', $line) ?? $line;
    }
}
