<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerDeferredViewReturningCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentMutations
     * @param list<array<string,mixed>> $nextMutations
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param array{name:string,columns:list<string>,where?:callable(array<string,mixed>):bool,order_by?:string} $view
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{key?:string,current_source?:string,next_source?:string,rollback_on_deferred_violation?:bool,trigger?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentMutations,
        array $nextMutations,
        array $foreignKey,
        array $view,
        array $returning,
        array $options = [],
    ): array {
        $key = self::identifier((string) ($options['key'] ?? 'option_name'), 'key column');
        $trigger = self::identifier((string) ($options['trigger'] ?? 'wp_options_view_io_update'), 'trigger name');
        $currentSource = self::source((string) ($options['current_source'] ?? 'current'));
        $nextSource = self::source((string) ($options['next_source'] ?? 'next'));
        $fk = [
            'parent_key' => self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key'),
            'child_key' => self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key'),
            'deferred' => (bool) ($foreignKey['deferred'] ?? true),
        ];
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite trigger deferred view RETURNING current-source projection cannot be empty');
        }

        $beforeRows = self::normalizeRows($rows, $key);
        $baseChildren = self::childRows($beforeRows, $fk);
        $current = self::applyMutations($beforeRows, $currentMutations, $key, $trigger, 'current', $currentSource, $returning);
        $currentViewRows = self::viewRows($current['rows'], $view);
        $currentChildren = self::childRows($current['rows'], $fk);
        $violations = self::violations($current['rows'], $currentChildren, $fk);
        $rollback = $fk['deferred']
            && $violations !== []
            && (bool) ($options['rollback_on_deferred_violation'] ?? true);

        if ($rollback) {
            $next = self::applyMutations($beforeRows, $nextMutations, $key, $trigger, 'next', $nextSource, $returning);
            $finalRows = $beforeRows;
            $nextReturning = [];
            $nextYield = [];
            $status = 'deferred-view-returning-current-source-rolled-back';
            $visibleSource = $currentSource;
        } else {
            $next = self::applyMutations($current['rows'], $nextMutations, $key, $trigger, 'next', $nextSource, $returning);
            $finalRows = $next['rows'];
            $nextReturning = $next['returning_rows'];
            $nextYield = $next['yield_stream'];
            $status = 'deferred-view-returning-current-source-admitted';
            $visibleSource = $nextSource;
        }

        return [
            'status' => $status,
            'trigger' => $trigger,
            'key' => $key,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'visible_source' => $visibleSource,
            'view' => self::viewName((string) ($view['name'] ?? 'current_view')),
            'view_columns' => self::columns((array) ($view['columns'] ?? [])),
            'current_view_rows' => $currentViewRows,
            'current_view_row_count' => count($currentViewRows),
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $nextReturning,
            'attempted_next_returning_rows' => $next['returning_rows'],
            'yield_stream' => array_merge($current['yield_stream'], $nextYield),
            'current_yield_stream' => $current['yield_stream'],
            'next_yield_stream' => $nextYield,
            'attempted_next_yield_stream' => $next['yield_stream'],
            'trigger_effects' => array_merge($current['effects'], $next['effects']),
            'current_rows' => $current['rows'],
            'final_rows' => $finalRows,
            'before_rows' => $beforeRows,
            'children' => $baseChildren,
            'current_children' => $currentChildren,
            'deferred_violations' => $violations,
            'deferred_violation_count' => count($violations),
            'deferred_checked_after_current_view' => true,
            'rolled_back_to_current_source' => $rollback,
            'next_source_blocked_by_deferred_fk' => $rollback,
            'yield_boundary' => $rollback
                ? 'current-view-returning-yield-then-deferred-fk-rollback'
                : 'current-view-returning-yield-then-next-source',
            'dependencies' => [
                'sqlite-trigger-deferred-view-returning-current-source-next131',
                'sqlite-instead-of-view-trigger-returning-current-source',
                'sqlite-deferred-fk-check-after-current-view-returning',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function normalizeRows(array $rows, string $key): array
    {
        $seen = [];
        foreach ($rows as $row) {
            if (!array_key_exists($key, $row)) {
                throw new InvalidArgumentException("SQLite trigger deferred view RETURNING key column {$key} is missing");
            }
            $value = (string) $row[$key];
            if (isset($seen[$value])) {
                throw new InvalidArgumentException("SQLite trigger deferred view RETURNING duplicate key {$value}");
            }
            $seen[$value] = true;
        }

        return array_values($rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $mutations
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,effects:list<array<string,mixed>>}
     */
    private static function applyMutations(array $rows, array $mutations, string $key, string $trigger, string $phase, string $source, array $returning): array
    {
        $returningRows = [];
        $yieldStream = [];
        $effects = [];

        foreach (array_values($mutations) as $ordinal => $mutation) {
            if (!array_key_exists($key, $mutation)) {
                throw new InvalidArgumentException("SQLite trigger deferred view RETURNING mutation key {$key} is missing");
            }
            $index = self::findRow($rows, $key, $mutation[$key]);
            $old = $index === null ? null : $rows[$index];
            $new = $old === null ? $mutation : array_replace($old, $mutation);
            if ($index === null) {
                $rows[] = $new;
                $event = 'insert';
            } else {
                $rows[$index] = $new;
                $event = 'update';
            }

            $returningRow = self::returningRow($returning, $new, $old, $mutation, $event, (int) $ordinal);
            $returningRows[] = [
                'phase' => $phase,
                'source' => $source,
                'ordinal' => (int) $ordinal,
                'event' => $event,
                'returning' => $returningRow,
            ];
            $yieldStream[] = [
                'phase' => $phase,
                'source' => $source,
                'ordinal' => (int) $ordinal,
                'trigger' => $trigger,
                'event' => $event,
                'row_key' => $new[$key],
                'returning' => $returningRow,
            ];
            $effects[] = [
                'trigger' => $trigger,
                'phase' => $phase,
                'source' => $source,
                'event' => $event,
                'old_key' => $old[$key] ?? null,
                'new_key' => $new[$key],
            ];
        }

        return [
            'rows' => array_values($rows),
            'returning_rows' => $returningRows,
            'yield_stream' => $yieldStream,
            'effects' => $effects,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function findRow(array $rows, string $key, mixed $value): ?int
    {
        foreach ($rows as $index => $row) {
            if (($row[$key] ?? null) === $value) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param array{parent_key:string,child_key:string,deferred:bool} $fk
     * @return list<array<string,mixed>>
     */
    private static function childRows(array $rows, array $fk): array
    {
        $children = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists($fk['child_key'], $row)) {
                continue;
            }
            $children[] = [
                'row_index' => $index,
                'child_key' => $row[$fk['child_key']],
                'option_name' => $row['option_name'] ?? null,
            ];
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
            $key = $child['child_key'] ?? null;
            if ($key === null || in_array($key, $keys, true)) {
                continue;
            }
            $violations[] = [
                'phase' => 'deferred-check-after-current-view-returning',
                'child_index' => $index,
                'child_key' => $key,
                'parent_key' => $fk['parent_key'],
                'option_name' => $child['option_name'] ?? null,
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
            throw new InvalidArgumentException('SQLite trigger deferred view RETURNING WHERE callback is malformed');
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
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $new, ?array $old, array $mutation, string $event, int $ordinal): array
    {
        $row = [];
        foreach ($returning as $index => $term) {
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $mutation, $event, $ordinal);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = (string) ($term['as'] ?? $expr);
                $row[self::identifier($alias, 'RETURNING alias')] = self::value($expr, $new, $old, $mutation, $event, $ordinal);
                continue;
            }
            $column = self::identifier((string) $term, 'RETURNING column');
            $row[$column] = $new[$column] ?? null;
        }

        return $row;
    }

    private static function value(string $expr, array $new, ?array $old, array $mutation, string $event, int $ordinal): mixed
    {
        if ($expr === 'event') {
            return $event;
        }
        if ($expr === 'ordinal') {
            return $ordinal;
        }
        if (str_starts_with($expr, 'new.')) {
            return $new[substr($expr, 4)] ?? null;
        }
        if (str_starts_with($expr, 'old.')) {
            return $old[substr($expr, 4)] ?? null;
        }
        if (str_starts_with($expr, 'excluded.')) {
            return $mutation[substr($expr, 9)] ?? null;
        }

        return $new[$expr] ?? null;
    }

    /**
     * @return list<string>
     */
    private static function columns(array $columns): array
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new InvalidArgumentException('SQLite trigger deferred view RETURNING columns must be a non-empty list');
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
            throw new InvalidArgumentException("SQLite trigger deferred view RETURNING {$label} is malformed");
        }

        return $value;
    }

    private static function source(string $source): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $source) !== 1) {
            throw new InvalidArgumentException('SQLite trigger deferred view RETURNING source token is malformed');
        }

        return $source;
    }
}
