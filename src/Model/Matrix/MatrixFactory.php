<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Matrix;

use InvalidArgumentException;
use JG\BatchEntityImportBundle\Service\CsvDelimiterDetector;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MatrixFactory
{
    /**
     * @throws InvalidArgumentException
     */
    public static function createFromUploadedFile(UploadedFile $file): Matrix
    {
        $reader = self::getReader($file);
        $spreadsheet = $reader->load($file->getPathname());

        /** @var array<int, array<string|null>> $data */
        $data = $spreadsheet->getActiveSheet()->toArray();
        /** @var string[] $header */
        $header = array_shift($data);

        return new Matrix(
            $header,
            self::addKeysToRows($header, $data),
        );
    }

    /**
     * @param array<array<string, mixed>> $data
     */
    public static function createFromPostData(array $data): Matrix
    {
        return $data ? new Matrix(array_keys($data[0]), $data) : new Matrix();
    }

    /**
     * @param string[] $header
     * @param array<array<string|null>> $data
     *
     * @return array<array<string,string>>
     */
    private static function addKeysToRows(array $header, array $data): array
    {
        return array_map(
            static fn (array $row): array => array_combine($header, $row),
            $data,
        );
    }

    private static function getReader(UploadedFile $file): BaseReader
    {
        $extension = ucfirst(strtolower($file->getClientOriginalExtension()));
        $readerClass = 'PhpOffice\PhpSpreadsheet\Reader\\' . $extension;
        if (!class_exists($readerClass)) {
            throw new InvalidArgumentException(sprintf('Reader for extension %s is not supported by PhpOffice.', $extension));
        }

        /** @var BaseReader $reader */
        $reader = new $readerClass();
        if ($reader instanceof Csv) {
            $detectedDelimiter = (new CsvDelimiterDetector())->detect($file->getContent());
            $reader->setDelimiter($detectedDelimiter->value);
        } elseif ($reader instanceof Xls || $reader instanceof Xlsx) {
            $reader->setIgnoreRowsWithNoCells(true);
        }

        return $reader;
    }
}
