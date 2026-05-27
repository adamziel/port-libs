<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteForeignKeyCascadeTriggerPlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $grandchildRows
     * @param list<array<string,mixed>> $deleteKeys
     * @param array{parent_key:string,child_key:string,on_delete?:string} $foreignKey
     * @param list<array{timing:string,event:string,action:string,audit?:array<string,mixed>,grandchild_key?:mixed,set_grandchild_key?:mixed}> $childTriggers
     * @param array{parent_key:string,child_key:string,on_delete?:string}|null $grandchildForeignKey
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,grandchild:list<array<string,mixed>>,cascade_actions:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int}
     */
    public static function deleteParents(
        array $parentRows,
        array $childRows,
        array $grandchildRows,
        array $deleteKeys,
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

        foreach ($deleteKeys as $deleteKey) {
            $parentKey = self::rowValue($deleteKey, $spec['parent_key'], 'delete key');
            $parentIndex = self::findRowIndex($parents, $spec['parent_key'], $parentKey, 'parent row');
            if ($parentIndex === null) {
                continue;
            }

            unset($parents[$parentIndex]);
            $parents = array_values($parents);
            $changes++;

            if ($spec['on_delete'] !== 'cascade') {
                continue;
            }

            $keptChildren = [];
            foreach ($children as $child) {
                if (self::rowValue($child, $spec['child_key'], 'child row') !== $parentKey) {
                    $keptChildren[] = $child;
                    continue;
                }

                $before = self::applyChildTriggers('before', $child, $grandchildren, $childTriggers, $grandchildSpec);
                $grandchildren = $before['grandchild'];
                $triggerEffects = array_merge($triggerEffects, $before['effects']);
                $audit = array_merge($audit, $before['audit']);
                $changes += $before['changes'];

                $cascadeActions[] = [
                    'action' => 'cascade-delete-child',
                    'parent_key' => $parentKey,
                    'child_key' => self::rowValue($child, $spec['child_key'], 'child row'),
                    'child' => $child,
                ];
                $changes++;

                if ($grandchildSpec !== null && strtolower($grandchildSpec['on_delete']) === 'cascade') {
                    $grandchild = self::cascadeGrandchildren($grandchildren, self::rowValue($child, $grandchildSpec['parent_key'], 'child row'), $grandchildSpec);
                    $grandchildren = $grandchild['grandchild'];
                    $cascadeActions = array_merge($cascadeActions, $grandchild['actions']);
                    $changes += $grandchild['changes'];
                }

                $after = self::applyChildTriggers('after', $child, $grandchildren, $childTriggers, $grandchildSpec);
                $grandchildren = $after['grandchild'];
                $triggerEffects = array_merge($triggerEffects, $after['effects']);
                $audit = array_merge($audit, $after['audit']);
                $changes += $after['changes'];
            }
            $children = array_values($keptChildren);
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
                foreach (($trigger['audit'] ?? []) as $column => $value) {
                    $row[$column] = self::triggerValue($value, $oldChild, count($grandchildren));
                }
                $audit[] = $row;
                $effects[] = ['timing' => $timing, 'action' => $action, 'rows' => 1, 'grandchild_count' => count($grandchildren)];
                $changes++;
                continue;
            }

            if ($grandchildSpec === null) {
                throw new \InvalidArgumentException('SQLite cascaded child trigger requires a grandchild foreign key');
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

            throw new \InvalidArgumentException('SQLite cascaded child trigger action is unsupported');
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
            $actions[] = ['action' => 'cascade-delete-grandchild', 'child_key' => $deletedChildKey, 'grandchild' => $grandchild];
            $changes++;
        }

        return ['grandchild' => array_values($kept), 'actions' => $actions, 'changes' => $changes];
    }

    /**
     * @param array{parent_key:string,child_key:string,on_delete?:string} $foreignKey
     * @return array{parent_key:string,child_key:string,on_delete:string}
     */
    private static function normalizeForeignKey(array $foreignKey, string $label): array
    {
        $action = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'no action'], true)) {
            throw new \InvalidArgumentException("SQLite {$label} cascaded trigger foreign key action is unsupported");
        }

        return [
            'parent_key' => self::identifier($foreignKey['parent_key'] ?? null, "{$label} parent key"),
            'child_key' => self::identifier($foreignKey['child_key'] ?? null, "{$label} child key"),
            'on_delete' => $action,
        ];
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

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite cascaded child trigger {$label} is malformed");
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
            throw new \InvalidArgumentException("SQLite cascaded child trigger {$label} is missing column {$column}");
        }

        return $row[$column];
    }
}
