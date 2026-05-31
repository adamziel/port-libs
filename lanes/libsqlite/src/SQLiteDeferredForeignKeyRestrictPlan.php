<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteDeferredForeignKeyRestrictPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update?:string,on_delete?:string} $foreignKey
     * @param array{defer_foreign_keys?:bool,trigger?:array<string,mixed>} $options
     * @return array<string,mixed>
     */
    public static function updateParentKeys(array $parents, array $children, array $foreignKey, callable $update, array $options = []): array
    {
        $spec = self::foreignKeySpec($foreignKey);
        $defer = (bool) ($options['defer_foreign_keys'] ?? false);
        $original = array_values($parents);
        $attempted = $original;
        $actions = [];

        foreach ($attempted as $index => $row) {
            $oldKey = $row[$spec['parent_key']] ?? null;
            $newKey = $update($oldKey, $row, $index);
            if ($newKey === $oldKey) {
                continue;
            }

            $matchingChildren = self::childIndexesForKey($children, $spec['child_key'], $oldKey);
            $actions[] = [
                'event' => 'update',
                'parent_index' => $index,
                'old_key' => $oldKey,
                'new_key' => $newKey,
                'matching_child_indexes' => $matchingChildren,
                'restrict_deferred' => $defer && $spec['on_update'] === 'restrict',
            ];

            if (!$defer && $spec['on_update'] === 'restrict' && $matchingChildren !== []) {
                return self::failed('immediate-restrict-update', $original, $attempted, $children, $actions, []);
            }

            $attempted[$index][$spec['parent_key']] = $newKey;
        }

        $violations = self::violations($attempted, $children, $spec);
        if ($violations !== []) {
            return self::failed('deferred-foreign-key-commit', $original, $attempted, $children, $actions, $violations);
        }

        return [
            'status' => 'committed',
            'defer_foreign_keys' => $defer,
            'parents' => $attempted,
            'children' => array_values($children),
            'attempted_parents' => $attempted,
            'actions' => $actions,
            'trigger_effects' => [],
            'violations' => [],
            'changes' => count($actions),
            'rolled_back' => false,
            'dependencies' => self::dependencies(),
        ];
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update?:string,on_delete?:string} $foreignKey
     * @param array{defer_foreign_keys?:bool,trigger?:array<string,mixed>} $options
     * @return array<string,mixed>
     */
    public static function deleteParents(array $parents, array $children, array $foreignKey, callable $where, array $options = []): array
    {
        $spec = self::foreignKeySpec($foreignKey);
        $defer = (bool) ($options['defer_foreign_keys'] ?? false);
        $trigger = (array) ($options['trigger'] ?? []);
        $original = array_values($parents);
        $remaining = [];
        $actions = [];
        $triggerEffects = [];

        foreach ($original as $index => $row) {
            if (!$where($row, $index)) {
                $remaining[] = $row;
                continue;
            }

            $oldKey = $row[$spec['parent_key']] ?? null;
            $matchingChildren = self::childIndexesForKey($children, $spec['child_key'], $oldKey);
            $actions[] = [
                'event' => 'delete',
                'parent_index' => $index,
                'old_key' => $oldKey,
                'matching_child_indexes' => $matchingChildren,
                'restrict_deferred' => $defer && $spec['on_delete'] === 'restrict',
            ];

            if (!$defer && $spec['on_delete'] === 'restrict' && $matchingChildren !== []) {
                return self::failed('immediate-restrict-delete', $original, $original, $children, $actions, []);
            }

            if ($trigger !== []) {
                $insert = self::triggerInsertRow($trigger, $row);
                if ($insert !== null) {
                    $remaining[] = $insert;
                    $triggerEffects[] = [
                        'trigger' => self::identifier((string) ($trigger['name'] ?? 'after_delete_repair'), 'trigger name'),
                        'event' => 'after-delete',
                        'action' => 'insert-parent',
                        'old_key' => $oldKey,
                        'new_key' => $insert[$spec['parent_key']] ?? null,
                    ];
                }
            }
        }

        $violations = self::violations($remaining, $children, $spec);
        if ($violations !== []) {
            return self::failed('deferred-foreign-key-commit', $original, $remaining, $children, $actions, $violations, $triggerEffects);
        }

        return [
            'status' => 'committed',
            'defer_foreign_keys' => $defer,
            'parents' => array_values($remaining),
            'children' => array_values($children),
            'attempted_parents' => array_values($remaining),
            'actions' => $actions,
            'trigger_effects' => $triggerEffects,
            'violations' => [],
            'changes' => count($actions) + count($triggerEffects),
            'rolled_back' => false,
            'dependencies' => self::dependencies(),
        ];
    }

    /**
     * @param list<array<string,mixed>> $original
     * @param list<array<string,mixed>> $attempted
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $actions
     * @param list<array<string,mixed>> $violations
     * @param list<array<string,mixed>> $triggerEffects
     * @return array<string,mixed>
     */
    private static function failed(string $reason, array $original, array $attempted, array $children, array $actions, array $violations, array $triggerEffects = []): array
    {
        return [
            'status' => 'foreign-key-failed',
            'failure_reason' => $reason,
            'parents' => $original,
            'children' => array_values($children),
            'attempted_parents' => array_values($attempted),
            'actions' => $actions,
            'trigger_effects' => $triggerEffects,
            'violations' => $violations,
            'changes' => 0,
            'rolled_back' => true,
            'dependencies' => self::dependencies(),
        ];
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update?:string,on_delete?:string} $foreignKey
     * @return array{parent_key:string,child_key:string,on_update:string,on_delete:string}
     */
    private static function foreignKeySpec(array $foreignKey): array
    {
        return [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'foreign key parent column'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'foreign key child column'),
            'on_update' => self::action((string) ($foreignKey['on_update'] ?? 'no action')),
            'on_delete' => self::action((string) ($foreignKey['on_delete'] ?? 'no action')),
        ];
    }

    private static function action(string $action): string
    {
        $normalized = strtolower(trim($action));
        if (!in_array($normalized, ['restrict', 'no action'], true)) {
            throw new \InvalidArgumentException('SQLite deferred FK RESTRICT action is unsupported');
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @return list<int>
     */
    private static function childIndexesForKey(array $children, string $column, mixed $key): array
    {
        $indexes = [];
        foreach ($children as $index => $child) {
            if (($child[$column] ?? null) === $key) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update:string,on_delete:string} $spec
     * @return list<array<string,mixed>>
     */
    private static function violations(array $parents, array $children, array $spec): array
    {
        $parentKeys = [];
        foreach ($parents as $row) {
            $parentKeys[] = $row[$spec['parent_key']] ?? null;
        }

        $violations = [];
        foreach ($children as $index => $child) {
            $key = $child[$spec['child_key']] ?? null;
            if ($key !== null && !in_array($key, $parentKeys, true)) {
                $violations[] = ['child_index' => $index, 'child_key' => $key];
            }
        }

        return $violations;
    }

    /**
     * @param array<string,mixed> $trigger
     * @param array<string,mixed> $old
     * @return array<string,mixed>|null
     */
    private static function triggerInsertRow(array $trigger, array $old): ?array
    {
        if (($trigger['timing'] ?? null) !== 'after' || ($trigger['event'] ?? null) !== 'delete') {
            throw new \InvalidArgumentException('SQLite deferred FK RESTRICT trigger must be AFTER DELETE');
        }
        if (($trigger['action'] ?? null) !== 'insert-parent') {
            throw new \InvalidArgumentException('SQLite deferred FK RESTRICT trigger action is unsupported');
        }

        $row = [];
        foreach ((array) ($trigger['row'] ?? []) as $column => $value) {
            self::identifier((string) $column, 'trigger insert column');
            $row[(string) $column] = self::triggerValue($value, $old);
        }

        return $row === [] ? null : $row;
    }

    /**
     * @param array<string,mixed> $old
     */
    private static function triggerValue(mixed $value, array $old): mixed
    {
        if (is_string($value) && str_starts_with($value, 'old.')) {
            $column = substr($value, 4);
            if (!array_key_exists($column, $old)) {
                throw new \InvalidArgumentException("SQLite deferred FK RESTRICT OLD column {$column} is missing");
            }

            return $old[$column];
        }

        return $value;
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('SQLite deferred FK RESTRICT invalid ' . $label);
        }

        return $identifier;
    }

    /**
     * @return list<string>
     */
    private static function dependencies(): array
    {
        return [
            'sqlite-pragma-defer-foreign-keys',
            'sqlite-foreign-key-restrict-checks',
            'sqlite-after-delete-trigger-repair',
        ];
    }
}
