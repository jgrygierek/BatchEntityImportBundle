<?php

namespace JG\BatchImportBundle\Model\Matrix;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Yectep\PhpSpreadsheetBundle\Factory;

class MatrixFactory
{
    /**
     * @param UploadedFile $file
     *
     * @return Matrix
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ReaderException
     */
    public static function createFromUploadedFile(UploadedFile $file): Matrix
    {
        $factory   = new Factory();
        $extension = ucfirst($file->getClientOriginalExtension());
        /** @var BaseReader $reader */
        $reader      = $factory->createReader($extension);
        $spreadsheet = $reader->load($file->getPathname());

        $data = $spreadsheet->getActiveSheet()->toArray();

        if ($data) {
            $header = array_shift($data);
            self::addKeysToRows($header, $data);
        } else {
            $header = [];
        }

        return new Matrix($header, $data);
    }

    public static function createFromPostData(array $data): Matrix
    {
        return $data ? new Matrix(array_keys($data[0]), $data) : new Matrix();
    }

    private static function addKeysToRows(array $header, array &$data): void
    {
        array_walk(
            $data,
            static function (array &$row) use ($header) {
                $row = array_combine($header, $row);
            }
        );
    }
}
