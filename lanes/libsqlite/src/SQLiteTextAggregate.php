<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTextAggregate
{
    /**
     * @param iterable<mixed> $values
     */
    public static function groupConcat(iterable $values, mixed $separator = ','): ?string
    {
        $separatorText = self::separatorText($separator);
        if ($separatorText === null) {
            return null;
        }

        $items = [];
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $items[] = self::valueText($value);
        }

        return $items === [] ? null : implode($separatorText, $items);
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function groupConcatDistinct(iterable $values, mixed $separator = ','): ?string
    {
        $separatorText = self::separatorText($separator);
        if ($separatorText === null) {
            return null;
        }

        $items = [];
        $seen = [];
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $key = self::distinctKey($value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = self::valueText($value);
        }

        return $items === [] ? null : implode($separatorText, $items);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     */
    public static function groupConcatOrderBy(iterable $rows, mixed $separator = ','): ?string
    {
        $ordered = [];
        $sequence = 0;
        foreach ($rows as $row) {
            $ordered[] = [$row[0], $row[1], $sequence++];
        }

        usort($ordered, static function (array $left, array $right): int {
            $comparison = self::compareSqlValues($left[1], $right[1]);

            return $comparison !== 0 ? $comparison : ($left[2] <=> $right[2]);
        });

        return self::groupConcat(array_column($ordered, 0), $separator);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     */
    public static function groupConcatDistinctOrderBy(iterable $rows, mixed $separator = ','): ?string
    {
        $distinctRows = self::distinctOrderRows($rows);

        return self::groupConcatOrderBy($distinctRows, $separator);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed,2:mixed}> $rows
     */
    public static function groupConcatDistinctOrderByFilter(iterable $rows, mixed $separator = ','): ?string
    {
        $filteredRows = [];
        foreach ($rows as $row) {
            if (count($row) < 3) {
                throw new \InvalidArgumentException('SQLite group_concat(DISTINCT ORDER BY) FILTER rows require value, order key, and filter columns');
            }
            if (self::isSqlTrue($row[2])) {
                $filteredRows[] = [$row[0], $row[1]];
            }
        }

        return self::groupConcatDistinctOrderBy($filteredRows, $separator);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     * @return list<array{0:mixed,1:mixed}>
     */
    private static function distinctOrderRows(iterable $rows): array
    {
        $distinct = [];
        $sequence = 0;
        foreach ($rows as $row) {
            if (count($row) < 2) {
                throw new \InvalidArgumentException('SQLite group_concat(DISTINCT ORDER BY) rows require value and order key columns');
            }
            if ($row[0] === null) {
                continue;
            }

            $key = self::distinctKey($row[0]);
            if (isset($distinct[$key])) {
                continue;
            }

            $distinct[$key] = [$row[0], $row[1], $sequence++];
        }

        $ordered = array_values($distinct);
        usort($ordered, static function (array $left, array $right): int {
            $comparison = self::compareSqlValues($left[1], $right[1]);

            return $comparison !== 0 ? $comparison : ($left[2] <=> $right[2]);
        });

        return array_map(
            static fn (array $row): array => [$row[0], $row[1]],
            $ordered
        );
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     */
    public static function groupConcatFilter(iterable $rows, mixed $separator = ','): ?string
    {
        $values = [];
        foreach ($rows as $row) {
            if (self::isSqlTrue($row[1])) {
                $values[] = $row[0];
            }
        }

        return self::groupConcat($values, $separator);
    }

    /**
     * @param iterable<mixed> $values
     * @return list<?string>
     */
    public static function groupConcatWindow(iterable $values, int $preceding, int $following = 0, mixed $separator = ','): array
    {
        if ($preceding < 0 || $following < 0) {
            throw new \InvalidArgumentException('SQLite group_concat window frame bounds must be non-negative');
        }

        $rows = array_values(is_array($values) ? $values : iterator_to_array($values, false));
        $frames = [];
        $count = count($rows);
        for ($index = 0; $index < $count; $index++) {
            $start = max(0, $index - $preceding);
            $end = min($count - 1, $index + $following);
            $frames[] = self::groupConcat(array_slice($rows, $start, $end - $start + 1), $separator);
        }

        return $frames;
    }

    /**
     * @param iterable<mixed> $values
     * @param iterable<mixed> $separators
     * @return list<?string>
     */
    public static function groupConcatWindowSeparators(
        iterable $values,
        iterable $separators,
        int $preceding,
        int $following = 0,
    ): array {
        if ($preceding < 0 || $following < 0) {
            throw new \InvalidArgumentException('SQLite group_concat window frame bounds must be non-negative');
        }

        $rows = array_values(is_array($values) ? $values : iterator_to_array($values, false));
        $separatorRows = array_values(is_array($separators) ? $separators : iterator_to_array($separators, false));
        if (count($rows) !== count($separatorRows)) {
            throw new \InvalidArgumentException('SQLite group_concat window separator rows must match value rows');
        }

        $frames = [];
        $count = count($rows);
        for ($index = 0; $index < $count; $index++) {
            $start = max(0, $index - $preceding);
            $end = min($count - 1, $index + $following);
            $parts = [];
            for ($frameIndex = $start; $frameIndex <= $end; $frameIndex++) {
                $value = self::valueText($rows[$frameIndex]);
                if ($value === null) {
                    continue;
                }
                if ($parts !== []) {
                    $parts[] = self::separatorText($separatorRows[$frameIndex]) ?? '';
                }
                $parts[] = $value;
            }

            $frames[] = $parts === [] ? null : implode('', $parts);
        }

        return $frames;
    }

    private static function separatorText(mixed $separator): ?string
    {
        if ($separator === null) {
            return null;
        }

        return self::valueText($separator);
    }

    private static function valueText(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('SQLite group_concat() values must be scalar, BLOB, or NULL');
    }

    private static function distinctKey(mixed $value): string
    {
        if ($value === null) {
            return 'null:';
        }
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . $value->bytes;
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

        throw new \InvalidArgumentException('SQLite group_concat(DISTINCT) values must be scalar, BLOB, or NULL');
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

        return strcmp(self::valueText($left), self::valueText($right));
    }

    private static function sortRank(mixed $value): int
    {
        return match (true) {
            $value === null => 0,
            is_int($value) || is_float($value) || is_bool($value) => 1,
            is_string($value) => 2,
            $value instanceof SQLiteBlobValue => 3,
            default => throw new \InvalidArgumentException('SQLite aggregate ORDER BY values must be scalar, BLOB, or NULL'),
        };
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
