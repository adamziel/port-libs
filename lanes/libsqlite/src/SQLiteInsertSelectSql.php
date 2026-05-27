<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteInsertSelectSql
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<int|string,mixed> $parameters
     * @return array{target:string,columns:list<string>,source_rows:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,before:list<array<string,mixed>>,after:list<array<string,mixed>>,changes:int}
     */
    public static function execute(string $sql, array $tables, array $parameters = []): array
    {
        $plan = self::plan($sql, $tables, $parameters);
        $target = $plan['target'];
        $before = $tables[$target];
        $after = array_values(array_merge($before, $plan['inserted_rows']));

        return [
            'target' => $target,
            'columns' => $plan['columns'],
            'source_rows' => $plan['source_rows'],
            'inserted_rows' => $plan['inserted_rows'],
            'before' => $before,
            'after' => $after,
            'changes' => count($plan['inserted_rows']),
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<int|string,mixed> $parameters
     * @return array{target:string,columns:list<string>,select_sql:string,source_rows:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>}
     */
    public static function plan(string $sql, array $tables, array $parameters = []): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (preg_match('/^insert\s+(?:or\s+(?:abort|fail|ignore|rollback|replace)\s+)?into\s+/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite INSERT SELECT SQL must start with INSERT INTO');
        }
        if (preg_match('/^insert\s+or\s+/i', $sql) === 1) {
            throw new \InvalidArgumentException('SQLite INSERT SELECT conflict clauses are not supported by this bounded executor');
        }

        $offset = strlen($match[0]);
        $target = self::readIdentifier($sql, $offset, 'SQLite INSERT SELECT target table');
        if (!array_key_exists($target, $tables)) {
            throw new \InvalidArgumentException("SQLite INSERT SELECT target table {$target} is missing");
        }

        $offset += strlen($target);
        $offset = self::skipWhitespace($sql, $offset);
        $columns = [];
        if (($sql[$offset] ?? null) === '(') {
            [$columnSql, $offset] = self::consumeParenthesized($sql, $offset);
            foreach (self::splitTopLevel($columnSql, ',') as $column) {
                $columns[] = self::readCompleteIdentifier($column, 'SQLite INSERT SELECT target column');
            }
            if ($columns === []) {
                throw new \InvalidArgumentException('SQLite INSERT SELECT target column list cannot be empty');
            }
            if (count(array_unique($columns)) !== count($columns)) {
                throw new \InvalidArgumentException('SQLite INSERT SELECT target columns must be unique');
            }
            $offset = self::skipWhitespace($sql, $offset);
        }

        $selectSql = trim(substr($sql, $offset));
        if ($selectSql === '' || preg_match('/^select\s+|^with\s+/i', $selectSql) !== 1) {
            throw new \InvalidArgumentException('SQLite INSERT SELECT requires a SELECT source');
        }

        $sourceRows = SQLiteSelectSql::execute($selectSql, $tables, $parameters);
        if ($columns === []) {
            if ($sourceRows === []) {
                throw new \InvalidArgumentException('SQLite INSERT SELECT without target columns requires at least one source row');
            }
            $columns = array_keys($sourceRows[0]);
        }

        $insertedRows = [];
        foreach ($sourceRows as $row) {
            if (count($row) !== count($columns)) {
                throw new \InvalidArgumentException('SQLite INSERT SELECT source column count does not match target column count');
            }
            $values = array_values($row);
            $inserted = [];
            foreach ($columns as $index => $column) {
                $inserted[$column] = $values[$index] ?? null;
            }
            $insertedRows[] = $inserted;
        }

        return [
            'target' => $target,
            'columns' => $columns,
            'select_sql' => $selectSql,
            'source_rows' => $sourceRows,
            'inserted_rows' => $insertedRows,
        ];
    }

    private static function readIdentifier(string $sql, int $offset, string $label): string
    {
        if (preg_match('/\G([A-Za-z_][A-Za-z0-9_]*)/A', $sql, $match, 0, $offset) !== 1) {
            throw new \InvalidArgumentException("{$label} is malformed");
        }

        return $match[1];
    }

    private static function readCompleteIdentifier(string $sql, string $label): string
    {
        $sql = trim($sql);
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $sql) !== 1) {
            throw new \InvalidArgumentException("{$label} is malformed");
        }

        return $sql;
    }

    private static function skipWhitespace(string $sql, int $offset): int
    {
        $length = strlen($sql);
        while ($offset < $length && ctype_space($sql[$offset])) {
            $offset++;
        }

        return $offset;
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function consumeParenthesized(string $sql, int $offset): array
    {
        $depth = 0;
        $quote = false;
        $start = $offset + 1;
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
                    return [substr($sql, $start, $i - $start), $i + 1];
                }
                if ($depth < 0) {
                    break;
                }
            }
        }

        throw new \InvalidArgumentException('SQLite INSERT SELECT parenthesized column list is malformed');
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql, string $separator): array
    {
        $parts = [];
        $start = 0;
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
            if ($depth === 0 && $char === $separator) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($sql, $start));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
