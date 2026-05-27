<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectRecursiveJsonMaterialization
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $indexColumns
     * @param list<string> $orderColumns
     * @return array{sql:string,rows:list<array<string,mixed>>,indexColumns:list<string>,orderColumns:list<string>,indexes:array<string,list<int>>,keys:array<int,string>,currentNext:list<array{key:array<string,mixed>,current:array<string,mixed>,next:?array<string,mixed>,currentPosition:int,nextPosition:int|null}>,recursiveCurrentNext:list<array{iteration:int,current:array<string,mixed>,next:?array<string,mixed>,currentJsonRows:list<array<string,mixed>>,nextJsonRows:list<array<string,mixed>>,currentPosition:int,nextPosition:int|null,acceptedNext:list<array<string,mixed>>,skippedDuplicates:list<array<string,mixed>>,emitted:bool}>,trace:array<string,mixed>,dependencies:list<string>}
     */
    public static function materialize(string $sql, array $tables, array $indexColumns, array $orderColumns = []): array
    {
        if (stripos($sql, 'WITH RECURSIVE') === false) {
            throw new \InvalidArgumentException('SQLite recursive JSON materialization needs WITH RECURSIVE SQL');
        }
        if (stripos($sql, 'json_') === false) {
            throw new \InvalidArgumentException('SQLite recursive JSON materialization needs a JSON table source');
        }

        $plan = SQLiteJsonTableDerivedIndex::materialize($sql, $tables, $indexColumns, $orderColumns);
        $trace = self::traceFirstRecursiveCte($sql, $tables);

        return [
            'sql' => $sql,
            'rows' => $plan['rows'],
            'indexColumns' => $plan['indexColumns'],
            'orderColumns' => $plan['orderColumns'],
            'indexes' => $plan['indexes'],
            'keys' => $plan['keys'],
            'currentNext' => SQLiteJsonTableDerivedIndex::currentNextPairs($plan),
            'recursiveCurrentNext' => self::recursiveCurrentNext($trace, $plan['rows']),
            'trace' => $trace,
            'dependencies' => [
                'sqlite-select-recursive-materialized-current-source',
                'sqlite-json-table-derived-current-next',
                'sqlite-recursive-json-table-yield',
                'sqlite-recursive-current-next-json-yield-boundary',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $criteria
     * @return list<array{key:array<string,mixed>,current:array<string,mixed>,next:?array<string,mixed>,currentPosition:int,nextPosition:int|null}>
     */
    public static function currentNextFor(array $plan, array $criteria): array
    {
        $indexPlan = [
            'rows' => $plan['rows'],
            'indexColumns' => $plan['indexColumns'],
            'indexes' => $plan['indexes'],
        ];

        return SQLiteJsonTableDerivedIndex::currentNextFor($indexPlan, $criteria);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array{name:string,columns:list<string>,operator:string,rows:list<array<string,mixed>>,trace:list<array<string,mixed>>,skipped:list<array<string,mixed>>,dependencies:list<string>}
     */
    private static function traceFirstRecursiveCte(string $sql, array $tables): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        $depth = 0;
        $quote = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($quote && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $quote = !$quote;
                continue;
            }
            if ($quote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth === 0 && strncasecmp(substr($sql, $i), 'SELECT', 6) === 0 && self::keywordBounded($sql, $i, 6)) {
                return SQLiteSelectSql::recursiveCteCycleTrace(substr($sql, 0, $i) . 'SELECT * FROM ' . self::firstCteName($sql), $tables);
            }
        }

        throw new \InvalidArgumentException('SQLite recursive JSON materialization needs trailing SELECT');
    }

    /**
     * @param array{name:string,columns:list<string>,rows:list<array<string,mixed>>,trace:list<array<string,mixed>>} $trace
     * @param list<array<string,mixed>> $jsonRows
     * @return list<array{iteration:int,current:array<string,mixed>,next:?array<string,mixed>,currentJsonRows:list<array<string,mixed>>,nextJsonRows:list<array<string,mixed>>,currentPosition:int,nextPosition:int|null,acceptedNext:list<array<string,mixed>>,skippedDuplicates:list<array<string,mixed>>,emitted:bool}>
     */
    private static function recursiveCurrentNext(array $trace, array $jsonRows): array
    {
        $rows = array_values(array_filter(
            $trace['rows'],
            static fn (mixed $row): bool => is_array($row),
        ));
        $pairs = [];

        foreach ($trace['trace'] as $entry) {
            if (!is_array($entry['current'] ?? null)) {
                continue;
            }

            $current = $entry['current'];
            $currentPosition = self::recursiveRowPosition($rows, $current);
            $nextPosition = $currentPosition === null ? null : ($currentPosition + 1 < count($rows) ? $currentPosition + 1 : null);
            $next = $nextPosition === null ? null : $rows[$nextPosition];

            $pairs[] = [
                'iteration' => (int) ($entry['iteration'] ?? count($pairs)),
                'current' => $current,
                'next' => $next,
                'currentJsonRows' => self::jsonRowsForRecursiveRow($jsonRows, $current),
                'nextJsonRows' => $next === null ? [] : self::jsonRowsForRecursiveRow($jsonRows, $next),
                'currentPosition' => $currentPosition ?? count($pairs),
                'nextPosition' => $nextPosition,
                'acceptedNext' => array_values(array_filter(
                    $entry['accepted_next'] ?? [],
                    static fn (mixed $row): bool => is_array($row),
                )),
                'skippedDuplicates' => array_values(array_filter(
                    $entry['skipped_duplicates'] ?? [],
                    static fn (mixed $row): bool => is_array($row),
                )),
                'emitted' => (bool) ($entry['emitted'] ?? false),
            ];
        }

        return $pairs;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $wanted
     */
    private static function recursiveRowPosition(array $rows, array $wanted): ?int
    {
        foreach ($rows as $position => $row) {
            if (self::rowsShareValues($row, $wanted, array_keys($wanted))) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $jsonRows
     * @param array<string,mixed> $recursiveRow
     * @return list<array<string,mixed>>
     */
    private static function jsonRowsForRecursiveRow(array $jsonRows, array $recursiveRow): array
    {
        $columns = array_values(array_filter(
            array_keys($recursiveRow),
            static fn (string $column): bool => array_key_exists($column, $jsonRows[0] ?? []),
        ));
        if ($columns === []) {
            return [];
        }

        return array_values(array_filter(
            $jsonRows,
            static fn (array $row): bool => self::rowsShareValues($row, $recursiveRow, $columns),
        ));
    }

    /**
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     * @param list<string> $columns
     */
    private static function rowsShareValues(array $left, array $right, array $columns): bool
    {
        foreach ($columns as $column) {
            if (($left[$column] ?? null) !== ($right[$column] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private static function firstCteName(string $sql): string
    {
        if (preg_match('/^with\s+recursive\s+([A-Za-z_][A-Za-z0-9_]*)/i', trim($sql), $match) !== 1) {
            throw new \InvalidArgumentException('SQLite recursive JSON materialization CTE name is malformed');
        }

        return $match[1];
    }

    private static function keywordBounded(string $sql, int $offset, int $length): bool
    {
        $before = $offset === 0 ? '' : $sql[$offset - 1];
        $after = $sql[$offset + $length] ?? '';

        return ($before === '' || !preg_match('/[A-Za-z0-9_]/', $before))
            && ($after === '' || !preg_match('/[A-Za-z0-9_]/', $after));
    }
}
