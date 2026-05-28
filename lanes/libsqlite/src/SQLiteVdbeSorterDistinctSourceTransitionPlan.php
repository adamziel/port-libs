<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeSorterDistinctSourceTransitionPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param non-empty-list<string>|string $distinctColumns
     * @param non-empty-string $valueColumn
     * @param non-empty-string $rowIdColumn
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @return array{
     *     currentValues:list<mixed>,
     *     nextValues:list<mixed>,
     *     retained:list<mixed>,
     *     inserted:list<mixed>,
     *     deleted:list<mixed>,
     *     moved:list<array{id:mixed,from:int,to:int}>,
     *     changedRepresentatives:list<array{current:mixed,next:mixed,currentKey:list<mixed>,nextKey:list<mixed>}>,
     *     currentDuplicateSkips:list<array{representative:mixed,skipped:list<mixed>,key:list<mixed>}>,
     *     nextDuplicateSkips:list<array{representative:mixed,skipped:list<mixed>,key:list<mixed>}>,
     *     currentDistinct:list<array<string,mixed>>,
     *     nextDistinct:list<array<string,mixed>>,
     *     changed:bool,
     *     dependencies:list<string>
     * }
     */
    public static function plan(
        array $currentRows,
        array $nextRows,
        array|string $distinctColumns,
        string $valueColumn,
        string $rowIdColumn,
        ?string $filterColumn = null,
        array|string $affinities = [],
        array $collations = [],
    ): array {
        if ($valueColumn === '') {
            throw new \InvalidArgumentException('SQLite VDBE sorter DISTINCT transition value column must be non-empty');
        }
        if ($rowIdColumn === '') {
            throw new \InvalidArgumentException('SQLite VDBE sorter DISTINCT transition row id column must be non-empty');
        }

        $columns = self::distinctColumns($distinctColumns);
        $currentDistinct = self::distinctEntries($currentRows, $columns, $valueColumn, $rowIdColumn, $filterColumn, $affinities, $collations);
        $nextDistinct = self::distinctEntries($nextRows, $columns, $valueColumn, $rowIdColumn, $filterColumn, $affinities, $collations);

        $currentValues = array_map(static fn (array $entry): mixed => $entry['value'], $currentDistinct['entries']);
        $nextValues = array_map(static fn (array $entry): mixed => $entry['value'], $nextDistinct['entries']);
        $matches = self::matchEntries($currentDistinct['entries'], $nextDistinct['entries'], $affinities, $collations);

        $retained = [];
        $deleted = [];
        $moved = [];
        $changedRepresentatives = [];
        $matchedNext = [];
        foreach ($currentDistinct['entries'] as $currentIndex => $current) {
            $nextIndex = $matches[$currentIndex] ?? null;
            if ($nextIndex === null) {
                $deleted[] = $current['value'];
                continue;
            }

            $next = $nextDistinct['entries'][$nextIndex];
            $matchedNext[$nextIndex] = true;
            $retained[] = $next['value'];
            if ($currentIndex !== $nextIndex) {
                $moved[] = ['id' => $next['value'], 'from' => $currentIndex, 'to' => $nextIndex];
            }
            if ($current['value'] !== $next['value']) {
                $changedRepresentatives[] = [
                    'current' => $current['value'],
                    'next' => $next['value'],
                    'currentKey' => $current['key'],
                    'nextKey' => $next['key'],
                ];
            }
        }

        $inserted = [];
        foreach ($nextDistinct['entries'] as $nextIndex => $next) {
            if (!isset($matchedNext[$nextIndex])) {
                $inserted[] = $next['value'];
            }
        }

        return [
            'currentValues' => $currentValues,
            'nextValues' => $nextValues,
            'retained' => $retained,
            'inserted' => $inserted,
            'deleted' => $deleted,
            'moved' => $moved,
            'changedRepresentatives' => $changedRepresentatives,
            'currentDuplicateSkips' => $currentDistinct['duplicateSkips'],
            'nextDuplicateSkips' => $nextDistinct['duplicateSkips'],
            'currentDistinct' => $currentDistinct['entries'],
            'nextDistinct' => $nextDistinct['entries'],
            'changed' => $currentValues !== $nextValues,
            'dependencies' => [
                'sqlite-vdbe-sorter-distinct',
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
     * @return array{
     *     entries:list<array{key:list<mixed>,value:mixed,id:mixed,row:array<string,mixed>,sequence:int,skipped:list<mixed>}>,
     *     duplicateSkips:list<array{representative:mixed,skipped:list<mixed>,key:list<mixed>}>
     * }
     */
    private static function distinctEntries(
        array $rows,
        array $columns,
        string $valueColumn,
        string $rowIdColumn,
        ?string $filterColumn,
        array|string $affinities,
        array $collations
    ): array {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite VDBE sorter DISTINCT transition rows must be a list');
        }

        $entries = [];
        foreach ($rows as $sequence => $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite VDBE sorter DISTINCT transition row is missing id column {$rowIdColumn}");
            }
            if ($filterColumn !== null) {
                if (!array_key_exists($filterColumn, $row)) {
                    throw new \InvalidArgumentException("SQLite VDBE sorter DISTINCT transition row is missing filter column {$filterColumn}");
                }
                if (!self::isSqlTrue($row[$filterColumn])) {
                    continue;
                }
            }
            if (!array_key_exists($valueColumn, $row)) {
                throw new \InvalidArgumentException("SQLite VDBE sorter DISTINCT transition row is missing value column {$valueColumn}");
            }

            $key = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite VDBE sorter DISTINCT transition row is missing key column {$column}");
                }
                $key[] = $row[$column];
            }
            SQLiteVdbeSortCompare::compareRecords($key, $key, $affinities, $collations);

            $entries[] = [
                'key' => $key,
                'value' => $row[$valueColumn],
                'id' => $row[$rowIdColumn],
                'row' => $row,
                'sequence' => $sequence,
                'skipped' => [],
            ];
        }

        usort($entries, static function (array $left, array $right) use ($affinities, $collations): int {
            $comparison = SQLiteVdbeSortCompare::compareRecords($left['key'], $right['key'], $affinities, $collations);

            return $comparison !== 0 ? $comparison : ($left['sequence'] <=> $right['sequence']);
        });

        $distinct = [];
        foreach ($entries as $entry) {
            $lastIndex = array_key_last($distinct);
            $last = $lastIndex === null ? null : $distinct[$lastIndex];
            if ($last !== null && SQLiteVdbeSortCompare::compareRecords($last['key'], $entry['key'], $affinities, $collations) === 0) {
                $distinct[$lastIndex]['skipped'][] = $entry['id'];
                continue;
            }

            $distinct[] = $entry;
        }

        $duplicateSkips = [];
        foreach ($distinct as $entry) {
            if ($entry['skipped'] !== []) {
                $duplicateSkips[] = [
                    'representative' => $entry['id'],
                    'skipped' => $entry['skipped'],
                    'key' => $entry['key'],
                ];
            }
        }

        return ['entries' => $distinct, 'duplicateSkips' => $duplicateSkips];
    }

    /**
     * @param list<array{key:list<mixed>}> $current
     * @param list<array{key:list<mixed>}> $next
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @return array<int,int>
     */
    private static function matchEntries(array $current, array $next, array|string $affinities, array $collations): array
    {
        $matches = [];
        $usedNext = [];
        foreach ($current as $currentIndex => $currentEntry) {
            foreach ($next as $nextIndex => $nextEntry) {
                if (isset($usedNext[$nextIndex])) {
                    continue;
                }
                if (SQLiteVdbeSortCompare::compareRecords($currentEntry['key'], $nextEntry['key'], $affinities, $collations) === 0) {
                    $matches[$currentIndex] = $nextIndex;
                    $usedNext[$nextIndex] = true;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * @return non-empty-list<string>
     */
    private static function distinctColumns(array|string $columns): array
    {
        if (is_string($columns)) {
            if ($columns === '') {
                throw new \InvalidArgumentException('SQLite VDBE sorter DISTINCT transition key column must be non-empty');
            }

            return [$columns];
        }
        if (!array_is_list($columns) || $columns === []) {
            throw new \InvalidArgumentException('SQLite VDBE sorter DISTINCT transition key columns must be a non-empty list');
        }

        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite VDBE sorter DISTINCT transition key columns must be non-empty strings');
            }
        }

        return $columns;
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
