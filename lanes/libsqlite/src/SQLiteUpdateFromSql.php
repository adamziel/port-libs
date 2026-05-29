<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpdateFromSql
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<int|string,mixed> $parameters
     * @param list<list<string>> $uniqueColumns
     * @return array{target:string,conflict_action:string,assignments:array<string,string>,matched_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,deleted_rows:list<array<string,mixed>>,before:list<array<string,mixed>>,after:list<array<string,mixed>>,changes:int}
     */
    public static function execute(string $sql, array $tables, array $parameters = [], array $uniqueColumns = []): array
    {
        $plan = self::plan($sql, $tables, $parameters);
        $target = $plan['target'];
        $before = $tables[$target];
        $after = $before;
        $deleted = [];
        $updated = [];

        foreach ($plan['updates'] as $update) {
            $index = $update['target_index'];
            if (!array_key_exists($index, $after)) {
                continue;
            }

            $row = $after[$index];
            foreach ($plan['assignments'] as $column => $_expression) {
                $row[$column] = $update[$column] ?? null;
            }

            foreach ($uniqueColumns as $columns) {
                self::validateUniqueColumns($columns);
                foreach ($after as $candidateIndex => $candidate) {
                    if ($candidateIndex === $index || !self::uniqueRowsConflict($row, $candidate, $columns)) {
                        continue;
                    }
                    if ($plan['conflict_action'] !== 'replace') {
                        throw new \InvalidArgumentException('SQLite UPDATE FROM current unique constraint conflict');
                    }
                    $deleted[] = $candidate;
                    unset($after[$candidateIndex]);
                }
            }

            $after[$index] = $row;
            $updated[] = $row;
        }

        return [
            'target' => $target,
            'conflict_action' => $plan['conflict_action'],
            'assignments' => $plan['assignments'],
            'matched_rows' => $plan['matched_rows'],
            'updated_rows' => $updated,
            'deleted_rows' => $deleted,
            'before' => $before,
            'after' => array_values($after),
            'changes' => count($updated),
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<int|string,mixed> $parameters
     * @return array{target:string,conflict_action:string,assignments:array<string,string>,select_sql:string,order_limit_sql:string,matched_rows:list<array<string,mixed>>,updates:list<array<string,mixed>>}
     */
    public static function plan(string $sql, array $tables, array $parameters = []): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        $withSql = '';
        if (preg_match('/^with\b/i', $sql) === 1) {
            $updateOffset = self::keywordOffset($sql, 'UPDATE');
            if ($updateOffset === null) {
                throw new \InvalidArgumentException('SQLite UPDATE FROM SQL with CTE must contain UPDATE');
            }
            $withSql = trim(substr($sql, 0, $updateOffset));
            $sql = trim(substr($sql, $updateOffset));
        }

        if (preg_match('/^update\s+(?:or\s+(abort|fail|ignore|rollback|replace)\s+)?([A-Za-z_][A-Za-z0-9_]*)(?:\s+(?:as\s+)?([A-Za-z_][A-Za-z0-9_]*))?\s+set\s+/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite UPDATE FROM SQL must start with UPDATE target SET');
        }

        $conflictAction = strtolower(($match[1] ?? '') === '' ? 'abort' : $match[1]);
        if ($conflictAction !== 'abort' && $conflictAction !== 'replace') {
            throw new \InvalidArgumentException('SQLite UPDATE FROM bounded executor supports only OR ABORT and OR REPLACE');
        }
        $target = $match[2];
        if (!array_key_exists($target, $tables)) {
            throw new \InvalidArgumentException("SQLite UPDATE FROM target table {$target} is missing");
        }
        $targetAlias = $match[3] ?? null;
        if ($targetAlias !== null && strcasecmp($targetAlias, 'set') === 0) {
            $targetAlias = null;
        }
        $targetSource = $targetAlias ?? $target;

        $setOffset = strlen($match[0]);
        $fromOffset = self::keywordOffset($sql, 'FROM', $setOffset);
        if ($fromOffset === null) {
            throw new \InvalidArgumentException('SQLite UPDATE FROM SQL needs FROM');
        }
        $setSql = trim(substr($sql, $setOffset, $fromOffset - $setOffset));
        $tail = trim(substr($sql, $fromOffset + 4));
        if ($setSql === '' || $tail === '') {
            throw new \InvalidArgumentException('SQLite UPDATE FROM SQL needs assignments and source');
        }

        $tailParts = self::fromTailParts($tail);
        $fromSql = $tailParts['from'];
        $whereSql = $tailParts['where'];
        $orderLimitSql = $tailParts['order_limit'];
        if ($fromSql === '') {
            throw new \InvalidArgumentException('SQLite UPDATE FROM SQL needs source table');
        }

        $assignments = self::assignments($setSql);
        $workingTables = $tables;
        $workingTables[$target] = self::indexedRows($tables[$target]);
        $projection = ["{$targetSource}.__sqlite_update_index AS __sqlite_update_index"];
        foreach ($assignments as $column => $expression) {
            $projection[] = "{$expression} AS {$column}";
        }
        $targetFromSql = $targetAlias === null ? $target : "{$target} AS {$targetAlias}";
        $selectSql = ($withSql === '' ? '' : $withSql . ' ') . 'SELECT ' . implode(', ', $projection) . " FROM {$targetFromSql} CROSS JOIN {$fromSql}";
        if ($whereSql !== null && $whereSql !== '') {
            $selectSql .= " WHERE {$whereSql}";
        }
        if ($orderLimitSql !== '') {
            $selectSql .= " {$orderLimitSql}";
        }

        $matchedRows = SQLiteSelectSql::execute($selectSql, $workingTables, $parameters);
        $updatesByIndex = [];
        foreach ($matchedRows as $row) {
            $index = $row['__sqlite_update_index'] ?? null;
            if (!is_int($index)) {
                throw new \InvalidArgumentException('SQLite UPDATE FROM target row identity is malformed');
            }
            $row['target_index'] = $index;
            $updatesByIndex[$index] = $row;
        }

        return [
            'target' => $target,
            'conflict_action' => $conflictAction,
            'assignments' => $assignments,
            'select_sql' => $selectSql,
            'order_limit_sql' => $orderLimitSql,
            'matched_rows' => $matchedRows,
            'updates' => array_values($updatesByIndex),
        ];
    }

    /**
     * @return array{from:string,where:?string,order_limit:string}
     */
    private static function fromTailParts(string $tail): array
    {
        $whereOffset = self::keywordOffset($tail, 'WHERE');
        $orderOffset = self::keywordOffset($tail, 'ORDER BY');
        $limitOffset = self::keywordOffset($tail, 'LIMIT');

        $clauseOffsets = array_filter(
            [$whereOffset, $orderOffset, $limitOffset],
            static fn (?int $offset): bool => $offset !== null
        );
        $firstClauseOffset = $clauseOffsets === [] ? null : min($clauseOffsets);
        $fromSql = $firstClauseOffset === null ? $tail : trim(substr($tail, 0, $firstClauseOffset));

        $whereSql = null;
        if ($whereOffset !== null) {
            $whereEndCandidates = array_filter(
                [$orderOffset, $limitOffset],
                static fn (?int $offset): bool => $offset !== null && $offset > $whereOffset
            );
            $whereEnd = $whereEndCandidates === [] ? strlen($tail) : min($whereEndCandidates);
            $whereSql = trim(substr($tail, $whereOffset + 5, $whereEnd - ($whereOffset + 5)));
        }

        $orderLimitOffsetCandidates = array_filter(
            [$orderOffset, $limitOffset],
            static fn (?int $offset): bool => $offset !== null
        );
        $orderLimitOffset = $orderLimitOffsetCandidates === [] ? null : min($orderLimitOffsetCandidates);
        $orderLimitSql = $orderLimitOffset === null ? '' : trim(substr($tail, $orderLimitOffset));

        return [
            'from' => $fromSql,
            'where' => $whereSql,
            'order_limit' => $orderLimitSql,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function indexedRows(array $rows): array
    {
        $indexed = [];
        foreach (array_values($rows) as $index => $row) {
            if (array_key_exists('__sqlite_update_index', $row)) {
                throw new \InvalidArgumentException('SQLite UPDATE FROM target rows cannot contain reserved row identity column');
            }
            $row['__sqlite_update_index'] = $index;
            $indexed[] = $row;
        }

        return $indexed;
    }

    /**
     * @return array<string,string>
     */
    private static function assignments(string $sql): array
    {
        $assignments = [];
        foreach (self::splitTopLevel($sql, ',') as $assignment) {
            $equalOffset = self::topLevelCharOffset($assignment, '=');
            if ($equalOffset === null) {
                throw new \InvalidArgumentException('SQLite UPDATE FROM assignment needs =');
            }
            $column = trim(substr($assignment, 0, $equalOffset));
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1 || $column === '__sqlite_update_index') {
                throw new \InvalidArgumentException('SQLite UPDATE FROM assignment column is malformed');
            }
            if (array_key_exists($column, $assignments)) {
                throw new \InvalidArgumentException('SQLite UPDATE FROM assignment columns must be unique');
            }
            $expression = trim(substr($assignment, $equalOffset + 1));
            if ($expression === '') {
                throw new \InvalidArgumentException('SQLite UPDATE FROM assignment expression is empty');
            }
            $assignments[$column] = $expression;
        }
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite UPDATE FROM needs at least one assignment');
        }

        return $assignments;
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

    private static function topLevelCharOffset(string $sql, string $needle): ?int
    {
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
            if ($depth === 0 && $char === $needle) {
                return $i;
            }
        }

        return null;
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

    /**
     * @param list<string> $columns
     */
    private static function validateUniqueColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite UPDATE FROM unique constraint column list cannot be empty');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new \InvalidArgumentException('SQLite UPDATE FROM unique constraint column is malformed');
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
                throw new \InvalidArgumentException("SQLite UPDATE FROM unique constraint column {$column} is missing");
            }
            if ($left[$column] === null || $right[$column] === null || $left[$column] !== $right[$column]) {
                return false;
            }
        }

        return true;
    }
}
