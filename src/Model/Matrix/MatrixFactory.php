<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Matrix;

use InvalidArgumentException;
use JG\BatchEntityImportBundle\Exception\FileEncodingException;
use JG\BatchEntityImportBundle\Exception\FileReadException;
use JG\BatchEntityImportBundle\Service\CsvDelimiterDetector;
use JG\BatchEntityImportBundle\Utils\EncodingHelper;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class MatrixFactory
{
    private const int CSV_DELIMITER_DETECTION_LENGTH = 65536;

    /**
     * @param string|null $csvFallbackEncoding encoding used for CSV file which is not encoded in Unicode,
     *                                         without it such file is rejected
     *
     * @throws InvalidArgumentException when file extension or fallback encoding is not supported
     * @throws FileEncodingException when encoding of CSV file is not recognized and fallback encoding is not passed
     * @throws FileReadException when file can't be read
     */
    public static function createFromUploadedFile(UploadedFile $file, ?string $csvFallbackEncoding = null): Matrix
    {
        $reader = self::getReader($file, $csvFallbackEncoding);

        try {
            /** @var array<int, array<scalar|null>> $data */
            $data = $reader->load($file->getPathname())->getActiveSheet()->toArray();
        } catch (Throwable $throwable) {
            // corrupted file, or content which does not match the extension
            throw new FileReadException(previous: $throwable);
        }

        // column without header has empty name and is skipped by Matrix
        $header = array_map(static fn (string|int|float|bool|null $name): string => (string) $name, array_shift($data) ?? []);

        return new Matrix(
            $header,
            self::addKeysToRows($header, array_filter($data, self::isNotEmptyRow(...))),
        );
    }

    /**
     * Rows which are not arrays, and columns with invalid names or not scalar values can be sent
     * only in modified request, so such data is treated as no data at all.
     *
     * @param array<mixed> $data
     */
    public static function createFromPostData(array $data): Matrix
    {
        $header = null;
        $rows = [];
        foreach ($data as $row) {
            if (!\is_array($row)) {
                return new Matrix();
            }

            foreach ($row as $name => $value) {
                if ((null !== $value && !\is_scalar($value)) || !self::isPostColumnNameValid($name)) {
                    return new Matrix();
                }
            }

            /** @var array<string, scalar|null> $row */
            $header ??= array_keys($row);
            $rows[] = $row;
        }

        return new Matrix($header ?? [], $rows);
    }

    /**
     * @param string[] $header
     * @param array<array<scalar|null>> $data
     *
     * @return array<array<string, scalar|null>>
     */
    private static function addKeysToRows(array $header, array $data): array
    {
        return array_map(
            static fn (array $row): array => array_combine($header, $row),
            array_values($data),
        );
    }

    /**
     * @param array<scalar|null> $row
     */
    private static function isNotEmptyRow(array $row): bool
    {
        return array_any($row, static fn (int|float|string|bool|null $value): bool => null !== $value && '' !== trim((string) $value));
    }

    /**
     * Only Unicode can be recognized reliably (by BOM or by content). Other encodings can't be distinguished
     * from each other, and wrong guess would silently break the data, so they have to be passed explicitly.
     *
     * @throws InvalidArgumentException
     * @throws FileEncodingException
     */
    private static function getCsvEncoding(UploadedFile $file, ?string $fallbackEncoding): string
    {
        // Encoding from configuration is checked when container is built, but it can also come
        // from environment variable or from controller. Without this check, unsupported encoding
        // would be reported to the user as unreadable file.
        if (null !== $fallbackEncoding && '' !== $fallbackEncoding && !EncodingHelper::isSupported($fallbackEncoding)) {
            throw new InvalidArgumentException(sprintf('CSV fallback encoding "%s" is not supported.', $fallbackEncoding));
        }

        $encoding = Csv::guessEncoding($file->getPathname(), (string) $fallbackEncoding);
        if ('' === $encoding) {
            throw new FileEncodingException();
        }

        return $encoding;
    }

    /**
     * Only beginning of file is needed to detect delimiter, there is no reason to load whole file to memory.
     *
     * @throws FileReadException
     */
    private static function getBeginningOfFile(UploadedFile $file): string
    {
        try {
            return (string) $file->openFile('rb')->fread(self::CSV_DELIMITER_DETECTION_LENGTH);
        } catch (RuntimeException $runtimeException) {
            throw new FileReadException(previous: $runtimeException);
        }
    }

    private static function isPostColumnNameValid(string|int $name): bool
    {
        $name = (string) $name;

        // empty name is used by columns without header, they are skipped by Matrix
        return '' === trim($name) || 1 === preg_match(Matrix::COLUMN_NAME_PATTERN, $name);
    }

    /**
     * @throws InvalidArgumentException
     * @throws FileEncodingException
     * @throws FileReadException
     */
    private static function getReader(UploadedFile $file, ?string $csvFallbackEncoding): BaseReader
    {
        $extension = ucfirst(strtolower($file->getClientOriginalExtension()));
        $readerClass = 'PhpOffice\PhpSpreadsheet\Reader\\' . $extension;
        if (!class_exists($readerClass)) {
            throw new InvalidArgumentException(sprintf('Reader for extension %s is not supported by PhpOffice.', $extension));
        }

        /** @var BaseReader $reader */
        $reader = new $readerClass();
        if ($reader instanceof Csv) {
            $reader->setDelimiter(new CsvDelimiterDetector()->detect(self::getBeginningOfFile($file))->value);
            $reader->setInputEncoding(self::getCsvEncoding($file, $csvFallbackEncoding));
        } elseif ($reader instanceof Xls || $reader instanceof Xlsx) {
            $reader->setIgnoreRowsWithNoCells(true);
        }

        return $reader;
    }
}
