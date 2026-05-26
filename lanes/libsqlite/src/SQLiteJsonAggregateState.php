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
    private array $distinctOrderedArrayValues = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $filteredArrayValues = [];

    /** @var list<mixed> */
    private array $windowArrayValues = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $orderedWindowArrayValues = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $objectPairs = [];

    /** @var list<array{0:mixed,1:mixed,2:mixed}> */
    private array $filteredObjectPairs = [];

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

    public function stepArrayDistinctOrderBy(mixed $value, mixed $orderKey): void
    {
        $this->distinctOrderedArrayValues[] = [$value, $orderKey];
    }

    public function stepArrayFilter(mixed $value, mixed $filter): void
    {
        $this->filteredArrayValues[] = [$value, $filter];
    }

    public function stepArrayWindow(mixed $value): void
    {
        $this->windowArrayValues[] = $value;
    }

    public function stepArrayOrderByWindow(mixed $value, mixed $orderKey): void
    {
        $this->orderedWindowArrayValues[] = [$value, $orderKey];
    }

    public function stepObject(mixed $label, mixed $value): void
    {
        $this->objectPairs[] = [$label, $value];
    }

    public function stepObjectFilter(mixed $label, mixed $value, mixed $filter): void
    {
        $this->filteredObjectPairs[] = [$label, $value, $filter];
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

    public function finalizeDistinctOrderedArray(string $function = 'json_group_array'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupArrayDistinctOrderBySqlFunction($function, $this->distinctOrderedArrayValues);
    }

    public function finalizeFilteredArray(string $function = 'json_group_array'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupArrayFilterSqlFunction($function, $this->filteredArrayValues);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeWindowedArray(int $preceding, int $following = 0, string $function = 'json_group_array'): array
    {
        return SQLiteJsonAggregate::jsonGroupArrayWindowSqlFunction($function, $this->windowArrayValues, $preceding, $following);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeOrderedWindowedArray(int $preceding, int $following = 0, string $function = 'json_group_array'): array
    {
        return SQLiteJsonAggregate::jsonGroupArrayOrderByWindowSqlFunction($function, $this->orderedWindowArrayValues, $preceding, $following);
    }

    public function finalizeObject(string $function = 'json_group_object'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupObjectSqlFunction($function, $this->objectPairs);
    }

    public function finalizeFilteredObject(string $function = 'json_group_object'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupObjectFilterSqlFunction($function, $this->filteredObjectPairs);
    }

    /**
     * @return array{arrayRows:int,distinctArrayRows:int,orderedArrayRows:int,distinctOrderedArrayRows:int,filteredArrayRows:int,windowArrayRows:int,orderedWindowArrayRows:int,objectRows:int,filteredObjectRows:int}
     */
    public function summary(): array
    {
        return [
            'arrayRows' => count($this->arrayValues),
            'distinctArrayRows' => count($this->distinctArrayValues),
            'orderedArrayRows' => count($this->orderedArrayValues),
            'distinctOrderedArrayRows' => count($this->distinctOrderedArrayValues),
            'filteredArrayRows' => count($this->filteredArrayValues),
            'windowArrayRows' => count($this->windowArrayValues),
            'orderedWindowArrayRows' => count($this->orderedWindowArrayValues),
            'objectRows' => count($this->objectPairs),
            'filteredObjectRows' => count($this->filteredObjectPairs),
        ];
    }
}
