<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $grandchildren
     * @param array{parent_key:string,child_key:string,grandchild_key?:string,deferred?:bool,on_delete?:string} $foreignKey
     * @param array{where:callable(array<string,mixed>):bool,trigger?:array<string,mixed>,recursive_triggers?:bool,max_depth?:int,rollback_to_savepoint?:bool,savepoint?:string,current_source?:string,next_source?:string,rowid_column?:string,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,array<string,mixed>):mixed>} $statement
     * @return array<string,mixed>
     */
    public static function delete(array $parents, array $children, array $grandchildren, array $foreignKey, array $statement): array
    {
        $rowid = self::identifier((string) ($statement['rowid_column'] ?? 'option_id'), 'rowid column');
        $savepoint = self::identifier((string) ($statement['savepoint'] ?? 'wp_recursive_delete'), 'savepoint');
        $currentSource = self::source((string) ($statement['current_source'] ?? 'current'));
        $nextSource = self::source((string) ($statement['next_source'] ?? 'next'));
        $where = $statement['where'] ?? null;
        if (!is_callable($where)) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING recursive FK current-source WHERE callback is required');
        }

        $fk = [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key'),
            'grandchild_key' => self::identifier((string) ($foreignKey['grandchild_key'] ?? ($foreignKey['child_key'] ?? '')), 'grandchild key'),
            'deferred' => (bool) ($foreignKey['deferred'] ?? true),
            'on_delete' => strtolower((string) ($foreignKey['on_delete'] ?? 'cascade')),
        ];
        if ($fk['on_delete'] !== 'cascade') {
            throw new \InvalidArgumentException('SQLite trigger RETURNING recursive FK current-source only supports CASCADE');
        }

        $originalParents = array_values($parents);
        $originalChildren = array_values($children);
        $originalGrandchildren = array_values($grandchildren);
        $parents = array_values($parents);
        $children = array_values($children);
        $grandchildren = array_values($grandchildren);
        $returning = isset($statement['returning']) ? (array) $statement['returning'] : null;
        $recursive = (bool) ($statement['recursive_triggers'] ?? true);
        $maxDepth = self::nonNegativeInt($statement['max_depth'] ?? 1000, 'max depth');
        $trigger = (array) ($statement['trigger'] ?? []);
        $rollback = (bool) ($statement['rollback_to_savepoint'] ?? false);

        $queue = [];
        foreach ($parents as $index => $row) {
            if ($where($row)) {
                $queue[] = ['index' => $index, 'depth' => 0, 'source' => 'statement'];
            }
        }

        $deletedParents = [];
        $statementReturning = [];
        $triggerReturning = [];
        $attemptedReturning = [];
        $cascadeActions = [];
        $visited = [];
        $changes = 0;

        while ($queue !== []) {
            $work = array_shift($queue);
            $index = (int) $work['index'];
            $depth = (int) $work['depth'];
            $source = (string) $work['source'];
            if ($depth > $maxDepth) {
                throw new \InvalidArgumentException('SQLite trigger RETURNING recursive FK current-source max depth exceeded');
            }
            if (!isset($parents[$index])) {
                continue;
            }
            $old = $parents[$index];
            $key = $old[$fk['parent_key']] ?? null;
            $visitKey = $index . ':' . (string) $key;
            if (isset($visited[$visitKey])) {
                continue;
            }
            $visited[$visitKey] = true;
            unset($parents[$index]);
            $parents = array_values($parents);
            $deletedParents[] = $old;
            ++$changes;

            $entry = [
                'ordinal' => count($attemptedReturning),
                'source' => $currentSource,
                'event' => 'delete',
                'trigger_depth' => $depth,
                'trigger_source' => $source,
                'old_key' => $old[$rowid] ?? null,
                'returning' => self::returningRow($returning, $old, [
                    'source' => $currentSource,
                    'trigger_depth' => $depth,
                    'trigger_source' => $source,
                    'savepoint' => $savepoint,
                ]),
            ];
            $attemptedReturning[] = $entry;
            if ($source === 'statement') {
                $statementReturning[] = $entry;
            } else {
                $triggerReturning[] = $entry + ['trigger' => $source];
            }

            $cascade = self::cascade($children, $grandchildren, $old, $fk);
            $children = $cascade['children'];
            $grandchildren = $cascade['grandchildren'];
            $cascadeActions = array_merge($cascadeActions, $cascade['actions']);
            $changes += $cascade['changes'];

            if ($recursive && $trigger !== []) {
                foreach (self::recursiveTargets($parents, $old, $trigger, $fk['parent_key']) as $nextIndex) {
                    $queue[] = ['index' => $nextIndex, 'depth' => $depth + 1, 'source' => (string) ($trigger['name'] ?? 'recursive_delete')];
                }
            }
        }

        $violations = self::violations($parents, $children, $grandchildren, $fk);
        $rolledBack = $rollback || ($fk['deferred'] && $violations !== []);
        $nextParents = $rolledBack ? $originalParents : array_values($parents);
        $nextChildren = $rolledBack ? $originalChildren : array_values($children);
        $nextGrandchildren = $rolledBack ? $originalGrandchildren : array_values($grandchildren);

        return [
            'status' => $rolledBack ? 'rolled-back-to-savepoint-after-returning-yield' : 'current-yield-next-commit',
            'savepoint' => $savepoint,
            'current_source' => $currentSource,
            'next_source' => $rolledBack ? $currentSource : $nextSource,
            'returning_source' => $currentSource,
            'recursive_triggers' => $recursive,
            'current_parent' => array_values($parents),
            'next_parent' => $nextParents,
            'current_child' => array_values($children),
            'next_child' => $nextChildren,
            'current_grandchild' => array_values($grandchildren),
            'next_grandchild' => $nextGrandchildren,
            'deleted_parents' => $deletedParents,
            'cascade_actions' => array_values($cascadeActions),
            'current_returning_rows' => array_values(array_map(static fn (array $row): array => $row['returning'], $statementReturning)),
            'next_returning_rows' => $rolledBack ? [] : array_values(array_map(static fn (array $row): array => $row['returning'], $statementReturning)),
            'attempted_returning_rows' => $attemptedReturning,
            'trigger_returning_rows' => $triggerReturning,
            'foreign_key_violations' => $violations,
            'current_changes' => $changes,
            'next_changes' => $rolledBack ? 0 : $changes,
            'yield_boundary' => $rolledBack ? 'current-yield-next-rollback' : 'current-yield-next-commit',
            'yield_suppressed_by_savepoint' => $rolledBack && $statementReturning !== [],
            'deleted_parent_keys' => array_values(array_map(static fn (array $row): mixed => $row[$fk['parent_key']] ?? null, $deletedParents)),
            'current_parent_keys' => array_values(array_map(static fn (array $row): mixed => $row[$fk['parent_key']] ?? null, $parents)),
            'next_parent_keys' => array_values(array_map(static fn (array $row): mixed => $row[$fk['parent_key']] ?? null, $nextParents)),
            'current_child_keys' => array_values(array_map(static fn (array $row): mixed => $row[$fk['child_key']] ?? null, $children)),
            'next_child_keys' => array_values(array_map(static fn (array $row): mixed => $row[$fk['child_key']] ?? null, $nextChildren)),
            'current_grandchild_keys' => array_values(array_map(static fn (array $row): mixed => $row[$fk['grandchild_key']] ?? null, $grandchildren)),
            'next_grandchild_keys' => array_values(array_map(static fn (array $row): mixed => $row[$fk['grandchild_key']] ?? null, $nextGrandchildren)),
            'dependencies' => [
                'sqlite-trigger-returning-recursive-fk-current-source-next124',
                'sqlite-returning-yield-before-recursive-fk-cascade-rollback',
                'sqlite-recursive-delete-trigger-current-source-fk-cascade',
            ],
        ];
    }

    /**
     * @param array{parent_key:string,child_key:string,grandchild_key:string,deferred:bool,on_delete:string} $fk
     * @return array{children:list<array<string,mixed>>,grandchildren:list<array<string,mixed>>,actions:list<array<string,mixed>>,changes:int}
     */
    private static function cascade(array $children, array $grandchildren, array $parent, array $fk): array
    {
        $key = $parent[$fk['parent_key']] ?? null;
        $nextChildren = [];
        $removedChildKeys = [];
        $actions = [];
        $changes = 0;
        foreach ($children as $child) {
            if (($child[$fk['child_key']] ?? null) === $key) {
                $removedChildKeys[] = $child[$fk['child_key']];
                $actions[] = ['action' => 'cascade-delete-child', 'parent_key' => $key, 'child' => $child];
                ++$changes;
                continue;
            }
            $nextChildren[] = $child;
        }

        $nextGrandchildren = [];
        foreach ($grandchildren as $grandchild) {
            if (in_array($grandchild[$fk['grandchild_key']] ?? null, $removedChildKeys, true)) {
                $actions[] = ['action' => 'cascade-delete-grandchild', 'parent_key' => $key, 'grandchild' => $grandchild];
                ++$changes;
                continue;
            }
            $nextGrandchildren[] = $grandchild;
        }

        return ['children' => array_values($nextChildren), 'grandchildren' => array_values($nextGrandchildren), 'actions' => $actions, 'changes' => $changes];
    }

    /**
     * @return list<int>
     */
    private static function recursiveTargets(array $parents, array $old, array $trigger, string $parentKey): array
    {
        $column = self::identifier((string) ($trigger['match_column'] ?? $parentKey), 'trigger match column');
        $value = self::triggerValue($trigger['match_value'] ?? 'old.next_id', $old);
        $targets = [];
        foreach ($parents as $index => $row) {
            if (($row[$column] ?? null) === $value) {
                $targets[] = $index;
            }
        }

        return $targets;
    }

    private static function triggerValue(mixed $value, array $old): mixed
    {
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return $old[substr($value, 4)] ?? null;
        }

        return $value;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,array<string,mixed>):mixed>|null $projection
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function returningRow(?array $projection, array $old, array $context): array
    {
        if ($projection === null) {
            return $old;
        }
        $row = [];
        foreach ($projection as $index => $entry) {
            if (is_string($entry)) {
                $alias = str_contains($entry, '.') ? substr($entry, (int) strrpos($entry, '.') + 1) : $entry;
                $row[self::identifier($alias, 'RETURNING alias')] = self::value($entry, $old, $context);
                continue;
            }
            if (is_array($entry)) {
                $expr = (string) ($entry['expr'] ?? '');
                $alias = (string) ($entry['as'] ?? (str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr));
                $row[self::identifier($alias, 'RETURNING alias')] = self::value($expr, $old, $context);
                continue;
            }
            if (is_callable($entry)) {
                $row['expr' . $index] = $entry($old, $old, $context);
                continue;
            }
            throw new \InvalidArgumentException('SQLite trigger RETURNING recursive FK current-source projection is malformed');
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function value(string $expr, array $old, array $context): mixed
    {
        if (str_starts_with($expr, 'old.')) {
            return $old[substr($expr, 4)] ?? throw new \InvalidArgumentException('SQLite trigger RETURNING recursive FK current-source OLD column is missing');
        }
        if (str_starts_with($expr, 'context.')) {
            return $context[substr($expr, 8)] ?? throw new \InvalidArgumentException('SQLite trigger RETURNING recursive FK current-source context column is missing');
        }

        return $old[$expr] ?? throw new \InvalidArgumentException('SQLite trigger RETURNING recursive FK current-source column is missing');
    }

    /**
     * @param array{parent_key:string,child_key:string,grandchild_key:string,deferred:bool,on_delete:string} $fk
     * @return list<array<string,mixed>>
     */
    private static function violations(array $parents, array $children, array $grandchildren, array $fk): array
    {
        $parentKeys = array_column($parents, $fk['parent_key']);
        $childKeys = array_column($children, $fk['child_key']);
        $violations = [];
        foreach ($children as $index => $child) {
            $key = $child[$fk['child_key']] ?? null;
            if ($key !== null && !in_array($key, $parentKeys, true)) {
                $violations[] = ['phase' => 'deferred-commit', 'table' => 'child', 'row_index' => $index, 'key' => $key];
            }
        }
        foreach ($grandchildren as $index => $grandchild) {
            $key = $grandchild[$fk['grandchild_key']] ?? null;
            if ($key !== null && !in_array($key, $childKeys, true)) {
                $violations[] = ['phase' => 'deferred-commit', 'table' => 'grandchild', 'row_index' => $index, 'key' => $key];
            }
        }

        return $violations;
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger RETURNING recursive FK current-source {$label} is malformed");
        }

        return $identifier;
    }

    private static function source(string $source): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $source) !== 1) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING recursive FK current-source source token is malformed');
        }

        return $source;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite trigger RETURNING recursive FK current-source {$label} is malformed");
        }

        return $value;
    }
}
