<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRecursiveSavepointUpsertPlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param array{recursive_triggers?:bool,max_depth?:int,conflict_action?:string} $options
     * @return array{savepoint:string,parent:list<array<string,mixed>>,child:list<array<string,mixed>>,current_parent:list<array<string,mixed>>,current_child:list<array<string,mixed>>,attempted_parent:list<array<string,mixed>>,attempted_child:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,skipped:list<array<string,mixed>>,yielded:list<array<string,mixed>>,foreign_key_violations:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,rolled_back:bool,aborted:bool,rollback_scope:string,rollback_reason:?string,savepoint_preserved:bool,discarded:list<array<string,mixed>>,recursive_triggers:bool,max_depth:int,dependencies:list<string>}
     */
    public static function execute(
        string $savepoint,
        array $parentRows,
        array $childRows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        array $foreignKey,
        array $triggers,
        array $options = [],
    ): array {
        $savepoint = trim($savepoint);
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite recursive UPSERT savepoint name cannot be empty');
        }
        foreach ($uniqueColumns as $column) {
            self::identifier($column, 'unique column');
        }
        if ($uniqueColumns === []) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT requires at least one unique column');
        }

        $state = [
            'parent' => array_values($parentRows),
            'child' => array_values($childRows),
            'inserted' => [],
            'updated' => [],
            'skipped' => [],
            'yielded' => [],
            'foreign_key_violations' => [],
            'trigger_effects' => [],
            'changes' => 0,
        ];
        $spec = self::foreignKeySpec($foreignKey);
        $recursive = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = (int) ($options['max_depth'] ?? 1000);
        if ($maxDepth < 1) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT max depth must be positive');
        }
        $conflictAction = self::conflictAction((string) ($options['conflict_action'] ?? 'rollback'));
        $aborted = false;
        $rollbackReason = null;

        try {
            foreach ($incomingRows as $ordinal => $incoming) {
                self::applyRow($state, $incoming, $uniqueColumns, $assignments, $spec, $triggers, $recursive, $maxDepth, 0, $ordinal, null);
            }
        } catch (SQLiteRecursiveSavepointUpsertSignal $signal) {
            $rollbackReason = $signal->reason;
            if ($signal->action === 'ignore') {
                $state['skipped'][] = $signal->row;
                $state['yielded'][] = self::yieldRow($signal->ordinal, 'skipped', $signal->event, $signal->old, $signal->row, $signal->depth, 'raise-ignore');
            } elseif ($signal->action === 'fail') {
                $aborted = true;
            } elseif ($conflictAction === 'ignore') {
                $state['skipped'][] = $signal->row;
                $state['yielded'][] = self::yieldRow($signal->ordinal, 'skipped', $signal->event, $signal->old, $signal->row, $signal->depth, 'conflict-ignore');
            } elseif ($conflictAction === 'fail') {
                $aborted = true;
            } else {
                $aborted = true;
            }
        }

        $attemptedParent = array_values($state['parent']);
        $attemptedChild = array_values($state['child']);
        $rolledBack = $aborted && ($conflictAction === 'rollback' || $rollbackReason !== null);
        if ($rolledBack) {
            $discarded = array_merge($state['inserted'], $state['updated']);
            $state['parent'] = array_values($parentRows);
            $state['child'] = array_values($childRows);
            $state['inserted'] = [];
            $state['updated'] = [];
            $state['skipped'] = [];
            $state['changes'] = 0;
            $state['foreign_key_violations'] = [];
            $state['trigger_effects'][] = [
                'trigger' => null,
                'timing' => 'savepoint',
                'event' => 'rollback',
                'action' => 'rollback-to-current-savepoint',
                'depth' => 0,
                'ordinal' => null,
                'savepoint' => $savepoint,
                'discarded_count' => count($discarded),
                'reason' => $rollbackReason ?? 'recursive-upsert-abort',
            ];
        } else {
            $discarded = [];
        }

        return [
            'savepoint' => $savepoint,
            'parent' => array_values($state['parent']),
            'child' => array_values($state['child']),
            'current_parent' => array_values($state['parent']),
            'current_child' => array_values($state['child']),
            'attempted_parent' => $attemptedParent,
            'attempted_child' => $attemptedChild,
            'inserted' => array_values($state['inserted']),
            'updated' => array_values($state['updated']),
            'skipped' => array_values($state['skipped']),
            'yielded' => array_values($state['yielded']),
            'foreign_key_violations' => self::dedupeViolations($state['foreign_key_violations']),
            'trigger_effects' => array_values($state['trigger_effects']),
            'changes' => $state['changes'],
            'rolled_back' => $rolledBack,
            'aborted' => $aborted,
            'rollback_scope' => $rolledBack ? 'savepoint' : 'none',
            'rollback_reason' => $rollbackReason,
            'savepoint_preserved' => self::rowsEqual($state['parent'], $parentRows) && self::rowsEqual($state['child'], $childRows),
            'discarded' => array_values($discarded),
            'recursive_triggers' => $recursive,
            'max_depth' => $maxDepth,
            'dependencies' => [
                'sqlite-upsert-trigger-yield',
                'sqlite-recursive-trigger-current-savepoint',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     * @param list<array<string,mixed>> $triggers
     */
    private static function applyRow(array &$state, array $incoming, array $uniqueColumns, array $assignments, array $spec, array $triggers, bool $recursive, int $maxDepth, int $depth, int $ordinal, ?string $sourceTrigger): void
    {
        if ($depth > $maxDepth) {
            throw new SQLiteRecursiveSavepointUpsertSignal('rollback', 'recursive-trigger-depth', $incoming, $ordinal, 'insert', null, $depth);
        }
        $conflictIndex = self::findConflictIndex($state['parent'], $incoming, $uniqueColumns);
        $event = $conflictIndex === null ? 'insert' : 'update';
        $old = $conflictIndex === null ? null : $state['parent'][$conflictIndex];
        $new = $old ?? $incoming;
        if ($old !== null) {
            foreach ($assignments as $column => $assignment) {
                self::identifier((string) $column, 'assignment column');
                $new[$column] = $assignment($old, $incoming);
            }
        }

        self::fireTriggers($state, 'before', $event, $old, $new, $uniqueColumns, $assignments, $spec, $triggers, $recursive, $maxDepth, $depth, $ordinal);
        if ($old === null) {
            $state['parent'][] = $new;
            $state['inserted'][] = $new;
        } else {
            $state['parent'][$conflictIndex] = $new;
            $state['updated'][] = $new;
        }
        ++$state['changes'];

        $violations = self::foreignKeyViolations($state['parent'], $state['child'], $spec);
        if (!$spec['deferred'] && $violations !== []) {
            throw new SQLiteRecursiveSavepointUpsertSignal('rollback', 'foreign-key-immediate', $new, $ordinal, $event, $old, $depth);
        }
        $state['foreign_key_violations'] = array_merge($state['foreign_key_violations'], self::tagViolations($violations, $ordinal, 'statement', $depth));
        self::fireTriggers($state, 'after', $event, $old, $new, $uniqueColumns, $assignments, $spec, $triggers, $recursive, $maxDepth, $depth, $ordinal);
        $afterViolations = self::foreignKeyViolations($state['parent'], $state['child'], $spec);
        if (!$spec['deferred'] && $afterViolations !== []) {
            throw new SQLiteRecursiveSavepointUpsertSignal('rollback', 'foreign-key-after-trigger', $new, $ordinal, $event, $old, $depth);
        }
        $state['foreign_key_violations'] = array_merge($state['foreign_key_violations'], self::tagViolations($afterViolations, $ordinal, 'after-trigger', $depth));
        $state['yielded'][] = self::yieldRow($ordinal, 'changed', $event, $old, $new, $depth, $sourceTrigger);
    }

    /**
     * @param array<string,mixed> $state
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     * @param list<array<string,mixed>> $triggers
     */
    private static function fireTriggers(array &$state, string $timing, string $event, ?array $old, array &$new, array $uniqueColumns, array $assignments, array $spec, array $triggers, bool $recursive, int $maxDepth, int $depth, int $ordinal): void
    {
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, $new, $spec)) {
                continue;
            }
            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'set-new') {
                foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                    self::identifier((string) $column, 'trigger set column');
                    $new[$column] = self::value($value, $old, $new, $spec);
                }
            } elseif ($action === 'insert-child') {
                $state['child'][] = self::project((array) ($trigger['row'] ?? []), $old, $new, $spec);
            } elseif ($action === 'update-child') {
                $match = self::value($trigger['match'] ?? 'old.parent_key', $old, $new, $spec);
                $set = self::value($trigger['set_child_key'] ?? 'new.parent_key', $old, $new, $spec);
                foreach ($state['child'] as &$child) {
                    if (self::rowValue($child, $spec['child_key'], 'child row') == $match) {
                        $child[$spec['child_key']] = $set;
                    }
                }
                unset($child);
            } elseif ($action === 'upsert-parent') {
                if ($recursive) {
                    self::applyRow($state, self::project((array) ($trigger['row'] ?? []), $old, $new, $spec), $uniqueColumns, $assignments, $spec, $triggers, $recursive, $maxDepth, $depth + 1, $ordinal, (string) ($trigger['name'] ?? ''));
                }
            } elseif ($action === 'raise') {
                $raise = self::conflictAction((string) ($trigger['raise'] ?? 'rollback'));
                throw new SQLiteRecursiveSavepointUpsertSignal($raise, (string) ($trigger['reason'] ?? 'trigger-raise'), $new, $ordinal, $event, $old, $depth);
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite recursive UPSERT trigger action is unsupported');
            }

            $state['trigger_effects'][] = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'timing' => $timing,
                'event' => $event,
                'action' => $action,
                'depth' => $depth,
                'ordinal' => $ordinal,
                'row' => self::project((array) ($trigger['values'] ?? []), $old, $new, $spec),
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     */
    private static function findConflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new \InvalidArgumentException("SQLite recursive UPSERT unique column {$column} is missing");
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
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     * @return list<array<string,mixed>>
     */
    private static function foreignKeyViolations(array $parents, array $children, array $spec): array
    {
        $parentKeys = [];
        foreach ($parents as $parent) {
            $parentKeys[] = self::rowValue($parent, $spec['parent_key'], 'parent row');
        }

        $violations = [];
        foreach ($children as $index => $child) {
            $childKey = self::rowValue($child, $spec['child_key'], 'child row');
            if ($childKey === null || in_array($childKey, $parentKeys, true)) {
                continue;
            }
            $violations[] = ['child_index' => $index, 'child_key' => $childKey, 'parent' => $spec['parent_key']];
        }

        return $violations;
    }

    private static function yieldRow(int $ordinal, string $status, string $event, ?array $old, array $new, int $depth, ?string $sourceTrigger): array
    {
        return [
            'ordinal' => $ordinal,
            'status' => $status,
            'event' => $event,
            'depth' => $depth,
            'source_trigger' => $sourceTrigger,
            'old_key' => $old['setting_id'] ?? null,
            'new_key' => $new['setting_id'] ?? null,
            'key_name' => $new['key_name'] ?? null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $violations
     * @return list<array<string,mixed>>
     */
    private static function tagViolations(array $violations, int $ordinal, string $phase, int $depth): array
    {
        foreach ($violations as &$violation) {
            $violation['ordinal'] = $ordinal;
            $violation['phase'] = $phase;
            $violation['depth'] = $depth;
        }
        unset($violation);

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $violations
     * @return list<array<string,mixed>>
     */
    private static function dedupeViolations(array $violations): array
    {
        $seen = [];
        $deduped = [];
        foreach ($violations as $violation) {
            $key = implode('|', [(string) $violation['phase'], (string) $violation['ordinal'], (string) $violation['depth'], (string) $violation['child_index'], (string) $violation['child_key']]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $violation;
        }

        return $deduped;
    }

    /**
     * @param array<string,mixed> $template
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     * @return array<string,mixed>
     */
    private static function project(array $template, ?array $old, array $new, array $spec): array
    {
        $row = [];
        foreach ($template as $column => $value) {
            self::identifier((string) $column, 'projection column');
            $row[$column] = self::value($value, $old, $new, $spec);
        }

        return $row;
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @return array{parent_key:string,child_key:string,deferred:bool}
     */
    private static function foreignKeySpec(array $foreignKey): array
    {
        return [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key'),
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
        ];
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     */
    private static function value(mixed $value, ?array $old, array $new, array $spec): mixed
    {
        if ($value === 'new.parent_key') {
            return self::rowValue($new, $spec['parent_key'], 'NEW row');
        }
        if ($value === 'old.parent_key') {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite recursive UPSERT OLD row is unavailable for INSERT');
            }

            return self::rowValue($old, $spec['parent_key'], 'OLD row');
        }
        if (is_string($value) && str_starts_with($value, 'new_increment.')) {
            $column = substr($value, 14);
            $current = self::rowValue($new, $column, 'NEW row');

            return is_int($current) ? $current + 1 : $current;
        }
        if (is_string($value) && str_starts_with($value, 'concat:')) {
            return self::concatValue(substr($value, 7), $old, $new, $spec);
        }
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return self::rowValue($new, substr($value, 4), 'NEW row');
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite recursive UPSERT OLD row is unavailable for INSERT');
            }

            return self::rowValue($old, substr($value, 4), 'OLD row');
        }

        return $value;
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     */
    private static function concatValue(string $template, ?array $old, array $new, array $spec): string
    {
        return preg_replace_callback('/\b(?:new|old)\.[A-Za-z_][A-Za-z0-9_]*/', static function (array $match) use ($old, $new, $spec): string {
            return (string) self::value($match[0], $old, $new, $spec);
        }, $template) ?? $template;
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     */
    private static function whenMatches(mixed $when, ?array $old, array $new, array $spec): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new, $spec);
        $right = self::value($right, $old, $new, $spec);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite recursive UPSERT WHEN operator is unsupported'),
        };
    }

    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite recursive UPSERT {$label} missing column {$column}");
        }

        return $row[$column];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite recursive UPSERT {$label} is malformed");
        }

        return $value;
    }

    private static function conflictAction(string $action): string
    {
        $action = strtolower($action);
        if (!in_array($action, ['rollback', 'fail', 'ignore'], true)) {
            throw new \InvalidArgumentException('SQLite recursive UPSERT conflict action is unsupported');
        }

        return $action;
    }

    /**
     * @param list<array<string,mixed>> $left
     * @param list<array<string,mixed>> $right
     */
    private static function rowsEqual(array $left, array $right): bool
    {
        return json_encode(array_values($left), JSON_THROW_ON_ERROR) === json_encode(array_values($right), JSON_THROW_ON_ERROR);
    }
}

final class SQLiteRecursiveSavepointUpsertSignal extends \RuntimeException
{
    public function __construct(
        public readonly string $action,
        public readonly string $reason,
        public readonly array $row,
        public readonly int $ordinal,
        public readonly string $event,
        public readonly ?array $old,
        public readonly int $depth,
    ) {
        parent::__construct($reason);
    }
}
