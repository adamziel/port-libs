<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteSelectRecursiveJsonMaterialization
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $indexColumns
     * @param list<string> $orderColumns
     * @return array{sql:string,rows:list<array<string,mixed>>,indexColumns:list<string>,orderColumns:list<string>,indexes:array<string,list<int>>,keys:array<int,string>,currentNext:list<array{key:array<string,mixed>,current:array<string,mixed>,next:?array<string,mixed>,currentPosition:int,nextPosition:int|null}>,trace:array<string,mixed>,dependencies:list<string>}
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
            'trace' => $trace,
            'dependencies' => [
                'sqlite-select-recursive-materialized-current-source',
                'sqlite-json-table-derived-current-next',
                'sqlite-recursive-json-table-yield',
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
