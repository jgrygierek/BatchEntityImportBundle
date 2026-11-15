<?php

declare(strict_types=1);

namespace JG\BatchEntityImportBundle\Model\Matrix;

use JG\BatchEntityImportBundle\Service\PropertyExistenceChecker;
use JG\BatchEntityImportBundle\Utils\ColumnNameHelper;
use Symfony\Component\Validator\Constraints as Assert;

use const ARRAY_FILTER_USE_KEY;

class Matrix
{
    /**
     * Name of column is used as a name of form field, so it has to start with letter, digit or underscore.
     * Modifier "D" is needed, because without it name ending with new line would be valid too.
     */
    public const COLUMN_NAME_PATTERN = '/^\w[\w -]*(:\w+)?$/D';
    private const string RESERVED_ENTITY_COLUMN_NAME = 'entity';
    private const string RESERVED_ENTITY_ID_COLUMN_NAME = 'entity_id';
    /**
     * @var array<int, string>
     */
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Type('string'),
        new Assert\Regex(pattern: self::COLUMN_NAME_PATTERN, message: 'validation.matrix.header.name'),
    ])]
    #[Assert\NotBlank]
    #[Assert\Unique(message: 'validation.matrix.header.unique')]
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
     * @param array<string|int|null> $header
     * @param array<array<string, scalar|null>> $recordsData
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
     * Returns information which columns will be imported: column has to be allowed, and entity has to have a property for it.
     *
     * @param class-string $className
     * @param array<string> $allowedColumns list of names of columns which can be imported, empty list means that all columns are allowed
     *
     * @return array<string, bool>
     */
    public function getHeaderInfo(string $className, array $allowedColumns = []): array
    {
        $info = [];
        $checker = new PropertyExistenceChecker($className);
        // names are compared as names of properties, so column can't be passed around the list by other notation of its name
        $allowedProperties = array_map(ColumnNameHelper::toCamelCase(...), $allowedColumns);

        foreach ($this->header as $name) {
            $isAllowed = [] === $allowedProperties || \in_array(ColumnNameHelper::toCamelCase($name), $allowedProperties, true);
            $info[$name] = $isAllowed && $checker->propertyExists($name);
        }

        return $info;
    }

    /**
     * @param array<string, scalar|null> $data
     */
    private function getEntityIdValue(array $data): int|string|null
    {
        foreach ($data as $name => $entityId) {
            // name is compared after normalization, the same way as in isColumnNameValid(), so column removed
            // from data as reserved one (e.g. "entity id") is always used as identifier, and never silently lost
            if (self::RESERVED_ENTITY_ID_COLUMN_NAME === ColumnNameHelper::normalize($name)) {
                // identifier can't be float or bool
                return \is_int($entityId) || \is_string($entityId) ? $entityId : null;
            }
        }

        return null;
    }

    /**
     * @param array<string|int|null> $header
     *
     * @return array<int, string>
     */
    private function clearHeader(array $header): array
    {
        $header = array_values(
            array_filter($header, $this->isColumnNameValid(...)),
        );

        return \array_map(ColumnNameHelper::normalize(...), $header);
    }

    /**
     * @param array<string, scalar|null> $data
     *
     * @return array<string, scalar|null>
     */
    private function clearRecordData(array $data): array
    {
        return array_filter($data, $this->isColumnNameValid(...), ARRAY_FILTER_USE_KEY);
    }

    private function isColumnNameValid(string|int|null $name): bool
    {
        // name is compared after normalization, so e.g. "entity id" is reserved too, and "0" is a valid name
        $name = ColumnNameHelper::normalize($name);

        return '' !== $name && !\in_array($name, [self::RESERVED_ENTITY_COLUMN_NAME, self::RESERVED_ENTITY_ID_COLUMN_NAME], true);
    }
}
