<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertDoUpdateWherePlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param list<list<string>>|null $uniqueConstraints
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $where
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,changes:int}
     */
    public static function execute(
        array $rows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        ?callable $where = null,
        ?array $uniqueConstraints = null,
    ): array {
        self::validateRows($rows, 'target');
        self::validateRows($incomingRows, 'incoming');
        self::validateUniqueColumns($uniqueColumns);
        self::validateAssignments($assignments);
        $uniqueConstraints = self::normalizeUniqueConstraints($uniqueColumns, $uniqueConstraints);

        $before = $rows;
        $inserted = [];
        $updated = [];
        $skipped = [];
        $returning = [];
        $changes = 0;

        foreach ($incomingRows as $incoming) {
            self::ensureColumns($incoming, $uniqueColumns, 'incoming');
            $conflictIndex = self::findConflictIndex($rows, $incoming, $uniqueColumns);
            if ($conflictIndex === null) {
                self::ensureNoUniqueConflict($rows, $incoming, $uniqueConstraints, null, 'insert');
                $rows[] = $incoming;
                $inserted[] = $incoming;
                $returning[] = $incoming;
                ++$changes;
                continue;
            }

            $current = $rows[$conflictIndex];
            self::ensureColumns($current, $uniqueColumns, 'target');
            if ($where !== null && !$where($current, $incoming)) {
                $skipped[] = $incoming;
                continue;
            }

            $updatedRow = $current;
            foreach ($assignments as $column => $assignment) {
                $updatedRow[$column] = $assignment($current, $incoming);
            }

            $otherRows = $rows;
            unset($otherRows[$conflictIndex]);
            self::ensureNoUniqueConflict(array_values($otherRows), $updatedRow, $uniqueConstraints, null, 'update');

            $rows[$conflictIndex] = $updatedRow;
            $updated[] = $updatedRow;
            $returning[] = $updatedRow;
            ++$changes;
        }

        return [
            'before' => $before,
            'after' => array_values($rows),
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'returning_rows' => $returning,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<array{target?:list<string>|null,action?:string,assignments?:array<string,callable(array<string,mixed>,array<string,mixed>):mixed>,where?:callable(array<string,mixed>,array<string,mixed>):bool|null}> $conflictArms
     * @param list<list<string>> $uniqueConstraints
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,matched_arms:list<array{incoming:array<string,mixed>,target:list<string>|null,action:string}>,changes:int}
     */
    public static function executeConflictArms(
        array $rows,
        array $incomingRows,
        array $conflictArms,
        array $uniqueConstraints,
    ): array {
        self::validateRows($rows, 'target');
        self::validateRows($incomingRows, 'incoming');
        $uniqueConstraints = self::normalizeUniqueConstraints($uniqueConstraints[0] ?? [], $uniqueConstraints);
        $conflictArms = self::normalizeConflictArms($conflictArms);
        self::validateConflictArmTargets($conflictArms, $uniqueConstraints);

        $before = $rows;
        $inserted = [];
        $updated = [];
        $skipped = [];
        $returning = [];
        $matchedArms = [];
        $changes = 0;

        foreach ($incomingRows as $incoming) {
            $match = self::findMatchingConflictArm($rows, $incoming, $uniqueConstraints, $conflictArms);
            if ($match === null) {
                self::ensureNoUniqueConflict($rows, $incoming, $uniqueConstraints, null, 'insert');
                $rows[] = $incoming;
                $inserted[] = $incoming;
                $returning[] = $incoming;
                ++$changes;
                continue;
            }

            $arm = $match['arm'];
            $current = $rows[$match['index']];
            $matchedArms[] = [
                'incoming' => $incoming,
                'target' => $arm['target'],
                'action' => $arm['action'],
            ];

            if ($arm['action'] === 'nothing') {
                $skipped[] = $incoming;
                continue;
            }
            if ($arm['where'] !== null && !$arm['where']($current, $incoming)) {
                $skipped[] = $incoming;
                continue;
            }

            $updatedRow = $current;
            foreach ($arm['assignments'] as $column => $assignment) {
                $updatedRow[$column] = $assignment($current, $incoming);
            }

            $otherRows = $rows;
            unset($otherRows[$match['index']]);
            self::ensureNoUniqueConflict(array_values($otherRows), $updatedRow, $uniqueConstraints, null, 'update');

            $rows[$match['index']] = $updatedRow;
            $updated[] = $updatedRow;
            $returning[] = $updatedRow;
            ++$changes;
        }

        return [
            'before' => $before,
            'after' => array_values($rows),
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'returning_rows' => $returning,
            'matched_arms' => $matchedArms,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param callable(array<string,mixed>,array<string,mixed>):bool|null $where
     * @param list<list<string>>|null $uniqueConstraints
     * @return array{before:list<array<string,mixed>>,after:list<array<string,mixed>>,inserted_rows:list<array<string,mixed>>,updated_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_trace:list<array{event:string,row:array<string,mixed>,old?:array<string,mixed>,new?:array<string,mixed>}>,changes:int,dependencies:list<string>}
     */
    public static function executeWithTriggerTrace(
        array $rows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        ?callable $where = null,
        ?array $uniqueConstraints = null,
        string $conflictAction = 'update',
    ): array {
        self::validateRows($rows, 'target');
        self::validateRows($incomingRows, 'incoming');
        self::validateUniqueColumns($uniqueColumns);
        $uniqueConstraints = self::normalizeUniqueConstraints($uniqueColumns, $uniqueConstraints);

        $conflictAction = strtolower($conflictAction);
        if (!in_array($conflictAction, ['update', 'nothing'], true)) {
            throw new \InvalidArgumentException('SQLite UPSERT trigger trace conflict action must be update or nothing');
        }
        if ($conflictAction === 'update') {
            self::validateAssignments($assignments);
        } elseif ($assignments !== []) {
            throw new \InvalidArgumentException('SQLite UPSERT trigger trace DO NOTHING cannot have assignments');
        }

        $before = $rows;
        $inserted = [];
        $updated = [];
        $skipped = [];
        $returning = [];
        $triggerTrace = [];
        $changes = 0;

        foreach ($incomingRows as $incoming) {
            self::ensureColumns($incoming, $uniqueColumns, 'incoming');
            $triggerTrace[] = ['event' => 'before-insert', 'row' => $incoming];

            $conflictIndex = self::findConflictIndex($rows, $incoming, $uniqueColumns);
            if ($conflictIndex === null) {
                self::ensureNoUniqueConflict($rows, $incoming, $uniqueConstraints, null, 'insert');
                $rows[] = $incoming;
                $inserted[] = $incoming;
                $returning[] = $incoming;
                $triggerTrace[] = ['event' => 'after-insert', 'row' => $incoming];
                ++$changes;
                continue;
            }

            if ($conflictAction === 'nothing') {
                $skipped[] = $incoming;
                continue;
            }

            $current = $rows[$conflictIndex];
            if ($where !== null && !$where($current, $incoming)) {
                $skipped[] = $incoming;
                continue;
            }

            $updatedRow = $current;
            foreach ($assignments as $column => $assignment) {
                $updatedRow[$column] = $assignment($current, $incoming);
            }

            $otherRows = $rows;
            unset($otherRows[$conflictIndex]);
            self::ensureNoUniqueConflict(array_values($otherRows), $updatedRow, $uniqueConstraints, null, 'update');

            $triggerTrace[] = ['event' => 'before-update', 'row' => $updatedRow, 'old' => $current, 'new' => $updatedRow];
            $rows[$conflictIndex] = $updatedRow;
            $updated[] = $updatedRow;
            $returning[] = $updatedRow;
            $triggerTrace[] = ['event' => 'after-update', 'row' => $updatedRow, 'old' => $current, 'new' => $updatedRow];
            ++$changes;
        }

        return [
            'before' => $before,
            'after' => array_values($rows),
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'returning_rows' => $returning,
            'trigger_trace' => $triggerTrace,
            'changes' => $changes,
            'dependencies' => [
                'sqlite-upsert-trigger-trace',
                'upsert2.test-100',
                'upsert2.test-110',
                'upsert2.test-300',
                'upsert2.test-310',
                'upsert2.test-320',
                'upsert2.test-400',
                'upsert2.test-410',
                'upsert2.test-420',
                'returning1.test-4.5',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string|callable(array<string,mixed>):mixed>|array<string,string|callable(array<string,mixed>):mixed>|null $projection
     * @return list<array<string,mixed>>
     */
    public static function returningRows(array $rows, ?array $projection = null): array
    {
        self::validateRows($rows, 'returning');

        if ($projection === null) {
            return array_map(static fn (array $row): array => $row, $rows);
        }

        $projected = [];
        foreach ($rows as $row) {
            $output = [];
            foreach ($projection as $alias => $expression) {
                if ($expression === '*') {
                    foreach ($row as $column => $value) {
                        $output[$column] = $value;
                    }
                    continue;
                }

                if (is_int($alias)) {
                    if (!is_string($expression) || $expression === '') {
                        throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection columns must be non-empty strings');
                    }
                    if (!array_key_exists($expression, $row)) {
                        throw new \InvalidArgumentException("SQLite UPSERT RETURNING projection column {$expression} is missing");
                    }
                    $output[$expression] = $row[$expression];
                    continue;
                }

                if (!is_string($alias) || $alias === '') {
                    throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection aliases must be non-empty strings');
                }
                if (is_string($expression)) {
                    if ($expression === '') {
                        throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection columns must be non-empty strings');
                    }
                    if (!array_key_exists($expression, $row)) {
                        throw new \InvalidArgumentException("SQLite UPSERT RETURNING projection column {$expression} is missing");
                    }
                    $output[$alias] = $row[$expression];
                    continue;
                }
                if (is_callable($expression)) {
                    $output[$alias] = $expression($row);
                    continue;
                }

                throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection expressions must be column names or callables');
            }
            $projected[] = $output;
        }

        return $projected;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string>|null $projection
     * @return list<array<string,mixed>>
     */
    public static function returningRowsWithScope(
        array $rows,
        ?array $projection,
        string $targetTable,
        ?string $targetAlias = null,
    ): array {
        self::validateRows($rows, 'returning');
        if ($targetTable === '') {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING target table must be a non-empty string');
        }
        if ($targetAlias === '') {
            throw new \InvalidArgumentException('SQLite UPSERT RETURNING target alias must be null or a non-empty string');
        }

        if ($projection === null) {
            return self::returningRows($rows, null);
        }

        $normalized = [];
        foreach ($projection as $expression) {
            if (!is_string($expression) || $expression === '') {
                throw new \InvalidArgumentException('SQLite UPSERT RETURNING projection columns must be non-empty strings');
            }
            if ($expression === '*') {
                $normalized[] = '*';
                continue;
            }
            if (str_ends_with($expression, '.*')) {
                throw new \InvalidArgumentException('RETURNING may not use "TABLE.*" wildcards');
            }

            $parts = explode('.', $expression);
            if (count($parts) > 2 || in_array('', $parts, true)) {
                throw new \InvalidArgumentException("SQLite UPSERT RETURNING projection column {$expression} is malformed");
            }
            if (count($parts) === 1) {
                $normalized[] = $expression;
                continue;
            }

            [$qualifier, $column] = $parts;
            if ($qualifier === $targetTable) {
                $normalized[] = $column;
                continue;
            }
            if ($qualifier === 'new' || $qualifier === 'old' || $qualifier === $targetAlias) {
                throw new \InvalidArgumentException("no such column: {$expression}");
            }

            throw new \InvalidArgumentException("no such column: {$expression}");
        }

        return self::returningRows($rows, $normalized);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     */
    private static function findConflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            if (self::rowsConflict($row, $incoming, $uniqueColumns)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<string> $uniqueColumns
     */
    private static function rowsConflict(array $left, array $right, array $uniqueColumns): bool
    {
        foreach ($uniqueColumns as $column) {
            if (!array_key_exists($column, $left) || !array_key_exists($column, $right)) {
                throw new \InvalidArgumentException("SQLite UPSERT unique column {$column} is missing from a row");
            }
            if ($left[$column] === null || $right[$column] === null || $left[$column] != $right[$column]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function validateRows(array $rows, string $label): void
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException("SQLite UPSERT {$label} rows must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite UPSERT {$label} row must be an array");
            }
        }
    }

    /**
     * @param list<string> $uniqueColumns
     */
    private static function validateUniqueColumns(array $uniqueColumns): void
    {
        if ($uniqueColumns === [] || !array_is_list($uniqueColumns)) {
            throw new \InvalidArgumentException('SQLite UPSERT unique columns must be a non-empty list');
        }
        foreach ($uniqueColumns as $column) {
            if (!is_string($column) || preg_match('/\A[A-Za-z_][A-Za-z0-9_ ]*\z/', $column) !== 1) {
                throw new \InvalidArgumentException('SQLite UPSERT unique column name is malformed');
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
            if (!is_array($constraint)) {
                throw new \InvalidArgumentException('SQLite UPSERT unique constraint must be a column list');
            }
            self::validateUniqueColumns($constraint);
            $normalized[] = $constraint;
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $conflictArms
     * @return list<array{target:list<string>|null,action:string,assignments:array<string,callable(array<string,mixed>,array<string,mixed>):mixed>,where:callable(array<string,mixed>,array<string,mixed>):bool|null}>
     */
    private static function normalizeConflictArms(array $conflictArms): array
    {
        if ($conflictArms === [] || !array_is_list($conflictArms)) {
            throw new \InvalidArgumentException('SQLite UPSERT conflict arms must be a non-empty list');
        }

        $normalized = [];
        foreach ($conflictArms as $arm) {
            if (!is_array($arm)) {
                throw new \InvalidArgumentException('SQLite UPSERT conflict arm must be an array');
            }
            $target = $arm['target'] ?? null;
            if ($target !== null) {
                if (!is_array($target)) {
                    throw new \InvalidArgumentException('SQLite UPSERT conflict arm target must be a column list or null');
                }
                self::validateUniqueColumns($target);
            }

            $action = strtolower((string) ($arm['action'] ?? 'update'));
            if (!in_array($action, ['update', 'nothing'], true)) {
                throw new \InvalidArgumentException('SQLite UPSERT conflict arm action must be update or nothing');
            }

            $assignments = $arm['assignments'] ?? [];
            if ($action === 'update') {
                if (!is_array($assignments)) {
                    throw new \InvalidArgumentException('SQLite UPSERT conflict arm assignments must be an array');
                }
                self::validateAssignments($assignments);
            } elseif ($assignments !== [] && $assignments !== null) {
                throw new \InvalidArgumentException('SQLite UPSERT DO NOTHING arm cannot have assignments');
            } else {
                $assignments = [];
            }

            $where = $arm['where'] ?? null;
            if ($where !== null && !is_callable($where)) {
                throw new \InvalidArgumentException('SQLite UPSERT conflict arm WHERE must be callable');
            }

            $normalized[] = [
                'target' => $target === null ? null : array_values($target),
                'action' => $action,
                'assignments' => $assignments,
                'where' => $where,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{target:list<string>|null,action:string,assignments:array<string,callable(array<string,mixed>,array<string,mixed>):mixed>,where:callable(array<string,mixed>,array<string,mixed>):bool|null}> $conflictArms
     * @param list<list<string>> $uniqueConstraints
     */
    private static function validateConflictArmTargets(array $conflictArms, array $uniqueConstraints): void
    {
        foreach ($conflictArms as $arm) {
            if ($arm['target'] === null) {
                continue;
            }
            if (!self::targetMatchesUniqueConstraint($arm['target'], $uniqueConstraints)) {
                throw new \InvalidArgumentException('SQLite UPSERT ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint');
            }
        }
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
     * @param list<list<string>> $uniqueConstraints
     * @param list<array{target:list<string>|null,action:string,assignments:array<string,callable(array<string,mixed>,array<string,mixed>):mixed>,where:callable(array<string,mixed>,array<string,mixed>):bool|null}> $conflictArms
     * @return array{index:int,arm:array{target:list<string>|null,action:string,assignments:array<string,callable(array<string,mixed>,array<string,mixed>):mixed>,where:callable(array<string,mixed>,array<string,mixed>):bool|null}}|null
     */
    private static function findMatchingConflictArm(array $rows, array $incoming, array $uniqueConstraints, array $conflictArms): ?array
    {
        foreach ($conflictArms as $arm) {
            $targets = $arm['target'] === null ? $uniqueConstraints : [$arm['target']];
            foreach ($targets as $target) {
                self::ensureColumns($incoming, $target, 'incoming');
                $index = self::findConflictIndex($rows, $incoming, $target);
                if ($index !== null) {
                    return ['index' => $index, 'arm' => $arm];
                }
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<list<string>> $uniqueConstraints
     */
    private static function ensureNoUniqueConflict(array $rows, array $candidate, array $uniqueConstraints, ?int $ignoreIndex, string $operation): void
    {
        foreach ($uniqueConstraints as $columns) {
            foreach ($rows as $index => $row) {
                if ($ignoreIndex !== null && $index === $ignoreIndex) {
                    continue;
                }
                if (self::rowsConflict($row, $candidate, $columns)) {
                    throw new \InvalidArgumentException("SQLite UPSERT {$operation} produced a unique constraint conflict");
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $assignments
     */
    private static function validateAssignments(array $assignments): void
    {
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite UPSERT DO UPDATE needs at least one assignment');
        }
        foreach ($assignments as $column => $assignment) {
            if (!is_string($column) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $column)) {
                throw new \InvalidArgumentException('SQLite UPSERT assignment column name is malformed');
            }
            if (!is_callable($assignment)) {
                throw new \InvalidArgumentException('SQLite UPSERT assignment must be callable');
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function ensureColumns(array $row, array $columns, string $label): void
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite UPSERT {$label} row is missing unique column {$column}");
            }
        }
    }
}
