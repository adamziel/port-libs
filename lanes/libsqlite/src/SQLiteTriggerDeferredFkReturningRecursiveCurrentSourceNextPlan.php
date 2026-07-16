<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $updates
     * @param array{parent_key:string,child_key:string,deferred?:bool,on_update?:string} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int,int):mixed> $returning
     * @param array{recursive_triggers?:bool,max_depth?:int,current_source?:string,next_source?:string} $options
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,foreign_key_actions:list<array<string,mixed>>,deferred_violations:list<array<string,mixed>>,commit_status:string,recursive_triggers:bool,current_source:string,next_source:string,dependencies:list<string>}
     */
    public static function updateParents(
        array $parents,
        array $children,
        array $updates,
        array $foreignKey,
        array $triggers = [],
        array $returning = ['*'],
        array $options = [],
    ): array {
        $spec = self::foreignKeySpec($foreignKey);
        $recursiveTriggers = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = self::positiveInt($options['max_depth'] ?? 32, 'max depth');
        $currentSource = (string) ($options['current_source'] ?? 'current');
        $nextSource = (string) ($options['next_source'] ?? 'next');
        $parents = array_values($parents);
        $children = array_values($children);
        $queue = self::initialQueue($updates);
        $returningRows = [];
        $yielded = [];
        $effects = [];
        $fkActions = [];
        $statement = 0;

        while ($queue !== []) {
            $item = array_shift($queue);
            $depth = (int) $item['depth'];
            if ($depth > $maxDepth) {
                throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING depth limit exceeded');
            }

            $index = self::parentIndex($parents, $item['match'], $spec['parent_key']);
            if ($index === null) {
                continue;
            }

            $old = $parents[$index];
            $new = self::applySet($old, $item['set']);
            $parents[$index] = $new;
            $returningRow = self::returningRow($returning, $old, $new, $statement, $depth);
            $returningRows[] = $returningRow;
            $yielded[] = [
                'statement' => $statement,
                'depth' => $depth,
                'event' => 'update',
                'old_key' => $old[$spec['parent_key']],
                'new_key' => $new[$spec['parent_key']],
                'returning' => $returningRow,
                'current_source' => $currentSource,
                'next_source' => $nextSource,
            ];

            $fk = self::cascadeChildren($children, $old, $new, $spec, $statement, $depth);
            $children = $fk['child'];
            $fkActions = array_merge($fkActions, $fk['actions']);

            $after = self::afterUpdateTriggers($triggers, $old, $new, $statement, $depth, $recursiveTriggers);
            foreach ($after['children'] as $child) {
                $children[] = $child;
            }
            $effects = array_merge($effects, $after['effects']);
            foreach ($after['updates'] as $update) {
                if ($recursiveTriggers) {
                    $queue[] = $update;
                }
            }

            ++$statement;
        }

        $violations = self::foreignKeyViolations($parents, $children, $spec);
        if (!$spec['deferred'] && $violations !== []) {
            throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING immediate constraint failed');
        }

        return [
            'parent' => array_values($parents),
            'child' => array_values($children),
            'returning_rows' => $returningRows,
            'yielded' => $yielded,
            'trigger_effects' => $effects,
            'foreign_key_actions' => $fkActions,
            'deferred_violations' => $violations,
            'commit_status' => $violations === [] ? 'ok' : 'deferred-constraint-failed',
            'recursive_triggers' => $recursiveTriggers,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'dependencies' => [
                'sqlite-trigger-deferred-fk-returning-recursive-current-source-next114',
                'sqlite-returning-yield-before-recursive-after-trigger-drain',
                'sqlite-deferred-foreign-key-check-at-commit',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param list<mixed> $deleteKeys
     * @param array{parent_key:string,child_key:string,deferred?:bool,on_delete?:string,default?:mixed} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param array{recursive_triggers?:bool,max_depth?:int,current_source?:string,next_source?:string} $options
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,deleted_parent_keys:list<mixed>,trigger_effects:list<array<string,mixed>>,foreign_key_actions:list<array<string,mixed>>,deferred_violations:list<array<string,mixed>>,commit_status:string,recursive_triggers:bool,current_source:string,next_source:string,dependencies:list<string>}
     */
    public static function deleteParents(
        array $parents,
        array $children,
        array $deleteKeys,
        array $foreignKey,
        array $triggers = [],
        array $options = [],
    ): array {
        $spec = self::foreignKeyDeleteSpec($foreignKey);
        $recursiveTriggers = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = self::positiveInt($options['max_depth'] ?? 32, 'max depth');
        $currentSource = (string) ($options['current_source'] ?? 'current');
        $nextSource = (string) ($options['next_source'] ?? 'next');
        $parents = array_values($parents);
        $children = array_values($children);
        $queue = [];
        foreach ($deleteKeys as $deleteKey) {
            $queue[] = ['key' => $deleteKey, 'depth' => 0, 'trigger' => null];
        }

        $deleted = [];
        $effects = [];
        $fkActions = [];
        $statement = 0;
        while ($queue !== []) {
            $item = array_shift($queue);
            $depth = (int) $item['depth'];
            if ($depth > $maxDepth) {
                throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING delete depth limit exceeded');
            }

            $index = self::parentIndex($parents, $item['key'], $spec['parent_key']);
            if ($index === null) {
                continue;
            }

            $old = $parents[$index];
            array_splice($parents, $index, 1);
            $oldKey = self::rowValue($old, $spec['parent_key'], 'OLD parent row');
            $deleted[] = $oldKey;

            $fk = self::applyDeleteAction($children, $oldKey, $spec, $statement, $depth);
            $children = $fk['child'];
            $fkActions = array_merge($fkActions, $fk['actions']);

            $after = self::afterDeleteTriggers($triggers, $old, $parents, $statement, $depth, $recursiveTriggers);
            foreach ($after['children'] as $child) {
                $children[] = $child;
            }
            $effects = array_merge($effects, $after['effects']);
            foreach ($after['deletes'] as $delete) {
                if ($recursiveTriggers) {
                    $queue[] = $delete;
                }
            }

            ++$statement;
        }

        $violations = self::foreignKeyViolations($parents, $children, [
            'parent_key' => $spec['parent_key'],
            'child_key' => $spec['child_key'],
            'on_update' => 'no action',
            'deferred' => $spec['deferred'],
        ]);
        if (!$spec['deferred'] && $violations !== []) {
            throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING immediate delete constraint failed');
        }

        return [
            'parent' => array_values($parents),
            'child' => array_values($children),
            'deleted_parent_keys' => $deleted,
            'trigger_effects' => $effects,
            'foreign_key_actions' => $fkActions,
            'deferred_violations' => $violations,
            'commit_status' => $violations === [] ? 'ok' : 'deferred-constraint-failed',
            'recursive_triggers' => $recursiveTriggers,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'dependencies' => [
                'sqlite-trigger-deferred-fk-returning-recursive-current-source-next114',
                'sqlite-fkey-delete-action-corpus',
                'sqlite-foreign-key-actions-ignore-recursive-trigger-pragma',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $updates
     * @return list<array{match:mixed,set:array<string,mixed>,depth:int,trigger:?string}>
     */
    private static function initialQueue(array $updates): array
    {
        $queue = [];
        foreach ($updates as $update) {
            if (!array_key_exists('match', $update) || !isset($update['set']) || !is_array($update['set'])) {
                throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING update is malformed');
            }
            $queue[] = [
                'match' => $update['match'],
                'set' => $update['set'],
                'depth' => 0,
                'trigger' => null,
            ];
        }

        return $queue;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $set
     * @return array<string,mixed>
     */
    private static function applySet(array $row, array $set): array
    {
        foreach ($set as $column => $value) {
            $row[self::identifier((string) $column, 'set column')] = $value;
        }

        return $row;
    }

    /**
     * @param list<array<string,mixed>> $parents
     */
    private static function parentIndex(array $parents, mixed $match, string $parentKey): ?int
    {
        foreach ($parents as $index => $row) {
            if (!array_key_exists($parentKey, $row)) {
                throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING parent key is missing');
            }
            if ($row[$parentKey] == $match) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update:string,deferred:bool} $spec
     * @return array{child:list<array<string,mixed>>,actions:list<array<string,mixed>>}
     */
    private static function cascadeChildren(array $children, array $old, array $new, array $spec, int $statement, int $depth): array
    {
        $oldKey = self::rowValue($old, $spec['parent_key'], 'OLD parent row');
        $newKey = self::rowValue($new, $spec['parent_key'], 'NEW parent row');
        if ($oldKey === $newKey) {
            return ['child' => array_values($children), 'actions' => []];
        }

        $actions = [];
        foreach ($children as $index => &$child) {
            $childKey = self::rowValue($child, $spec['child_key'], 'child row');
            if ($childKey != $oldKey) {
                continue;
            }
            if ($spec['on_update'] !== 'cascade') {
                $actions[] = ['statement' => $statement, 'depth' => $depth, 'child_index' => $index, 'action' => $spec['on_update'], 'from' => $oldKey, 'to' => $newKey];
                continue;
            }
            $child[$spec['child_key']] = $newKey;
            $actions[] = ['statement' => $statement, 'depth' => $depth, 'child_index' => $index, 'action' => 'cascade', 'from' => $oldKey, 'to' => $newKey];
        }
        unset($child);

        return ['child' => array_values($children), 'actions' => $actions];
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @return array{children:list<array<string,mixed>>,updates:list<array{match:mixed,set:array<string,mixed>,depth:int,trigger:string}>,effects:list<array<string,mixed>>}
     */
    private static function afterUpdateTriggers(array $triggers, array $old, array $new, int $statement, int $depth, bool $recursiveTriggers): array
    {
        $children = [];
        $updates = [];
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? 'after')) !== 'after' || strtolower((string) ($trigger['event'] ?? 'update')) !== 'update') {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, $new)) {
                continue;
            }
            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'insert-child') {
                $children[] = self::project((array) ($trigger['row'] ?? []), $old, $new);
            } elseif ($action === 'enqueue-update') {
                if (!isset($trigger['set']) || !is_array($trigger['set'])) {
                    throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING recursive set is malformed');
                }
                $updates[] = [
                    'match' => self::value($trigger['match'] ?? 'new.parent_id', $old, $new),
                    'set' => self::project($trigger['set'], $old, $new),
                    'depth' => $depth + 1,
                    'trigger' => (string) ($trigger['name'] ?? ''),
                ];
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING trigger action is unsupported');
            }

            $effects[] = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'action' => $action,
                'statement' => $statement,
                'depth' => $depth,
                'recursive_triggers' => $recursiveTriggers,
                'row' => self::project((array) ($trigger['values'] ?? []), $old, $new),
            ];
        }

        return ['children' => $children, 'updates' => $updates, 'effects' => $effects];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_delete:string,deferred:bool,default:mixed} $spec
     * @return array{child:list<array<string,mixed>>,actions:list<array<string,mixed>>}
     */
    private static function applyDeleteAction(array $children, mixed $oldKey, array $spec, int $statement, int $depth): array
    {
        $actions = [];
        foreach ($children as $index => &$child) {
            $childKey = self::rowValue($child, $spec['child_key'], 'child row');
            if ($childKey != $oldKey) {
                continue;
            }

            $actions[] = ['statement' => $statement, 'depth' => $depth, 'child_index' => $index, 'action' => $spec['on_delete'], 'from' => $oldKey];
            if ($spec['on_delete'] === 'cascade') {
                unset($children[$index]);
            } elseif ($spec['on_delete'] === 'set null') {
                $child[$spec['child_key']] = null;
            } elseif ($spec['on_delete'] === 'set default') {
                $child[$spec['child_key']] = $spec['default'];
            }
        }
        unset($child);

        return ['child' => array_values($children), 'actions' => $actions];
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @return array{children:list<array<string,mixed>>,deletes:list<array{key:mixed,depth:int,trigger:string}>,effects:list<array<string,mixed>>}
     */
    private static function afterDeleteTriggers(array $triggers, array $old, array $parents, int $statement, int $depth, bool $recursiveTriggers): array
    {
        $children = [];
        $deletes = [];
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? 'after')) !== 'after' || strtolower((string) ($trigger['event'] ?? 'delete')) !== 'delete') {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, [])) {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'insert-child') {
                $children[] = self::project((array) ($trigger['row'] ?? []), $old, []);
            } elseif ($action === 'enqueue-delete') {
                $deletes[] = [
                    'key' => self::value($trigger['match'] ?? 'old.parent_id', $old, []),
                    'depth' => $depth + 1,
                    'trigger' => (string) ($trigger['name'] ?? ''),
                ];
            } elseif ($action === 'enqueue-delete-children') {
                $childParentKey = self::identifier((string) ($trigger['child_parent_key'] ?? 'parent_id'), 'trigger child parent key');
                $childKey = self::identifier((string) ($trigger['child_key'] ?? 'record_id'), 'trigger child key');
                $oldKey = self::rowValue($old, $childKey, 'OLD row');
                foreach ($parents as $parent) {
                    if (self::rowValue($parent, $childParentKey, 'trigger child row') == $oldKey) {
                        $deletes[] = [
                            'key' => self::rowValue($parent, $childKey, 'trigger child row'),
                            'depth' => $depth + 1,
                            'trigger' => (string) ($trigger['name'] ?? ''),
                        ];
                    }
                }
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING delete trigger action is unsupported');
            }

            $effects[] = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'action' => $action,
                'statement' => $statement,
                'depth' => $depth,
                'recursive_triggers' => $recursiveTriggers,
                'row' => self::project((array) ($trigger['values'] ?? []), $old, []),
            ];
        }

        return ['children' => $children, 'deletes' => $deletes, 'effects' => $effects];
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,int,int):mixed> $projection
     * @return array<string,mixed>
     */
    private static function returningRow(array $projection, array $old, array $new, int $statement, int $depth): array
    {
        if ($projection === []) {
            throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING projection is empty');
        }
        $row = [];
        foreach ($projection as $index => $term) {
            if ($term === '*') {
                $row['*'] = $new;
                continue;
            }
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $statement, $depth);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = (string) ($term['as'] ?? (str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr));
                $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($expr, $old, $new);
                continue;
            }
            if (!is_string($term) || $term === '') {
                throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING projection term is malformed');
            }
            $alias = str_contains($term, '.') ? substr($term, (int) strrpos($term, '.') + 1) : $term;
            $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($term, $old, $new);
        }

        return $row;
    }

    private static function returningValue(string $expr, array $old, array $new): mixed
    {
        $expr = trim($expr);
        if (str_starts_with($expr, 'old.')) {
            return self::rowValue($old, substr($expr, 4), 'OLD row');
        }
        if (str_starts_with($expr, 'new.')) {
            return self::rowValue($new, substr($expr, 4), 'NEW row');
        }

        return self::rowValue($new, $expr, 'RETURNING row');
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function project(array $template, array $old, array $new): array
    {
        $row = [];
        foreach ($template as $column => $value) {
            $row[self::identifier((string) $column, 'projection column')] = self::value($value, $old, $new);
        }

        return $row;
    }

    private static function value(mixed $value, array $old, array $new): mixed
    {
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return self::rowValue($old, substr($value, 4), 'OLD row');
        }
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return self::rowValue($new, substr($value, 4), 'NEW row');
        }

        return $value;
    }

    private static function whenMatches(mixed $when, array $old, array $new): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new);
        $right = self::value($right, $old, $new);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING WHEN operator is unsupported'),
        };
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update:string,deferred:bool} $spec
     * @return list<array<string,mixed>>
     */
    private static function foreignKeyViolations(array $parents, array $children, array $spec): array
    {
        $keys = [];
        foreach ($parents as $parent) {
            $keys[] = self::rowValue($parent, $spec['parent_key'], 'parent row');
        }
        $violations = [];
        foreach ($children as $index => $child) {
            $key = self::rowValue($child, $spec['child_key'], 'child row');
            if ($key === null || in_array($key, $keys, true)) {
                continue;
            }
            $violations[] = [
                'child_index' => $index,
                'child_key' => $key,
                'phase' => 'commit',
            ];
        }

        return $violations;
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred?:bool,on_update?:string} $foreignKey
     * @return array{parent_key:string,child_key:string,on_update:string,deferred:bool}
     */
    private static function foreignKeySpec(array $foreignKey): array
    {
        return [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key'),
            'on_update' => self::onUpdate((string) ($foreignKey['on_update'] ?? 'cascade')),
            'deferred' => (bool) ($foreignKey['deferred'] ?? true),
        ];
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred?:bool,on_delete?:string,default?:mixed} $foreignKey
     * @return array{parent_key:string,child_key:string,on_delete:string,deferred:bool,default:mixed}
     */
    private static function foreignKeyDeleteSpec(array $foreignKey): array
    {
        return [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key'),
            'on_delete' => self::onDelete((string) ($foreignKey['on_delete'] ?? 'cascade')),
            'deferred' => (bool) ($foreignKey['deferred'] ?? true),
            'default' => $foreignKey['default'] ?? null,
        ];
    }

    private static function onUpdate(string $action): string
    {
        return match (strtolower(trim($action))) {
            'cascade' => 'cascade',
            'no action', 'no-action' => 'no action',
            'restrict' => 'restrict',
            default => throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING FK action is unsupported'),
        };
    }

    private static function onDelete(string $action): string
    {
        return match (strtolower(trim($action))) {
            'cascade' => 'cascade',
            'set null', 'set-null' => 'set null',
            'set default', 'set-default' => 'set default',
            'no action', 'no-action' => 'no action',
            'restrict' => 'restrict',
            default => throw new \InvalidArgumentException('SQLite recursive trigger deferred FK RETURNING delete FK action is unsupported'),
        };
    }

    private static function rowValue(array $row, string $column, string $label): mixed
    {
        self::identifier($column, $label . ' column');
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite recursive trigger deferred FK RETURNING {$label} {$column} is missing");
        }

        return $row[$column];
    }

    private static function identifier(string $identifier, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("SQLite recursive trigger deferred FK RETURNING {$label} is malformed");
        }

        return $identifier;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException("SQLite recursive trigger deferred FK RETURNING {$label} is malformed");
        }

        return $value;
    }
}
