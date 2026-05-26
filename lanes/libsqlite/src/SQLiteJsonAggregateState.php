<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonAggregateState
{
    /** @var list<mixed> */
    private array $arrayValues = [];

    /** @var list<mixed> */
    private array $distinctArrayValues = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $orderedArrayValues = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $objectPairs = [];

    public function stepArray(mixed $value): void
    {
        $this->arrayValues[] = $value;
    }

    public function stepArrayDistinct(mixed $value): void
    {
        $this->distinctArrayValues[] = $value;
    }

    public function stepArrayOrderBy(mixed $value, mixed $orderKey): void
    {
        $this->orderedArrayValues[] = [$value, $orderKey];
    }

    public function stepObject(mixed $label, mixed $value): void
    {
        $this->objectPairs[] = [$label, $value];
    }

    public function finalizeArray(string $function = 'json_group_array'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupArraySqlFunction($function, $this->arrayValues);
    }

    public function finalizeDistinctArray(string $function = 'json_group_array'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupArrayDistinctSqlFunction($function, $this->distinctArrayValues);
    }

    public function finalizeOrderedArray(string $function = 'json_group_array'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupArrayOrderBySqlFunction($function, $this->orderedArrayValues);
    }

    public function finalizeObject(string $function = 'json_group_object'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupObjectSqlFunction($function, $this->objectPairs);
    }

    /**
     * @return array{arrayRows:int,distinctArrayRows:int,orderedArrayRows:int,objectRows:int}
     */
    public function summary(): array
    {
        return [
            'arrayRows' => count($this->arrayValues),
            'distinctArrayRows' => count($this->distinctArrayValues),
            'orderedArrayRows' => count($this->orderedArrayValues),
            'objectRows' => count($this->objectPairs),
        ];
    }
}
