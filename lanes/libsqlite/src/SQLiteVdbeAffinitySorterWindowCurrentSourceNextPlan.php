<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeAffinitySorterWindowCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<string> $partitionColumns
     * @param non-empty-list<string> $orderColumns
     * @param list<string>|string $partitionAffinities
     * @param list<string> $partitionCollations
     * @param list<string>|string $orderAffinities
     * @param list<string> $orderCollations
     * @param list<bool> $orderDescending
     * @param list<string|null> $orderNulls
     * @return array{
     *     status:string,
     *     sourceToken:string,
     *     currentSource:array<string,mixed>,
     *     nextSource:array<string,mixed>,
     *     inserted:list<mixed>,
     *     deleted:list<mixed>,
     *     moved:list<array{id:mixed,from:int,to:int}>,
     *     currentOrder:list<mixed>,
     *     nextOrder:list<mixed>,
     *     currentWindows:list<array<string,mixed>>,
     *     nextWindows:list<array<string,mixed>>,
     *     changed:bool,
     *     dependencies:list<string>
     * }
     */
    public static function compareWindowSources(
        array $currentRows,
        array $nextRows,
        string $rowIdColumn,
        string $valueColumn,
        array $partitionColumns,
        array $orderColumns,
        ?string $filterColumn = null,
        int|float $preceding = 0,
        int|float $following = 0,
        array|string $partitionAffinities = [],
        array $partitionCollations = [],
        array|string $orderAffinities = [],
        array $orderCollations = [],
        array $orderDescending = [],
        array $orderNulls = [],
        string $frameUnit = 'ROWS',
        string $excludeMode = 'NO OTHERS',
        string $sourceToken = 'vdbe-affinity-sorter-window-current-source-next'
    ): array {
        if ($rowIdColumn === '') {
            throw new \InvalidArgumentException('SQLite VDBE affinity sorter window next row id column must be non-empty');
        }
        if ($valueColumn === '') {
            throw new \InvalidArgumentException('SQLite VDBE affinity sorter window next value column must be non-empty');
        }
        if ($sourceToken === '') {
            throw new \InvalidArgumentException('SQLite VDBE affinity sorter window next source token must be non-empty');
        }

        $currentWindows = self::windowSummaries($currentRows, $rowIdColumn, $valueColumn, $partitionColumns, $orderColumns, $filterColumn, $preceding, $following, $partitionAffinities, $partitionCollations, $orderAffinities, $orderCollations, $orderDescending, $orderNulls, $frameUnit, $excludeMode);
        $nextWindows = self::windowSummaries($nextRows, $rowIdColumn, $valueColumn, $partitionColumns, $orderColumns, $filterColumn, $preceding, $following, $partitionAffinities, $partitionCollations, $orderAffinities, $orderCollations, $orderDescending, $orderNulls, $frameUnit, $excludeMode);
        $currentOrder = array_map(static fn (array $summary): mixed => $summary['rowid'], $currentWindows);
        $nextOrder = array_map(static fn (array $summary): mixed => $summary['rowid'], $nextWindows);
        $currentPositions = self::positions($currentOrder);
        $nextPositions = self::positions($nextOrder);

        $inserted = [];
        foreach ($nextOrder as $id) {
            if (!array_key_exists(self::idKey($id), $currentPositions)) {
                $inserted[] = $id;
            }
        }

        $deleted = [];
        foreach ($currentOrder as $id) {
            if (!array_key_exists(self::idKey($id), $nextPositions)) {
                $deleted[] = $id;
            }
        }

        $moved = [];
        foreach ($nextOrder as $id) {
            $key = self::idKey($id);
            if (array_key_exists($key, $currentPositions) && $currentPositions[$key] !== $nextPositions[$key]) {
                $moved[] = ['id' => $id, 'from' => $currentPositions[$key], 'to' => $nextPositions[$key]];
            }
        }

        return [
            'status' => 'vdbe-affinity-sorter-window-current-source-next-ready',
            'sourceToken' => $sourceToken,
            'currentSource' => [
                'rowCount' => count($currentRows),
                'windowCount' => count($currentWindows),
                'source' => 'current',
            ],
            'nextSource' => [
                'rowCount' => count($nextRows),
                'windowCount' => count($nextWindows),
                'source' => 'next',
            ],
            'inserted' => $inserted,
            'deleted' => $deleted,
            'moved' => $moved,
            'currentOrder' => $currentOrder,
            'nextOrder' => $nextOrder,
            'currentWindows' => $currentWindows,
            'nextWindows' => $nextWindows,
            'changed' => $currentOrder !== $nextOrder || $inserted !== [] || $deleted !== [],
            'dependencies' => [
                'sqlite-vdbe-sorter-yield',
                'sqlite-vdbe-window-current-next',
                'sqlite-affinity-comparison',
                'sqlite-collation-sequence',
            ],
        ];
    }

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
     * @return list<array<string,mixed>>
     */
    private static function windowSummaries(
        array $rows,
        string $rowIdColumn,
        string $valueColumn,
        array $partitionColumns,
        array $orderColumns,
        ?string $filterColumn,
        int|float $preceding,
        int|float $following,
        array|string $partitionAffinities,
        array $partitionCollations,
        array|string $orderAffinities,
        array $orderCollations,
        array $orderDescending,
        array $orderNulls,
        string $frameUnit,
        string $excludeMode
    ): array {
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite VDBE affinity sorter window next row is missing id column {$rowIdColumn}");
            }
        }

        $cursor = new SQLiteVdbeWindowAggregateCursor(
            $rows,
            $valueColumn,
            $partitionColumns,
            $orderColumns,
            $filterColumn,
            $preceding,
            $following,
            $partitionAffinities,
            $partitionCollations,
            $orderAffinities,
            $orderCollations,
            $orderDescending,
            $orderNulls,
            $frameUnit,
            $excludeMode
        );

        $summaries = [];
        while (!$cursor->eof()) {
            $row = $cursor->currentRow();
            $summary = $cursor->currentNextAggregateSummary($rowIdColumn, '|', 2, false);
            $current = $summary['current'];
            $next = $summary['next'];
            $summaries[] = [
                'rowid' => $row[$rowIdColumn],
                'row' => $row,
                'position' => $current['position'],
                'partitionKey' => $cursor->currentPartitionKey(),
                'orderKey' => $cursor->currentOrderKey(),
                'nextRowid' => $next['row'][$rowIdColumn] ?? null,
                'nextPartitionKey' => $cursor->peekNextPartitionKey(),
                'nextOrderKey' => $cursor->peekNextOrderKey(),
                'frameRowids' => $current['frameRowids'],
                'filteredFrameRowids' => $current['filteredFrameRowids'],
                'countAll' => $current['countAll'],
                'countValue' => $current['countValue'],
                'sum' => $current['sum'],
                'total' => $current['total'],
                'groupConcat' => $current['groupConcat'],
                'firstValue' => $current['firstValue'],
                'lastValue' => $current['lastValue'],
                'nthValue' => $current['nthValue'],
                'currentFilterPassed' => $cursor->currentFilterPassed(),
            ];
            $cursor->next();
        }

        return $summaries;
    }

    /**
     * @param list<mixed> $ids
     * @return array<string,int>
     */
    private static function positions(array $ids): array
    {
        $positions = [];
        foreach ($ids as $position => $id) {
            $positions[self::idKey($id)] = $position;
        }

        return $positions;
    }

    private static function idKey(mixed $id): string
    {
        return get_debug_type($id) . ':' . (string) $id;
    }
}
