<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVdbeSortCompare
{
    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     * @param list<string|null> $nulls
     */
    public static function compareRecords(
        array $left,
        array $right,
        array|string $affinities = [],
        array $collations = [],
        array $descending = [],
        array $nulls = []
    ): int {
        if (!array_is_list($left) || !array_is_list($right)) {
            throw new \InvalidArgumentException('SQLite VDBE comparison records must be lists');
        }
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('SQLite VDBE comparison records must have the same number of fields');
        }

        foreach (self::comparisonSteps($left, $right, $affinities, $collations, $descending, $nulls) as $step) {
            if ($step['result'] !== 0) {
                return $step['result'];
            }
        }

        return 0;
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     * @param list<string|null> $nulls
     * @return list<array{
     *     index:int,
     *     affinity:string,
     *     collation:string,
     *     descending:bool,
     *     nulls:string|null,
     *     left:mixed,
     *     right:mixed,
     *     leftStorageClass:string,
     *     rightStorageClass:string,
     *     comparison:int,
     *     result:int,
     *     decided:bool
     * }>
     */
    public static function comparisonSteps(
        array $left,
        array $right,
        array|string $affinities = [],
        array $collations = [],
        array $descending = [],
        array $nulls = []
    ): array {
        if (!array_is_list($left) || !array_is_list($right)) {
            throw new \InvalidArgumentException('SQLite VDBE comparison records must be lists');
        }
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('SQLite VDBE comparison records must have the same number of fields');
        }

        $affinityList = self::affinityList($affinities, count($left));
        $steps = [];
        foreach ($left as $index => $leftValue) {
            $collation = strtoupper($collations[$index] ?? 'BINARY');
            [$leftValue, $rightValue] = self::applySlotAffinity($leftValue, $right[$index], $affinityList[$index]);
            $comparison = SQLiteAffinityComparison::compare(
                $leftValue,
                $rightValue,
                'NONE',
                'NONE',
                $collation
            );
            $explicitNullComparison = self::compareExplicitNulls($leftValue, $rightValue, $nulls[$index] ?? null);
            $explicitNullPlacement = $explicitNullComparison !== null;
            if ($explicitNullComparison !== null) {
                $comparison = $explicitNullComparison;
            }
            $comparison ??= self::compareNulls($leftValue, $rightValue);
            $result = $comparison === 0 || $explicitNullPlacement ? $comparison : (($descending[$index] ?? false) ? -$comparison : $comparison);
            $steps[] = [
                'index' => $index,
                'affinity' => $affinityList[$index],
                'collation' => $collation,
                'descending' => $descending[$index] ?? false,
                'nulls' => $nulls[$index] ?? null,
                'left' => $leftValue,
                'right' => $rightValue,
                'leftStorageClass' => SQLiteAffinityComparison::storageClass($leftValue),
                'rightStorageClass' => SQLiteAffinityComparison::storageClass($rightValue),
                'comparison' => $comparison,
                'result' => $result,
                'decided' => $result !== 0,
            ];
            if ($result !== 0) {
                break;
            }
        }

        return $steps;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     * @param list<string|null> $nulls
     * @return list<array<string,mixed>>
     */
    public static function sortRows(
        array $rows,
        array $columns,
        array|string $affinities = [],
        array $collations = [],
        array $descending = [],
        array $nulls = []
    ): array {
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException('SQLite VDBE sort columns must be a non-empty list');
        }

        $ordered = [];
        foreach ($rows as $index => $row) {
            $record = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite VDBE sort row is missing column {$column}");
                }
                $record[] = $row[$column];
            }
            $ordered[] = [$row, $record, $index];
        }

        usort($ordered, static function (array $left, array $right) use ($affinities, $collations, $descending, $nulls): int {
            $comparison = self::compareRecords($left[1], $right[1], $affinities, $collations, $descending, $nulls);

            return $comparison !== 0 ? $comparison : $left[2] <=> $right[2];
        });

        return array_column($ordered, 0);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     * @param list<string|null> $nulls
     * @return list<array{
     *     row:array<string,mixed>,
     *     record:list<mixed>,
     *     sequence:int,
     *     previousSequence:int|null,
     *     comparison:int|null,
     *     stableTie:bool,
     *     steps:list<array<string,mixed>>
     * }>
     */
    public static function sortedRowTrace(
        array $rows,
        array $columns,
        array|string $affinities = [],
        array $collations = [],
        array $descending = [],
        array $nulls = []
    ): array {
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException('SQLite VDBE sort columns must be a non-empty list');
        }

        $entries = [];
        foreach ($rows as $sequence => $row) {
            $record = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite VDBE sort row is missing column {$column}");
                }
                $record[] = $row[$column];
            }
            $entries[] = [
                'row' => $row,
                'record' => $record,
                'sequence' => $sequence,
                'previousSequence' => null,
                'comparison' => null,
                'stableTie' => false,
                'steps' => [],
            ];
        }

        usort($entries, static function (array $left, array $right) use ($affinities, $collations, $descending, $nulls): int {
            $comparison = self::compareRecords($left['record'], $right['record'], $affinities, $collations, $descending, $nulls);

            return $comparison !== 0 ? $comparison : $left['sequence'] <=> $right['sequence'];
        });

        $ordered = $entries;
        foreach ($ordered as $index => $entry) {
            if ($index === 0) {
                continue;
            }
            $previous = $ordered[$index - 1];
            $steps = self::comparisonSteps($previous['record'], $entry['record'], $affinities, $collations, $descending, $nulls);
            $comparison = self::compareRecords($previous['record'], $entry['record'], $affinities, $collations, $descending, $nulls);
            $ordered[$index]['previousSequence'] = $previous['sequence'];
            $ordered[$index]['comparison'] = $comparison;
            $ordered[$index]['stableTie'] = $comparison === 0 && $previous['sequence'] < $entry['sequence'];
            $ordered[$index]['steps'] = $steps;
        }

        return $ordered;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     * @param list<string|null> $nulls
     */
    public static function cursor(
        array $rows,
        array $columns,
        array|string $affinities = [],
        array $collations = [],
        array $descending = [],
        array $nulls = []
    ): SQLiteVdbeSorterCursor {
        return new SQLiteVdbeSorterCursor(self::sortRows($rows, $columns, $affinities, $collations, $descending, $nulls));
    }

    /**
     * @param list<string>|string $affinities
     * @return list<string>
     */
    private static function affinityList(array|string $affinities, int $count): array
    {
        if (is_string($affinities)) {
            $mapped = [];
            for ($i = 0; $i < $count; $i++) {
                $mapped[] = self::affinityCode($affinities[$i] ?? '');
            }

            return $mapped;
        }

        if (!array_is_list($affinities)) {
            throw new \InvalidArgumentException('SQLite VDBE comparison affinities must be a list or affinity string');
        }

        $mapped = [];
        for ($i = 0; $i < $count; $i++) {
            $mapped[] = $affinities[$i] ?? 'NONE';
        }

        return $mapped;
    }

    private static function affinityCode(string $code): string
    {
        return match ($code) {
            'A', 'B', '' => 'NONE',
            'C' => 'NUMERIC',
            'D' => 'INTEGER',
            'E', 'F' => 'REAL',
            'G' => 'TEXT',
            default => throw new \InvalidArgumentException("SQLite VDBE affinity code {$code} is not supported"),
        };
    }

    /**
     * @return array{0:mixed,1:mixed}
     */
    private static function applySlotAffinity(mixed $left, mixed $right, string $affinity): array
    {
        $pair = SQLiteAffinityComparison::coercedPair($left, $right, $affinity, 'NONE');
        $pair = SQLiteAffinityComparison::coercedPair($pair['left'], $pair['right'], 'NONE', $affinity);

        return [$pair['left'], $pair['right']];
    }

    private static function compareNulls(mixed $left, mixed $right): int
    {
        if ($left === null && $right === null) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }

        throw new \InvalidArgumentException('SQLite VDBE NULL comparison called for non-NULL values');
    }

    private static function compareExplicitNulls(mixed $left, mixed $right, ?string $placement): ?int
    {
        if ($left !== null && $right !== null) {
            return null;
        }
        if ($placement === null || $placement === '') {
            return null;
        }

        $placement = strtoupper($placement);
        if ($placement !== 'FIRST' && $placement !== 'LAST') {
            throw new \InvalidArgumentException('SQLite VDBE NULL placement must be FIRST, LAST, or NULL');
        }
        if ($left === null && $right === null) {
            return 0;
        }

        return $left === null
            ? ($placement === 'FIRST' ? -1 : 1)
            : ($placement === 'FIRST' ? 1 : -1);
    }
}
