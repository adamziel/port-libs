<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerForeignKeyReturningPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     * @param callable(array<string,mixed>):bool $where
     * @param array{parent_key:string,child_key:string,on_update?:string,on_delete?:string,deferred?:bool,child_default?:mixed,child_defaults?:array<string,mixed>} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,string):mixed>|null $returning
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,foreign_key_actions:list<array<string,mixed>>,foreign_key_violations:list<array<string,mixed>>,changes:int}
     */
    public static function updateParents(
        array $parents,
        array $children,
        array $assignments,
        callable $where,
        array $foreignKey,
        array $triggers = [],
        ?array $returning = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        self::identifier($rowIdColumn, 'rowid column');
        self::validateAssignments($assignments);
        $parents = array_values($parents);
        $children = array_values($children);
        $spec = self::foreignKeySpec($foreignKey);
        $restrictImmediate = $spec['on_update'] === 'restrict';
        $yielded = [];
        $effects = [];
        $fkActions = [];
        $violations = [];
        $changes = 0;

        foreach ($parents as $index => $old) {
            if (!$where($old)) {
                continue;
            }

            $new = self::updatedRow($old, $assignments);
            $before = self::fireTriggers('before', 'update', $old, $new, $children, $triggers, $spec);
            $new = $before['row'];
            $children = $before['child'];
            $effects = array_merge($effects, $before['effects']);

            $parents[$index] = $new;
            ++$changes;
            $returningImage = $new;

            $fk = self::applyForeignKeyAction('update', $old, $new, $children, $spec);
            $children = $fk['child'];
            $fkActions = array_merge($fkActions, $fk['actions']);
            $statementViolations = self::foreignKeyViolations($parents, $children, $spec);
            if ($restrictImmediate && $statementViolations !== []) {
                throw new \InvalidArgumentException('SQLite trigger/FK RETURNING immediate constraint failed');
            }
            $violations = array_merge($violations, self::tagViolations($statementViolations, $changes - 1, 'statement'));

            $after = self::fireTriggers('after', 'update', $old, $new, $children, $triggers, $spec);
            $parents[$index] = $after['row'];
            $children = $after['child'];
            $effects = array_merge($effects, $after['effects']);
            $afterViolations = self::foreignKeyViolations($parents, $children, $spec);
            if ((!$spec['deferred'] || $restrictImmediate) && $afterViolations !== []) {
                throw new \InvalidArgumentException('SQLite trigger/FK RETURNING immediate constraint failed after trigger');
            }
            $violations = array_merge($violations, self::tagViolations($afterViolations, $changes - 1, 'after-trigger'));

            $yielded[] = self::yieldRow($changes - 1, 'update', $old, $returningImage, $returning, $rowIdColumn, $statementViolations, $afterViolations);
        }

        return self::result($parents, $children, $yielded, $effects, $fkActions, $violations, $changes);
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param callable(array<string,mixed>):bool $where
     * @param array{parent_key:string,child_key:string,on_update?:string,on_delete?:string,deferred?:bool,child_default?:mixed,child_defaults?:array<string,mixed>} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,string):mixed>|null $returning
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,foreign_key_actions:list<array<string,mixed>>,foreign_key_violations:list<array<string,mixed>>,changes:int}
     */
    public static function deleteParents(
        array $parents,
        array $children,
        callable $where,
        array $foreignKey,
        array $triggers = [],
        ?array $returning = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        self::identifier($rowIdColumn, 'rowid column');
        $parents = array_values($parents);
        $children = array_values($children);
        $spec = self::foreignKeySpec($foreignKey);
        $restrictImmediate = $spec['on_delete'] === 'restrict';
        $yielded = [];
        $effects = [];
        $fkActions = [];
        $violations = [];
        $changes = 0;

        foreach ($parents as $index => $old) {
            if (!$where($old)) {
                continue;
            }

            $before = self::fireTriggers('before', 'delete', $old, $old, $children, $triggers, $spec);
            $children = $before['child'];
            $effects = array_merge($effects, $before['effects']);
            unset($parents[$index]);
            ++$changes;

            $fk = self::applyForeignKeyAction('delete', $old, null, $children, $spec);
            $children = $fk['child'];
            $fkActions = array_merge($fkActions, $fk['actions']);
            $remainingParents = array_values($parents);
            $statementViolations = self::foreignKeyViolations($remainingParents, $children, $spec);
            if ($restrictImmediate && $statementViolations !== []) {
                throw new \InvalidArgumentException('SQLite trigger/FK RETURNING immediate constraint failed');
            }
            $violations = array_merge($violations, self::tagViolations($statementViolations, $changes - 1, 'statement'));

            $after = self::fireTriggers('after', 'delete', $old, $old, $children, $triggers, $spec);
            $children = $after['child'];
            $effects = array_merge($effects, $after['effects']);
            $afterViolations = self::foreignKeyViolations($remainingParents, $children, $spec);
            if ((!$spec['deferred'] || $restrictImmediate) && $afterViolations !== []) {
                throw new \InvalidArgumentException('SQLite trigger/FK RETURNING immediate constraint failed after trigger');
            }
            $violations = array_merge($violations, self::tagViolations($afterViolations, $changes - 1, 'after-trigger'));

            $yielded[] = self::yieldRow($changes - 1, 'delete', $old, $old, $returning, $rowIdColumn, $statementViolations, $afterViolations);
        }

        return self::result(array_values($parents), $children, $yielded, $effects, $fkActions, $violations, $changes);
    }

    /**
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     * @return array<string,mixed>
     */
    private static function updatedRow(array $old, array $assignments): array
    {
        $new = $old;
        foreach ($assignments as $column => $value) {
            self::identifier((string) $column, 'assignment column');
            $new[$column] = is_callable($value) ? $value($old) : $value;
        }

        return $new;
    }

    /**
     * @param array<string,mixed|callable(array<string,mixed>):mixed> $assignments
     */
    private static function validateAssignments(array $assignments): void
    {
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite trigger/FK RETURNING UPDATE requires assignments');
        }
        foreach (array_keys($assignments) as $column) {
            self::identifier((string) $column, 'assignment column');
        }
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $triggers
     * @param array{parent_key:string,child_key:string,on_update:string,on_delete:string,deferred:bool,child_default:mixed} $spec
     * @return array{row:array<string,mixed>,child:list<array<string,mixed>>,effects:list<array<string,mixed>>}
     */
    private static function fireTriggers(string $timing, string $event, array $old, array $new, array $children, array $triggers, array $spec): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, $new, $spec)) {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'set-new') {
                foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                    self::identifier((string) $column, 'trigger set column');
                    $new[$column] = self::value($value, $old, $new, $spec);
                }
            } elseif ($action === 'insert-child') {
                $children[] = self::project((array) ($trigger['row'] ?? []), $old, $new, $spec);
            } elseif ($action === 'update-child') {
                $match = self::value($trigger['match'] ?? 'old.parent_key', $old, $new, $spec);
                foreach ($children as &$child) {
                    if (self::rowValue($child, $spec['child_key'], 'child row') == $match) {
                        foreach ((array) ($trigger['set'] ?? [$spec['child_key'] => 'new.parent_key']) as $column => $value) {
                            self::identifier((string) $column, 'trigger child column');
                            $child[$column] = self::value($value, $old, $new, $spec);
                        }
                    }
                }
                unset($child);
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite trigger/FK RETURNING trigger action is unsupported');
            }

            $effects[] = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'timing' => $timing,
                'event' => $event,
                'action' => $action,
                'row' => self::project((array) ($trigger['values'] ?? []), $old, $new, $spec),
            ];
        }

        return ['row' => $new, 'child' => array_values($children), 'effects' => $effects];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update:string,on_delete:string,deferred:bool,child_default:mixed} $spec
     * @return array{child:list<array<string,mixed>>,actions:list<array<string,mixed>>}
     */
    private static function applyForeignKeyAction(string $event, array $old, ?array $new, array $children, array $spec): array
    {
        $oldKey = self::rowValue($old, $spec['parent_key'], 'OLD parent row');
        $newKey = $new === null ? null : self::rowValue($new, $spec['parent_key'], 'NEW parent row');
        if ($event === 'update' && $oldKey === $newKey) {
            return ['child' => array_values($children), 'actions' => []];
        }

        $mode = $event === 'update' ? $spec['on_update'] : $spec['on_delete'];
        $actions = [];
        foreach ($children as $index => &$child) {
            if (self::rowValue($child, $spec['child_key'], 'child row') != $oldKey) {
                continue;
            }
            if ($mode === 'cascade' && $event === 'update') {
                $child[$spec['child_key']] = $newKey;
                $actions[] = ['event' => $event, 'action' => 'cascade', 'child_index' => $index, 'from' => $oldKey, 'to' => $newKey];
            } elseif ($mode === 'cascade' && $event === 'delete') {
                $actions[] = ['event' => $event, 'action' => 'cascade-delete', 'child_index' => $index, 'from' => $oldKey, 'to' => null];
                unset($children[$index]);
            } elseif ($mode === 'set null') {
                $child[$spec['child_key']] = null;
                $actions[] = ['event' => $event, 'action' => 'set-null', 'child_index' => $index, 'from' => $oldKey, 'to' => null];
            } elseif ($mode === 'set default') {
                $child[$spec['child_key']] = $spec['child_default'];
                $actions[] = ['event' => $event, 'action' => 'set-default', 'child_index' => $index, 'from' => $oldKey, 'to' => $spec['child_default']];
            } elseif ($mode === 'restrict' || $mode === 'no action') {
                $actions[] = ['event' => $event, 'action' => $mode, 'child_index' => $index, 'from' => $oldKey, 'to' => $newKey];
            } else {
                throw new \InvalidArgumentException('SQLite trigger/FK RETURNING foreign key action is unsupported');
            }
        }
        unset($child);

        return ['child' => array_values($children), 'actions' => $actions];
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update:string,on_delete:string,deferred:bool,child_default:mixed} $spec
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
            $violations[] = ['child_index' => $index, 'child_key' => $key, 'parent' => $spec['parent_key']];
        }

        return $violations;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,string):mixed>|null $projection
     * @param list<array<string,mixed>> $beforeViolations
     * @param list<array<string,mixed>> $afterViolations
     * @return array<string,mixed>
     */
    private static function yieldRow(int $ordinal, string $event, array $old, array $row, ?array $projection, string $rowIdColumn, array $beforeViolations, array $afterViolations): array
    {
        return [
            'ordinal' => $ordinal,
            'event' => $event,
            'old_key' => $old[$rowIdColumn] ?? null,
            'new_key' => $row[$rowIdColumn] ?? null,
            'violations_before_after_triggers' => count($beforeViolations),
            'violations_after_triggers' => count($afterViolations),
            'returning' => self::returningRow($projection, $old, $row, $event),
        ];
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,string):mixed>|null $projection
     * @return array<string,mixed>
     */
    private static function returningRow(?array $projection, array $old, array $row, string $event): array
    {
        if ($projection === null) {
            return $row;
        }

        $out = [];
        foreach ($projection as $index => $entry) {
            if (is_string($entry)) {
                if ($entry === '*') {
                    $out['*'] = $row;
                    continue;
                }
                $alias = str_contains($entry, '.') ? substr($entry, (int) strrpos($entry, '.') + 1) : $entry;
                $out[self::identifier($alias, 'RETURNING alias')] = self::returningValue($entry, $old, $row);
                continue;
            }
            if (is_array($entry)) {
                $expr = (string) ($entry['expr'] ?? '');
                $alias = (string) ($entry['as'] ?? (str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr));
                $out[self::identifier($alias, 'RETURNING alias')] = self::returningValue($expr, $old, $row);
                continue;
            }
            if (is_callable($entry)) {
                $out['expr' . $index] = $entry($row, $old, $event);
                continue;
            }
            throw new \InvalidArgumentException('SQLite trigger/FK RETURNING projection is malformed');
        }

        return $out;
    }

    private static function returningValue(string $expr, array $old, array $row): mixed
    {
        $expr = trim($expr);
        if (str_starts_with($expr, 'old.')) {
            return self::rowValue($old, substr($expr, 4), 'RETURNING OLD row');
        }
        if (str_starts_with($expr, 'new.')) {
            return self::rowValue($row, substr($expr, 4), 'RETURNING NEW row');
        }

        return self::rowValue($row, $expr, 'RETURNING row');
    }

    /**
     * @param array<string,mixed> $template
     * @param array{parent_key:string,child_key:string,on_update:string,on_delete:string,deferred:bool,child_default:mixed} $spec
     * @return array<string,mixed>
     */
    private static function project(array $template, array $old, array $new, array $spec): array
    {
        $row = [];
        foreach ($template as $column => $value) {
            self::identifier((string) $column, 'projection column');
            $row[$column] = self::value($value, $old, $new, $spec);
        }

        return $row;
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update?:string,on_delete?:string,deferred?:bool,child_default?:mixed,child_defaults?:array<string,mixed>} $foreignKey
     * @return array{parent_key:string,child_key:string,on_update:string,on_delete:string,deferred:bool,child_default:mixed}
     */
    private static function foreignKeySpec(array $foreignKey): array
    {
        $childKey = self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key');

        return [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'child_key' => $childKey,
            'on_update' => self::fkAction((string) ($foreignKey['on_update'] ?? 'no action')),
            'on_delete' => self::fkAction((string) ($foreignKey['on_delete'] ?? 'no action')),
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
            'child_default' => self::childDefault($foreignKey, $childKey),
        ];
    }

    private static function fkAction(string $action): string
    {
        return match (strtolower(trim($action))) {
            'cascade' => 'cascade',
            'set null', 'set-null' => 'set null',
            'set default', 'set-default' => 'set default',
            'restrict' => 'restrict',
            'no action', 'no-action' => 'no action',
            default => throw new \InvalidArgumentException('SQLite trigger/FK RETURNING foreign key action is unsupported'),
        };
    }

    /**
     * @param array<string,mixed> $foreignKey
     */
    private static function childDefault(array $foreignKey, string $childKey): mixed
    {
        if (array_key_exists('child_defaults', $foreignKey)) {
            if (!is_array($foreignKey['child_defaults'])) {
                throw new \InvalidArgumentException('SQLite trigger/FK RETURNING child defaults are malformed');
            }
            if (array_key_exists($childKey, $foreignKey['child_defaults'])) {
                return $foreignKey['child_defaults'][$childKey];
            }
        }
        if (array_key_exists('child_default', $foreignKey)) {
            return $foreignKey['child_default'];
        }

        return null;
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update:string,on_delete:string,deferred:bool,child_default:mixed} $spec
     */
    private static function value(mixed $value, array $old, array $new, array $spec): mixed
    {
        if ($value === 'old.parent_key') {
            return self::rowValue($old, $spec['parent_key'], 'OLD parent row');
        }
        if ($value === 'new.parent_key') {
            return self::rowValue($new, $spec['parent_key'], 'NEW parent row');
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return self::rowValue($old, substr($value, 4), 'OLD row');
        }
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return self::rowValue($new, substr($value, 4), 'NEW row');
        }

        return $value;
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update:string,on_delete:string,deferred:bool} $spec
     */
    private static function whenMatches(mixed $when, array $old, array $new, array $spec): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new \InvalidArgumentException('SQLite trigger/FK RETURNING WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new, $spec);
        $right = self::value($right, $old, $new, $spec);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite trigger/FK RETURNING WHEN operator is unsupported'),
        };
    }

    /**
     * @param list<array<string,mixed>> $violations
     * @return list<array<string,mixed>>
     */
    private static function tagViolations(array $violations, int $ordinal, string $phase): array
    {
        foreach ($violations as &$violation) {
            $violation['ordinal'] = $ordinal;
            $violation['phase'] = $phase;
        }
        unset($violation);

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $violations
     * @return list<array<string,mixed>>
     */
    private static function dedupeViolations(array $violations): array
    {
        $seen = [];
        $out = [];
        foreach ($violations as $violation) {
            $key = implode('|', [(string) $violation['phase'], (string) $violation['ordinal'], (string) $violation['child_index'], (string) $violation['child_key']]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $violation;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $yielded
     * @param list<array<string,mixed>> $effects
     * @param list<array<string,mixed>> $fkActions
     * @param list<array<string,mixed>> $violations
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,foreign_key_actions:list<array<string,mixed>>,foreign_key_violations:list<array<string,mixed>>,changes:int}
     */
    private static function result(array $parents, array $children, array $yielded, array $effects, array $fkActions, array $violations, int $changes): array
    {
        return [
            'parent' => array_values($parents),
            'child' => array_values($children),
            'yielded' => $yielded,
            'trigger_effects' => $effects,
            'foreign_key_actions' => $fkActions,
            'foreign_key_violations' => self::dedupeViolations($violations),
            'changes' => $changes,
        ];
    }

    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite trigger/FK RETURNING {$label} missing column {$column}");
        }

        return $row[$column];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger/FK RETURNING {$label} is malformed");
        }

        return $value;
    }
}
