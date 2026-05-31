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
        private readonly string $startBoundary = 'PRECEDING',
        private readonly string $endBoundary = 'FOLLOWING',
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
        self::assertFrameBoundary($startBoundary, true);
        self::assertFrameBoundary($endBoundary, false);
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

    public function countFilteredAll(): int
    {
        return SQLiteNumericAggregate::countAll($this->currentFrameRows(true));
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
     * @return array{values:list<mixed>,firstValue:mixed,lastValue:mixed,nthValue:mixed,rowids:list<mixed>}
     */
    public function currentValueFrameSummary(int $nth = 2, bool $applyFilter = true, string $rowidColumn = 'rowid'): array
    {
        $this->requireCurrentRow();

        return $this->valueFrameSummaryAt($this->position, $nth, $applyFilter, $rowidColumn);
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
     * @return array{current:array{values:list<mixed>,firstValue:mixed,lastValue:mixed,nthValue:mixed,rowids:list<mixed>},next:array{values:list<mixed>,firstValue:mixed,lastValue:mixed,nthValue:mixed,rowids:list<mixed>}|null,advanced:bool}
     */
    public function currentNextValueFrameSummary(int $nth = 2, bool $applyFilter = true, string $rowidColumn = 'rowid'): array
    {
        $this->requireCurrentRow();

        return [
            'current' => $this->valueFrameSummaryAt($this->position, $nth, $applyFilter, $rowidColumn),
            'next' => $this->position + 1 < count($this->orderedRows)
                ? $this->valueFrameSummaryAt($this->position + 1, $nth, $applyFilter, $rowidColumn)
                : null,
            'advanced' => false,
        ];
    }

    /**
     * @return array{current:array{position:int,row:array<string,mixed>,frameRowids:list<mixed>,filteredFrameRowids:list<mixed>,countAll:int,countValue:int,sum:int|float|null,total:float,avg:float|null,min:mixed,max:mixed,groupConcat:?string,firstValue:mixed,lastValue:mixed,nthValue:mixed},next:array{position:int,row:array<string,mixed>,frameRowids:list<mixed>,filteredFrameRowids:list<mixed>,countAll:int,countValue:int,sum:int|float|null,total:float,avg:float|null,min:mixed,max:mixed,groupConcat:?string,firstValue:mixed,lastValue:mixed,nthValue:mixed}|null,advanced:bool}
     */
    public function currentNextAggregateSummary(string $rowidColumn = 'rowid', mixed $separator = ',', int $nth = 2, bool $valueFunctionsApplyFilter = false): array
    {
        $this->requireCurrentRow();

        return [
            'current' => $this->aggregateSummaryAt($this->position, $rowidColumn, $separator, $nth, $valueFunctionsApplyFilter),
            'next' => $this->position + 1 < count($this->orderedRows)
                ? $this->aggregateSummaryAt($this->position + 1, $rowidColumn, $separator, $nth, $valueFunctionsApplyFilter)
                : null,
            'advanced' => false,
        ];
    }

    /**
     * @param non-empty-list<string> $aggregateOrderColumns
     * @param list<string>|string $aggregateOrderAffinities
     * @param list<string> $aggregateOrderCollations
     * @param list<bool> $aggregateOrderDescending
     * @param list<string|null> $aggregateOrderNulls
     * @return array{current:array{position:int,row:array<string,mixed>,frameRowids:list<mixed>,orderedFrameRowids:list<mixed>,orderedValues:list<mixed>,countValue:int,sum:int|float|null,total:float,avg:float|null,min:mixed,max:mixed,groupConcat:?string},next:array{position:int,row:array<string,mixed>,frameRowids:list<mixed>,orderedFrameRowids:list<mixed>,orderedValues:list<mixed>,countValue:int,sum:int|float|null,total:float,avg:float|null,min:mixed,max:mixed,groupConcat:?string}|null,advanced:bool,aggregateOrderColumns:non-empty-list<string>}
     */
    public function currentNextOrderedAggregateSummary(
        array $aggregateOrderColumns,
        string $rowidColumn = 'rowid',
        mixed $separator = ',',
        array|string $aggregateOrderAffinities = [],
        array $aggregateOrderCollations = [],
        array $aggregateOrderDescending = [],
        array $aggregateOrderNulls = [],
    ): array {
        $this->requireCurrentRow();
        self::assertColumnList($aggregateOrderColumns, false, 'aggregate order');

        return [
            'current' => $this->orderedAggregateSummaryAt(
                $this->position,
                $aggregateOrderColumns,
                $rowidColumn,
                $separator,
                $aggregateOrderAffinities,
                $aggregateOrderCollations,
                $aggregateOrderDescending,
                $aggregateOrderNulls
            ),
            'next' => $this->position + 1 < count($this->orderedRows)
                ? $this->orderedAggregateSummaryAt(
                    $this->position + 1,
                    $aggregateOrderColumns,
                    $rowidColumn,
                    $separator,
                    $aggregateOrderAffinities,
                    $aggregateOrderCollations,
                    $aggregateOrderDescending,
                    $aggregateOrderNulls
                )
                : null,
            'advanced' => false,
            'aggregateOrderColumns' => $aggregateOrderColumns,
        ];
    }

    /**
     * @return array{position:int,currentRowid:mixed,nextRowid:mixed,partitionKey:list<mixed>,orderKey:list<mixed>,nextPartitionKey:list<mixed>|null,nextOrderKey:list<mixed>|null,nextSamePartition:bool,nextSamePeer:bool,rawFrameRowids:list<mixed>,frameRowids:list<mixed>,excludedRowids:list<mixed>,filteredRowids:list<mixed>,frameValues:list<mixed>,filteredValues:list<mixed>,countAll:int,countValue:int,sum:int|float|null,total:float,groupConcat:?string}
     */
    public function currentYieldSummary(string $rowidColumn = 'rowid', mixed $separator = ','): array
    {
        $row = $this->requireCurrentRow();
        $rawIndexes = $this->currentRawFrameIndexes();
        $frameIndexes = $this->applyExcludeMode($rawIndexes);
        $excludedIndexes = array_values(array_diff($rawIndexes, $frameIndexes));
        $filteredRows = $this->currentFrameRows(true);
        $filteredValues = array_map(fn (array $frameRow): mixed => $frameRow[$this->valueColumn], $filteredRows);
        $nextRow = $this->peekNextRow();

        return [
            'position' => $this->position,
            'currentRowid' => $row[$rowidColumn] ?? null,
            'nextRowid' => $nextRow[$rowidColumn] ?? null,
            'partitionKey' => $this->currentPartitionKey(),
            'orderKey' => $this->currentOrderKey(),
            'nextPartitionKey' => $this->peekNextPartitionKey(),
            'nextOrderKey' => $this->peekNextOrderKey(),
            'nextSamePartition' => $nextRow !== null && $this->samePartition($this->position, $this->position + 1),
            'nextSamePeer' => $nextRow !== null && $this->samePeer($this->position, $this->position + 1),
            'rawFrameRowids' => $this->rowidsForIndexes($rawIndexes, $rowidColumn),
            'frameRowids' => $this->rowidsForIndexes($frameIndexes, $rowidColumn),
            'excludedRowids' => $this->rowidsForIndexes($excludedIndexes, $rowidColumn),
            'filteredRowids' => array_map(static fn (array $frameRow): mixed => $frameRow[$rowidColumn] ?? null, $filteredRows),
            'frameValues' => array_map(fn (int $index): mixed => $this->orderedRows[$index][$this->valueColumn], $frameIndexes),
            'filteredValues' => $filteredValues,
            'countAll' => count($frameIndexes),
            'countValue' => SQLiteNumericAggregate::countValue($filteredValues),
            'sum' => SQLiteNumericAggregate::sum($filteredValues),
            'total' => SQLiteNumericAggregate::total($filteredValues),
            'groupConcat' => SQLiteTextAggregate::groupConcat($filteredValues, $separator),
        ];
    }

    /**
     * @return list<array{position:int,partitionKey:list<mixed>,orderKey:list<mixed>,frameStart:int|null,frameEnd:int|null,frameRows:int,filteredRows:int,currentFilterPassed:bool,nextPartitionKey:list<mixed>|null,nextOrderKey:list<mixed>|null,eof:bool,value:mixed,countAll:int,countFilteredAll:int,countValue:int,sum:int|float|null,total:float,groupConcat:?string,firstValue:mixed,lastValue:mixed,nthValue:mixed}>
     */
    public function drainSummaries(mixed $separator = ',', bool $applyValueFilter = false): array
    {
        $summaries = [];
        while (!$this->eof()) {
            $summary = $this->currentSummary();
            $summary['value'] = $this->requireCurrentRow()[$this->valueColumn];
            $summary['countAll'] = $this->countAll();
            $summary['countFilteredAll'] = $this->countFilteredAll();
            $summary['countValue'] = $this->countValue();
            $summary['sum'] = $this->sum();
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
            return [
                max($partitionStart, min($partitionEnd + 1, $this->rowsBoundaryIndex($this->startBoundary, $this->preceding, $partitionStart, $partitionEnd, true))),
                min($partitionEnd, max($partitionStart - 1, $this->rowsBoundaryIndex($this->endBoundary, $this->following, $partitionStart, $partitionEnd, false))),
            ];
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
        $startBoundary = strtoupper(trim($this->startBoundary));
        $endBoundary = strtoupper(trim($this->endBoundary));
        [$peerStart, $peerEnd] = $this->currentPeerRange();
        $start = null;
        $end = null;
        for ($index = $partitionStart; $index <= $partitionEnd; $index++) {
            $inFrame = $this->rangeBoundaryIncludes($index, $startBoundary, (float) $this->preceding, true, $peerStart, $peerEnd)
                && $this->rangeBoundaryIncludes($index, $endBoundary, (float) $this->following, false, $peerStart, $peerEnd);
            if (!$inFrame) {
                continue;
            }
            $start ??= $index;
            $end = $index;
        }

        return [$start ?? $partitionEnd + 1, $end ?? $partitionStart - 1];
    }

    private function rangeBoundaryIncludes(
        int $index,
        string $boundary,
        float $offset,
        bool $isStart,
        int $peerStart,
        int $peerEnd
    ): bool {
        if ($boundary === 'UNBOUNDED PRECEDING' || $boundary === 'UNBOUNDED FOLLOWING') {
            return true;
        }
        if ($boundary === 'CURRENT ROW') {
            return $isStart ? $index >= $peerStart : $index <= $peerEnd;
        }

        $orderColumn = $this->orderColumns[0];
        $currentValue = $this->orderedRows[$this->position][$orderColumn];
        $candidateValue = $this->orderedRows[$index][$orderColumn];
        if (!self::isNumericRangeValue($currentValue) || !self::isNumericRangeValue($candidateValue)) {
            return $index >= $peerStart && $index <= $peerEnd;
        }

        $current = (float) $currentValue;
        $candidate = (float) $candidateValue;
        $descending = $this->orderDescending[0] ?? false;
        if ($isStart) {
            $limit = $this->rangeLowerLimit($current, $boundary, $offset, $descending);

            return $descending
                ? $candidate <= $limit + 1.0e-12
                : $candidate >= $limit - 1.0e-12;
        }

        $limit = $this->rangeUpperLimit($current, $boundary, $offset, $descending);

        return $descending
            ? $candidate >= $limit - 1.0e-12
            : $candidate <= $limit + 1.0e-12;
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
        $startGroup = max(0, min(count($groups), $this->groupBoundaryIndex($currentGroup, count($groups), $this->startBoundary, (int) $this->preceding, true)));
        $endGroup = min(count($groups) - 1, max(-1, $this->groupBoundaryIndex($currentGroup, count($groups), $this->endBoundary, (int) $this->following, false)));
        if ($startGroup > $endGroup) {
            return [$partitionEnd + 1, $partitionStart - 1];
        }

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
     * @return array{values:list<mixed>,firstValue:mixed,lastValue:mixed,nthValue:mixed,rowids:list<mixed>}
     */
    private function valueFrameSummaryAt(int $position, int $nth, bool $applyFilter, string $rowidColumn): array
    {
        if ($nth <= 0) {
            throw new \InvalidArgumentException('SQLite VDBE window nth_value() index must be positive');
        }

        $rows = $this->frameRowsAt($position, $applyFilter);
        $values = array_map(fn (array $row): mixed => $row[$this->valueColumn], $rows);

        return [
            'values' => $values,
            'firstValue' => $values[0] ?? null,
            'lastValue' => $values[count($values) - 1] ?? null,
            'nthValue' => $values[$nth - 1] ?? null,
            'rowids' => array_map(static fn (array $row): mixed => $row[$rowidColumn] ?? null, $rows),
        ];
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
        return $this->applyExcludeMode($this->currentRawFrameIndexes());
    }

    /**
     * @return list<int>
     */
    private function currentRawFrameIndexes(): array
    {
        [$start, $end] = $this->currentFrameRange();
        if ($start > $end) {
            return [];
        }

        return range($start, $end);
    }

    private static function assertFrameBoundary(string $boundary, bool $isStart): void
    {
        $boundary = strtoupper(trim($boundary));
        $allowed = $isStart
            ? ['UNBOUNDED PRECEDING', 'PRECEDING', 'CURRENT ROW', 'FOLLOWING']
            : ['PRECEDING', 'CURRENT ROW', 'FOLLOWING', 'UNBOUNDED FOLLOWING'];
        if (!in_array($boundary, $allowed, true)) {
            throw new \InvalidArgumentException('SQLite VDBE window aggregate frame boundary is not supported');
        }
    }

    private function rowsBoundaryIndex(string $boundary, int|float $offset, int $partitionStart, int $partitionEnd, bool $isStart): int
    {
        return match (strtoupper(trim($boundary))) {
            'UNBOUNDED PRECEDING' => $partitionStart,
            'UNBOUNDED FOLLOWING' => $partitionEnd,
            'CURRENT ROW' => $this->position,
            'PRECEDING' => $this->position - (int) $offset,
            'FOLLOWING' => $this->position + (int) $offset,
            default => $isStart ? $partitionEnd + 1 : $partitionStart - 1,
        };
    }

    private function groupBoundaryIndex(int $currentGroup, int $groupCount, string $boundary, int $offset, bool $isStart): int
    {
        return match (strtoupper(trim($boundary))) {
            'UNBOUNDED PRECEDING' => 0,
            'UNBOUNDED FOLLOWING' => $groupCount - 1,
            'CURRENT ROW' => $currentGroup,
            'PRECEDING' => $currentGroup - $offset,
            'FOLLOWING' => $currentGroup + $offset,
            default => $isStart ? $groupCount : -1,
        };
    }

    private function rangeLowerLimit(float|int $current, string $boundary, float $offset, bool $descending): float
    {
        return match ($boundary) {
            'UNBOUNDED PRECEDING' => $descending ? INF : -INF,
            'CURRENT ROW' => (float) $current,
            'PRECEDING' => $descending ? (float) $current + $offset : (float) $current - $offset,
            'FOLLOWING' => $descending ? (float) $current - $offset : (float) $current + $offset,
            default => INF,
        };
    }

    private function rangeUpperLimit(float|int $current, string $boundary, float $offset, bool $descending): float
    {
        return match ($boundary) {
            'UNBOUNDED FOLLOWING' => $descending ? -INF : INF,
            'CURRENT ROW' => (float) $current,
            'PRECEDING' => $descending ? (float) $current + $offset : (float) $current - $offset,
            'FOLLOWING' => $descending ? (float) $current - $offset : (float) $current + $offset,
            default => -INF,
        };
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

    /**
     * @return array{position:int,row:array<string,mixed>,frameRowids:list<mixed>,filteredFrameRowids:list<mixed>,countAll:int,countValue:int,sum:int|float|null,total:float,avg:float|null,min:mixed,max:mixed,groupConcat:?string,firstValue:mixed,lastValue:mixed,nthValue:mixed}
     */
    private function aggregateSummaryAt(int $position, string $rowidColumn, mixed $separator, int $nth, bool $valueFunctionsApplyFilter): array
    {
        return $this->withPosition($position, function () use ($rowidColumn, $separator, $nth, $valueFunctionsApplyFilter): array {
            $frameRows = $this->currentFrameRows(false);
            $filteredRows = $this->currentFrameRows(true);
            $values = array_map(fn (array $row): mixed => $row[$this->valueColumn], $filteredRows);

            return [
                'position' => $this->position,
                'row' => $this->requireCurrentRow(),
                'frameRowids' => array_map(static fn (array $row): mixed => $row[$rowidColumn] ?? null, $frameRows),
                'filteredFrameRowids' => array_map(static fn (array $row): mixed => $row[$rowidColumn] ?? null, $filteredRows),
                'countAll' => SQLiteNumericAggregate::countAll($frameRows),
                'countValue' => SQLiteNumericAggregate::countValue($values),
                'sum' => SQLiteNumericAggregate::sum($values),
                'total' => SQLiteNumericAggregate::total($values),
                'avg' => SQLiteNumericAggregate::avg($values),
                'min' => SQLiteNumericAggregate::min($values),
                'max' => SQLiteNumericAggregate::max($values),
                'groupConcat' => SQLiteTextAggregate::groupConcat($values, $separator),
                'firstValue' => $this->firstValue($valueFunctionsApplyFilter),
                'lastValue' => $this->lastValue($valueFunctionsApplyFilter),
                'nthValue' => $this->nthValue($nth, $valueFunctionsApplyFilter),
            ];
        });
    }

    /**
     * @param non-empty-list<string> $aggregateOrderColumns
     * @param list<string>|string $aggregateOrderAffinities
     * @param list<string> $aggregateOrderCollations
     * @param list<bool> $aggregateOrderDescending
     * @param list<string|null> $aggregateOrderNulls
     * @return array{position:int,row:array<string,mixed>,frameRowids:list<mixed>,orderedFrameRowids:list<mixed>,orderedValues:list<mixed>,countValue:int,sum:int|float|null,total:float,avg:float|null,min:mixed,max:mixed,groupConcat:?string}
     */
    private function orderedAggregateSummaryAt(
        int $position,
        array $aggregateOrderColumns,
        string $rowidColumn,
        mixed $separator,
        array|string $aggregateOrderAffinities,
        array $aggregateOrderCollations,
        array $aggregateOrderDescending,
        array $aggregateOrderNulls,
    ): array {
        return $this->withPosition($position, function () use ($aggregateOrderColumns, $rowidColumn, $separator, $aggregateOrderAffinities, $aggregateOrderCollations, $aggregateOrderDescending, $aggregateOrderNulls): array {
            $frameRows = $this->currentFrameRows(true);
            foreach ($frameRows as $row) {
                foreach ($aggregateOrderColumns as $column) {
                    if (!array_key_exists($column, $row)) {
                        throw new \InvalidArgumentException("SQLite VDBE window aggregate row is missing aggregate order column {$column}");
                    }
                    self::assertScalar($row[$column]);
                }
            }

            $orderedRows = $frameRows === []
                ? []
                : SQLiteVdbeSortCompare::sortRows(
                    $frameRows,
                    $aggregateOrderColumns,
                    $aggregateOrderAffinities,
                    $aggregateOrderCollations,
                    $aggregateOrderDescending,
                    $aggregateOrderNulls
                );
            $values = array_map(fn (array $row): mixed => $row[$this->valueColumn], $orderedRows);

            return [
                'position' => $this->position,
                'row' => $this->requireCurrentRow(),
                'frameRowids' => array_map(static fn (array $row): mixed => $row[$rowidColumn] ?? null, $frameRows),
                'orderedFrameRowids' => array_map(static fn (array $row): mixed => $row[$rowidColumn] ?? null, $orderedRows),
                'orderedValues' => $values,
                'countValue' => SQLiteNumericAggregate::countValue($values),
                'sum' => SQLiteNumericAggregate::sum($values),
                'total' => SQLiteNumericAggregate::total($values),
                'avg' => SQLiteNumericAggregate::avg($values),
                'min' => SQLiteNumericAggregate::min($values),
                'max' => SQLiteNumericAggregate::max($values),
                'groupConcat' => SQLiteTextAggregate::groupConcat($values, $separator),
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

    private static function isNumericRangeValue(mixed $value): bool
    {
        return is_bool($value) || is_int($value) || is_float($value);
    }

    /**
     * @param list<int> $indexes
     * @return list<mixed>
     */
    private function rowidsForIndexes(array $indexes, string $rowidColumn): array
    {
        return array_map(fn (int $index): mixed => $this->orderedRows[$index][$rowidColumn] ?? null, $indexes);
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
