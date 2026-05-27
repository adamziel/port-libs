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
        private readonly int|float $preceding = 0,
        private readonly int|float $following = 0,
        private readonly array|string $partitionAffinities = [],
        private readonly array $partitionCollations = [],
        private readonly array|string $orderAffinities = [],
        private readonly array $orderCollations = [],
        private readonly array $orderDescending = [],
        private readonly array $orderNulls = [],
        private readonly string $frameUnit = 'ROWS',
        private readonly string $excludeMode = 'NO OTHERS',
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
        $unit = strtoupper(trim($frameUnit));
        if (!in_array($unit, ['ROWS', 'RANGE', 'GROUPS'], true)) {
            throw new \InvalidArgumentException('SQLite VDBE window aggregate frame unit is not supported');
        }
        if ($unit !== 'RANGE' && (!self::isIntegerOffset($preceding) || !self::isIntegerOffset($following))) {
            throw new \InvalidArgumentException("SQLite VDBE window aggregate {$unit} frame bounds must be integers");
        }
        if ($unit === 'RANGE' && count($orderColumns) !== 1) {
            throw new \InvalidArgumentException('SQLite VDBE window aggregate RANGE frame requires one ORDER BY column');
        }
        $exclude = strtoupper(trim($excludeMode));
        if (!in_array($exclude, ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'], true)) {
            throw new \InvalidArgumentException('SQLite VDBE window aggregate EXCLUDE mode is not supported');
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

    public function currentFilterValue(): mixed
    {
        $row = $this->requireCurrentRow();

        return $this->filterColumn === null ? null : $row[$this->filterColumn];
    }

    public function currentFilterPassed(): bool
    {
        $this->requireCurrentRow();

        return $this->filterColumn === null || self::isSqlTrue($this->currentFilterValue());
    }

    /**
     * @return array<string,mixed>|null
     */
    public function peekNextRow(): ?array
    {
        return $this->orderedRows[$this->position + 1] ?? null;
    }

    /**
     * @return list<mixed>|null
     */
    public function peekNextPartitionKey(): ?array
    {
        $row = $this->peekNextRow();
        if ($row === null) {
            return null;
        }

        return $this->record($row, $this->partitionColumns);
    }

    /**
     * @return list<mixed>|null
     */
    public function peekNextOrderKey(): ?array
    {
        $row = $this->peekNextRow();
        if ($row === null) {
            return null;
        }

        return $this->record($row, $this->orderColumns);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function currentPeerRows(bool $applyFilter = false): array
    {
        $this->requireCurrentRow();
        [$start, $end] = $this->currentPeerRange();
        $rows = array_slice($this->orderedRows, $start, $end - $start + 1);
        if (!$applyFilter || $this->filterColumn === null) {
            return $rows;
        }

        return array_values(array_filter($rows, fn (array $row): bool => self::isSqlTrue($row[$this->filterColumn])));
    }

    /**
     * @return list<mixed>
     */
    public function currentPeerValues(bool $applyFilter = true): array
    {
        return array_map(fn (array $row): mixed => $row[$this->valueColumn], $this->currentPeerRows($applyFilter));
    }

    /**
     * @return array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,peerStart:int,peerEnd:int,peerRows:int,filteredPeerRows:int,rowids:list<mixed>,values:list<mixed>}
     */
    public function currentPeerSummary(string $rowidColumn = 'rowid'): array
    {
        $this->requireCurrentRow();
        [$start, $end] = $this->currentPeerRange();
        $rows = $this->currentPeerRows(false);

        return [
            'position' => $this->position,
            'partitionKey' => $this->currentPartitionKey(),
            'orderKey' => $this->currentOrderKey(),
            'peerStart' => $start,
            'peerEnd' => $end,
            'peerRows' => $end - $start + 1,
            'filteredPeerRows' => count($this->currentPeerRows(true)),
            'rowids' => array_map(static fn (array $row): mixed => $row[$rowidColumn] ?? null, $rows),
            'values' => $this->currentPeerValues(false),
        ];
    }

    /**
     * @return list<array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,peerStart:int,peerEnd:int,peerRows:int,filteredPeerRows:int,rowids:list<mixed>,values:list<mixed>}>
     */
    public function drainPeerSummaries(string $rowidColumn = 'rowid'): array
    {
        $summaries = [];
        while (!$this->eof()) {
            $summaries[] = $this->currentPeerSummary($rowidColumn);
            $this->next();
        }

        return $summaries;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function currentFrameRows(bool $applyFilter = false): array
    {
        $this->requireCurrentRow();
        $rows = array_map(
            fn (int $index): array => $this->orderedRows[$index],
            $this->currentFrameIndexes()
        );
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

    public function firstValue(bool $applyFilter = false): mixed
    {
        $rows = $this->currentFrameRows($applyFilter);

        return $rows === [] ? null : $rows[0][$this->valueColumn];
    }

    public function lastValue(bool $applyFilter = false): mixed
    {
        $rows = $this->currentFrameRows($applyFilter);

        return $rows === [] ? null : $rows[count($rows) - 1][$this->valueColumn];
    }

    public function nthValue(int $nth, bool $applyFilter = false): mixed
    {
        if ($nth <= 0) {
            throw new \InvalidArgumentException('SQLite VDBE window nth_value() index must be positive');
        }

        $rows = $this->currentFrameRows($applyFilter);

        return $rows[$nth - 1][$this->valueColumn] ?? null;
    }

    /**
     * @return array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,frameStart:int|null,frameEnd:int|null,frameRows:int,filteredRows:int,currentFilterPassed:bool,nextPartitionKey:list<mixed>|null,nextOrderKey:list<mixed>|null,eof:bool,firstValue:mixed,lastValue:mixed,nthValue:mixed}
     */
    public function currentSummary(): array
    {
        $this->requireCurrentRow();

        return $this->summaryAt($this->position);
    }

    /**
     * @return array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,frameStart:int|null,frameEnd:int|null,frameRows:int,filteredRows:int,currentFilterPassed:bool,nextPartitionKey:list<mixed>|null,nextOrderKey:list<mixed>|null,eof:bool,firstValue:mixed,lastValue:mixed,nthValue:mixed}|null
     */
    public function peekNextSummary(): ?array
    {
        if ($this->position + 1 >= count($this->orderedRows)) {
            return null;
        }

        return $this->summaryAt($this->position + 1);
    }

    /**
     * @return array{current:array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,frameStart:int|null,frameEnd:int|null,frameRows:int,filteredRows:int,currentFilterPassed:bool,nextPartitionKey:list<mixed>|null,nextOrderKey:list<mixed>|null,eof:bool,firstValue:mixed,lastValue:mixed,nthValue:mixed},next:array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,frameStart:int|null,frameEnd:int|null,frameRows:int,filteredRows:int,currentFilterPassed:bool,nextPartitionKey:list<mixed>|null,nextOrderKey:list<mixed>|null,eof:bool,firstValue:mixed,lastValue:mixed,nthValue:mixed}|null,advanced:bool}
     */
    public function currentNextSummary(): array
    {
        return [
            'current' => $this->currentSummary(),
            'next' => $this->peekNextSummary(),
            'advanced' => false,
        ];
    }

    /**
     * @return array{current:list<array<string,mixed>>,next:list<array<string,mixed>>|null,advanced:bool}
     */
    public function currentNextFrameRows(bool $applyFilter = false): array
    {
        $this->requireCurrentRow();

        return [
            'current' => $this->frameRowsAt($this->position, $applyFilter),
            'next' => $this->position + 1 < count($this->orderedRows) ? $this->frameRowsAt($this->position + 1, $applyFilter) : null,
            'advanced' => false,
        ];
    }

    /**
     * @return list<array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,frameStart:int|null,frameEnd:int|null,frameRows:int,filteredRows:int,currentFilterPassed:bool,nextPartitionKey:list<mixed>|null,nextOrderKey:list<mixed>|null,eof:bool,value:mixed,total:float,groupConcat:?string,firstValue:mixed,lastValue:mixed,nthValue:mixed}>
     */
    public function drainSummaries(mixed $separator = ',', bool $applyValueFilter = false): array
    {
        $summaries = [];
        while (!$this->eof()) {
            $summary = $this->currentSummary();
            $summary['value'] = $this->requireCurrentRow()[$this->valueColumn];
            $summary['total'] = $this->total();
            $summary['groupConcat'] = $this->groupConcat($separator);
            $summary['firstValue'] = $this->firstValue($applyValueFilter);
            $summary['lastValue'] = $this->lastValue($applyValueFilter);
            $summary['nthValue'] = $this->nthValue(2, $applyValueFilter);
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

        $unit = strtoupper(trim($this->frameUnit));
        if ($unit === 'ROWS') {
            return [max($partitionStart, $this->position - (int) $this->preceding), min($partitionEnd, $this->position + (int) $this->following)];
        }
        if ($unit === 'RANGE') {
            return $this->currentRangeFrame($partitionStart, $partitionEnd);
        }

        return $this->currentGroupsFrame($partitionStart, $partitionEnd);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function currentRangeFrame(int $partitionStart, int $partitionEnd): array
    {
        $orderColumn = $this->orderColumns[0];
        $current = $this->numericRangeKey($this->orderedRows[$this->position][$orderColumn]);
        $descending = $this->orderDescending[0] ?? false;
        $start = null;
        $end = null;
        for ($index = $partitionStart; $index <= $partitionEnd; $index++) {
            $numeric = $this->numericRangeKey($this->orderedRows[$index][$orderColumn]);
            if ($descending) {
                $inFrame = $numeric <= $current + (float) $this->preceding + 1.0e-12
                    && $numeric >= $current - (float) $this->following - 1.0e-12;
            } else {
                $inFrame = $numeric >= $current - (float) $this->preceding - 1.0e-12
                    && $numeric <= $current + (float) $this->following + 1.0e-12;
            }
            if (!$inFrame) {
                continue;
            }
            $start ??= $index;
            $end = $index;
        }

        return [$start ?? $this->position, $end ?? $this->position];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function currentGroupsFrame(int $partitionStart, int $partitionEnd): array
    {
        $groups = [];
        $groupIndexByRow = [];
        for ($index = $partitionStart; $index <= $partitionEnd; $index++) {
            if ($index === $partitionStart || !$this->samePeer($index - 1, $index)) {
                $groups[] = [];
            }
            $groupIndex = count($groups) - 1;
            $groups[$groupIndex][] = $index;
            $groupIndexByRow[$index] = $groupIndex;
        }

        $currentGroup = $groupIndexByRow[$this->position];
        $startGroup = max(0, $currentGroup - (int) $this->preceding);
        $endGroup = min(count($groups) - 1, $currentGroup + (int) $this->following);

        return [$groups[$startGroup][0], $groups[$endGroup][count($groups[$endGroup]) - 1]];
    }

    /**
     * @return list<int>
     */
    private function currentFrameIndexes(): array
    {
        return $this->frameIndexesAt($this->position);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function frameRowsAt(int $position, bool $applyFilter = false): array
    {
        $rows = array_map(
            fn (int $index): array => $this->orderedRows[$index],
            $this->frameIndexesAt($position)
        );
        if (!$applyFilter || $this->filterColumn === null) {
            return $rows;
        }

        return array_values(array_filter($rows, fn (array $row): bool => self::isSqlTrue($row[$this->filterColumn])));
    }

    /**
     * @return list<int>
     */
    private function frameIndexesAt(int $position): array
    {
        return $this->withPosition($position, fn (): array => $this->currentFrameIndexesForCurrentPosition());
    }

    /**
     * @return list<int>
     */
    private function currentFrameIndexesForCurrentPosition(): array
    {
        [$start, $end] = $this->currentFrameRange();

        return $this->applyExcludeMode(range($start, $end));
    }

    /**
     * @return array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,frameStart:int|null,frameEnd:int|null,frameRows:int,filteredRows:int,currentFilterPassed:bool,nextPartitionKey:list<mixed>|null,nextOrderKey:list<mixed>|null,eof:bool,firstValue:mixed,lastValue:mixed,nthValue:mixed}
     */
    private function summaryAt(int $position): array
    {
        return $this->withPosition($position, function (): array {
            $indexes = $this->currentFrameIndexesForCurrentPosition();

            return [
                'position' => $this->position,
                'partitionKey' => $this->currentPartitionKey(),
                'orderKey' => $this->currentOrderKey(),
                'frameStart' => $indexes[0] ?? null,
                'frameEnd' => $indexes[count($indexes) - 1] ?? null,
                'frameRows' => count($indexes),
                'filteredRows' => count($this->currentFrameRows(true)),
                'currentFilterPassed' => $this->currentFilterPassed(),
                'nextPartitionKey' => $this->peekNextPartitionKey(),
                'nextOrderKey' => $this->peekNextOrderKey(),
                'eof' => false,
                'firstValue' => $this->firstValue(),
                'lastValue' => $this->lastValue(),
                'nthValue' => $this->nthValue(2),
            ];
        });
    }

    private function withPosition(int $position, callable $callback): mixed
    {
        if (!array_key_exists($position, $this->orderedRows)) {
            throw new \OutOfBoundsException('SQLite VDBE window aggregate cursor position is out of range');
        }
        $original = $this->position;
        $this->position = $position;
        try {
            return $callback();
        } finally {
            $this->position = $original;
        }
    }

    /**
     * @param list<int> $indexes
     * @return list<int>
     */
    private function applyExcludeMode(array $indexes): array
    {
        return $this->applyExclude($indexes);
    }

    /**
     * @param list<int> $indexes
     * @return list<int>
     */
    private function applyExclude(array $indexes): array
    {
        $exclude = strtoupper(trim($this->excludeMode));
        if ($exclude === 'NO OTHERS') {
            return $indexes;
        }

        return array_values(array_filter($indexes, function (int $index) use ($exclude): bool {
            $current = $index === $this->position;
            $peer = $this->sameOrderPeer($index, $this->position);

            return match ($exclude) {
                'CURRENT ROW' => !$current,
                'GROUP' => !$peer,
                'TIES' => !$peer || $current,
                default => true,
            };
        }));
    }

    /**
     * @return array{0:int,1:int}
     */
    private function currentPartitionRange(): array
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

        return [$partitionStart, $partitionEnd];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function currentPeerRange(): array
    {
        $peerStart = $this->position;
        while ($peerStart > 0 && $this->samePeer($peerStart - 1, $this->position)) {
            $peerStart--;
        }

        $peerEnd = $this->position;
        $last = count($this->orderedRows) - 1;
        while ($peerEnd < $last && $this->samePeer($peerEnd + 1, $this->position)) {
            $peerEnd++;
        }

        return [$peerStart, $peerEnd];
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

    private function samePeer(int $left, int $right): bool
    {
        return $this->samePartition($left, $right)
            && SQLiteVdbeSortCompare::compareRecords(
                $this->record($this->orderedRows[$left], $this->orderColumns),
                $this->record($this->orderedRows[$right], $this->orderColumns),
                $this->orderAffinities,
                $this->orderCollations,
                $this->orderDescending,
                $this->orderNulls
            ) === 0;
    }

    private function sameOrderPeer(int $left, int $right): bool
    {
        return SQLiteVdbeSortCompare::compareRecords(
            $this->record($this->orderedRows[$left], $this->orderColumns),
            $this->record($this->orderedRows[$right], $this->orderColumns),
            $this->orderAffinities,
            $this->orderCollations,
            $this->orderDescending,
            $this->orderNulls
        ) === 0;
    }

    private function numericRangeKey(mixed $value): float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        throw new \InvalidArgumentException('SQLite VDBE window aggregate RANGE frame requires numeric ORDER BY values');
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
        if ($this->filterColumn !== null) {
            self::assertFilterValue($row[$this->filterColumn]);
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

    private static function assertFilterValue(mixed $value): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return;
        }

        throw new \InvalidArgumentException('SQLite VDBE window aggregate FILTER values must be scalar or NULL');
    }

    private static function isIntegerOffset(int|float $offset): bool
    {
        return is_int($offset) || floor($offset) === $offset;
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
