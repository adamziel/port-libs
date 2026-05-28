<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext163Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $nextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_next_source?:bool,max_depth?:int,savepoint?:string,current_generation?:string,next_generation?:string,trigger_child_prefix?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentRoots,
        array $nextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $prefix = self::token((string) ($options['trigger_child_prefix'] ?? 'audit-child'), 'trigger child prefix');
        $releaseNext = (bool) ($options['release_next_source'] ?? false);
        $currentRows = self::normalizeRows($rows);
        $currentView = self::normalizeView($currentView, 'current view');
        $nextView = self::normalizeView($nextView, 'next view');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext160Plan::execute(
            $currentRows,
            $currentRoots,
            $nextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => $releaseNext,
                'max_depth' => $options['max_depth'] ?? 8,
                'savepoint' => $options['savepoint'] ?? 'wp_recursive_view_returning_next163',
                'current_generation' => $options['current_generation'] ?? 'current-source-next163',
                'next_generation' => $options['next_generation'] ?? 'next-source-next163',
            ],
        );

        $generatedRows = self::triggerGeneratedChildren($base['current_returning_rows'], $prefix, $currentView);
        $currentNames = self::names($base['current_recursive_rows']);
        $generatedNames = self::names($generatedRows);
        $suppressed = array_values(array_intersect($generatedNames, $currentNames));
        $seededNextRows = self::releasedNextRows($currentRows, $base, $generatedRows, $nextRoots, $nextView, $returning);
        $seededNextNames = self::names($seededNextRows['recursive_rows']);

        $base['status'] = $releaseNext
            ? 'trigger-recursive-view-returning-snapshot-release-next163'
            : 'trigger-recursive-view-returning-snapshot-barrier-next163';
        $base['trigger_generated_rows'] = $generatedRows;
        $base['trigger_generated_names'] = $generatedNames;
        $base['current_snapshot_guard'] = [
            'source' => $base['source_barrier']['current_source'],
            'snapshot_taken_before_trigger_writes' => true,
            'generated_rows' => count($generatedRows),
            'generated_names' => $generatedNames,
            'current_recursive_names' => $currentNames,
            'reentrant_visible_names' => $suppressed,
            'reentrant_suppressed' => $suppressed === [],
            'reason' => 'current recursive view source is materialized before INSTEAD OF trigger RETURNING rows are drained',
        ];
        $base['next_source_seed'] = [
            'released' => $releaseNext,
            'source' => $base['source_barrier']['next_source'],
            'seeded_recursive_rows' => $releaseNext ? count($seededNextRows['recursive_rows']) : 0,
            'seeded_names' => $releaseNext ? $seededNextNames : [],
            'seeded_returning_keys' => $releaseNext ? array_column($seededNextRows['returning_rows'], 'visibility_key') : [],
            'seeded_changes' => $releaseNext ? count($seededNextRows['returning_rows']) : 0,
        ];
        $base['statement_rows'] += $releaseNext ? count($seededNextRows['returning_rows']) : 0;
        $base['changes'] += $releaseNext ? count($seededNextRows['returning_rows']) : 0;
        $base['next_returning_rows'] = $releaseNext ? array_merge($base['next_returning_rows'], $seededNextRows['returning_rows']) : $base['next_returning_rows'];
        $base['returning_visibility']['visible'] = $releaseNext
            ? array_merge($base['returning_visibility']['visible'], array_column($seededNextRows['returning_rows'], 'visibility_key'))
            : $base['returning_visibility']['visible'];
        $base['yield_boundary'] = $releaseNext
            ? 'current-source-snapshot-drained-trigger-writes-seed-released-next-source-next163'
            : 'current-source-snapshot-drained-trigger-writes-held-from-recursive-reentry-next163';
        $base['dependencies'] = array_values(array_unique(array_merge($base['dependencies'], [
            'sqlite-trigger-recursive-view-returning-current-source-next163',
            'sqlite-recursive-view-source-snapshot-before-trigger-writes',
            'sqlite-trigger-generated-rows-seed-next-source-only-after-release',
        ])));

        return $base;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function normalizeRows(array $rows): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite trigger recursive view next163 rows must be a list');
        }

        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('option_name', $row)) {
                throw new InvalidArgumentException('SQLite trigger recursive view next163 row option_name is missing');
            }
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,order_by:?string}
     */
    private static function normalizeView(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite trigger recursive view next163 {$label} columns must be a non-empty list");
        }

        return [
            'name' => self::identifier((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::token((string) ($view['source'] ?? ''), $label . ' source'),
            'trigger' => self::identifier((string) ($view['trigger'] ?? ''), $label . ' trigger'),
            'trigger_source' => self::token((string) ($view['trigger_source'] ?? ''), $label . ' trigger source'),
            'root_key' => self::identifier((string) ($view['root_key'] ?? ''), $label . ' root key'),
            'parent_key' => self::identifier((string) ($view['parent_key'] ?? ''), $label . ' parent key'),
            'columns' => array_map(static fn (mixed $column): string => self::identifier((string) $column, $label . ' column'), $columns),
            'order_by' => isset($view['order_by']) ? self::identifier((string) $view['order_by'], $label . ' order column') : null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $returningRows
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,order_by:?string} $view
     * @return list<array<string,mixed>>
     */
    private static function triggerGeneratedChildren(array $returningRows, string $prefix, array $view): array
    {
        $out = [];
        foreach ($returningRows as $ordinal => $row) {
            $returning = $row['returning'] ?? null;
            if (!is_array($returning) || !array_key_exists('option_name', $returning)) {
                throw new InvalidArgumentException('SQLite trigger recursive view next163 RETURNING row option_name is missing');
            }
            $parent = (string) $returning['option_name'];
            $name = $prefix . ':' . $parent;
            $out[] = [
                'option_name' => $name,
                'option_value' => 'generated-from:' . $parent,
                'autoload' => $returning['autoload'] ?? 'no',
                $view['parent_key'] => $parent,
                'priority' => 1000 + $ordinal,
                'source' => $view['trigger_source'],
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $baseRows
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $generatedRows
     * @param list<array<string,mixed>> $nextRoots
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,parent_key:string,columns:list<string>,order_by:?string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array{recursive_rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>}
     */
    private static function releasedNextRows(array $baseRows, array $base, array $generatedRows, array $nextRoots, array $nextView, array $returning): array
    {
        if ($generatedRows === []) {
            return ['recursive_rows' => [], 'returning_rows' => []];
        }

        $rows = array_merge($baseRows, $generatedRows);
        $probe = SQLiteTriggerRecursiveViewReturningCurrentSourceNext160Plan::execute(
            $rows,
            [],
            $nextRoots,
            $nextView,
            $nextView,
            $returning,
            [
                'release_next_source' => true,
                'max_depth' => 1,
                'savepoint' => (string) $base['savepoint'] . '_seed',
                'current_generation' => (string) $base['current_generation'] . '-seed',
                'next_generation' => (string) $base['next_generation'] . '-seed',
            ],
        );

        $generated = array_flip(self::names($generatedRows));
        $recursiveRows = [];
        foreach ($probe['attempted_next_recursive_rows'] as $ordinal => $row) {
            if (!isset($generated[(string) ($row['option_name'] ?? '')])) {
                continue;
            }
            $row['_seed_ordinal'] = $ordinal;
            $recursiveRows[] = $row;
        }
        $returningRows = [];
        foreach ($recursiveRows as $recursiveRow) {
            $ordinal = (int) ($recursiveRow['_seed_ordinal'] ?? -1);
            $row = $probe['attempted_next_returning_rows'][$ordinal] ?? null;
            $returningRow = is_array($row) ? ($row['returning'] ?? []) : [];
            if (!is_array($row) || !is_array($returningRow)) {
                continue;
            }
            $row['source_generation'] = $base['next_generation'];
            $row['visibility'] = 'next-returning-released-from-trigger-generated-seed';
            $row['visible_to_statement'] = true;
            $row['visibility_key'] = (string) $base['next_generation'] . ':' . (string) ($returningRow['option_name'] ?? $recursiveRow['option_name']);
            $returningRows[] = $row;
        }

        return ['recursive_rows' => array_map(static function (array $row): array {
            unset($row['_seed_ordinal']);
            return $row;
        }, $recursiveRows), 'returning_rows' => $returningRows];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function names(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['option_name'] ?? ''), $rows));
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next163 {$label} is malformed");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next163 {$label} is malformed");
        }

        return $value;
    }
}
