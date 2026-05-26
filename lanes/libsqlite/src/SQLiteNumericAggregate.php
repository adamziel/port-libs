<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteNumericAggregate
{
    /**
     * @param iterable<mixed> $values
     */
    public static function countAll(iterable $values): int
    {
        $count = 0;
        foreach ($values as $_value) {
            $count++;
        }

        return $count;
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function countValue(iterable $values): int
    {
        $count = 0;
        foreach ($values as $value) {
            if ($value !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function countDistinct(iterable $values): int
    {
        $seen = [];
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $seen[self::distinctKey($value)] = true;
        }

        return count($seen);
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function sum(iterable $values): int|float|null
    {
        $seen = false;
        $hasFloat = false;
        $sum = 0;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            [$numeric, $isFloat] = self::numericValue($value);
            $seen = true;
            $hasFloat = $hasFloat || $isFloat;
            $sum += $numeric;
        }

        if (!$seen) {
            return null;
        }

        return $hasFloat ? (float) $sum : (int) $sum;
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function sumDistinct(iterable $values): int|float|null
    {
        return self::sum(self::distinctValues($values));
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function total(iterable $values): float
    {
        $total = 0.0;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            [$numeric] = self::numericValue($value);
            $total += (float) $numeric;
        }

        return $total;
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function totalDistinct(iterable $values): float
    {
        return self::total(self::distinctValues($values));
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function avg(iterable $values): ?float
    {
        $count = 0;
        $total = 0.0;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            [$numeric] = self::numericValue($value);
            $total += (float) $numeric;
            $count++;
        }

        return $count === 0 ? null : $total / $count;
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function avgDistinct(iterable $values): ?float
    {
        return self::avg(self::distinctValues($values));
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function min(iterable $values): mixed
    {
        return self::minMax($values, -1);
    }

    /**
     * @param iterable<mixed> $values
     */
    public static function max(iterable $values): mixed
    {
        return self::minMax($values, 1);
    }

    /**
     * @param iterable<array{0:mixed,1:mixed}> $rows
     */
    public static function sumFilter(iterable $rows): int|float|null
    {
        $values = [];
        foreach ($rows as $row) {
            if (self::isSqlTrue($row[1])) {
                $values[] = $row[0];
            }
        }

        return self::sum($values);
    }

    /**
     * @param iterable<mixed> $values
     * @return list<float>
     */
    public static function totalWindow(iterable $values, int $preceding, int $following = 0): array
    {
        if ($preceding < 0 || $following < 0) {
            throw new \InvalidArgumentException('SQLite numeric aggregate window frame bounds must be non-negative');
        }

        $rows = array_values(is_array($values) ? $values : iterator_to_array($values, false));
        $frames = [];
        $count = count($rows);
        for ($index = 0; $index < $count; $index++) {
            $start = max(0, $index - $preceding);
            $end = min($count - 1, $index + $following);
            $frames[] = self::total(array_slice($rows, $start, $end - $start + 1));
        }

        return $frames;
    }

    /**
     * @param iterable<mixed> $values
     */
    private static function minMax(iterable $values, int $direction): mixed
    {
        $winner = null;
        $seen = false;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            self::assertComparable($value);
            if (!$seen || self::compareSqlValues($value, $winner) * $direction > 0) {
                $winner = $value;
                $seen = true;
            }
        }

        return $seen ? $winner : null;
    }

    /**
     * @return array{0:int|float,1:bool}
     */
    private static function numericValue(mixed $value): array
    {
        if ($value instanceof SQLiteBlobValue) {
            return self::numericValue($value->bytes);
        }
        if (is_bool($value)) {
            return [(int) $value, false];
        }
        if (is_int($value)) {
            return [$value, false];
        }
        if (is_float($value)) {
            return [$value, true];
        }
        if (is_string($value)) {
            if (preg_match('/^[ \t\r\n\f]*[+-]?(?:(?:\d+(?:\.\d*)?)|(?:\.\d+))(?:[eE][+-]?\d+)?/', $value, $match) === 1) {
                $text = $match[0];
                $isFloat = str_contains($text, '.') || stripos($text, 'e') !== false;

                return [$isFloat ? (float) $text : (int) $text, $isFloat];
            }

            return [0, false];
        }

        throw new \InvalidArgumentException('SQLite numeric aggregate values must be scalar, BLOB, or NULL');
    }

    private static function distinctKey(mixed $value): string
    {
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

        throw new \InvalidArgumentException('SQLite count(DISTINCT) values must be scalar, BLOB, or NULL');
    }

    /**
     * @param iterable<mixed> $values
     * @return list<mixed>
     */
    private static function distinctValues(iterable $values): array
    {
        $distinct = [];
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
            $distinct[] = $value;
        }

        return $distinct;
    }

    private static function compareSqlValues(mixed $left, mixed $right): int
    {
        $leftRank = self::sortRank($left);
        $rightRank = self::sortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
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
            is_int($value) || is_float($value) || is_bool($value) => 1,
            is_string($value) => 2,
            $value instanceof SQLiteBlobValue => 3,
            default => throw new \InvalidArgumentException('SQLite min()/max() values must be scalar, BLOB, or NULL'),
        };
    }

    private static function assertComparable(mixed $value): void
    {
        self::sortRank($value);
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
