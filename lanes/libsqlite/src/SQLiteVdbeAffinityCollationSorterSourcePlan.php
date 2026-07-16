<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeAffinityCollationSorterSourcePlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param non-empty-list<string> $columns
     * @param non-empty-string $rowIdColumn
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     * @param list<string|null> $nulls
     * @return array{
     *     currentOrder:list<mixed>,
     *     nextOrder:list<mixed>,
     *     inserted:list<mixed>,
     *     deleted:list<mixed>,
     *     moved:list<array{id:mixed,from:int,to:int}>,
     *     stableTieIds:list<mixed>,
     *     changed:bool,
     *     currentTrace:list<array<string,mixed>>,
     *     nextTrace:list<array<string,mixed>>,
     *     dependencies:list<string>
     * }
     */
    public static function compareSources(
        array $currentRows,
        array $nextRows,
        array $columns,
        string $rowIdColumn,
        array|string $affinities = [],
        array $collations = [],
        array $descending = [],
        array $nulls = []
    ): array {
        if ($rowIdColumn === '') {
            throw new \InvalidArgumentException('SQLite VDBE sorter source row id column must be non-empty');
        }

        $currentTrace = self::trace($currentRows, $columns, $rowIdColumn, $affinities, $collations, $descending, $nulls);
        $nextTrace = self::trace($nextRows, $columns, $rowIdColumn, $affinities, $collations, $descending, $nulls);
        $currentOrder = array_map(static fn (array $entry): mixed => $entry['id'], $currentTrace);
        $nextOrder = array_map(static fn (array $entry): mixed => $entry['id'], $nextTrace);

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

        $stableTieIds = [];
        foreach ($nextTrace as $entry) {
            if (($entry['stableTie'] ?? false) === true) {
                $stableTieIds[] = $entry['id'];
            }
        }

        return [
            'currentOrder' => $currentOrder,
            'nextOrder' => $nextOrder,
            'inserted' => $inserted,
            'deleted' => $deleted,
            'moved' => $moved,
            'stableTieIds' => $stableTieIds,
            'changed' => $currentOrder !== $nextOrder,
            'currentTrace' => $currentTrace,
            'nextTrace' => $nextTrace,
            'dependencies' => [
                'sqlite-vdbe-sorter-yield',
                'sqlite-affinity-comparison',
                'sqlite-collation-sequence',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param non-empty-list<string> $columns
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     * @param list<string|null> $nulls
     * @return list<array<string,mixed>>
     */
    private static function trace(
        array $rows,
        array $columns,
        string $rowIdColumn,
        array|string $affinities,
        array $collations,
        array $descending,
        array $nulls
    ): array {
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite VDBE sorter source row is missing id column {$rowIdColumn}");
            }
        }

        $cursor = new SQLiteVdbeSorterYieldCursor($rows, $columns, $affinities, $collations, $descending, $nulls);
        $trace = [];
        while (!$cursor->eof()) {
            $row = $cursor->current();
            $summary = $cursor->currentSummary();
            $trace[] = $summary + [
                'id' => $row[$rowIdColumn],
                'row' => $row,
                'steps' => $cursor->comparisonStepsFromPrevious(),
            ];
            $cursor->next();
        }

        return $trace;
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
