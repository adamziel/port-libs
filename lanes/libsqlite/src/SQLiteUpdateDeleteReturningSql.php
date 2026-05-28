<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpdateDeleteReturningSql
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array{row_id:int|string,columns:list<string>,key:string,conflicting_row_ids:list<int|string>}>,failed_conflict?:array{row_id:int|string,columns:list<string>,key:string,conflicting_row_ids:list<int|string>}}
     */
    public static function execute(string $sql, array $tables, string $rowIdColumn = 'option_id', array $uniqueConstraints = [], bool $preserveFailChanges = false): array
    {
        $parsed = self::parse($sql);
        $table = $parsed['table'];
        if (!isset($tables[$table]) || !is_array($tables[$table]) || !array_is_list($tables[$table])) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE RETURNING table {$table} is missing");
        }

        $where = self::wherePredicate($parsed['where']);
        if ($parsed['action'] === 'delete') {
            $plan = SQLiteUpdateDeleteLimitPlan::delete(
                $tables[$table],
                $where,
                $parsed['order_by'],
                $parsed['limit'],
                $parsed['offset'],
                $rowIdColumn,
            );
        } else {
            $plan = SQLiteUpdateDeleteLimitPlan::update(
                $tables[$table],
                $where,
                self::assignmentCallbacks($parsed['assignments']),
                $parsed['order_by'],
                $parsed['limit'],
                $parsed['offset'],
                $rowIdColumn,
            );
        }

        $projection = self::returningProjection($parsed['returning']);
        $conflictAction = $parsed['conflict_action'];
        $conflictResult = [
            'rows' => $plan->resultRows,
            'returning' => $plan->returningRows($projection),
            'ignored_rows' => [],
            'deleted_conflict_rows' => [],
            'conflicts' => [],
        ];
        if ($parsed['action'] === 'update' && $uniqueConstraints !== []) {
            $conflictResult = self::applyUpdateConflicts($plan, $projection, $uniqueConstraints, $conflictAction, $rowIdColumn, $preserveFailChanges);
        }

        $nextTables = $tables;
        $nextTables[$table] = $conflictResult['rows'];

        return [
            'action' => $parsed['action'],
            'table' => $table,
            'conflict_action' => $conflictAction,
            'plan' => $plan,
            'tables' => $nextTables,
            'returning' => $conflictResult['returning'],
            'ignored_rows' => $conflictResult['ignored_rows'],
            'deleted_conflict_rows' => $conflictResult['deleted_conflict_rows'],
            'conflicts' => $conflictResult['conflicts'],
            'failed_conflict' => $conflictResult['failed_conflict'] ?? null,
        ];
    }

    /**
     * @return array{action:string,table:string,conflict_action:string,assignments:array<string,string>,where:?string,returning:string,order_by:list<array{column:string,direction?:string}>,limit:?int,offset:int}
     */
    public static function parse(string $sql): array
    {
        $sql = trim(rtrim($sql, " \t\n\r\0\x0B;"));
        if ($sql === '') {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE RETURNING SQL must not be empty');
        }

        if (preg_match('/^DELETE\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)(.*)$/is', $sql, $match) === 1) {
            $table = $match[1];
            $tail = trim($match[2]);
            $clauses = self::parseTail($tail);

            return [
                'action' => 'delete',
                'table' => $table,
                'conflict_action' => 'abort',
                'assignments' => [],
                'where' => $clauses['where'],
                'returning' => $clauses['returning'],
                'order_by' => $clauses['order_by'],
                'limit' => $clauses['limit'],
                'offset' => $clauses['offset'],
            ];
        }

        if (preg_match('/^UPDATE(?:\s+OR\s+(ABORT|FAIL|IGNORE|REPLACE|ROLLBACK))?\s+([A-Za-z_][A-Za-z0-9_]*)\s+SET\s+(.*)$/is', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE RETURNING SQL must start with UPDATE or DELETE');
        }

        $conflictAction = strtolower($match[1] === '' ? 'abort' : $match[1]);
        $table = $match[2];
        $tail = $match[3];
        $firstClause = self::firstKeywordPosition($tail, [' WHERE ', ' RETURNING ']);
        if ($firstClause === null) {
            throw new \InvalidArgumentException('SQLite UPDATE RETURNING SQL requires RETURNING');
        }
        $assignmentSql = trim(substr($tail, 0, $firstClause));
        $clauses = self::parseTail(trim(substr($tail, $firstClause)));

        return [
            'action' => 'update',
            'table' => $table,
            'conflict_action' => $conflictAction,
            'assignments' => self::parseAssignments($assignmentSql),
            'where' => $clauses['where'],
            'returning' => $clauses['returning'],
            'order_by' => $clauses['order_by'],
            'limit' => $clauses['limit'],
            'offset' => $clauses['offset'],
        ];
    }

    /**
     * @param list<string>|array<string,string|callable(array<string,mixed>):mixed> $projection
     * @param list<list<string>> $uniqueConstraints
     * @return array{rows:list<array<string,mixed>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array{row_id:int|string,columns:list<string>,key:string,conflicting_row_ids:list<int|string>}>,failed_conflict?:array{row_id:int|string,columns:list<string>,key:string,conflicting_row_ids:list<int|string>}}
     */
    private static function applyUpdateConflicts(
        SQLiteUpdateDeleteLimitPlan $plan,
        array $projection,
        array $uniqueConstraints,
        string $conflictAction,
        string $rowIdColumn,
        bool $preserveFailChanges,
    ): array {
        $rows = $plan->inputRows;
        $inputById = self::rowsById($plan->inputRows, $rowIdColumn);
        $mutationById = self::rowsById($plan->mutationRows, $rowIdColumn);
        $returningRows = [];
        $ignoredRows = [];
        $deletedRows = [];
        $conflicts = [];
        $deletedIds = [];

        foreach ($plan->mutationIds as $rowId) {
            if (isset($deletedIds[(string) $rowId]) || !array_key_exists($rowId, $mutationById)) {
                continue;
            }

            $row = $mutationById[$rowId];
            $rows = self::replaceRowById($rows, $rowIdColumn, $rowId, $row);
            $conflict = self::firstRowConflict($row, $rows, $uniqueConstraints, $rowIdColumn);
            if ($conflict === null) {
                $returningRows[] = self::projectReturningRow($row, $projection);
                continue;
            }

            $conflicts[] = $conflict;
            if ($conflictAction === 'ignore') {
                $rows = self::replaceRowById($rows, $rowIdColumn, $rowId, $inputById[$rowId]);
                $ignoredRows[] = $row;
                continue;
            }
            if ($conflictAction === 'replace') {
                foreach ($conflict['conflicting_row_ids'] as $conflictingId) {
                    $removed = self::rowById($rows, $rowIdColumn, $conflictingId);
                    if ($removed !== null) {
                        $deletedRows[] = $removed;
                    }
                    $deletedIds[(string) $conflictingId] = true;
                    $rows = self::removeRowById($rows, $rowIdColumn, $conflictingId);
                }
                $returningRows[] = self::projectReturningRow($row, $projection);
                continue;
            }
            if ($conflictAction === 'fail' && $preserveFailChanges) {
                $rows = self::replaceRowById($rows, $rowIdColumn, $rowId, $inputById[$rowId]);

                return [
                    'rows' => $rows,
                    'returning' => $returningRows,
                    'ignored_rows' => $ignoredRows,
                    'deleted_conflict_rows' => $deletedRows,
                    'conflicts' => $conflicts,
                    'failed_conflict' => $conflict,
                ];
            }

            throw new \InvalidArgumentException(
                'SQLite UPDATE RETURNING unique constraint failed: '
                . implode(',', $conflict['columns'])
                . '='
                . $conflict['key']
                . ' using OR '
                . strtoupper($conflictAction)
            );
        }

        return [
            'rows' => $rows,
            'returning' => $returningRows,
            'ignored_rows' => $ignoredRows,
            'deleted_conflict_rows' => $deletedRows,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int|string,array<string,mixed>>
     */
    private static function rowsById(array $rows, string $rowIdColumn): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $id = self::column($row, $rowIdColumn);
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite UPDATE/DELETE rowid column {$rowIdColumn} must be int or string");
            }
            $indexed[$id] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $rows
     * @param list<list<string>> $uniqueConstraints
     * @return array{row_id:int|string,columns:list<string>,key:string,conflicting_row_ids:list<int|string>}|null
     */
    private static function firstRowConflict(array $row, array $rows, array $uniqueConstraints, string $rowIdColumn): ?array
    {
        $rowId = self::column($row, $rowIdColumn);
        if (!is_int($rowId) && !is_string($rowId)) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE rowid column {$rowIdColumn} must be int or string");
        }

        foreach ($uniqueConstraints as $columns) {
            if (!is_array($columns) || $columns === []) {
                throw new \InvalidArgumentException('SQLite UPDATE RETURNING unique constraints need columns');
            }
            $key = self::uniqueKey($row, $columns);
            if ($key === null) {
                continue;
            }

            $conflictingIds = [];
            foreach ($rows as $other) {
                $otherId = self::column($other, $rowIdColumn);
                if ($otherId === $rowId) {
                    continue;
                }
                if (self::uniqueKey($other, $columns) === $key) {
                    if (!is_int($otherId) && !is_string($otherId)) {
                        throw new \InvalidArgumentException("SQLite UPDATE/DELETE rowid column {$rowIdColumn} must be int or string");
                    }
                    $conflictingIds[] = $otherId;
                }
            }
            if ($conflictingIds !== []) {
                return ['row_id' => $rowId, 'columns' => array_values($columns), 'key' => $key, 'conflicting_row_ids' => $conflictingIds];
            }
        }

        return null;
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
                throw new \InvalidArgumentException('SQLite UPDATE RETURNING unique columns must be non-empty strings');
            }
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite UPDATE RETURNING unique column {$column} is missing");
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
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function replaceRowById(array $rows, string $rowIdColumn, int|string $rowId, array $replacement): array
    {
        foreach ($rows as $index => $row) {
            if (self::column($row, $rowIdColumn) === $rowId) {
                $rows[$index] = $replacement;
                return $rows;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function removeRowById(array $rows, string $rowIdColumn, int|string $rowId): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => self::column($row, $rowIdColumn) !== $rowId,
        ));
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function rowById(array $rows, string $rowIdColumn, int|string $rowId): ?array
    {
        foreach ($rows as $row) {
            if (self::column($row, $rowIdColumn) === $rowId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param list<string>|array<string,string|callable(array<string,mixed>):mixed> $projection
     * @return array<string,mixed>
     */
    private static function projectReturningRow(array $row, array $projection): array
    {
        $returned = [];
        foreach ($projection as $alias => $expression) {
            if (is_int($alias)) {
                if (!is_string($expression) || $expression === '') {
                    throw new \InvalidArgumentException('SQLite RETURNING projection columns must be non-empty strings');
                }
                if ($expression === '*') {
                    foreach ($row as $column => $value) {
                        $returned[$column] = $value;
                    }
                    continue;
                }
                if (!array_key_exists($expression, $row)) {
                    throw new \InvalidArgumentException("SQLite RETURNING projection column {$expression} is missing");
                }
                $returned[$expression] = $row[$expression];
                continue;
            }

            if (!is_string($alias) || $alias === '') {
                throw new \InvalidArgumentException('SQLite RETURNING projection aliases must be non-empty strings');
            }
            if (is_string($expression)) {
                if ($expression === '') {
                    throw new \InvalidArgumentException('SQLite RETURNING projection columns must be non-empty strings');
                }
                if (!array_key_exists($expression, $row)) {
                    throw new \InvalidArgumentException("SQLite RETURNING projection column {$expression} is missing");
                }
                $returned[$alias] = $row[$expression];
                continue;
            }
            if (!is_callable($expression)) {
                throw new \InvalidArgumentException('SQLite RETURNING projection expressions must be column names or callables');
            }
            $returned[$alias] = $expression($row);
        }

        return $returned;
    }

    /**
     * @return array{where:?string,returning:string,order_by:list<array{column:string,direction?:string}>,limit:?int,offset:int}
     */
    private static function parseTail(string $tail): array
    {
        if (preg_match('/(?:^|\s)RETURNING\s/i', $tail, $returningMatch, PREG_OFFSET_CAPTURE) !== 1) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE RETURNING SQL requires RETURNING');
        }
        $returningPos = (int) $returningMatch[0][1];
        $returningText = $returningMatch[0][0];
        $returningKeywordOffset = stripos($returningText, 'RETURNING');
        if ($returningKeywordOffset === false) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE RETURNING SQL requires RETURNING');
        }
        $afterReturningStart = $returningPos + $returningKeywordOffset + strlen('RETURNING');

        $whereSql = null;
        $beforeReturning = trim(substr($tail, 0, $returningPos));
        if ($beforeReturning !== '') {
            if (stripos($beforeReturning, 'WHERE ') !== 0) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE RETURNING only supports WHERE before RETURNING');
            }
            $whereSql = trim(substr($beforeReturning, 6));
        }

        $afterReturning = trim(substr($tail, $afterReturningStart));
        $orderPos = self::keywordPosition($afterReturning, ' ORDER BY ');
        $limitPos = self::keywordPosition($afterReturning, ' LIMIT ');
        $cut = self::minPosition($orderPos, $limitPos);
        $returning = trim($cut === null ? $afterReturning : substr($afterReturning, 0, $cut));
        if ($returning === '') {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE RETURNING projection must not be empty');
        }

        $orderBy = [];
        $limit = null;
        $offset = 0;
        if ($orderPos !== null) {
            $orderStart = $orderPos + strlen(' ORDER BY ');
            $orderEnd = $limitPos !== null && $limitPos > $orderPos ? $limitPos : strlen($afterReturning);
            $orderBy = self::parseOrderBy(trim(substr($afterReturning, $orderStart, $orderEnd - $orderStart)));
        }
        if ($limitPos !== null) {
            [$limit, $offset] = self::parseLimit(trim(substr($afterReturning, $limitPos + strlen(' LIMIT '))));
        }

        return ['where' => $whereSql, 'returning' => $returning, 'order_by' => $orderBy, 'limit' => $limit, 'offset' => $offset];
    }

    /**
     * @param list<string> $keywords
     */
    private static function firstKeywordPosition(string $sql, array $keywords): ?int
    {
        $positions = [];
        foreach ($keywords as $keyword) {
            $position = self::keywordPosition(' ' . ltrim($sql), $keyword);
            if ($position !== null) {
                $positions[] = max(0, $position - 1);
            }
        }

        return $positions === [] ? null : min($positions);
    }

    private static function keywordPosition(string $sql, string $keyword): ?int
    {
        $needle = strtolower($keyword);
        $needleLength = strlen($needle);
        $length = strlen($sql);
        $inString = false;
        $depth = 0;

        for ($i = 0; $i <= $length - $needleLength; $i++) {
            $char = $sql[$i];
            if ($char === "'") {
                if ($inString && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                    continue;
                }
                $inString = !$inString;
                continue;
            }
            if (!$inString && $char === '(') {
                $depth++;
                continue;
            }
            if (!$inString && $char === ')') {
                $depth--;
                continue;
            }
            if ($inString || $depth !== 0) {
                continue;
            }
            if (strtolower(substr($sql, $i, $needleLength)) === $needle) {
                return $i;
            }
        }

        return null;
    }

    private static function minPosition(?int ...$positions): ?int
    {
        $present = array_values(array_filter($positions, static fn (?int $position): bool => $position !== null));

        return $present === [] ? null : min($present);
    }

    /**
     * @return array<string,string>
     */
    private static function parseAssignments(string $sql): array
    {
        $assignments = [];
        foreach (self::splitComma($sql) as $part) {
            if (preg_match('/^\(([^()]+)\)\s*=\s*\((.*)\)$/s', trim($part), $match) === 1) {
                $columns = self::rowValueColumns($match[1]);
                $expressions = self::splitComma($match[2]);
                if (count($columns) !== count($expressions)) {
                    throw new \InvalidArgumentException('SQLite UPDATE row-value assignment arity mismatch');
                }
                foreach ($columns as $index => $column) {
                    if (array_key_exists($column, $assignments)) {
                        throw new \InvalidArgumentException("SQLite UPDATE assignment repeats column {$column}");
                    }
                    $assignments[$column] = trim($expressions[$index]);
                }
                continue;
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)$/s', trim($part), $match) !== 1) {
                throw new \InvalidArgumentException('SQLite UPDATE RETURNING assignments must be column = expression pairs');
            }
            if (array_key_exists($match[1], $assignments)) {
                throw new \InvalidArgumentException("SQLite UPDATE assignment repeats column {$match[1]}");
            }
            $assignments[$match[1]] = trim($match[2]);
        }
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite UPDATE RETURNING needs assignments');
        }

        return $assignments;
    }

    /**
     * @param array<string,string> $assignments
     * @return array<string,callable(array<string,mixed>):mixed>
     */
    private static function assignmentCallbacks(array $assignments): array
    {
        $callbacks = [];
        foreach ($assignments as $column => $expression) {
            $callbacks[$column] = static fn (array $row): mixed => self::evaluateExpression($expression, $row);
        }

        return $callbacks;
    }

    /**
     * @return list<array{column:string,direction?:string}>
     */
    private static function parseOrderBy(string $sql): array
    {
        if ($sql === '') {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE ORDER BY must not be empty');
        }

        $terms = [];
        foreach (self::splitComma($sql) as $term) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(ASC|DESC))?$/i', trim($term), $match) !== 1) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE ORDER BY only supports column terms');
            }
            $entry = ['column' => $match[1]];
            if (isset($match[2]) && $match[2] !== '') {
                $entry['direction'] = strtoupper($match[2]);
            }
            $terms[] = $entry;
        }

        return $terms;
    }

    /**
     * @return array{0:?int,1:int}
     */
    private static function parseLimit(string $sql): array
    {
        if (preg_match('/^(-?\d+)(?:\s+OFFSET\s+(\d+))?$/i', $sql, $match) === 1) {
            return [(int) $match[1], isset($match[2]) ? (int) $match[2] : 0];
        }
        if (preg_match('/^(\d+)\s*,\s*(-?\d+)$/', $sql, $match) === 1) {
            return [(int) $match[2], (int) $match[1]];
        }

        throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT must be an integer, integer OFFSET integer, or offset,count');
    }

    /**
     * @return callable(array<string,mixed>):bool|null
     */
    private static function wherePredicate(?string $where): callable
    {
        if ($where === null || $where === '') {
            return static fn (): bool => true;
        }

        $terms = self::splitWhereAnd($where);

        return static function (array $row) use ($terms): ?bool {
            foreach ($terms as $term) {
                $value = self::evaluatePredicate(trim($term), $row);
                if ($value !== true) {
                    return $value;
                }
            }

            return true;
        };
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function evaluatePredicate(string $term, array $row): ?bool
    {
        if (preg_match('/^\(([^()]+)\)\s+(NOT\s+)?BETWEEN\s*\((.*?)\)\s+AND\s*\((.*)\)$/is', $term, $match) === 1) {
            $value = self::rowValue($row, self::rowValueColumns($match[1]));
            $lower = self::rowValueExpressions($match[3], $row);
            $upper = self::rowValueExpressions($match[4], $row);
            $result = self::nullableAnd(
                self::rowValueCompareBoolean($value, '>=', $lower),
                self::rowValueCompareBoolean($value, '<=', $upper),
            );

            return isset($match[2]) && trim($match[2]) !== '' ? self::negateNullable($result) : $result;
        }
        if (preg_match('/^\(([^()]+)\)\s+(NOT\s+)?IN\s*\((.*)\)$/is', $term, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $tuples = self::rowValueTupleList($match[3], $row);
            $result = self::rowValueIn($left, $tuples);

            return isset($match[2]) && trim($match[2]) !== '' ? self::negateNullable($result) : $result;
        }
        if (preg_match('/^\(([^()]+)\)\s+IS\s+(NOT\s+)?\((.*)\)$/is', $term, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row);
            $result = self::rowValueIs($left, $right);

            return isset($match[2]) && trim($match[2]) !== '' ? !$result : $result;
        }
        if (preg_match('/^\(([^()]+)\)\s+IS\s+(NOT\s+)?DISTINCT\s+FROM\s*\((.*)\)$/is', $term, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row);
            $result = self::rowValueIsDistinctFrom($left, $right);

            return isset($match[2]) && trim($match[2]) !== '' ? !$result : $result;
        }
        if (preg_match('/^\(([^()]+)\)\s*(=|<>|!=|>=|<=|>|<)\s*\((.*)\)$/s', $term, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row);
            $comparison = self::rowValueCompare($left, $right);

            return match ($match[2]) {
                '=' => $comparison === null ? null : $comparison === 0,
                '<>', '!=' => $comparison === null ? null : $comparison !== 0,
                '>' => $comparison === null ? null : $comparison > 0,
                '>=' => $comparison === null ? null : $comparison >= 0,
                '<' => $comparison === null ? null : $comparison < 0,
                '<=' => $comparison === null ? null : $comparison <= 0,
                default => false,
            };
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+IS\s+(NOT\s+)?NULL$/i', $term, $match) === 1) {
            $isNull = self::column($row, $match[1]) === null;

            return isset($match[2]) && trim($match[2]) !== '' ? !$isNull : $isNull;
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+(NOT\s+)?IN\s*\((.*)\)$/is', $term, $match) === 1) {
            $left = self::column($row, $match[1]);
            $values = array_map(static fn (string $value): mixed => self::literal(trim($value)), self::splitComma($match[3]));
            if ($left === null || in_array(null, $values, true)) {
                return null;
            }
            $found = in_array($left, $values, true);

            return isset($match[2]) && trim($match[2]) !== '' ? !$found : $found;
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+(LIKE|GLOB)\s+(.+)$/is', $term, $match) === 1) {
            $left = self::column($row, $match[1]);
            $pattern = self::literal(trim($match[3]));
            if ($left === null || $pattern === null) {
                return null;
            }

            return strtoupper($match[2]) === 'LIKE'
                ? SQLiteDatabase::likeMatches((string) $left, (string) $pattern)
                : SQLiteDatabase::globMatches((string) $left, (string) $pattern);
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*(=|<>|!=|>=|<=|>|<)\s*(.+)$/s', $term, $match) === 1) {
            $left = self::column($row, $match[1]);
            $right = self::evaluateExpression(trim($match[3]), $row);
            if ($left === null || $right === null) {
                return null;
            }
            $comparison = $left <=> $right;

            return match ($match[2]) {
                '=' => $left == $right,
                '<>', '!=' => $left != $right,
                '>' => $comparison > 0,
                '>=' => $comparison >= 0,
                '<' => $comparison < 0,
                '<=' => $comparison <= 0,
                default => false,
            };
        }

        throw new \InvalidArgumentException("SQLite UPDATE/DELETE WHERE predicate is not supported: {$term}");
    }

    /**
     * @return list<string>|array<string,string|callable(array<string,mixed>):mixed>
     */
    private static function returningProjection(string $sql): array
    {
        $projection = [];
        foreach (self::splitComma($sql) as $term) {
            $term = trim($term);
            if ($term === '*') {
                $projection[] = '*';
                continue;
            }
            if (preg_match('/^(.+?)\s+AS\s+([A-Za-z_][A-Za-z0-9_]*)$/is', $term, $match) === 1) {
                $expression = trim($match[1]);
                $alias = $match[2];
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
                    $projection[$alias] = $expression;
                    continue;
                }
                $projection[$alias] = static fn (array $row): mixed => self::evaluateReturningExpression($expression, $row);
                continue;
            }
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $term) !== 1) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE RETURNING expressions require an AS alias');
            }
            $projection[] = $term;
        }

        return $projection;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function evaluateReturningExpression(string $expression, array $row): mixed
    {
        $expression = trim($expression);
        if (preg_match('/^\(([^()]+)\)\s+(NOT\s+)?BETWEEN\s*\((.*?)\)\s+AND\s*\((.*)\)$/is', $expression, $match) === 1) {
            $value = self::rowValue($row, self::rowValueColumns($match[1]));
            $lower = self::rowValueExpressions($match[3], $row);
            $upper = self::rowValueExpressions($match[4], $row);
            $result = self::nullableAnd(
                self::rowValueCompareBoolean($value, '>=', $lower),
                self::rowValueCompareBoolean($value, '<=', $upper),
            );
            if (isset($match[2]) && trim($match[2]) !== '') {
                $result = self::negateNullable($result);
            }

            return $result === null ? null : ($result ? 1 : 0);
        }
        if (preg_match('/^\(([^()]+)\)\s*(=|<>|!=|>=|<=|>|<)\s*\((.*)\)$/s', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row);
            $comparison = self::rowValueCompare($left, $right);
            $result = match ($match[2]) {
                '=' => $comparison === null ? null : $comparison === 0,
                '<>', '!=' => $comparison === null ? null : $comparison !== 0,
                '>' => $comparison === null ? null : $comparison > 0,
                '>=' => $comparison === null ? null : $comparison >= 0,
                '<' => $comparison === null ? null : $comparison < 0,
                '<=' => $comparison === null ? null : $comparison <= 0,
                default => false,
            };

            return $result === null ? null : ($result ? 1 : 0);
        }
        if (preg_match('/^\(([^()]+)\)\s+(NOT\s+)?IN\s*\((.*)\)$/is', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $tuples = self::rowValueTupleList($match[3], $row);
            $result = self::rowValueIn($left, $tuples);
            if (isset($match[2]) && trim($match[2]) !== '') {
                $result = self::negateNullable($result);
            }

            return $result === null ? null : ($result ? 1 : 0);
        }
        if (preg_match('/^\(([^()]+)\)\s+IS\s+(NOT\s+)?\((.*)\)$/is', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row);
            $result = self::rowValueIs($left, $right);
            if (isset($match[2]) && trim($match[2]) !== '') {
                $result = !$result;
            }

            return $result ? 1 : 0;
        }
        if (preg_match('/^\(([^()]+)\)\s+IS\s+(NOT\s+)?DISTINCT\s+FROM\s*\((.*)\)$/is', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row);
            $result = self::rowValueIsDistinctFrom($left, $right);
            if (isset($match[2]) && trim($match[2]) !== '') {
                $result = !$result;
            }

            return $result ? 1 : 0;
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+IS\s+(NOT\s+)?NULL$/i', $expression, $match) === 1) {
            $result = self::column($row, $match[1]) === null;
            if (isset($match[2]) && trim($match[2]) !== '') {
                $result = !$result;
            }

            return $result ? 1 : 0;
        }

        return self::evaluateExpression($expression, $row);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function evaluateExpression(string $expression, array $row): mixed
    {
        $expression = trim($expression);
        $concatParts = self::splitOperator($expression, '||');
        if (count($concatParts) > 1) {
            $pieces = [];
            foreach ($concatParts as $part) {
                $value = self::evaluateExpression($part, $row);
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
                $value = self::evaluateExpression($part, $row);
                if ($value === null) {
                    return null;
                }
                $sum += (int) $value;
            }

            return $sum;
        }
        if (
            preg_match("/^'.*'$/s", $expression) === 1
            || strcasecmp($expression, 'NULL') === 0
            || preg_match('/^-?(?:\d+|\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?$/', $expression) === 1
        ) {
            return self::literal($expression);
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
            return self::column($row, $expression);
        }

        return self::literal($expression);
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
     * @return list<string>
     */
    private static function rowValueColumns(string $sql): array
    {
        $columns = [];
        foreach (self::splitComma($sql) as $column) {
            $column = trim($column);
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value columns must be identifiers');
            }
            $columns[] = $column;
        }
        if (count($columns) < 2) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value expressions need at least two columns');
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return list<mixed>
     */
    private static function rowValue(array $row, array $columns): array
    {
        return array_map(static fn (string $column): mixed => self::column($row, $column), $columns);
    }

    /**
     * @param array<string,mixed> $row
     * @return list<mixed>
     */
    private static function rowValueExpressions(string $sql, array $row): array
    {
        $values = array_map(
            static fn (string $expression): mixed => self::evaluateExpression(trim($expression), $row),
            self::splitComma($sql),
        );
        if (count($values) < 2) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value expressions need at least two values');
        }

        return $values;
    }

    /**
     * @param array<string,mixed> $row
     * @return list<list<mixed>>
     */
    private static function rowValueTupleList(string $sql, array $row): array
    {
        $tuples = [];
        foreach (self::splitComma($sql) as $tuple) {
            $tuple = trim($tuple);
            if (!str_starts_with($tuple, '(') || !str_ends_with($tuple, ')')) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value IN list must contain row tuples');
            }
            $tuples[] = self::rowValueExpressions(substr($tuple, 1, -1), $row);
        }

        return $tuples;
    }

    /**
     * @param list<mixed> $left
     * @param list<list<mixed>> $tuples
     */
    private static function rowValueIn(array $left, array $tuples): ?bool
    {
        $unknown = false;
        foreach ($tuples as $right) {
            $comparison = self::rowValueCompare($left, $right);
            if ($comparison === 0) {
                return true;
            }
            if ($comparison === null) {
                $unknown = true;
            }
        }

        return $unknown ? null : false;
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function rowValueIs(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value arity mismatch');
        }
        foreach ($left as $index => $leftValue) {
            $rightValue = $right[$index];
            if ($leftValue === null || $rightValue === null) {
                if ($leftValue !== null || $rightValue !== null) {
                    return false;
                }
                continue;
            }
            if ($leftValue != $rightValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function rowValueIsDistinctFrom(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value arity mismatch');
        }
        foreach ($left as $index => $leftValue) {
            $rightValue = $right[$index];
            if ($leftValue === null || $rightValue === null) {
                if ($leftValue !== $rightValue) {
                    return true;
                }
                continue;
            }
            if (self::compareDistinctValues($leftValue, $rightValue) !== 0) {
                return true;
            }
        }

        return false;
    }

    private static function compareDistinctValues(mixed $left, mixed $right): int
    {
        $leftRank = self::distinctSortRank($left);
        $rightRank = self::distinctSortRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($leftRank === 1) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function distinctSortRank(mixed $value): int
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }

        throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value DISTINCT values must be scalar or NULL');
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function rowValueCompare(array $left, array $right): ?int
    {
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value arity mismatch');
        }
        foreach ($left as $index => $leftValue) {
            $rightValue = $right[$index];
            if ($leftValue === null || $rightValue === null) {
                return null;
            }
            if ($leftValue == $rightValue) {
                continue;
            }

            return $leftValue <=> $rightValue;
        }

        return 0;
    }

    private static function negateNullable(?bool $value): ?bool
    {
        return $value === null ? null : !$value;
    }

    private static function nullableAnd(?bool $left, ?bool $right): ?bool
    {
        if ($left === false || $right === false) {
            return false;
        }
        if ($left === null || $right === null) {
            return null;
        }

        return true;
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function rowValueCompareBoolean(array $left, string $operator, array $right): ?bool
    {
        $comparison = self::rowValueCompare($left, $right);

        return match ($operator) {
            '=' => $comparison === null ? null : $comparison === 0,
            '<>', '!=' => $comparison === null ? null : $comparison !== 0,
            '>' => $comparison === null ? null : $comparison > 0,
            '>=' => $comparison === null ? null : $comparison >= 0,
            '<' => $comparison === null ? null : $comparison < 0,
            '<=' => $comparison === null ? null : $comparison <= 0,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    private static function splitWhereAnd(string $sql): array
    {
        $parts = [];
        $buffer = '';
        $inString = false;
        $depth = 0;
        $betweenNeedsAnd = false;
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
                $buffer .= $char;
                continue;
            }
            if (!$inString && $char === ')') {
                $depth--;
                $buffer .= $char;
                continue;
            }
            if (!$inString && $depth === 0 && self::keywordAt($sql, $i, 'BETWEEN')) {
                $betweenNeedsAnd = true;
                $buffer .= substr($sql, $i, 7);
                $i += 6;
                continue;
            }
            if (!$inString && $depth === 0 && self::keywordAt($sql, $i, 'AND')) {
                if ($betweenNeedsAnd) {
                    $betweenNeedsAnd = false;
                    $buffer .= substr($sql, $i, 3);
                    $i += 2;
                    continue;
                }
                $parts[] = trim($buffer);
                $buffer = '';
                $i += 2;
                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    private static function keywordAt(string $sql, int $offset, string $keyword): bool
    {
        $length = strlen($keyword);
        if (strncasecmp(substr($sql, $offset, $length), $keyword, $length) !== 0) {
            return false;
        }
        $before = $offset === 0 ? ' ' : $sql[$offset - 1];
        $after = $sql[$offset + $length] ?? ' ';

        return !preg_match('/[A-Za-z0-9_]/', $before) && !preg_match('/[A-Za-z0-9_]/', $after);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function column(array $row, string $column): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE column {$column} is missing");
        }

        return $row[$column];
    }

    private static function literal(string $sql): mixed
    {
        if (preg_match("/^'(.*)'$/s", $sql, $match) === 1) {
            return str_replace("''", "'", $match[1]);
        }
        if (strcasecmp($sql, 'NULL') === 0) {
            return null;
        }
        if (preg_match('/^-?\d+$/', $sql) === 1) {
            return (int) $sql;
        }
        if (preg_match('/^-?(?:\d+\.\d*|\.\d+|\d+[eE][+-]?\d+|\d+\.\d*[eE][+-]?\d+|\.\d+[eE][+-]?\d+)$/', $sql) === 1) {
            return (float) $sql;
        }

        throw new \InvalidArgumentException("SQLite UPDATE/DELETE literal is not supported: {$sql}");
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
                $buffer .= $char;
                continue;
            }
            if (!$inString && $char === ')') {
                $depth--;
                if ($depth < 0) {
                    throw new \InvalidArgumentException('SQLite UPDATE/DELETE SQL has unbalanced parentheses');
                }
                $buffer .= $char;
                continue;
            }
            if ($char === ',' && !$inString && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if ($inString) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE SQL has an unterminated string literal');
        }
        if ($depth !== 0) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE SQL has unbalanced parentheses');
        }
        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }
}
