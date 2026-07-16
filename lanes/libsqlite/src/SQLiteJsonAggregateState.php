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

    /** @var list<array{0:mixed,1:mixed,2:mixed}> */
    private array $windowArrayFrameRows = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $objectPairs = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $distinctObjectPairs = [];

    /** @var list<array{0:mixed,1:mixed,2:mixed}> */
    private array $orderedObjectRows = [];

    /** @var list<array{0:mixed,1:mixed,2:mixed}> */
    private array $distinctOrderedObjectRows = [];

    /** @var list<array{0:mixed,1:mixed,2:mixed}> */
    private array $filteredObjectPairs = [];

    /** @var list<array{0:mixed,1:mixed}> */
    private array $windowObjectPairs = [];

    /** @var list<array{0:mixed,1:mixed,2:mixed}> */
    private array $orderedWindowObjectRows = [];

    /** @var list<array{0:mixed,1:mixed,2:mixed,3:mixed}> */
    private array $windowObjectFrameRows = [];

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

    public function stepArrayWindowFrame(mixed $value, mixed $orderKey, mixed $filter = true): void
    {
        $this->windowArrayFrameRows[] = [$value, $orderKey, $filter];
    }

    public function stepObject(mixed $label, mixed $value): void
    {
        $this->objectPairs[] = [$label, $value];
    }

    public function stepObjectDistinct(mixed $label, mixed $value): void
    {
        $this->distinctObjectPairs[] = [$label, $value];
    }

    public function stepObjectOrderBy(mixed $label, mixed $value, mixed $orderKey): void
    {
        $this->orderedObjectRows[] = [$label, $value, $orderKey];
    }

    public function stepObjectDistinctOrderBy(mixed $label, mixed $value, mixed $orderKey): void
    {
        $this->distinctOrderedObjectRows[] = [$label, $value, $orderKey];
    }

    public function stepObjectFilter(mixed $label, mixed $value, mixed $filter): void
    {
        $this->filteredObjectPairs[] = [$label, $value, $filter];
    }

    public function stepObjectWindow(mixed $label, mixed $value): void
    {
        $this->windowObjectPairs[] = [$label, $value];
    }

    public function stepObjectOrderByWindow(mixed $label, mixed $value, mixed $orderKey): void
    {
        $this->orderedWindowObjectRows[] = [$label, $value, $orderKey];
    }

    public function stepObjectWindowFrame(mixed $label, mixed $value, mixed $orderKey, mixed $filter = true): void
    {
        $this->windowObjectFrameRows[] = [$label, $value, $orderKey, $filter];
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

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeWindowFrameArray(int $preceding, int $following = 0, string $exclude = 'NO OTHERS', string $function = 'json_group_array'): array
    {
        return SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsSqlFunction($function, $this->windowArrayFrameRows, $preceding, $following, $exclude);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeWindowFrameArrayByUnit(string $unit, int|float $preceding, int|float $following = 0, string $exclude = 'NO OTHERS', string $function = 'json_group_array'): array
    {
        return SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnitSqlFunction($function, $this->windowArrayFrameRows, $unit, $preceding, $following, $exclude);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeDistinctOrderedWindowFrameArray(int $preceding, int $following = 0, string $exclude = 'NO OTHERS', string $function = 'json_group_array'): array
    {
        return SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsSqlFunction($function, $this->windowArrayFrameRows, $preceding, $following, $exclude);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeDistinctOrderedWindowFrameArrayByUnit(string $unit, int|float $preceding, int|float $following = 0, string $exclude = 'NO OTHERS', string $function = 'json_group_array'): array
    {
        return SQLiteJsonAggregate::jsonGroupArrayDistinctOrderByWindowFrameRowsByUnitSqlFunction($function, $this->windowArrayFrameRows, $unit, $preceding, $following, $exclude);
    }

    public function finalizeObject(string $function = 'json_group_object'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupObjectSqlFunction($function, $this->objectPairs);
    }

    public function finalizeDistinctObject(string $function = 'json_group_object'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupObjectDistinctSqlFunction($function, $this->distinctObjectPairs);
    }

    public function finalizeOrderedObject(string $function = 'json_group_object'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupObjectOrderBySqlFunction($function, $this->orderedObjectRows);
    }

    public function finalizeDistinctOrderedObject(string $function = 'json_group_object'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupObjectDistinctOrderBySqlFunction($function, $this->distinctOrderedObjectRows);
    }

    public function finalizeFilteredObject(string $function = 'json_group_object'): string|SQLiteBlobValue
    {
        return SQLiteJsonAggregate::jsonGroupObjectFilterSqlFunction($function, $this->filteredObjectPairs);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeWindowedObject(int $preceding, int $following = 0, string $function = 'json_group_object'): array
    {
        return SQLiteJsonAggregate::jsonGroupObjectWindowSqlFunction($function, $this->windowObjectPairs, $preceding, $following);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeOrderedWindowedObject(int $preceding, int $following = 0, string $function = 'json_group_object'): array
    {
        return SQLiteJsonAggregate::jsonGroupObjectOrderByWindowSqlFunction($function, $this->orderedWindowObjectRows, $preceding, $following);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeWindowFrameObject(int $preceding, int $following = 0, string $exclude = 'NO OTHERS', string $function = 'json_group_object'): array
    {
        return SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsSqlFunction($function, $this->windowObjectFrameRows, $preceding, $following, $exclude);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeWindowFrameObjectByUnit(string $unit, int|float $preceding, int|float $following = 0, string $exclude = 'NO OTHERS', string $function = 'json_group_object'): array
    {
        return SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnitSqlFunction($function, $this->windowObjectFrameRows, $unit, $preceding, $following, $exclude);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeDistinctOrderedWindowFrameObject(int $preceding, int $following = 0, string $exclude = 'NO OTHERS', string $function = 'json_group_object'): array
    {
        return SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsSqlFunction($function, $this->windowObjectFrameRows, $preceding, $following, $exclude);
    }

    /**
     * @return list<string|SQLiteBlobValue>
     */
    public function finalizeDistinctOrderedWindowFrameObjectByUnit(string $unit, int|float $preceding, int|float $following = 0, string $exclude = 'NO OTHERS', string $function = 'json_group_object'): array
    {
        return SQLiteJsonAggregate::jsonGroupObjectDistinctOrderByWindowFrameRowsByUnitSqlFunction($function, $this->windowObjectFrameRows, $unit, $preceding, $following, $exclude);
    }

    public function summary(): array
    {
        $summary = [
            'arrayRows' => count($this->arrayValues),
            'distinctArrayRows' => count($this->distinctArrayValues),
            'orderedArrayRows' => count($this->orderedArrayValues),
            'distinctOrderedArrayRows' => count($this->distinctOrderedArrayValues),
            'filteredArrayRows' => count($this->filteredArrayValues),
            'windowArrayRows' => count($this->windowArrayValues),
            'orderedWindowArrayRows' => count($this->orderedWindowArrayValues),
            'objectRows' => count($this->objectPairs),
            'distinctObjectRows' => count($this->distinctObjectPairs),
            'orderedObjectRows' => count($this->orderedObjectRows),
            'distinctOrderedObjectRows' => count($this->distinctOrderedObjectRows),
            'filteredObjectRows' => count($this->filteredObjectPairs),
            'windowObjectRows' => count($this->windowObjectPairs),
            'orderedWindowObjectRows' => count($this->orderedWindowObjectRows),
        ];

        if ($this->windowArrayFrameRows !== []) {
            $summary['windowArrayFrameRows'] = count($this->windowArrayFrameRows);
        }
        if ($this->windowObjectFrameRows !== []) {
            $summary['windowObjectFrameRows'] = count($this->windowObjectFrameRows);
        }

        return $summary;
    }
}
