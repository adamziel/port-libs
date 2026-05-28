<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueSavepointUpsertCurrentSourceNext131Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_upsert_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPSERT savepoint needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value UPSERT savepoint needs unique constraints');
        }

        $current = self::normalizeTables($tables);
        $attempted = $current;
        $executed = [];
        $yielded = [];
        $attemptedReturning = [];
        $inserted = [];
        $updated = [];
        $conflicts = [];
        $rollbackReason = null;
        $rollbackStatement = null;

        foreach ($statements as $ordinal => $sql) {
            try {
                $result = self::executeOne($sql, $attempted, $uniqueConstraints, $rowIdColumn);
            } catch (\InvalidArgumentException $exception) {
                $rollbackReason = $exception->getMessage();
                $rollbackStatement = $ordinal;
                break;
            }

            $attempted = $result['tables'];
            $executed[] = [
                'ordinal' => $ordinal,
                'table' => $result['table'],
                'action' => $result['action'],
                'conflict_target' => $result['conflict_target'],
                'input_row' => $result['input_row'],
                'returning_rows' => $result['returning'],
                'conflict' => $result['conflict'],
            ];
            $attemptedReturning[] = ['ordinal' => $ordinal, 'action' => $result['action'], 'rows' => $result['returning']];
            if ($result['action'] === 'insert') {
                $inserted[] = ['ordinal' => $ordinal, 'row' => $result['returning'][0] ?? $result['input_row']];
            } else {
                $updated[] = ['ordinal' => $ordinal, 'row' => $result['returning'][0] ?? []];
            }
            if ($result['conflict'] !== null) {
                $conflicts[] = ['ordinal' => $ordinal] + $result['conflict'];
            }
            $yielded[] = ['ordinal' => $ordinal, 'action' => $result['action'], 'rows' => $result['returning']];
        }

        $rolledBack = $rollbackReason !== null;

        return [
            'savepoint' => $savepoint,
            'status' => $rolledBack ? 'rolled-back-to-savepoint' : 'released',
            'rolled_back' => $rolledBack,
            'rollback_reason' => $rollbackReason,
            'rollback_statement_ordinal' => $rollbackStatement,
            'current_source_tables' => $rolledBack ? $current : $attempted,
            'next_source_tables' => $attempted,
            'savepoint_image_tables' => $current,
            'executed_statements' => $executed,
            'yielded_returning' => $rolledBack ? array_slice($yielded, 0, max(0, (int) $rollbackStatement)) : $yielded,
            'attempted_returning' => $attemptedReturning,
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'conflicts' => $conflicts,
            'changes' => $rolledBack ? 0 : count($executed),
            'attempted_changes' => count($executed),
            'dependencies' => [
                'sqlite-insert-on-conflict-do-update',
                'sqlite-row-value-upsert-assignment',
                'sqlite-savepoint-current-source-upsert-rollback',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{table:string,action:string,conflict_target:list<string>,input_row:array<string,mixed>,returning:list<array<string,mixed>>,tables:array<string,list<array<string,mixed>>>,conflict:?array{row_id:int|string,columns:list<string>,key:string}}
     */
    private static function executeOne(string $sql, array $tables, array $uniqueConstraints, string $rowIdColumn): array
    {
        $parsed = self::parse($sql);
        $table = $parsed['table'];
        if (!isset($tables[$table]) || !array_is_list($tables[$table])) {
            throw new \InvalidArgumentException("SQLite row-value UPSERT table {$table} is missing");
        }

        $rows = $tables[$table];
        $input = self::rowFromInsertValues($parsed['columns'], $parsed['values']);
        $target = $parsed['conflict_target'];
        self::assertKnownUniqueConstraint($target, $uniqueConstraints);

        $conflictingIndex = self::findConflictIndex($rows, $input, $target);
        $action = 'insert';
        $conflict = null;
        if ($conflictingIndex === null) {
            $rows[] = $input;
            $returnedRow = $input;
        } else {
            $action = 'update';
            $oldRow = $rows[$conflictingIndex];
            $newRow = $oldRow;
            foreach ($parsed['assignments'] as $column => $expression) {
                $newRow[$column] = self::evaluateExpression($expression, $oldRow, $input);
            }
            $rows[$conflictingIndex] = $newRow;
            $returnedRow = $newRow;
            $conflict = [
                'row_id' => self::column($oldRow, $rowIdColumn),
                'columns' => $target,
                'key' => self::uniqueKey($input, $target) ?? '',
            ];
        }

        $violation = self::firstUniqueViolation($rows, $uniqueConstraints, $rowIdColumn);
        if ($violation !== null) {
            throw new \InvalidArgumentException(
                'SQLite UPSERT unique constraint failed after DO UPDATE: '
                . implode(',', $violation['columns'])
                . '='
                . $violation['key']
            );
        }

        $next = $tables;
        $next[$table] = $rows;

        return [
            'table' => $table,
            'action' => $action,
            'conflict_target' => $target,
            'input_row' => $input,
            'returning' => [self::projectReturningRow($returnedRow, $parsed['returning'])],
            'tables' => $next,
            'conflict' => $conflict,
        ];
    }

    /**
     * @return array{table:string,columns:list<string>,values:list<string>,conflict_target:list<string>,assignments:array<string,string>,returning:list<string>}
     */
    public static function parse(string $sql): array
    {
        $sql = trim(rtrim($sql, " \t\n\r\0\x0B;"));
        $pattern = '/^INSERT\s+INTO\s+([A-Za-z_][A-Za-z0-9_]*)\s*\((.*?)\)\s*VALUES\s*\((.*?)\)\s+ON\s+CONFLICT\s*\((.*?)\)\s+DO\s+UPDATE\s+SET\s+(.*?)\s+RETURNING\s+(.+)$/is';
        if (preg_match($pattern, $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value UPSERT SQL must be INSERT ... ON CONFLICT ... DO UPDATE ... RETURNING');
        }

        return [
            'table' => $match[1],
            'columns' => self::identifierList($match[2], 'INSERT columns'),
            'values' => self::splitComma($match[3]),
            'conflict_target' => self::identifierList($match[4], 'conflict target'),
            'assignments' => self::parseAssignments($match[5]),
            'returning' => self::identifierList($match[6], 'RETURNING columns'),
        ];
    }

    /**
     * @param list<string> $columns
     * @param list<string> $values
     * @return array<string,mixed>
     */
    private static function rowFromInsertValues(array $columns, array $values): array
    {
        if (count($columns) !== count($values)) {
            throw new \InvalidArgumentException('SQLite row-value UPSERT insert arity mismatch');
        }
        $row = [];
        foreach ($columns as $index => $column) {
            $row[$column] = self::literal(trim($values[$index]));
        }

        return $row;
    }

    /**
     * @return array<string,string>
     */
    private static function parseAssignments(string $sql): array
    {
        $assignments = [];
        foreach (self::splitComma($sql) as $part) {
            if (preg_match('/^\(([^()]+)\)\s*=\s*\((.*)\)$/s', trim($part), $match) !== 1) {
                throw new \InvalidArgumentException('SQLite row-value UPSERT only supports row-value DO UPDATE assignment');
            }
            $columns = self::identifierList($match[1], 'row-value assignment columns');
            $expressions = self::splitComma($match[2]);
            if (count($columns) !== count($expressions)) {
                throw new \InvalidArgumentException('SQLite row-value UPSERT assignment arity mismatch');
            }
            foreach ($columns as $index => $column) {
                if (array_key_exists($column, $assignments)) {
                    throw new \InvalidArgumentException("SQLite row-value UPSERT assignment repeats column {$column}");
                }
                $assignments[$column] = trim($expressions[$index]);
            }
        }
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite row-value UPSERT needs DO UPDATE assignments');
        }

        return $assignments;
    }

    /**
     * @param array<string,mixed> $oldRow
     * @param array<string,mixed> $excluded
     */
    private static function evaluateExpression(string $expression, array $oldRow, array $excluded): mixed
    {
        $expression = trim($expression);
        $concatParts = self::splitOperator($expression, '||');
        if (count($concatParts) > 1) {
            $pieces = [];
            foreach ($concatParts as $part) {
                $value = self::evaluateExpression($part, $oldRow, $excluded);
                if ($value === null) {
                    return null;
                }
                $pieces[] = (string) $value;
            }

            return implode('', $pieces);
        }
        $additionParts = self::splitOperator($expression, '+');
        if (count($additionParts) > 1) {
            $sum = 0;
            foreach ($additionParts as $part) {
                $value = self::evaluateExpression($part, $oldRow, $excluded);
                if ($value === null) {
                    return null;
                }
                $sum += (int) $value;
            }

            return $sum;
        }
        if (preg_match('/^excluded\.([A-Za-z_][A-Za-z0-9_]*)$/i', $expression, $match) === 1) {
            return self::column($excluded, $match[1]);
        }
        if (preg_match("/^'.*'$/s", $expression) === 1 || strcasecmp($expression, 'NULL') === 0 || preg_match('/^-?\d+$/', $expression) === 1) {
            return self::literal($expression);
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
            return self::column($oldRow, $expression);
        }

        throw new \InvalidArgumentException("SQLite row-value UPSERT expression is unsupported: {$expression}");
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function projectReturningRow(array $row, array $columns): array
    {
        $returned = [];
        foreach ($columns as $column) {
            $returned[$column] = self::column($row, $column);
        }

        return $returned;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $columns
     */
    private static function findConflictIndex(array $rows, array $candidate, array $columns): ?int
    {
        $key = self::uniqueKey($candidate, $columns);
        if ($key === null) {
            return null;
        }
        foreach ($rows as $index => $row) {
            if (self::uniqueKey($row, $columns) === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<list<string>> $uniqueConstraints
     * @return array{columns:list<string>,key:string}|null
     */
    private static function firstUniqueViolation(array $rows, array $uniqueConstraints, string $rowIdColumn): ?array
    {
        foreach ($uniqueConstraints as $columns) {
            if (!is_array($columns) || $columns === []) {
                throw new \InvalidArgumentException('SQLite row-value UPSERT unique constraints need columns');
            }
            $seen = [];
            foreach ($rows as $row) {
                $key = self::uniqueKey($row, $columns);
                if ($key === null) {
                    continue;
                }
                if (isset($seen[$key])) {
                    return ['columns' => array_values($columns), 'key' => $key];
                }
                $seen[$key] = true;
            }
        }

        return null;
    }

    /**
     * @param list<string> $columns
     */
    private static function assertKnownUniqueConstraint(array $columns, array $uniqueConstraints): void
    {
        foreach ($uniqueConstraints as $constraint) {
            if ($constraint === $columns) {
                return;
            }
        }

        throw new \InvalidArgumentException('SQLite row-value UPSERT conflict target does not match a unique constraint');
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function uniqueKey(array $row, array $columns): ?string
    {
        $parts = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite row-value UPSERT unique columns must be non-empty strings');
            }
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite row-value UPSERT unique column {$column} is missing");
            }
            if ($row[$column] === null) {
                return null;
            }
            $parts[] = self::keyPart($row[$column]);
        }

        return implode('|', $parts);
    }

    private static function keyPart(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        return serialize($value);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function column(array $row, string $column): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite row-value UPSERT column {$column} is missing");
        }

        return $row[$column];
    }

    private static function literal(string $sql): mixed
    {
        if (strcasecmp($sql, 'NULL') === 0) {
            return null;
        }
        if (preg_match('/^-?\d+$/', $sql) === 1) {
            return (int) $sql;
        }
        if (preg_match("/^'(.*)'$/s", $sql, $match) === 1) {
            return str_replace("''", "'", $match[1]);
        }

        throw new \InvalidArgumentException("SQLite row-value UPSERT literal is unsupported: {$sql}");
    }

    /**
     * @return list<string>
     */
    private static function identifierList(string $sql, string $label): array
    {
        $identifiers = [];
        foreach (self::splitComma($sql) as $part) {
            $identifier = trim($part);
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
                throw new \InvalidArgumentException("SQLite row-value UPSERT {$label} must contain identifiers");
            }
            $identifiers[] = $identifier;
        }
        if ($identifiers === []) {
            throw new \InvalidArgumentException("SQLite row-value UPSERT {$label} must not be empty");
        }

        return $identifiers;
    }

    /**
     * @return list<string>
     */
    private static function splitComma(string $sql): array
    {
        $parts = [];
        $buffer = '';
        $inString = false;
        $depth = 0;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                $buffer .= $char;
                if ($inString && ($sql[$i + 1] ?? null) === "'") {
                    $buffer .= "'";
                    $i++;
                    continue;
                }
                $inString = !$inString;
                continue;
            }
            if (!$inString && $char === '(') {
                $depth++;
            } elseif (!$inString && $char === ')') {
                $depth--;
            }
            if (!$inString && $depth === 0 && $char === ',') {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private static function splitOperator(string $sql, string $operator): array
    {
        $parts = [];
        $buffer = '';
        $inString = false;
        $depth = 0;
        $length = strlen($sql);
        $operatorLength = strlen($operator);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                $buffer .= $char;
                if ($inString && ($sql[$i + 1] ?? null) === "'") {
                    $buffer .= "'";
                    $i++;
                    continue;
                }
                $inString = !$inString;
                continue;
            }
            if (!$inString && $char === '(') {
                $depth++;
                $buffer .= $char;
                continue;
            }
            if (!$inString && $char === ')') {
                $depth--;
                $buffer .= $char;
                continue;
            }
            if (!$inString && $depth === 0 && substr($sql, $i, $operatorLength) === $operator) {
                $parts[] = trim($buffer);
                $buffer = '';
                $i += $operatorLength - 1;
                continue;
            }
            $buffer .= $char;
        }
        $parts[] = trim($buffer);

        return count($parts) > 1 ? $parts : [$sql];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value UPSERT tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value UPSERT rows must be arrays');
                }
            }
        }

        return $tables;
    }
}
