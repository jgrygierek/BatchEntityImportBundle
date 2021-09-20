<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Matrix;

use const ARRAY_FILTER_USE_KEY;
use JG\BatchEntityImportBundle\Service\PropertyExistenceChecker;
use Symfony\Component\Validator\Constraints as Assert;

class Matrix
{
    /**
     * @Assert\NotBlank()
     * @Assert\All({
     *     @Assert\NotBlank(),
     *     @Assert\Type("string"),
     *     @Assert\Regex(pattern="/^([\w]+)(:[\w]+)?$/", message="validation.matrix.header.name")
     * })
     */
    private array $header;
    /**
     * @var array|MatrixRecord[]
     *
     * @Assert\NotBlank()
     * @Assert\All({
     *     @Assert\Type(MatrixRecord::class)
     * })
     */
    private array $records = [];

    public function __construct(array $header = [], array $recordsData = [])
    {
        $this->header = $this->clearHeader($header);

        foreach ($recordsData as $data) {
            $data = $this->clearRecordData($data);
            if ($data) {
                $this->records[] = new MatrixRecord($data);
            }
        }
    }

    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * @return array|MatrixRecord[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    public function getHeaderInfo(string $className): array
    {
        $info = [];
        foreach ($this->header as $name) {
            $info[$name] = property_exists($className, $name);
        }

        return $info;
    }

    private function clearHeader(array $header): array
    {
        return array_values(
            array_filter($header, static fn ($e) => !empty(trim((string) $e)))
        );
    }

    private function clearRecordData(array $data): array
    {
        return array_filter($data, static fn ($key) => !empty(trim((string) $key)), ARRAY_FILTER_USE_KEY);
    }
}
