<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Matrix;

use JG\BatchEntityImportBundle\Service\PropertyExistenceChecker;
use Symfony\Component\Validator\Constraints as Assert;

use const ARRAY_FILTER_USE_KEY;

class Matrix
{
    private const RESERVED_ENTITY_COLUMN_NAME = 'entity';
    private const RESERVED_ENTITY_ID_COLUMN_NAME = 'entity_id';
    /**
     * @var array<int, string>
     */
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Type('string'),
        new Assert\Regex(pattern: "/^([\w -]+)(:[\w]+)?$/", message: 'validation.matrix.header.name'),
    ])]
    #[Assert\NotBlank]
    private readonly array $header;
    /**
     * @var array|MatrixRecord[]
     */
    #[Assert\All([
        new Assert\Type(MatrixRecord::class),
    ])]
    #[Assert\NotBlank]
    private array $records = [];

    /**
     * @param array<string|null> $header
     * @param array<array<string, string|int>> $recordsData
     */
    public function __construct(array $header = [], array $recordsData = [])
    {
        $this->header = $this->clearHeader($header);

        foreach ($recordsData as $data) {
            $clearedData = $this->clearRecordData($data);
            if ($clearedData) {
                $this->records[] = new MatrixRecord($clearedData, $this->getEntityIdValue($data));
            }
        }
    }

    /**
     * @return array<string>
     */
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

    /**
     * @param class-string $className
     *
     * @return array<string, bool>
     */
    public function getHeaderInfo(string $className): array
    {
        $info = [];
        $checker = new PropertyExistenceChecker($className);

        foreach ($this->header as $name) {
            $info[$name] = $checker->propertyExists($name);
        }

        return $info;
    }

    /**
     * @param array<string, string|int> $data
     */
    private function getEntityIdValue(array $data): int|string|null
    {
        return $data[self::RESERVED_ENTITY_ID_COLUMN_NAME] ?? null;
    }

    /**
     * @param array<string|null> $header
     *
     * @return string[]
     */
    private function clearHeader(array $header): array
    {
        $header = array_values(
            array_filter($header, $this->isColumnNameValid(...)),
        );

        return \array_map(static fn (?string $name): string => \str_replace(' ', '_', $name ?: ''), $header);
    }

    /**
     * @param array<string, string|int> $data
     *
     * @return array<string, string|int>
     */
    private function clearRecordData(array $data): array
    {
        return array_filter($data, $this->isColumnNameValid(...), ARRAY_FILTER_USE_KEY);
    }

    private function isColumnNameValid(string|int|null $name): bool
    {
        return !empty(trim((string) $name)) && !\in_array($name, [self::RESERVED_ENTITY_COLUMN_NAME, self::RESERVED_ENTITY_ID_COLUMN_NAME], true);
    }
}
