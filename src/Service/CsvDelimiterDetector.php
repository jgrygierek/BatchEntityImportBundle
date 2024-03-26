<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Service;

use JG\BatchEntityImportBundle\Enums\CsvDelimiterEnum;

class CsvDelimiterDetector
{
    public function detect(string $csvContent): CsvDelimiterEnum
    {
        $delimiter = $this->detectDelimiter($csvContent);

        return ',' === $delimiter
            ? CsvDelimiterEnum::COMMA
            : CsvDelimiterEnum::SEMICOLON;
    }

    private function detectDelimiter(string $csvContent): string
    {
        $delimiters = CsvDelimiterEnum::asValues();
        $delimiterCount = array_fill_keys($delimiters, 0);

        foreach ($delimiters as $delimiter) {
            $delimiterCount[$delimiter] = substr_count($csvContent, $delimiter);
        }

        return array_search(max($delimiterCount), $delimiterCount, true);
    }
}
