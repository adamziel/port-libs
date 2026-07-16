<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRecursiveDmlCurrentSource
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<int|string,mixed> $parameters
     * @param list<list<string>> $uniqueColumns
     * @return array<string,mixed>
     */
    public static function insertSelect(string $sql, array $tables, array $parameters = [], array $uniqueColumns = []): array
    {
        [$tables, $dml] = self::materializeSingleCte($sql, $tables, $parameters);

        return SQLiteInsertSelectSql::execute($dml, $tables, [], $uniqueColumns);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<int|string,mixed> $parameters
     * @param list<list<string>> $uniqueColumns
     * @return array<string,mixed>
     */
    public static function updateFrom(string $sql, array $tables, array $parameters = [], array $uniqueColumns = []): array
    {
        [$tables, $dml] = self::materializeSingleCte($sql, $tables, $parameters);

        return SQLiteUpdateFromSql::execute($dml, $tables, [], $uniqueColumns);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<int|string,mixed> $parameters
     * @return array<string,mixed>
     */
    public static function updateDeleteReturning(string $sql, array $tables, array $parameters = [], string $rowIdColumn = 'setting_id'): array
    {
        [$tables, $dml, $name] = self::materializeSingleCte($sql, $tables, $parameters);
        $dml = self::rewriteSingleColumnInSubquery($dml, $name, $tables[$name]);

        return SQLiteUpdateDeleteReturningSql::execute($dml, $tables, $rowIdColumn);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<int|string,mixed> $parameters
     * @return array{0:array<string,list<array<string,mixed>>>,1:string,2:string}
     */
    private static function materializeSingleCte(string $sql, array $tables, array $parameters): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (preg_match('/^WITH\s+(?:RECURSIVE\s+)?([A-Za-z_][A-Za-z0-9_]*)(?:\s*\(([^)]*)\))?\s+AS\s*\(/i', $sql, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new \InvalidArgumentException('SQLite recursive DML current source requires a single WITH CTE');
        }
        $name = $match[1][0];
        if (array_key_exists($name, $tables)) {
            throw new \InvalidArgumentException("SQLite recursive DML CTE {$name} shadows an input table");
        }

        $bodyStart = (int) $match[0][1] + strlen($match[0][0]) - 1;
        $bodyEnd = self::matchingParenOffset($sql, $bodyStart);
        $dml = trim(substr($sql, $bodyEnd + 1));
        if ($dml === '' || preg_match('/^(INSERT|UPDATE|DELETE)\b/i', $dml) !== 1) {
            throw new \InvalidArgumentException('SQLite recursive DML current source requires INSERT, UPDATE, or DELETE after the CTE');
        }

        $columns = [];
        if (isset($match[2]) && $match[2][0] !== '') {
            foreach (explode(',', $match[2][0]) as $column) {
                $column = trim($column);
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                    throw new \InvalidArgumentException('SQLite recursive DML CTE column name is malformed');
                }
                $columns[] = $column;
            }
        }

        $prefix = substr($sql, 0, $bodyEnd + 1);
        $select = $columns === []
            ? "{$prefix} SELECT * FROM {$name}"
            : "{$prefix} SELECT " . implode(', ', $columns) . " FROM {$name}";
        $rows = SQLiteSelectSql::execute($select, $tables, $parameters);
        $tables[$name] = $rows;

        return [$tables, $dml, $name];
    }

    private static function matchingParenOffset(string $sql, int $offset): int
    {
        if (($sql[$offset] ?? null) !== '(') {
            throw new \InvalidArgumentException('SQLite recursive DML CTE body is malformed');
        }
        $depth = 0;
        $quote = false;
        $length = strlen($sql);
        for ($i = $offset; $i < $length; $i++) {
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
                if ($depth === 0) {
                    return $i;
                }
                if ($depth < 0) {
                    break;
                }
            }
        }

        throw new \InvalidArgumentException('SQLite recursive DML CTE body is unterminated');
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function rewriteSingleColumnInSubquery(string $sql, string $cteName, array $rows): string
    {
        return (string) preg_replace_callback(
            '/\b(IN|NOT\s+IN)\s*\(\s*SELECT\s+([A-Za-z_][A-Za-z0-9_]*)\s+FROM\s+' . preg_quote($cteName, '/') . '\s*\)/i',
            static function (array $match) use ($rows): string {
                $column = $match[2];
                $values = [];
                foreach ($rows as $row) {
                    if (!array_key_exists($column, $row)) {
                        throw new \InvalidArgumentException("SQLite recursive DML CTE column {$column} is missing");
                    }
                    $values[] = self::literal($row[$column]);
                }

                return $match[1] . ' (' . implode(', ', $values) . ')';
            },
            $sql,
        );
    }

    private static function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }
}
