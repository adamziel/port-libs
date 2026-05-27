<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteForeignKeyDeferredCascadePlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $deleteKeys
     * @param array{parent_key:string,child_key:string,on_delete?:string,deferred?:bool,child_default?:mixed} $foreignKey
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,deferred:list<array<string,mixed>>,commit_actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int}
     */
    public static function deleteParents(array $parentRows, array $childRows, array $deleteKeys, array $foreignKey): array
    {
        $spec = self::normalizeForeignKey($foreignKey);
        $parents = array_values($parentRows);
        $children = array_values($childRows);
        $deletedParents = [];
        $deferred = [];

        foreach ($deleteKeys as $deleteKey) {
            $key = self::rowValue($deleteKey, $spec['parent_key'], 'delete key');
            foreach ($parents as $index => $parent) {
                if (self::rowValue($parent, $spec['parent_key'], 'parent row') !== $key) {
                    continue;
                }
                unset($parents[$index]);
                $deletedParents[] = $parent;
                $deferred[] = [
                    'operation' => 'delete-parent',
                    'parent_key' => $key,
                    'action' => $spec['on_delete'],
                    'deferred' => $spec['deferred'],
                ];
                break;
            }
        }

        $parents = array_values($parents);
        if ($spec['on_delete'] === 'restrict' && self::hasReferencingChild($children, $deletedParents, $spec['parent_key'], $spec['child_key'])) {
            throw new \InvalidArgumentException('SQLite foreign key RESTRICT prevents parent delete before deferred commit');
        }

        $commit = self::commit($parents, $children, $deletedParents, $spec);

        return [
            'parent' => $parents,
            'child' => $commit['child'],
            'deferred' => $deferred,
            'commit_actions' => $commit['actions'],
            'violations' => $commit['violations'],
            'changes' => count($deletedParents) + $commit['child_changes'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $deleteKeys
     * @param array{parent_key:string,child_key:string,on_delete?:string,deferred?:bool,child_default?:mixed} $foreignKey
     * @return array{before:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>},after:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,deferred:list<array<string,mixed>>,commit_actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int},rollback:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,deferred:list<array<string,mixed>>,commit_actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int}}
     */
    public static function deleteParentsWithRollbackPreview(array $parentRows, array $childRows, array $deleteKeys, array $foreignKey): array
    {
        $before = ['parent' => array_values($parentRows), 'child' => array_values($childRows)];
        $after = self::deleteParents($parentRows, $childRows, $deleteKeys, $foreignKey);

        return [
            'before' => $before,
            'after' => $after,
            'rollback' => [
                'parent' => $before['parent'],
                'child' => $before['child'],
                'deferred' => [],
                'commit_actions' => [[
                    'action' => 'rollback',
                    'restored_parent_rows' => count($before['parent']),
                    'restored_child_rows' => count($before['child']),
                ]],
                'violations' => [],
                'changes' => 0,
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $deletedParents
     * @param array{parent_key:string,child_key:string,on_delete:string,deferred:bool,child_default:mixed} $spec
     * @return array{child:list<array<string,mixed>>,actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,child_changes:int}
     */
    private static function commit(array $parents, array $children, array $deletedParents, array $spec): array
    {
        $deletedKeys = self::keySet($deletedParents, $spec['parent_key']);
        $remainingKeys = self::keySet($parents, $spec['parent_key']);
        $result = [];
        $actions = [];
        $violations = [];
        $childChanges = 0;

        foreach ($children as $child) {
            $childKey = self::rowValue($child, $spec['child_key'], 'child row');
            if ($childKey === null) {
                $result[] = $child;
                continue;
            }

            $referencesDeletedParent = array_key_exists((string) $childKey, $deletedKeys);
            if ($referencesDeletedParent && $spec['on_delete'] === 'cascade') {
                $actions[] = ['action' => 'cascade-delete-child', 'child_key' => $childKey, 'child' => $child];
                $childChanges++;
                continue;
            }

            if ($referencesDeletedParent && $spec['on_delete'] === 'set null') {
                $child[$spec['child_key']] = null;
                $actions[] = ['action' => 'set-null-child', 'child_key' => $childKey, 'child' => $child];
                $childChanges++;
                $result[] = $child;
                continue;
            }

            if ($referencesDeletedParent && $spec['on_delete'] === 'set default') {
                $child[$spec['child_key']] = $spec['child_default'];
                $actions[] = ['action' => 'set-default-child', 'child_key' => $childKey, 'default' => $spec['child_default'], 'child' => $child];
                $childChanges++;
                $result[] = $child;
                continue;
            }

            if (!array_key_exists((string) $childKey, $remainingKeys)) {
                $violations[] = ['child_key' => $childKey, 'reason' => 'missing-parent', 'child' => $child];
            }
            $result[] = $child;
        }

        return [
            'child' => array_values($result),
            'actions' => $actions,
            'violations' => $violations,
            'child_changes' => $childChanges,
        ];
    }

    /**
     * @param array{parent_key:string,child_key:string,on_delete?:string,deferred?:bool,child_default?:mixed} $foreignKey
     * @return array{parent_key:string,child_key:string,on_delete:string,deferred:bool,child_default:mixed}
     */
    private static function normalizeForeignKey(array $foreignKey): array
    {
        $parentKey = self::identifier($foreignKey['parent_key'] ?? null, 'parent key');
        $childKey = self::identifier($foreignKey['child_key'] ?? null, 'child key');
        $action = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'no action', 'restrict', 'set null', 'set default'], true)) {
            throw new \InvalidArgumentException('SQLite foreign key ON DELETE action is unsupported');
        }

        return [
            'parent_key' => $parentKey,
            'child_key' => $childKey,
            'on_delete' => $action,
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
            'child_default' => $foreignKey['child_default'] ?? null,
        ];
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite foreign key {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite foreign key {$label} is missing column {$column}");
        }

        return $row[$column];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,bool>
     */
    private static function keySet(array $rows, string $column): array
    {
        $set = [];
        foreach ($rows as $row) {
            $value = self::rowValue($row, $column, 'row');
            if ($value !== null) {
                $set[(string) $value] = true;
            }
        }

        return $set;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $deletedParents
     */
    private static function hasReferencingChild(array $children, array $deletedParents, string $parentKey, string $childKey): bool
    {
        $deletedKeys = self::keySet($deletedParents, $parentKey);
        foreach ($children as $child) {
            $value = self::rowValue($child, $childKey, 'child row');
            if ($value !== null && array_key_exists((string) $value, $deletedKeys)) {
                return true;
            }
        }

        return false;
    }
}
