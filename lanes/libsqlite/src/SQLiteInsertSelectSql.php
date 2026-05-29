<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteInsertSelectSql
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<int|string,mixed> $parameters
     * @param list<list<string>> $uniqueColumns
     * @return array{target:string,conflict_action:string,columns:list<string>,source_rows:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,deleted_rows:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,returning_sql:?string,returning_rows:list<array<string,mixed>>,before:list<array<string,mixed>>,after:list<array<string,mixed>>,changes:int}
     */
    public static function execute(string $sql, array $tables, array $parameters = [], array $uniqueColumns = []): array
    {
        $plan = self::plan($sql, $tables, $parameters);
        $target = $plan['target'];
        $before = $tables[$target];
        $after = $before;
        $inserted = [];
        $deleted = [];
        $ignored = [];

        foreach ($plan['inserted_rows'] as $row) {
            $conflictingIndexes = self::conflictingIndexes($after, $row, $uniqueColumns);
            if ($conflictingIndexes !== []) {
                if ($plan['conflict_action'] === 'ignore') {
                    $ignored[] = $row;
                    continue;
                }
                if ($plan['conflict_action'] !== 'replace') {
                    throw new \InvalidArgumentException('SQLite INSERT SELECT current unique constraint conflict');
                }
                foreach (array_reverse($conflictingIndexes) as $index) {
                    $deleted[] = $after[$index];
                    unset($after[$index]);
                }
                $after = array_values($after);
            }
            $after[] = $row;
            $inserted[] = $row;
        }

        $returningRows = $plan['returning_sql'] === null
            ? []
            : self::returningRows($plan['returning_sql'], $target, $inserted);

        return [
            'target' => $target,
            'conflict_action' => $plan['conflict_action'],
            'columns' => $plan['columns'],
            'source_rows' => $plan['source_rows'],
            'inserted_rows' => $inserted,
            'deleted_rows' => $deleted,
            'ignored_rows' => $ignored,
            'returning_sql' => $plan['returning_sql'],
            'returning_rows' => $returningRows,
            'before' => $before,
            'after' => $after,
            'changes' => count($inserted),
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<int|string,mixed> $parameters
     * @return array{target:string,conflict_action:string,columns:list<string>,select_sql:string,returning_sql:?string,source_rows:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>}
     */
    public static function plan(string $sql, array $tables, array $parameters = []): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        if (preg_match('/^insert\s+(?:or\s+(abort|fail|ignore|rollback|replace)\s+)?into\s+/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite INSERT SELECT SQL must start with INSERT INTO');
        }
        $conflictAction = strtolower(($match[1] ?? '') === '' ? 'abort' : $match[1]);

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

        $tailSql = trim(substr($sql, $offset));
        $returningOffset = self::keywordOffset($tailSql, 'RETURNING');
        $selectSql = $returningOffset === null ? $tailSql : trim(substr($tailSql, 0, $returningOffset));
        $returningSql = $returningOffset === null ? null : trim(substr($tailSql, $returningOffset + strlen('RETURNING')));
        if ($selectSql === '' || preg_match('/^select\s+|^with\s+/i', $selectSql) !== 1) {
            throw new \InvalidArgumentException('SQLite INSERT SELECT requires a SELECT source');
        }
        if ($returningSql === '') {
            throw new \InvalidArgumentException('SQLite INSERT SELECT RETURNING projection cannot be empty');
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
            'conflict_action' => $conflictAction,
            'columns' => $columns,
            'select_sql' => $selectSql,
            'returning_sql' => $returningSql,
            'source_rows' => $sourceRows,
            'inserted_rows' => $insertedRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $insertedRows
     * @return list<array<string,mixed>>
     */
    private static function returningRows(string $returningSql, string $target, array $insertedRows): array
    {
        if ($insertedRows === []) {
            return [];
        }

        return SQLiteSelectSql::execute(
            'SELECT ' . $returningSql . ' FROM ' . $target,
            [$target => $insertedRows],
        );
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

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<list<string>> $uniqueColumns
     * @return list<int>
     */
    private static function conflictingIndexes(array $rows, array $candidate, array $uniqueColumns): array
    {
        $indexes = [];
        foreach ($uniqueColumns as $columns) {
            self::validateUniqueColumns($columns);
            foreach ($rows as $index => $row) {
                if (self::uniqueRowsConflict($candidate, $row, $columns)) {
                    $indexes[(int) $index] = true;
                }
            }
        }

        return array_keys($indexes);
    }

    /**
     * @param list<string> $columns
     */
    private static function validateUniqueColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite INSERT SELECT unique constraint column list cannot be empty');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new \InvalidArgumentException('SQLite INSERT SELECT unique constraint column is malformed');
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function uniqueRowsConflict(array $left, array $right, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $left) || !array_key_exists($column, $right)) {
                throw new \InvalidArgumentException("SQLite INSERT SELECT unique constraint column {$column} is missing from row data");
            }
            if ($left[$column] === null || $right[$column] === null) {
                return false;
            }
            if ($left[$column] !== $right[$column]) {
                return false;
            }
        }

        return true;
    }

    private static function skipWhitespace(string $sql, int $offset): int
    {
        $length = strlen($sql);
        while ($offset < $length && ctype_space($sql[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private static function keywordOffset(string $sql, string $keyword, int $offset = 0): ?int
    {
        $depth = 0;
        $quote = false;
        $length = strlen($sql);
        $keywordLength = strlen($keyword);
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
                continue;
            }
            if ($depth === 0 && strcasecmp(substr($sql, $i, $keywordLength), $keyword) === 0) {
                $before = $sql[$i - 1] ?? ' ';
                $after = $sql[$i + $keywordLength] ?? ' ';
                if (!preg_match('/[A-Za-z0-9_]/', $before) && !preg_match('/[A-Za-z0-9_]/', $after)) {
                    return $i;
                }
            }
        }

        return null;
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
