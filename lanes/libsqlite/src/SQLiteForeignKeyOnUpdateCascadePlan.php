<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteForeignKeyOnUpdateCascadePlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $updates
     * @param array{parent_key:string,child_key:string,on_update?:string,deferred?:bool,child_default?:mixed} $foreignKey
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,deferred:list<array<string,mixed>>,commit_actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int}
     */
    public static function updateParents(array $parentRows, array $childRows, array $updates, array $foreignKey): array
    {
        $spec = self::normalizeForeignKey($foreignKey);
        $parents = array_values($parentRows);
        $children = array_values($childRows);
        $keyChanges = [];
        $deferred = [];
        $parentChanges = 0;

        foreach ($updates as $update) {
            $oldKey = self::rowValue($update, 'old', 'update row');
            $newKey = self::rowValue($update, 'new', 'update row');
            foreach ($parents as $index => $parent) {
                if (self::rowValue($parent, $spec['parent_key'], 'parent row') !== $oldKey) {
                    continue;
                }

                if ($oldKey === $newKey) {
                    break;
                }

                $parents[$index][$spec['parent_key']] = $newKey;
                $parentChanges++;
                $keyChanges[] = ['old' => $oldKey, 'new' => $newKey];
                $deferred[] = [
                    'operation' => 'update-parent',
                    'old_key' => $oldKey,
                    'new_key' => $newKey,
                    'action' => $spec['on_update'],
                    'deferred' => $spec['deferred'],
                ];
                break;
            }
        }

        if ($spec['on_update'] === 'restrict' && self::hasReferencingChild($children, $keyChanges, $spec['child_key'])) {
            throw new \InvalidArgumentException('SQLite foreign key RESTRICT prevents parent update before deferred commit');
        }

        $commit = self::commit($parents, $children, $keyChanges, $spec);

        return [
            'parent' => array_values($parents),
            'child' => $commit['child'],
            'deferred' => $deferred,
            'commit_actions' => $commit['actions'],
            'violations' => $commit['violations'],
            'changes' => $parentChanges + $commit['child_changes'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $updates
     * @param array{parent_key:string,child_key:string,on_update?:string,deferred?:bool,child_default?:mixed} $foreignKey
     * @return array{before:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>},after:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,deferred:list<array<string,mixed>>,commit_actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int},rollback:array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,deferred:list<array<string,mixed>>,commit_actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int}}
     */
    public static function updateParentsWithRollbackPreview(array $parentRows, array $childRows, array $updates, array $foreignKey): array
    {
        $before = ['parent' => array_values($parentRows), 'child' => array_values($childRows)];
        $after = self::updateParents($parentRows, $childRows, $updates, $foreignKey);

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
     * @param list<array{old:mixed,new:mixed}> $keyChanges
     * @param array{parent_key:string,child_key:string,on_update:string,deferred:bool,child_default:mixed} $spec
     * @return array{child:list<array<string,mixed>>,actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,child_changes:int}
     */
    private static function commit(array $parents, array $children, array $keyChanges, array $spec): array
    {
        $remainingKeys = self::keySet($parents, $spec['parent_key']);
        $actions = [];
        $violations = [];
        $childChanges = 0;

        foreach ($children as $index => $child) {
            $childKey = self::rowValue($child, $spec['child_key'], 'child row');
            if ($childKey === null) {
                continue;
            }

            foreach ($keyChanges as $change) {
                if ($childKey !== $change['old']) {
                    continue;
                }

                if ($spec['on_update'] === 'cascade') {
                    $children[$index][$spec['child_key']] = $change['new'];
                    $actions[] = ['action' => 'cascade-update-child', 'old_key' => $change['old'], 'new_key' => $change['new'], 'child' => $children[$index]];
                    $childChanges++;
                    break;
                }

                if ($spec['on_update'] === 'set null') {
                    $children[$index][$spec['child_key']] = null;
                    $actions[] = ['action' => 'set-null-child', 'old_key' => $change['old'], 'child' => $children[$index]];
                    $childChanges++;
                    break;
                }

                if ($spec['on_update'] === 'set default') {
                    $children[$index][$spec['child_key']] = $spec['child_default'];
                    $actions[] = ['action' => 'set-default-child', 'old_key' => $change['old'], 'default' => $spec['child_default'], 'child' => $children[$index]];
                    $childChanges++;
                    break;
                }

                if ($spec['on_update'] === 'no action') {
                    $violations[] = ['child_key' => $childKey, 'reason' => 'missing-parent-after-update', 'child' => $child];
                }
            }

            $updatedKey = $children[$index][$spec['child_key']];
            if ($updatedKey !== null && !array_key_exists((string) $updatedKey, $remainingKeys)) {
                $alreadyReported = false;
                foreach ($violations as $violation) {
                    if ($violation['child'] === $child) {
                        $alreadyReported = true;
                        break;
                    }
                }
                if (!$alreadyReported) {
                    $violations[] = ['child_key' => $updatedKey, 'reason' => 'missing-parent', 'child' => $children[$index]];
                }
            }
        }

        return [
            'child' => array_values($children),
            'actions' => $actions,
            'violations' => $violations,
            'child_changes' => $childChanges,
        ];
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update?:string,deferred?:bool,child_default?:mixed} $foreignKey
     * @return array{parent_key:string,child_key:string,on_update:string,deferred:bool,child_default:mixed}
     */
    private static function normalizeForeignKey(array $foreignKey): array
    {
        $parentKey = self::identifier($foreignKey['parent_key'] ?? null, 'parent key');
        $childKey = self::identifier($foreignKey['child_key'] ?? null, 'child key');
        $action = strtolower(trim((string) ($foreignKey['on_update'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'no action', 'restrict', 'set null', 'set default'], true)) {
            throw new \InvalidArgumentException('SQLite foreign key ON UPDATE action is unsupported');
        }

        return [
            'parent_key' => $parentKey,
            'child_key' => $childKey,
            'on_update' => $action,
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
     * @param list<array{old:mixed,new:mixed}> $keyChanges
     */
    private static function hasReferencingChild(array $children, array $keyChanges, string $childKey): bool
    {
        $oldKeys = [];
        foreach ($keyChanges as $change) {
            $oldKeys[(string) $change['old']] = true;
        }

        foreach ($children as $child) {
            $value = self::rowValue($child, $childKey, 'child row');
            if ($value !== null && array_key_exists((string) $value, $oldKeys)) {
                return true;
            }
        }

        return false;
    }
}
