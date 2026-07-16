<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteForeignKeyDeferredCascadePlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $deleteKeys
     * @param array{parent_key:string|list<string>,child_key:string|list<string>,on_delete?:string,deferred?:bool,child_default?:mixed} $foreignKey
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
            $key = self::rowValue($deleteKey, $spec['parent_columns'], 'delete key');
            foreach ($parents as $index => $parent) {
                if (self::rowValue($parent, $spec['parent_columns'], 'parent row') !== $key) {
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
        if ($spec['on_delete'] === 'restrict' && self::hasReferencingChild($children, $deletedParents, $spec['parent_columns'], $spec['child_columns'])) {
            throw new \InvalidArgumentException('SQLite foreign key RESTRICT prevents parent delete before deferred commit');
        }

        $commit = self::commit($parents, $children, $deletedParents, $spec);
        self::assertImmediateViolationsAllowed($commit['violations'], $spec['deferred'], 'delete');

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
     * @param list<array<string,mixed>> $updates
     * @param array{parent_key:string|list<string>,child_key:string|list<string>,on_update?:string,deferred?:bool,child_default?:mixed} $foreignKey
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,deferred:list<array<string,mixed>>,commit_actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,changes:int}
     */
    public static function updateParents(array $parentRows, array $childRows, array $updates, array $foreignKey): array
    {
        $spec = self::normalizeUpdateForeignKey($foreignKey);
        $parents = array_values($parentRows);
        $children = array_values($childRows);
        $updatedKeys = [];
        $deferred = [];
        $parentChanges = 0;

        foreach ($updates as $update) {
            $oldKey = self::rowValue($update, $spec['parent_columns'], 'update key');
            $newKey = count($spec['parent_columns']) === 1
                ? (array_key_exists('new_' . $spec['parent_key'], $update)
                    ? $update['new_' . $spec['parent_key']]
                    : self::rowValue($update, 'new', 'update key'))
                : self::newCompositeKey($update, $spec['parent_columns']);

            foreach ($parents as $index => $parent) {
                if (self::rowValue($parent, $spec['parent_columns'], 'parent row') !== $oldKey) {
                    continue;
                }

                foreach ($update as $column => $value) {
                    if (!in_array($column, $spec['parent_columns'], true) && $column !== 'new' && !str_starts_with($column, 'new_')) {
                        $parent[$column] = $value;
                    }
                }

                if ($oldKey !== $newKey) {
                    self::writeRowKey($parent, $spec['parent_columns'], $newKey);
                    $updatedKeys[] = ['old' => $oldKey, 'new' => $newKey];
                    $deferred[] = [
                        'operation' => 'update-parent',
                        'old_parent_key' => $oldKey,
                        'new_parent_key' => $newKey,
                        'action' => $spec['on_update'],
                        'deferred' => $spec['deferred'],
                    ];
                }

                $parents[$index] = $parent;
                $parentChanges++;
                break;
            }
        }

        if ($spec['on_update'] === 'restrict' && self::hasReferencingUpdatedChild($children, $updatedKeys, $spec['child_columns'])) {
            throw new \InvalidArgumentException('SQLite foreign key RESTRICT prevents parent update before deferred commit');
        }

        $commit = self::commitUpdate($parents, $children, $updatedKeys, $spec);
        self::assertImmediateViolationsAllowed($commit['violations'], $spec['deferred'], 'update');

        return [
            'parent' => $parents,
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
     * @param array{parent_key:string|list<string>,child_key:string|list<string>,on_update?:string,deferred?:bool,child_default?:mixed} $foreignKey
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
                    'action' => 'rollback-update',
                    'restored_parent_rows' => count($before['parent']),
                    'restored_child_rows' => count($before['child']),
                ]],
                'violations' => [],
                'changes' => 0,
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $deleteKeys
     * @param array{parent_key:string|list<string>,child_key:string|list<string>,on_delete?:string,deferred?:bool,child_default?:mixed} $foreignKey
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
     * @param array{parent_key:string,child_key:string,on_delete:string,deferred:bool,child_default:mixed,parent_columns:list<string>,child_columns:list<string>} $spec
     * @return array{child:list<array<string,mixed>>,actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,child_changes:int}
     */
    private static function commit(array $parents, array $children, array $deletedParents, array $spec): array
    {
        $deletedKeys = self::keySet($deletedParents, $spec['parent_columns']);
        $remainingKeys = self::keySet($parents, $spec['parent_columns']);
        $result = [];
        $actions = [];
        $violations = [];
        $childChanges = 0;

        foreach ($children as $child) {
            $childKey = self::rowValue($child, $spec['child_columns'], 'child row');
            if (self::isNullCompositeKey($childKey)) {
                $result[] = $child;
                continue;
            }

            $referencesDeletedParent = array_key_exists(self::keyIndex($childKey), $deletedKeys);
            if ($referencesDeletedParent && $spec['on_delete'] === 'cascade') {
                $actions[] = ['action' => 'cascade-delete-child', 'child_key' => $childKey, 'child' => $child];
                $childChanges++;
                continue;
            }

            if ($referencesDeletedParent && $spec['on_delete'] === 'set null') {
                self::writeRowKey($child, $spec['child_columns'], self::nullCompositeKey($spec['child_columns']));
                $actions[] = ['action' => 'set-null-child', 'child_key' => $childKey, 'child' => $child];
                $childChanges++;
                $result[] = $child;
                continue;
            }

            if ($referencesDeletedParent && $spec['on_delete'] === 'set default') {
                self::writeRowKey($child, $spec['child_columns'], self::defaultCompositeKey($spec['child_columns'], $spec['child_default']));
                $actions[] = ['action' => 'set-default-child', 'child_key' => $childKey, 'default' => $spec['child_default'], 'child' => $child];
                $childChanges++;
                $result[] = $child;
                continue;
            }

            if (!array_key_exists(self::keyIndex($childKey), $remainingKeys)) {
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
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param list<array{old:mixed,new:mixed}> $updatedKeys
     * @param array{parent_key:string,child_key:string,on_update:string,deferred:bool,child_default:mixed,parent_columns:list<string>,child_columns:list<string>} $spec
     * @return array{child:list<array<string,mixed>>,actions:list<array<string,mixed>>,violations:list<array<string,mixed>>,child_changes:int}
     */
    private static function commitUpdate(array $parents, array $children, array $updatedKeys, array $spec): array
    {
        $updated = [];
        foreach ($updatedKeys as $change) {
            $updated[self::keyIndex($change['old'])] = $change['new'];
        }

        $remainingKeys = self::keySet($parents, $spec['parent_columns']);
        $result = [];
        $actions = [];
        $violations = [];
        $childChanges = 0;

        foreach ($children as $child) {
            $childKey = self::rowValue($child, $spec['child_columns'], 'child row');
            if (self::isNullCompositeKey($childKey)) {
                $result[] = $child;
                continue;
            }

            $childKeyIndex = self::keyIndex($childKey);
            $referencesUpdatedParent = array_key_exists($childKeyIndex, $updated);
            if ($referencesUpdatedParent && $spec['on_update'] === 'cascade') {
                $old = $childKey;
                self::writeRowKey($child, $spec['child_columns'], $updated[$childKeyIndex]);
                $actions[] = ['action' => 'cascade-update-child', 'old_child_key' => $old, 'new_child_key' => $updated[$childKeyIndex], 'child' => $child];
                $childChanges++;
                $result[] = $child;
                continue;
            }

            if ($referencesUpdatedParent && $spec['on_update'] === 'set null') {
                self::writeRowKey($child, $spec['child_columns'], self::nullCompositeKey($spec['child_columns']));
                $actions[] = ['action' => 'set-null-child', 'child_key' => $childKey, 'child' => $child];
                $childChanges++;
                $result[] = $child;
                continue;
            }

            if ($referencesUpdatedParent && $spec['on_update'] === 'set default') {
                self::writeRowKey($child, $spec['child_columns'], self::defaultCompositeKey($spec['child_columns'], $spec['child_default']));
                $actions[] = ['action' => 'set-default-child', 'child_key' => $childKey, 'default' => $spec['child_default'], 'child' => $child];
                $childChanges++;
                $result[] = $child;
                continue;
            }

            if (!array_key_exists(self::keyIndex($childKey), $remainingKeys)) {
                $violations[] = ['child_key' => $childKey, 'reason' => 'missing-parent-after-update', 'child' => $child];
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
     * @param array{parent_key:string|list<string>,child_key:string|list<string>,on_delete?:string,deferred?:bool,child_default?:mixed} $foreignKey
     * @return array{parent_key:string,child_key:string,on_delete:string,deferred:bool,child_default:mixed,parent_columns:list<string>,child_columns:list<string>}
     */
    private static function normalizeForeignKey(array $foreignKey): array
    {
        $parentColumns = self::identifierList($foreignKey['parent_key'] ?? null, 'parent key');
        $childColumns = self::identifierList($foreignKey['child_key'] ?? null, 'child key');
        if (count($parentColumns) !== count($childColumns)) {
            throw new \InvalidArgumentException('SQLite foreign key parent and child key column counts differ');
        }
        $action = self::normalizeAction($foreignKey['on_delete'] ?? 'no action', 'ON DELETE');

        return [
            'parent_key' => $parentColumns[0],
            'child_key' => $childColumns[0],
            'on_delete' => $action,
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
            'child_default' => $foreignKey['child_default'] ?? null,
            'parent_columns' => $parentColumns,
            'child_columns' => $childColumns,
        ];
    }

    /**
     * @param array{parent_key:string|list<string>,child_key:string|list<string>,on_update?:string,deferred?:bool,child_default?:mixed} $foreignKey
     * @return array{parent_key:string,child_key:string,on_update:string,deferred:bool,child_default:mixed,parent_columns:list<string>,child_columns:list<string>}
     */
    private static function normalizeUpdateForeignKey(array $foreignKey): array
    {
        $parentColumns = self::identifierList($foreignKey['parent_key'] ?? null, 'parent key');
        $childColumns = self::identifierList($foreignKey['child_key'] ?? null, 'child key');
        if (count($parentColumns) !== count($childColumns)) {
            throw new \InvalidArgumentException('SQLite foreign key parent and child key column counts differ');
        }
        $action = self::normalizeAction($foreignKey['on_update'] ?? 'no action', 'ON UPDATE');

        return [
            'parent_key' => $parentColumns[0],
            'child_key' => $childColumns[0],
            'on_update' => $action,
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
            'child_default' => $foreignKey['child_default'] ?? null,
            'parent_columns' => $parentColumns,
            'child_columns' => $childColumns,
        ];
    }

    private static function normalizeAction(mixed $value, string $label): string
    {
        $action = strtolower(trim((string) $value));
        if (!in_array($action, ['cascade', 'no action', 'restrict', 'set null', 'set default'], true)) {
            throw new \InvalidArgumentException("SQLite foreign key {$label} action is unsupported");
        }

        return $action;
    }

    /**
     * @param list<array<string,mixed>> $violations
     */
    private static function assertImmediateViolationsAllowed(array $violations, bool $deferred, string $operation): void
    {
        if ($deferred || $violations === []) {
            return;
        }

        throw new \InvalidArgumentException("SQLite foreign key NO ACTION {$operation} constraint failed at statement boundary");
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite foreign key {$label} is malformed");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function identifierList(mixed $value, string $label): array
    {
        if (is_string($value)) {
            return [self::identifier($value, $label)];
        }

        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite foreign key {$label} is malformed");
        }

        $columns = [];
        foreach (array_values($value) as $index => $column) {
            $columns[] = self::identifier($column, "{$label} column {$index}");
        }

        if (count(array_unique($columns)) !== count($columns)) {
            throw new \InvalidArgumentException("SQLite foreign key {$label} has duplicate columns");
        }

        return $columns;
    }

    /**
     * @param array<string,mixed> $row
     * @param string|list<string> $column
     */
    private static function rowValue(array $row, string|array $column, string $label): mixed
    {
        if (is_string($column)) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite foreign key {$label} is missing column {$column}");
            }

            return $row[$column];
        }

        $values = [];
        foreach ($column as $part) {
            if (!array_key_exists($part, $row)) {
                throw new \InvalidArgumentException("SQLite foreign key {$label} is missing column {$part}");
            }
            $values[] = $row[$part];
        }

        if (count($values) === 1) {
            return $values[0];
        }

        return $values;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,bool>
     */
    private static function keySet(array $rows, string|array $column): array
    {
        $set = [];
        foreach ($rows as $row) {
            $value = self::rowValue($row, $column, 'row');
            if (!self::isNullCompositeKey($value)) {
                $set[self::keyIndex($value)] = true;
            }
        }

        return $set;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $deletedParents
     */
    private static function hasReferencingChild(array $children, array $deletedParents, string|array $parentKey, string|array $childKey): bool
    {
        $deletedKeys = self::keySet($deletedParents, $parentKey);
        foreach ($children as $child) {
            $value = self::rowValue($child, $childKey, 'child row');
            if (!self::isNullCompositeKey($value) && array_key_exists(self::keyIndex($value), $deletedKeys)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array{old:mixed,new:mixed}> $updatedKeys
     */
    private static function hasReferencingUpdatedChild(array $children, array $updatedKeys, string|array $childKey): bool
    {
        $updated = [];
        foreach ($updatedKeys as $change) {
            $updated[self::keyIndex($change['old'])] = true;
        }

        foreach ($children as $child) {
            $value = self::rowValue($child, $childKey, 'child row');
            if (!self::isNullCompositeKey($value) && array_key_exists(self::keyIndex($value), $updated)) {
                return true;
            }
        }

        return false;
    }

    private static function isNullCompositeKey(mixed $value): bool
    {
        if (!is_array($value)) {
            return $value === null;
        }

        foreach ($value as $part) {
            if ($part === null) {
                return true;
            }
        }

        return false;
    }

    private static function keyIndex(mixed $value): string
    {
        return is_array($value) ? json_encode(array_values($value), JSON_THROW_ON_ERROR) : (string) $value;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     */
    private static function writeRowKey(array &$row, array $columns, mixed $value): void
    {
        if (count($columns) === 1) {
            $row[$columns[0]] = is_array($value) ? $value[0] ?? null : $value;
            return;
        }

        if (!is_array($value) || count($value) !== count($columns)) {
            throw new \InvalidArgumentException('SQLite foreign key composite value column count is invalid');
        }

        foreach ($columns as $index => $column) {
            $row[$column] = $value[$index];
        }
    }

    /**
     * @param list<string> $columns
     * @return list<null>
     */
    private static function nullCompositeKey(array $columns): array
    {
        return array_fill(0, count($columns), null);
    }

    /**
     * @param list<string> $columns
     * @return mixed
     */
    private static function defaultCompositeKey(array $columns, mixed $default): mixed
    {
        if (count($columns) === 1 || is_array($default)) {
            return $default;
        }

        return array_fill(0, count($columns), $default);
    }

    /**
     * @param array<string,mixed> $update
     * @param list<string> $parentColumns
     * @return list<mixed>
     */
    private static function newCompositeKey(array $update, array $parentColumns): array
    {
        $key = [];
        foreach ($parentColumns as $column) {
            $newColumn = 'new_' . $column;
            if (!array_key_exists($newColumn, $update)) {
                throw new \InvalidArgumentException("SQLite foreign key update key is missing column {$newColumn}");
            }
            $key[] = $update[$newColumn];
        }

        return $key;
    }
}
