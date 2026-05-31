<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertReturningSql
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>>|null $uniqueConstraints
     * @param array<string,mixed>|list<string> $views
     * @return array{target:string,target_alias:?string,conflict_target:list<string>,conflict_where:?string,columns:list<string>,incoming_rows:list<array<string,mixed>>,before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning:list<array<string,mixed>>,changes:int}
     */
    public static function execute(string $sql, array $tables, ?array $uniqueConstraints = null, array $views = []): array
    {
        self::rejectViewUpsert($sql, $views);
        $parsed = self::parse($sql);
        $target = $parsed['target'];
        if (!isset($tables[$target]) || !is_array($tables[$target]) || !array_is_list($tables[$target])) {
            throw new \InvalidArgumentException("SQLite UPSERT RETURNING target table {$target} is missing");
        }

        $conflictArms = $parsed['conflict_arms'];
        $usesChainedConflictArms = count($conflictArms) > 1;
        $omittedConflictTarget = $parsed['conflict_target'] === [];
        $conflictTarget = self::effectiveConflictTarget($parsed['conflict_target'], $uniqueConstraints);
        if (!$usesChainedConflictArms) {
            self::validateConflictTarget($conflictTarget, $uniqueConstraints);
        } elseif ($uniqueConstraints === null || $uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING chained ON CONFLICT arms require UNIQUE constraints');
        }

        $incomingRows = self::completeIncomingRowsFromTargetImage($tables[$target], $parsed['incoming_rows']);

        if ($usesChainedConflictArms) {
            $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
                $tables[$target],
                $incomingRows,
                self::conflictArmCallbacks($target, $parsed['target_alias'], $conflictArms),
                $uniqueConstraints,
            );
        } elseif ($parsed['action'] === 'nothing') {
            $result = self::executeDoNothing($tables[$target], $incomingRows, $conflictTarget, $uniqueConstraints, $omittedConflictTarget);
        } else {
            $result = SQLiteUpsertDoUpdateWherePlan::execute(
                $tables[$target],
                $incomingRows,
                $conflictTarget,
                self::assignmentCallbacks($target, $parsed['target_alias'], $parsed['assignments']),
                self::wherePredicate($target, $parsed['target_alias'], $parsed['where']),
                $uniqueConstraints,
            );
        }

        return [
            'target' => $target,
            'target_alias' => $parsed['target_alias'],
            'conflict_target' => $conflictTarget,
            'conflict_where' => $parsed['conflict_where'],
            'columns' => $parsed['columns'],
            'incoming_rows' => $incomingRows,
            'before' => $result['before'],
            'after' => $result['after'],
            'inserted_rows' => $result['inserted_rows'],
            'updated_rows' => $result['updated_rows'],
            'skipped_rows' => $result['skipped_rows'],
            'returning' => SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], self::returningProjection($target, $parsed['target_alias'], $parsed['returning'])),
            'changes' => $result['changes'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $targetRows
     * @param list<array<string,mixed>> $incomingRows
     * @return list<array<string,mixed>>
     */
    private static function completeIncomingRowsFromTargetImage(array $targetRows, array $incomingRows): array
    {
        if ($targetRows === [] || $incomingRows === []) {
            return $incomingRows;
        }

        $defaults = [];
        foreach ($targetRows as $row) {
            foreach ($row as $column => $value) {
                if (!is_string($column) || $column === '') {
                    continue;
                }
                if (!array_key_exists($column, $defaults)) {
                    $defaults[$column] = $value;
                    continue;
                }
                if ($defaults[$column] !== $value) {
                    $defaults[$column] = null;
                }
            }
        }

        if ($defaults === []) {
            return $incomingRows;
        }

        $completed = [];
        foreach ($incomingRows as $incoming) {
            foreach ($defaults as $column => $value) {
                if (!array_key_exists($column, $incoming)) {
                    $incoming[$column] = $value;
                }
            }
            $completed[] = $incoming;
        }

        return $completed;
    }

    /** @param array<string,mixed>|list<string> $views */
    private static function rejectViewUpsert(string $sql, array $views): void
    {
        if ($views === []) {
            return;
        }

        $target = self::insertTargetForViewPreflight($sql);
        if ($target === null || !self::viewNameMatches($target, $views)) {
            return;
        }
        if (self::keywordOffset($sql, 'ON CONFLICT', 0) === null) {
            return;
        }

        throw new \InvalidArgumentException('cannot UPSERT a view');
    }

    private static function insertTargetForViewPreflight(string $sql): ?string
    {
        if (preg_match('/^\s*INSERT(?:\s+OR\s+(?:ABORT|FAIL|IGNORE|REPLACE|ROLLBACK))?\s+INTO\s+/i', $sql, $match) !== 1) {
            return null;
        }

        $offset = strlen($match[0]);
        try {
            $target = self::readIdentifier($sql, $offset, 'SQLite UPSERT RETURNING target table');
            $offset += strlen($target);
            $offset = self::skipWhitespace($sql, $offset);
            if (($sql[$offset] ?? null) === '.') {
                ++$offset;
                $offset = self::skipWhitespace($sql, $offset);
                $target = self::readIdentifier($sql, $offset, 'SQLite UPSERT RETURNING target table');
            }
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $target;
    }

    /** @param array<string,mixed>|list<string> $views */
    private static function viewNameMatches(string $target, array $views): bool
    {
        foreach ($views as $name => $metadata) {
            $view = is_int($name) ? $metadata : $name;
            if (is_string($view) && strcasecmp($view, $target) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{target:string,target_alias:?string,columns:list<string>,incoming_rows:list<array<string,mixed>>,conflict_target:list<string>,conflict_where:?string,action:'update'|'nothing',assignments:array<string,string>,where:?string,returning:string,conflict_arms:list<array{target:list<string>|null,conflict_where:?string,action:'update'|'nothing',assignments:array<string,string>,where:?string}>}
     */
    public static function parse(string $sql): array
    {
        $sql = trim(rtrim($sql, " \t\n\r\0\x0B;"));
        $withRows = null;
        $withColumns = null;
        $withName = null;
        if (preg_match('/^WITH\s+/i', $sql, $withMatch) === 1) {
            $offset = strlen($withMatch[0]);
            $withName = self::readIdentifier($sql, $offset, 'SQLite UPSERT RETURNING CTE name');
            $offset += strlen($withName);
            $offset = self::skipWhitespace($sql, $offset);
            if (($sql[$offset] ?? null) !== '(') {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING CTE requires a column list');
            }
            [$withColumnSql, $offset] = self::consumeParenthesized($sql, $offset);
            $withColumns = self::identifierList($withColumnSql, 'SQLite UPSERT RETURNING CTE column');
            $offset = self::skipWhitespace($sql, $offset);
            if (preg_match('/\GAS\s*/i', $sql, $asMatch, 0, $offset) !== 1) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING CTE requires AS');
            }
            $offset += strlen($asMatch[0]);
            if (($sql[$offset] ?? null) !== '(') {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING CTE body must be parenthesized');
            }
            [$withBody, $offset] = self::consumeParenthesized($sql, $offset);
            $withBody = trim($withBody);
            if (preg_match('/^VALUES\b/i', $withBody, $valuesMatch) !== 1) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING only supports VALUES CTE input');
            }
            $withRows = self::parseValues(trim(substr($withBody, strlen($valuesMatch[0]))), $withColumns);
            $sql = trim(substr($sql, $offset));
        }
        if (preg_match('/^INSERT\s+INTO\s+/i', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING SQL must start with INSERT INTO');
        }

        $offset = strlen($match[0]);
        $target = self::readIdentifier($sql, $offset, 'SQLite UPSERT RETURNING target table');
        $offset += strlen($target);
        if (($sql[$offset] ?? null) === '.') {
            $offset++;
            $target = self::readIdentifier($sql, $offset, 'SQLite UPSERT RETURNING target table');
            $offset += strlen($target);
        }
        $offset = self::skipWhitespace($sql, $offset);
        $targetAlias = null;
        if (preg_match('/\GAS\s+/i', $sql, $aliasMatch, 0, $offset) === 1) {
            $offset += strlen($aliasMatch[0]);
            $targetAlias = self::readIdentifier($sql, $offset, 'SQLite UPSERT RETURNING target alias');
            $offset += strlen($targetAlias);
            $offset = self::skipWhitespace($sql, $offset);
        }
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

        $conflictOffset = self::keywordOffset($sql, 'ON CONFLICT', $offset);
        if ($conflictOffset === null) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING requires ON CONFLICT');
        }

        $inputSql = trim(substr($sql, $offset, $conflictOffset - $offset));
        if (preg_match('/^VALUES\b/i', $inputSql, $valuesMatch) === 1) {
            $incomingRows = self::parseValues(trim(substr($inputSql, strlen($valuesMatch[0]))), $columns);
        } elseif (preg_match('/^SELECT\b/i', $inputSql) === 1) {
            if ($withName === null || $withColumns === null || $withRows === null) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING SELECT input requires a VALUES CTE');
            }
            $incomingRows = self::parseSelectInput($inputSql, $columns, $withName, $withColumns, $withRows);
        } else {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING requires VALUES or SELECT input');
        }

        [$conflictArms, $returningOffset] = self::parseConflictArms($sql, $conflictOffset, $columns);
        $firstArm = $conflictArms[0];
        $conflictTarget = $firstArm['target'] ?? [];
        $conflictWhere = $firstArm['conflict_where'];
        $action = $firstArm['action'];
        $whereSql = $firstArm['where'];

        $returning = trim(substr($sql, $returningOffset + strlen('RETURNING')));
        if ($returning === '') {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection must not be empty');
        }

        return [
            'target' => $target,
            'target_alias' => $targetAlias,
            'columns' => $columns,
            'incoming_rows' => $incomingRows,
            'conflict_target' => $conflictTarget,
            'conflict_where' => $conflictWhere,
            'action' => $action,
            'assignments' => $firstArm['assignments'],
            'where' => $whereSql,
            'returning' => $returning,
            'conflict_arms' => $conflictArms,
        ];
    }

    /**
     * @param list<string> $columns
     * @return array{0:list<array{target:list<string>|null,conflict_where:?string,action:'update'|'nothing',assignments:array<string,string>,where:?string}>,1:int}
     */
    private static function parseConflictArms(string $sql, int $offset, array $columns): array
    {
        $arms = [];

        while (true) {
            $offset = self::skipWhitespace($sql, $offset);
            if (preg_match('/\GON\s+CONFLICT\b/i', $sql, $conflictMatch, 0, $offset) !== 1) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING requires ON CONFLICT');
            }
            $offset += strlen($conflictMatch[0]);
            $offset = self::skipWhitespace($sql, $offset);

            $conflictTarget = null;
            if (($sql[$offset] ?? null) === '(') {
                [$conflictSql, $offset] = self::consumeParenthesized($sql, $offset);
                $conflictTarget = self::identifierList($conflictSql, 'SQLite UPSERT RETURNING conflict target');
            }

            $offset = self::skipWhitespace($sql, $offset);
            $conflictWhere = null;
            if (preg_match('/\GWHERE\b/i', $sql, $whereMatch, 0, $offset) === 1) {
                $whereStart = $offset + strlen($whereMatch[0]);
                $actionOffset = self::keywordOffset($sql, 'DO UPDATE', $whereStart);
                $doNothingOffset = self::keywordOffset($sql, 'DO NOTHING', $whereStart);
                if ($actionOffset === null || ($doNothingOffset !== null && $doNothingOffset < $actionOffset)) {
                    $actionOffset = $doNothingOffset;
                }
                if ($actionOffset === null) {
                    throw new \InvalidArgumentException('SQLite UPSERT conflict target WHERE must be followed by DO UPDATE SET or DO NOTHING');
                }
                $conflictWhere = trim(substr($sql, $whereStart, $actionOffset - $whereStart));
                if ($conflictWhere === '') {
                    throw new \InvalidArgumentException('SQLite UPSERT conflict target WHERE clause must not be empty');
                }
                self::validateConflictTargetWhere($conflictWhere, $columns);
                $offset = self::skipWhitespace($sql, $actionOffset);
            }

            $action = 'update';
            $assignments = [];
            $whereSql = null;
            if (preg_match('/\GDO\s+NOTHING\b/i', $sql, $doNothingMatch, 0, $offset) === 1) {
                $action = 'nothing';
                $offset += strlen($doNothingMatch[0]);
                [$nextKeyword, $nextOffset] = self::nextConflictArmOrReturningOffset($sql, $offset);
                if (trim(substr($sql, $offset, $nextOffset - $offset)) !== '') {
                    throw new \InvalidArgumentException('SQLite UPSERT DO NOTHING RETURNING cannot include assignments or WHERE');
                }
            } else {
                if (preg_match('/\GDO\s+UPDATE\s+SET\b/i', $sql, $doMatch, 0, $offset) !== 1) {
                    throw new \InvalidArgumentException('SQLite UPSERT RETURNING only supports DO UPDATE SET or DO NOTHING');
                }
                $offset += strlen($doMatch[0]);
                [$nextKeyword, $nextOffset] = self::nextConflictArmOrReturningOffset($sql, $offset);

                $beforeNext = trim(substr($sql, $offset, $nextOffset - $offset));
                $whereOffset = self::keywordOffset($beforeNext, 'WHERE', 0);
                $assignmentSql = $whereOffset === null ? $beforeNext : trim(substr($beforeNext, 0, $whereOffset));
                $whereSql = $whereOffset === null ? null : trim(substr($beforeNext, $whereOffset + strlen('WHERE')));
                if ($whereSql === '') {
                    throw new \InvalidArgumentException('SQLite UPSERT RETURNING WHERE clause must not be empty');
                }
                $assignments = self::parseAssignments($assignmentSql);
            }

            if ($conflictTarget === null && $nextKeyword === 'ON CONFLICT') {
                throw new \InvalidArgumentException('SQLite UPSERT catchall ON CONFLICT arm must be last');
            }

            $arms[] = [
                'target' => $conflictTarget,
                'conflict_where' => $conflictWhere,
                'action' => $action,
                'assignments' => $assignments,
                'where' => $whereSql,
            ];

            if ($nextKeyword === 'RETURNING') {
                return [$arms, $nextOffset];
            }
            $offset = $nextOffset;
        }
    }

    /**
     * @return array{0:'ON CONFLICT'|'RETURNING',1:int}
     */
    private static function nextConflictArmOrReturningOffset(string $sql, int $offset): array
    {
        $returningOffset = self::keywordOffset($sql, 'RETURNING', $offset);
        if ($returningOffset === null) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING requires RETURNING');
        }

        $conflictOffset = self::keywordOffset($sql, 'ON CONFLICT', $offset);
        if ($conflictOffset !== null && $conflictOffset < $returningOffset) {
            return ['ON CONFLICT', $conflictOffset];
        }

        return ['RETURNING', $returningOffset];
    }

    /**
     * @param list<string> $conflictTarget
     * @param list<list<string>>|null $uniqueConstraints
     * @return list<string>
     */
    private static function effectiveConflictTarget(array $conflictTarget, ?array $uniqueConstraints): array
    {
        if ($conflictTarget !== []) {
            return $conflictTarget;
        }
        if ($uniqueConstraints === null || $uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING without a conflict target requires a UNIQUE constraint');
        }
        $first = $uniqueConstraints[0];
        if (!is_array($first) || $first === [] || !array_is_list($first)) {
            throw new \InvalidArgumentException('SQLite UPSERT unique constraint must be a non-empty column list');
        }

        return array_values($first);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $conflictTarget
     * @param list<list<string>>|null $uniqueConstraints
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,changes:int}
     */
    private static function executeDoNothing(array $rows, array $incomingRows, array $conflictTarget, ?array $uniqueConstraints, bool $catchAnyUniqueConflict): array
    {
        $uniqueConstraints = self::normalizeUniqueConstraints($conflictTarget, $uniqueConstraints);
        $after = array_values($rows);
        $inserted = [];
        $skipped = [];
        foreach ($incomingRows as $incoming) {
            if ($catchAnyUniqueConflict
                ? self::findAnyUniqueConflictIndex($after, $incoming, $uniqueConstraints) !== null
                : self::findConflictIndex($after, $incoming, $conflictTarget) !== null
            ) {
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
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $incoming
     * @param list<list<string>> $uniqueConstraints
     */
    private static function findAnyUniqueConflictIndex(array $rows, array $incoming, array $uniqueConstraints): ?int
    {
        foreach ($uniqueConstraints as $constraint) {
            $index = self::findConflictIndex($rows, $incoming, $constraint);
            if ($index !== null) {
                return $index;
            }
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
     * @param list<string> $columns
     */
    private static function validateConflictTargetWhere(string $where, array $columns): void
    {
        $sql = preg_replace("/'(?:''|[^'])*'/", ' ', $where);
        if (!is_string($sql)) {
            throw new \InvalidArgumentException('SQLite UPSERT conflict target WHERE clause is malformed');
        }
        $sql = preg_replace_callback('/"(?:""|[^"])*"|\[(?:\]\]|[^\]])*\]/', static function (array $match): string {
            $identifier = self::dequoteIdentifier($match[0]);

            return $identifier === null ? ' ' : $identifier;
        }, $sql);
        if (!is_string($sql)) {
            throw new \InvalidArgumentException('SQLite UPSERT conflict target WHERE clause is malformed');
        }

        preg_match_all('/\b[A-Za-z_][A-Za-z0-9_]*\b/', $sql, $matches);
        $keywords = [
            'and' => true,
            'binary' => true,
            'collate' => true,
            'false' => true,
            'glob' => true,
            'is' => true,
            'like' => true,
            'not' => true,
            'null' => true,
            'or' => true,
            'true' => true,
        ];
        foreach ($matches[0] as $identifier) {
            if (isset($keywords[strtolower($identifier)])) {
                continue;
            }
            if (!in_array($identifier, $columns, true)) {
                throw new \InvalidArgumentException("no such column: {$identifier}");
            }
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
     * @param list<string> $targetColumns
     * @param list<string> $cteColumns
     * @param list<array<string,mixed>> $cteRows
     * @return list<array<string,mixed>>
     */
    private static function parseSelectInput(string $sql, array $targetColumns, string $cteName, array $cteColumns, array $cteRows): array
    {
        if (preg_match('/^SELECT\s+(.+?)\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)(?:\s+WHERE\s+(.+))?$/is', trim($sql), $match) !== 1) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING SELECT input is unsupported');
        }
        if (strcasecmp($match[2], $cteName) !== 0) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING SELECT source CTE is missing');
        }
        $where = isset($match[3]) ? trim($match[3]) : null;
        if ($where !== null && !in_array(strtolower($where), ['true', '1'], true)) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING SELECT input only supports WHERE true');
        }

        $selectColumns = self::identifierList($match[1], 'SQLite UPSERT RETURNING SELECT column');
        if (count($selectColumns) !== count($targetColumns)) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING SELECT column count does not match target columns');
        }
        foreach ($selectColumns as $column) {
            if (!in_array($column, $cteColumns, true)) {
                throw new \InvalidArgumentException("SQLite UPSERT RETURNING SELECT column {$column} is missing from CTE");
            }
        }

        $rows = [];
        foreach ($cteRows as $cteRow) {
            $row = [];
            foreach ($targetColumns as $index => $targetColumn) {
                $sourceColumn = $selectColumns[$index];
                $row[$targetColumn] = $cteRow[$sourceColumn];
            }
            $rows[] = $row;
        }
        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING SELECT input must not be empty');
        }

        return $rows;
    }

    /**
     * @return array<string,string>
     */
    private static function parseAssignments(string $sql): array
    {
        $assignments = [];
        foreach (self::splitComma($sql) as $part) {
            $part = trim($part);
            if (preg_match('/^\(([^()]*)\)\s*=\s*\((.+)\)$/s', $part, $tupleMatch) === 1) {
                $columns = self::identifierList($tupleMatch[1], 'SQLite UPSERT RETURNING row-value assignment column');
                $expressions = self::rowValueAssignmentExpressions($tupleMatch[2]);
                if (count($columns) !== count($expressions)) {
                    throw new \InvalidArgumentException('SQLite UPSERT RETURNING row-value assignment arity mismatch');
                }
                foreach ($columns as $index => $column) {
                    if (array_key_exists($column, $assignments)) {
                        throw new \InvalidArgumentException("SQLite UPSERT RETURNING assignment column {$column} is duplicated");
                    }
                    $assignments[$column] = $expressions[$index];
                }
                continue;
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)$/s', $part, $match) !== 1) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING assignments must be column = expression pairs');
            }
            if (array_key_exists($match[1], $assignments)) {
                throw new \InvalidArgumentException("SQLite UPSERT RETURNING assignment column {$match[1]} is duplicated");
            }
            $assignments[$match[1]] = trim($match[2]);
        }
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING DO UPDATE needs assignments');
        }

        return $assignments;
    }

    /**
     * @return list<string>
     */
    private static function rowValueAssignmentExpressions(string $sql): array
    {
        $sql = trim($sql);
        if (preg_match('/^SELECT\s+(.+)$/is', $sql, $match) === 1) {
            $sql = trim($match[1]);
        }

        return self::splitComma($sql);
    }

    /**
     * @param list<array{target:list<string>|null,conflict_where:?string,action:'update'|'nothing',assignments:array<string,string>,where:?string}> $arms
     * @return list<array{target:list<string>|null,action:string,assignments:array<string,callable(array<string,mixed>,array<string,mixed>):mixed>,where:callable(array<string,mixed>,array<string,mixed>):bool|null}>
     */
    private static function conflictArmCallbacks(string $target, ?string $targetAlias, array $arms): array
    {
        $callbacks = [];
        foreach ($arms as $arm) {
            $callbacks[] = [
                'target' => $arm['target'],
                'action' => $arm['action'],
                'assignments' => $arm['action'] === 'update' ? self::assignmentCallbacks($target, $targetAlias, $arm['assignments']) : [],
                'where' => self::wherePredicate($target, $targetAlias, $arm['where']),
            ];
        }

        return $callbacks;
    }

    /**
     * @param array<string,string> $assignments
     * @return array<string,callable(array<string,mixed>,array<string,mixed>):mixed>
     */
    private static function assignmentCallbacks(string $target, ?string $targetAlias, array $assignments): array
    {
        $callbacks = [];
        foreach ($assignments as $column => $expression) {
            $callbacks[$column] = static fn (array $current, array $excluded): mixed => self::evaluateExpression($expression, $target, $targetAlias, $current, $excluded);
        }

        return $callbacks;
    }

    /**
     * @return callable(array<string,mixed>,array<string,mixed>):bool|null
     */
    private static function wherePredicate(string $target, ?string $targetAlias, ?string $where): ?callable
    {
        if ($where === null || $where === '') {
            return null;
        }

        $terms = self::splitTopLevelKeyword($where, 'AND');

        return static function (array $current, array $excluded) use ($target, $targetAlias, $terms): bool {
            foreach ($terms as $term) {
                if (self::evaluatePredicate($term, $target, $targetAlias, $current, $excluded) !== true) {
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
    private static function evaluatePredicate(string $term, string $target, ?string $targetAlias, array $current, array $excluded): ?bool
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
        if (strcasecmp($term, 'TRUE') === 0) {
            return true;
        }
        if (strcasecmp($term, 'FALSE') === 0) {
            return false;
        }
        if (preg_match('/^(.+?)\s+IS\s+(NOT\s+)?NULL$/is', $term, $match) === 1) {
            $isNull = self::evaluateExpression($match[1], $target, $targetAlias, $current, $excluded) === null;

            return isset($match[2]) && trim($match[2]) !== '' ? !$isNull : $isNull;
        }
        if (preg_match('/^(.+?)\s+(LIKE|GLOB)\s+(.+)$/is', $term, $match) === 1) {
            $left = self::evaluateExpression($match[1], $target, $targetAlias, $current, $excluded);
            $pattern = self::evaluateExpression($match[3], $target, $targetAlias, $current, $excluded);
            if ($left === null || $pattern === null) {
                return null;
            }

            return strtoupper($match[2]) === 'LIKE'
                ? SQLiteDatabase::likeMatches((string) $left, (string) $pattern)
                : SQLiteDatabase::globMatches((string) $left, (string) $pattern);
        }
        if (preg_match('/^(.+?)\s*(=|<>|!=|>=|<=|>|<)\s*(.+)$/s', $term, $match) === 1) {
            $left = self::evaluateExpression($match[1], $target, $targetAlias, $current, $excluded);
            $right = self::evaluateExpression($match[3], $target, $targetAlias, $current, $excluded);
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
    private static function evaluateExpression(string $expression, string $target, ?string $targetAlias, array $current, array $excluded): mixed
    {
        $expression = trim(self::stripOuterParens($expression));
        if (str_starts_with(strtolower($expression), 'coalesce(') && str_ends_with($expression, ')')) {
            $inner = substr($expression, 9, -1);
            foreach (self::splitComma($inner) as $part) {
                $value = self::evaluateExpression($part, $target, $targetAlias, $current, $excluded);
                if ($value !== null) {
                    return $value;
                }
            }

            return null;
        }
        foreach (['+', '-'] as $operator) {
            $parts = self::splitBinaryOperator($expression, $operator);
            if ($parts !== null) {
                $left = self::evaluateExpression($parts[0], $target, $targetAlias, $current, $excluded);
                $right = self::evaluateExpression($parts[1], $target, $targetAlias, $current, $excluded);
                if ($left === null || $right === null) {
                    return null;
                }

                if (is_float($left) || is_float($right)) {
                    return $operator === '+' ? (float) $left + (float) $right : (float) $left - (float) $right;
                }

                return $operator === '+' ? (int) $left + (int) $right : (int) $left - (int) $right;
            }
        }
        $concat = self::splitBinaryOperator($expression, '||');
        if ($concat !== null) {
            $left = self::evaluateExpression($concat[0], $target, $targetAlias, $current, $excluded);
            $right = self::evaluateExpression($concat[1], $target, $targetAlias, $current, $excluded);

            return $left === null || $right === null ? null : (string) $left . (string) $right;
        }
        $dequoted = self::dequoteIdentifier($expression);
        if ($dequoted !== null) {
            if (!array_key_exists($dequoted, $current)) {
                throw new \InvalidArgumentException("SQLite UPSERT RETURNING column {$dequoted} is missing");
            }

            return $current[$dequoted];
        }
        $targetQualifier = $targetAlias ?? $target;
        if (preg_match('/^(?:(excluded|' . preg_quote($targetQualifier, '/') . '|' . preg_quote($target, '/') . ')\.)?([A-Za-z_][A-Za-z0-9_]*)$/i', $expression, $match) === 1) {
            $qualifier = $match[1] ?? '';
            if ($targetAlias !== null && strcasecmp($qualifier, $target) === 0 && strcasecmp($target, 'excluded') !== 0) {
                throw new \InvalidArgumentException("SQLite UPSERT RETURNING column {$target}.{$match[2]} is missing");
            }
            $source = strtolower($qualifier) === 'excluded' && !(strcasecmp($target, 'excluded') === 0 && $targetAlias === null)
                ? $excluded
                : $current;
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
    private static function returningProjection(string $target, ?string $targetAlias, string $sql): array
    {
        $projection = [];
        foreach (self::splitComma($sql) as $term) {
            $term = trim($term);
            if ($term === '*') {
                $projection[] = '*';
                continue;
            }
            if (preg_match('/^(?:(excluded|[A-Za-z_][A-Za-z0-9_]*)\.)?\*$/i', $term) === 1) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING may not use TABLE.* wildcards');
            }
            $dequoted = self::dequoteIdentifier($term);
            if ($dequoted !== null) {
                $projection[] = $dequoted;
                continue;
            }
            if (preg_match("/^'(.*)'$/s", $term) === 1) {
                $projection[$term] = static fn (): mixed => self::literal($term);
                continue;
            }
            if (preg_match('/^(TRUE|FALSE)(?:\s+AS\s+([A-Za-z_][A-Za-z0-9_]*))?$/i', $term, $boolMatch) === 1) {
                $alias = $boolMatch[2] ?? strtoupper($boolMatch[1]);
                $literal = strtoupper($boolMatch[1]);
                $projection[$alias] = static fn (): int => $literal === 'TRUE' ? 1 : 0;
                continue;
            }
            if (preg_match('/^(?:(excluded|[A-Za-z_][A-Za-z0-9_]*)\.)?([A-Za-z_][A-Za-z0-9_]*)(?:\s+AS\s+([A-Za-z_][A-Za-z0-9_]*))?$/i', $term, $match) !== 1) {
                if (preg_match('/^(.+?)\s+AS\s+(.+)$/is', $term, $expressionMatch) !== 1) {
                    throw new \InvalidArgumentException('SQLite UPSERT RETURNING only supports columns, aliases, expressions with aliases, literals, and *');
                }
                $expression = trim($expressionMatch[1]);
                $alias = self::dequoteIdentifier(trim($expressionMatch[2])) ?? trim($expressionMatch[2]);
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) !== 1) {
                    throw new \InvalidArgumentException('SQLite UPSERT RETURNING alias is malformed');
                }
                if (preg_match('/^excluded\./i', $expression) === 1 || preg_match('/[^A-Za-z0-9_]excluded\./i', $expression) === 1) {
                    throw new \InvalidArgumentException('SQLite UPSERT RETURNING cannot reference excluded columns');
                }
                $projection[$alias] = static fn (array $row): mixed => self::evaluateExpression($expression, $target, $targetAlias, $row, $row);
                continue;
            }
            $qualifier = $match[1] ?? '';
            if (strcasecmp($qualifier, 'excluded') === 0) {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING cannot reference excluded columns');
            }
            $targetQualifier = $targetAlias ?? $target;
            if ($qualifier !== '' && strcasecmp($qualifier, $targetQualifier) !== 0) {
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

    private static function dequoteIdentifier(string $identifier): ?string
    {
        $identifier = trim($identifier);
        if (preg_match('/^"((?:[^"]|"")*)"$/s', $identifier, $match) === 1) {
            return str_replace('""', '"', $match[1]);
        }
        if (preg_match('/^\[((?:[^\]]|\]\])*)\]$/s', $identifier, $match) === 1) {
            return str_replace(']]', ']', $match[1]);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function identifierList(string $sql, string $label): array
    {
        $identifiers = [];
        foreach (self::splitComma($sql) as $part) {
            $part = trim($part);
            $dequoted = self::dequoteIdentifier($part);
            if ($dequoted !== null) {
                if ($dequoted === '') {
                    throw new \InvalidArgumentException("{$label} is malformed");
                }
                $identifiers[] = $dequoted;
                continue;
            }
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
        if (strcasecmp($sql, 'TRUE') === 0) {
            return 1;
        }
        if (strcasecmp($sql, 'FALSE') === 0) {
            return 0;
        }
        if (preg_match('/^-?\d+$/', $sql) === 1) {
            return (int) $sql;
        }
        if (preg_match('/^-?(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?$/', $sql) === 1
            || preg_match('/^-?\d+[eE][+-]?\d+$/', $sql) === 1
        ) {
            return (float) $sql;
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
