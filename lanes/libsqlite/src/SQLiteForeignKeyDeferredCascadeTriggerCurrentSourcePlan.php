<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $grandchildRows
     * @param list<array<string,mixed>> $deleteKeys
     * @param array{parent_key:string,child_key:string,on_delete?:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $childTriggers
     * @param array{parent_key:string,child_key:string,on_delete?:string}|null $grandchildForeignKey
     * @param list<array<string,mixed>> $currentSourceOps
     * @return array{before_statement:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>},after_statement:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>},before_commit:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>},after_commit:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>},deferred:list<array<string,mixed>>,current_source_actions:list<array<string,mixed>>,cascade_actions:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int,dependencies:list<string>}
     */
    public static function deleteParents(
        array $parentRows,
        array $childRows,
        array $grandchildRows,
        array $deleteKeys,
        array $foreignKey,
        array $childTriggers = [],
        ?array $grandchildForeignKey = null,
        array $currentSourceOps = [],
    ): array {
        $spec = self::normalizeForeignKey($foreignKey, 'parent');
        $grandchildSpec = $grandchildForeignKey === null ? null : self::normalizeForeignKey($grandchildForeignKey, 'grandchild');
        $parents = array_values($parentRows);
        $children = array_values($childRows);
        $grandchildren = array_values($grandchildRows);
        $deletedKeys = [];
        $deferred = [];
        $statementChanges = 0;

        foreach ($deleteKeys as $deleteKey) {
            $parentKey = self::rowValue($deleteKey, $spec['parent_key'], 'delete key');
            $parentIndex = self::findRowIndex($parents, $spec['parent_key'], $parentKey, 'parent row');
            if ($parentIndex === null) {
                continue;
            }

            unset($parents[$parentIndex]);
            $parents = array_values($parents);
            $deletedKeys[] = $parentKey;
            $statementChanges++;
            $deferred[] = [
                'operation' => 'deferred-delete-parent',
                'parent_key' => $parentKey,
                'action' => $spec['on_delete'],
                'deferred' => $spec['deferred'],
            ];
        }

        if ($spec['on_delete'] === 'restrict' && self::hasReferencingChild($children, $deletedKeys, $spec['child_key'])) {
            throw new \InvalidArgumentException('SQLite deferred cascade trigger RESTRICT prevents parent delete before commit');
        }

        $afterStatement = [
            'parent' => $parents,
            'child' => $children,
            'grandchild' => $grandchildren,
        ];
        $current = self::applyCurrentSourceOps($children, $grandchildren, $currentSourceOps, $spec, $grandchildSpec);
        $children = $current['child'];
        $grandchildren = $current['grandchild'];

        $commit = self::commitDeferredCascade($children, $grandchildren, $deletedKeys, $spec, $childTriggers, $grandchildSpec);

        return [
            'before_statement' => [
                'parent' => array_values($parentRows),
                'child' => array_values($childRows),
                'grandchild' => array_values($grandchildRows),
            ],
            'after_statement' => $afterStatement,
            'before_commit' => [
                'parent' => $parents,
                'child' => $children,
                'grandchild' => $grandchildren,
            ],
            'after_commit' => [
                'parent' => $parents,
                'child' => $commit['child'],
                'grandchild' => $commit['grandchild'],
            ],
            'deferred' => $deferred,
            'current_source_actions' => $current['actions'],
            'cascade_actions' => $commit['cascade_actions'],
            'trigger_effects' => $commit['trigger_effects'],
            'audit' => $commit['audit'],
            'violations' => $commit['violations'],
            'changes' => $statementChanges + $current['changes'] + $commit['changes'],
            'dependencies' => [
                'sqlite-deferred-foreign-key-cascade',
                'sqlite-current-source-before-deferred-commit',
                'sqlite-cascade-delete-trigger-current-source',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $grandchildRows
     * @param list<array<string,mixed>> $updates
     * @param array{parent_key:string,child_key:string,on_update?:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $childTriggers
     * @param array{parent_key:string,child_key:string,on_update?:string}|null $grandchildForeignKey
     * @param list<array<string,mixed>> $currentSourceOps
     * @return array{before_statement:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>},after_statement:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>},before_commit:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>},after_commit:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>},deferred:list<array<string,mixed>>,current_source_actions:list<array<string,mixed>>,cascade_actions:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int,dependencies:list<string>}
     */
    public static function updateParents(
        array $parentRows,
        array $childRows,
        array $grandchildRows,
        array $updates,
        array $foreignKey,
        array $childTriggers = [],
        ?array $grandchildForeignKey = null,
        array $currentSourceOps = [],
    ): array {
        $spec = self::normalizeUpdateForeignKey($foreignKey, 'parent');
        $grandchildSpec = $grandchildForeignKey === null ? null : self::normalizeUpdateForeignKey($grandchildForeignKey, 'grandchild');
        $parents = array_values($parentRows);
        $children = array_values($childRows);
        $grandchildren = array_values($grandchildRows);
        $updatedKeys = [];
        $deferred = [];
        $statementChanges = 0;

        foreach ($updates as $update) {
            $oldKey = self::rowValue($update, $spec['parent_key'], 'update key');
            $newKey = array_key_exists('new_' . $spec['parent_key'], $update)
                ? $update['new_' . $spec['parent_key']]
                : self::rowValue($update, 'new', 'update key');
            $parentIndex = self::findRowIndex($parents, $spec['parent_key'], $oldKey, 'parent row');
            if ($parentIndex === null) {
                continue;
            }

            $parent = $parents[$parentIndex];
            foreach ($update as $column => $value) {
                if ($column === 'new' || str_starts_with((string) $column, 'new_')) {
                    continue;
                }
                if ($column !== $spec['parent_key']) {
                    $parent[$column] = $value;
                }
            }
            $parent[$spec['parent_key']] = $newKey;
            $parents[$parentIndex] = $parent;
            $statementChanges++;

            if ($oldKey !== $newKey) {
                $updatedKeys[] = ['old' => $oldKey, 'new' => $newKey];
                $deferred[] = [
                    'operation' => 'deferred-update-parent',
                    'old_parent_key' => $oldKey,
                    'new_parent_key' => $newKey,
                    'action' => $spec['on_update'],
                    'deferred' => $spec['deferred'],
                ];
            }
        }

        if ($spec['on_update'] === 'restrict' && self::hasReferencingUpdatedChild($children, $updatedKeys, $spec['child_key'])) {
            throw new \InvalidArgumentException('SQLite deferred cascade trigger RESTRICT prevents parent update before commit');
        }

        $afterStatement = [
            'parent' => array_values($parents),
            'child' => $children,
            'grandchild' => $grandchildren,
        ];
        $current = self::applyCurrentSourceOps($children, $grandchildren, $currentSourceOps, [
            'parent_key' => $spec['parent_key'],
            'child_key' => $spec['child_key'],
            'on_delete' => 'cascade',
            'deferred' => $spec['deferred'],
        ], $grandchildSpec === null ? null : [
            'parent_key' => $grandchildSpec['parent_key'],
            'child_key' => $grandchildSpec['child_key'],
            'on_delete' => 'cascade',
        ]);
        $children = $current['child'];
        $grandchildren = $current['grandchild'];

        $commit = self::commitDeferredUpdateCascade($children, $grandchildren, $updatedKeys, $spec, $childTriggers, $grandchildSpec);

        return [
            'before_statement' => [
                'parent' => array_values($parentRows),
                'child' => array_values($childRows),
                'grandchild' => array_values($grandchildRows),
            ],
            'after_statement' => $afterStatement,
            'before_commit' => [
                'parent' => array_values($parents),
                'child' => $children,
                'grandchild' => $grandchildren,
            ],
            'after_commit' => [
                'parent' => array_values($parents),
                'child' => $commit['child'],
                'grandchild' => $commit['grandchild'],
            ],
            'deferred' => $deferred,
            'current_source_actions' => $current['actions'],
            'cascade_actions' => $commit['cascade_actions'],
            'trigger_effects' => $commit['trigger_effects'],
            'audit' => $commit['audit'],
            'violations' => $commit['violations'],
            'changes' => $statementChanges + $current['changes'] + $commit['changes'],
            'dependencies' => [
                'sqlite-deferred-foreign-key-cascade',
                'sqlite-current-source-before-deferred-commit',
                'sqlite-cascade-update-trigger-current-source',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $grandchildren
     * @param list<array<string,mixed>> $ops
     * @param array{parent_key:string,child_key:string,on_delete:string,deferred:bool} $spec
     * @param array{parent_key:string,child_key:string,on_delete:string}|null $grandchildSpec
     * @return array{child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>,actions:list<array<string,mixed>>,changes:int}
     */
    private static function applyCurrentSourceOps(array $children, array $grandchildren, array $ops, array $spec, ?array $grandchildSpec): array
    {
        $actions = [];
        $changes = 0;

        foreach ($ops as $op) {
            $operation = strtolower((string) ($op['operation'] ?? ''));
            $table = strtolower((string) ($op['table'] ?? 'child'));

            if ($table === 'child' && $operation === 'insert') {
                $row = self::row($op['row'] ?? null, 'current child insert');
                self::rowValue($row, $spec['child_key'], 'current child insert');
                $children[] = $row;
                $actions[] = ['action' => 'insert-current-child', 'child' => $row];
                $changes++;
                continue;
            }

            if ($table === 'child' && $operation === 'update') {
                $match = self::row($op['match'] ?? null, 'current child update match');
                $set = self::row($op['set'] ?? null, 'current child update set');
                $updated = 0;
                foreach ($children as &$child) {
                    if (!self::rowMatches($child, $match)) {
                        continue;
                    }
                    $child = array_replace($child, $set);
                    self::rowValue($child, $spec['child_key'], 'current child update');
                    $updated++;
                }
                unset($child);
                $actions[] = ['action' => 'update-current-child', 'rows' => $updated, 'match' => $match, 'set' => $set];
                $changes += $updated;
                continue;
            }

            if ($table === 'child' && $operation === 'delete') {
                $match = self::row($op['match'] ?? null, 'current child delete match');
                $kept = [];
                $deleted = 0;
                foreach ($children as $child) {
                    if (self::rowMatches($child, $match)) {
                        $deleted++;
                        continue;
                    }
                    $kept[] = $child;
                }
                $children = array_values($kept);
                $actions[] = ['action' => 'delete-current-child', 'rows' => $deleted, 'match' => $match];
                $changes += $deleted;
                continue;
            }

            if ($table === 'grandchild') {
                if ($grandchildSpec === null) {
                    throw new \InvalidArgumentException('SQLite deferred cascade current grandchild operation requires a grandchild foreign key');
                }

                if ($operation === 'insert') {
                    $row = self::row($op['row'] ?? null, 'current grandchild insert');
                    self::rowValue($row, $grandchildSpec['child_key'], 'current grandchild insert');
                    $grandchildren[] = $row;
                    $actions[] = ['action' => 'insert-current-grandchild', 'grandchild' => $row];
                    $changes++;
                    continue;
                }

                if ($operation === 'update') {
                    $match = self::row($op['match'] ?? null, 'current grandchild update match');
                    $set = self::row($op['set'] ?? null, 'current grandchild update set');
                    $updated = 0;
                    foreach ($grandchildren as &$grandchild) {
                        if (!self::rowMatches($grandchild, $match)) {
                            continue;
                        }
                        $grandchild = array_replace($grandchild, $set);
                        self::rowValue($grandchild, $grandchildSpec['child_key'], 'current grandchild update');
                        $updated++;
                    }
                    unset($grandchild);
                    $actions[] = ['action' => 'update-current-grandchild', 'rows' => $updated, 'match' => $match, 'set' => $set];
                    $changes += $updated;
                    continue;
                }

                if ($operation === 'delete') {
                    $match = self::row($op['match'] ?? null, 'current grandchild delete match');
                    $kept = [];
                    $deleted = 0;
                    foreach ($grandchildren as $grandchild) {
                        if (self::rowMatches($grandchild, $match)) {
                            $deleted++;
                            continue;
                        }
                        $kept[] = $grandchild;
                    }
                    $grandchildren = array_values($kept);
                    $actions[] = ['action' => 'delete-current-grandchild', 'rows' => $deleted, 'match' => $match];
                    $changes += $deleted;
                    continue;
                }
            }

            throw new \InvalidArgumentException('SQLite deferred cascade current-source operation is unsupported');
        }

        return ['child' => array_values($children), 'grandchild' => array_values($grandchildren), 'actions' => $actions, 'changes' => $changes];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $grandchildren
     * @param list<mixed> $deletedKeys
     * @param array{parent_key:string,child_key:string,on_delete:string,deferred:bool} $spec
     * @param list<array<string,mixed>> $triggers
     * @param array{parent_key:string,child_key:string,on_delete:string}|null $grandchildSpec
     * @return array{child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>,cascade_actions:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int}
     */
    private static function commitDeferredCascade(array $children, array $grandchildren, array $deletedKeys, array $spec, array $triggers, ?array $grandchildSpec): array
    {
        $deleted = array_fill_keys(array_map(self::keyIndex(...), $deletedKeys), true);
        $keptChildren = [];
        $cascadeActions = [];
        $triggerEffects = [];
        $audit = [];
        $violations = [];
        $changes = 0;

        foreach ($children as $child) {
            $childKey = self::rowValue($child, $spec['child_key'], 'child row');
            if (!array_key_exists(self::keyIndex($childKey), $deleted)) {
                $keptChildren[] = $child;
                continue;
            }

            if ($spec['on_delete'] !== 'cascade') {
                $violations[] = ['reason' => 'referenced-parent-deleted-at-deferred-commit', 'child_key' => $childKey, 'child' => $child];
                $keptChildren[] = $child;
                continue;
            }

            $before = self::applyChildTriggers('before', $child, $grandchildren, $triggers, $grandchildSpec);
            $grandchildren = $before['grandchild'];
            $triggerEffects = array_merge($triggerEffects, $before['effects']);
            $audit = array_merge($audit, $before['audit']);
            $changes += $before['changes'];

            $cascadeActions[] = ['action' => 'deferred-cascade-delete-child', 'child_key' => $childKey, 'child' => $child];
            $changes++;

            if ($grandchildSpec !== null && $grandchildSpec['on_delete'] === 'cascade') {
                $grandchild = self::cascadeGrandchildren($grandchildren, self::rowValue($child, $grandchildSpec['parent_key'], 'child row'), $grandchildSpec);
                $grandchildren = $grandchild['grandchild'];
                $cascadeActions = array_merge($cascadeActions, $grandchild['actions']);
                $changes += $grandchild['changes'];
            }

            $after = self::applyChildTriggers('after', $child, $grandchildren, $triggers, $grandchildSpec);
            $grandchildren = $after['grandchild'];
            $triggerEffects = array_merge($triggerEffects, $after['effects']);
            $audit = array_merge($audit, $after['audit']);
            $changes += $after['changes'];
        }

        return [
            'child' => array_values($keptChildren),
            'grandchild' => array_values($grandchildren),
            'cascade_actions' => $cascadeActions,
            'trigger_effects' => $triggerEffects,
            'audit' => $audit,
            'violations' => $violations,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $grandchildren
     * @param list<array{old:mixed,new:mixed}> $updatedKeys
     * @param array{parent_key:string,child_key:string,on_update:string,deferred:bool} $spec
     * @param list<array<string,mixed>> $triggers
     * @param array{parent_key:string,child_key:string,on_update:string}|null $grandchildSpec
     * @return array{child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>,cascade_actions:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int}
     */
    private static function commitDeferredUpdateCascade(array $children, array $grandchildren, array $updatedKeys, array $spec, array $triggers, ?array $grandchildSpec): array
    {
        $updates = [];
        foreach ($updatedKeys as $updated) {
            $updates[self::keyIndex($updated['old'])] = $updated['new'];
        }

        $cascadeActions = [];
        $triggerEffects = [];
        $audit = [];
        $violations = [];
        $changes = 0;

        foreach ($children as &$child) {
            $oldChild = $child;
            $childKey = self::rowValue($child, $spec['child_key'], 'child row');
            if (!array_key_exists(self::keyIndex($childKey), $updates)) {
                continue;
            }

            if ($spec['on_update'] !== 'cascade') {
                $violations[] = ['reason' => 'referenced-parent-updated-at-deferred-commit', 'child_key' => $childKey, 'child' => $child];
                continue;
            }

            $newChild = $child;
            $newChild[$spec['child_key']] = $updates[self::keyIndex($childKey)];

            $before = self::applyChildUpdateTriggers('before', $oldChild, $newChild, $grandchildren, $triggers, $grandchildSpec);
            $grandchildren = $before['grandchild'];
            $triggerEffects = array_merge($triggerEffects, $before['effects']);
            $audit = array_merge($audit, $before['audit']);
            $changes += $before['changes'];

            $child = $newChild;
            $cascadeActions[] = ['action' => 'deferred-cascade-update-child', 'old_child_key' => $childKey, 'new_child_key' => $newChild[$spec['child_key']], 'old_child' => $oldChild, 'child' => $newChild];
            $changes++;

            if ($grandchildSpec !== null && $grandchildSpec['on_update'] === 'cascade') {
                $grandchild = self::cascadeUpdateGrandchildren($grandchildren, self::rowValue($oldChild, $grandchildSpec['parent_key'], 'old child'), self::rowValue($newChild, $grandchildSpec['parent_key'], 'new child'), $grandchildSpec);
                $grandchildren = $grandchild['grandchild'];
                $cascadeActions = array_merge($cascadeActions, $grandchild['actions']);
                $changes += $grandchild['changes'];
            }

            $after = self::applyChildUpdateTriggers('after', $oldChild, $newChild, $grandchildren, $triggers, $grandchildSpec);
            $grandchildren = $after['grandchild'];
            $triggerEffects = array_merge($triggerEffects, $after['effects']);
            $audit = array_merge($audit, $after['audit']);
            $changes += $after['changes'];
        }
        unset($child);

        return [
            'child' => array_values($children),
            'grandchild' => array_values($grandchildren),
            'cascade_actions' => $cascadeActions,
            'trigger_effects' => $triggerEffects,
            'audit' => $audit,
            'violations' => $violations,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $grandchildren
     * @param list<array<string,mixed>> $triggers
     * @param array{parent_key:string,child_key:string,on_delete:string}|null $grandchildSpec
     * @return array{grandchild:list<array<string,mixed>>,effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int}
     */
    private static function applyChildTriggers(string $timing, array $oldChild, array $grandchildren, array $triggers, ?array $grandchildSpec): array
    {
        $effects = [];
        $audit = [];
        $changes = 0;

        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== 'delete') {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? ''));
            if ($action === 'insert-audit') {
                $row = [];
                foreach (self::row($trigger['audit'] ?? [], 'trigger audit') as $column => $value) {
                    $row[$column] = self::triggerValue($value, $oldChild, count($grandchildren));
                }
                $audit[] = $row;
                $effects[] = ['timing' => $timing, 'action' => $action, 'rows' => 1, 'grandchild_count' => count($grandchildren)];
                $changes++;
                continue;
            }

            if ($grandchildSpec === null) {
                throw new \InvalidArgumentException('SQLite deferred cascade child trigger requires a grandchild foreign key');
            }

            if ($action === 'update-grandchild-key') {
                $match = array_key_exists('grandchild_key', $trigger)
                    ? self::triggerValue($trigger['grandchild_key'], $oldChild, count($grandchildren))
                    : self::rowValue($oldChild, $grandchildSpec['parent_key'], 'old child');
                $set = self::triggerValue($trigger['set_grandchild_key'] ?? null, $oldChild, count($grandchildren));
                $updated = 0;
                foreach ($grandchildren as &$grandchild) {
                    if (self::rowValue($grandchild, $grandchildSpec['child_key'], 'grandchild row') !== $match) {
                        continue;
                    }
                    $grandchild[$grandchildSpec['child_key']] = $set;
                    $updated++;
                }
                unset($grandchild);
                $effects[] = ['timing' => $timing, 'action' => $action, 'matched_grandchild_key' => $match, 'set_grandchild_key' => $set, 'rows' => $updated];
                $changes += $updated;
                continue;
            }

            if ($action === 'delete-grandchild') {
                $match = array_key_exists('grandchild_key', $trigger)
                    ? self::triggerValue($trigger['grandchild_key'], $oldChild, count($grandchildren))
                    : self::rowValue($oldChild, $grandchildSpec['parent_key'], 'old child');
                $kept = [];
                $deleted = 0;
                foreach ($grandchildren as $grandchild) {
                    if (self::rowValue($grandchild, $grandchildSpec['child_key'], 'grandchild row') === $match) {
                        $deleted++;
                        continue;
                    }
                    $kept[] = $grandchild;
                }
                $grandchildren = array_values($kept);
                $effects[] = ['timing' => $timing, 'action' => $action, 'matched_grandchild_key' => $match, 'rows' => $deleted];
                $changes += $deleted;
                continue;
            }

            throw new \InvalidArgumentException('SQLite deferred cascade child trigger action is unsupported');
        }

        return ['grandchild' => array_values($grandchildren), 'effects' => $effects, 'audit' => $audit, 'changes' => $changes];
    }

    /**
     * @param list<array<string,mixed>> $grandchildren
     * @param list<array<string,mixed>> $triggers
     * @param array{parent_key:string,child_key:string,on_update:string}|null $grandchildSpec
     * @return array{grandchild:list<array<string,mixed>>,effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int}
     */
    private static function applyChildUpdateTriggers(string $timing, array $oldChild, array $newChild, array $grandchildren, array $triggers, ?array $grandchildSpec): array
    {
        $effects = [];
        $audit = [];
        $changes = 0;

        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== 'update') {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? ''));
            if ($action === 'insert-audit') {
                $row = [];
                foreach (self::row($trigger['audit'] ?? [], 'trigger audit') as $column => $value) {
                    $row[$column] = self::updateTriggerValue($value, $oldChild, $newChild, count($grandchildren));
                }
                $audit[] = $row;
                $effects[] = ['timing' => $timing, 'action' => $action, 'rows' => 1, 'grandchild_count' => count($grandchildren)];
                $changes++;
                continue;
            }

            if ($grandchildSpec === null) {
                throw new \InvalidArgumentException('SQLite deferred cascade child update trigger requires a grandchild foreign key');
            }

            if ($action === 'update-grandchild-key') {
                $match = array_key_exists('grandchild_key', $trigger)
                    ? self::updateTriggerValue($trigger['grandchild_key'], $oldChild, $newChild, count($grandchildren))
                    : self::rowValue($oldChild, $grandchildSpec['parent_key'], 'old child');
                $set = self::updateTriggerValue($trigger['set_grandchild_key'] ?? null, $oldChild, $newChild, count($grandchildren));
                $updated = 0;
                foreach ($grandchildren as &$grandchild) {
                    if (self::rowValue($grandchild, $grandchildSpec['child_key'], 'grandchild row') !== $match) {
                        continue;
                    }
                    $grandchild[$grandchildSpec['child_key']] = $set;
                    $updated++;
                }
                unset($grandchild);
                $effects[] = ['timing' => $timing, 'action' => $action, 'matched_grandchild_key' => $match, 'set_grandchild_key' => $set, 'rows' => $updated];
                $changes += $updated;
                continue;
            }

            throw new \InvalidArgumentException('SQLite deferred cascade child update trigger action is unsupported');
        }

        return ['grandchild' => array_values($grandchildren), 'effects' => $effects, 'audit' => $audit, 'changes' => $changes];
    }

    /**
     * @param list<array<string,mixed>> $grandchildren
     * @param array{parent_key:string,child_key:string,on_delete:string} $spec
     * @return array{grandchild:list<array<string,mixed>>,actions:list<array<string,mixed>>,changes:int}
     */
    private static function cascadeGrandchildren(array $grandchildren, mixed $deletedChildKey, array $spec): array
    {
        $kept = [];
        $actions = [];
        $changes = 0;
        foreach ($grandchildren as $grandchild) {
            if (self::rowValue($grandchild, $spec['child_key'], 'grandchild row') !== $deletedChildKey) {
                $kept[] = $grandchild;
                continue;
            }
            $actions[] = ['action' => 'deferred-cascade-delete-grandchild', 'child_key' => $deletedChildKey, 'grandchild' => $grandchild];
            $changes++;
        }

        return ['grandchild' => array_values($kept), 'actions' => $actions, 'changes' => $changes];
    }

    /**
     * @param list<array<string,mixed>> $grandchildren
     * @param array{parent_key:string,child_key:string,on_update:string} $spec
     * @return array{grandchild:list<array<string,mixed>>,actions:list<array<string,mixed>>,changes:int}
     */
    private static function cascadeUpdateGrandchildren(array $grandchildren, mixed $oldChildKey, mixed $newChildKey, array $spec): array
    {
        $actions = [];
        $changes = 0;
        foreach ($grandchildren as &$grandchild) {
            if (self::rowValue($grandchild, $spec['child_key'], 'grandchild row') !== $oldChildKey) {
                continue;
            }
            $before = $grandchild;
            $grandchild[$spec['child_key']] = $newChildKey;
            $actions[] = ['action' => 'deferred-cascade-update-grandchild', 'old_child_key' => $oldChildKey, 'new_child_key' => $newChildKey, 'before' => $before, 'grandchild' => $grandchild];
            $changes++;
        }
        unset($grandchild);

        return ['grandchild' => array_values($grandchildren), 'actions' => $actions, 'changes' => $changes];
    }

    /**
     * @param array{parent_key:string,child_key:string,on_delete?:string,deferred?:bool} $foreignKey
     * @return array{parent_key:string,child_key:string,on_delete:string,deferred:bool}
     */
    private static function normalizeForeignKey(array $foreignKey, string $label): array
    {
        $action = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'no action', 'restrict'], true)) {
            throw new \InvalidArgumentException("SQLite deferred cascade {$label} foreign key action is unsupported");
        }

        return [
            'parent_key' => self::identifier($foreignKey['parent_key'] ?? null, "{$label} parent key"),
            'child_key' => self::identifier($foreignKey['child_key'] ?? null, "{$label} child key"),
            'on_delete' => $action,
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
        ];
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update?:string,deferred?:bool} $foreignKey
     * @return array{parent_key:string,child_key:string,on_update:string,deferred:bool}
     */
    private static function normalizeUpdateForeignKey(array $foreignKey, string $label): array
    {
        $action = strtolower(trim((string) ($foreignKey['on_update'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'no action', 'restrict'], true)) {
            throw new \InvalidArgumentException("SQLite deferred cascade {$label} foreign key action is unsupported");
        }

        return [
            'parent_key' => self::identifier($foreignKey['parent_key'] ?? null, "{$label} parent key"),
            'child_key' => self::identifier($foreignKey['child_key'] ?? null, "{$label} child key"),
            'on_update' => $action,
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
        ];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<mixed> $deletedKeys
     */
    private static function hasReferencingChild(array $children, array $deletedKeys, string $childKeyColumn): bool
    {
        $deleted = array_fill_keys(array_map(self::keyIndex(...), $deletedKeys), true);
        foreach ($children as $child) {
            $childKey = self::rowValue($child, $childKeyColumn, 'child row');
            if (array_key_exists(self::keyIndex($childKey), $deleted)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array{old:mixed,new:mixed}> $updatedKeys
     */
    private static function hasReferencingUpdatedChild(array $children, array $updatedKeys, string $childKeyColumn): bool
    {
        $updated = [];
        foreach ($updatedKeys as $key) {
            $updated[self::keyIndex($key['old'])] = true;
        }
        foreach ($children as $child) {
            $childKey = self::rowValue($child, $childKeyColumn, 'child row');
            if (array_key_exists(self::keyIndex($childKey), $updated)) {
                return true;
            }
        }

        return false;
    }

    private static function triggerValue(mixed $value, array $oldChild, int $grandchildCount): mixed
    {
        if ($value === 'grandchild_count') {
            return $grandchildCount;
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return self::rowValue($oldChild, substr($value, 4), 'old child');
        }

        return $value;
    }

    private static function updateTriggerValue(mixed $value, array $oldChild, array $newChild, int $grandchildCount): mixed
    {
        if ($value === 'grandchild_count') {
            return $grandchildCount;
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return self::rowValue($oldChild, substr($value, 4), 'old child');
        }
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return self::rowValue($newChild, substr($value, 4), 'new child');
        }

        return $value;
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite deferred cascade {$label} is malformed");
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
     * @param array<string,mixed> $match
     */
    private static function rowMatches(array $row, array $match): bool
    {
        foreach ($match as $column => $value) {
            if (!array_key_exists($column, $row) || $row[$column] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    private static function row(mixed $value, string $label): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("SQLite deferred cascade {$label} must be a row array");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite deferred cascade {$label} is missing column {$column}");
        }

        return $row[$column];
    }

    private static function keyIndex(mixed $key): string
    {
        return is_scalar($key) || $key === null ? get_debug_type($key) . ':' . (string) $key : serialize($key);
    }
}
