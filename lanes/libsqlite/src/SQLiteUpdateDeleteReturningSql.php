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

        $where = self::wherePredicate($parsed['where'], $tables);
        if ($parsed['action'] === 'delete') {
            $plan = SQLiteUpdateDeleteLimitPlan::delete(
                $tables[$table],
                $where,
                self::orderByCallbacks($parsed['order_by']),
                $parsed['limit'],
                $parsed['offset'],
                $rowIdColumn,
            );
        } else {
            $plan = SQLiteUpdateDeleteLimitPlan::update(
                $tables[$table],
                $where,
                self::assignmentCallbacks($parsed['assignments'], $tables),
                self::orderByCallbacks($parsed['order_by']),
                $parsed['limit'],
                $parsed['offset'],
                $rowIdColumn,
            );
        }

        $projection = self::returningProjection($parsed['returning'], $tables);
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
     * @return array{action:string,table:string,conflict_action:string,assignments:array<string,string>,where:?string,returning:string,order_by:list<array{column?:string,expression?:string,direction?:string}>,limit:?int,offset:int}
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
     * @return array{where:?string,returning:string,order_by:list<array{column:string,direction?:string,nulls?:string}>,limit:?int,offset:int}
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
                $rightHandSql = trim($match[2]);
                if (preg_match('/^SELECT\s+(.+)$/is', $rightHandSql, $selectMatch) === 1) {
                    $selectExpressions = trim($selectMatch[1]);
                    if ($selectExpressions === '') {
                        throw new \InvalidArgumentException('SQLite UPDATE row-value assignment SELECT only supports expression lists');
                    }
                    if (self::keywordPosition(' ' . $selectExpressions, ' FROM ') !== null) {
                        $select = self::parseAssignmentSelect($rightHandSql);
                        if (count($columns) !== count($select['expressions'])) {
                            throw new \InvalidArgumentException('SQLite UPDATE row-value assignment arity mismatch');
                        }
                        foreach ($columns as $index => $column) {
                            if (array_key_exists($column, $assignments)) {
                                throw new \InvalidArgumentException("SQLite UPDATE assignment repeats column {$column}");
                            }
                            $assignments[$column] = ['select' => $select, 'index' => $index];
                        }
                        continue;
                    }
                    $expressions = self::splitComma($selectExpressions);
                } else {
                    $expressions = self::splitComma($rightHandSql);
                }
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
     * @return array{expressions:list<string>,table:string,where:?string,order_by:?string,limit:?string}
     */
    private static function parseAssignmentSelect(string $sql): array
    {
        $sql = trim($sql);
        if (preg_match('/^SELECT\s+(.+?)\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)(.*)$/is', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite UPDATE row-value assignment SELECT only supports simple SELECT expression lists from one table');
        }

        $tail = trim($match[3]);
        $whereSql = null;
        $orderSql = null;
        $limitSql = null;
        $wherePos = self::keywordPosition(' ' . $tail, ' WHERE ');
        $orderPos = self::keywordPosition(' ' . $tail, ' ORDER BY ');
        $limitPos = self::keywordPosition(' ' . $tail, ' LIMIT ');

        if ($wherePos !== null) {
            $whereStart = $wherePos + strlen(' WHERE ') - 1;
            $whereEnd = self::minPosition($orderPos === null ? null : $orderPos - 1, $limitPos === null ? null : $limitPos - 1) ?? strlen($tail);
            $whereSql = trim(substr($tail, $whereStart, $whereEnd - $whereStart));
        }
        if ($orderPos !== null) {
            $orderStart = $orderPos + strlen(' ORDER BY ') - 1;
            $orderEnd = $limitPos !== null && $limitPos > $orderPos ? $limitPos - 1 : strlen($tail);
            $orderSql = trim(substr($tail, $orderStart, $orderEnd - $orderStart));
        }
        if ($limitPos !== null) {
            $limitSql = trim(substr($tail, $limitPos + strlen(' LIMIT ') - 1));
        }

        return [
            'expressions' => self::splitComma(trim($match[1])),
            'table' => $match[2],
            'where' => $whereSql,
            'order_by' => $orderSql,
            'limit' => $limitSql,
        ];
    }

    /**
     * @param array<string,string|array{select:array{expressions:list<string>,table:string,where:?string,order_by:?string,limit:?string},index:int}> $assignments
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,callable(array<string,mixed>):mixed>
     */
    private static function assignmentCallbacks(array $assignments, array $tables = []): array
    {
        $callbacks = [];
        foreach ($assignments as $column => $expression) {
            if (is_array($expression)) {
                $select = $expression['select'];
                $index = $expression['index'];
                $callbacks[$column] = static fn (array $row): mixed => self::assignmentSelectValues($select, $row, $tables)[$index];
                continue;
            }
            $callbacks[$column] = static fn (array $row): mixed => self::evaluateExpression($expression, $row);
        }

        return $callbacks;
    }

    /**
     * @param array{expressions:list<string>,table:string,where:?string,order_by:?string,limit:?string} $select
     * @param array<string,mixed> $row
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<mixed>
     */
    private static function assignmentSelectValues(array $select, array $row, array $tables): array
    {
        $table = $select['table'];
        if (!isset($tables[$table]) || !is_array($tables[$table]) || !array_is_list($tables[$table])) {
            throw new \InvalidArgumentException("SQLite UPDATE row-value assignment SELECT table {$table} is missing");
        }

        $sourceRows = [];
        foreach ($tables[$table] as $sourceRow) {
            if (!is_array($sourceRow)) {
                throw new \InvalidArgumentException("SQLite UPDATE row-value assignment SELECT table {$table} rows must be arrays");
            }
            $combined = array_merge($row, $sourceRow);
            if ($select['where'] !== null && $select['where'] !== '') {
                $predicate = self::wherePredicate($select['where'], $tables)($combined);
                if ($predicate !== true) {
                    continue;
                }
            }
            $sourceRows[] = $combined;
        }

        if ($select['order_by'] !== null && $select['order_by'] !== '') {
            $sourceRows = self::orderRowValueSelectRows($sourceRows, $select['order_by'], $select['expressions']);
        }
        if ($select['limit'] !== null && $select['limit'] !== '') {
            [$limit, $offset] = self::parseLimit($select['limit']);
            $offset = max(0, $offset);
            $sourceRows = $limit !== null && $limit >= 0
                ? array_slice($sourceRows, $offset, $limit)
                : array_slice($sourceRows, $offset);
        }
        if ($sourceRows === []) {
            return array_fill(0, count($select['expressions']), null);
        }

        $values = [];
        foreach ($select['expressions'] as $expression) {
            $values[] = self::evaluateExpression(trim($expression), $sourceRows[0]);
        }

        return $values;
    }

    /**
     * @return list<array{column?:string,expression?:string,direction?:string,nulls?:string}>
     */
    private static function parseOrderBy(string $sql): array
    {
        if ($sql === '') {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE ORDER BY must not be empty');
        }

        $terms = [];
        foreach (self::splitComma($sql) as $term) {
            if (preg_match('/^(.+?)(?:\s+(ASC|DESC))?(?:\s+NULLS\s+(FIRST|LAST))?$/is', trim($term), $match) !== 1) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE ORDER BY terms are malformed');
            }
            $expression = trim($match[1]);
            if ($expression === '') {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE ORDER BY terms must not be empty');
            }
            $entry = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1
                ? ['column' => $expression]
                : ['expression' => $expression];
            if (isset($match[2]) && $match[2] !== '') {
                $entry['direction'] = strtoupper($match[2]);
            }
            if (isset($match[3]) && $match[3] !== '') {
                $entry['nulls'] = strtoupper($match[3]);
            }
            $terms[] = $entry;
        }

        return $terms;
    }

    /**
     * @param list<array{column?:string,expression?:string,direction?:string,nulls?:string}> $terms
     * @return list<array{column:string,direction?:string,nulls?:string,expression?:string,value?:callable(array<string,mixed>):mixed}>
     */
    private static function orderByCallbacks(array $terms): array
    {
        $prepared = [];
        foreach ($terms as $index => $term) {
            if (isset($term['column'])) {
                $entry = ['column' => $term['column']];
            } elseif (isset($term['expression'])) {
                $expression = $term['expression'];
                $entry = [
                    'column' => '__sqlite_udl_order_' . $index,
                    'expression' => $expression,
                    'value' => static fn (array $row): mixed => self::evaluateExpression($expression, $row),
                ];
            } else {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE ORDER BY term needs a column or expression');
            }
            if (isset($term['direction'])) {
                $entry['direction'] = $term['direction'];
            }
            if (isset($term['nulls'])) {
                $entry['nulls'] = $term['nulls'];
            }
            $prepared[] = $entry;
        }

        return $prepared;
    }

    /**
     * @return array{0:?int,1:int}
     */
    private static function parseLimit(string $sql): array
    {
        $commaParts = self::splitComma($sql);
        if (count($commaParts) === 2) {
            return [self::limitInteger($commaParts[1]), self::limitInteger($commaParts[0])];
        }
        if (preg_match('/^(.+?)(?:\s+OFFSET\s+(.+))?$/i', $sql, $match) === 1) {
            $limit = self::limitInteger(trim($match[1]));
            $offset = isset($match[2]) ? self::limitInteger(trim($match[2])) : 0;

            return [$limit, $offset];
        }

        throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT must be an integer expression, integer OFFSET integer, or offset,count');
    }

    private static function limitInteger(string $expression): int
    {
        $value = self::limitExpressionValue($expression);
        if (is_string($value)) {
            if (preg_match('/^-?\d+$/', $value) === 1) {
                $value = (int) $value;
            } elseif (preg_match('/^-?(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?$/', $value) === 1) {
                $value = (float) $value;
            }
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }
        if (!is_int($value)) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT expressions must evaluate to an integer');
        }

        return $value;
    }

    private static function limitExpressionValue(string $expression): int|float|string|null
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT expression must not be empty');
        }
        while (($stripped = self::stripEnclosingParentheses($expression)) !== null) {
            $expression = $stripped;
        }
        $logical = self::evaluateLimitLogicalExpression($expression);
        if ($logical['matched']) {
            return $logical['value'] === null ? null : ($logical['value'] ? 1 : 0);
        }
        $concatParts = self::splitOperator($expression, '||');
        if (count($concatParts) > 1) {
            $pieces = [];
            foreach ($concatParts as $part) {
                $value = self::limitExpressionValue($part);
                if ($value === null) {
                    return null;
                }
                $pieces[] = (string) $value;
            }

            return implode('', $pieces);
        }
        if (preg_match("/^'.*'$/s", $expression) === 1) {
            return self::literal($expression);
        }
        if (preg_match('/^SELECT\s+(.+)$/is', $expression, $match) === 1) {
            $selectExpression = trim($match[1]);
            if ($selectExpression === '' || self::keywordPosition(' ' . $selectExpression, ' FROM ') !== null) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT scalar SELECT only supports constant expressions');
            }

            return self::limitExpressionValue($selectExpression);
        }
        if (strcasecmp($expression, 'NULL') === 0 || preg_match('/^X\'[0-9A-F]*\'$/i', $expression) === 1) {
            return null;
        }
        if (strcasecmp($expression, 'TRUE') === 0) {
            return 1;
        }
        if (strcasecmp($expression, 'FALSE') === 0) {
            return 0;
        }
        if (preg_match('/^CAST\s*\((.+)\s+AS\s+([A-Za-z]+)\s*\)$/is', $expression, $match) === 1) {
            $value = self::limitExpressionValue(trim($match[1]));
            return self::castLimitExpressionValue($value, strtoupper($match[2]));
        }
        if (str_starts_with($expression, '+')) {
            return self::limitExpressionValue(substr($expression, 1));
        }
        if (str_starts_with($expression, '-')) {
            return -self::limitNumericValue(substr($expression, 1));
        }
        if (str_starts_with($expression, '~')) {
            return ~self::limitInteger(substr($expression, 1));
        }
        if (preg_match('/^abs\s*\((.+)\)$/is', $expression, $match) === 1) {
            return abs(self::limitNumericValue($match[1]));
        }
        if (preg_match('/^length\s*\((.+)\)$/is', $expression, $match) === 1) {
            $value = self::limitExpressionValue(trim($match[1]));

            return $value === null ? null : self::textLength((string) $value);
        }
        $scalarFunction = self::wholeLimitScalarFunction($expression);
        if (
            $scalarFunction !== null
            && in_array($scalarFunction['name'], ['coalesce', 'ifnull', 'nullif', 'min', 'max', 'round', 'sign', 'ceil', 'ceiling', 'floor', 'trunc', 'sqrt', 'pow', 'power', 'exp', 'ln', 'log', 'log10', 'log2', 'mod', 'acos', 'asin', 'atan', 'atan2', 'cos', 'sin', 'tan', 'pi', 'upper', 'lower', 'trim', 'ltrim', 'rtrim', 'substr', 'substring', 'instr', 'replace', 'char', 'unicode', 'octet_length', 'hex', 'quote', 'typeof', 'printf', 'format', 'iif', 'if', 'likely', 'unlikely', 'likelihood', 'zeroblob', 'randomblob', 'date', 'time', 'datetime', 'julianday', 'unixepoch', 'strftime'], true)
        ) {
            return self::evaluateLimitScalarFunction($scalarFunction['name'], $scalarFunction['arguments']);
        }
        $predicate = self::evaluateLimitPredicateExpression($expression);
        if ($predicate['matched']) {
            return $predicate['value'] === null ? null : ($predicate['value'] ? 1 : 0);
        }
        if (preg_match('/^-?\d+$/', $expression) === 1) {
            return (int) $expression;
        }
        if (preg_match('/^-?0x[0-9A-F]+$/i', $expression) === 1) {
            $negative = str_starts_with($expression, '-');
            $hex = ltrim($expression, '-');
            $value = hexdec(substr($hex, 2));
            if (!is_int($value)) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT hexadecimal literal is out of range');
            }

            return $negative ? -$value : $value;
        }
        if (preg_match('/^-?(?:(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?|\d+[eE][+-]?\d+)$/', $expression) === 1) {
            return (float) $expression;
        }

        foreach (['+', '-'] as $operator) {
            $parts = self::splitLimitOperator($expression, $operator);
            if (count($parts) > 1) {
                $value = self::limitNumericValue(array_shift($parts));
                foreach ($parts as $part) {
                    $right = self::limitNumericValue($part);
                    $value = $operator === '+' ? $value + $right : $value - $right;
                }

                return $value;
            }
        }
        foreach (['*', '/'] as $operator) {
            $parts = self::splitLimitOperator($expression, $operator);
            if (count($parts) > 1) {
                $value = self::limitNumericValue(array_shift($parts));
                foreach ($parts as $part) {
                    $right = self::limitNumericValue($part);
                    if ($operator === '/' && $right == 0) {
                        throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT division by zero');
                    }
                    $value = $operator === '*' ? $value * $right : $value / $right;
                }

                return $value;
            }
        }
        foreach (['<<', '>>', '&', '|', '%'] as $operator) {
            $parts = self::splitLimitOperator($expression, $operator);
            if (count($parts) > 1) {
                $value = self::limitInteger(array_shift($parts));
                foreach ($parts as $part) {
                    $right = self::limitInteger($part);
                    if ($operator === '%' && $right === 0) {
                        throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT modulo by zero');
                    }
                    $value = match ($operator) {
                        '<<' => $value << $right,
                        '>>' => $value >> $right,
                        '&' => $value & $right,
                        '|' => $value | $right,
                        '%' => $value % $right,
                    };
                }

                return $value;
            }
        }

        try {
            $value = self::evaluateExpression($expression, []);
        } catch (\InvalidArgumentException) {
            $value = null;
        }
        if (is_int($value) || is_float($value) || is_string($value) || $value === null) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT expression is not supported');
    }

    /**
     * @return array{matched:bool,value:?bool}
     */
    private static function evaluateLimitLogicalExpression(string $expression): array
    {
        $orParts = self::splitWhereOr($expression);
        if (count($orParts) > 1) {
            $sawNull = false;
            foreach ($orParts as $part) {
                $truth = self::sqliteTruthValue(self::limitExpressionValue($part));
                if ($truth === true) {
                    return ['matched' => true, 'value' => true];
                }
                if ($truth === null) {
                    $sawNull = true;
                }
            }

            return ['matched' => true, 'value' => $sawNull ? null : false];
        }

        $andParts = self::splitWhereAnd($expression);
        if (count($andParts) > 1) {
            $sawNull = false;
            foreach ($andParts as $part) {
                $truth = self::sqliteTruthValue(self::limitExpressionValue($part));
                if ($truth === false) {
                    return ['matched' => true, 'value' => false];
                }
                if ($truth === null) {
                    $sawNull = true;
                }
            }

            return ['matched' => true, 'value' => $sawNull ? null : true];
        }

        return ['matched' => false, 'value' => null];
    }

    /**
     * @return array{matched:bool,value:?bool}
     */
    private static function evaluateLimitPredicateExpression(string $expression): array
    {
        $not = self::unwrapUnaryNot($expression);
        if ($not !== null) {
            $inner = self::evaluateLimitPredicateExpression($not);
            if (!$inner['matched']) {
                $value = self::limitExpressionValue($not);
                return ['matched' => true, 'value' => self::sqliteTruthValue($value) === true ? false : true];
            }

            return ['matched' => true, 'value' => self::negateNullable($inner['value'])];
        }

        $truth = self::splitLimitTruthPredicate($expression);
        if ($truth !== null) {
            $value = self::limitExpressionValue($truth['value']);
            $truthValue = self::sqliteTruthValue($value);
            $result = match ($truth['predicate']) {
                'TRUE' => $truthValue === true,
                'FALSE' => $truthValue === false,
            };

            return ['matched' => true, 'value' => $truth['not'] ? !$result : $result];
        }

        $between = self::splitLimitBetween($expression);
        if ($between !== null) {
            $value = self::limitExpressionValue($between['value']);
            $lower = self::limitExpressionValue($between['lower']);
            $upper = self::limitExpressionValue($between['upper']);
            if ($value === null || $lower === null || $upper === null) {
                $result = null;
            } else {
                $result = self::compareLimitScalarValues($value, $lower) >= 0
                    && self::compareLimitScalarValues($value, $upper) <= 0;
            }

            return ['matched' => true, 'value' => $between['not'] ? self::negateNullable($result) : $result];
        }

        $in = self::splitLimitInPredicate($expression);
        if ($in !== null) {
            $left = self::limitExpressionValue($in['value']);
            $values = array_map(static fn (string $part): int|float|string|null => self::limitExpressionValue($part), self::splitComma($in['list']));
            if ($left === null || in_array(null, $values, true)) {
                $result = null;
            } else {
                $result = false;
                foreach ($values as $value) {
                    if (self::limitScalarEquals($left, $value)) {
                        $result = true;
                        break;
                    }
                }
            }

            return ['matched' => true, 'value' => $in['not'] ? self::negateNullable($result) : $result];
        }

        foreach (['IS NOT', 'IS', '<>', '!=', '>=', '<=', '=', '>', '<'] as $operator) {
            $parts = self::splitLimitComparison($expression, $operator);
            if ($parts === null) {
                continue;
            }
            $left = self::limitExpressionValue($parts[0]);
            $right = self::limitExpressionValue($parts[1]);
            if ($operator === 'IS' || $operator === 'IS NOT') {
                $result = $left === null ? $right === null : ($right !== null && self::limitScalarEquals($left, $right));
                return ['matched' => true, 'value' => $operator === 'IS NOT' ? !$result : $result];
            }
            if ($left === null || $right === null) {
                return ['matched' => true, 'value' => null];
            }
            $comparison = self::compareLimitScalarValues($left, $right);
            $equals = self::limitScalarEquals($left, $right);
            $result = match ($operator) {
                '=' => $equals,
                '<>', '!=' => !$equals,
                '>' => $comparison > 0,
                '>=' => $comparison >= 0,
                '<' => $comparison < 0,
                '<=' => $comparison <= 0,
                default => false,
            };

            return ['matched' => true, 'value' => $result];
        }

        return ['matched' => false, 'value' => null];
    }

    /**
     * @return array{value:string,predicate:string,not:bool}|null
     */
    private static function splitLimitTruthPredicate(string $sql): ?array
    {
        foreach (['IS NOT TRUE', 'IS NOT FALSE', 'IS TRUE', 'IS FALSE'] as $keyword) {
            $position = self::topLevelKeywordPosition($sql, $keyword);
            if ($position === null) {
                continue;
            }

            $value = trim(substr($sql, 0, $position));
            if ($value === '') {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT IS TRUE/FALSE needs a left operand');
            }

            return [
                'value' => $value,
                'predicate' => str_ends_with($keyword, 'TRUE') ? 'TRUE' : 'FALSE',
                'not' => str_contains($keyword, 'NOT'),
            ];
        }

        return null;
    }

    private static function limitNumericValue(string $expression): int|float
    {
        $value = self::limitExpressionValue($expression);
        if (is_string($value)) {
            if (preg_match('/^-?\d+$/', $value) === 1) {
                $value = (int) $value;
            } elseif (preg_match('/^-?(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?$/', $value) === 1) {
                $value = (float) $value;
            }
        }
        if (!is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT arithmetic terms must be numeric');
        }

        return $value;
    }

    private static function castLimitExpressionValue(int|float|string|null $value, string $type): int|float|string|null
    {
        if ($value === null) {
            return null;
        }

        if ($type === 'INT' || $type === 'INTEGER') {
            return (int) $value;
        }
        if ($type === 'REAL' || $type === 'FLOAT' || $type === 'DOUBLE') {
            return (float) $value;
        }
        if ($type === 'TEXT' || $type === 'CHAR' || $type === 'CLOB') {
            return (string) $value;
        }
        if ($type === 'NUMERIC') {
            if (is_int($value)) {
                return $value;
            }
            $numeric = is_string($value) ? (float) $value : $value;
            return floor($numeric) === $numeric ? (int) $numeric : $numeric;
        }
        if ($type === 'BLOB' || $type === 'NONE') {
            return null;
        }

        throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT CAST type {$type} is not supported");
    }

    private static function evaluateLimitScalarFunction(string $function, string $arguments): int|float|string|null
    {
        $parts = self::splitComma($arguments);
        if ($function === 'ifnull' && count($parts) !== 2) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT ifnull() needs two arguments');
        }
        if ($function === 'nullif' && count($parts) !== 2) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT nullif() needs two arguments');
        }
        if ($function === 'coalesce' && count($parts) < 2) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT coalesce() needs at least two arguments');
        }
        if (($function === 'min' || $function === 'max') && $parts === []) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs at least one argument");
        }
        if ($function === 'round' && count($parts) !== 1 && count($parts) !== 2) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT round() needs one or two arguments');
        }
        if ($function === 'sign' && count($parts) !== 1) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT sign() needs one argument');
        }
        if (($function === 'ceil' || $function === 'ceiling' || $function === 'floor' || $function === 'trunc' || $function === 'sqrt') && count($parts) !== 1) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs one argument");
        }
        if (($function === 'pow' || $function === 'power') && count($parts) !== 2) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs two arguments");
        }
        if ($function === 'instr' && count($parts) !== 2) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT instr() needs two arguments');
        }
        if ($function === 'replace' && count($parts) !== 3) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT replace() needs three arguments');
        }
        if ($function === 'char' && count($parts) < 1) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT char() needs at least one argument');
        }
        if (($function === 'zeroblob' || $function === 'randomblob') && count($parts) !== 1) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs one argument");
        }
        if ($function === 'unicode' && count($parts) !== 1) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT unicode() needs one argument');
        }
        if ($function === 'octet_length' && count($parts) !== 1) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT octet_length() needs one argument');
        }
        if ($function === 'hex' && count($parts) !== 1) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT hex() needs one argument');
        }
        if (($function === 'quote' || $function === 'typeof') && count($parts) !== 1) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs one argument");
        }
        if (($function === 'printf' || $function === 'format') && count($parts) < 1) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs a format argument");
        }
        if (($function === 'iif' || $function === 'if') && count($parts) !== 3) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs three arguments");
        }
        if (($function === 'likely' || $function === 'unlikely') && count($parts) !== 1) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs one argument");
        }
        if ($function === 'likelihood' && count($parts) !== 2) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT likelihood() needs two arguments');
        }
        if (($function === 'upper' || $function === 'lower') && count($parts) !== 1) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs one argument");
        }
        if (($function === 'trim' || $function === 'ltrim' || $function === 'rtrim') && count($parts) !== 1 && count($parts) !== 2) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs one or two arguments");
        }
        if (($function === 'substr' || $function === 'substring') && count($parts) !== 2 && count($parts) !== 3) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() needs two or three arguments");
        }
        if (in_array($function, ['date', 'time', 'datetime', 'julianday', 'unixepoch', 'strftime'], true)) {
            $values = array_map(static fn (string $part): int|float|string|null => self::limitExpressionValue($part), $parts);
            $value = SQLiteCoreScalarFunction::sqlFunctionArguments($function, $values);
            if (!is_int($value) && !is_float($value) && !is_string($value) && $value !== null) {
                throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() returned an unsupported value");
            }

            return $value;
        }
        if (in_array($function, ['exp', 'ln', 'log', 'log10', 'log2', 'mod', 'acos', 'asin', 'atan', 'atan2', 'cos', 'sin', 'tan', 'pi'], true)) {
            $values = array_map(static fn (string $part): int|float|string|null => self::limitExpressionValue($part), $parts);
            $value = SQLiteCoreScalarFunction::sqlFunctionArguments($function, $values);
            if (!is_int($value) && !is_float($value) && !is_string($value) && $value !== null) {
                throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() returned an unsupported value");
            }

            return $value;
        }

        if ($function === 'iif' || $function === 'if') {
            $condition = self::sqliteTruthValue(self::limitExpressionValue($parts[0]));

            return $condition === true
                ? self::limitExpressionValue($parts[1])
                : self::limitExpressionValue($parts[2]);
        }
        if ($function === 'likely' || $function === 'unlikely' || $function === 'likelihood') {
            if ($function === 'likelihood') {
                $probability = self::limitExpressionValue($parts[1]);
                if (!is_int($probability) && !is_float($probability) && !(is_string($probability) && is_numeric($probability))) {
                    throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT likelihood() probability must be numeric');
                }
            }

            return self::limitExpressionValue($parts[0]);
        }

        $values = array_map(static fn (string $part): int|float|string|null => self::limitExpressionValue($part), $parts);
        if ($function === 'upper' || $function === 'lower') {
            if ($values[0] === null) {
                return null;
            }

            return $function === 'upper' ? strtoupper((string) $values[0]) : strtolower((string) $values[0]);
        }
        if ($function === 'trim' || $function === 'ltrim' || $function === 'rtrim') {
            if ($values[0] === null || (array_key_exists(1, $values) && $values[1] === null)) {
                return null;
            }
            $value = (string) $values[0];
            $characters = array_key_exists(1, $values) ? (string) $values[1] : " \t\n\r\0\x0B";

            return match ($function) {
                'ltrim' => ltrim($value, $characters),
                'rtrim' => rtrim($value, $characters),
                default => trim($value, $characters),
            };
        }
        if ($function === 'substr' || $function === 'substring') {
            if ($values[0] === null || $values[1] === null || (array_key_exists(2, $values) && $values[2] === null)) {
                return null;
            }
            $start = self::integerFunctionArgument($values[1], $function, 'start');
            $length = array_key_exists(2, $values) ? self::integerFunctionArgument($values[2], $function, 'length') : null;

            return self::sqliteSubstring((string) $values[0], $start, $length);
        }
        if ($function === 'sign') {
            if ($values[0] === null) {
                return null;
            }
            if (!is_int($values[0]) && !is_float($values[0]) && !(is_string($values[0]) && is_numeric($values[0]))) {
                return null;
            }

            return (float) $values[0] <=> 0.0;
        }
        if ($function === 'ceil' || $function === 'ceiling' || $function === 'floor' || $function === 'trunc' || $function === 'sqrt') {
            if ($values[0] === null) {
                return null;
            }
            if (!is_int($values[0]) && !is_float($values[0]) && !(is_string($values[0]) && is_numeric($values[0]))) {
                throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() value must be numeric");
            }
            $value = (float) $values[0];
            if ($function === 'sqrt' && $value < 0.0) {
                return null;
            }

            return match ($function) {
                'ceil', 'ceiling' => ceil($value),
                'floor' => floor($value),
                'trunc' => $value < 0.0 ? ceil($value) : floor($value),
                'sqrt' => sqrt($value),
                default => throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() is not supported"),
            };
        }
        if ($function === 'pow' || $function === 'power') {
            foreach ([0, 1] as $index) {
                if ($values[$index] === null) {
                    return null;
                }
                if (!is_int($values[$index]) && !is_float($values[$index]) && !(is_string($values[$index]) && is_numeric($values[$index]))) {
                    throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() arguments must be numeric");
                }
            }

            return pow((float) $values[0], (float) $values[1]);
        }
        if ($function === 'instr') {
            if ($values[0] === null || $values[1] === null) {
                return null;
            }

            return self::sqliteTextPosition((string) $values[0], (string) $values[1]);
        }
        if ($function === 'replace') {
            if ($values[0] === null || $values[1] === null || $values[2] === null) {
                return null;
            }

            return str_replace((string) $values[1], (string) $values[2], (string) $values[0]);
        }
        if ($function === 'char') {
            $characters = [];
            foreach ($values as $value) {
                if ($value === null) {
                    $characters[] = "\0";
                    continue;
                }
                $codepoint = self::integerFunctionArgument($value, $function, 'codepoint');
                $characters[] = self::sqliteCharacter($codepoint);
            }

            return implode('', $characters);
        }
        if ($function === 'zeroblob' || $function === 'randomblob') {
            if ($values[0] === null) {
                return null;
            }
            $length = self::integerFunctionArgument($values[0], $function, 'length');
            if ($length < 0) {
                $length = $function === 'randomblob' ? 1 : 0;
            }
            if ($function === 'randomblob' && $length === 0) {
                $length = 1;
            }

            return str_repeat("\0", $length);
        }
        if ($function === 'unicode') {
            if ($values[0] === null) {
                return null;
            }

            return self::sqliteUnicodeCodepoint((string) $values[0]);
        }
        if ($function === 'octet_length') {
            if ($values[0] === null) {
                return null;
            }

            return strlen((string) $values[0]);
        }
        if ($function === 'hex') {
            if ($values[0] === null) {
                return '';
            }

            return strtoupper(bin2hex((string) $values[0]));
        }
        if ($function === 'quote') {
            return self::quoteLimitValue($values[0]);
        }
        if ($function === 'typeof') {
            return self::typeofLimitValue($values[0]);
        }
        if ($function === 'printf' || $function === 'format') {
            return self::formatLimitValue($function, $values);
        }
        if ($function === 'round') {
            if ($values[0] === null) {
                return null;
            }
            if (!is_int($values[0]) && !is_float($values[0]) && !(is_string($values[0]) && is_numeric($values[0]))) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT round() value must be numeric');
            }
            $precision = 0;
            if (array_key_exists(1, $values)) {
                if ($values[1] === null) {
                    return null;
                }
                if (!is_int($values[1]) && !is_float($values[1]) && !(is_string($values[1]) && is_numeric($values[1]))) {
                    throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT round() precision must be numeric');
                }
                $precision = max(0, (int) $values[1]);
            }

            return round((float) $values[0], $precision);
        }
        if ($function === 'nullif') {
            return $values[0] == $values[1] ? null : $values[0];
        }
        if ($function === 'min' || $function === 'max') {
            $selected = array_shift($values);
            if ($selected === null) {
                return null;
            }
            foreach ($values as $value) {
                if ($value === null) {
                    return null;
                }
                $comparison = self::compareLimitScalarValues($value, $selected);
                if (($function === 'min' && $comparison < 0) || ($function === 'max' && $comparison > 0)) {
                    $selected = $value;
                }
            }

            return $selected;
        }

        foreach ($values as $value) {
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private static function quoteLimitValue(int|float|string|null $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }

    private static function typeofLimitValue(int|float|string|null $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'real';
        }

        return 'text';
    }

    /**
     * @param list<int|float|string|null> $values
     */
    private static function formatLimitValue(string $function, array $values): string
    {
        $format = array_shift($values);
        if ($format === null) {
            return '';
        }
        if (!is_int($format) && !is_float($format) && !is_string($format)) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() format must be scalar");
        }

        [$sqliteFormat, $values] = self::expandSqliteRepeatedCharacterFormats((string) $format, $values, $function);
        $phpFormat = str_replace(['%i', '%q', '%Q'], ['%d', '%s', '%s'], $sqliteFormat);
        try {
            return sprintf($phpFormat, ...array_map(
                static fn (int|float|string|null $value): int|float|string => $value ?? 0,
                $values,
            ));
        } catch (\ArgumentCountError|\ValueError $exception) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() format is malformed", 0, $exception);
        }
    }

    /**
     * SQLite's printf()/format() treats precision on %c as a repeat count.
     *
     * @param list<int|float|string|null> $values
     * @return array{0:string,1:list<int|float|string|null>}
     */
    private static function expandSqliteRepeatedCharacterFormats(string $format, array $values, string $function): array
    {
        $expanded = '';
        $expandedValues = [];
        $argumentIndex = 0;
        $length = strlen($format);

        for ($i = 0; $i < $length; $i++) {
            $char = $format[$i];
            if ($char !== '%' || ($format[$i + 1] ?? null) === '%') {
                $expanded .= $char;
                if ($char === '%' && ($format[$i + 1] ?? null) === '%') {
                    $expanded .= '%';
                    $i++;
                }
                continue;
            }

            $start = $i;
            $i++;
            while ($i < $length && str_contains('-+ #0,', $format[$i])) {
                $i++;
            }
            while ($i < $length && ctype_digit($format[$i])) {
                $i++;
            }

            $precision = null;
            $precisionFromArgument = false;
            if (($format[$i] ?? null) === '.') {
                $i++;
                if (($format[$i] ?? null) === '*') {
                    $precisionFromArgument = true;
                    $i++;
                } else {
                    $digitsStart = $i;
                    while ($i < $length && ctype_digit($format[$i])) {
                        $i++;
                    }
                    $precision = $digitsStart === $i ? 0 : (int) substr($format, $digitsStart, $i - $digitsStart);
                }
            }

            while ($i < $length && str_contains('hlLzjt', $format[$i])) {
                $i++;
            }
            $type = $format[$i] ?? '';
            $specifier = substr($format, $start, $i - $start + 1);

            if ($type === 'c' && ($precisionFromArgument || $precision !== null)) {
                $repeat = $precision ?? 0;
                if ($precisionFromArgument) {
                    if (!array_key_exists($argumentIndex, $values)) {
                        throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() missing %c precision argument");
                    }
                    $repeat = self::integerFunctionArgument($values[$argumentIndex] ?? 0, $function, 'precision');
                    $argumentIndex++;
                }
                if (!array_key_exists($argumentIndex, $values)) {
                    throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() missing %c argument");
                }
                $value = $values[$argumentIndex];
                $argumentIndex++;

                $expanded .= '%s';
                $expandedValues[] = str_repeat(self::formatCharacterValue($value), max(0, $repeat));
                continue;
            }

            $expanded .= $specifier;
            if (!in_array($type, ['%', ''], true)) {
                if (!array_key_exists($argumentIndex, $values)) {
                    throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() missing format argument");
                }
                $expandedValues[] = $values[$argumentIndex];
                $argumentIndex++;
            }
        }

        while (array_key_exists($argumentIndex, $values)) {
            $expandedValues[] = $values[$argumentIndex];
            $argumentIndex++;
        }

        return [$expanded, $expandedValues];
    }

    private static function formatCharacterValue(int|float|string|null $value): string
    {
        if ($value === null) {
            return "\0";
        }

        $text = (string) $value;
        if ($text === '') {
            return "\0";
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters !== false && $characters !== []) {
            return $characters[0];
        }

        return $text[0];
    }

    private static function integerFunctionArgument(int|float|string $value, string $function, string $name): int
    {
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (is_int($value)) {
            return $value;
        }

        throw new \InvalidArgumentException("SQLite UPDATE/DELETE LIMIT {$function}() {$name} must be an integer");
    }

    private static function sqliteSubstring(string $value, int $start, ?int $length): string
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            $characters = str_split($value);
        }

        $count = count($characters);
        $offset = $start > 0 ? $start - 1 : ($start < 0 ? max(0, $count + $start) : 0);
        $slice = $length === null ? array_slice($characters, $offset) : array_slice($characters, $offset, max(0, $length));

        return implode('', $slice);
    }

    private static function sqliteTextPosition(string $haystack, string $needle): int
    {
        if ($needle === '') {
            return 1;
        }

        $position = strpos($haystack, $needle);

        return $position === false ? 0 : $position + 1;
    }

    private static function sqliteCharacter(int $codepoint): string
    {
        if ($codepoint <= 0) {
            return "\0";
        }
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7FF) {
            return chr(0xC0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3F));
        }
        if ($codepoint <= 0xFFFF) {
            return chr(0xE0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F));
        }
        if ($codepoint <= 0x10FFFF) {
            return chr(0xF0 | ($codepoint >> 18))
                . chr(0x80 | (($codepoint >> 12) & 0x3F))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F));
        }

        return "\xEF\xBF\xBD";
    }

    private static function sqliteUnicodeCodepoint(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $byte0 = ord($value[0]);
        if ($byte0 < 0x80) {
            return $byte0;
        }
        if (($byte0 & 0xE0) === 0xC0 && isset($value[1])) {
            return (($byte0 & 0x1F) << 6) | (ord($value[1]) & 0x3F);
        }
        if (($byte0 & 0xF0) === 0xE0 && isset($value[2])) {
            return (($byte0 & 0x0F) << 12) | ((ord($value[1]) & 0x3F) << 6) | (ord($value[2]) & 0x3F);
        }
        if (($byte0 & 0xF8) === 0xF0 && isset($value[3])) {
            return (($byte0 & 0x07) << 18) | ((ord($value[1]) & 0x3F) << 12) | ((ord($value[2]) & 0x3F) << 6) | (ord($value[3]) & 0x3F);
        }

        return $byte0;
    }

    private static function compareLimitScalarValues(int|float|string $left, int|float|string $right): int
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }
        if (is_string($left) && is_numeric($left) && (is_int($right) || is_float($right))) {
            return (float) $left <=> $right;
        }
        if ((is_int($left) || is_float($left)) && is_string($right) && is_numeric($right)) {
            return $left <=> (float) $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function limitScalarEquals(int|float|string $left, int|float|string $right): bool
    {
        if ((is_int($left) || is_float($left) || is_numeric((string) $left))
            && (is_int($right) || is_float($right) || is_numeric((string) $right))) {
            return (float) $left == (float) $right;
        }

        return (string) $left === (string) $right;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function splitLimitComparison(string $sql, string $operator): ?array
    {
        $parts = self::splitLimitOperator($sql, $operator);
        if (count($parts) !== 2) {
            return null;
        }
        if ($parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT comparison needs both operands');
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @return array{value:string,lower:string,upper:string,not:bool}|null
     */
    private static function splitLimitBetween(string $sql): ?array
    {
        $between = self::topLevelKeywordPosition($sql, 'BETWEEN');
        $not = false;
        $notBetween = self::topLevelKeywordPosition($sql, 'NOT BETWEEN');
        if ($notBetween !== null && ($between === null || $notBetween <= $between)) {
            $between = $notBetween;
            $not = true;
        }
        if ($between === null) {
            return null;
        }
        $keyword = $not ? 'NOT BETWEEN' : 'BETWEEN';
        $value = trim(substr($sql, 0, $between));
        $tail = trim(substr($sql, $between + strlen($keyword)));
        $and = self::topLevelKeywordPosition($tail, 'AND');
        if ($value === '' || $and === null) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT BETWEEN needs lower and upper operands');
        }
        $lower = trim(substr($tail, 0, $and));
        $upper = trim(substr($tail, $and + strlen('AND')));
        if ($lower === '' || $upper === '') {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT BETWEEN needs lower and upper operands');
        }

        return ['value' => $value, 'lower' => $lower, 'upper' => $upper, 'not' => $not];
    }

    /**
     * @return array{value:string,list:string,not:bool}|null
     */
    private static function splitLimitInPredicate(string $sql): ?array
    {
        $in = self::topLevelKeywordPosition($sql, 'IN');
        $not = false;
        $notIn = self::topLevelKeywordPosition($sql, 'NOT IN');
        if ($notIn !== null && ($in === null || $notIn <= $in)) {
            $in = $notIn;
            $not = true;
        }
        if ($in === null) {
            return null;
        }
        $keyword = $not ? 'NOT IN' : 'IN';
        $value = trim(substr($sql, 0, $in));
        $list = trim(substr($sql, $in + strlen($keyword)));
        $stripped = self::stripEnclosingParentheses($list);
        if ($value === '' || $stripped === null) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE LIMIT IN needs a parenthesized value list');
        }

        return ['value' => $value, 'list' => $stripped, 'not' => $not];
    }

    private static function topLevelKeywordPosition(string $sql, string $keyword): ?int
    {
        $position = self::keywordPosition(' ' . $sql . ' ', ' ' . $keyword . ' ');

        return $position;
    }

    /**
     * @return list<string>
     */
    private static function splitLimitOperator(string $sql, string $operator): array
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
                $previous = $buffer === '' ? '' : substr(rtrim($buffer), -1);
                $next = $sql[$i + $operatorLength] ?? '';
                if (($operator === '+' || $operator === '-') && ($previous === 'e' || $previous === 'E') && preg_match('/\d/', $next) === 1) {
                    $beforeExponent = substr(rtrim($buffer), 0, -1);
                    $exponentBaseTail = $beforeExponent === '' ? '' : substr($beforeExponent, -1);
                    if ($exponentBaseTail !== '' && (ctype_digit($exponentBaseTail) || $exponentBaseTail === '.')) {
                        $buffer .= $char;
                        continue;
                    }
                }
                if (($operator === '+' || $operator === '-') && ($previous === '' || in_array($previous, ['+', '-', '*', '/', '('], true))) {
                    $buffer .= $char;
                    continue;
                }
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
     * @return callable(array<string,mixed>):bool|null
     */
    private static function wherePredicate(?string $where, array $tables = []): callable
    {
        if ($where === null || $where === '') {
            return static fn (): bool => true;
        }
        while (($stripped = self::stripEnclosingParentheses($where)) !== null) {
            $where = $stripped;
        }

        $orGroups = array_map(
            static fn (string $group): array => self::splitWhereAnd($group),
            self::splitWhereOr($where),
        );

        return static function (array $row) use ($orGroups, $tables): ?bool {
            $sawUnknown = false;
            foreach ($orGroups as $terms) {
                $group = true;
                foreach ($terms as $term) {
                    $value = self::evaluatePredicate(trim($term), $row, $tables);
                    if ($value === false) {
                        $group = false;
                        break;
                    }
                    if ($value === null) {
                        $group = null;
                    }
                }
                if ($group === true) {
                    return true;
                }
                if ($group === null) {
                    $sawUnknown = true;
                }
            }

            return $sawUnknown ? null : false;
        };
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function evaluatePredicate(string $term, array $row, array $tables = []): ?bool
    {
        $stripped = self::stripEnclosingParentheses($term);
        if ($stripped !== null) {
            return self::evaluatePredicate($stripped, $row, $tables);
        }

        $not = self::unwrapUnaryNot($term);
        if ($not !== null) {
            return self::negateNullable(self::evaluatePredicate($not, $row, $tables));
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+(NOT\s+)?BETWEEN\s+(.+?)\s+AND\s+(.+)$/is', $term, $match) === 1) {
            $result = self::scalarBetween(
                self::column($row, $match[1]),
                self::evaluateExpression(trim($match[3]), $row),
                self::evaluateExpression(trim($match[4]), $row),
            );

            return isset($match[2]) && trim($match[2]) !== '' ? self::negateNullable($result) : $result;
        }
        if (preg_match('/^\(([^()]+)\)\s+(NOT\s+)?BETWEEN\s*\((.*?)\)\s+AND\s*\((.*)\)$/is', $term, $match) === 1) {
            $value = self::rowValue($row, self::rowValueColumns($match[1]));
            $lower = self::rowValueExpressions($match[3], $row, $tables);
            $upper = self::rowValueExpressions($match[4], $row, $tables);
            $result = self::nullableAnd(
                self::rowValueCompareBoolean($value, '>=', $lower),
                self::rowValueCompareBoolean($value, '<=', $upper),
            );

            return isset($match[2]) && trim($match[2]) !== '' ? self::negateNullable($result) : $result;
        }
        if (preg_match('/^\(([^()]+)\)\s+(NOT\s+)?IN\s*\((.*)\)$/is', $term, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $tuples = self::rowValueTupleList($match[3], $row, $tables);
            $result = self::rowValueIn($left, $tuples);

            return isset($match[2]) && trim($match[2]) !== '' ? self::negateNullable($result) : $result;
        }
        if (preg_match('/^\(([^()]+)\)\s+IS\s+(NOT\s+)?\((.*)\)$/is', $term, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row, $tables);
            $result = self::rowValueIs($left, $right);

            return isset($match[2]) && trim($match[2]) !== '' ? !$result : $result;
        }
        if (preg_match('/^\(([^()]+)\)\s+IS\s+(NOT\s+)?DISTINCT\s+FROM\s*\((.*)\)$/is', $term, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row, $tables);
            $result = self::rowValueIsDistinctFrom($left, $right);

            return isset($match[2]) && trim($match[2]) !== '' ? !$result : $result;
        }
        if (preg_match('/^\(([^()]+)\)\s*(=|<>|!=|>=|<=|>|<)\s*\((.*)\)$/s', $term, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row, $tables);
            if ($match[2] === '=' || $match[2] === '<>' || $match[2] === '!=') {
                $equals = self::rowValueEqualsNullable($left, $right);

                return match ($match[2]) {
                    '=' => $equals,
                    '<>', '!=' => self::negateNullable($equals),
                    default => false,
                };
            }

            $comparison = self::rowValueCompare($left, $right);
            return match ($match[2]) {
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
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+(NOT\s+)?(LIKE|GLOB)\s+(.+)$/is', $term, $match) === 1) {
            $left = self::column($row, $match[1]);
            $pattern = self::literal(trim($match[4]));
            if ($left === null || $pattern === null) {
                return null;
            }

            $matched = strtoupper($match[3]) === 'LIKE'
                ? SQLiteDatabase::likeMatches((string) $left, (string) $pattern)
                : SQLiteDatabase::globMatches((string) $left, (string) $pattern);

            return isset($match[2]) && trim($match[2]) !== '' ? !$matched : $matched;
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
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<string>|array<string,string|callable(array<string,mixed>):mixed>
     */
    private static function returningProjection(string $sql, array $tables = []): array
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
                $projection[$alias] = static fn (array $row): mixed => self::evaluateReturningExpression($expression, $row, $tables);
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
    private static function evaluateReturningExpression(string $expression, array $row, array $tables = []): mixed
    {
        $expression = trim($expression);
        $stripped = self::stripEnclosingParentheses($expression);
        if ($stripped !== null) {
            return self::evaluateReturningExpression($stripped, $row, $tables);
        }

        $not = self::unwrapUnaryNot($expression);
        if ($not !== null) {
            $result = self::evaluatePredicate($not, $row, $tables);

            return $result === null ? null : ($result ? 0 : 1);
        }
        if (preg_match('/^\(([^()]+)\)\s+(NOT\s+)?BETWEEN\s*\((.*?)\)\s+AND\s*\((.*)\)$/is', $expression, $match) === 1) {
            $value = self::rowValue($row, self::rowValueColumns($match[1]));
            $lower = self::rowValueExpressions($match[3], $row, $tables);
            $upper = self::rowValueExpressions($match[4], $row, $tables);
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
            $right = self::rowValueExpressions($match[3], $row, $tables);
            if ($match[2] === '=' || $match[2] === '<>' || $match[2] === '!=') {
                $equals = self::rowValueEqualsNullable($left, $right);
                $result = match ($match[2]) {
                    '=' => $equals,
                    '<>', '!=' => self::negateNullable($equals),
                    default => false,
                };
            } else {
                $comparison = self::rowValueCompare($left, $right);
                $result = match ($match[2]) {
                    '>' => $comparison === null ? null : $comparison > 0,
                    '>=' => $comparison === null ? null : $comparison >= 0,
                    '<' => $comparison === null ? null : $comparison < 0,
                    '<=' => $comparison === null ? null : $comparison <= 0,
                    default => false,
                };
            }

            return $result === null ? null : ($result ? 1 : 0);
        }
        if (preg_match('/^\(([^()]+)\)\s+(NOT\s+)?IN\s*\((.*)\)$/is', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $tuples = self::rowValueTupleList($match[3], $row, $tables);
            $result = self::rowValueIn($left, $tuples);
            if (isset($match[2]) && trim($match[2]) !== '') {
                $result = self::negateNullable($result);
            }

            return $result === null ? null : ($result ? 1 : 0);
        }
        if (preg_match('/^\(([^()]+)\)\s+IS\s+(NOT\s+)?\((.*)\)$/is', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row, $tables);
            $result = self::rowValueIs($left, $right);
            if (isset($match[2]) && trim($match[2]) !== '') {
                $result = !$result;
            }

            return $result ? 1 : 0;
        }
        if (preg_match('/^\(([^()]+)\)\s+IS\s+(NOT\s+)?DISTINCT\s+FROM\s*\((.*)\)$/is', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row, $tables);
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
        $collation = self::splitCollationExpression($expression);
        if ($collation !== null) {
            return self::collatedValue(
                self::evaluateExpression($collation['expression'], $row),
                $collation['collation'],
            );
        }
        $predicate = self::evaluateRowValueExpressionPredicate($expression, $row);
        if ($predicate['matched']) {
            return $predicate['value'] === null ? null : ($predicate['value'] ? 1 : 0);
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+(NOT\s+)?BETWEEN\s+(.+?)\s+AND\s+(.+)$/is', $expression, $match) === 1) {
            $result = self::scalarBetween(
                self::column($row, $match[1]),
                self::evaluateExpression(trim($match[3]), $row),
                self::evaluateExpression(trim($match[4]), $row),
            );
            if (isset($match[2]) && trim($match[2]) !== '') {
                $result = self::negateNullable($result);
            }

            return $result === null ? null : ($result ? 1 : 0);
        }

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
            || strcasecmp($expression, 'TRUE') === 0
            || strcasecmp($expression, 'FALSE') === 0
            || preg_match('/^-?(?:\d+|\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?$/', $expression) === 1
        ) {
            return self::literal($expression);
        }
        if (preg_match('/^length\s*\((.+)\)$/is', $expression, $match) === 1) {
            $value = self::evaluateExpression(trim($match[1]), $row);

            return $value === null ? null : self::textLength((string) $value);
        }
        if (preg_match('/^(upper|lower)\s*\((.+)\)$/is', $expression, $match) === 1) {
            $value = self::evaluateExpression(trim($match[2]), $row);
            if ($value === null) {
                return null;
            }

            return strcasecmp($match[1], 'upper') === 0 ? strtoupper((string) $value) : strtolower((string) $value);
        }
        if (preg_match('/^round\s*\((.*)\)$/is', $expression, $match) === 1) {
            $parts = self::splitComma($match[1]);
            if (count($parts) !== 1 && count($parts) !== 2) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE round() needs one or two arguments');
            }
            $value = self::evaluateExpression($parts[0], $row);
            if ($value === null) {
                return null;
            }
            if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE round() value must be numeric');
            }
            $precision = 0;
            if (array_key_exists(1, $parts)) {
                $precisionValue = self::evaluateExpression($parts[1], $row);
                if ($precisionValue === null) {
                    return null;
                }
                if (!is_int($precisionValue) && !is_float($precisionValue) && !(is_string($precisionValue) && is_numeric($precisionValue))) {
                    throw new \InvalidArgumentException('SQLite UPDATE/DELETE round() precision must be numeric');
                }
                $precision = max(0, (int) $precisionValue);
            }

            return round((float) $value, $precision);
        }
        if (preg_match('/^nullif\s*\((.*)\)$/is', $expression, $match) === 1) {
            $parts = self::splitComma($match[1]);
            if (count($parts) !== 2) {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE nullif() needs two arguments');
            }
            $left = self::evaluateExpression($parts[0], $row);
            $right = self::evaluateExpression($parts[1], $row);

            return $left == $right ? null : $left;
        }
        if (preg_match('/^CASE\s+WHEN\s+(.+?)\s+THEN\s+(.+?)\s+ELSE\s+(.+?)\s+END$/is', $expression, $match) === 1) {
            $truth = self::sqliteTruthValue(self::evaluateExpression($match[1], $row));

            return $truth === true
                ? self::evaluateExpression($match[2], $row)
                : self::evaluateExpression($match[3], $row);
        }
        if (preg_match('/^CASE\s+(.+?)\s+WHEN\s+(.+?)\s+THEN\s+(.+?)\s+ELSE\s+(.+?)\s+END$/is', $expression, $match) === 1) {
            $caseValue = self::evaluateExpression($match[1], $row);
            $whenValue = self::evaluateExpression($match[2], $row);

            return $caseValue == $whenValue
                ? self::evaluateExpression($match[3], $row)
                : self::evaluateExpression($match[4], $row);
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
            return self::column($row, $expression);
        }

        return self::literal($expression);
    }

    private static function sqliteTruthValue(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            $numeric = preg_match('/^[\t\n\f\r ]*([+-]?(?:(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?|\d+(?:[eE][+-]?\d+)?))/', $value, $match) === 1
                ? (float) $match[1]
                : 0.0;

            return $numeric != 0.0;
        }

        throw new \InvalidArgumentException('SQLite UPDATE/DELETE expression truth values must be scalar or NULL');
    }

    /**
     * @param array<string,mixed> $row
     * @return array{matched:bool,value:?bool}
     */
    private static function evaluateRowValueExpressionPredicate(string $expression, array $row, array $tables = []): array
    {
        $not = self::unwrapUnaryNot($expression);
        if ($not !== null) {
            return ['matched' => true, 'value' => self::negateNullable(self::evaluatePredicate($not, $row))];
        }
        if (preg_match('/^\(([^()]+)\)\s+(NOT\s+)?BETWEEN\s*\((.*?)\)\s+AND\s*\((.*)\)$/is', $expression, $match) === 1) {
            $value = self::rowValue($row, self::rowValueColumns($match[1]));
            $lower = self::rowValueExpressions($match[3], $row, $tables);
            $upper = self::rowValueExpressions($match[4], $row, $tables);
            $result = self::nullableAnd(
                self::rowValueCompareBoolean($value, '>=', $lower),
                self::rowValueCompareBoolean($value, '<=', $upper),
            );

            return [
                'matched' => true,
                'value' => isset($match[2]) && trim($match[2]) !== '' ? self::negateNullable($result) : $result,
            ];
        }
        if (preg_match('/^\(([^()]+)\)\s+(NOT\s+)?IN\s*\((.*)\)$/is', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $tuples = self::rowValueTupleList($match[3], $row, $tables);
            $result = self::rowValueIn($left, $tuples);

            return [
                'matched' => true,
                'value' => isset($match[2]) && trim($match[2]) !== '' ? self::negateNullable($result) : $result,
            ];
        }
        if (preg_match('/^\(([^()]+)\)\s+IS\s+(NOT\s+)?\((.*)\)$/is', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row, $tables);
            $result = self::rowValueIs($left, $right);

            return [
                'matched' => true,
                'value' => isset($match[2]) && trim($match[2]) !== '' ? !$result : $result,
            ];
        }
        if (preg_match('/^\(([^()]+)\)\s+IS\s+(NOT\s+)?DISTINCT\s+FROM\s*\((.*)\)$/is', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row, $tables);
            $result = self::rowValueIsDistinctFrom($left, $right);

            return [
                'matched' => true,
                'value' => isset($match[2]) && trim($match[2]) !== '' ? !$result : $result,
            ];
        }
        if (preg_match('/^\(([^()]+)\)\s*(=|<>|!=|>=|<=|>|<)\s*\((.*)\)$/s', $expression, $match) === 1) {
            $left = self::rowValue($row, self::rowValueColumns($match[1]));
            $right = self::rowValueExpressions($match[3], $row, $tables);
            if ($match[2] === '=' || $match[2] === '<>' || $match[2] === '!=') {
                $equals = self::rowValueEqualsNullable($left, $right);
                $result = match ($match[2]) {
                    '=' => $equals,
                    '<>', '!=' => self::negateNullable($equals),
                    default => false,
                };

                return ['matched' => true, 'value' => $result];
            }

            $comparison = self::rowValueCompare($left, $right);
            $result = match ($match[2]) {
                '>' => $comparison === null ? null : $comparison > 0,
                '>=' => $comparison === null ? null : $comparison >= 0,
                '<' => $comparison === null ? null : $comparison < 0,
                '<=' => $comparison === null ? null : $comparison <= 0,
                default => false,
            };

            return ['matched' => true, 'value' => $result];
        }

        return ['matched' => false, 'value' => null];
    }

    private static function unwrapUnaryNot(string $expression): ?string
    {
        $expression = trim($expression);
        if (preg_match('/^NOT\s+(.+)$/is', $expression, $match) !== 1) {
            return null;
        }

        $inner = trim($match[1]);
        if (str_starts_with($inner, '(') && str_ends_with($inner, ')')) {
            $stripped = self::stripEnclosingParentheses($inner);
            if ($stripped !== null) {
                $inner = $stripped;
            }
        }
        if ($inner === '') {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE unary NOT needs an expression');
        }

        return $inner;
    }

    /**
     * @return array{name:string,arguments:string}|null
     */
    private static function wholeLimitScalarFunction(string $expression): ?array
    {
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $expression, $match) !== 1) {
            return null;
        }

        $open = strlen($match[0]) - 1;
        $inString = false;
        $depth = 0;
        $last = strlen($expression) - 1;
        for ($i = $open; $i <= $last; $i++) {
            $char = $expression[$i];
            if ($char === "'") {
                if ($inString && ($expression[$i + 1] ?? null) === "'") {
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
                    if (trim(substr($expression, $i + 1)) !== '') {
                        return null;
                    }

                    return [
                        'name' => strtolower($match[1]),
                        'arguments' => substr($expression, $open + 1, $i - $open - 1),
                    ];
                }
                if ($depth < 0) {
                    return null;
                }
            }
        }

        return null;
    }

    private static function stripEnclosingParentheses(string $expression): ?string
    {
        $expression = trim($expression);
        if (!str_starts_with($expression, '(') || !str_ends_with($expression, ')')) {
            return null;
        }

        $inString = false;
        $depth = 0;
        $last = strlen($expression) - 1;
        for ($i = 0; $i <= $last; $i++) {
            $char = $expression[$i];
            if ($char === "'") {
                if ($inString && ($expression[$i + 1] ?? null) === "'") {
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
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0 && $i !== $last) {
                    return null;
                }
            }
            if ($depth < 0) {
                return null;
            }
        }
        if ($depth !== 0) {
            return null;
        }

        return trim(substr($expression, 1, -1));
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
            if (
                preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1
                && preg_match('/^[A-Za-z_][A-Za-z0-9_]*\s+COLLATE\s+(?:BINARY|NOCASE|RTRIM)$/i', $column) !== 1
            ) {
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
        return array_map(
            static fn (string $column): mixed => preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) === 1
                ? self::column($row, $column)
                : self::evaluateExpression($column, $row),
            $columns,
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return list<mixed>
     */
    private static function rowValueExpressions(string $sql, array $row, array $tables = []): array
    {
        $sql = trim($sql);
        if (preg_match('/^SELECT\b/is', $sql) === 1) {
            $tuples = self::rowValueSimpleSelectTupleList($sql, $tables);
            if ($tuples === []) {
                return array_fill(0, self::rowValueSelectExpressionCount($sql), null);
            }

            return $tuples[0];
        }

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
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<list<mixed>>
     */
    private static function rowValueTupleList(string $sql, array $row, array $tables = []): array
    {
        $sql = trim($sql);
        $compound = self::rowValueCompoundSelectTupleList($sql, $tables);
        if ($compound !== null) {
            return $compound;
        }
        if (preg_match('/^SELECT\s+(DISTINCT\s+)?(.+?)\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)(?:\s+WHERE\s+(.+?))?(?:\s+ORDER\s+BY\s+(.+?))?(?:\s+LIMIT\s+(.+))?$/is', $sql, $match) === 1) {
            return self::rowValueSelectTupleList(
                trim($match[2]),
                $match[3],
                isset($match[4]) ? trim($match[4]) : null,
                isset($match[5]) ? trim($match[5]) : null,
                isset($match[6]) ? trim($match[6]) : null,
                $tables,
                trim($match[1] ?? '') !== '',
            );
        }
        if (preg_match('/^VALUES\b(.*)$/is', $sql, $match) === 1) {
            $sql = trim($match[1]);
            if ($sql === '') {
                throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value VALUES list must contain row tuples');
            }
        }
        if ($sql === '') {
            return [];
        }

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

    private static function rowValueSelectExpressionCount(string $sql): int
    {
        if (preg_match('/^SELECT\s+(DISTINCT\s+)?(.+?)\s+FROM\s+[A-Za-z_][A-Za-z0-9_]*/is', trim($sql), $match) !== 1) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value scalar subquery must return at least two columns');
        }

        $count = count(self::splitComma(trim($match[2])));
        if ($count < 2) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value scalar subquery must return at least two columns');
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<list<mixed>>|null
     */
    private static function rowValueCompoundSelectTupleList(string $sql, array $tables): ?array
    {
        $parts = self::splitCompoundSelect($sql);
        if (count($parts) === 1) {
            return null;
        }

        $current = self::rowValueSimpleSelectTupleList($parts[0]['sql'], $tables);
        foreach (array_slice($parts, 1) as $part) {
            $right = self::rowValueSimpleSelectTupleList($part['sql'], $tables);
            $operator = $part['operator'];
            if ($operator === 'UNION ALL') {
                $current = array_merge($current, $right);
                continue;
            }
            if ($operator === 'UNION') {
                $current = self::distinctRowValueTuples(array_merge($current, $right));
                continue;
            }
            if ($operator === 'INTERSECT') {
                $rightKeys = self::rowValueTupleKeySet($right);
                $intersect = [];
                foreach (self::distinctRowValueTuples($current) as $tuple) {
                    if (isset($rightKeys[self::rowValueTupleKey($tuple)])) {
                        $intersect[] = $tuple;
                    }
                }
                $current = $intersect;
                continue;
            }
            if ($operator === 'EXCEPT') {
                $rightKeys = self::rowValueTupleKeySet($right);
                $except = [];
                foreach (self::distinctRowValueTuples($current) as $tuple) {
                    if (!isset($rightKeys[self::rowValueTupleKey($tuple)])) {
                        $except[] = $tuple;
                    }
                }
                $current = $except;
                continue;
            }
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE row-value compound operator {$operator} is not supported");
        }

        return $current;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<list<mixed>>
     */
    private static function rowValueSimpleSelectTupleList(string $sql, array $tables): array
    {
        if (preg_match('/^SELECT\s+(DISTINCT\s+)?(.+?)\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)(?:\s+WHERE\s+(.+?))?(?:\s+ORDER\s+BY\s+(.+?))?(?:\s+LIMIT\s+(.+))?$/is', trim($sql), $match) !== 1) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value compound subquery arms must be simple SELECT statements');
        }

        return self::rowValueSelectTupleList(
            trim($match[2]),
            $match[3],
            isset($match[4]) ? trim($match[4]) : null,
            isset($match[5]) ? trim($match[5]) : null,
            isset($match[6]) ? trim($match[6]) : null,
            $tables,
            trim($match[1] ?? '') !== '',
        );
    }

    /**
     * @return list<array{operator:?string,sql:string}>
     */
    private static function splitCompoundSelect(string $sql): array
    {
        $parts = [];
        $buffer = '';
        $operator = null;
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
                $buffer .= $char;
                continue;
            }
            if (!$inString && $depth === 0) {
                $matched = self::compoundOperatorAt($sql, $i);
                if ($matched !== null) {
                    $part = trim($buffer);
                    if ($part === '') {
                        throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value compound subquery has an empty SELECT arm');
                    }
                    $parts[] = ['operator' => $operator, 'sql' => $part];
                    $operator = $matched;
                    $buffer = '';
                    $i += strlen($matched) - 1;
                    continue;
                }
            }
            $buffer .= $char;
        }

        $part = trim($buffer);
        if ($parts === []) {
            return [['operator' => null, 'sql' => $sql]];
        }
        if ($part === '') {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value compound subquery has an empty SELECT arm');
        }
        $parts[] = ['operator' => $operator, 'sql' => $part];

        return $parts;
    }

    private static function compoundOperatorAt(string $sql, int $offset): ?string
    {
        foreach (['UNION ALL', 'INTERSECT', 'EXCEPT', 'UNION'] as $operator) {
            $length = strlen($operator);
            if (strtoupper(substr($sql, $offset, $length)) !== $operator) {
                continue;
            }
            $before = $offset === 0 ? ' ' : $sql[$offset - 1];
            $after = $sql[$offset + $length] ?? ' ';
            if (!preg_match('/\s/', $before) || !preg_match('/\s/', $after)) {
                continue;
            }

            return $operator;
        }

        return null;
    }

    /**
     * @param list<list<mixed>> $tuples
     * @return array<string,bool>
     */
    private static function rowValueTupleKeySet(array $tuples): array
    {
        $keys = [];
        foreach ($tuples as $tuple) {
            $keys[self::rowValueTupleKey($tuple)] = true;
        }

        return $keys;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<list<mixed>>
     */
    private static function rowValueSelectTupleList(string $selectSql, string $table, ?string $whereSql, ?string $orderSql, ?string $limitSql, array $tables, bool $distinct = false): array
    {
        if (!isset($tables[$table]) || !is_array($tables[$table]) || !array_is_list($tables[$table])) {
            throw new \InvalidArgumentException("SQLite UPDATE/DELETE row-value IN subquery table {$table} is missing");
        }

        $expressions = self::splitComma($selectSql);
        if (count($expressions) < 2) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value IN subquery must return at least two columns');
        }

        $sourceRows = [];
        foreach ($tables[$table] as $sourceRow) {
            if (!is_array($sourceRow)) {
                throw new \InvalidArgumentException("SQLite UPDATE/DELETE row-value IN subquery table {$table} rows must be arrays");
            }
            if ($whereSql !== null && $whereSql !== '') {
                $predicate = self::evaluatePredicate($whereSql, $sourceRow, $tables);
                if ($predicate !== true) {
                    continue;
                }
            }
            $sourceRows[] = $sourceRow;
        }

        if ($orderSql !== null && $orderSql !== '') {
            $sourceRows = self::orderRowValueSelectRows($sourceRows, $orderSql, $expressions);
        }

        $tuples = [];
        foreach ($sourceRows as $sourceRow) {
            $tuple = [];
            foreach ($expressions as $expression) {
                $tuple[] = self::evaluateExpression(trim($expression), $sourceRow);
            }
            $tuples[] = $tuple;
        }

        if ($distinct) {
            $tuples = self::distinctRowValueTuples($tuples);
        }

        if ($limitSql !== null && $limitSql !== '') {
            [$limit, $offset] = self::parseLimit($limitSql);
            $offset = max(0, $offset);
            if ($limit !== null && $limit >= 0) {
                $tuples = array_slice($tuples, $offset, $limit);
            } elseif ($limit !== null && $limit < 0) {
                $tuples = array_slice($tuples, $offset);
            } elseif ($limit === null) {
                $tuples = array_slice($tuples, $offset);
            }
        }

        return $tuples;
    }

    /**
     * @param list<list<mixed>> $tuples
     * @return list<list<mixed>>
     */
    private static function distinctRowValueTuples(array $tuples): array
    {
        $seen = [];
        $distinct = [];
        foreach ($tuples as $tuple) {
            $key = self::rowValueTupleKey($tuple);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $distinct[] = $tuple;
        }

        return $distinct;
    }

    /**
     * @param list<mixed> $tuple
     */
    private static function rowValueTupleKey(array $tuple): string
    {
        $keyParts = [];
        foreach ($tuple as $value) {
            if ($value === null) {
                $keyParts[] = 'N:';
            } elseif (is_bool($value)) {
                $keyParts[] = 'B:' . ($value ? '1' : '0');
            } elseif (is_int($value)) {
                $keyParts[] = 'I:' . $value;
            } elseif (is_float($value)) {
                $keyParts[] = 'F:' . sprintf('%.17G', $value);
            } elseif (is_string($value)) {
                $keyParts[] = 'S:' . $value;
            } else {
                $keyParts[] = 'X:' . serialize($value);
            }
        }

        return implode("\0", $keyParts);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function orderRowValueSelectRows(array $rows, string $orderSql, array $selectExpressions = []): array
    {
        $terms = self::parseOrderBy($orderSql);
        usort($rows, static function (array $left, array $right) use ($terms, $selectExpressions): int {
            foreach ($terms as $term) {
                if (isset($term['expression']) && preg_match('/^\d+$/', $term['expression']) === 1) {
                    $ordinal = (int) $term['expression'];
                    if ($ordinal < 1 || $ordinal > count($selectExpressions)) {
                        throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value subquery ORDER BY ordinal is out of range');
                    }
                    $expression = trim($selectExpressions[$ordinal - 1]);
                    $leftValue = self::evaluateExpression($expression, $left);
                    $rightValue = self::evaluateExpression($expression, $right);
                } elseif (isset($term['column'])) {
                    $leftValue = self::column($left, $term['column']);
                    $rightValue = self::column($right, $term['column']);
                } elseif (isset($term['expression'])) {
                    $leftValue = self::evaluateExpression($term['expression'], $left);
                    $rightValue = self::evaluateExpression($term['expression'], $right);
                } else {
                    throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value subquery ORDER BY term needs a column or expression');
                }
                if (self::rowValueScalarEquals($leftValue, $rightValue)) {
                    continue;
                }
                $nulls = strtoupper($term['nulls'] ?? '');
                if ($leftValue === null || $rightValue === null) {
                    if ($leftValue === null && $rightValue === null) {
                        $comparison = 0;
                    } elseif ($nulls === 'FIRST') {
                        $comparison = $leftValue === null ? -1 : 1;
                    } elseif ($nulls === 'LAST') {
                        $comparison = $leftValue === null ? 1 : -1;
                    } else {
                        $comparison = $leftValue === null ? -1 : 1;
                        if (($term['direction'] ?? 'ASC') === 'DESC') {
                            $comparison *= -1;
                        }
                    }
                } else {
                    $comparison = self::rowValueScalarCompare($leftValue, $rightValue);
                    if (($term['direction'] ?? 'ASC') === 'DESC') {
                        $comparison *= -1;
                    }
                }

                return $comparison;
            }

            return 0;
        });

        return $rows;
    }

    /**
     * @param list<mixed> $left
     * @param list<list<mixed>> $tuples
     */
    private static function rowValueIn(array $left, array $tuples): ?bool
    {
        $unknown = false;
        foreach ($tuples as $right) {
            $equals = self::rowValueEqualsNullable($left, $right);
            if ($equals === true) {
                return true;
            }
            if ($equals === null) {
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
            if (!self::rowValueScalarEquals($leftValue, $rightValue)) {
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
            if (self::rowValueScalarCompare($leftValue, $rightValue) !== 0) {
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
            if (self::rowValueScalarEquals($leftValue, $rightValue)) {
                continue;
            }

            return self::rowValueScalarCompare($leftValue, $rightValue);
        }

        return 0;
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function rowValueEqualsNullable(array $left, array $right): ?bool
    {
        if (count($left) !== count($right)) {
            throw new \InvalidArgumentException('SQLite UPDATE/DELETE row-value arity mismatch');
        }

        $unknown = false;
        foreach ($left as $index => $leftValue) {
            $rightValue = $right[$index];
            if ($leftValue === null || $rightValue === null) {
                $unknown = true;
                continue;
            }
            if (!self::rowValueScalarEquals($leftValue, $rightValue)) {
                return false;
            }
        }

        return $unknown ? null : true;
    }

    private static function negateNullable(?bool $value): ?bool
    {
        return $value === null ? null : !$value;
    }

    private static function scalarBetween(mixed $value, mixed $lower, mixed $upper): ?bool
    {
        if ($value === null || $lower === null || $upper === null) {
            return null;
        }

        return $value >= $lower && $value <= $upper;
    }

    private static function textLength(string $value): int
    {
        if ($value === '') {
            return 0;
        }
        if (preg_match_all('/./us', $value, $matches) === false) {
            return strlen($value);
        }

        return count($matches[0]);
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
     * @return array{expression:string,collation:string}|null
     */
    private static function splitCollationExpression(string $expression): ?array
    {
        $expression = trim($expression);
        $inString = false;
        $depth = 0;
        $length = strlen($expression);
        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];
            if ($char === "'") {
                if ($inString && ($expression[$i + 1] ?? null) === "'") {
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
            if (!$inString && $depth === 0 && self::keywordAt($expression, $i, 'COLLATE')) {
                $left = trim(substr($expression, 0, $i));
                $right = trim(substr($expression, $i + strlen('COLLATE')));
                if ($left === '' || preg_match('/^(BINARY|NOCASE|RTRIM)$/i', $right) !== 1) {
                    throw new \InvalidArgumentException('SQLite UPDATE/DELETE COLLATE expression is malformed');
                }

                return ['expression' => $left, 'collation' => strtoupper($right)];
            }
        }

        return null;
    }

    /**
     * @return array{__sqlite_collation:string,value:mixed}
     */
    private static function collatedValue(mixed $value, string $collation): array
    {
        return ['__sqlite_collation' => $collation, 'value' => $value];
    }

    private static function valueCollation(mixed $left, mixed $right): string
    {
        if (is_array($left) && isset($left['__sqlite_collation']) && is_string($left['__sqlite_collation'])) {
            return $left['__sqlite_collation'];
        }
        if (is_array($right) && isset($right['__sqlite_collation']) && is_string($right['__sqlite_collation'])) {
            return $right['__sqlite_collation'];
        }

        return 'BINARY';
    }

    private static function uncollatedValue(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('__sqlite_collation', $value) && array_key_exists('value', $value)
            ? $value['value']
            : $value;
    }

    private static function rowValueScalarEquals(mixed $left, mixed $right): bool
    {
        return self::rowValueScalarCompare($left, $right) === 0;
    }

    private static function rowValueScalarCompare(mixed $left, mixed $right): int
    {
        $collation = self::valueCollation($left, $right);
        $left = self::uncollatedValue($left);
        $right = self::uncollatedValue($right);
        if ($collation === 'NOCASE' && is_string($left) && is_string($right)) {
            return strcasecmp($left, $right);
        }
        if ($collation === 'RTRIM' && is_string($left) && is_string($right)) {
            return strcmp(rtrim($left), rtrim($right));
        }

        return $left <=> $right;
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

    /**
     * @return list<string>
     */
    private static function splitWhereOr(string $sql): array
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
                $buffer .= $char;
                continue;
            }
            if (!$inString && $depth === 0 && self::keywordAt($sql, $i, 'OR')) {
                $parts[] = trim($buffer);
                $buffer = '';
                $i++;
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
        if (strcasecmp($sql, 'TRUE') === 0) {
            return 1;
        }
        if (strcasecmp($sql, 'FALSE') === 0) {
            return 0;
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
