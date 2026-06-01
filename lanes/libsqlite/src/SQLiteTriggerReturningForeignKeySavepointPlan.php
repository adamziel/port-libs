<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerReturningForeignKeySavepointPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update?:string,on_delete?:string,deferred?:bool} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param array{operation:string,where:callable(array<string,mixed>):bool,assignments?:array<string,mixed|callable(array<string,mixed>):mixed>,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,string):mixed>,savepoint?:string,rollback_on_deferred_violation?:bool,page_images?:array<int,string>,dirty_pages?:array<int,string>,wal_start_frame?:int,wal_frames?:list<array<string,mixed>>,rowid_column?:string} $statement
     * @return array{status:string,savepoint:string,operation:string,parent:list<array<string,mixed>>,child:list<array<string,mixed>>,attempted_parent:list<array<string,mixed>>,attempted_child:list<array<string,mixed>>,yielded:list<array<string,mixed>>,attempted_yielded:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,foreign_key_actions:list<array<string,mixed>>,foreign_key_violations:list<array<string,mixed>>,changes:int,attempted_changes:int,rolled_back_to_savepoint:bool,rollback_reason:?string,rollback_page_numbers:list<int>,restored_page_images:array<int,string>,dirty_page_numbers:list<int>,rollback_to_wal_frame:int,discarded_wal_frames:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function run(array $parents, array $children, array $foreignKey, array $triggers, array $statement): array
    {
        $savepoint = self::identifier((string) ($statement['savepoint'] ?? 'trigger_returning_fk'), 'savepoint');
        $operation = strtolower((string) ($statement['operation'] ?? ''));
        $where = $statement['where'] ?? null;
        if (!is_callable($where)) {
            throw new \InvalidArgumentException('SQLite trigger RETURNING/FK savepoint WHERE callback is required');
        }
        $returning = isset($statement['returning']) ? (array) $statement['returning'] : null;
        $rowIdColumn = self::identifier((string) ($statement['rowid_column'] ?? 'setting_id'), 'rowid column');

        if ($operation === 'update') {
            $attempt = SQLiteTriggerForeignKeyReturningPlan::updateParents(
                $parents,
                $children,
                (array) ($statement['assignments'] ?? []),
                $where,
                $foreignKey,
                $triggers,
                $returning,
                $rowIdColumn,
            );
        } elseif ($operation === 'delete') {
            $attempt = SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                $parents,
                $children,
                $where,
                $foreignKey,
                $triggers,
                $returning,
                $rowIdColumn,
            );
        } else {
            throw new \InvalidArgumentException('SQLite trigger RETURNING/FK savepoint operation is unsupported');
        }

        $violations = $attempt['foreign_key_violations'];
        $rollback = (bool) ($statement['rollback_on_deferred_violation'] ?? false) && $violations !== [];
        $pageImages = self::pageImages((array) ($statement['page_images'] ?? []), 'page image');
        $dirtyPages = self::pageImages((array) ($statement['dirty_pages'] ?? []), 'dirty page');
        $walStart = self::nonNegativeInt($statement['wal_start_frame'] ?? 0, 'WAL start frame');
        $walFrames = self::walFrames((array) ($statement['wal_frames'] ?? []));

        return [
            'status' => $violations === [] ? 'commit-ok' : ($rollback ? 'rolled-back' : 'commit-blocked'),
            'savepoint' => $savepoint,
            'operation' => $operation,
            'parent' => $rollback ? array_values($parents) : $attempt['parent'],
            'child' => $rollback ? array_values($children) : $attempt['child'],
            'attempted_parent' => $attempt['parent'],
            'attempted_child' => $attempt['child'],
            'yielded' => $rollback ? [] : $attempt['yielded'],
            'attempted_yielded' => $attempt['yielded'],
            'trigger_effects' => $attempt['trigger_effects'],
            'foreign_key_actions' => $attempt['foreign_key_actions'],
            'foreign_key_violations' => $violations,
            'changes' => $rollback ? 0 : $attempt['changes'],
            'attempted_changes' => $attempt['changes'],
            'rolled_back_to_savepoint' => $rollback,
            'rollback_reason' => $rollback ? 'deferred-foreign-key-violation' : null,
            'rollback_page_numbers' => $rollback ? array_values(array_unique(array_merge(array_keys($pageImages), array_keys($dirtyPages)))) : [],
            'restored_page_images' => $rollback ? $pageImages : [],
            'dirty_page_numbers' => $rollback ? array_keys($dirtyPages) : [],
            'rollback_to_wal_frame' => $rollback ? $walStart : 0,
            'discarded_wal_frames' => $rollback ? self::discardedWalFrames($walFrames, $walStart) : [],
            'dependencies' => [
                'sqlite-trigger-returning-current-row',
                'sqlite-foreign-key-deferred-check',
                'sqlite-savepoint-current-rollback',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update?:string,on_delete?:string,deferred?:bool,child_default?:mixed,child_defaults?:array<string,mixed>} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param array{operation:string,where:callable(array<string,mixed>):bool,assignments?:array<string,mixed|callable(array<string,mixed>):mixed>,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,string):mixed>,savepoint?:string,rollback_on_deferred_violation?:bool,page_images?:array<int,string>,dirty_pages?:array<int,string>,wal_start_frame?:int,wal_frames?:list<array<string,mixed>>,rowid_column?:string} $statement
     * @return array<string,mixed>
     */
    public static function savepointBoundaryYield(array $parents, array $children, array $foreignKey, array $triggers, array $statement): array
    {
        $plan = self::run($parents, $children, $foreignKey, $triggers, $statement);
        $currentYielded = $plan['attempted_yielded'];
        $nextYielded = $plan['yielded'];
        $rolledBack = $plan['rolled_back_to_savepoint'];

        $out = $plan + [
            'current_parent' => $plan['attempted_parent'],
            'current_child' => $plan['attempted_child'],
            'next_parent' => $plan['parent'],
            'next_child' => $plan['child'],
            'current_yielded' => $currentYielded,
            'next_yielded' => $nextYielded,
            'current_returning_rows' => array_values(array_map(static fn (array $row): array => $row['returning'], $currentYielded)),
            'next_returning_rows' => array_values(array_map(static fn (array $row): array => $row['returning'], $nextYielded)),
            'current_changes' => $plan['attempted_changes'],
            'next_changes' => $plan['changes'],
            'yield_suppressed_by_rollback' => $rolledBack && $currentYielded !== [] && $nextYielded === [],
            'current_next_boundary' => $rolledBack ? 'rollback-to-savepoint' : ($plan['foreign_key_violations'] === [] ? 'commit' : 'deferred-commit-blocked'),
            'next_restored_savepoint_image' => $rolledBack && $plan['parent'] === array_values($parents) && $plan['child'] === array_values($children),
            'current_rowids' => self::rowIds($plan['attempted_parent'], (string) ($statement['rowid_column'] ?? 'setting_id')),
            'next_rowids' => self::rowIds($plan['parent'], (string) ($statement['rowid_column'] ?? 'setting_id')),
        ];
        $out['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-trigger-returning-savepoint-current-next64']
        )));

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,on_update?:string,on_delete?:string,deferred?:bool,child_default?:mixed,child_defaults?:array<string,mixed>} $foreignKey
     * @param list<array<string,mixed>> $triggers
     * @param array{operation:string,where:callable(array<string,mixed>):bool,assignments?:array<string,mixed|callable(array<string,mixed>):mixed>,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,string):mixed>,savepoint?:string,rollback_on_deferred_violation?:bool,page_images?:array<int,string>,dirty_pages?:array<int,string>,wal_start_frame?:int,wal_frames?:list<array<string,mixed>>,rowid_column?:string} $statement
     * @return array<string,mixed>
     */
    public static function currentNextYield(array $parents, array $children, array $foreignKey, array $triggers, array $statement): array
    {
        return self::savepointBoundaryYield($parents, $children, $foreignKey, $triggers, $statement);
    }

    /**
     * @return array<int,string>
     */
    private static function pageImages(array $pages, string $label): array
    {
        $out = [];
        foreach ($pages as $pageNumber => $bytes) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite trigger RETURNING/FK savepoint {$label} number is malformed");
            }
            if (!is_string($bytes) || $bytes === '') {
                throw new \InvalidArgumentException("SQLite trigger RETURNING/FK savepoint {$label} bytes are malformed");
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
                throw new \InvalidArgumentException('SQLite trigger RETURNING/FK savepoint WAL frame is malformed');
            }
            $index = self::nonNegativeInt($frame['frame_index'] ?? null, 'WAL frame index');
            if ($index < 1) {
                throw new \InvalidArgumentException('SQLite trigger RETURNING/FK savepoint WAL frame index is malformed');
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
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function rowIds(array $rows, string $column): array
    {
        self::identifier($column, 'rowid column');

        return array_values(array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows));
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite trigger RETURNING/FK savepoint {$label} is malformed");
        }

        return $value;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger RETURNING/FK savepoint {$label} is malformed");
        }

        return $value;
    }
}
