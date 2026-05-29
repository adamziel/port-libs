<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan
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
    public static function executeViewSavepointReturningRollback(
        array $rows,
        array $currentMutations,
        array $nextMutations,
        array $currentView,
        array $nextView,
        array $triggers,
        array $returning,
        array $options = [],
    ): array {
        $key = self::viewReturningIdentifier((string) ($options['key'] ?? 'option_name'), 'key column');
        $savepoint = self::viewReturningToken((string) ($options['savepoint'] ?? 'wp_view_import'), 'savepoint');
        $trigger = self::viewReturningIdentifier((string) ($options['trigger'] ?? 'wp_options_view_io_update'), 'trigger');
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite trigger savepoint view RETURNING projection cannot be empty');
        }

        $beforeRows = self::normalizeViewRollbackRows($rows, $key);
        $currentView = self::normalizeReturningView($currentView, 'current view');
        $nextView = self::normalizeReturningView($nextView, 'next view');
        $current = self::applyViewReturningPhase($beforeRows, $currentMutations, $key, $savepoint, $trigger, $currentView, 'current', $triggers, $returning);
        $rolledBack = $current['rollback_reason'] !== null;

        if ($rolledBack) {
            $attemptedNext = self::applyViewReturningPhase($beforeRows, $nextMutations, $key, $savepoint, $trigger, $nextView, 'next', $triggers, $returning);
            $next = self::emptyViewReturningPhase($nextView);
            $finalRows = $beforeRows;
            $visibleView = $currentView;
            $status = 'trigger-savepoint-returning-view-current-source-rolled-back';
        } else {
            $attemptedNext = self::applyViewReturningPhase($current['rows'], $nextMutations, $key, $savepoint, $trigger, $nextView, 'next', $triggers, $returning);
            $next = $attemptedNext;
            $finalRows = $next['rows'];
            $visibleView = $nextView;
            $status = 'trigger-savepoint-returning-view-next-source-admitted';
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
            'current_view' => self::returningViewSummary($currentView),
            'next_view' => self::returningViewSummary($nextView),
            'visible_view' => self::returningViewSummary($visibleView),
            'before_rows' => $beforeRows,
            'current_rows' => $current['rows'],
            'after_savepoint' => $finalRows,
            'current_view_rows' => self::visibleReturningViewRows($current['rows'], $currentView),
            'next_view_rows' => $rolledBack ? [] : self::visibleReturningViewRows($finalRows, $nextView),
            'attempted_next_view_rows' => self::visibleReturningViewRows($attemptedNext['rows'], $nextView),
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
            'suppressed_next_source' => $rolledBack ? self::returningViewSummary($nextView) : null,
            'discarded_returning_count' => $rolledBack ? count($current['returning_rows']) : 0,
            'changes' => $rolledBack ? 0 : count($current['returning_rows']) + count($next['returning_rows']),
            'yield_boundary' => $rolledBack
                ? 'view-returning-yield-then-savepoint-rollback-keeps-current-source'
                : 'view-returning-yield-then-release-admits-next-source',
            'dependencies' => [
                'sqlite-trigger-savepoint-returning-view-current-source',
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
    private static function normalizeViewRollbackRows(array $rows, string $key): array
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
    private static function normalizeReturningView(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING {$label} columns must be a non-empty list");
        }
        $normalized = [
            'name' => self::viewReturningIdentifier((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::viewReturningToken((string) ($view['source'] ?? ''), $label . ' source'),
            'columns' => array_map(static fn (mixed $column): string => self::viewReturningIdentifier((string) $column, $label . ' column'), $columns),
        ];
        if (array_key_exists('where', $view)) {
            if (!is_callable($view['where'])) {
                throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING {$label} WHERE callback is malformed");
            }
            $normalized['where'] = $view['where'];
        }
        if (array_key_exists('order_by', $view)) {
            $normalized['order_by'] = self::viewReturningIdentifier((string) $view['order_by'], $label . ' order column');
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
    private static function applyViewReturningPhase(
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
            $index = self::findViewReturningRow($rows, $key, $mutation[$key]);
            $old = $index === null ? null : $rows[$index];
            $new = $old === null ? $mutation : array_replace($old, $mutation);
            $event = $old === null ? 'insert' : 'update';

            $returningRow = self::viewReturningRow($returning, $new, $old, $mutation, $event, (int) $ordinal, $view['source']);
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

            $rows = self::storeViewReturningRow($rows, $key, $new);
            $phaseEffects = self::viewReturningTriggerEffects($triggers, $phase, $event, $old, $new, $view['source']);
            $effects = array_merge($effects, $phaseEffects);
            $rollbackReason = self::viewReturningRollbackReason($phaseEffects) ?? $rollbackReason;
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
    private static function emptyViewReturningPhase(array $view): array
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
    private static function storeViewReturningRow(array $rows, string $key, array $new): array
    {
        $index = self::findViewReturningRow($rows, $key, $new[$key]);
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
    private static function findViewReturningRow(array $rows, string $key, mixed $value): ?int
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
    private static function viewReturningTriggerEffects(array $triggers, string $phase, string $event, ?array $old, array $new, string $source): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (($trigger['phase'] ?? $phase) !== $phase || ($trigger['event'] ?? $event) !== $event) {
                continue;
            }
            if (!self::viewReturningWhenMatches($trigger['when'] ?? null, $old, $new, $source)) {
                continue;
            }
            $effect = [
                'trigger' => (string) ($trigger['name'] ?? ''),
                'phase' => $phase,
                'event' => $event,
                'source' => $source,
                'row' => self::projectViewTriggerValues((array) ($trigger['values'] ?? []), $old, $new, $source),
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
    private static function viewReturningRow(array $returning, array $new, ?array $old, array $mutation, string $event, int $ordinal, string $source): array
    {
        $row = [];
        foreach ($returning as $index => $term) {
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $mutation, $event, $ordinal, $source);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = self::viewReturningIdentifier((string) ($term['as'] ?? $expr), 'RETURNING alias');
                $row[$alias] = self::viewReturningValue($expr, $old, $new, $mutation, $event, $ordinal, $source);
                continue;
            }
            $column = self::viewReturningIdentifier((string) $term, 'RETURNING column');
            $row[$column] = $new[$column] ?? null;
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    private static function projectViewTriggerValues(array $template, ?array $old, array $new, string $source): array
    {
        $row = [];
        foreach ($template as $column => $expr) {
            $row[self::viewReturningIdentifier((string) $column, 'trigger value column')] = self::viewReturningValue($expr, $old, $new, [], 'trigger', 0, $source);
        }

        return $row;
    }

    private static function viewReturningWhenMatches(mixed $when, ?array $old, array $new, string $source): bool
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
        $left = self::viewReturningValue($left, $old, $new, [], 'trigger', 0, $source);
        $right = self::viewReturningValue($right, $old, $new, [], 'trigger', 0, $source);

        return match (strtoupper((string) $operator)) {
            '=' => $left == $right,
            '!=', '<>' => $left != $right,
            'IS' => $left === $right,
            'IS NOT' => $left !== $right,
            default => throw new InvalidArgumentException('SQLite trigger savepoint view RETURNING WHEN operator is unsupported'),
        };
    }

    private static function viewReturningValue(mixed $expr, ?array $old, array $new, array $mutation, string $event, int $ordinal, string $source): mixed
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
    private static function viewReturningRollbackReason(array $effects): ?string
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
    private static function visibleReturningViewRows(array $rows, array $view): array
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
    private static function returningViewSummary(array $view): array
    {
        return [
            'name' => $view['name'],
            'source' => $view['source'],
            'columns' => $view['columns'],
        ];
    }

    private static function viewReturningIdentifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING {$label} is malformed");
        }

        return $value;
    }

    private static function viewReturningToken(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger savepoint view RETURNING {$label} is malformed");
        }

        return $value;
    }


    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentViewRows
     * @param list<array<string,mixed>> $nextViewRows
     * @param array<string,string> $viewToBase
     * @param list<string> $uniqueColumns
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{savepoint?:string,view?:string,current_source?:string,next_source?:string,rollback_current?:bool} $options
     * @return array<string,mixed>
     */
    public static function executeMappedViewReturningSavepoint(
        array $baseRows,
        array $currentViewRows,
        array $nextViewRows,
        array $viewToBase,
        array $uniqueColumns,
        array $triggers,
        array $returning,
        array $options = [],
    ): array {
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite trigger savepoint RETURNING view projection cannot be empty');
        }

        $savepoint = self::mappedViewIdentifier((string) ($options['savepoint'] ?? 'wp_view_returning'), 'savepoint');
        $view = self::mappedViewIdentifier((string) ($options['view'] ?? 'wp_option_import_view'), 'view');
        $currentSource = self::mappedViewSourceToken((string) ($options['current_source'] ?? 'current-view-returning'));
        $nextSource = self::mappedViewSourceToken((string) ($options['next_source'] ?? 'next-view-returning'));
        $rollbackCurrent = (bool) ($options['rollback_current'] ?? false);
        self::validateViewMapping($viewToBase);
        self::validateViewColumns($uniqueColumns, 'unique column');

        $savepointRows = array_values($baseRows);
        $current = self::runMappedViewReturningSource($savepointRows, $currentViewRows, $viewToBase, $uniqueColumns, $triggers, $returning, $view, $currentSource);
        $currentRolledBack = $rollbackCurrent || $current['rolled_back'];
        $nextStartRows = $currentRolledBack ? $savepointRows : $current['rows'];
        $next = self::runMappedViewReturningSource($nextStartRows, $nextViewRows, $viewToBase, $uniqueColumns, $triggers, $returning, $view, $nextSource);

        return [
            'status' => $currentRolledBack
                ? 'trigger-savepoint-returning-view-current-source-current-rolled-back'
                : 'trigger-savepoint-returning-view-current-source-released',
            'savepoint' => $savepoint,
            'view' => $view,
            'current_source' => $currentSource,
            'next_source' => $nextSource,
            'rollback_current' => $currentRolledBack,
            'rollback_reason' => $current['rollback_reason'],
            'savepoint_rows' => $savepointRows,
            'current_statement_rows' => $current['rows'],
            'next_start_rows' => $nextStartRows,
            'next_rows' => $next['rows'],
            'rows' => $next['rows'],
            'current_returning_rows' => $currentRolledBack ? [] : $current['returning_rows'],
            'attempted_current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $next['returning_rows'],
            'returning_rows' => array_merge($currentRolledBack ? [] : $current['returning_rows'], $next['returning_rows']),
            'current_view_attempts' => $current['view_attempts'],
            'next_view_attempts' => $next['view_attempts'],
            'current_yields' => $current['yields'],
            'next_yields' => $next['yields'],
            'trigger_effects' => array_merge($current['trigger_effects'], $next['trigger_effects']),
            'current_trigger_effects' => $current['trigger_effects'],
            'next_trigger_effects' => $next['trigger_effects'],
            'current_changes' => $currentRolledBack ? 0 : $current['changes'],
            'attempted_current_changes' => $current['changes'],
            'next_changes' => $next['changes'],
            'committed_changes' => ($currentRolledBack ? 0 : $current['changes']) + $next['changes'],
            'source_transition' => [
                'current' => $currentSource,
                'next' => $nextSource,
                'next_started_from' => $currentRolledBack ? 'savepoint' : 'current-source',
                'view' => $view,
                'returning_stream' => $currentRolledBack ? 'current-suppressed-next-admitted' : 'current-and-next-admitted',
            ],
            'dependencies' => [
                'sqlite-trigger-savepoint-returning-view-current-source-mapped',
                'sqlite-instead-of-view-trigger-returning-current-source',
                'sqlite-savepoint-rollback-suppresses-view-returning-stream',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $startRows
     * @param list<array<string,mixed>> $viewRows
     * @param array<string,string> $viewToBase
     * @param list<string> $uniqueColumns
     * @param list<array<string,mixed>> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,view_attempts:list<array<string,mixed>>,yields:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,rolled_back:bool,rollback_reason:?string}
     */
    private static function runMappedViewReturningSource(
        array $startRows,
        array $viewRows,
        array $viewToBase,
        array $uniqueColumns,
        array $triggers,
        array $returning,
        string $view,
        string $source,
    ): array {
        $rows = array_values($startRows);
        $returningRows = [];
        $attempts = [];
        $yields = [];
        $effects = [];
        $changes = 0;

        foreach (array_values($viewRows) as $ordinal => $viewRow) {
            $incoming = self::mapReturningViewRow($viewRow, $viewToBase);
            $attempts[] = ['source' => $source, 'view' => $view, 'ordinal' => $ordinal, 'view_row' => $viewRow, 'incoming_row' => $incoming];
            $oldIndex = self::mappedViewConflictIndex($rows, $incoming, $uniqueColumns);
            $old = $oldIndex === null ? null : $rows[$oldIndex];
            $event = $old === null ? 'insert' : 'update';
            $new = $old === null ? $incoming : array_replace($old, $incoming);

            $before = self::fireMappedViewTriggers($triggers, 'before', $event, $old, $new, $viewRow, $source, $ordinal);
            if ($before['rollback']) {
                return self::mappedViewReturningRolledBack($rows, $returningRows, $attempts, $yields, array_merge($effects, $before['effects']), $changes, $before['reason']);
            }
            $new = $before['row'];
            $effects = array_merge($effects, $before['effects']);

            if ($oldIndex === null) {
                $rows[] = $new;
            } else {
                $rows[$oldIndex] = $new;
            }
            ++$changes;

            $after = self::fireMappedViewTriggers($triggers, 'after', $event, $old, $new, $viewRow, $source, $ordinal);
            if ($after['rollback']) {
                return self::mappedViewReturningRolledBack($rows, $returningRows, $attempts, $yields, array_merge($effects, $after['effects']), $changes, $after['reason']);
            }
            $new = $after['row'];
            if ($oldIndex === null) {
                $rows[array_key_last($rows)] = $new;
            } else {
                $rows[$oldIndex] = $new;
            }
            $effects = array_merge($effects, $after['effects']);

            $returningRow = self::mappedViewReturningRow($returning, $new, $old, $viewRow, $event, $ordinal);
            $returningRows[] = $returningRow + ['view_ordinal' => $ordinal, 'source' => $source, 'event' => $event];
            $yields[] = ['source' => $source, 'view' => $view, 'ordinal' => $ordinal, 'event' => $event, 'status' => 'changed', 'returning' => $returningRow];
        }

        return [
            'rows' => array_values($rows),
            'returning_rows' => $returningRows,
            'view_attempts' => $attempts,
            'yields' => $yields,
            'trigger_effects' => $effects,
            'changes' => $changes,
            'rolled_back' => false,
            'rollback_reason' => null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $returningRows
     * @param list<array<string,mixed>> $attempts
     * @param list<array<string,mixed>> $yields
     * @param list<array<string,mixed>> $effects
     * @return array{rows:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,view_attempts:list<array<string,mixed>>,yields:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,rolled_back:bool,rollback_reason:string}
     */
    private static function mappedViewReturningRolledBack(array $rows, array $returningRows, array $attempts, array $yields, array $effects, int $changes, string $reason): array
    {
        return [
            'rows' => array_values($rows),
            'returning_rows' => $returningRows,
            'view_attempts' => $attempts,
            'yields' => $yields,
            'trigger_effects' => $effects,
            'changes' => $changes,
            'rolled_back' => true,
            'rollback_reason' => $reason,
        ];
    }

    /**
     * @param array<string,string> $viewToBase
     * @return array<string,mixed>
     */
    private static function mapReturningViewRow(array $viewRow, array $viewToBase): array
    {
        $row = [];
        foreach ($viewToBase as $viewColumn => $baseColumn) {
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new InvalidArgumentException("SQLite trigger view RETURNING row is missing {$viewColumn}");
            }
            $row[$baseColumn] = $viewRow[$viewColumn];
        }

        return $row;
    }

    /**
     * @param list<array<string,mixed>> $triggers
     * @return array{row:array<string,mixed>,effects:list<array<string,mixed>>,rollback:bool,reason:string}
     */
    private static function fireMappedViewTriggers(array $triggers, string $timing, string $event, ?array $old, array $new, array $viewRow, string $source, int $ordinal): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::mappedViewWhenMatches($trigger['when'] ?? true, $old, $new, $viewRow)) {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'set-new') {
                foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                    $new[self::mappedViewIdentifier((string) $column, 'trigger set column')] = self::mappedViewValue($value, $old, $new, $viewRow);
                }
            } elseif ($action === 'raise-rollback') {
                $effects[] = self::mappedViewTriggerEffect($trigger, $timing, $event, $action, $source, $ordinal, $old, $new, $viewRow);

                return ['row' => $new, 'effects' => $effects, 'rollback' => true, 'reason' => (string) ($trigger['reason'] ?? 'view-trigger-raise-rollback-current-savepoint')];
            } elseif ($action !== 'audit') {
                throw new InvalidArgumentException('SQLite trigger savepoint RETURNING view trigger action is unsupported');
            }

            $effects[] = self::mappedViewTriggerEffect($trigger, $timing, $event, $action, $source, $ordinal, $old, $new, $viewRow);
        }

        return ['row' => $new, 'effects' => $effects, 'rollback' => false, 'reason' => ''];
    }

    /**
     * @param array<string,mixed> $trigger
     * @return array<string,mixed>
     */
    private static function mappedViewTriggerEffect(array $trigger, string $timing, string $event, string $action, string $source, int $ordinal, ?array $old, array $new, array $viewRow): array
    {
        return [
            'trigger' => (string) ($trigger['name'] ?? ''),
            'timing' => $timing,
            'event' => $event,
            'action' => $action,
            'source' => $source,
            'ordinal' => $ordinal,
            'row' => self::projectMappedViewValues((array) ($trigger['values'] ?? []), $old, $new, $viewRow),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     */
    private static function mappedViewConflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new InvalidArgumentException("SQLite trigger view RETURNING conflict column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return (int) $index;
        }

        return null;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @return array<string,mixed>
     */
    private static function mappedViewReturningRow(array $returning, array $new, ?array $old, array $viewRow, string $event, int $ordinal): array
    {
        $row = [];
        foreach ($returning as $index => $term) {
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $viewRow, $event, $ordinal);
                continue;
            }
            if (is_array($term)) {
                $expr = (string) ($term['expr'] ?? '');
                $alias = (string) ($term['as'] ?? $expr);
                $row[self::mappedViewIdentifier($alias, 'RETURNING alias')] = self::mappedViewExpressionValue($expr, $new, $old, $viewRow);
                continue;
            }
            if (!is_string($term) || $term === '') {
                throw new InvalidArgumentException('SQLite trigger view RETURNING term is malformed');
            }
            $alias = str_contains($term, '.') ? substr($term, (int) strrpos($term, '.') + 1) : $term;
            $row[self::mappedViewIdentifier($alias, 'RETURNING alias')] = self::mappedViewExpressionValue($term, $new, $old, $viewRow);
        }

        return $row;
    }

    private static function mappedViewExpressionValue(string $expr, array $new, ?array $old, array $viewRow): mixed
    {
        $expr = trim($expr);
        if (str_starts_with($expr, 'new.')) {
            return self::mappedViewRowValue($new, substr($expr, 4), 'NEW row');
        }
        if (str_starts_with($expr, 'old.')) {
            if ($old === null) {
                return null;
            }

            return self::mappedViewRowValue($old, substr($expr, 4), 'OLD row');
        }
        if (str_starts_with($expr, 'view.')) {
            return self::mappedViewRowValue($viewRow, substr($expr, 5), 'view row');
        }

        return self::mappedViewRowValue($new, $expr, 'RETURNING row');
    }

    private static function mappedViewWhenMatches(mixed $when, ?array $old, array $new, array $viewRow): bool
    {
        if ($when === true || $when === null) {
            return true;
        }
        if ($when === false) {
            return false;
        }
        if (!is_array($when) || count($when) !== 3) {
            throw new InvalidArgumentException('SQLite trigger view RETURNING WHEN clause is malformed');
        }
        [$left, $operator, $right] = array_values($when);
        $leftValue = self::mappedViewValue($left, $old, $new, $viewRow);
        $rightValue = self::mappedViewValue($right, $old, $new, $viewRow);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $leftValue == $rightValue,
            '!=', '<>' => $leftValue != $rightValue,
            'IS' => $leftValue === $rightValue,
            'IS NOT' => $leftValue !== $rightValue,
            default => throw new InvalidArgumentException('SQLite trigger view RETURNING WHEN operator is unsupported'),
        };
    }

    private static function mappedViewValue(mixed $value, ?array $old, array $new, array $viewRow): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        if (str_starts_with($value, 'concat:')) {
            $parts = explode(':', substr($value, 7));

            return implode('', array_map(static fn (string $part): string => (string) self::mappedViewValue($part, $old, $new, $viewRow), $parts));
        }
        if (str_starts_with($value, 'new.')) {
            return self::mappedViewRowValue($new, substr($value, 4), 'NEW row');
        }
        if (str_starts_with($value, 'old.')) {
            if ($old === null) {
                return null;
            }

            return self::mappedViewRowValue($old, substr($value, 4), 'OLD row');
        }
        if (str_starts_with($value, 'view.')) {
            return self::mappedViewRowValue($viewRow, substr($value, 5), 'view row');
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $terms
     * @return array<string,mixed>
     */
    private static function projectMappedViewValues(array $terms, ?array $old, array $new, array $viewRow): array
    {
        $row = [];
        foreach ($terms as $column => $value) {
            $row[self::mappedViewIdentifier((string) $column, 'projection column')] = self::mappedViewValue($value, $old, $new, $viewRow);
        }

        return $row;
    }

    private static function mappedViewRowValue(array $row, string $column, string $context): mixed
    {
        self::mappedViewIdentifier($column, $context . ' column');
        if (!array_key_exists($column, $row)) {
            throw new InvalidArgumentException("SQLite trigger view RETURNING {$context} is missing {$column}");
        }

        return $row[$column];
    }

    /**
     * @param array<string,string> $mapping
     */
    private static function validateViewMapping(array $mapping): void
    {
        if ($mapping === []) {
            throw new InvalidArgumentException('SQLite trigger view RETURNING mapping cannot be empty');
        }
        foreach ($mapping as $viewColumn => $baseColumn) {
            self::mappedViewIdentifier((string) $viewColumn, 'view column');
            self::mappedViewIdentifier((string) $baseColumn, 'base column');
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function validateViewColumns(array $columns, string $context): void
    {
        if ($columns === []) {
            throw new InvalidArgumentException("SQLite trigger view RETURNING {$context} list cannot be empty");
        }
        foreach ($columns as $column) {
            self::mappedViewIdentifier((string) $column, $context);
        }
    }

    private static function mappedViewIdentifier(string $value, string $context): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("SQLite trigger view RETURNING {$context} is malformed");
        }

        return $value;
    }

    private static function mappedViewSourceToken(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]+$/', $value)) {
            throw new InvalidArgumentException('SQLite trigger view RETURNING source token is malformed');
        }

        return $value;
    }
}
