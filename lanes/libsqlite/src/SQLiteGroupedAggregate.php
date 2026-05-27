<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteGroupedAggregate
{
    /**
     * @param iterable<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function summarize(iterable $rows, string|array $groupColumn, string $valueColumn): array
    {
        $groupColumns = self::groupColumns($groupColumn);
        $groups = [];
        foreach ($rows as $row) {
            $groupValues = [];
            foreach ($groupColumns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite GROUP BY row is missing column {$column}");
                }
                $groupValues[$column] = $row[$column];
            }
            if (!array_key_exists($valueColumn, $row)) {
                throw new \InvalidArgumentException("SQLite aggregate row is missing column {$valueColumn}");
            }

            $key = self::compositeGroupKey($groupValues);
            $groups[$key] ??= [
                'group' => $groupValues[$groupColumns[0]],
                'groupValues' => $groupValues,
                'rows' => [],
                'values' => [],
            ];
            $groups[$key]['rows'][] = $row;
            $groups[$key]['values'][] = $row[$valueColumn];
        }

        $summaries = [];
        foreach ($groups as $group) {
            $values = $group['values'];
            $summary = [
                'group' => $group['group'],
                'countAll' => SQLiteNumericAggregate::countAll($group['rows']),
                'countValue' => SQLiteNumericAggregate::countValue($values),
                'countDistinct' => SQLiteNumericAggregate::countDistinct($values),
                'sum' => SQLiteNumericAggregate::sum($values),
                'total' => SQLiteNumericAggregate::total($values),
                'avg' => SQLiteNumericAggregate::avg($values),
                'min' => SQLiteNumericAggregate::min($values),
                'max' => SQLiteNumericAggregate::max($values),
                'groupConcat' => SQLiteTextAggregate::groupConcat($values, '|'),
            ];
            foreach ($group['groupValues'] as $column => $value) {
                $summary[$column] = $value;
            }
            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * @param list<array<string,mixed>> $summaries
     * @return list<array<string,mixed>>
     */
    public static function havingCountAtLeast(array $summaries, int $minimum): array
    {
        return array_values(array_filter(
            $summaries,
            static fn (array $summary): bool => (int) ($summary['countAll'] ?? 0) >= $minimum
        ));
    }

    /**
     * @param list<array<string,mixed>> $summaries
     * @return list<array<string,mixed>>
     */
    public static function havingSumGreaterThan(array $summaries, int|float $threshold): array
    {
        return array_values(array_filter(
            $summaries,
            static fn (array $summary): bool => $summary['sum'] !== null && (float) $summary['sum'] > (float) $threshold
        ));
    }

    /**
     * @param list<array<string,mixed>> $summaries
     * @return list<array<string,mixed>>
     */
    public static function orderBy(array $summaries, string $column, string $direction = 'ASC'): array
    {
        $direction = strtoupper($direction);
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \InvalidArgumentException('SQLite grouped aggregate ORDER BY direction must be ASC or DESC');
        }

        foreach ($summaries as $summary) {
            if (!array_key_exists($column, $summary)) {
                throw new \InvalidArgumentException("SQLite grouped aggregate ORDER BY column is missing: {$column}");
            }
        }

        $ordered = [];
        foreach ($summaries as $index => $summary) {
            $ordered[] = [$summary, $index];
        }

        usort($ordered, static function (array $left, array $right) use ($column, $direction): int {
            $comparison = self::compareSqlValues($left[0][$column], $right[0][$column]);
            if ($comparison === 0) {
                $comparison = $left[1] <=> $right[1];
            }

            return $direction === 'DESC' ? -$comparison : $comparison;
        });

        return array_column($ordered, 0);
    }

    private static function groupKey(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . $value->bytes;
        }
        if ($value === null) {
            return 'null:';
        }
        if (is_bool($value) || is_int($value)) {
            return 'integer:' . (int) $value;
        }
        if (is_float($value)) {
            return 'real:' . sprintf('%.17G', $value);
        }
        if (is_string($value)) {
            return 'text:' . $value;
        }

        throw new \InvalidArgumentException('SQLite GROUP BY values must be scalar, BLOB, or NULL');
    }

    /**
     * @return non-empty-list<string>
     */
    private static function groupColumns(string|array $groupColumn): array
    {
        if (is_string($groupColumn)) {
            if ($groupColumn === '') {
                throw new \InvalidArgumentException('SQLite GROUP BY column must be a non-empty string');
            }

            return [$groupColumn];
        }

        if (!array_is_list($groupColumn) || $groupColumn === []) {
            throw new \InvalidArgumentException('SQLite GROUP BY columns must be a non-empty list');
        }

        $columns = [];
        foreach ($groupColumn as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite GROUP BY columns must be non-empty strings');
            }
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function compositeGroupKey(array $values): string
    {
        $parts = [];
        foreach ($values as $column => $value) {
            $parts[] = $column . '=' . self::groupKey($value);
        }

        return implode("\0", $parts);
    }

    private static function compareSqlValues(mixed $left, mixed $right): int
    {
        $leftRank = self::sortRank($left);
        $rightRank = self::sortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($left === null || $right === null) {
            return 0;
        }
        if ($left instanceof SQLiteBlobValue && $right instanceof SQLiteBlobValue) {
            return strcmp($left->bytes, $right->bytes);
        }
        if ((is_int($left) || is_float($left) || is_bool($left)) && (is_int($right) || is_float($right) || is_bool($right))) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function sortRank(mixed $value): int
    {
        return match (true) {
            $value === null => 0,
            is_int($value) || is_float($value) || is_bool($value) => 1,
            is_string($value) => 2,
            $value instanceof SQLiteBlobValue => 3,
            default => throw new \InvalidArgumentException('SQLite grouped aggregate ORDER BY values must be scalar, BLOB, or NULL'),
        };
    }
}
