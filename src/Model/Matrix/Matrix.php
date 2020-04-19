<?php

namespace JG\BatchEntityImportBundle\Model\Matrix;

use Symfony\Component\Validator\Constraints as Assert;

class Matrix
{
    /**
     * @Assert\All({
     *     @Assert\NotBlank(),
     *     @Assert\Type("string"),
     * })
     */
    private array $header;
    /**
     * @var array|MatrixRecord[]
     *
     * @Assert\All({
     *     @Assert\Type(MatrixRecord::class)
     * })
     */
    private array $records = [];

    public function __construct(array $header = [], array $recordsData = [])
    {
        $this->header = array_filter($header);
        foreach ($recordsData as $data) {
            $data = array_filter($data, fn($key) => !empty($key), ARRAY_FILTER_USE_KEY);
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
}
