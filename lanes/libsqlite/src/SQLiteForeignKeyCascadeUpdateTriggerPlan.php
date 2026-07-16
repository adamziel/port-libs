<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteForeignKeyCascadeUpdateTriggerPlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $grandchildRows
     * @param list<array<string,mixed>> $updates
     * @param array{parent_key:string,child_key:string,on_update?:string} $foreignKey
     * @param list<array{timing:string,event:string,action:string,audit?:array<string,mixed>,grandchild_key?:mixed,set_grandchild_key?:mixed}> $childTriggers
     * @param array{parent_key:string,child_key:string,on_update?:string}|null $grandchildForeignKey
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>,cascade_actions:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int}
     */
    public static function updateParentKeys(
        array $parentRows,
        array $childRows,
        array $grandchildRows,
        array $updates,
        array $foreignKey,
        array $childTriggers = [],
        ?array $grandchildForeignKey = null,
    ): array {
        $spec = self::normalizeForeignKey($foreignKey, 'parent');
        $grandchildSpec = $grandchildForeignKey === null ? null : self::normalizeForeignKey($grandchildForeignKey, 'grandchild');
        $parents = array_values($parentRows);
        $children = array_values($childRows);
        $grandchildren = array_values($grandchildRows);
        $cascadeActions = [];
        $triggerEffects = [];
        $audit = [];
        $changes = 0;

        foreach ($updates as $update) {
            $oldKey = self::rowValue($update, $spec['parent_key'], 'update key');
            $newKey = array_key_exists('new_' . $spec['parent_key'], $update)
                ? $update['new_' . $spec['parent_key']]
                : self::rowValue($update, 'new_parent_key', 'update key');
            $parentIndex = self::findRowIndex($parents, $spec['parent_key'], $oldKey, 'parent row');
            if ($parentIndex === null) {
                continue;
            }

            $oldParent = $parents[$parentIndex];
            $parents[$parentIndex][$spec['parent_key']] = $newKey;
            $changes++;

            if ($spec['on_update'] !== 'cascade' || $oldKey === $newKey) {
                continue;
            }

            foreach ($children as $childIndex => $child) {
                if (self::rowValue($child, $spec['child_key'], 'child row') !== $oldKey) {
                    continue;
                }

                $newChild = $child;
                $newChild[$spec['child_key']] = $newKey;

                $before = self::applyChildTriggers('before', $child, $newChild, $grandchildren, $childTriggers, $grandchildSpec);
                $grandchildren = $before['grandchild'];
                $triggerEffects = array_merge($triggerEffects, $before['effects']);
                $audit = array_merge($audit, $before['audit']);
                $changes += $before['changes'];

                $children[$childIndex] = $newChild;
                $cascadeActions[] = [
                    'action' => 'cascade-update-child',
                    'old_parent_key' => $oldKey,
                    'new_parent_key' => $newKey,
                    'child_key' => $spec['child_key'],
                    'old_child' => $child,
                    'new_child' => $newChild,
                    'parent' => $oldParent,
                ];
                $changes++;

                if ($grandchildSpec !== null && $grandchildSpec['on_update'] === 'cascade') {
                    $grandchild = self::cascadeGrandchildren(
                        $grandchildren,
                        self::rowValue($child, $grandchildSpec['parent_key'], 'old child'),
                        self::rowValue($newChild, $grandchildSpec['parent_key'], 'new child'),
                        $grandchildSpec,
                    );
                    $grandchildren = $grandchild['grandchild'];
                    $cascadeActions = array_merge($cascadeActions, $grandchild['actions']);
                    $changes += $grandchild['changes'];
                }

                $after = self::applyChildTriggers('after', $child, $newChild, $grandchildren, $childTriggers, $grandchildSpec);
                $grandchildren = $after['grandchild'];
                $triggerEffects = array_merge($triggerEffects, $after['effects']);
                $audit = array_merge($audit, $after['audit']);
                $changes += $after['changes'];
            }
        }

        return [
            'parent' => array_values($parents),
            'child' => array_values($children),
            'grandchild' => array_values($grandchildren),
            'cascade_actions' => $cascadeActions,
            'trigger_effects' => $triggerEffects,
            'audit' => $audit,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $grandchildren
     * @param list<array{timing:string,event:string,action:string,audit?:array<string,mixed>,grandchild_key?:mixed,set_grandchild_key?:mixed}> $triggers
     * @param array{parent_key:string,child_key:string,on_update:string}|null $grandchildSpec
     * @return array{grandchild:list<array<string,mixed>>,effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int}
     */
    private static function applyChildTriggers(string $timing, array $oldChild, array $newChild, array $grandchildren, array $triggers, ?array $grandchildSpec): array
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
                foreach (($trigger['audit'] ?? []) as $column => $value) {
                    $row[$column] = self::triggerValue($value, $oldChild, $newChild, count($grandchildren));
                }
                $audit[] = $row;
                $effects[] = ['timing' => $timing, 'action' => $action, 'rows' => 1, 'grandchild_count' => count($grandchildren)];
                $changes++;
                continue;
            }

            if ($grandchildSpec === null) {
                throw new \InvalidArgumentException('SQLite cascaded child update trigger requires a grandchild foreign key');
            }

            if ($action === 'update-grandchild-key') {
                $match = array_key_exists('grandchild_key', $trigger)
                    ? self::triggerValue($trigger['grandchild_key'], $oldChild, $newChild, count($grandchildren))
                    : self::rowValue($oldChild, $grandchildSpec['parent_key'], 'old child');
                $set = self::triggerValue($trigger['set_grandchild_key'] ?? null, $oldChild, $newChild, count($grandchildren));
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
                    ? self::triggerValue($trigger['grandchild_key'], $oldChild, $newChild, count($grandchildren))
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

            throw new \InvalidArgumentException('SQLite cascaded child update trigger action is unsupported');
        }

        return ['grandchild' => array_values($grandchildren), 'effects' => $effects, 'audit' => $audit, 'changes' => $changes];
    }

    /**
     * @param list<array<string,mixed>> $grandchildren
     * @param array{parent_key:string,child_key:string,on_update:string} $spec
     * @return array{grandchild:list<array<string,mixed>>,actions:list<array<string,mixed>>,changes:int}
     */
    private static function cascadeGrandchildren(array $grandchildren, mixed $oldChildKey, mixed $newChildKey, array $spec): array
    {
        $actions = [];
        $changes = 0;

        foreach ($grandchildren as &$grandchild) {
            if (self::rowValue($grandchild, $spec['child_key'], 'grandchild row') !== $oldChildKey) {
                continue;
            }
            $oldGrandchild = $grandchild;
            $grandchild[$spec['child_key']] = $newChildKey;
            $actions[] = [
                'action' => 'cascade-update-grandchild',
                'old_child_key' => $oldChildKey,
                'new_child_key' => $newChildKey,
                'old_grandchild' => $oldGrandchild,
                'new_grandchild' => $grandchild,
            ];
            $changes++;
        }
        unset($grandchild);

        return ['grandchild' => array_values($grandchildren), 'actions' => $actions, 'changes' => $changes];
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update?:string} $foreignKey
     * @return array{parent_key:string,child_key:string,on_update:string}
     */
    private static function normalizeForeignKey(array $foreignKey, string $label): array
    {
        $action = strtolower(trim((string) ($foreignKey['on_update'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'no action'], true)) {
            throw new \InvalidArgumentException("SQLite {$label} cascaded update foreign key action is unsupported");
        }

        return [
            'parent_key' => self::identifier($foreignKey['parent_key'] ?? null, "{$label} parent key"),
            'child_key' => self::identifier($foreignKey['child_key'] ?? null, "{$label} child key"),
            'on_update' => $action,
        ];
    }

    private static function triggerValue(mixed $value, array $oldChild, array $newChild, int $grandchildCount): mixed
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
            throw new \InvalidArgumentException("SQLite cascaded child update trigger {$label} is malformed");
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
            throw new \InvalidArgumentException("SQLite cascaded child update trigger {$label} is missing column {$column}");
        }

        return $row[$column];
    }
}
