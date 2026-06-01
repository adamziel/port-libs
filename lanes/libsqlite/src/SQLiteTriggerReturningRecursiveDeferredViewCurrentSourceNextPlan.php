<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<array<string,mixed>> $triggers
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param array{name:string,columns:list<string>,where?:callable(array<string,mixed>):bool,order_by?:string} $view
     * @param array{recursive_triggers?:bool,max_depth?:int,conflict_action?:string,current_source?:string,next_source?:string,returning?:list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,?string):mixed>,rollback_on_deferred_violation?:bool} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentRows,
        array $nextRows,
        array $uniqueColumns,
        array $assignments,
        array $triggers,
        array $foreignKey,
        array $view,
        array $options = [],
    ): array {
        $currentSource = self::source((string) ($options['current_source'] ?? 'current'));
        $nextSource = self::source((string) ($options['next_source'] ?? 'next'));
        $fk = [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key'),
            'deferred' => (bool) ($foreignKey['deferred'] ?? true),
        ];

        $baseChildren = self::children(array_values($rows), $fk);
        $currentPlan = SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan::execute(
            $rows,
            $currentRows,
            $nextRows,
            $uniqueColumns,
            $assignments,
            $triggers,
            $options + ['current_source' => $currentSource, 'next_source' => $nextSource],
        );

        $currentParents = array_values($currentPlan['next_source_rows']);
        $violations = self::violations($currentParents, $baseChildren, $fk);
        $rollback = $fk['deferred']
            && $violations !== []
            && (bool) ($options['rollback_on_deferred_violation'] ?? true);
        $viewRows = self::viewRows($currentParents, $view);

        return [
            'status' => $rollback
                ? 'deferred-fk-blocked-before-next-source'
                : 'view-current-source-drained-before-next-source',
            'current_source' => $currentSource,
            'next_source' => $rollback ? $currentSource : $nextSource,
            'view' => self::viewName((string) ($view['name'] ?? 'current_view')),
            'view_source' => $currentSource,
            'view_rows' => $viewRows,
            'view_row_count' => count($viewRows),
            'view_columns' => self::columns((array) ($view['columns'] ?? [])),
            'current_returning_rows' => $currentPlan['current_returning_rows'],
            'attempted_next_returning_rows' => $currentPlan['next_returning_rows'],
            'next_returning_rows' => $rollback ? [] : $currentPlan['next_returning_rows'],
            'yield_stream' => $currentPlan['yield_stream'],
            'current_yield_stream' => $currentPlan['current_yield_stream'],
            'attempted_next_yield_stream' => $currentPlan['next_yield_stream'],
            'next_yield_stream' => $rollback ? [] : $currentPlan['next_yield_stream'],
            'current_parent_rows' => $currentParents,
            'next_parent_rows' => $rollback ? array_values($rows) : array_values($currentPlan['rows']),
            'children' => $baseChildren,
            'foreign_key_violations' => $violations,
            'deferred_foreign_key_checked_after_view' => true,
            'yield_boundary' => $rollback
                ? 'current-returning-view-yield-then-deferred-fk-rollback'
                : 'current-returning-view-yield-then-next-source',
            'rollback_to_current_source' => $rollback,
            'next_source_blocked_by_deferred_fk' => $rollback,
            'handoff' => $currentPlan['handoff'],
            'dependencies' => [
                'sqlite-trigger-returning-recursive-deferred-view-current-source-next128',
                'sqlite-trigger-recursive-upsert-returning-current-source-next126',
                'sqlite-view-current-source-materialization',
                'sqlite-deferred-fk-commit-check-before-next-source',
            ],
        ];
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred:bool} $fk
     * @return list<array<string,mixed>>
     */
    private static function children(array $rows, array $fk): array
    {
        $children = [];
        foreach ($rows as $row) {
            if (array_key_exists($fk['child_key'], $row)) {
                $children[] = [
                    $fk['child_key'] => $row[$fk['child_key']],
                    'key_name' => $row['key_name'] ?? null,
                ];
            }
        }

        return $children;
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred:bool} $fk
     * @return list<array<string,mixed>>
     */
    private static function violations(array $parents, array $children, array $fk): array
    {
        $keys = array_values(array_filter(array_column($parents, $fk['parent_key']), static fn (mixed $key): bool => $key !== null));
        $violations = [];
        foreach ($children as $index => $child) {
            $key = $child[$fk['child_key']] ?? null;
            if ($key === null || in_array($key, $keys, true)) {
                continue;
            }
            $violations[] = [
                'phase' => 'deferred-commit-before-next-source',
                'child_index' => $index,
                'child_key' => $key,
                'parent_key' => $fk['parent_key'],
                'key_name' => $child['key_name'] ?? null,
            ];
        }

        return $violations;
    }

    /**
     * @param array{name:string,columns:list<string>,where?:callable(array<string,mixed>):bool,order_by?:string} $view
     * @return list<array<string,mixed>>
     */
    private static function viewRows(array $rows, array $view): array
    {
        $columns = self::columns((array) ($view['columns'] ?? []));
        $where = $view['where'] ?? static fn (array $row): bool => true;
        if (!is_callable($where)) {
            throw new \InvalidArgumentException('SQLite trigger deferred view current-source WHERE callback is malformed');
        }

        $out = [];
        foreach ($rows as $row) {
            if (!$where($row)) {
                continue;
            }
            $viewRow = [];
            foreach ($columns as $column) {
                $viewRow[$column] = $row[$column] ?? null;
            }
            $out[] = $viewRow;
        }

        $orderBy = isset($view['order_by']) ? self::identifier((string) $view['order_by'], 'view order column') : null;
        if ($orderBy !== null) {
            usort($out, static fn (array $left, array $right): int => ($left[$orderBy] ?? null) <=> ($right[$orderBy] ?? null));
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function columns(array $columns): array
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new \InvalidArgumentException('SQLite trigger deferred view current-source columns must be a non-empty list');
        }

        return array_values(array_map(static fn (mixed $column): string => self::identifier((string) $column, 'view column'), $columns));
    }

    private static function viewName(string $name): string
    {
        return self::identifier($name, 'view name');
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite trigger deferred view current-source {$label} is malformed");
        }

        return $value;
    }

    private static function source(string $source): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $source) !== 1) {
            throw new \InvalidArgumentException('SQLite trigger deferred view current-source token is malformed');
        }

        return $source;
    }
}
