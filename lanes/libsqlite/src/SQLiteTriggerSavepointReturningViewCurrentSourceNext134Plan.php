<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerSavepointReturningViewCurrentSourceNext134Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentMutations
     * @param list<array<string,mixed>> $nextMutations
     * @param array{name:string,columns:list<string>,source:string,where?:callable(array<string,mixed>):bool,order_by?:string} $currentView
     * @param array{name:string,columns:list<string>,source:string,where?:callable(array<string,mixed>):bool,order_by?:string} $nextView
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,trigger?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentMutations,
        array $nextMutations,
        array $currentView,
        array $nextView,
        array $triggers,
        array $returning,
        array $options = [],
    ): array {
        $key = self::identifier((string) ($options['key'] ?? 'option_name'), 'key column');
        $savepoint = self::token((string) ($options['savepoint'] ?? 'wp_view_import'), 'savepoint');
        $trigger = self::identifier((string) ($options['trigger'] ?? 'wp_options_view_io_update'), 'trigger');
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite trigger savepoint view RETURNING projection cannot be empty');
        }

        $beforeRows = self::normalizeRows($rows, $key);
        $currentView = self::normalizeView($currentView, 'current view');
        $nextView = self::normalizeView($nextView, 'next view');
        $current = self::applyPhase($beforeRows, $currentMutations, $key, $savepoint, $trigger, $currentView, 'current', $triggers, $returning);
        $rolledBack = $current['rollback_reason'] !== null;

        if ($rolledBack) {
            $attemptedNext = self::applyPhase($beforeRows, $nextMutations, $key, $savepoint, $trigger, $nextView, 'next', $triggers, $returning);
            $next = self::emptyPhase($nextView);
            $finalRows = $beforeRows;
            $visibleView = $currentView;
            $status = 'trigger-savepoint-returning-view-current-source-rolled-back-next134';
        } else {
            $attemptedNext = self::applyPhase($current['rows'], $nextMutations, $key, $savepoint, $trigger, $nextView, 'next', $triggers, $returning);
            $next = $attemptedNext;
            $finalRows = $next['rows'];
            $visibleView = $nextView;
            $status = 'trigger-savepoint-returning-view-next-source-admitted-next134';
        }

        $yieldStream = array_merge($current['yield_stream'], $next['yield_stream']);
        if ($rolledBack) {
            foreach ($yieldStream as $index => $row) {
                $yieldStream[$index]['rolled_back_after_yield'] = true;
            }
        }

        return [
            'status' => $status,
            'savepoint' => $savepoint,
            'trigger' => $trigger,
            'key' => $key,
            'current_view' => self::viewSummary($currentView),
            'next_view' => self::viewSummary($nextView),
            'visible_view' => self::viewSummary($visibleView),
            'before_rows' => $beforeRows,
            'current_rows' => $current['rows'],
            'after_savepoint' => $finalRows,
            'current_view_rows' => self::viewRows($current['rows'], $currentView),
            'next_view_rows' => $rolledBack ? [] : self::viewRows($finalRows, $nextView),
            'attempted_next_view_rows' => self::viewRows($attemptedNext['rows'], $nextView),
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $next['returning_rows'],
            'attempted_next_returning_rows' => $attemptedNext['returning_rows'],
            'yield_stream' => $yieldStream,
            'current_yield_stream' => $current['yield_stream'],
            'next_yield_stream' => $next['yield_stream'],
            'attempted_next_yield_stream' => $attemptedNext['yield_stream'],
            'trigger_effects_before_rollback' => array_merge($current['effects'], $attemptedNext['effects']),
            'rolled_back_to_savepoint' => $rolledBack,
            'rollback_reason' => $current['rollback_reason'],
            'next_source_admitted' => !$rolledBack,
            'suppressed_next_source' => $rolledBack ? self::viewSummary($nextView) : null,
            'discarded_returning_count' => $rolledBack ? count($current['returning_rows']) : 0,
            'changes' => $rolledBack ? 0 : count($current['returning_rows']) + count($next['returning_rows']),
            'yield_boundary' => $rolledBack
                ? 'view-returning-yield-then-savepoint-rollback-keeps-current-source'
                : 'view-returning-yield-then-release-admits-next-source',
            'dependencies' => [
                'sqlite-trigger-savepoint-returning-view-current-source-next134',
                'sqlite-instead-of-view-trigger-returning-savepoint',
                'sqlite-returning-yield-before-view-trigger-rollback',
                'sqlite-next-view-source-blocked-by-savepoint-rollback',
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
            throw new InvalidArgumentException('SQLite trigger savepoint view RETURNING rows must be a list');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING row key {$key} is missing");
            }
            $value = (string) $row[$key];
            if (isset($seen[$value])) {
                throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING duplicate key {$value}");
            }
            $seen[$value] = true;
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,columns:list<string>,source:string,where?:callable(array<string,mixed>):bool,order_by?:string}
     */
    private static function normalizeView(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING {$label} columns must be a non-empty list");
        }
        $normalized = [
            'name' => self::identifier((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::token((string) ($view['source'] ?? ''), $label . ' source'),
            'columns' => array_map(static fn (mixed $column): string => self::identifier((string) $column, $label . ' column'), $columns),
        ];
        if (array_key_exists('where', $view)) {
            if (!is_callable($view['where'])) {
                throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING {$label} WHERE callback is malformed");
            }
            $normalized['where'] = $view['where'];
        }
        if (array_key_exists('order_by', $view)) {
            $normalized['order_by'] = self::identifier((string) $view['order_by'], $label . ' order column');
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $mutations
     * @param array{name:string,columns:list<string>,source:string,where?:callable(array<string,mixed>):bool,order_by?:string} $view
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,string):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,effects:list<array<string,mixed>>,rollback_reason:?string}
     */
    private static function applyPhase(
        array $rows,
        array $mutations,
        string $key,
        string $savepoint,
        string $trigger,
        array $view,
        string $phase,
        array $triggers,
        array $returning,
    ): array {
        $returningRows = [];
        $yieldStream = [];
        $effects = [];
        $rollbackReason = null;

        foreach (array_values($mutations) as $ordinal => $mutation) {
            if (!is_array($mutation) || !array_key_exists($key, $mutation)) {
                throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING mutation key {$key} is missing");
            }
            $index = self::findRow($rows, $key, $mutation[$key]);
            $old = $index === null ? null : $rows[$index];
            $new = $old === null ? $mutation : array_replace($old, $mutation);
            $event = $old === null ? 'insert' : 'update';

            $returningRow = self::returningRow($returning, $new, $old, $mutation, $event, (int) $ordinal, $view['source']);
            $returningRows[] = [
                'phase' => $phase,
                'source' => $view['source'],
                'view' => $view['name'],
                'ordinal' => (int) $ordinal,
                'event' => $event,
                'returning' => $returningRow,
            ];
            $yieldStream[] = [
                'savepoint' => $savepoint,
                'phase' => $phase,
                'source' => $view['source'],
                'view' => $view['name'],
                'ordinal' => (int) $ordinal,
                'trigger' => $trigger,
                'event' => $event,
                'row_key' => $new[$key],
                'returning' => $returningRow,
                'rolled_back_after_yield' => false,
            ];

            $rows = self::storeRow($rows, $key, $new);
            $phaseEffects = self::triggerEffects($triggers, $phase, $event, $old, $new, $view['source']);
            $effects = array_merge($effects, $phaseEffects);
            $rollbackReason = self::rollbackReason($phaseEffects) ?? $rollbackReason;
            if ($rollbackReason !== null) {
                break;
            }
        }

        return [
            'rows' => array_values($rows),
            'returning_rows' => $returningRows,
            'yield_stream' => $yieldStream,
            'effects' => $effects,
            'rollback_reason' => $rollbackReason,
        ];
    }

    /**
     * @return array{returning_rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,effects:list<array<string,mixed>>,rows:list<array<string,mixed>>,rollback_reason:?string}
     */
    private static function emptyPhase(array $view): array
    {
        return [
            'rows' => [],
            'returning_rows' => [],
            'yield_stream' => [],
            'effects' => [],
            'rollback_reason' => null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function storeRow(array $rows, string $key, array $new): array
    {
        $index = self::findRow($rows, $key, $new[$key]);
        if ($index === null) {
            $rows[] = $new;
        } else {
            $rows[$index] = $new;
        }

        return array_values($rows);
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
     * @param list<array<string,mixed>> $triggers
     * @return list<array<string,mixed>>
     */
    private static function triggerEffects(array $triggers, string $phase, string $event, ?array $old, array $new, string $source): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (($trigger['phase'] ?? $phase) !== $phase || ($trigger['event'] ?? $event) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? null, $old, $new, $source)) {
                continue;
            }
            $effect = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'phase' => $phase,
                'event' => $event,
                'source' => $source,
                'row' => self::projectValues((array) ($trigger['values'] ?? []), $old, $new, $source),
            ];
            if (($trigger['raise'] ?? null) !== null) {
                $effect['raise'] = strtolower((string) $trigger['raise']);
                $effect['reason'] = (string) ($trigger['reason'] ?? $trigger['name'] ?? 'trigger rollback');
            }
            $effects[] = $effect;
        }

        return $effects;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $new, ?array $old, array $mutation, string $event, int $ordinal, string $source): array
    {
        $row = [];
        foreach ($returning as $index => $term) {
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $mutation, $event, $ordinal, $source);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = self::identifier((string) ($term['as'] ?? $expr), 'RETURNING alias');
                $row[$alias] = self::value($expr, $old, $new, $mutation, $event, $ordinal, $source);
                continue;
            }
            $column = self::identifier((string) $term, 'RETURNING column');
            $row[$column] = $new[$column] ?? null;
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function projectValues(array $template, ?array $old, array $new, string $source): array
    {
        $row = [];
        foreach ($template as $column => $expr) {
            $row[self::identifier((string) $column, 'trigger value column')] = self::value($expr, $old, $new, [], 'trigger', 0, $source);
        }

        return $row;
    }

    private static function whenMatches(mixed $when, ?array $old, array $new, string $source): bool
    {
        if ($when === null || $when === true) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new InvalidArgumentException('SQLite trigger savepoint view RETURNING WHEN clause is malformed');
        }

        [$left, $operator, $right] = array_values($when);
        $left = self::value($left, $old, $new, [], 'trigger', 0, $source);
        $right = self::value($right, $old, $new, [], 'trigger', 0, $source);

        return match (strtoupper((string) $operator)) {
            '=' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new InvalidArgumentException('SQLite trigger savepoint view RETURNING WHEN operator is unsupported'),
        };
    }

    private static function value(mixed $expr, ?array $old, array $new, array $mutation, string $event, int $ordinal, string $source): mixed
    {
        if ($expr === 'event') {
            return $event;
        }
        if ($expr === 'ordinal') {
            return $ordinal;
        }
        if ($expr === 'source') {
            return $source;
        }
        if (is_string($expr) && str_starts_with($expr, 'new.')) {
            return $new[substr($expr, 4)] ?? null;
        }
        if (is_string($expr) && str_starts_with($expr, 'old.')) {
            return $old[substr($expr, 4)] ?? null;
        }
        if (is_string($expr) && str_starts_with($expr, 'excluded.')) {
            return $mutation[substr($expr, 9)] ?? null;
        }
        if (is_string($expr) && array_key_exists($expr, $new)) {
            return $new[$expr];
        }

        return $expr;
    }

    /**
     * @param list<array<string,mixed>> $effects
     */
    private static function rollbackReason(array $effects): ?string
    {
        foreach ($effects as $effect) {
            if (($effect['raise'] ?? null) === 'rollback') {
                return (string) ($effect['reason'] ?? $effect['trigger'] ?? 'trigger rollback');
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{name:string,columns:list<string>,source:string,where?:callable(array<string,mixed>):bool,order_by?:string} $view
     * @return list<array<string,mixed>>
     */
    private static function viewRows(array $rows, array $view): array
    {
        $where = $view['where'] ?? static fn (array $row): bool => true;
        $out = [];
        foreach ($rows as $row) {
            if (!$where($row)) {
                continue;
            }
            $viewRow = [];
            foreach ($view['columns'] as $column) {
                $viewRow[$column] = $row[$column] ?? null;
            }
            $out[] = $viewRow;
        }
        if (isset($view['order_by'])) {
            $order = $view['order_by'];
            usort($out, static fn (array $left, array $right): int => ($left[$order] ?? null) <=> ($right[$order] ?? null));
        }

        return $out;
    }

    /**
     * @param array{name:string,columns:list<string>,source:string,where?:callable(array<string,mixed>):bool,order_by?:string} $view
     * @return array{name:string,source:string,columns:list<string>}
     */
    private static function viewSummary(array $view): array
    {
        return [
            'name' => $view['name'],
            'source' => $view['source'],
            'columns' => $view['columns'],
        ];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING {$label} is malformed");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING {$label} is malformed");
        }

        return $value;
    }
}
