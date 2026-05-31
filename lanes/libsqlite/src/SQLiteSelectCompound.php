<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectCompound
{
    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @return list<array<string,mixed>>
     */
    public static function union(array $leftRows, array $rightRows, bool $all = false): array
    {
        return self::combine($leftRows, $rightRows, $all ? 'UNION ALL' : 'UNION');
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @return list<array<string,mixed>>
     */
    public static function intersect(array $leftRows, array $rightRows): array
    {
        return self::combine($leftRows, $rightRows, 'INTERSECT');
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @return list<array<string,mixed>>
     */
    public static function except(array $leftRows, array $rightRows): array
    {
        return self::combine($leftRows, $rightRows, 'EXCEPT');
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @return list<array<string,mixed>>
     */
    public static function combine(array $leftRows, array $rightRows, string $operator, array $collations = []): array
    {
        $operator = strtoupper(trim(preg_replace('/\s+/', ' ', $operator) ?? $operator));
        $columns = self::resultColumns($leftRows, $rightRows);
        $leftRows = self::normalizeRows($leftRows, $columns, 'left');
        $rightRows = self::normalizeRows($rightRows, $columns, 'right');

        return match ($operator) {
            'UNION ALL' => array_merge($leftRows, $rightRows),
            'UNION' => self::distinctRows(array_merge($leftRows, $rightRows), $columns, $collations),
            'INTERSECT' => self::intersectRows($leftRows, $rightRows, $columns, $collations),
            'EXCEPT' => self::exceptRows($leftRows, $rightRows, $columns, $collations),
            default => throw new \InvalidArgumentException('SQLite compound SELECT operator must be UNION, UNION ALL, INTERSECT, or EXCEPT'),
        };
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array<string,mixed>>
     */
    public static function execute(
        array $leftRows,
        array $rightRows,
        string $operator,
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0
    ): array {
        return SQLiteSelectResult::execute(
            self::combine($leftRows, $rightRows, $operator),
            null,
            $orderBy,
            $limit,
            $offset
        );
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @return list<string>
     */
    private static function resultColumns(array $leftRows, array $rightRows): array
    {
        $sample = $leftRows[0] ?? $rightRows[0] ?? null;
        if ($sample === null) {
            throw new \InvalidArgumentException('SQLite compound SELECT needs at least one result row to derive columns');
        }

        $columns = array_keys($sample);
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite compound SELECT rows need result columns');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite compound SELECT result columns must be named');
            }
        }

        return $columns;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     */
    private static function normalizeRows(array $rows, array $columns, string $side): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (count($row) !== count($columns)) {
                throw new \InvalidArgumentException("SQLite compound SELECT {$side} row width must match the first SELECT result width");
            }
            foreach ($row as $value) {
                self::valueKey($value);
            }
            $renamed = array_combine($columns, array_values($row));
            if (!is_array($renamed)) {
                throw new \InvalidArgumentException("SQLite compound SELECT {$side} row width must match the first SELECT result width");
            }
            $normalized[] = $renamed;
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function distinctRows(array $rows, array $columns, array $collations = []): array
    {
        $seen = [];
        $order = [];
        foreach ($rows as $row) {
            $key = self::rowKey($row, $columns, $collations);
            if (!isset($seen[$key])) {
                $order[] = $key;
            }
            $seen[$key] = $row;
        }

        $result = [];
        foreach ($order as $key) {
            $result[] = $seen[$key];
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function intersectRows(array $leftRows, array $rightRows, array $columns, array $collations = []): array
    {
        $right = [];
        foreach ($rightRows as $row) {
            $right[self::rowKey($row, $columns, $collations)] = true;
        }

        $result = [];
        $emitted = [];
        foreach ($leftRows as $row) {
            $key = self::rowKey($row, $columns, $collations);
            if (isset($right[$key]) && !isset($emitted[$key])) {
                $emitted[$key] = true;
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $leftRows
     * @param list<array<string,mixed>> $rightRows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function exceptRows(array $leftRows, array $rightRows, array $columns, array $collations = []): array
    {
        $right = [];
        foreach ($rightRows as $row) {
            $right[self::rowKey($row, $columns, $collations)] = true;
        }

        $result = [];
        $emitted = [];
        foreach ($leftRows as $row) {
            $key = self::rowKey($row, $columns, $collations);
            if (!isset($right[$key]) && !isset($emitted[$key])) {
                $emitted[$key] = true;
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function rowKey(array $row, array $columns, array $collations = []): string
    {
        $parts = [];
        foreach ($columns as $column) {
            $parts[] = self::valueKey($row[$column], isset($collations[$column]) && is_string($collations[$column]) ? $collations[$column] : 'BINARY');
        }

        return implode("\0", $parts);
    }

    /**
     * @param list<mixed> $values
     */
    public static function rowValueKey(array $values): string
    {
        return implode("\0", array_map(static fn (mixed $value): string => self::valueKey($value), $values));
    }

    private static function valueKey(mixed $value, string $collation = 'BINARY'): string
    {
        if ($value === null) {
            return 'null:';
        }
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . $value->bytes;
        }
        if (is_bool($value)) {
            return 'numeric:' . (int) $value;
        }
        if (is_int($value)) {
            return 'numeric:' . $value;
        }
        if (is_float($value)) {
            if (is_finite($value) && floor($value) === $value) {
                return 'numeric:' . sprintf('%.0F', $value);
            }

            return 'numeric:' . sprintf('%.17G', $value);
        }
        if (is_string($value)) {
            $collation = strtoupper($collation);
            $value = match ($collation) {
                'BINARY' => $value,
                'NOCASE' => self::asciiLower($value),
                'RTRIM' => rtrim($value, ' '),
                default => throw new \InvalidArgumentException("Unsupported SQLite compound SELECT collation: {$collation}"),
            };
            return 'text:' . $value;
        }

        throw new \InvalidArgumentException('SQLite compound SELECT result values must be scalar, BLOB, or NULL');
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
