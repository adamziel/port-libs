<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertReturningSql
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>>|null $uniqueConstraints
     * @return array{target:string,conflict_target:list<string>,columns:list<string>,incoming_rows:list<array<string,mixed>>,before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning:list<array<string,mixed>>,changes:int}
     */
    public static function execute(string $sql, array $tables, ?array $uniqueConstraints = null): array
    {
        $parsed = self::parse($sql);
        $target = $parsed['target'];
        if (!isset($tables[$target]) || !is_array($tables[$target]) || !array_is_list($tables[$target])) {
            throw new \InvalidArgumentException("SQLite UPSERT RETURNING target table {$target} is missing");
        }

        self::validateConflictTarget($parsed['conflict_target'], $uniqueConstraints);

        if ($parsed['action'] === 'nothing') {
            $result = self::executeDoNothing($tables[$target], $parsed['incoming_rows'], $parsed['conflict_target'], $uniqueConstraints);
        } else {
            $result = SQLiteUpsertDoUpdateWherePlan::execute(
                $tables[$target],
                $parsed['incoming_rows'],
                $parsed['conflict_target'],
                self::assignmentCallbacks($target, $parsed['assignments']),
                self::wherePredicate($target, $parsed['where']),
                $uniqueConstraints,
            );
        }

        return [
            'target' => $target,
            'conflict_target' => $parsed['conflict_target'],
            'columns' => $parsed['columns'],
            'incoming_rows' => $parsed['incoming_rows'],
            'before' => $result['before'],
            'after' => $result['after'],
            'inserted_rows' => $result['inserted_rows'],
            'updated_rows' => $result['updated_rows'],
            'skipped_rows' => $result['skipped_rows'],
            'returning' => SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], self::returningProjection($target, $parsed['returning'])),
            'changes' => $result['changes'],
        ];
    }

    /**
     * @return array{target:string,columns:list<string>,incoming_rows:list<array<string,mixed>>,conflict_target:list<string>,action:'update'|'nothing',assignments:array<string,string>,where:?string,returning:string}
     */
    public static function parse(string $sql): array
    {
        $sql = trim(rtrim($sql, " \t\n\r\0\x0B;"));
        if (preg_match('/^INSERT\s+INTO\s+/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING SQL must start with INSERT INTO');
        }

        $offset = strlen($match[0]);
        $target = self::readIdentifier($sql, $offset, 'SQLite UPSERT RETURNING target table');
        $offset += strlen($target);
        $offset = self::skipWhitespace($sql, $offset);
        if (($sql[$offset] ?? null) !== '(') {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING requires a target column list');
        }
        [$columnSql, $offset] = self::consumeParenthesized($sql, $offset);
        $columns = self::identifierList($columnSql, 'SQLite UPSERT RETURNING target column');
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING target column list cannot be empty');
        }
        if (count(array_unique($columns)) !== count($columns)) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING target columns must be unique');
        }

        $offset = self::skipWhitespace($sql, $offset);
        if (preg_match('/\GVALUES\b/i', $sql, $valuesMatch, 0, $offset) !== 1) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING requires VALUES input');
        }
        $offset += strlen($valuesMatch[0]);
        $conflictOffset = self::keywordOffset($sql, 'ON CONFLICT', $offset);
        if ($conflictOffset === null) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING requires ON CONFLICT');
        }

        $valuesSql = trim(substr($sql, $offset, $conflictOffset - $offset));
        $incomingRows = self::parseValues($valuesSql, $columns);

        $offset = $conflictOffset + strlen('ON CONFLICT');
        $offset = self::skipWhitespace($sql, $offset);
        if (($sql[$offset] ?? null) !== '(') {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING requires a conflict target');
        }
        [$conflictSql, $offset] = self::consumeParenthesized($sql, $offset);
        $conflictTarget = self::identifierList($conflictSql, 'SQLite UPSERT RETURNING conflict target');

        $offset = self::skipWhitespace($sql, $offset);
        $action = 'update';
        $assignmentSql = '';
        $whereSql = null;
        if (preg_match('/\GDO\s+NOTHING\b/i', $sql, $doNothingMatch, 0, $offset) === 1) {
            $action = 'nothing';
            $offset += strlen($doNothingMatch[0]);
            $returningOffset = self::keywordOffset($sql, 'RETURNING', $offset);
            if ($returningOffset === null) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING requires RETURNING');
            }
            if (trim(substr($sql, $offset, $returningOffset - $offset)) !== '') {
                throw new \InvalidArgumentException('SQLite UPSERT DO NOTHING RETURNING cannot include assignments or WHERE');
            }
        } else {
            if (preg_match('/\GDO\s+UPDATE\s+SET\b/i', $sql, $doMatch, 0, $offset) !== 1) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING only supports DO UPDATE SET or DO NOTHING');
            }
            $offset += strlen($doMatch[0]);

            $returningOffset = self::keywordOffset($sql, 'RETURNING', $offset);
            if ($returningOffset === null) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING requires RETURNING');
            }

            $beforeReturning = trim(substr($sql, $offset, $returningOffset - $offset));
            $whereOffset = self::keywordOffset($beforeReturning, 'WHERE', 0);
            $assignmentSql = $whereOffset === null ? $beforeReturning : trim(substr($beforeReturning, 0, $whereOffset));
            $whereSql = $whereOffset === null ? null : trim(substr($beforeReturning, $whereOffset + strlen('WHERE')));
            if ($whereSql === '') {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING WHERE clause must not be empty');
            }
        }

        $returning = trim(substr($sql, $returningOffset + strlen('RETURNING')));
        if ($returning === '') {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection must not be empty');
        }

        return [
            'target' => $target,
            'columns' => $columns,
            'incoming_rows' => $incomingRows,
            'conflict_target' => $conflictTarget,
            'action' => $action,
            'assignments' => $action === 'update' ? self::parseAssignments($assignmentSql) : [],
            'where' => $whereSql,
            'returning' => $returning,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $conflictTarget
     * @param list<list<string>>|null $uniqueConstraints
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,changes:int}
     */
    private static function executeDoNothing(array $rows, array $incomingRows, array $conflictTarget, ?array $uniqueConstraints): array
    {
        $uniqueConstraints = self::normalizeUniqueConstraints($conflictTarget, $uniqueConstraints);
        $after = array_values($rows);
        $inserted = [];
        $skipped = [];
        foreach ($incomingRows as $incoming) {
            if (self::findConflictIndex($after, $incoming, $conflictTarget) !== null) {
                $skipped[] = $incoming;
                continue;
            }
            self::ensureNoSecondaryUniqueConflict($after, $incoming, $uniqueConstraints, $conflictTarget);

            $after[] = $incoming;
            $inserted[] = $incoming;
        }

        return [
            'before' => array_values($rows),
            'after' => $after,
            'inserted_rows' => $inserted,
            'updated_rows' => [],
            'skipped_rows' => $skipped,
            'returning_rows' => $inserted,
            'changes' => count($inserted),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $incoming
     * @param list<string> $conflictTarget
     */
    private static function findConflictIndex(array $rows, array $incoming, array $conflictTarget): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($conflictTarget as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new \InvalidArgumentException("SQLite UPSERT RETURNING conflict column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return $index;
        }

        return null;
    }

    /**
     * @param list<string> $conflictTarget
     * @param list<list<string>>|null $uniqueConstraints
     */
    private static function validateConflictTarget(array $conflictTarget, ?array $uniqueConstraints): void
    {
        if ($uniqueConstraints === null) {
            return;
        }
        $uniqueConstraints = self::normalizeUniqueConstraints($conflictTarget, $uniqueConstraints);
        if (!self::targetMatchesUniqueConstraint($conflictTarget, $uniqueConstraints)) {
            throw new \InvalidArgumentException('SQLite UPSERT ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint');
        }
    }

    /**
     * @param list<string> $conflictTarget
     * @param list<list<string>>|null $uniqueConstraints
     * @return list<list<string>>
     */
    private static function normalizeUniqueConstraints(array $conflictTarget, ?array $uniqueConstraints): array
    {
        if ($uniqueConstraints === null) {
            return [$conflictTarget];
        }
        if ($uniqueConstraints === [] || !array_is_list($uniqueConstraints)) {
            throw new \InvalidArgumentException('SQLite UPSERT unique constraints must be a non-empty list');
        }

        $normalized = [];
        foreach ($uniqueConstraints as $constraint) {
            if (!is_array($constraint) || $constraint === [] || !array_is_list($constraint)) {
                throw new \InvalidArgumentException('SQLite UPSERT unique constraint must be a non-empty column list');
            }
            foreach ($constraint as $column) {
                if (!is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_ ]*$/', $column) !== 1) {
                    throw new \InvalidArgumentException('SQLite UPSERT unique constraint column is malformed');
                }
            }
            $normalized[] = array_values($constraint);
        }

        return $normalized;
    }

    /**
     * @param list<string> $target
     * @param list<list<string>> $uniqueConstraints
     */
    private static function targetMatchesUniqueConstraint(array $target, array $uniqueConstraints): bool
    {
        $sortedTarget = $target;
        sort($sortedTarget);
        foreach ($uniqueConstraints as $constraint) {
            if (count($constraint) !== count($target)) {
                continue;
            }
            $sortedConstraint = $constraint;
            sort($sortedConstraint);
            if ($sortedConstraint === $sortedTarget) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $incoming
     * @param list<list<string>> $uniqueConstraints
     * @param list<string> $conflictTarget
     */
    private static function ensureNoSecondaryUniqueConflict(array $rows, array $incoming, array $uniqueConstraints, array $conflictTarget): void
    {
        foreach ($uniqueConstraints as $constraint) {
            if (self::sameColumnSet($constraint, $conflictTarget)) {
                continue;
            }
            if (self::findConflictIndex($rows, $incoming, $constraint) !== null) {
                throw new \InvalidArgumentException('SQLite UPSERT insert produced a unique constraint conflict');
            }
        }
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private static function sameColumnSet(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }
        sort($left);
        sort($right);

        return $left === $right;
    }

    /**
     * @param list<string> $columns
     * @return list<array<string,mixed>>
     */
    private static function parseValues(string $sql, array $columns): array
    {
        $tuples = [];
        $offset = 0;
        $length = strlen($sql);
        while (true) {
            $offset = self::skipWhitespace($sql, $offset);
            if ($offset >= $length) {
                break;
            }
            if ($sql[$offset] !== '(') {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING VALUES rows must be parenthesized');
            }
            [$tupleSql, $offset] = self::consumeParenthesized($sql, $offset);
            $values = array_map(static fn (string $value): mixed => self::literal(trim($value)), self::splitComma($tupleSql));
            if (count($values) !== count($columns)) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING VALUES column count does not match target columns');
            }
            $row = [];
            foreach ($columns as $index => $column) {
                $row[$column] = $values[$index] ?? null;
            }
            $tuples[] = $row;
            $offset = self::skipWhitespace($sql, $offset);
            if ($offset >= $length) {
                break;
            }
            if ($sql[$offset] !== ',') {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING VALUES rows must be comma separated');
            }
            $offset++;
        }
        if ($tuples === []) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING VALUES input must not be empty');
        }

        return $tuples;
    }

    /**
     * @return array<string,string>
     */
    private static function parseAssignments(string $sql): array
    {
        $assignments = [];
        foreach (self::splitComma($sql) as $part) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)$/s', trim($part), $match) !== 1) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING assignments must be column = expression pairs');
            }
            $assignments[$match[1]] = trim($match[2]);
        }
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING DO UPDATE needs assignments');
        }

        return $assignments;
    }

    /**
     * @param array<string,string> $assignments
     * @return array<string,callable(array<string,mixed>,array<string,mixed>):mixed>
     */
    private static function assignmentCallbacks(string $target, array $assignments): array
    {
        $callbacks = [];
        foreach ($assignments as $column => $expression) {
            $callbacks[$column] = static fn (array $current, array $excluded): mixed => self::evaluateExpression($expression, $target, $current, $excluded);
        }

        return $callbacks;
    }

    /**
     * @return callable(array<string,mixed>,array<string,mixed>):bool|null
     */
    private static function wherePredicate(string $target, ?string $where): ?callable
    {
        if ($where === null || $where === '') {
            return null;
        }

        $terms = self::splitTopLevelKeyword($where, 'AND');

        return static function (array $current, array $excluded) use ($target, $terms): bool {
            foreach ($terms as $term) {
                if (self::evaluatePredicate($term, $target, $current, $excluded) !== true) {
                    return false;
                }
            }

            return true;
        };
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $excluded
     */
    private static function evaluatePredicate(string $term, string $target, array $current, array $excluded): ?bool
    {
        $term = trim(self::stripOuterParens($term));
        if (strcasecmp($term, 'NULL') === 0) {
            return null;
        }
        if ($term === '1') {
            return true;
        }
        if ($term === '0') {
            return false;
        }
        if (preg_match('/^(.+?)\s+IS\s+(NOT\s+)?NULL$/is', $term, $match) === 1) {
            $isNull = self::evaluateExpression($match[1], $target, $current, $excluded) === null;

            return isset($match[2]) && trim($match[2]) !== '' ? !$isNull : $isNull;
        }
        if (preg_match('/^(.+?)\s+(LIKE|GLOB)\s+(.+)$/is', $term, $match) === 1) {
            $left = self::evaluateExpression($match[1], $target, $current, $excluded);
            $pattern = self::evaluateExpression($match[3], $target, $current, $excluded);
            if ($left === null || $pattern === null) {
                return null;
            }

            return strtoupper($match[2]) === 'LIKE'
                ? SQLiteDatabase::likeMatches((string) $left, (string) $pattern)
                : SQLiteDatabase::globMatches((string) $left, (string) $pattern);
        }
        if (preg_match('/^(.+?)\s*(=|<>|!=|>=|<=|>|<)\s*(.+)$/s', $term, $match) === 1) {
            $left = self::evaluateExpression($match[1], $target, $current, $excluded);
            $right = self::evaluateExpression($match[3], $target, $current, $excluded);
            if ($left === null || $right === null) {
                return null;
            }

            return match ($match[2]) {
                '=' => $left == $right,
                '<>', '!=' => $left != $right,
                '>' => $left > $right,
                '>=' => $left >= $right,
                '<' => $left < $right,
                '<=' => $left <= $right,
                default => false,
            };
        }

        throw new \InvalidArgumentException("SQLite UPSERT RETURNING WHERE predicate is not supported: {$term}");
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $excluded
     */
    private static function evaluateExpression(string $expression, string $target, array $current, array $excluded): mixed
    {
        $expression = trim(self::stripOuterParens($expression));
        if (str_starts_with(strtolower($expression), 'coalesce(') && str_ends_with($expression, ')')) {
            $inner = substr($expression, 9, -1);
            foreach (self::splitComma($inner) as $part) {
                $value = self::evaluateExpression($part, $target, $current, $excluded);
                if ($value !== null) {
                    return $value;
                }
            }

            return null;
        }
        foreach (['+', '-'] as $operator) {
            $parts = self::splitBinaryOperator($expression, $operator);
            if ($parts !== null) {
                $left = self::evaluateExpression($parts[0], $target, $current, $excluded);
                $right = self::evaluateExpression($parts[1], $target, $current, $excluded);
                if ($left === null || $right === null) {
                    return null;
                }

                return $operator === '+' ? (int) $left + (int) $right : (int) $left - (int) $right;
            }
        }
        $concat = self::splitBinaryOperator($expression, '||');
        if ($concat !== null) {
            $left = self::evaluateExpression($concat[0], $target, $current, $excluded);
            $right = self::evaluateExpression($concat[1], $target, $current, $excluded);

            return $left === null || $right === null ? null : (string) $left . (string) $right;
        }
        if (preg_match('/^(?:(excluded|' . preg_quote($target, '/') . ')\.)?([A-Za-z_][A-Za-z0-9_]*)$/i', $expression, $match) === 1) {
            $source = strtolower($match[1] ?? '') === 'excluded' ? $excluded : $current;
            $column = $match[2];
            if (!array_key_exists($column, $source)) {
                throw new \InvalidArgumentException("SQLite UPSERT RETURNING column {$column} is missing");
            }

            return $source[$column];
        }

        return self::literal($expression);
    }

    /**
     * @return list<string>|array<string,string>
     */
    private static function returningProjection(string $target, string $sql): array
    {
        $projection = [];
        foreach (self::splitComma($sql) as $term) {
            $term = trim($term);
            if ($term === '*') {
                $projection[] = '*';
                continue;
            }
            if (preg_match('/^(?:(excluded|[A-Za-z_][A-Za-z0-9_]*)\.)?([A-Za-z_][A-Za-z0-9_]*)(?:\s+AS\s+([A-Za-z_][A-Za-z0-9_]*))?$/i', $term, $match) !== 1) {
                if (preg_match('/^(.+?)\s+AS\s+([A-Za-z_][A-Za-z0-9_]*)$/is', $term, $expressionMatch) !== 1) {
                    throw new \InvalidArgumentException('SQLite UPSERT RETURNING only supports columns, aliases, expressions with aliases, and *');
                }
                $expression = trim($expressionMatch[1]);
                $alias = $expressionMatch[2];
                if (preg_match('/^excluded\./i', $expression) === 1 || preg_match('/[^A-Za-z0-9_]excluded\./i', $expression) === 1) {
                    throw new \InvalidArgumentException('SQLite UPSERT RETURNING cannot reference excluded columns');
                }
                $projection[$alias] = static fn (array $row): mixed => self::evaluateExpression($expression, $target, $row, $row);
                continue;
            }
            $qualifier = $match[1] ?? '';
            if (strcasecmp($qualifier, 'excluded') === 0) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING cannot reference excluded columns');
            }
            if ($qualifier !== '' && strcasecmp($qualifier, $target) !== 0) {
                throw new \InvalidArgumentException("SQLite UPSERT RETURNING column {$qualifier}.{$match[2]} is missing");
            }
            if (isset($match[3]) && $match[3] !== '') {
                $projection[$match[3]] = $match[2];
                continue;
            }
            $projection[] = $match[2];
        }

        return $projection;
    }

    /**
     * @return list<string>
     */
    private static function identifierList(string $sql, string $label): array
    {
        $identifiers = [];
        foreach (self::splitComma($sql) as $part) {
            $part = trim($part);
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) !== 1) {
                throw new \InvalidArgumentException("{$label} is malformed");
            }
            $identifiers[] = $part;
        }

        return $identifiers;
    }

    private static function readIdentifier(string $sql, int $offset, string $label): string
    {
        if (preg_match('/\G([A-Za-z_][A-Za-z0-9_]*)/A', $sql, $match, 0, $offset) !== 1) {
            throw new \InvalidArgumentException("{$label} is malformed");
        }

        return $match[1];
    }

    private static function literal(string $sql): mixed
    {
        $sql = trim($sql);
        if (preg_match("/^'(.*)'$/s", $sql, $match) === 1) {
            return str_replace("''", "'", $match[1]);
        }
        if (strcasecmp($sql, 'NULL') === 0) {
            return null;
        }
        if (preg_match('/^-?\d+$/', $sql) === 1) {
            return (int) $sql;
        }

        throw new \InvalidArgumentException("SQLite UPSERT RETURNING literal is not supported: {$sql}");
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
        $inString = false;
        $length = strlen($sql);
        $start = $offset + 1;
        for ($i = $offset; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($inString && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $inString = !$inString;
                continue;
            }
            if ($inString) {
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
            }
        }

        throw new \InvalidArgumentException('SQLite UPSERT RETURNING SQL has unbalanced parentheses');
    }

    /**
     * @return list<string>
     */
    private static function splitComma(string $sql): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $inString = false;
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
            if (!$inString) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                } elseif ($char === ',' && $depth === 0) {
                    $parts[] = trim($buffer);
                    $buffer = '';
                    continue;
                }
            }
            $buffer .= $char;
        }
        if ($inString) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING SQL has an unterminated string literal');
        }
        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevelKeyword(string $sql, string $keyword): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $inString = false;
        $length = strlen($sql);
        $keywordLength = strlen($keyword);
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
            if (!$inString) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                } elseif ($depth === 0 && strcasecmp(substr($sql, $i, $keywordLength), $keyword) === 0) {
                    $before = $i === 0 ? ' ' : $sql[$i - 1];
                    $after = $sql[$i + $keywordLength] ?? ' ';
                    if (!preg_match('/[A-Za-z0-9_]/', $before) && !preg_match('/[A-Za-z0-9_]/', $after)) {
                        $parts[] = trim($buffer);
                        $buffer = '';
                        $i += $keywordLength - 1;
                        continue;
                    }
                }
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    private static function keywordOffset(string $sql, string $keyword, int $start): ?int
    {
        $depth = 0;
        $inString = false;
        $length = strlen($sql);
        $keywordLength = strlen($keyword);
        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($inString && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $inString = !$inString;
                continue;
            }
            if ($inString) {
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
                $before = $i === 0 ? ' ' : $sql[$i - 1];
                $after = $sql[$i + $keywordLength] ?? ' ';
                if (!preg_match('/[A-Za-z0-9_]/', $before) && !preg_match('/[A-Za-z0-9_]/', $after)) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function stripOuterParens(string $sql): string
    {
        $sql = trim($sql);
        while (str_starts_with($sql, '(') && str_ends_with($sql, ')')) {
            try {
                [, $offset] = self::consumeParenthesized($sql, 0);
            } catch (\InvalidArgumentException) {
                break;
            }
            if ($offset !== strlen($sql)) {
                break;
            }
            $sql = trim(substr($sql, 1, -1));
        }

        return $sql;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function splitBinaryOperator(string $sql, string $operator): ?array
    {
        $depth = 0;
        $inString = false;
        $length = strlen($sql);
        $operatorLength = strlen($operator);
        $matchOffset = null;
        for ($i = 0; $i <= $length - $operatorLength; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($inString && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $inString = !$inString;
                continue;
            }
            if ($inString) {
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
            if ($depth === 0 && substr($sql, $i, $operatorLength) === $operator) {
                if ($operator === '-' && ($i === 0 || preg_match('/[,(+\-]/', $sql[$i - 1]) === 1)) {
                    continue;
                }

                $matchOffset = $i;
            }
        }
        if ($matchOffset !== null) {
            return [trim(substr($sql, 0, $matchOffset)), trim(substr($sql, $matchOffset + $operatorLength))];
        }

        return null;
    }
}
