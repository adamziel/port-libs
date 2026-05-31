<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update?:string,deferred?:bool} $foreignKey
     * @param array{where:callable(array<string,mixed>):bool,assignments:array<string,mixed|callable(array<string,mixed>, int):mixed>,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string):mixed>,savepoint?:string,recursive_triggers?:bool,max_depth?:int,rollback_on_deferred_violation?:bool,rowid_column?:string,trigger?:array<string,mixed>,page_images?:array<int,string>,dirty_pages?:array<int,string>,wal_start_frame?:int,wal_frames?:list<array<string,mixed>>} $statement
     * @return array<string,mixed>
     */
    public static function run(array $parents, array $children, array $foreignKey, array $statement): array
    {
        $rowIdColumn = self::identifier((string) ($statement['rowid_column'] ?? 'option_id'), 'rowid column');
        $savepoint = self::identifier((string) ($statement['savepoint'] ?? 'recursive_returning_fk'), 'savepoint');
        $where = $statement['where'] ?? null;
        if (!is_callable($where)) {
            throw new \InvalidArgumentException('SQLite recursive trigger RETURNING deferred FK WHERE callback is required');
        }
        $assignments = (array) ($statement['assignments'] ?? []);
        if ($assignments === []) {
            throw new \InvalidArgumentException('SQLite recursive trigger RETURNING deferred FK assignments are required');
        }
        foreach (array_keys($assignments) as $column) {
            self::identifier((string) $column, 'assignment column');
        }

        $spec = self::foreignKeySpec($foreignKey);
        $parents = array_values($parents);
        $children = array_values($children);
        $originalParents = $parents;
        $originalChildren = $children;
        $recursive = (bool) ($statement['recursive_triggers'] ?? true);
        $maxDepth = self::nonNegativeInt($statement['max_depth'] ?? 1000, 'max depth');
        $returning = isset($statement['returning']) ? (array) $statement['returning'] : null;
        $trigger = (array) ($statement['trigger'] ?? []);

        $topLevelYielded = [];
        $triggerEffects = [];
        $visited = [];
        $queue = [];
        foreach ($parents as $index => $row) {
            if ($where($row)) {
                $queue[] = ['index' => $index, 'depth' => 0, 'source' => 'statement'];
            }
        }

        $changes = 0;
        while ($queue !== []) {
            $work = array_shift($queue);
            $index = (int) $work['index'];
            $depth = (int) $work['depth'];
            $source = (string) $work['source'];
            if (!isset($parents[$index])) {
                continue;
            }
            if ($depth > $maxDepth) {
                throw new \InvalidArgumentException('SQLite recursive trigger RETURNING deferred FK max depth exceeded');
            }

            $old = $parents[$index];
            $visitKey = $index . ':' . $depth . ':' . (string) ($old[$rowIdColumn] ?? '');
            if (isset($visited[$visitKey])) {
                continue;
            }
            $visited[$visitKey] = true;

            $new = self::updatedRow($old, $assignments, $depth);
            if ($new === $old) {
                continue;
            }
            $parents[$index] = $new;
            ++$changes;

            $action = self::applyNoAction($old, $new, $children, $spec);
            if ($source === 'statement') {
                $topLevelYielded[] = self::yieldRow(count($topLevelYielded), $old, $new, $returning, $rowIdColumn, $depth);
            } else {
                $triggerEffects[] = self::effectRow($source, $old, $new, $depth, $action);
            }

            if ($recursive && $trigger !== []) {
                foreach (self::recursiveTargets($parents, $old, $new, $trigger, $rowIdColumn) as $nextIndex) {
                    $queue[] = ['index' => $nextIndex, 'depth' => $depth + 1, 'source' => (string) ($trigger['name'] ?? 'recursive_trigger')];
                }
            }
        }

        $violations = self::foreignKeyViolations($parents, $children, $spec);
        $rollback = (bool) ($statement['rollback_on_deferred_violation'] ?? false) && $spec['deferred'] && $violations !== [];
        $pageImages = self::pageImages((array) ($statement['page_images'] ?? []), 'page image');
        $dirtyPages = self::pageImages((array) ($statement['dirty_pages'] ?? []), 'dirty page');
        $walStart = self::nonNegativeInt($statement['wal_start_frame'] ?? 0, 'WAL start frame');
        $walFrames = self::walFrames((array) ($statement['wal_frames'] ?? []));

        return [
            'status' => $violations === [] ? 'commit-ok' : ($rollback ? 'rolled-back' : 'deferred-commit-blocked'),
            'savepoint' => $savepoint,
            'recursive_triggers' => $recursive,
            'max_depth' => $maxDepth,
            'current_parent' => $parents,
            'next_parent' => $rollback ? $originalParents : $parents,
            'current_child' => $children,
            'next_child' => $rollback ? $originalChildren : $children,
            'current_yielded' => $topLevelYielded,
            'next_yielded' => $rollback ? [] : $topLevelYielded,
            'current_returning_rows' => array_values(array_map(static fn (array $row): array => $row['returning'], $topLevelYielded)),
            'next_returning_rows' => $rollback ? [] : array_values(array_map(static fn (array $row): array => $row['returning'], $topLevelYielded)),
            'trigger_effects' => $triggerEffects,
            'foreign_key_actions' => self::foreignKeyActions($parents, $children, $spec),
            'foreign_key_violations' => $violations,
            'statement_changes' => count($topLevelYielded),
            'next_statement_changes' => $rollback ? 0 : count($topLevelYielded),
            'current_changes' => $changes,
            'next_changes' => $rollback ? 0 : $changes,
            'yield_suppressed_by_rollback' => $rollback && $topLevelYielded !== [],
            'recursive_effects_suppressed_by_rollback' => $rollback && $triggerEffects !== [],
            'current_next_boundary' => $rollback ? 'rollback-to-savepoint' : ($violations === [] ? 'commit' : 'deferred-commit-blocked'),
            'current_rowids' => self::rowIds($parents, $rowIdColumn),
            'next_rowids' => self::rowIds($rollback ? $originalParents : $parents, $rowIdColumn),
            'rollback_page_numbers' => $rollback ? array_values(array_unique(array_merge(array_keys($pageImages), array_keys($dirtyPages)))) : [],
            'restored_page_images' => $rollback ? $pageImages : [],
            'dirty_page_numbers' => $rollback ? array_keys($dirtyPages) : [],
            'rollback_to_wal_frame' => $rollback ? $walStart : 0,
            'discarded_wal_frames' => $rollback ? self::discardedWalFrames($walFrames, $walStart) : [],
            'dependencies' => [
                'sqlite-recursive-triggers-current-source',
                'sqlite-returning-top-level-yield',
                'sqlite-deferred-foreign-key-commit-check',
                'sqlite-savepoint-current-next-rollback',
            ],
        ];
    }

    /**
     * @param array<string,mixed|callable(array<string,mixed>, int):mixed> $assignments
     * @return array<string,mixed>
     */
    private static function updatedRow(array $old, array $assignments, int $depth): array
    {
        $new = $old;
        foreach ($assignments as $column => $value) {
            $new[(string) $column] = is_callable($value) ? $value($old, $depth) : $value;
        }

        return $new;
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @return list<int>
     */
    private static function recursiveTargets(array $parents, array $old, array $new, array $trigger, string $rowIdColumn): array
    {
        $matchColumn = self::identifier((string) ($trigger['match_column'] ?? $rowIdColumn), 'trigger match column');
        $matchValue = self::triggerValue($trigger['match_value'] ?? 'new.next_id', $old, $new);
        $targets = [];
        foreach ($parents as $index => $row) {
            if (($row[$matchColumn] ?? null) === $matchValue) {
                $targets[] = $index;
            }
        }

        return $targets;
    }

    private static function triggerValue(mixed $value, array $old, array $new): mixed
    {
        if ($value === 'old.option_id') {
            return $old['option_id'] ?? null;
        }
        if ($value === 'new.option_id') {
            return $new['option_id'] ?? null;
        }
        if ($value === 'old.next_id') {
            return $old['next_id'] ?? null;
        }
        if ($value === 'new.next_id') {
            return $new['next_id'] ?? null;
        }
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return $old[substr($value, 4)] ?? null;
        }
        if (is_string($value) && str_starts_with($value, 'new.')) {
            return $new[substr($value, 4)] ?? null;
        }

        return $value;
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update:string,deferred:bool} $spec
     * @return array<string,mixed>
     */
    private static function applyNoAction(array $old, array $new, array $children, array $spec): array
    {
        return [
            'event' => 'update',
            'action' => $spec['on_update'],
            'from' => $old[$spec['parent_key']] ?? null,
            'to' => $new[$spec['parent_key']] ?? null,
            'matching_children' => count(array_filter($children, static fn (array $child): bool => ($child[$spec['child_key']] ?? null) == ($old[$spec['parent_key']] ?? null))),
        ];
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update:string,deferred:bool} $spec
     * @return list<array<string,mixed>>
     */
    private static function foreignKeyActions(array $parents, array $children, array $spec): array
    {
        $actions = [];
        foreach ($children as $index => $child) {
            $key = $child[$spec['child_key']] ?? null;
            if ($key !== null && !in_array($key, array_column($parents, $spec['parent_key']), true)) {
                $actions[] = ['event' => 'update', 'action' => $spec['on_update'], 'child_index' => $index, 'child_key' => $key];
            }
        }

        return $actions;
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update:string,deferred:bool} $spec
     * @return list<array<string,mixed>>
     */
    private static function foreignKeyViolations(array $parents, array $children, array $spec): array
    {
        $keys = array_column($parents, $spec['parent_key']);
        $violations = [];
        foreach ($children as $index => $child) {
            $key = $child[$spec['child_key']] ?? null;
            if ($key === null || in_array($key, $keys, true)) {
                continue;
            }
            $violations[] = ['child_index' => $index, 'child_key' => $key, 'parent' => $spec['parent_key'], 'phase' => 'deferred-commit'];
        }

        return $violations;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string):mixed>|null $projection
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
                $alias = str_contains($entry, '.') ? substr($entry, (int) strrpos($entry, '.') + 1) : $entry;
                $out[self::identifier($alias, 'RETURNING alias')] = self::rowValue($entry, $old, $row);
            } elseif (is_array($entry)) {
                $expr = (string) ($entry['expr'] ?? '');
                $alias = (string) ($entry['as'] ?? (str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr));
                $out[self::identifier($alias, 'RETURNING alias')] = self::rowValue($expr, $old, $row);
            } elseif (is_callable($entry)) {
                $out['expr' . $index] = $entry($row, $old, $event);
            } else {
                throw new \InvalidArgumentException('SQLite recursive trigger RETURNING deferred FK projection is malformed');
            }
        }

        return $out;
    }

    private static function rowValue(string $expr, array $old, array $row): mixed
    {
        if (str_starts_with($expr, 'old.')) {
            return $old[substr($expr, 4)] ?? throw new \InvalidArgumentException('SQLite recursive trigger RETURNING deferred FK OLD column is missing');
        }
        if (str_starts_with($expr, 'new.')) {
            return $row[substr($expr, 4)] ?? throw new \InvalidArgumentException('SQLite recursive trigger RETURNING deferred FK NEW column is missing');
        }

        return $row[$expr] ?? throw new \InvalidArgumentException('SQLite recursive trigger RETURNING deferred FK column is missing');
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string):mixed>|null $returning
     * @return array<string,mixed>
     */
    private static function yieldRow(int $ordinal, array $old, array $new, ?array $returning, string $rowIdColumn, int $depth): array
    {
        return [
            'ordinal' => $ordinal,
            'event' => 'update',
            'depth' => $depth,
            'old_key' => $old[$rowIdColumn] ?? null,
            'new_key' => $new[$rowIdColumn] ?? null,
            'returning' => self::returningRow($returning, $old, $new, 'update'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function effectRow(string $trigger, array $old, array $new, int $depth, array $action): array
    {
        return [
            'trigger' => $trigger,
            'timing' => 'after',
            'event' => 'update',
            'depth' => $depth,
            'old_key' => $old['option_id'] ?? null,
            'new_key' => $new['option_id'] ?? null,
            'foreign_key_action' => $action,
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function pageImages(array $pages, string $label): array
    {
        $out = [];
        foreach ($pages as $pageNumber => $bytes) {
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($bytes) || $bytes === '') {
                throw new \InvalidArgumentException("SQLite recursive trigger RETURNING deferred FK {$label} is malformed");
            }
            $out[$pageNumber] = $bytes;
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function walFrames(array $frames): array
    {
        $out = [];
        foreach ($frames as $frame) {
            if (!is_array($frame)) {
                throw new \InvalidArgumentException('SQLite recursive trigger RETURNING deferred FK WAL frame is malformed');
            }
            $index = self::nonNegativeInt($frame['frame_index'] ?? null, 'WAL frame index');
            if ($index < 1) {
                throw new \InvalidArgumentException('SQLite recursive trigger RETURNING deferred FK WAL frame index is malformed');
            }
            $out[] = $frame + ['frame_index' => $index];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return list<array<string,mixed>>
     */
    private static function discardedWalFrames(array $frames, int $start): array
    {
        return array_values(array_filter($frames, static fn (array $frame): bool => (int) $frame['frame_index'] > $start));
    }

    /**
     * @return list<mixed>
     */
    private static function rowIds(array $rows, string $column): array
    {
        return array_values(array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows));
    }

    /**
     * @param array{parent_key:string,child_key:string,on_update?:string,deferred?:bool} $foreignKey
     * @return array{parent_key:string,child_key:string,on_update:string,deferred:bool}
     */
    private static function foreignKeySpec(array $foreignKey): array
    {
        return [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key'),
            'on_update' => self::fkAction((string) ($foreignKey['on_update'] ?? 'no action')),
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
        ];
    }

    private static function fkAction(string $action): string
    {
        return match (strtolower(trim($action))) {
            'no action', 'no-action' => 'no action',
            'restrict' => 'restrict',
            default => throw new \InvalidArgumentException('SQLite recursive trigger RETURNING deferred FK action is unsupported'),
        };
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite recursive trigger RETURNING deferred FK {$label} is malformed");
        }

        return $value;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite recursive trigger RETURNING deferred FK {$label} is malformed");
        }

        return $value;
    }
}
