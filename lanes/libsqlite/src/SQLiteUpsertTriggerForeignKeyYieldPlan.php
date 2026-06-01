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
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string):mixed>|null $returning
     * @param list<list<string>>|null $uniqueConstraints
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
        ?array $returning = null,
        ?array $uniqueConstraints = null,
    ): array {
        $parents = array_values($parentRows);
        $children = array_values($childRows);
        $spec = self::foreignKeySpec($foreignKey);
        $uniqueConstraints = self::normalizeUniqueConstraints($uniqueColumns, $uniqueConstraints);
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
                $yielded[] = self::yieldRow($ordinal, 'skipped', $event, $old, $incoming, $incoming, [], [], $returning, $spec);
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
            $returningImage = $new;
            $children = $before['child'];
            $effects = array_merge($effects, $before['effects']);
            $otherParents = $parents;
            if ($conflictIndex !== null) {
                unset($otherParents[$conflictIndex]);
            }
            self::ensureNoUniqueConflict(array_values($otherParents), $new, $uniqueConstraints, $event);

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
            $otherParents = $parents;
            if ($old === null) {
                array_pop($otherParents);
            } else {
                unset($otherParents[$conflictIndex]);
            }
            self::ensureNoUniqueConflict(array_values($otherParents), $new, $uniqueConstraints, $event . ' after trigger');
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
            $yielded[] = self::yieldRow($ordinal, 'changed', $event, $old, $returningImage, $incoming, $rowViolations, $afterViolations, $returning, $spec);
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
     * @param list<string> $conflictTarget
     * @param list<list<string>>|null $uniqueConstraints
     * @return list<list<string>>
     */
    private static function normalizeUniqueConstraints(array $conflictTarget, ?array $uniqueConstraints): array
    {
        if ($uniqueConstraints === null) {
            self::validateUniqueColumns($conflictTarget, 'unique column');

            return [$conflictTarget];
        }
        if ($uniqueConstraints === [] || !array_is_list($uniqueConstraints)) {
            throw new \InvalidArgumentException('SQLite UPSERT trigger/FK unique constraints must be a non-empty list');
        }

        $normalized = [];
        foreach ($uniqueConstraints as $columns) {
            if (!is_array($columns)) {
                throw new \InvalidArgumentException('SQLite UPSERT trigger/FK unique constraint must be a column list');
            }
            self::validateUniqueColumns($columns, 'unique constraint column');
            $normalized[] = array_values($columns);
        }

        return $normalized;
    }

    /**
     * @param list<string> $columns
     */
    private static function validateUniqueColumns(array $columns, string $label): void
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException('SQLite UPSERT trigger/FK unique columns must be a non-empty list');
        }
        foreach ($columns as $column) {
            self::identifier($column, $label);
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<list<string>> $uniqueConstraints
     */
    private static function ensureNoUniqueConflict(array $rows, array $candidate, array $uniqueConstraints, string $operation): void
    {
        foreach ($uniqueConstraints as $columns) {
            foreach ($rows as $row) {
                if (self::rowsConflict($row, $candidate, $columns)) {
                    throw new \InvalidArgumentException("SQLite UPSERT trigger/FK {$operation} produced a unique constraint conflict");
                }
            }
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function rowsConflict(array $left, array $right, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $left) || !array_key_exists($column, $right)) {
                throw new \InvalidArgumentException("SQLite UPSERT trigger/FK unique column {$column} is missing");
            }
            if ($left[$column] === null || $right[$column] === null || $left[$column] != $right[$column]) {
                return false;
            }
        }

        return true;
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
    private static function yieldRow(int $ordinal, string $status, string $event, ?array $old, array $new, array $incoming, array $before, array $after, ?array $returning, ?array $spec = null): array
    {
        $parentKey = is_array($spec) ? $spec['parent_key'] : null;

        return [
            'ordinal' => $ordinal,
            'status' => $status,
            'event' => $event,
            'old_key' => $parentKey === null || $old === null ? null : ($old[$parentKey] ?? null),
            'new_key' => $parentKey === null ? null : ($new[$parentKey] ?? null),
            'violations_before_after_triggers' => count($before),
            'violations_after_triggers' => count($after),
            'returning' => $status === 'changed' ? self::returningRow($returning, $old, $new, $incoming, $event) : null,
        ];
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string):mixed>|null $projection
     * @return array<string,mixed>
     */
    private static function returningRow(?array $projection, ?array $old, array $new, array $incoming, string $event): array
    {
        if ($projection === null) {
            return $new;
        }

        $row = [];
        foreach ($projection as $index => $entry) {
            if (is_string($entry)) {
                if ($entry === '*') {
                    $row['*'] = self::returningValue($entry, $old, $new, $incoming);
                    continue;
                }
                $alias = str_contains($entry, '.') ? substr($entry, (int) strrpos($entry, '.') + 1) : $entry;
                $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($entry, $old, $new, $incoming);
                continue;
            }
            if (is_array($entry)) {
                $expr = (string) ($entry['expr'] ?? '');
                $alias = (string) ($entry['as'] ?? (str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr));
                $row[self::identifier($alias, 'RETURNING alias')] = self::returningValue($expr, $old, $new, $incoming);
                continue;
            }
            if (is_callable($entry)) {
                $row['expr' . $index] = $entry($new, $old, $incoming, $event);
                continue;
            }

            throw new \InvalidArgumentException('SQLite UPSERT trigger/FK RETURNING projection is malformed');
        }

        return $row;
    }

    private static function returningValue(string $expr, ?array $old, array $new, array $incoming): mixed
    {
        $expr = trim($expr);
        if ($expr === '*') {
            return $new;
        }
        if (str_starts_with($expr, 'new.')) {
            return self::rowValue($new, substr($expr, 4), 'RETURNING NEW row');
        }
        if (str_starts_with($expr, 'excluded.')) {
            return self::rowValue($incoming, substr($expr, 9), 'RETURNING excluded row');
        }
        if (str_starts_with($expr, 'old.')) {
            if ($old === null) {
                throw new \InvalidArgumentException('SQLite UPSERT trigger/FK RETURNING OLD row is unavailable for INSERT');
            }

            return self::rowValue($old, substr($expr, 4), 'RETURNING OLD row');
        }

        return self::rowValue($new, $expr, 'RETURNING row');
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

    private static function whenMatches(mixed $when, ?array $old, array $new, array $spec): bool
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
        $left = self::value($left, $old, $new, $spec);
        $right = self::value($right, $old, $new, $spec);

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
