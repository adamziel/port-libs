<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerBeforeCascadeSavepointPlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $grandchildRows
     * @param list<array<string,mixed>> $deleteKeys
     * @param array{parent_key:string,child_key:string,on_delete?:string} $foreignKey
     * @param list<array<string,mixed>> $beforeTriggers
     * @param array{parent_key:string,child_key:string,on_delete?:string}|null $grandchildForeignKey
     * @return array{savepoint:string,parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>,current_parent:list<array<string,mixed>>,current_child:list<array<string,mixed>>,current_grandchild:list<array<string,mixed>>,attempted_parent:list<array<string,mixed>>,attempted_child:list<array<string,mixed>>,attempted_grandchild:list<array<string,mixed>>,cascade_actions:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,skipped:list<array<string,mixed>>,discarded:list<array<string,mixed>>,changes:int,rolled_back:bool,aborted:bool,rollback_scope:string,rollback_reason:?string,savepoint_preserved:bool,dependencies:list<string>}
     */
    public static function deleteParents(
        string $savepoint,
        array $parentRows,
        array $childRows,
        array $grandchildRows,
        array $deleteKeys,
        array $foreignKey,
        array $beforeTriggers = [],
        ?array $grandchildForeignKey = null,
        string $conflictAction = 'rollback',
    ): array {
        $savepoint = trim($savepoint);
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite before-cascade savepoint name cannot be empty');
        }
        $spec = self::normalizeForeignKey($foreignKey, 'parent');
        $grandchildSpec = $grandchildForeignKey === null ? null : self::normalizeForeignKey($grandchildForeignKey, 'grandchild');
        $conflictAction = self::conflictAction($conflictAction);

        $parents = array_values($parentRows);
        $children = array_values($childRows);
        $grandchildren = array_values($grandchildRows);
        $cascadeActions = [];
        $triggerEffects = [];
        $audit = [];
        $skipped = [];
        $changes = 0;
        $aborted = false;
        $rollbackReason = null;

        foreach ($deleteKeys as $ordinal => $deleteKey) {
            $parentKey = self::rowValue($deleteKey, $spec['parent_key'], 'delete key');
            $parentIndex = self::findRowIndex($parents, $spec['parent_key'], $parentKey, 'parent row');
            if ($parentIndex === null) {
                continue;
            }
            $oldParent = $parents[$parentIndex];

            try {
                $before = self::applyBeforeTriggers($oldParent, $children, $grandchildren, $beforeTriggers, $spec, $grandchildSpec, $ordinal);
            } catch (SQLiteTriggerBeforeCascadeSignal $signal) {
                if ($signal->action === 'ignore') {
                    $skipped[] = [
                        'ordinal' => $ordinal,
                        'status' => 'skipped',
                        'reason' => $signal->reason,
                        'parent_key' => $parentKey,
                        'parent' => $oldParent,
                    ];
                    continue;
                }
                $rollbackReason = $signal->reason;
                $aborted = true;
                break;
            }

            $children = $before['child'];
            $grandchildren = $before['grandchild'];
            $triggerEffects = array_merge($triggerEffects, $before['effects']);
            $audit = array_merge($audit, $before['audit']);
            $changes += $before['changes'];

            unset($parents[$parentIndex]);
            $parents = array_values($parents);
            $changes++;

            if ($spec['on_delete'] !== 'cascade') {
                continue;
            }

            $cascade = self::cascadeChildren($children, $grandchildren, $oldParent, $spec, $grandchildSpec);
            $children = $cascade['child'];
            $grandchildren = $cascade['grandchild'];
            $cascadeActions = array_merge($cascadeActions, $cascade['actions']);
            $changes += $cascade['changes'];
        }

        $attemptedParent = array_values($parents);
        $attemptedChild = array_values($children);
        $attemptedGrandchild = array_values($grandchildren);
        $rolledBack = $aborted && $conflictAction === 'rollback';
        if ($rolledBack) {
            $discarded = self::discardedRows($parentRows, $childRows, $grandchildRows, $attemptedParent, $attemptedChild, $attemptedGrandchild);
            $parents = array_values($parentRows);
            $children = array_values($childRows);
            $grandchildren = array_values($grandchildRows);
            $changes = 0;
            $skipped = [];
            $triggerEffects[] = [
                'trigger' => null,
                'timing' => 'savepoint',
                'event' => 'rollback',
                'action' => 'rollback-to-current-savepoint',
                'savepoint' => $savepoint,
                'discarded_count' => count($discarded),
                'reason' => $rollbackReason,
            ];
        } else {
            $discarded = [];
        }

        return [
            'savepoint' => $savepoint,
            'parent' => array_values($parents),
            'child' => array_values($children),
            'grandchild' => array_values($grandchildren),
            'current_parent' => array_values($parents),
            'current_child' => array_values($children),
            'current_grandchild' => array_values($grandchildren),
            'attempted_parent' => $attemptedParent,
            'attempted_child' => $attemptedChild,
            'attempted_grandchild' => $attemptedGrandchild,
            'cascade_actions' => array_values($cascadeActions),
            'trigger_effects' => array_values($triggerEffects),
            'audit' => array_values($audit),
            'skipped' => array_values($skipped),
            'discarded' => array_values($discarded),
            'changes' => $changes,
            'rolled_back' => $rolledBack,
            'aborted' => $aborted,
            'rollback_scope' => $rolledBack ? 'savepoint' : ($aborted ? 'statement' : 'none'),
            'rollback_reason' => $rollbackReason,
            'savepoint_preserved' => self::rowsEqual($parents, $parentRows) && self::rowsEqual($children, $childRows) && self::rowsEqual($grandchildren, $grandchildRows),
            'dependencies' => [
                'sqlite-before-delete-trigger',
                'sqlite-foreign-key-cascade',
                'sqlite-savepoint-current-next-yield',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $grandchildren
     * @param list<array<string,mixed>> $triggers
     * @param array{parent_key:string,child_key:string,on_delete:string} $spec
     * @param array{parent_key:string,child_key:string,on_delete:string}|null $grandchildSpec
     * @return array{child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>,effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int}
     */
    private static function applyBeforeTriggers(array $oldParent, array $children, array $grandchildren, array $triggers, array $spec, ?array $grandchildSpec, int $ordinal): array
    {
        $effects = [];
        $audit = [];
        $changes = 0;

        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? 'before')) !== 'before' || strtolower((string) ($trigger['event'] ?? 'delete')) !== 'delete') {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $oldParent, $spec)) {
                continue;
            }
            $action = strtolower((string) ($trigger['action'] ?? 'insert-audit'));
            if ($action === 'raise') {
                $raise = self::conflictAction((string) ($trigger['raise'] ?? 'rollback'));
                throw new SQLiteTriggerBeforeCascadeSignal($raise, (string) ($trigger['reason'] ?? 'before-trigger-raise'));
            }
            if ($action === 'insert-audit') {
                $row = [];
                foreach ((array) ($trigger['audit'] ?? []) as $column => $value) {
                    self::identifier((string) $column, 'audit column');
                    $row[$column] = self::value($value, $oldParent, $children, $grandchildren, $spec);
                }
                $audit[] = $row;
                $effects[] = self::effect($trigger, $action, $oldParent, $ordinal, 1, $children, $grandchildren);
                $changes++;
                continue;
            }
            if ($action === 'update-child-key') {
                $match = self::value($trigger['match'] ?? 'old.parent_key', $oldParent, $children, $grandchildren, $spec);
                $set = self::value($trigger['set_child_key'] ?? null, $oldParent, $children, $grandchildren, $spec);
                $updated = 0;
                foreach ($children as &$child) {
                    if (self::rowValue($child, $spec['child_key'], 'child row') !== $match) {
                        continue;
                    }
                    $child[$spec['child_key']] = $set;
                    $updated++;
                }
                unset($child);
                $effects[] = self::effect($trigger, $action, $oldParent, $ordinal, $updated, $children, $grandchildren, ['matched_child_key' => $match, 'set_child_key' => $set]);
                $changes += $updated;
                continue;
            }
            if ($action === 'delete-child') {
                $match = self::value($trigger['match'] ?? 'old.parent_key', $oldParent, $children, $grandchildren, $spec);
                $kept = [];
                $deleted = 0;
                foreach ($children as $child) {
                    if (self::rowValue($child, $spec['child_key'], 'child row') === $match) {
                        $deleted++;
                        continue;
                    }
                    $kept[] = $child;
                }
                $children = array_values($kept);
                $effects[] = self::effect($trigger, $action, $oldParent, $ordinal, $deleted, $children, $grandchildren, ['matched_child_key' => $match]);
                $changes += $deleted;
                continue;
            }
            if ($action === 'update-grandchild-key') {
                if ($grandchildSpec === null) {
                    throw new \InvalidArgumentException('SQLite before-cascade grandchild trigger requires a grandchild foreign key');
                }
                $match = self::value($trigger['match'] ?? 'old.parent_key', $oldParent, $children, $grandchildren, $spec);
                $set = self::value($trigger['set_grandchild_key'] ?? null, $oldParent, $children, $grandchildren, $spec);
                $updated = 0;
                foreach ($grandchildren as &$grandchild) {
                    if (self::rowValue($grandchild, $grandchildSpec['child_key'], 'grandchild row') !== $match) {
                        continue;
                    }
                    $grandchild[$grandchildSpec['child_key']] = $set;
                    $updated++;
                }
                unset($grandchild);
                $effects[] = self::effect($trigger, $action, $oldParent, $ordinal, $updated, $children, $grandchildren, ['matched_grandchild_key' => $match, 'set_grandchild_key' => $set]);
                $changes += $updated;
                continue;
            }

            throw new \InvalidArgumentException('SQLite before-cascade trigger action is unsupported');
        }

        return ['child' => array_values($children), 'grandchild' => array_values($grandchildren), 'effects' => $effects, 'audit' => $audit, 'changes' => $changes];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $grandchildren
     * @param array{parent_key:string,child_key:string,on_delete:string} $spec
     * @param array{parent_key:string,child_key:string,on_delete:string}|null $grandchildSpec
     * @return array{child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>,actions:list<array<string,mixed>>,changes:int}
     */
    private static function cascadeChildren(array $children, array $grandchildren, array $oldParent, array $spec, ?array $grandchildSpec): array
    {
        $parentKey = self::rowValue($oldParent, $spec['parent_key'], 'old parent');
        $kept = [];
        $actions = [];
        $changes = 0;

        foreach ($children as $child) {
            if (self::rowValue($child, $spec['child_key'], 'child row') !== $parentKey) {
                $kept[] = $child;
                continue;
            }
            $actions[] = ['action' => 'cascade-delete-child', 'parent_key' => $parentKey, 'child_key' => $parentKey, 'parent' => $oldParent, 'child' => $child];
            $changes++;
            if ($grandchildSpec === null || $grandchildSpec['on_delete'] !== 'cascade') {
                continue;
            }
            $childKey = self::rowValue($child, $grandchildSpec['parent_key'], 'child row');
            $grandchildKept = [];
            foreach ($grandchildren as $grandchild) {
                if (self::rowValue($grandchild, $grandchildSpec['child_key'], 'grandchild row') !== $childKey) {
                    $grandchildKept[] = $grandchild;
                    continue;
                }
                $actions[] = ['action' => 'cascade-delete-grandchild', 'child_key' => $childKey, 'grandchild' => $grandchild];
                $changes++;
            }
            $grandchildren = array_values($grandchildKept);
        }

        return ['child' => array_values($kept), 'grandchild' => array_values($grandchildren), 'actions' => $actions, 'changes' => $changes];
    }

    /**
     * @param array{parent_key:string,child_key:string,on_delete?:string} $foreignKey
     * @return array{parent_key:string,child_key:string,on_delete:string}
     */
    private static function normalizeForeignKey(array $foreignKey, string $label): array
    {
        $action = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'no action'], true)) {
            throw new \InvalidArgumentException("SQLite before-cascade {$label} foreign key action is unsupported");
        }

        return [
            'parent_key' => self::identifier($foreignKey['parent_key'] ?? null, "{$label} parent key"),
            'child_key' => self::identifier($foreignKey['child_key'] ?? null, "{$label} child key"),
            'on_delete' => $action,
        ];
    }

    private static function conflictAction(string $action): string
    {
        $normalized = strtolower(trim($action));
        if (!in_array($normalized, ['rollback', 'abort', 'fail', 'ignore'], true)) {
            throw new \InvalidArgumentException('SQLite before-cascade trigger conflict action is unsupported');
        }

        return $normalized;
    }

    private static function whenMatches(mixed $when, array $oldParent, array $spec): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new \InvalidArgumentException('SQLite before-cascade trigger WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $oldParent, [], [], $spec);
        $operator = strtolower((string) $operator);
        return match ($operator) {
            '=' => $left === $right,
            '!=' , '<>' => $left !== $right,
            'is' => $left === $right,
            'is not' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite before-cascade trigger WHEN operator is unsupported'),
        };
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $grandchildren
     */
    private static function value(mixed $value, array $oldParent, array $children, array $grandchildren, array $spec): mixed
    {
        if ($value === 'child_count') {
            return count($children);
        }
        if ($value === 'grandchild_count') {
            return count($grandchildren);
        }
        if ($value === 'old.parent_key') {
            return self::rowValue($oldParent, $spec['parent_key'], 'old parent');
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return self::rowValue($oldParent, substr($value, 4), 'old parent');
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $grandchildren
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private static function effect(array $trigger, string $action, array $oldParent, int $ordinal, int $rows, array $children, array $grandchildren, array $extra = []): array
    {
        return $extra + [
            'trigger' => (string) ($trigger['name'] ?? ''),
            'timing' => 'before',
            'event' => 'delete',
            'action' => $action,
            'ordinal' => $ordinal,
            'parent_key' => $oldParent[array_key_first($oldParent)] ?? null,
            'rows' => $rows,
            'child_count' => count($children),
            'grandchild_count' => count($grandchildren),
        ];
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite before-cascade {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function findRowIndex(array $rows, string $column, mixed $key, string $label): ?int
    {
        foreach ($rows as $index => $row) {
            if (self::rowValue($row, $column, $label) === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite before-cascade {$label} is missing column {$column}");
        }

        return $row[$column];
    }

    /**
     * @param list<array<string,mixed>> $before
     * @param list<array<string,mixed>> $after
     */
    private static function rowsEqual(array $before, array $after): bool
    {
        return array_values($before) === array_values($after);
    }

    /**
     * @param list<array<string,mixed>> $beforeParent
     * @param list<array<string,mixed>> $beforeChild
     * @param list<array<string,mixed>> $beforeGrandchild
     * @param list<array<string,mixed>> $afterParent
     * @param list<array<string,mixed>> $afterChild
     * @param list<array<string,mixed>> $afterGrandchild
     * @return list<array<string,mixed>>
     */
    private static function discardedRows(array $beforeParent, array $beforeChild, array $beforeGrandchild, array $afterParent, array $afterChild, array $afterGrandchild): array
    {
        $discarded = [];
        foreach ([['parent', $beforeParent, $afterParent], ['child', $beforeChild, $afterChild], ['grandchild', $beforeGrandchild, $afterGrandchild]] as [$kind, $before, $after]) {
            foreach ($before as $row) {
                if (!in_array($row, $after, true)) {
                    $discarded[] = ['table' => $kind, 'row' => $row];
                }
            }
        }

        return $discarded;
    }
}

final class SQLiteTriggerBeforeCascadeSignal extends \RuntimeException
{
    public function __construct(
        public readonly string $action,
        public readonly string $reason,
    ) {
        parent::__construct($reason);
    }
}
