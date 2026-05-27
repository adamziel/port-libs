<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeWindowAggregateCursor
{
    /** @var list<array<string,mixed>> */
    private array $orderedRows = [];
    private int $position = 0;

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $partitionColumns
     * @param non-empty-list<string> $orderColumns
     * @param list<string>|string $partitionAffinities
     * @param list<string> $partitionCollations
     * @param list<string>|string $orderAffinities
     * @param list<string> $orderCollations
     * @param list<bool> $orderDescending
     * @param list<string|null> $orderNulls
     */
    public function __construct(
        array $rows,
        private readonly string $valueColumn,
        private readonly array $partitionColumns,
        private readonly array $orderColumns,
        private readonly ?string $filterColumn = null,
        private readonly int $preceding = 0,
        private readonly int $following = 0,
        private readonly array|string $partitionAffinities = [],
        private readonly array $partitionCollations = [],
        private readonly array|string $orderAffinities = [],
        private readonly array $orderCollations = [],
        private readonly array $orderDescending = [],
        private readonly array $orderNulls = [],
    ) {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite VDBE window aggregate rows must be a list');
        }
        if ($valueColumn === '') {
            throw new \InvalidArgumentException('SQLite VDBE window aggregate value column must be non-empty');
        }
        if ($preceding < 0 || $following < 0) {
            throw new \InvalidArgumentException('SQLite VDBE window aggregate frame bounds must be non-negative');
        }
        self::assertColumnList($partitionColumns, true, 'partition');
        self::assertColumnList($orderColumns, false, 'order');

        foreach ($rows as $row) {
            $this->assertRow($row);
            $this->orderedRows[] = $row;
        }

        if ($this->orderedRows !== []) {
            $columns = array_merge($partitionColumns, $orderColumns);
            $affinities = self::mergeTerms($partitionAffinities, count($partitionColumns), $orderAffinities);
            $collations = array_merge($partitionCollations, $orderCollations);
            $descending = array_merge(array_fill(0, count($partitionColumns), false), $orderDescending);
            $nulls = array_merge(array_fill(0, count($partitionColumns), null), $orderNulls);
            $this->orderedRows = SQLiteVdbeSortCompare::sortRows($this->orderedRows, $columns, $affinities, $collations, $descending, $nulls);
        }
    }

    public function eof(): bool
    {
        return $this->position >= count($this->orderedRows);
    }

    public function next(): void
    {
        if (!$this->eof()) {
            $this->position++;
        }
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function currentRow(): ?array
    {
        return $this->orderedRows[$this->position] ?? null;
    }

    /**
     * @return list<mixed>
     */
    public function currentPartitionKey(): array
    {
        $row = $this->requireCurrentRow();

        return $this->record($row, $this->partitionColumns);
    }

    /**
     * @return list<mixed>
     */
    public function currentOrderKey(): array
    {
        $row = $this->requireCurrentRow();

        return $this->record($row, $this->orderColumns);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function currentFrameRows(bool $applyFilter = false): array
    {
        $this->requireCurrentRow();
        [$start, $end] = $this->currentFrameRange();
        $rows = array_slice($this->orderedRows, $start, $end - $start + 1);
        if (!$applyFilter || $this->filterColumn === null) {
            return $rows;
        }

        return array_values(array_filter($rows, fn (array $row): bool => self::isSqlTrue($row[$this->filterColumn])));
    }

    /**
     * @return list<mixed>
     */
    public function currentValues(bool $applyFilter = true): array
    {
        return array_map(fn (array $row): mixed => $row[$this->valueColumn], $this->currentFrameRows($applyFilter));
    }

    public function countAll(): int
    {
        return SQLiteNumericAggregate::countAll($this->currentFrameRows(false));
    }

    public function countValue(): int
    {
        return SQLiteNumericAggregate::countValue($this->currentValues());
    }

    public function sum(): int|float|null
    {
        return SQLiteNumericAggregate::sum($this->currentValues());
    }

    public function total(): float
    {
        return SQLiteNumericAggregate::total($this->currentValues());
    }

    public function avg(): ?float
    {
        return SQLiteNumericAggregate::avg($this->currentValues());
    }

    public function min(): mixed
    {
        return SQLiteNumericAggregate::min($this->currentValues());
    }

    public function max(): mixed
    {
        return SQLiteNumericAggregate::max($this->currentValues());
    }

    public function groupConcat(mixed $separator = ','): ?string
    {
        return SQLiteTextAggregate::groupConcat($this->currentValues(), $separator);
    }

    /**
     * @return array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,frameStart:int,frameEnd:int,frameRows:int,filteredRows:int,eof:bool}
     */
    public function currentSummary(): array
    {
        $this->requireCurrentRow();
        [$start, $end] = $this->currentFrameRange();

        return [
            'position' => $this->position,
            'partitionKey' => $this->currentPartitionKey(),
            'orderKey' => $this->currentOrderKey(),
            'frameStart' => $start,
            'frameEnd' => $end,
            'frameRows' => $end - $start + 1,
            'filteredRows' => count($this->currentFrameRows(true)),
            'eof' => false,
        ];
    }

    /**
     * @return list<array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,frameStart:int,frameEnd:int,frameRows:int,filteredRows:int,value:mixed,total:float,groupConcat:?string}>
     */
    public function drainSummaries(mixed $separator = ','): array
    {
        $summaries = [];
        while (!$this->eof()) {
            $summary = $this->currentSummary();
            $summary['value'] = $this->requireCurrentRow()[$this->valueColumn];
            $summary['total'] = $this->total();
            $summary['groupConcat'] = $this->groupConcat($separator);
            $summaries[] = $summary;
            $this->next();
        }

        return $summaries;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function currentFrameRange(): array
    {
        $partitionStart = $this->position;
        while ($partitionStart > 0 && $this->samePartition($partitionStart - 1, $this->position)) {
            $partitionStart--;
        }

        $partitionEnd = $this->position;
        $last = count($this->orderedRows) - 1;
        while ($partitionEnd < $last && $this->samePartition($partitionEnd + 1, $this->position)) {
            $partitionEnd++;
        }

        return [max($partitionStart, $this->position - $this->preceding), min($partitionEnd, $this->position + $this->following)];
    }

    private function samePartition(int $left, int $right): bool
    {
        if ($this->partitionColumns === []) {
            return true;
        }

        return SQLiteVdbeSortCompare::compareRecords(
            $this->record($this->orderedRows[$left], $this->partitionColumns),
            $this->record($this->orderedRows[$right], $this->partitionColumns),
            $this->partitionAffinities,
            $this->partitionCollations
        ) === 0;
    }

    /**
     * @return array<string,mixed>
     */
    private function requireCurrentRow(): array
    {
        $row = $this->currentRow();
        if ($row === null) {
            throw new \OutOfBoundsException('SQLite VDBE window aggregate cursor is at EOF');
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     * @return list<mixed>
     */
    private function record(array $row, array $columns): array
    {
        $record = [];
        foreach ($columns as $column) {
            $record[] = $row[$column];
        }

        return $record;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function assertRow(array $row): void
    {
        if (!array_key_exists($this->valueColumn, $row)) {
            throw new \InvalidArgumentException("SQLite VDBE window aggregate row is missing value column {$this->valueColumn}");
        }
        foreach (array_merge($this->partitionColumns, $this->orderColumns) as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite VDBE window aggregate row is missing sort column {$column}");
            }
            self::assertScalar($row[$column]);
        }
        if ($this->filterColumn !== null && !array_key_exists($this->filterColumn, $row)) {
            throw new \InvalidArgumentException("SQLite VDBE window aggregate row is missing filter column {$this->filterColumn}");
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function assertColumnList(array $columns, bool $allowEmpty, string $label): void
    {
        if (!$allowEmpty && $columns === []) {
            throw new \InvalidArgumentException("SQLite VDBE window aggregate {$label} columns must be non-empty");
        }
        if (!array_is_list($columns)) {
            throw new \InvalidArgumentException("SQLite VDBE window aggregate {$label} columns must be a list");
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException("SQLite VDBE window aggregate {$label} columns must be non-empty strings");
            }
        }
    }

    /**
     * @param list<string>|string $left
     * @param list<string>|string $right
     * @return list<string>|string
     */
    private static function mergeTerms(array|string $left, int $leftCount, array|string $right): array|string
    {
        if (is_string($left) && is_string($right)) {
            return $left . $right;
        }
        if (is_string($left) && $right === []) {
            return $left;
        }
        if ($left === [] && is_string($right)) {
            return $right;
        }
        $leftList = is_string($left) ? str_split($left) : $left;
        $rightList = is_string($right) ? str_split($right) : $right;
        if ($leftList === [] && $leftCount > 0) {
            $leftList = array_fill(0, $leftCount, '');
        }

        return array_merge($leftList, $rightList);
    }

    private static function assertScalar(mixed $value): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value instanceof SQLiteBlobValue) {
            return;
        }

        throw new \InvalidArgumentException('SQLite VDBE window aggregate sort values must be scalar, BLOB, or NULL');
    }

    private static function isSqlTrue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value != 0.0;
        }
        if (is_string($value)) {
            return is_numeric($value) && (float) $value != 0.0;
        }

        return false;
    }
}
