<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param array{where:callable(array<string,mixed>):bool,assignments:array<string,mixed|callable(array<string,mixed>,int,string):mixed>,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,array<string,mixed>):mixed>,trigger?:array<string,mixed>,recursive_triggers?:bool,max_depth?:int,rollback_on_deferred_violation?:bool,savepoint?:string,rowid_column?:string,current_source?:string,next_source?:string} $statement
     * @return array<string,mixed>
     */
    public static function update(array $parents, array $children, array $foreignKey, array $statement): array
    {
        $rowid = self::identifier((string) ($statement['rowid_column'] ?? 'option_id'), 'rowid column');
        $savepoint = self::identifier((string) ($statement['savepoint'] ?? 'trigger_returning_deferred'), 'savepoint');
        $currentSource = self::sourceName((string) ($statement['current_source'] ?? 'current'));
        $nextSource = self::sourceName((string) ($statement['next_source'] ?? 'next'));
        $where = $statement['where'] ?? null;
        if (!is_callable($where)) {
            throw new \InvalidArgumentException('SQLite trigger recursive deferred RETURNING WHERE callback is required');
        }
        $assignments = (array) ($statement['assignments'] ?? []);
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite trigger recursive deferred RETURNING assignments are required');
        }
        foreach (array_keys($assignments) as $column) {
            self::identifier((string) $column, 'assignment column');
        }

        $fk = [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key'),
            'deferred' => (bool) ($foreignKey['deferred'] ?? true),
        ];
        $originalParents = array_values($parents);
        $originalChildren = array_values($children);
        $parents = array_values($parents);
        $children = array_values($children);
        $recursive = (bool) ($statement['recursive_triggers'] ?? true);
        $maxDepth = self::nonNegativeInt($statement['max_depth'] ?? 1000, 'max depth');
        $trigger = (array) ($statement['trigger'] ?? []);
        $returning = isset($statement['returning']) ? (array) $statement['returning'] : null;

        $queue = [];
        foreach ($parents as $index => $row) {
            if ($where($row)) {
                $queue[] = ['index' => $index, 'depth' => 0, 'source' => 'statement'];
            }
        }

        $changes = 0;
        $returningRows = [];
        $attemptedReturning = [];
        $triggerEffects = [];
        $visited = [];
        while ($queue !== []) {
            $work = array_shift($queue);
            $index = (int) $work['index'];
            $depth = (int) $work['depth'];
            $source = (string) $work['source'];
            if ($depth > $maxDepth) {
                throw new \InvalidArgumentException('SQLite trigger recursive deferred RETURNING max depth exceeded');
            }
            if (!isset($parents[$index])) {
                continue;
            }
            $old = $parents[$index];
            $visitKey = $index . ':' . $depth . ':' . (string) ($old[$rowid] ?? '');
            if (isset($visited[$visitKey])) {
                continue;
            }
            $visited[$visitKey] = true;
            $new = self::updatedRow($old, $assignments, $depth, $source);
            if ($new === $old) {
                continue;
            }
            $parents[$index] = $new;
            ++$changes;

            $entry = [
                'ordinal' => count($attemptedReturning),
                'source' => $currentSource,
                'event' => 'update',
                'trigger_depth' => $depth,
                'trigger_source' => $source,
                'old_key' => $old[$rowid] ?? null,
                'new_key' => $new[$rowid] ?? null,
                'returning' => self::returningRow($returning, $old, $new, [
                    'source' => $currentSource,
                    'trigger_depth' => $depth,
                    'trigger_source' => $source,
                    'savepoint' => $savepoint,
                ]),
            ];
            if ($source === 'statement') {
                $returningRows[] = $entry;
            } else {
                $triggerEffects[] = $entry + ['trigger' => $source];
            }
            $attemptedReturning[] = $entry;

            if ($recursive && $trigger !== []) {
                foreach (self::recursiveTargets($parents, $old, $new, $trigger, $rowid) as $nextIndex) {
                    $queue[] = ['index' => $nextIndex, 'depth' => $depth + 1, 'source' => (string) ($trigger['name'] ?? 'recursive_trigger')];
                }
            }
        }

        $violations = self::violations($parents, $children, $fk);
        $rollback = (bool) ($statement['rollback_on_deferred_violation'] ?? false) && $fk['deferred'] && $violations !== [];
        $visibleReturning = $rollback ? [] : array_values(array_map(static fn (array $row): array => $row['returning'], $returningRows));

        return [
            'status' => $violations === [] ? 'commit-ok' : ($rollback ? 'rolled-back' : 'deferred-commit-blocked'),
            'savepoint' => $savepoint,
            'current_source' => $currentSource,
            'next_source' => $rollback ? $currentSource : $nextSource,
            'returning_source' => $currentSource,
            'recursive_triggers' => $recursive,
            'max_depth' => $maxDepth,
            'current_parent' => $parents,
            'next_parent' => $rollback ? $originalParents : $parents,
            'current_child' => $children,
            'next_child' => $rollback ? $originalChildren : $children,
            'current_returning_rows' => array_values(array_map(static fn (array $row): array => $row['returning'], $returningRows)),
            'next_returning_rows' => $visibleReturning,
            'attempted_returning_rows' => $attemptedReturning,
            'trigger_returning_rows' => $triggerEffects,
            'foreign_key_violations' => $violations,
            'current_changes' => $changes,
            'next_changes' => $rollback ? 0 : $changes,
            'current_rowids' => self::rowids($parents, $rowid),
            'next_rowids' => self::rowids($rollback ? $originalParents : $parents, $rowid),
            'yield_boundary' => $rollback ? 'current-yield-next-rollback' : ($violations === [] ? 'current-yield-next-commit' : 'current-yield-next-blocked'),
            'yield_suppressed_by_deferred_rollback' => $rollback && $returningRows !== [],
            'dependencies' => [
                'sqlite-trigger-recursive-current-source',
                'sqlite-returning-current-source-before-deferred-check',
                'sqlite-deferred-fk-next-source-resolution',
            ],
        ];
    }

    /**
     * @param array<string,mixed|callable(array<string,mixed>,int,string):mixed> $assignments
     * @return array<string,mixed>
     */
    private static function updatedRow(array $old, array $assignments, int $depth, string $source): array
    {
        $new = $old;
        foreach ($assignments as $column => $value) {
            $new[(string) $column] = is_callable($value) ? $value($old, $depth, $source) : $value;
        }

        return $new;
    }

    /**
     * @return list<int>
     */
    private static function recursiveTargets(array $parents, array $old, array $new, array $trigger, string $rowid): array
    {
        $column = self::identifier((string) ($trigger['match_column'] ?? $rowid), 'trigger match column');
        $value = self::triggerValue($trigger['match_value'] ?? 'old.next_id', $old, $new);
        $targets = [];
        foreach ($parents as $index => $row) {
            if (($row[$column] ?? null) === $value) {
                $targets[] = $index;
            }
        }

        return $targets;
    }

    private static function triggerValue(mixed $value, array $old, array $new): mixed
    {
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return $old[substr($value, 4)] ?? null;
        }
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return $new[substr($value, 4)] ?? null;
        }

        return $value;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,array<string,mixed>):mixed>|null $projection
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function returningRow(?array $projection, array $old, array $new, array $context): array
    {
        if ($projection === null) {
            return $new;
        }
        $row = [];
        foreach ($projection as $index => $entry) {
            if (is_string($entry)) {
                $alias = str_contains($entry, '.') ? substr($entry, (int) strrpos($entry, '.') + 1) : $entry;
                $row[self::identifier($alias, 'RETURNING alias')] = self::value($entry, $old, $new, $context);
                continue;
            }
            if (is_array($entry)) {
                $expr = (string) ($entry['expr'] ?? '');
                $alias = (string) ($entry['as'] ?? (str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr));
                $row[self::identifier($alias, 'RETURNING alias')] = self::value($expr, $old, $new, $context);
                continue;
            }
            if (is_callable($entry)) {
                $row['expr' . $index] = $entry($new, $old, $context);
                continue;
            }
            throw new \InvalidArgumentException('SQLite trigger recursive deferred RETURNING projection is malformed');
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function value(string $expr, array $old, array $new, array $context): mixed
    {
        if (str_starts_with($expr, 'old.')) {
            return $old[substr($expr, 4)] ?? throw new \InvalidArgumentException('SQLite trigger recursive deferred RETURNING OLD column is missing');
        }
        if (str_starts_with($expr, 'new.')) {
            return $new[substr($expr, 4)] ?? throw new \InvalidArgumentException('SQLite trigger recursive deferred RETURNING NEW column is missing');
        }
        if (str_starts_with($expr, 'context.')) {
            return $context[substr($expr, 8)] ?? throw new \InvalidArgumentException('SQLite trigger recursive deferred RETURNING context column is missing');
        }

        return $new[$expr] ?? throw new \InvalidArgumentException('SQLite trigger recursive deferred RETURNING column is missing');
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred:bool} $fk
     * @return list<array<string,mixed>>
     */
    private static function violations(array $parents, array $children, array $fk): array
    {
        $keys = array_column($parents, $fk['parent_key']);
        $violations = [];
        foreach ($children as $index => $child) {
            $key = $child[$fk['child_key']] ?? null;
            if ($key === null || in_array($key, $keys, true)) {
                continue;
            }
            $violations[] = [
                'phase' => 'deferred-commit',
                'child_index' => $index,
                'child_key' => $key,
                'parent_key' => $fk['parent_key'],
            ];
        }

        return $violations;
    }

    /**
     * @return list<mixed>
     */
    private static function rowids(array $rows, string $column): array
    {
        return array_values(array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows));
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite trigger recursive deferred RETURNING {$label} is malformed");
        }

        return $value;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger recursive deferred RETURNING {$label} is malformed");
        }

        return $value;
    }

    private static function sourceName(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite trigger recursive deferred RETURNING source name is malformed');
        }

        return $value;
    }
}
