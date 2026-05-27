<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectRecursiveWindowMaterializePlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $keyColumns
     * @param list<string> $windowColumns
     * @return array{sql:string,rows:list<array<string,mixed>>,ctePlan:array<string,mixed>,recursiveTrace:array<string,mixed>,currentNext:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function execute(string $sql, array $tables, array $keyColumns, array $windowColumns): array
    {
        if ($keyColumns === []) {
            throw new \InvalidArgumentException('SQLite recursive window materialize plan needs key columns');
        }
        if ($windowColumns === []) {
            throw new \InvalidArgumentException('SQLite recursive window materialize plan needs window columns');
        }
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite recursive window materialize plan needs WITH RECURSIVE SQL');
        }
        if (stripos($sql, ' AS MATERIALIZED ') === false) {
            throw new \InvalidArgumentException('SQLite recursive window materialize plan needs a MATERIALIZED recursive source');
        }
        if (preg_match('/\bover\s*\(/i', $sql) !== 1) {
            throw new \InvalidArgumentException('SQLite recursive window materialize plan needs window SELECT output');
        }

        $rows = SQLiteSelectSql::execute($sql, $tables);
        self::assertColumns($rows, $keyColumns, 'key');
        self::assertColumns($rows, $windowColumns, 'window');

        return [
            'sql' => $sql,
            'rows' => $rows,
            'ctePlan' => SQLiteSelectCteFlattenMaterializePlan::plan($sql),
            'recursiveTrace' => SQLiteSelectSql::recursiveCteCycleTrace($sql, $tables),
            'currentNext' => self::currentNextRows($rows, $keyColumns, $windowColumns),
            'dependencies' => [
                'sqlite-select-recursive-materialized-current-source',
                'sqlite-select-window-current-next-yield',
                'sqlite-select-cte-flatten-materialize-boundary',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $keyColumns
     * @param list<string> $windowColumns
     * @return list<array<string,mixed>>
     */
    private static function currentNextRows(array $rows, array $keyColumns, array $windowColumns): array
    {
        $pairs = [];
        foreach ($rows as $position => $row) {
            $next = $rows[$position + 1] ?? null;
            $pairs[] = [
                'position' => $position,
                'key' => self::project($row, $keyColumns),
                'current' => $row,
                'next' => $next,
                'currentWindow' => self::project($row, $windowColumns),
                'nextWindow' => $next === null ? null : self::project($next, $windowColumns),
                'samePartition' => $next !== null && self::samePartition($row, $next, $keyColumns),
            ];
        }

        return $pairs;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     */
    private static function assertColumns(array $rows, array $columns, string $label): void
    {
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException("SQLite recursive window materialize {$label} columns must be non-empty strings");
            }
            foreach ($rows as $row) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite recursive window materialize row is missing {$label} column {$column}");
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function project(array $row, array $columns): array
    {
        $projected = [];
        foreach ($columns as $column) {
            $projected[$column] = $row[$column];
        }

        return $projected;
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     * @param list<string> $keyColumns
     */
    private static function samePartition(array $left, array $right, array $keyColumns): bool
    {
        $partitionColumns = array_slice($keyColumns, 0, max(1, count($keyColumns) - 1));
        foreach ($partitionColumns as $column) {
            if (($left[$column] ?? null) !== ($right[$column] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
