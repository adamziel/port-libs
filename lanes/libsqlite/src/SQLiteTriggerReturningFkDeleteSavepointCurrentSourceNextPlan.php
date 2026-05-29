<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerReturningFkDeleteSavepointCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_delete?:string,deferred?:bool} $foreignKey
     * @param array{where:callable(array<string,mixed>):bool,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,string):mixed>,savepoint?:string,rowid_column?:string,before_triggers?:list<array<string,mixed>>,after_triggers?:list<array<string,mixed>>,rollback_on_deferred_violation?:bool,page_images?:array<int,string>,dirty_pages?:array<int,string>,wal_start_frame?:int,wal_frames?:list<array<string,mixed>>} $statement
     * @return array<string,mixed>
     */
    public static function execute(array $parents, array $children, array $foreignKey, array $statement): array
    {
        $savepoint = self::identifier((string) ($statement['savepoint'] ?? 'delete_returning_fk'), 'savepoint');
        $rowIdColumn = self::identifier((string) ($statement['rowid_column'] ?? 'option_id'), 'rowid column');
        $where = $statement['where'] ?? null;
        if (!is_callable($where)) {
            throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK WHERE callback is required');
        }

        $spec = self::foreignKeySpec($foreignKey);
        $parents = array_values($parents);
        $children = array_values($children);
        if ($parents === []) {
            throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK parent rows are required');
        }
        $originalParents = $parents;
        $originalChildren = $children;
        $returning = isset($statement['returning']) ? (array) $statement['returning'] : null;
        $beforeTriggers = self::triggerList((array) ($statement['before_triggers'] ?? []), 'before');
        $afterTriggers = self::triggerList((array) ($statement['after_triggers'] ?? []), 'after');

        $currentYielded = [];
        $triggerEffects = [];
        $foreignKeyActions = [];
        $ignoredRowids = [];
        $deletedRowids = [];
        $rollbackReason = null;

        foreach ($parents as $index => $row) {
            if (!$where($row)) {
                continue;
            }

            $before = self::runTriggers($beforeTriggers, $row, $children, $spec, 'before-delete');
            $triggerEffects = array_merge($triggerEffects, $before['effects']);
            if ($before['ignore']) {
                $ignoredRowids[] = $row[$rowIdColumn] ?? null;
                continue;
            }
            if ($before['rollback'] !== null) {
                $rollbackReason = $before['rollback'];
                break;
            }

            unset($parents[$index]);
            $deletedRowids[] = $row[$rowIdColumn] ?? null;
            $action = self::applyDeleteAction($children, $row, $spec);
            $children = $action['children'];
            if ($action['action'] !== 'none') {
                $foreignKeyActions[] = [
                    'event' => 'delete',
                    'action' => $action['action'],
                    'parent_key' => $row[$spec['parent_key']] ?? null,
                    'child_indexes' => $action['child_indexes'],
                ];
            }
            if ($action['rollback'] !== null) {
                $rollbackReason = $action['rollback'];
            }

            $currentYielded[] = [
                'event' => 'delete',
                'rowid' => $row[$rowIdColumn] ?? null,
                'returning' => self::returningRow($returning, $row, 'delete'),
            ];

            $after = self::runTriggers($afterTriggers, $row, $children, $spec, 'after-delete');
            $triggerEffects = array_merge($triggerEffects, $after['effects']);
            $children = $after['children'];
            if ($after['rollback'] !== null) {
                $rollbackReason = $after['rollback'];
            }

            if ($rollbackReason !== null) {
                break;
            }
        }
        $parents = array_values($parents);
        $violations = self::foreignKeyViolations($parents, $children, $spec);
        $deferredRollback = (bool) ($statement['rollback_on_deferred_violation'] ?? false) && $spec['deferred'] && $violations !== [];
        $rollback = $rollbackReason !== null || $deferredRollback;
        $pageImages = self::pageImages((array) ($statement['page_images'] ?? []), 'page image');
        $dirtyPages = self::pageImages((array) ($statement['dirty_pages'] ?? []), 'dirty page');
        $walStart = self::nonNegativeInt($statement['wal_start_frame'] ?? 0, 'WAL start frame');
        $walFrames = self::walFrames((array) ($statement['wal_frames'] ?? []));

        return [
            'status' => $rollback ? 'rolled-back' : ($violations === [] ? 'commit-ok' : 'deferred-commit-blocked'),
            'savepoint' => $savepoint,
            'current_parent' => $parents,
            'current_child' => $children,
            'next_parent' => $rollback ? $originalParents : $parents,
            'next_child' => $rollback ? $originalChildren : $children,
            'current_rowids' => self::rowIds($parents, $rowIdColumn),
            'next_rowids' => self::rowIds($rollback ? $originalParents : $parents, $rowIdColumn),
            'deleted_rowids' => $deletedRowids,
            'ignored_rowids' => $ignoredRowids,
            'current_yielded' => $currentYielded,
            'current_returning_rows' => array_values(array_map(static fn (array $row): array => $row['returning'], $currentYielded)),
            'next_returning_rows' => $rollback ? [] : array_values(array_map(static fn (array $row): array => $row['returning'], $currentYielded)),
            'yield_suppressed_by_rollback' => $rollback && $currentYielded !== [],
            'foreign_key_actions' => $foreignKeyActions,
            'foreign_key_violations' => $violations,
            'trigger_effects' => $triggerEffects,
            'rollback_reason' => $rollbackReason ?? ($deferredRollback ? 'deferred-foreign-key-violation' : null),
            'current_changes' => count($deletedRowids) + count($foreignKeyActions) + count($triggerEffects),
            'next_changes' => $rollback ? 0 : count($deletedRowids) + count($foreignKeyActions) + count($triggerEffects),
            'current_next_boundary' => $rollback ? 'rollback-to-savepoint' : ($violations === [] ? 'release-savepoint' : 'deferred-commit-blocked'),
            'rollback_page_numbers' => $rollback ? array_values(array_unique(array_merge(array_keys($pageImages), array_keys($dirtyPages)))) : [],
            'restored_page_images' => $rollback ? $pageImages : [],
            'dirty_page_numbers' => $rollback ? array_keys($dirtyPages) : [],
            'rollback_to_wal_frame' => $rollback ? $walStart : 0,
            'discarded_wal_frames' => $rollback ? self::discardedWalFrames($walFrames, $walStart) : [],
            'dependencies' => [
                'sqlite-delete-returning-top-level-yield',
                'sqlite-trigger-before-after-delete',
                'sqlite-foreign-key-on-delete-actions',
                'sqlite-savepoint-current-source-next-rollback',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_delete:string,deferred:bool} $spec
     * @return array{children:list<array<string,mixed>>,action:string,child_indexes:list<int>,rollback:?string}
     */
    private static function applyDeleteAction(array $children, array $parent, array $spec): array
    {
        $parentKey = $parent[$spec['parent_key']] ?? null;
        $matched = [];
        foreach ($children as $index => $child) {
            if (($child[$spec['child_key']] ?? null) === $parentKey) {
                $matched[] = $index;
            }
        }
        if ($matched === []) {
            return ['children' => $children, 'action' => 'none', 'child_indexes' => [], 'rollback' => null];
        }
        if ($spec['on_delete'] === 'cascade') {
            foreach (array_reverse($matched) as $index) {
                unset($children[$index]);
            }
            return ['children' => array_values($children), 'action' => 'cascade', 'child_indexes' => $matched, 'rollback' => null];
        }
        if ($spec['on_delete'] === 'set null') {
            foreach ($matched as $index) {
                $children[$index][$spec['child_key']] = null;
            }
            return ['children' => array_values($children), 'action' => 'set null', 'child_indexes' => $matched, 'rollback' => null];
        }
        if ($spec['on_delete'] === 'restrict' || (!$spec['deferred'] && $spec['on_delete'] === 'no action')) {
            return ['children' => $children, 'action' => $spec['on_delete'], 'child_indexes' => $matched, 'rollback' => $spec['on_delete'] . '-foreign-key'];
        }

        return ['children' => $children, 'action' => 'no action', 'child_indexes' => $matched, 'rollback' => null];
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_delete:string,deferred:bool} $spec
     * @return array{effects:list<array<string,mixed>>,children:list<array<string,mixed>>,ignore:bool,rollback:?string}
     */
    private static function runTriggers(array $triggers, array $old, array $children, array $spec, string $phase): array
    {
        $effects = [];
        $ignore = false;
        $rollback = null;
        foreach ($triggers as $trigger) {
            $when = $trigger['when'] ?? null;
            if (is_callable($when) && !$when($old)) {
                continue;
            }
            $action = (string) ($trigger['action'] ?? 'log');
            $name = (string) $trigger['name'];
            if ($action === 'ignore') {
                $ignore = true;
                $effects[] = ['trigger' => $name, 'phase' => $phase, 'action' => 'raise-ignore', 'old_key' => $old[$spec['parent_key']] ?? null];
                break;
            }
            if ($action === 'rollback') {
                $rollback = 'trigger-rollback:' . $name;
                $effects[] = ['trigger' => $name, 'phase' => $phase, 'action' => 'raise-rollback', 'old_key' => $old[$spec['parent_key']] ?? null];
                break;
            }
            if ($action === 'insert_child') {
                $row = (array) ($trigger['row'] ?? []);
                if ($row === []) {
                    throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK insert_child row is required');
                }
                foreach ($row as $column => $value) {
                    $row[(string) $column] = self::triggerValue($value, $old);
                }
                $children[] = $row;
                $effects[] = ['trigger' => $name, 'phase' => $phase, 'action' => 'insert-child', 'old_key' => $old[$spec['parent_key']] ?? null, 'child_key' => $row[$spec['child_key']] ?? null];
                continue;
            }
            $effects[] = ['trigger' => $name, 'phase' => $phase, 'action' => 'log', 'old_key' => $old[$spec['parent_key']] ?? null];
        }

        return ['effects' => $effects, 'children' => array_values($children), 'ignore' => $ignore, 'rollback' => $rollback];
    }

    private static function triggerValue(mixed $value, array $old): mixed
    {
        if (is_string($value) && str_starts_with($value, 'old.')) {
            return $old[substr($value, 4)] ?? null;
        }

        return $value;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,string):mixed>|null $projection
     * @return array<string,mixed>
     */
    private static function returningRow(?array $projection, array $old, string $event): array
    {
        if ($projection === null) {
            return $old;
        }
        $out = [];
        foreach ($projection as $index => $entry) {
            if (is_string($entry)) {
                $alias = str_contains($entry, '.') ? substr($entry, (int) strrpos($entry, '.') + 1) : $entry;
                $out[self::identifier($alias, 'RETURNING alias')] = self::rowValue($entry, $old);
            } elseif (is_array($entry)) {
                $expr = (string) ($entry['expr'] ?? '');
                $alias = (string) ($entry['as'] ?? (str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr));
                $out[self::identifier($alias, 'RETURNING alias')] = self::rowValue($expr, $old);
            } elseif (is_callable($entry)) {
                $out['expr' . $index] = $entry($old, $event);
            } else {
                throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK projection entry is invalid');
            }
        }

        return $out;
    }

    private static function rowValue(string $expr, array $old): mixed
    {
        if (str_starts_with($expr, 'old.')) {
            return $old[substr($expr, 4)] ?? null;
        }
        if (str_starts_with($expr, 'new.')) {
            throw new \InvalidArgumentException('SQLite DELETE RETURNING cannot project NEW rows');
        }

        return $old[$expr] ?? null;
    }

    /**
     * @param array{parent_key:string,child_key:string,on_delete?:string,deferred?:bool} $foreignKey
     * @return array{parent_key:string,child_key:string,on_delete:string,deferred:bool}
     */
    private static function foreignKeySpec(array $foreignKey): array
    {
        $action = strtolower(trim((string) ($foreignKey['on_delete'] ?? 'no action')));
        if (!in_array($action, ['cascade', 'set null', 'no action', 'restrict'], true)) {
            throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK on_delete action is invalid');
        }

        return [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'foreign key parent column'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'foreign key child column'),
            'on_delete' => $action,
            'deferred' => (bool) ($foreignKey['deferred'] ?? false),
        ];
    }

    /**
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
            $violations[] = ['child_index' => $index, 'child_key' => $key, 'parent' => $spec['parent_key'], 'phase' => $spec['deferred'] ? 'deferred-commit' : 'statement'];
        }

        return $violations;
    }

    /**
     * @return list<array{name:string,action:string,when?:callable(array<string,mixed>):bool,row?:array<string,mixed>}>
     */
    private static function triggerList(array $triggers, string $phase): array
    {
        $out = [];
        foreach ($triggers as $trigger) {
            if (!is_array($trigger)) {
                throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK trigger must be an array');
            }
            $trigger['name'] = self::identifier((string) ($trigger['name'] ?? ''), $phase . ' trigger');
            $action = (string) ($trigger['action'] ?? 'log');
            if (!in_array($action, ['log', 'ignore', 'rollback', 'insert_child'], true)) {
                throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK trigger action is invalid');
            }
            $trigger['action'] = $action;
            $out[] = $trigger;
        }

        return $out;
    }

    /**
     * @return array<int,string>
     */
    private static function pageImages(array $pages, string $label): array
    {
        $out = [];
        foreach ($pages as $pageNumber => $image) {
            $number = self::positiveInt($pageNumber, $label . ' number');
            if (!is_string($image) || strlen($image) < 64) {
                throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK ' . $label . ' must be a page image');
            }
            $out[$number] = $image;
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
                throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK WAL frame must be an array');
            }
            $frame['frame_index'] = self::positiveInt($frame['frame_index'] ?? 0, 'WAL frame index');
            $frame['page_number'] = self::positiveInt($frame['page_number'] ?? 0, 'WAL page number');
            $frame['commit_frame'] = (bool) ($frame['commit_frame'] ?? false);
            $out[] = $frame;
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
    private static function rowIds(array $rows, string $rowIdColumn): array
    {
        return array_values(array_map(static fn (array $row): mixed => $row[$rowIdColumn] ?? null, $rows));
    }

    private static function identifier(string $value, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK invalid ' . $label);
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK invalid ' . $label);
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite trigger DELETE RETURNING FK invalid ' . $label);
        }

        return $value;
    }
}
