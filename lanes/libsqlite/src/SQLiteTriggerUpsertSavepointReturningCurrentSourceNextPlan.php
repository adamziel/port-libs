<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>|null,string,int):mixed> $returning
     * @return array<string,mixed>
     */
    public static function executeWithinSavepoint(
        string $savepoint,
        array $rows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $returning,
        ?callable $where = null,
    ): array {
        $savepoint = trim($savepoint);
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite trigger UPSERT savepoint name cannot be empty');
        }
        self::validateRows($rows, 'current');
        self::validateRows($incomingRows, 'incoming');
        self::validateColumns($uniqueColumns);

        $after = array_values($rows);
        $returningRows = [];
        $yieldStream = [];
        $triggerEffects = [];
        $inserted = [];
        $updated = [];
        $skipped = [];
        $ignored = [];
        $rollbackReason = null;
        $statement = 0;

        foreach ($incomingRows as $incoming) {
            $conflictIndex = self::findConflictIndex($after, $incoming, $uniqueColumns);
            if ($conflictIndex === null) {
                $old = null;
                $candidate = $incoming;
                $action = 'insert';
                $beforeEffects = self::triggerEffects('before', $action, $old, $candidate, $triggers);
                $triggerEffects = array_merge($triggerEffects, $beforeEffects);
                if (($ignoreReason = self::ignoreReason($beforeEffects)) !== null) {
                    $ignored[] = self::ignoredRow($statement, $action, $candidate, $ignoreReason);
                    ++$statement;
                    continue;
                }

                $returningRow = self::projectReturning($returning, $candidate, $old, $action, $statement);
                $returningRows[] = $returningRow;
                $yieldStream[] = self::yieldRow($savepoint, $statement, 0, $action, $returningRow, false);

                $after[] = $candidate;
                $inserted[] = $candidate;
                $afterIndex = array_key_last($after);
                $afterEffects = self::triggerEffects('after', $action, $old, $candidate, $triggers);
                $triggerEffects = array_merge($triggerEffects, $afterEffects);
                $after[$afterIndex] = self::applyAfterMutations($candidate, $action, $old, $triggers);
                $rollbackReason = self::rollbackReason($afterEffects) ?? $rollbackReason;
                ++$statement;
                if ($rollbackReason !== null) {
                    break;
                }
                continue;
            }

            $old = $after[$conflictIndex];
            if ($where !== null && !$where($old, $incoming)) {
                $skipped[] = $incoming;
                ++$statement;
                continue;
            }

            $candidate = $old;
            foreach ($assignments as $column => $assignment) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite trigger UPSERT assignment column is malformed');
                }
                $candidate[$column] = $assignment($old, $incoming);
            }

            $action = 'update';
            $beforeEffects = self::triggerEffects('before', $action, $old, $candidate, $triggers);
            $triggerEffects = array_merge($triggerEffects, $beforeEffects);
            if (($ignoreReason = self::ignoreReason($beforeEffects)) !== null) {
                $ignored[] = self::ignoredRow($statement, $action, $incoming, $ignoreReason);
                ++$statement;
                continue;
            }

            $returningRow = self::projectReturning($returning, $candidate, $old, $action, $statement);
            $returningRows[] = $returningRow;
            $yieldStream[] = self::yieldRow($savepoint, $statement, 0, $action, $returningRow, false);

            $after[$conflictIndex] = $candidate;
            $updated[] = $candidate;
            $afterEffects = self::triggerEffects('after', $action, $old, $candidate, $triggers);
            $triggerEffects = array_merge($triggerEffects, $afterEffects);
            $after[$conflictIndex] = self::applyAfterMutations($candidate, $action, $old, $triggers);
            $rollbackReason = self::rollbackReason($afterEffects) ?? $rollbackReason;
            ++$statement;
            if ($rollbackReason !== null) {
                break;
            }
        }

        $rolledBack = $rollbackReason !== null;
        if ($rolledBack) {
            foreach ($yieldStream as $index => $yield) {
                $yieldStream[$index]['rolled_back_after_yield'] = true;
            }
        }

        return [
            'savepoint' => $savepoint,
            'before' => array_values($rows),
            'after_statement' => array_values($after),
            'after_savepoint' => $rolledBack ? array_values($rows) : array_values($after),
            'current_returning' => $returningRows,
            'next_returning' => $rolledBack ? [] : $returningRows,
            'yield_stream' => $yieldStream,
            'inserted_rows' => $rolledBack ? [] : $inserted,
            'updated_rows' => $rolledBack ? [] : $updated,
            'skipped_rows' => $skipped,
            'ignored_rows' => $ignored,
            'trigger_effects_before_rollback' => $triggerEffects,
            'rolled_back_to_savepoint' => $rolledBack,
            'rollback_reason' => $rollbackReason,
            'discarded_returning_count' => $rolledBack ? count($returningRows) : 0,
            'changes' => $rolledBack ? 0 : count($returningRows),
            'status' => 'trigger-upsert-savepoint-returning-current-source-next132-ready',
            'dependencies' => [
                'sqlite-upsert-returning-trigger-current-source',
                'sqlite-returning-yield-before-savepoint-rollback',
                'sqlite-after-trigger-mutation-hidden-from-returning',
                'sqlite-savepoint-restores-current-source-after-trigger-rollback',
                'sqlite-trigger-raise-ignore-suppresses-returning-row',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function validateRows(array $rows, string $label): void
    {
        if (!array_is_list($rows)) {
            throw new \InvalidArgumentException("SQLite trigger UPSERT {$label} rows must be a list");
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException("SQLite trigger UPSERT {$label} row must be an array");
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function validateColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite trigger UPSERT unique columns cannot be empty');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite trigger UPSERT unique columns must be non-empty strings');
            }
        }
    }

    /**
     * @param list<string> $uniqueColumns
     */
    private static function findConflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new \InvalidArgumentException("SQLite trigger UPSERT unique column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return (int) $index;
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @return list<array<string,mixed>>
     */
    private static function triggerEffects(string $timing, string $event, ?array $old, array $new, array $triggers): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? null, $old, $new)) {
                continue;
            }

            $effect = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'timing' => $timing,
                'event' => $event,
                'row' => self::projectValues((array) ($trigger['values'] ?? []), $old, $new),
            ];
            if (($trigger['raise'] ?? null) !== null) {
                $effect['raise'] = strtolower((string) $trigger['raise']);
                $effect['reason'] = (string) ($trigger['reason'] ?? $trigger['name'] ?? 'trigger rollback');
            }
            $effects[] = $effect;
        }

        return $effects;
    }

    /**
     * @param list<array<string,mixed>> $triggers
     */
    private static function applyAfterMutations(array $row, string $event, ?array $old, array $triggers): array
    {
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== 'after' || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (($trigger['mutate_target'] ?? null) !== true || !self::whenMatches($trigger['when'] ?? null, $old, $row)) {
                continue;
            }
            foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite trigger UPSERT mutation column is malformed');
                }
                $row[$column] = self::value($value, $old, $row);
            }
        }

        return $row;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>|null,string,int):mixed> $returning
     * @return array<string,mixed>
     */
    private static function projectReturning(array $returning, array $new, ?array $old, string $action, int $statement): array
    {
        $row = [];
        foreach ($returning as $index => $expr) {
            if (is_callable($expr)) {
                $row['expr' . $index] = $expr($new, $old, $action, $statement);
                continue;
            }
            $alias = null;
            if (is_array($expr)) {
                $alias = isset($expr['as']) ? (string) $expr['as'] : null;
                $expr = (string) $expr['expr'];
            }
            if (!is_string($expr) || $expr === '') {
                throw new \InvalidArgumentException('SQLite trigger UPSERT RETURNING expression is malformed');
            }
            $column = $alias ?? str_replace(['new.', 'old.'], '', $expr);
            $row[$column] = self::value($expr, $old, $new);
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function projectValues(array $template, ?array $old, array $new): array
    {
        $row = [];
        foreach ($template as $column => $expr) {
            $row[(string) $column] = self::value($expr, $old, $new);
        }

        return $row;
    }

    private static function value(mixed $expr, ?array $old, array $new): mixed
    {
        if (is_string($expr) && str_starts_with($expr, 'new.')) {
            $column = substr($expr, 4);
            if (!array_key_exists($column, $new)) {
                throw new \InvalidArgumentException("SQLite trigger UPSERT NEW column {$column} is missing");
            }

            return $new[$column];
        }
        if (is_string($expr) && str_starts_with($expr, 'old.')) {
            $column = substr($expr, 4);
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite trigger UPSERT OLD row is unavailable for INSERT');
            }
            if (!array_key_exists($column, $old)) {
                throw new \InvalidArgumentException("SQLite trigger UPSERT OLD column {$column} is missing");
            }

            return $old[$column];
        }
        if (is_string($expr) && array_key_exists($expr, $new)) {
            return $new[$expr];
        }

        return $expr;
    }

    private static function whenMatches(mixed $when, ?array $old, array $new): bool
    {
        if ($when === null || $when === true) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new \InvalidArgumentException('SQLite trigger UPSERT WHEN clause is malformed');
        }

        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new);
        $right = self::value($right, $old, $new);

        return match (strtoupper((string) $operator)) {
            '=' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite trigger UPSERT WHEN operator is unsupported'),
        };
    }

    /**
     * @param list<array<string,mixed>> $effects
     */
    private static function rollbackReason(array $effects): ?string
    {
        foreach ($effects as $effect) {
            if (($effect['raise'] ?? null) === 'rollback') {
                return (string) ($effect['reason'] ?? $effect['trigger'] ?? 'trigger rollback');
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $effects
     */
    private static function ignoreReason(array $effects): ?string
    {
        foreach ($effects as $effect) {
            if (($effect['raise'] ?? null) === 'ignore') {
                return (string) ($effect['reason'] ?? $effect['trigger'] ?? 'trigger ignore');
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{statement:int,action:string,reason:string,row:array<string,mixed>}
     */
    private static function ignoredRow(int $statement, string $action, array $row, string $reason): array
    {
        return [
            'statement' => $statement,
            'action' => $action,
            'reason' => $reason,
            'row' => $row,
        ];
    }

    /**
     * @param array<string,mixed> $returning
     * @return array<string,mixed>
     */
    private static function yieldRow(string $savepoint, int $statement, int $depth, string $action, array $returning, bool $rolledBack): array
    {
        return [
            'savepoint' => $savepoint,
            'statement' => $statement,
            'depth' => $depth,
            'action' => $action,
            'returning' => $returning,
            'rolled_back_after_yield' => $rolledBack,
        ];
    }
}
