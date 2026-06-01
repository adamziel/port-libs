<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerReturningFkSavepointCurrentNextPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     * @param callable(array<string,mixed>):bool $where
     * @param array{parent_key:string,child_key:string,on_update?:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string):mixed> $returning
     * @param array{savepoint?:string,conflict_action?:string,rowid_column?:string,label_column?:string} $options
     * @return array{savepoint:string,status:string,current_parent:list<array<string,mixed>>,current_child:list<array<string,mixed>>,attempt_parent:list<array<string,mixed>>,attempt_child:list<array<string,mixed>>,next_parent:list<array<string,mixed>>,next_child:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,current_returning_rows:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,foreign_key_actions:list<array<string,mixed>>,foreign_key_violations:list<array<string,mixed>>,discarded_parent:list<array<string,mixed>>,discarded_child:list<array<string,mixed>>,changes:int,attempted_changes:int,rollback_reason:?string,savepoint_preserved:bool,dependencies:list<string>}
     */
    public static function update(
        array $parents,
        array $children,
        array $assignments,
        callable $where,
        array $foreignKey,
        array $triggers = [],
        array $returning = ['*'],
        array $options = [],
    ): array {
        $savepoint = self::identifier((string) ($options['savepoint'] ?? 'fk_returning_statement'), 'savepoint');
        $conflictAction = self::conflictAction((string) ($options['conflict_action'] ?? 'abort-statement'));
        $rowIdColumn = self::identifier((string) ($options['rowid_column'] ?? 'setting_id'), 'rowid column');
        $labelColumn = self::identifier((string) ($options['label_column'] ?? 'key_name'), 'label column');
        $fk = self::foreignKey($foreignKey);
        self::validateAssignments($assignments);

        $attemptParents = array_values($parents);
        $attemptChildren = array_values($children);
        $effects = [];
        $fkActions = [];
        $violations = [];
        $yielded = [];
        $rollbackReason = null;
        $attemptedChanges = 0;
        $rolledBack = false;

        foreach ($attemptParents as $index => $old) {
            if (!$where($old)) {
                continue;
            }

            $ordinal = count($yielded);
            $next = self::updatedRow($old, $assignments);

            try {
                $before = self::fireTriggers('before', 'update', $old, $next, $triggers, $ordinal);
                $next = $before['row'];
                $effects = array_merge($effects, $before['effects']);
            } catch (SQLiteTriggerReturningFkSavepointCurrentNextSignal $signal) {
                if ($signal->action === 'ignore') {
                    $yielded[] = self::yieldRow($ordinal, 'skipped', $old, $next, $returning, 'before-trigger-ignore', $signal->reason, $rowIdColumn, $labelColumn);
                    continue;
                }
                $rollbackReason = $signal->reason;
                $rolledBack = true;
                break;
            }

            $attemptParents[$index] = $next;
            ++$attemptedChanges;
            self::applyForeignKey($attemptChildren, $old, $next, $fk, $fkActions, $violations, $ordinal, 'statement');

            try {
                $beforeAfterTriggers = $next;
                $after = self::fireTriggers('after', 'update', $old, $next, $triggers, $ordinal);
                $next = $after['row'];
                $attemptParents[$index] = $next;
                $effects = array_merge($effects, $after['effects']);
                self::applyForeignKey($attemptChildren, $beforeAfterTriggers, $next, $fk, $fkActions, $violations, $ordinal, 'after-trigger');
            } catch (SQLiteTriggerReturningFkSavepointCurrentNextSignal $signal) {
                $yielded[] = self::yieldRow($ordinal, 'attempted-before-rollback', $old, $next, $returning, 'after-trigger-rollback', $signal->reason, $rowIdColumn, $labelColumn);
                $rollbackReason = $signal->reason;
                $rolledBack = true;
                break;
            }

            $yielded[] = self::yieldRow($ordinal, 'changed', $old, $next, $returning, 'statement', null, $rowIdColumn, $labelColumn);
        }

        foreach (self::findViolations($attemptParents, $attemptChildren, $fk, 'savepoint-release') as $violation) {
            $violations[] = $violation;
        }

        if ($violations !== [] && !$fk['deferred']) {
            $rollbackReason ??= 'foreign-key-constraint';
            $rolledBack = true;
        }

        if ($rolledBack && $conflictAction === 'abort-statement') {
            $nextParents = $parents;
            $nextChildren = $children;
            $status = 'rolled-back';
            $returningRows = [];
            $changes = 0;
        } else {
            $nextParents = $attemptParents;
            $nextChildren = $attemptChildren;
            $status = $violations !== [] && $fk['deferred'] ? 'deferred-violation' : 'released';
            $returningRows = array_values(array_map(static fn (array $row): array => $row['returning'], array_filter(
                $yielded,
                static fn (array $row): bool => $row['status'] === 'changed',
            )));
            $changes = count($returningRows);
        }

        return [
            'savepoint' => $savepoint,
            'status' => $status,
            'current_parent' => array_values($parents),
            'current_child' => array_values($children),
            'attempt_parent' => array_values($attemptParents),
            'attempt_child' => array_values($attemptChildren),
            'next_parent' => array_values($nextParents),
            'next_child' => array_values($nextChildren),
            'returning_rows' => $returningRows,
            'current_returning_rows' => array_values(array_map(static fn (array $row): array => $row['returning'], $yielded)),
            'yielded' => array_values($yielded),
            'trigger_effects' => array_values($effects),
            'foreign_key_actions' => array_values($fkActions),
            'foreign_key_violations' => array_values($violations),
            'discarded_parent' => self::discardedRows($parents, $attemptParents),
            'discarded_child' => self::discardedRows($children, $attemptChildren),
            'changes' => $changes,
            'attempted_changes' => $attemptedChanges,
            'rollback_reason' => $rollbackReason,
            'savepoint_preserved' => $nextParents === array_values($parents) && $nextChildren === array_values($children),
            'dependencies' => [
                'sqlite-trigger-returning-fk-savepoint-current-next74',
                'sqlite-fk-savepoint-returning-yield',
            ],
        ];
    }

    /** @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments */
    private static function validateAssignments(array $assignments): void
    {
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING FK savepoint UPDATE requires assignments');
        }
        foreach (array_keys($assignments) as $column) {
            self::identifier((string) $column, 'assignment column');
        }
    }

    /** @return array{parent_key:string,child_key:string,on_update:string,deferred:bool} */
    private static function foreignKey(array $foreignKey): array
    {
        $action = strtolower(trim((string) ($foreignKey['on_update'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'set null', 'no action'], true)) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING FK savepoint action is unsupported');
        }

        return [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'foreign key parent column'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'foreign key child column'),
            'on_update' => $action,
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
        ];
    }

    /** @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments */
    private static function updatedRow(array $old, array $assignments): array
    {
        $next = $old;
        foreach ($assignments as $column => $value) {
            $next[(string) $column] = is_callable($value) ? $value($old) : $value;
        }

        return $next;
    }

    /** @param list<array<string,mixed>> $children */
    private static function applyForeignKey(array &$children, array $old, array $next, array $fk, array &$actions, array &$violations, int $ordinal, string $phase): void
    {
        $oldKey = $old[$fk['parent_key']] ?? null;
        $newKey = $next[$fk['parent_key']] ?? null;
        if ($oldKey === $newKey) {
            return;
        }

        if ($fk['on_update'] === 'cascade') {
            $count = 0;
            foreach ($children as &$child) {
                if (($child[$fk['child_key']] ?? null) == $oldKey) {
                    $child[$fk['child_key']] = $newKey;
                    ++$count;
                }
            }
            unset($child);
            $actions[] = ['ordinal' => $ordinal, 'phase' => $phase, 'action' => 'cascade', 'from' => $oldKey, 'to' => $newKey, 'rows' => $count];
            return;
        }

        if ($fk['on_update'] === 'set null') {
            $count = 0;
            foreach ($children as &$child) {
                if (($child[$fk['child_key']] ?? null) == $oldKey) {
                    $child[$fk['child_key']] = null;
                    ++$count;
                }
            }
            unset($child);
            $actions[] = ['ordinal' => $ordinal, 'phase' => $phase, 'action' => 'set-null', 'from' => $oldKey, 'to' => null, 'rows' => $count];
            return;
        }

        $actions[] = ['ordinal' => $ordinal, 'phase' => $phase, 'action' => 'no-action', 'from' => $oldKey, 'to' => $newKey, 'rows' => 0];
        foreach ($children as $childIndex => $child) {
            if (($child[$fk['child_key']] ?? null) == $oldKey) {
                $violations[] = ['phase' => $phase, 'ordinal' => $ordinal, 'child_index' => (int) $childIndex, 'child_key' => $oldKey, 'missing_parent' => $oldKey, 'deferred' => $fk['deferred']];
            }
        }
    }

    /** @return list<array<string,mixed>> */
    private static function findViolations(array $parents, array $children, array $fk, string $phase): array
    {
        $keys = [];
        foreach ($parents as $parent) {
            $keys[] = $parent[$fk['parent_key']] ?? null;
        }
        $violations = [];
        foreach ($children as $index => $child) {
            $key = $child[$fk['child_key']] ?? null;
            if ($key !== null && !in_array($key, $keys, false)) {
                $violations[] = ['phase' => $phase, 'child_index' => (int) $index, 'child_key' => $key, 'missing_parent' => $key, 'deferred' => $fk['deferred']];
            }
        }

        return $violations;
    }

    /** @return array{row:array<string,mixed>,effects:list<array<string,mixed>>} */
    private static function fireTriggers(string $timing, string $event, array $old, array $next, array $triggers, int $ordinal): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, $next)) {
                continue;
            }
            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'set-new') {
                foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                    $next[self::identifier((string) $column, 'trigger set column')] = self::value($value, $old, $next);
                }
            } elseif ($action === 'raise') {
                throw new SQLiteTriggerReturningFkSavepointCurrentNextSignal(
                    self::raiseAction((string) ($trigger['raise'] ?? 'rollback')),
                    (string) ($trigger['reason'] ?? 'trigger-raise'),
                );
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite trigger RETURNING FK savepoint trigger action is unsupported');
            }
            $effects[] = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'timing' => $timing,
                'event' => $event,
                'action' => $action,
                'ordinal' => $ordinal,
                'row' => self::project((array) ($trigger['values'] ?? []), $old, $next),
            ];
        }

        return ['row' => $next, 'effects' => $effects];
    }

    private static function whenMatches(mixed $when, array $old, array $next): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when)) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING FK savepoint WHEN clause is malformed');
        }
        $left = self::value($when[0] ?? $when['left'] ?? null, $old, $next);
        $operator = strtolower((string) ($when[1] ?? $when['operator'] ?? '='));
        $right = self::value($when[2] ?? $when['right'] ?? null, $old, $next);

        return match ($operator) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'is' => $left === $right,
            'is not' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite trigger RETURNING FK savepoint WHEN operator is unsupported'),
        };
    }

    /** @return array<string,mixed> */
    private static function projection(array $row, array $old, array $returning, string $event): array
    {
        $out = [];
        foreach ($returning as $index => $expr) {
            if ($expr === '*') {
                $out['*'] = $row;
                continue;
            }
            if (is_callable($expr)) {
                $out['expr' . $index] = $expr($row, $old, $event);
                continue;
            }
            if (is_array($expr)) {
                $alias = self::identifier((string) ($expr['as'] ?? $expr['expr'] ?? ''), 'returning alias');
                $out[$alias] = self::value($expr['expr'] ?? null, $old, $row);
                continue;
            }
            $column = self::identifier((string) $expr, 'returning column');
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite trigger RETURNING FK savepoint missing RETURNING column {$column}");
            }
            $out[$column] = $row[$column];
        }

        return $out;
    }

    private static function yieldRow(int $ordinal, string $status, array $old, array $next, array $returning, string $phase, ?string $reason, string $rowIdColumn, string $labelColumn): array
    {
        return [
            'ordinal' => $ordinal,
            'status' => $status,
            'phase' => $phase,
            'old_key' => $old[$rowIdColumn] ?? null,
            'new_key' => $next[$rowIdColumn] ?? null,
            'key_name' => $next[$labelColumn] ?? $old[$labelColumn] ?? null,
            'reason' => $reason,
            'returning' => self::projection($next, $old, $returning, 'update'),
        ];
    }

    /** @return array<string,mixed> */
    private static function project(array $values, array $old, array $next): array
    {
        $projected = [];
        foreach ($values as $key => $value) {
            $projected[(string) $key] = self::value($value, $old, $next);
        }

        return $projected;
    }

    private static function value(mixed $value, array $old, array $next): mixed
    {
        if (is_callable($value)) {
            return $value($old, $next);
        }
        if (!is_string($value)) {
            return $value;
        }
        if (str_starts_with($value, 'old.')) {
            $column = substr($value, 4);
            if (!array_key_exists($column, $old)) {
                throw new \InvalidArgumentException("SQLite trigger RETURNING FK savepoint missing OLD column {$column}");
            }
            return $old[$column];
        }
        if (str_starts_with($value, 'new.')) {
            $column = substr($value, 4);
            if (!array_key_exists($column, $next)) {
                throw new \InvalidArgumentException("SQLite trigger RETURNING FK savepoint missing NEW column {$column}");
            }
            return $next[$column];
        }
        if (str_starts_with($value, 'concat:')) {
            $parts = explode(':', substr($value, 7));
            return implode('', array_map(static fn (string $part): mixed => self::value($part, $old, $next), $parts));
        }

        return $value;
    }

    /** @return list<array<string,mixed>> */
    private static function discardedRows(array $before, array $after): array
    {
        $discarded = [];
        foreach ($after as $index => $row) {
            if (!array_key_exists($index, $before) || $before[$index] != $row) {
                $discarded[] = $row;
            }
        }

        return $discarded;
    }

    private static function identifier(string $value, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException("SQLite trigger RETURNING FK savepoint {$label} is invalid");
        }

        return $value;
    }

    private static function conflictAction(string $action): string
    {
        $normalized = strtolower(trim($action));
        if (!in_array($normalized, ['abort-statement', 'keep-deferred'], true)) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING FK savepoint conflict action is unsupported');
        }

        return $normalized;
    }

    private static function raiseAction(string $action): string
    {
        $normalized = strtolower(trim($action));
        if (!in_array($normalized, ['rollback', 'abort', 'ignore'], true)) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING FK savepoint RAISE action is unsupported');
        }

        return $normalized === 'ignore' ? 'ignore' : 'rollback';
    }
}

final class SQLiteTriggerReturningFkSavepointCurrentNextSignal extends \RuntimeException
{
    public function __construct(public readonly string $action, public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
