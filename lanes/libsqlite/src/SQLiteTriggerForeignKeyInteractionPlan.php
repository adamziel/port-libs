<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerForeignKeyInteractionPlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $deleteKeys
     * @param array{parent_key:string,child_key:string,on_delete?:string,child_default?:mixed} $foreignKey
     * @param list<array{timing:string,event:string,action:string,child_key?:mixed,set_child_key?:mixed,audit?:array<string,mixed>}> $triggers
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,foreign_key_actions:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int}
     */
    public static function deleteParents(
        array $parentRows,
        array $childRows,
        array $deleteKeys,
        array $foreignKey,
        array $triggers,
    ): array {
        $spec = self::normalizeForeignKey($foreignKey);
        $parents = array_values($parentRows);
        $children = array_values($childRows);
        $effects = [];
        $foreignKeyActions = [];
        $audit = [];
        $changes = 0;

        foreach ($deleteKeys as $deleteKey) {
            $key = self::rowValue($deleteKey, $spec['parent_key'], 'delete key');
            $parentIndex = self::findParentIndex($parents, $spec['parent_key'], $key);
            if ($parentIndex === null) {
                continue;
            }

            $oldParent = $parents[$parentIndex];
            $before = self::applyTriggers('before', $oldParent, $children, $triggers, $spec);
            $children = $before['child'];
            $effects = array_merge($effects, $before['effects']);
            $audit = array_merge($audit, $before['audit']);
            $changes += $before['changes'];

            if ($spec['on_delete'] === 'restrict' && self::hasReferencingChild($children, $spec['child_key'], $key)) {
                throw new \InvalidArgumentException('SQLite trigger/FK interaction RESTRICT prevents parent delete');
            }

            unset($parents[$parentIndex]);
            $parents = array_values($parents);
            $changes++;

            $fk = self::applyForeignKeyAction($children, $key, $spec);
            $children = $fk['child'];
            $foreignKeyActions = array_merge($foreignKeyActions, $fk['actions']);
            $changes += $fk['changes'];

            $after = self::applyTriggers('after', $oldParent, $children, $triggers, $spec);
            $children = $after['child'];
            $effects = array_merge($effects, $after['effects']);
            $audit = array_merge($audit, $after['audit']);
            $changes += $after['changes'];
        }

        return [
            'parent' => array_values($parents),
            'child' => array_values($children),
            'trigger_effects' => $effects,
            'foreign_key_actions' => $foreignKeyActions,
            'audit' => $audit,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array{timing:string,event:string,action:string,child_key?:mixed,set_child_key?:mixed,audit?:array<string,mixed>}> $triggers
     * @param array{parent_key:string,child_key:string,on_delete:string,child_default:mixed} $spec
     * @return array{child:list<array<string,mixed>>,effects:list<array<string,mixed>>,audit:list<array<string,mixed>>,changes:int}
     */
    private static function applyTriggers(string $timing, array $oldParent, array $children, array $triggers, array $spec): array
    {
        $effects = [];
        $audit = [];
        $changes = 0;
        $parentKey = self::rowValue($oldParent, $spec['parent_key'], 'old parent');

        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== 'delete') {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? ''));
            if ($action === 'update-child-key') {
                $matchKey = array_key_exists('child_key', $trigger) ? self::triggerValue($trigger['child_key'], $oldParent, $spec) : $parentKey;
                $setKey = self::triggerValue($trigger['set_child_key'] ?? null, $oldParent, $spec);
                $updated = 0;
                foreach ($children as &$child) {
                    if (self::rowValue($child, $spec['child_key'], 'child row') !== $matchKey) {
                        continue;
                    }
                    $child[$spec['child_key']] = $setKey;
                    $updated++;
                }
                unset($child);
                $changes += $updated;
                $effects[] = ['timing' => $timing, 'action' => $action, 'matched_child_key' => $matchKey, 'set_child_key' => $setKey, 'rows' => $updated];
                continue;
            }

            if ($action === 'delete-child') {
                $matchKey = array_key_exists('child_key', $trigger) ? self::triggerValue($trigger['child_key'], $oldParent, $spec) : $parentKey;
                $kept = [];
                $deleted = 0;
                foreach ($children as $child) {
                    if (self::rowValue($child, $spec['child_key'], 'child row') === $matchKey) {
                        $deleted++;
                        continue;
                    }
                    $kept[] = $child;
                }
                $children = $kept;
                $changes += $deleted;
                $effects[] = ['timing' => $timing, 'action' => $action, 'matched_child_key' => $matchKey, 'rows' => $deleted];
                continue;
            }

            if ($action === 'insert-audit') {
                $row = [];
                foreach (($trigger['audit'] ?? []) as $column => $value) {
                    $row[$column] = self::triggerValue($value, $oldParent, $spec, count($children));
                }
                $audit[] = $row;
                $changes++;
                $effects[] = ['timing' => $timing, 'action' => $action, 'child_count' => count($children), 'rows' => 1];
                continue;
            }

            throw new \InvalidArgumentException('SQLite trigger/FK interaction trigger action is unsupported');
        }

        return ['child' => array_values($children), 'effects' => $effects, 'audit' => $audit, 'changes' => $changes];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_delete:string,child_default:mixed} $spec
     * @return array{child:list<array<string,mixed>>,actions:list<array<string,mixed>>,changes:int}
     */
    private static function applyForeignKeyAction(array $children, mixed $deletedKey, array $spec): array
    {
        $result = [];
        $actions = [];
        $changes = 0;

        foreach ($children as $child) {
            $childKey = self::rowValue($child, $spec['child_key'], 'child row');
            if ($childKey !== $deletedKey) {
                $result[] = $child;
                continue;
            }

            if ($spec['on_delete'] === 'cascade') {
                $actions[] = ['action' => 'cascade-delete-child', 'child_key' => $childKey, 'child' => $child];
                $changes++;
                continue;
            }

            if ($spec['on_delete'] === 'set null') {
                $child[$spec['child_key']] = null;
                $actions[] = ['action' => 'set-null-child', 'child_key' => $childKey, 'child' => $child];
                $changes++;
                $result[] = $child;
                continue;
            }

            if ($spec['on_delete'] === 'set default') {
                $child[$spec['child_key']] = $spec['child_default'];
                $actions[] = ['action' => 'set-default-child', 'child_key' => $childKey, 'default' => $spec['child_default'], 'child' => $child];
                $changes++;
                $result[] = $child;
                continue;
            }

            $result[] = $child;
        }

        return ['child' => array_values($result), 'actions' => $actions, 'changes' => $changes];
    }

    /**
     * @param array{parent_key:string,child_key:string,on_delete?:string,child_default?:mixed} $foreignKey
     * @return array{parent_key:string,child_key:string,on_delete:string,child_default:mixed}
     */
    private static function normalizeForeignKey(array $foreignKey): array
    {
        $parentKey = self::identifier($foreignKey['parent_key'] ?? null, 'parent key');
        $childKey = self::identifier($foreignKey['child_key'] ?? null, 'child key');
        $action = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'no action', 'restrict', 'set null', 'set default'], true)) {
            throw new \InvalidArgumentException('SQLite trigger/FK interaction ON DELETE action is unsupported');
        }

        return [
            'parent_key' => $parentKey,
            'child_key' => $childKey,
            'on_delete' => $action,
            'child_default' => $foreignKey['child_default'] ?? null,
        ];
    }

    private static function triggerValue(mixed $value, array $oldParent, array $spec, ?int $childCount = null): mixed
    {
        if ($value === 'old.parent_key') {
            return self::rowValue($oldParent, $spec['parent_key'], 'old parent');
        }
        if ($value === 'child_count') {
            return $childCount;
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return self::rowValue($oldParent, substr($value, 4), 'old parent');
        }

        return $value;
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger/FK interaction {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $parents
     */
    private static function findParentIndex(array $parents, string $column, mixed $key): ?int
    {
        foreach ($parents as $index => $parent) {
            if (self::rowValue($parent, $column, 'parent row') === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $children
     */
    private static function hasReferencingChild(array $children, string $column, mixed $key): bool
    {
        foreach ($children as $child) {
            if (self::rowValue($child, $column, 'child row') === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite trigger/FK interaction {$label} is missing column {$column}");
        }

        return $row[$column];
    }
}
