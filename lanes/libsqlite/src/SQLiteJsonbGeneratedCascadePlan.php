<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonbGeneratedCascadePlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $updates
     * @param list<mixed> $deletes
     * @param array{parent_column:string,source_column:string,json_path:string,child_column:string,rowid_column?:string,on_update?:string,on_delete?:string,default?:mixed} $foreignKey
     * @return array<string,mixed>
     */
    public static function plan(
        array $parentRows,
        array $childRows,
        array $updates,
        array $deletes,
        array $foreignKey,
    ): array {
        $spec = self::normalizeSpec($foreignKey);
        $parents = self::hydrateParents($parentRows, $spec);
        $children = array_values($childRows);
        $beforeParents = $parents;
        $beforeChildren = $children;
        $actions = [];
        $changes = 0;

        foreach ($updates as $update) {
            $oldKey = self::rowValue($update, $spec['parent_column'], 'update old key');
            $newKey = array_key_exists('new_' . $spec['parent_column'], $update)
                ? $update['new_' . $spec['parent_column']]
                : self::rowValue($update, 'new_parent_key', 'update new key');
            $index = self::findParent($parents, $spec['parent_column'], $oldKey);
            if ($index === null) {
                continue;
            }

            $oldParent = $parents[$index];
            $mutated = SQLiteJsonMutation::mutateSqlFunction(
                'jsonb_set',
                self::jsonSource($oldParent, $spec),
                $spec['json_path'],
                $newKey,
            );
            if (!$mutated instanceof SQLiteBlobValue) {
                throw new \RuntimeException('SQLite JSONB generated cascade expected JSONB mutation output');
            }

            $parents[$index][$spec['source_column']] = $mutated;
            $parents[$index][$spec['parent_column']] = $newKey;
            $actions[] = [
                'action' => 'update-parent-generated-jsonb',
                'old_key' => $oldKey,
                'new_key' => $newKey,
                'rowid' => self::parentRowid($oldParent, $index, $spec),
                'json_path' => $spec['json_path'],
            ];
            $changes++;

            if ($oldKey !== $newKey && $spec['on_update'] === 'cascade') {
                foreach ($children as &$child) {
                    if (self::rowValue($child, $spec['child_column'], 'child row') !== $oldKey) {
                        continue;
                    }
                    $oldChild = $child;
                    $child[$spec['child_column']] = $newKey;
                    $actions[] = [
                        'action' => 'cascade-update-child-generated-key',
                        'old_key' => $oldKey,
                        'new_key' => $newKey,
                        'old_child' => $oldChild,
                        'new_child' => $child,
                    ];
                    $changes++;
                }
                unset($child);
            }
        }

        foreach ($deletes as $deleteKey) {
            $index = self::findParent($parents, $spec['parent_column'], $deleteKey);
            if ($index === null) {
                continue;
            }

            $deletedParent = $parents[$index];
            array_splice($parents, $index, 1);
            $actions[] = [
                'action' => 'delete-parent-generated-jsonb',
                'old_key' => $deleteKey,
                'rowid' => self::parentRowid($deletedParent, $index, $spec),
            ];
            $changes++;

            if ($spec['on_delete'] === 'cascade') {
                $kept = [];
                foreach ($children as $child) {
                    if (self::rowValue($child, $spec['child_column'], 'child row') === $deleteKey) {
                        $actions[] = [
                            'action' => 'cascade-delete-child-generated-key',
                            'old_key' => $deleteKey,
                            'old_child' => $child,
                        ];
                        $changes++;
                        continue;
                    }
                    $kept[] = $child;
                }
                $children = $kept;
                continue;
            }

            if ($spec['on_delete'] === 'set null' || $spec['on_delete'] === 'set default') {
                $replacement = $spec['on_delete'] === 'set null' ? null : $spec['default'];
                foreach ($children as &$child) {
                    if (self::rowValue($child, $spec['child_column'], 'child row') !== $deleteKey) {
                        continue;
                    }
                    $oldChild = $child;
                    $child[$spec['child_column']] = $replacement;
                    $actions[] = [
                        'action' => $spec['on_delete'] === 'set null' ? 'cascade-null-child-generated-key' : 'cascade-default-child-generated-key',
                        'old_key' => $deleteKey,
                        'new_key' => $replacement,
                        'old_child' => $oldChild,
                        'new_child' => $child,
                    ];
                    $changes++;
                }
                unset($child);
            }
        }

        return [
            'before_parent' => $beforeParents,
            'after_parent' => array_values($parents),
            'before_child' => $beforeChildren,
            'after_child' => array_values($children),
            'actions' => $actions,
            'action_count' => count($actions),
            'changes' => $changes,
            'violations' => self::violations($parents, $children, $spec),
            'foreign_key' => $spec,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $spec
     * @return list<array<string,mixed>>
     */
    private static function hydrateParents(array $rows, array $spec): array
    {
        $hydrated = [];
        foreach ($rows as $row) {
            $row[$spec['parent_column']] = SQLiteJsonExtract::extractSqlFunction(
                'jsonb_extract',
                self::jsonSource($row, $spec),
                $spec['json_path'],
            );
            $hydrated[] = $row;
        }

        return $hydrated;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $spec
     */
    private static function jsonSource(array $row, array $spec): string|SQLiteBlobValue|null
    {
        $value = self::rowValue($row, $spec['source_column'], 'parent row');
        if ($value !== null && !$value instanceof SQLiteBlobValue && !is_string($value)) {
            throw new \InvalidArgumentException('SQLite JSONB generated cascade source column must be JSONB, text JSON, or NULL');
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $parents
     */
    private static function findParent(array $parents, string $column, mixed $key): ?int
    {
        foreach ($parents as $index => $parent) {
            if (self::rowValue($parent, $column, 'parent row') === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array<string,mixed> $spec
     * @return list<array<string,mixed>>
     */
    private static function violations(array $parents, array $children, array $spec): array
    {
        $keys = [];
        foreach ($parents as $parent) {
            $key = self::rowValue($parent, $spec['parent_column'], 'parent row');
            if ($key !== null) {
                $keys[(string) $key] = true;
            }
        }

        $violations = [];
        foreach ($children as $child) {
            $key = self::rowValue($child, $spec['child_column'], 'child row');
            if ($key === null || isset($keys[(string) $key])) {
                continue;
            }
            $violations[] = ['child' => $child, 'missing_parent_key' => $key];
        }

        return $violations;
    }

    /**
     * @param array{parent_column:string,source_column:string,json_path:string,child_column:string,rowid_column?:string,on_update?:string,on_delete?:string,default?:mixed} $foreignKey
     * @return array{parent_column:string,source_column:string,json_path:string,child_column:string,rowid_column:string|null,on_update:string,on_delete:string,default:mixed}
     */
    private static function normalizeSpec(array $foreignKey): array
    {
        $path = $foreignKey['json_path'] ?? null;
        if (!is_string($path) || !SQLiteJsonPath::isWellFormed($path)) {
            throw new \InvalidArgumentException('SQLite JSONB generated cascade path is malformed');
        }

        $onUpdate = strtolower(trim((string) ($foreignKey['on_update'] ?? 'no action')));
        $onDelete = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'no action')));
        if (!in_array($onUpdate, ['cascade', 'no action'], true)) {
            throw new \InvalidArgumentException('SQLite JSONB generated cascade ON UPDATE action is unsupported');
        }
        if (!in_array($onDelete, ['cascade', 'set null', 'set default', 'no action'], true)) {
            throw new \InvalidArgumentException('SQLite JSONB generated cascade ON DELETE action is unsupported');
        }

        return [
            'parent_column' => self::identifier($foreignKey['parent_column'] ?? null, 'parent generated column'),
            'source_column' => self::identifier($foreignKey['source_column'] ?? null, 'parent JSONB source column'),
            'json_path' => $path,
            'child_column' => self::identifier($foreignKey['child_column'] ?? null, 'child key column'),
            'rowid_column' => array_key_exists('rowid_column', $foreignKey)
                ? self::identifier($foreignKey['rowid_column'], 'parent rowid column')
                : null,
            'on_update' => $onUpdate,
            'on_delete' => $onDelete,
            'default' => $foreignKey['default'] ?? null,
        ];
    }

    private static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite JSONB generated cascade {$label} is malformed");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite JSONB generated cascade {$label} is missing column {$column}");
        }

        return $row[$column];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $spec
     */
    private static function parentRowid(array $row, int $fallbackIndex, array $spec): int|string
    {
        $column = $spec['rowid_column'] ?? null;
        if (is_string($column)) {
            $value = self::rowValue($row, $column, 'parent rowid');

            if (is_int($value) || is_string($value)) {
                return $value;
            }

            throw new \InvalidArgumentException("SQLite JSONB generated cascade parent rowid column {$column} must be integer or text");
        }

        foreach (['rowid', 'setting_id', 'id'] as $candidate) {
            $value = $row[$candidate] ?? null;
            if (is_int($value) || is_string($value)) {
                return $value;
            }
        }

        return $fallbackIndex;
    }
}
