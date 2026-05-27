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
     */
    public static function compareRecords(
        array $left,
        array $right,
        array|string $affinities = [],
        array $collations = [],
        array $descending = []
    ): int {
        if (!array_is_list($left) || !array_is_list($right)) {
            throw new \InvalidArgumentException('SQLite VDBE comparison records must be lists');
        }
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('SQLite VDBE comparison records must have the same number of fields');
        }

        $affinityList = self::affinityList($affinities, count($left));
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
            $comparison ??= self::compareNulls($leftValue, $right[$index]);
            if ($comparison !== 0) {
                return ($descending[$index] ?? false) ? -$comparison : $comparison;
            }
        }

        return 0;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @param list<string>|string $affinities
     * @param list<string> $collations
     * @param list<bool> $descending
     * @return list<array<string,mixed>>
     */
    public static function sortRows(
        array $rows,
        array $columns,
        array|string $affinities = [],
        array $collations = [],
        array $descending = []
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

        usort($ordered, static function (array $left, array $right) use ($affinities, $collations, $descending): int {
            $comparison = self::compareRecords($left[1], $right[1], $affinities, $collations, $descending);

            return $comparison !== 0 ? $comparison : $left[2] <=> $right[2];
        });

        return array_column($ordered, 0);
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
}
