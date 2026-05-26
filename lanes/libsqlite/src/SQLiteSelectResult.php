<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectResult
{
    /**
     * @param iterable<array<string,mixed>> $rows
     * @param list<string>|null $distinctColumns
     * @param list<array{column:string,direction?:string}> $orderBy
     * @return list<array<string,mixed>>
     */
    public static function execute(
        iterable $rows,
        ?array $distinctColumns = null,
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0
    ): array {
        $result = array_values(is_array($rows) ? $rows : iterator_to_array($rows, false));

        if ($distinctColumns !== null) {
            $result = self::distinct($result, $distinctColumns);
        }
        if ($orderBy !== []) {
            $result = self::orderBy($result, $orderBy);
        }

        return self::limitOffset($result, $limit, $offset);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    public static function distinct(array $rows, array $columns): array
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite SELECT DISTINCT needs at least one result column');
        }

        $seen = [];
        $result = [];
        foreach ($rows as $row) {
            $parts = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite SELECT DISTINCT row is missing column {$column}");
                }
                $parts[] = self::valueKey($row[$column]);
            }

            $key = implode("\0", $parts);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{column:string,direction?:string}> $terms
     * @return list<array<string,mixed>>
     */
    public static function orderBy(array $rows, array $terms): array
    {
        if ($terms === []) {
            return $rows;
        }

        foreach ($terms as $term) {
            if (($term['column'] ?? '') === '') {
                throw new \InvalidArgumentException('SQLite ORDER BY term needs a column');
            }
            $direction = strtoupper($term['direction'] ?? 'ASC');
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite ORDER BY direction must be ASC or DESC');
            }
        }

        $ordered = [];
        foreach ($rows as $index => $row) {
            foreach ($terms as $term) {
                if (!array_key_exists($term['column'], $row)) {
                    throw new \InvalidArgumentException("SQLite ORDER BY row is missing column {$term['column']}");
                }
                self::sortRank($row[$term['column']]);
            }
            $ordered[] = [$row, $index];
        }

        usort($ordered, static function (array $left, array $right) use ($terms): int {
            foreach ($terms as $term) {
                $direction = strtoupper($term['direction'] ?? 'ASC');
                $comparison = self::compareSqlValues($left[0][$term['column']], $right[0][$term['column']]);
                if ($comparison !== 0) {
                    return $direction === 'DESC' ? -$comparison : $comparison;
                }
            }

            return $left[1] <=> $right[1];
        });

        return array_column($ordered, 0);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function limitOffset(array $rows, ?int $limit, int $offset = 0): array
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite OFFSET must be non-negative');
        }
        if ($limit !== null && $limit < 0) {
            return array_slice($rows, $offset);
        }

        return array_slice($rows, $offset, $limit);
    }

    private static function valueKey(mixed $value): string
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

        throw new \InvalidArgumentException('SQLite SELECT result values must be scalar, BLOB, or NULL');
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
            default => throw new \InvalidArgumentException('SQLite ORDER BY values must be scalar, BLOB, or NULL'),
        };
    }
}
