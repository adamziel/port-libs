<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonTableDerivedIndex
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $indexColumns
     * @param list<string> $orderColumns
     * @return array{sql:string,rows:list<array<string,mixed>>,indexColumns:list<string>,orderColumns:list<string>,indexes:array<string,list<int>>,keys:array<int,string>}
     */
    public static function materialize(string $sql, array $tables, array $indexColumns, array $orderColumns = []): array
    {
        if ($indexColumns === []) {
            throw new \InvalidArgumentException('SQLite JSON derived index needs at least one indexed column');
        }

        foreach (array_merge($indexColumns, $orderColumns) as $column) {
            self::assertColumnName($column);
        }

        $rows = SQLiteSelectSql::execute($sql, $tables);
        $indexes = [];
        $keys = [];
        foreach ($rows as $position => $row) {
            foreach (array_merge($indexColumns, $orderColumns) as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite JSON derived index column {$column} is not available");
                }
            }

            $key = self::rowKey($row, $indexColumns);
            $keys[$position] = $key;
            $indexes[$key][] = $position;
        }

        foreach ($indexes as &$positions) {
            if ($orderColumns === []) {
                continue;
            }

            usort($positions, static function (int $left, int $right) use ($rows, $orderColumns): int {
                foreach ($orderColumns as $column) {
                    $comparison = self::compareValues($rows[$left][$column], $rows[$right][$column]);
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return $left <=> $right;
            });
        }
        unset($positions);

        return [
            'sql' => $sql,
            'rows' => $rows,
            'indexColumns' => array_values($indexColumns),
            'orderColumns' => array_values($orderColumns),
            'indexes' => $indexes,
            'keys' => $keys,
        ];
    }

    /**
     * @param array{rows:list<array<string,mixed>>,indexColumns:list<string>,indexes:array<string,list<int>>} $plan
     * @param array<string,mixed> $criteria
     * @return list<array<string,mixed>>
     */
    public static function lookup(array $plan, array $criteria): array
    {
        $key = self::criteriaKey($plan['indexColumns'], $criteria);
        $positions = $plan['indexes'][$key] ?? [];

        return array_map(static fn (int $position): array => $plan['rows'][$position], $positions);
    }

    /**
     * @param array{rows:list<array<string,mixed>>,indexColumns:list<string>,indexes:array<string,list<int>>} $plan
     * @return list<array{key:array<string,mixed>,current:array<string,mixed>,next:?array<string,mixed>,currentPosition:int,nextPosition:int|null}>
     */
    public static function adjacentPairs(array $plan): array
    {
        $pairs = [];
        foreach ($plan['indexes'] as $positions) {
            $count = count($positions);
            for ($i = 0; $i < $count; $i++) {
                $currentPosition = $positions[$i];
                $nextPosition = $positions[$i + 1] ?? null;
                $current = $plan['rows'][$currentPosition];

                $pairs[] = [
                    'key' => self::keyValues($current, $plan['indexColumns']),
                    'current' => $current,
                    'next' => $nextPosition === null ? null : $plan['rows'][$nextPosition],
                    'currentPosition' => $currentPosition,
                    'nextPosition' => $nextPosition,
                ];
            }
        }

        return $pairs;
    }

    /**
     * @param array{rows:list<array<string,mixed>>,indexColumns:list<string>,indexes:array<string,list<int>>} $plan
     * @param array<string,mixed> $criteria
     * @return list<array{key:array<string,mixed>,current:array<string,mixed>,next:?array<string,mixed>,currentPosition:int,nextPosition:int|null}>
     */
    public static function adjacentFor(array $plan, array $criteria): array
    {
        $wanted = self::criteriaKey($plan['indexColumns'], $criteria);

        return array_values(array_filter(
            self::adjacentPairs($plan),
            static fn (array $pair): bool => self::rowKey($pair['current'], $plan['indexColumns']) === $wanted,
        ));
    }

    /**
     * @param list<string> $columns
     */
    private static function rowKey(array $row, array $columns): string
    {
        return implode("\x1f", array_map(
            static fn (string $column): string => self::typedKey($row[$column] ?? null),
            $columns,
        ));
    }

    /**
     * @param list<string> $columns
     * @param array<string,mixed> $criteria
     */
    private static function criteriaKey(array $columns, array $criteria): string
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $criteria)) {
                throw new \InvalidArgumentException("SQLite JSON derived index lookup is missing {$column}");
            }
        }

        return implode("\x1f", array_map(
            static fn (string $column): string => self::typedKey($criteria[$column]),
            $columns,
        ));
    }

    /**
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function keyValues(array $row, array $columns): array
    {
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = $row[$column] ?? null;
        }

        return $values;
    }

    private static function typedKey(mixed $value): string
    {
        if ($value instanceof SQLiteBlobValue) {
            return 'blob:' . bin2hex($value->bytes);
        }
        if ($value === null) {
            return 'null:';
        }
        if (is_bool($value)) {
            return 'bool:' . ($value ? '1' : '0');
        }
        if (is_int($value)) {
            return 'int:' . $value;
        }
        if (is_float($value)) {
            return 'float:' . sprintf('%.17G', $value);
        }
        if (is_string($value)) {
            return 'text:' . $value;
        }

        return 'json:' . json_encode($value, JSON_UNESCAPED_SLASHES);
    }

    private static function compareValues(mixed $left, mixed $right): int
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
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function assertColumnName(string $column): void
    {
        if ($column === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException('SQLite JSON derived index columns must be simple output names');
        }
    }
}
