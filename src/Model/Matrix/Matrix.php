<?php

namespace JG\BatchImportBundle\Model\Matrix;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class Matrix
{
    /**
     * @Assert\All({
     *     @Assert\Type("string")
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
        $this->header = $header;
        foreach ($recordsData as $recordData) {
            $this->records[] = new MatrixRecord($recordData);
        }
    }

    public function getHeader(): array
    {
        return $this->header;
    }

    public function setHeader(array $header): void
    {
        $this->header = $header;
    }

    /**
     * @return array|MatrixRecord[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @param array|MatrixRecord[] $records
     */
    public function setRecords(array $records): void
    {
        $this->records = $records;
    }

    /**
     * @Assert\Callback
     *
     * @param ExecutionContextInterface $context
     */
    public function haveRecordsExactFieldsNumber(ExecutionContextInterface $context): void
    {
        $prev = null;
        foreach ($this->records as $record) {
            $count = count($record->getData());
            if ($prev && $prev !== $count) {
                $context->addViolation('validation.matrix.fields_number');

                return;
            }

            $prev = $count;
        }
    }
}
