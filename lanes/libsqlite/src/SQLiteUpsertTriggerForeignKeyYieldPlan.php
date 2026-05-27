<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpsertTriggerForeignKeyYieldPlan
{
    /**
     * @param list<array<string,mixed>> $parentRows
     * @param list<array<string,mixed>> $childRows
     * @param list<array<string,mixed>> $incomingRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @return array{parent:list<array<string,mixed>>,child:list<array<string,mixed>>,inserted:list<array<string,mixed>>,updated:list<array<string,mixed>>,skipped:list<array<string,mixed>>,yielded:list<array<string,mixed>>,foreign_key_violations:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int}
     */
    public static function execute(
        array $parentRows,
        array $childRows,
        array $incomingRows,
        array $uniqueColumns,
        array $assignments,
        array $foreignKey,
        array $triggers,
        ?callable $where = null,
    ): array {
        $parents = array_values($parentRows);
        $children = array_values($childRows);
        $spec = self::foreignKeySpec($foreignKey);
        $inserted = [];
        $updated = [];
        $skipped = [];
        $yielded = [];
        $violations = [];
        $effects = [];
        $changes = 0;

        foreach ($incomingRows as $ordinal => $incoming) {
            $conflictIndex = self::findConflictIndex($parents, $incoming, $uniqueColumns);
            $event = $conflictIndex === null ? 'insert' : 'update';
            $old = $conflictIndex === null ? null : $parents[$conflictIndex];

            if ($old !== null && $where !== null && !$where($old, $incoming)) {
                $skipped[] = $incoming;
                $yielded[] = self::yieldRow($ordinal, 'skipped', $event, $old, $incoming, [], []);
                continue;
            }

            $new = $old ?? $incoming;
            if ($old !== null) {
                foreach ($assignments as $column => $assignment) {
                    self::identifier((string) $column, 'assignment column');
                    $new[$column] = $assignment($old, $incoming);
                }
            }

            $before = self::fireTriggers('before', $event, $old, $new, $children, $triggers, $spec);
            $new = $before['row'];
            $children = $before['child'];
            $effects = array_merge($effects, $before['effects']);

            if ($old === null) {
                $parents[] = $new;
                $inserted[] = $new;
            } else {
                $parents[$conflictIndex] = $new;
                $updated[] = $new;
            }
            ++$changes;

            $rowViolations = self::foreignKeyViolations($parents, $children, $spec);
            if (!$spec['deferred'] && $rowViolations !== []) {
                throw new \InvalidArgumentException('SQLite UPSERT trigger foreign key immediate constraint failed');
            }
            $violations = array_merge($violations, self::tagViolations($rowViolations, $ordinal));

            $after = self::fireTriggers('after', $event, $old, $new, $children, $triggers, $spec);
            $new = $after['row'];
            $children = $after['child'];
            $effects = array_merge($effects, $after['effects']);
            if ($old === null) {
                $parents[array_key_last($parents)] = $new;
                $inserted[array_key_last($inserted)] = $new;
            } else {
                $parents[$conflictIndex] = $new;
                $updated[array_key_last($updated)] = $new;
            }

            $afterViolations = self::foreignKeyViolations($parents, $children, $spec);
            if (!$spec['deferred'] && $afterViolations !== []) {
                throw new \InvalidArgumentException('SQLite UPSERT trigger foreign key immediate constraint failed after trigger');
            }
            $violations = array_merge($violations, self::tagViolations($afterViolations, $ordinal, 'after-trigger'));
            $yielded[] = self::yieldRow($ordinal, 'changed', $event, $old, $new, $rowViolations, $afterViolations);
        }

        return [
            'parent' => array_values($parents),
            'child' => array_values($children),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'yielded' => $yielded,
            'foreign_key_violations' => self::dedupeViolations($violations),
            'trigger_effects' => $effects,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     */
    private static function findConflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($uniqueColumns as $column) {
            self::identifier($column, 'unique column');
        }
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new \InvalidArgumentException("SQLite UPSERT trigger/FK unique column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return $index;
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param list<array<string,mixed>> $triggers
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     * @return array{row:array<string,mixed>,child:list<array<string,mixed>>,effects:list<array<string,mixed>>}
     */
    private static function fireTriggers(string $timing, string $event, ?array $old, array $new, array $children, array $triggers, array $spec): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, $new)) {
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
                $match = self::value($trigger['match'] ?? 'new.parent_key', $old, $new, $spec);
                $set = self::value($trigger['set_child_key'] ?? 'new.parent_key', $old, $new, $spec);
                foreach ($children as &$child) {
                    if (self::rowValue($child, $spec['child_key'], 'child row') == $match) {
                        $child[$spec['child_key']] = $set;
                    }
                }
                unset($child);
            } elseif ($action !== 'audit') {
                throw new \InvalidArgumentException('SQLite UPSERT trigger/FK action is unsupported');
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
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     * @return list<array<string,mixed>>
     */
    private static function foreignKeyViolations(array $parents, array $children, array $spec): array
    {
        $parentKeys = [];
        foreach ($parents as $parent) {
            $parentKeys[] = self::rowValue($parent, $spec['parent_key'], 'parent row');
        }

        $violations = [];
        foreach ($children as $index => $child) {
            $childKey = self::rowValue($child, $spec['child_key'], 'child row');
            if ($childKey === null || in_array($childKey, $parentKeys, true)) {
                continue;
            }
            $violations[] = ['child_index' => $index, 'child_key' => $childKey, 'parent' => $spec['parent_key']];
        }

        return $violations;
    }

    /**
     * @param list<array<string,mixed>> $before
     * @param list<array<string,mixed>> $after
     * @return array<string,mixed>
     */
    private static function yieldRow(int $ordinal, string $status, string $event, ?array $old, array $new, array $before, array $after): array
    {
        return [
            'ordinal' => $ordinal,
            'status' => $status,
            'event' => $event,
            'old_key' => $old['option_id'] ?? null,
            'new_key' => $new['option_id'] ?? null,
            'violations_before_after_triggers' => count($before),
            'violations_after_triggers' => count($after),
        ];
    }

    /**
     * @param list<array<string,mixed>> $violations
     * @return list<array<string,mixed>>
     */
    private static function tagViolations(array $violations, int $ordinal, string $phase = 'statement'): array
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
        $deduped = [];
        foreach ($violations as $violation) {
            $key = implode('|', [(string) $violation['phase'], (string) $violation['ordinal'], (string) $violation['child_index'], (string) $violation['child_key']]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $violation;
        }

        return $deduped;
    }

    /**
     * @param array<string,mixed> $template
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     * @return array<string,mixed>
     */
    private static function project(array $template, ?array $old, array $new, array $spec): array
    {
        $row = [];
        foreach ($template as $column => $value) {
            self::identifier((string) $column, 'projection column');
            $row[$column] = self::value($value, $old, $new, $spec);
        }

        return $row;
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @return array{parent_key:string,child_key:string,deferred:bool}
     */
    private static function foreignKeySpec(array $foreignKey): array
    {
        return [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key'),
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
        ];
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred:bool} $spec
     */
    private static function value(mixed $value, ?array $old, array $new, array $spec): mixed
    {
        if ($value === 'new.parent_key') {
            return self::rowValue($new, $spec['parent_key'], 'NEW row');
        }
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return self::rowValue($new, substr($value, 4), 'NEW row');
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite UPSERT trigger/FK OLD row is unavailable for INSERT');
            }

            return self::rowValue($old, substr($value, 4), 'OLD row');
        }

        return $value;
    }

    private static function whenMatches(mixed $when, ?array $old, array $new): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new \InvalidArgumentException('SQLite UPSERT trigger/FK WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new, ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true]);
        $right = self::value($right, $old, $new, ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true]);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new \InvalidArgumentException('SQLite UPSERT trigger/FK WHEN operator is unsupported'),
        };
    }

    private static function rowValue(array $row, string $column, string $label): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new \InvalidArgumentException("SQLite UPSERT trigger/FK {$label} missing column {$column}");
        }

        return $row[$column];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite UPSERT trigger/FK {$label} is malformed");
        }

        return $value;
    }
}
