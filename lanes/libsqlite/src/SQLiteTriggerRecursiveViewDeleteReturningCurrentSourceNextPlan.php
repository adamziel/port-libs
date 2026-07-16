<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $nextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int,int):mixed> $returning
     * @param array{key?:string,parent_key?:string,savepoint?:string,max_depth?:int,release_current?:bool,admit_next_source?:bool,recursive_triggers?:bool,blocked_key?:string} $options
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
        $key = self::identifier((string) ($options['key'] ?? 'key_name'), 'key');
        $parentKey = self::identifier((string) ($options['parent_key'] ?? 'parent_key_name'), 'parent key');
        $savepoint = self::token((string) ($options['savepoint'] ?? 'app_recursive_view_delete_returning_168'), 'savepoint');
        $maxDepth = (int) ($options['max_depth'] ?? 4);
        if ($maxDepth < 0) {
            throw new InvalidArgumentException('SQLite recursive view DELETE RETURNING next168 max depth must be non-negative');
        }
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite recursive view DELETE RETURNING next168 projection cannot be empty');
        }

        $releaseCurrent = (bool) ($options['release_current'] ?? false);
        $admitNext = (bool) ($options['admit_next_source'] ?? false);
        $recursive = (bool) ($options['recursive_triggers'] ?? true);
        $blockedKey = array_key_exists('blocked_key', $options) ? (string) $options['blocked_key'] : null;

        $baseRows = self::normalizeRows($rows, $key);
        $currentView = self::normalizeView($currentView, 'current view');
        $nextView = self::normalizeView($nextView, 'next view');

        $current = self::deleteFromView($baseRows, $currentRoots, $currentView, $returning, $key, $parentKey, $maxDepth, $recursive, $blockedKey, 'current');
        $afterCurrent = $releaseCurrent && !$current['rolled_back'] ? $current['rows'] : $baseRows;
        $next = self::deleteFromView($afterCurrent, $nextRoots, $nextView, $returning, $key, $parentKey, $maxDepth, $recursive, null, 'next');

        return [
            'status' => $admitNext
                ? 'trigger-recursive-view-delete-returning-next-source-admitted-next168'
                : ($releaseCurrent && !$current['rolled_back']
                    ? 'trigger-recursive-view-delete-returning-current-source-released-next168'
                    : 'trigger-recursive-view-delete-returning-current-source-rolled-back-next168'),
            'savepoint' => $savepoint,
            'key' => $key,
            'parent_key' => $parentKey,
            'max_depth' => $maxDepth,
            'recursive_triggers' => $recursive,
            'current_view' => self::viewSummary($currentView),
            'next_view' => self::viewSummary($nextView),
            'visible_view' => self::viewSummary($admitNext ? $nextView : $currentView),
            'before_rows' => $baseRows,
            'current_deleted_keys' => $current['deleted_keys'],
            'current_returning_rows' => $current['returning_rows'],
            'current_recursive_edges' => $current['recursive_edges'],
            'current_blocked_rows' => $current['blocked_rows'],
            'current_rolled_back' => $current['rolled_back'],
            'current_rows_after_delete' => $current['rows'],
            'after_current_savepoint' => $afterCurrent,
            'attempted_next_deleted_keys' => $next['deleted_keys'],
            'attempted_next_returning_rows' => $next['returning_rows'],
            'attempted_next_recursive_edges' => $next['recursive_edges'],
            'next_returning_rows' => $admitNext ? $next['returning_rows'] : [],
            'next_deleted_keys' => $admitNext ? $next['deleted_keys'] : [],
            'after_savepoint' => $admitNext ? $next['rows'] : $afterCurrent,
            'changes' => ($releaseCurrent && !$current['rolled_back'] ? $current['changes'] : 0) + ($admitNext ? $next['changes'] : 0),
            'current_changes' => $current['changes'],
            'next_changes' => $admitNext ? $next['changes'] : 0,
            'statement_rows' => count($current['returning_rows']) + ($admitNext ? count($next['returning_rows']) : 0),
            'yield_boundary' => $admitNext
                ? 'recursive-view-delete-returning-next168-next-source-admitted-after-current-delete-drain'
                : ($releaseCurrent && !$current['rolled_back']
                    ? 'recursive-view-delete-returning-next168-current-delete-released-next-source-held'
                    : 'recursive-view-delete-returning-next168-current-delete-returning-drained-then-rolled-back'),
            'dependencies' => [
                'sqlite-trigger-recursive-view-delete-returning-current-source-next168',
                'sqlite-instead-of-delete-view-trigger-returning-current-source',
                'sqlite-recursive-view-delete-savepoint-rollback',
                'sqlite-next-view-source-delete-attempted-only',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function normalizeRows(array $rows, string $key): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view DELETE RETURNING next168 rows must be a list');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                throw new InvalidArgumentException("SQLite recursive view DELETE RETURNING next168 row key {$key} is missing");
            }
            $value = (string) $row[$key];
            if (isset($seen[$value])) {
                throw new InvalidArgumentException("SQLite recursive view DELETE RETURNING next168 duplicate key {$value}");
            }
            $seen[$value] = true;
        }

        return array_values($rows);
    }

    /**
     * @return array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,columns:list<string>}
     */
    private static function normalizeView(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view DELETE RETURNING next168 {$label} columns must be a non-empty list");
        }

        return [
            'name' => self::identifier((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::token((string) ($view['source'] ?? ''), $label . ' source'),
            'trigger' => self::identifier((string) ($view['trigger'] ?? ''), $label . ' trigger'),
            'trigger_source' => self::token((string) ($view['trigger_source'] ?? ''), $label . ' trigger source'),
            'root_key' => self::identifier((string) ($view['root_key'] ?? 'root_name'), $label . ' root key'),
            'columns' => array_map(static fn (mixed $column): string => self::identifier((string) $column, $label . ' column'), $columns),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $roots
     * @param array{name:string,source:string,trigger:string,trigger_source:string,root_key:string,columns:list<string>} $view
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int,int):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,deleted_keys:list<string>,returning_rows:list<array<string,mixed>>,recursive_edges:list<array<string,mixed>>,blocked_rows:list<array<string,mixed>>,rolled_back:bool,changes:int}
     */
    private static function deleteFromView(array $rows, array $roots, array $view, array $returning, string $key, string $parentKey, int $maxDepth, bool $recursive, ?string $blockedKey, string $phase): array
    {
        $queue = [];
        foreach (array_values($roots) as $ordinal => $root) {
            if (!is_array($root) || !array_key_exists($view['root_key'], $root)) {
                throw new InvalidArgumentException("SQLite recursive view DELETE RETURNING next168 missing root key {$view['root_key']}");
            }
            $queue[] = ['key' => (string) $root[$view['root_key']], 'root' => $root, 'ordinal' => (int) $ordinal, 'depth' => 0, 'parent' => null];
        }

        $deleted = [];
        $returningRows = [];
        $edges = [];
        $blocked = [];
        $visited = [];
        $rolledBack = false;

        while ($queue !== []) {
            $item = array_shift($queue);
            $rowKey = (string) $item['key'];
            if (isset($visited[$rowKey])) {
                continue;
            }
            $visited[$rowKey] = true;
            $index = self::rowIndex($rows, $key, $rowKey);
            if ($index === null) {
                continue;
            }

            $old = $rows[$index];
            $children = self::childrenOf($rows, $key, $parentKey, $rowKey);
            if ($blockedKey !== null && $rowKey === $blockedKey) {
                $blocked[] = self::envelope($phase, $view, (int) $item['ordinal'], (int) $item['depth'], 'blocked-rollback', ['key_name' => $rowKey]);
                $rolledBack = true;
                break;
            }

            array_splice($rows, $index, 1);
            $deleted[] = $rowKey;
            $returningRows[] = self::envelope(
                $phase,
                $view,
                (int) $item['ordinal'],
                (int) $item['depth'],
                'delete',
                self::returningRow($returning, $old, (array) $item['root'], $view['trigger_source'], (int) $item['ordinal'], (int) $item['depth'])
            );

            if (!$recursive || (int) $item['depth'] >= $maxDepth) {
                continue;
            }
            foreach ($children as $child) {
                $queue[] = [
                    'key' => (string) $child[$key],
                    'root' => $item['root'],
                    'ordinal' => count($deleted) + count($queue),
                    'depth' => (int) $item['depth'] + 1,
                    'parent' => $rowKey,
                ];
                $edges[] = [
                    'phase' => $phase,
                    'parent_key' => $rowKey,
                    'child_key' => (string) $child[$key],
                    'parent_depth' => (int) $item['depth'],
                    'child_depth' => (int) $item['depth'] + 1,
                    'source' => $view['source'],
                    'trigger_source' => $view['trigger_source'],
                ];
            }
        }

        return [
            'rows' => $rolledBack ? [] : array_values($rows),
            'deleted_keys' => $deleted,
            'returning_rows' => $returningRows,
            'recursive_edges' => $edges,
            'blocked_rows' => $blocked,
            'rolled_back' => $rolledBack,
            'changes' => $rolledBack ? 0 : count($deleted),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function childrenOf(array $rows, string $key, string $parentKey, string $parent): array
    {
        $children = [];
        foreach ($rows as $row) {
            if ((string) ($row[$parentKey] ?? '') === $parent) {
                $children[] = $row;
            }
        }
        usort($children, static fn (array $a, array $b): int => ((int) ($a['priority'] ?? 0)) <=> ((int) ($b['priority'] ?? 0)) ?: strcmp((string) $a[$key], (string) $b[$key]));

        return $children;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int,int):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $old, array $root, string $source, int $ordinal, int $depth): array
    {
        $out = [];
        foreach (array_values($returning) as $index => $expr) {
            if (is_string($expr)) {
                $alias = str_starts_with($expr, 'old.') ? substr($expr, 4) : $expr;
                $out[$alias] = self::exprValue($expr, $old, $root, $source, $ordinal, $depth);
                continue;
            }
            if (is_array($expr)) {
                $sql = (string) ($expr['expr'] ?? '');
                $alias = (string) ($expr['as'] ?? (str_starts_with($sql, 'old.') ? substr($sql, 4) : $sql));
                $out[self::identifier($alias, 'RETURNING alias')] = self::exprValue($sql, $old, $root, $source, $ordinal, $depth);
                continue;
            }
            if (is_callable($expr)) {
                $out['expr' . $index] = $expr($old, $root, $source, $ordinal, $depth);
                continue;
            }
            throw new InvalidArgumentException('SQLite recursive view DELETE RETURNING next168 projection expression is unsupported');
        }

        return $out;
    }

    private static function exprValue(string $expr, array $old, array $root, string $source, int $ordinal, int $depth): mixed
    {
        return match ($expr) {
            'ordinal' => $ordinal,
            'depth' => $depth,
            'trigger_source', 'source' => $source,
            default => str_starts_with($expr, 'old.')
                ? ($old[substr($expr, 4)] ?? null)
                : (str_starts_with($expr, 'root.')
                    ? ($root[substr($expr, 5)] ?? null)
                    : ($old[$expr] ?? null)),
        };
    }

    private static function envelope(string $phase, array $view, int $ordinal, int $depth, string $event, array $returning): array
    {
        return [
            'phase' => $phase,
            'source' => $view['source'],
            'trigger_source' => $view['trigger_source'],
            'view' => $view['name'],
            'trigger' => $view['trigger'],
            'ordinal' => $ordinal,
            'depth' => $depth,
            'event' => $event,
            'returning' => $returning,
        ];
    }

    private static function viewSummary(array $view): array
    {
        return [
            'name' => $view['name'],
            'source' => $view['source'],
            'trigger' => $view['trigger'],
            'trigger_source' => $view['trigger_source'],
            'root_key' => $view['root_key'],
            'columns' => $view['columns'],
        ];
    }

    private static function rowIndex(array $rows, string $key, string $value): ?int
    {
        foreach ($rows as $index => $row) {
            if ((string) ($row[$key] ?? '') === $value) {
                return (int) $index;
            }
        }

        return null;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view DELETE RETURNING next168 {$label} is malformed");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view DELETE RETURNING next168 {$label} is malformed");
        }

        return $value;
    }
}
