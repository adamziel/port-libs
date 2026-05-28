<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext172Plan
{
    /**
     * @param list<array<string,mixed>> $baseRows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool,recursive_triggers?:bool,max_depth?:int,child_suffix?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $baseRows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $key = self::identifier((string) ($options['key'] ?? 'option_name'), 'key column');
        $savepoint = self::token((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_next172'), 'savepoint');
        $admitNext = (bool) ($options['admit_next_source'] ?? false);
        $recursive = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = (int) ($options['max_depth'] ?? 2);
        $childSuffix = self::token((string) ($options['child_suffix'] ?? ':child'), 'child suffix');
        if ($maxDepth < 0 || $maxDepth > 32) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next172 max depth must be between 0 and 32');
        }
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next172 projection cannot be empty');
        }

        $baseRows = self::normalizeRows($baseRows, $key);
        $currentView = self::normalizeView($currentView, 'current view');
        $nextView = self::normalizeView($nextView, 'next view');

        $current = self::runSource($baseRows, $currentInput, $currentView, $returning, $key, $recursive, $maxDepth, $childSuffix, 'current');
        $nextBase = $admitNext ? $current['rows'] : $baseRows;
        $nextAttempt = self::runSource($nextBase, $nextInput, $nextView, $returning, $key, $recursive, $maxDepth, $childSuffix, 'next');

        $attemptedStream = array_merge($current['yield_stream'], $nextAttempt['yield_stream']);
        $visibleStream = $admitNext ? $attemptedStream : $current['yield_stream'];

        return [
            'status' => $admitNext
                ? 'trigger-recursive-view-returning-current-source-next172-next-admitted'
                : 'trigger-recursive-view-returning-current-source-next172-current-pinned',
            'savepoint' => $savepoint,
            'key' => $key,
            'recursive_triggers' => $recursive,
            'max_depth' => $maxDepth,
            'child_suffix' => $childSuffix,
            'current_view' => self::viewSummary($currentView),
            'next_view' => self::viewSummary($nextView),
            'visible_view' => self::viewSummary($admitNext ? $nextView : $currentView),
            'before_rows' => $baseRows,
            'current_rows' => $current['rows'],
            'after_savepoint' => $admitNext ? $nextAttempt['rows'] : $baseRows,
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $admitNext ? $nextAttempt['returning_rows'] : [],
            'attempted_next_returning_rows' => $nextAttempt['returning_rows'],
            'visible_returning_rows' => self::returningOnly($visibleStream),
            'suppressed_returning_rows' => $admitNext ? [] : self::returningOnly($nextAttempt['yield_stream']),
            'current_yield_stream' => $current['yield_stream'],
            'next_yield_stream' => $admitNext ? $nextAttempt['yield_stream'] : [],
            'attempted_next_yield_stream' => $nextAttempt['yield_stream'],
            'attempted_yield_stream' => $attemptedStream,
            'visible_yield_stream' => $visibleStream,
            'current_trigger_effects' => $current['trigger_effects'],
            'next_trigger_effects' => $admitNext ? $nextAttempt['trigger_effects'] : [],
            'attempted_next_trigger_effects' => $nextAttempt['trigger_effects'],
            'current_changes' => $current['changes'],
            'next_changes' => $admitNext ? $nextAttempt['changes'] : 0,
            'attempted_next_changes' => $nextAttempt['changes'],
            'changes' => $admitNext ? $current['changes'] + $nextAttempt['changes'] : 0,
            'statement_rows' => $current['statement_rows'] + ($admitNext ? $nextAttempt['statement_rows'] : 0),
            'attempted_statement_rows' => $current['statement_rows'] + $nextAttempt['statement_rows'],
            'recursive_rows' => $current['recursive_rows'] + ($admitNext ? $nextAttempt['recursive_rows'] : 0),
            'attempted_recursive_rows' => $current['recursive_rows'] + $nextAttempt['recursive_rows'],
            'source_transition' => [
                'current_source' => $currentView['source'],
                'current_trigger_source' => $currentView['trigger_source'],
                'next_source' => $nextView['source'],
                'next_trigger_source' => $nextView['trigger_source'],
                'next_started_from' => $admitNext ? 'current-trigger-output' : 'savepoint-current-source',
                'current_returning_visibility' => 'drained-before-next-source',
                'next_returning_visibility' => $admitNext ? 'admitted-after-current-drain' : 'attempted-only-current-source-pinned',
                'visible_source' => $admitNext ? $nextView['source'] : $currentView['source'],
            ],
            'dependency_closure' => 'no-new-support-component-reuses-native-view-trigger-recursion-returning-current-source',
            'dependencies' => [
                'sqlite-trigger-recursive-view-returning-current-source-next172',
                'sqlite-instead-of-view-trigger-recursive-current-source-drain',
                'sqlite-returning-yield-before-next-view-source-admission',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $input
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string} $view
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int,statement_rows:int,recursive_rows:int}
     */
    private static function runSource(array $rows, array $input, array $view, array $returning, string $key, bool $recursive, int $maxDepth, string $childSuffix, string $phase): array
    {
        $yield = [];
        $returningRows = [];
        $effects = [];
        $changes = 0;
        $recursiveRows = 0;
        $queue = [];

        foreach (array_values($input) as $ordinal => $viewRow) {
            if (!is_array($viewRow)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next172 input row must be an array');
            }
            $queue[] = [self::projectViewRow($viewRow, $view), (int) $ordinal, 0, 'statement'];
        }

        while ($queue !== []) {
            [$incoming, $ordinal, $depth, $origin] = array_shift($queue);
            $index = self::rowIndex($rows, $incoming, $key);
            $old = $index === null ? null : $rows[$index];
            $event = $old === null ? 'insert' : 'update';
            $new = $old === null ? $incoming : array_replace($old, $incoming);
            $new['generation'] = $depth;
            $new['trigger_source'] = $view['trigger_source'];
            $new['source_phase'] = $phase;
            $new['origin'] = $origin;
            if ($index === null) {
                $rows[] = $new;
            } else {
                $rows[$index] = $new;
            }

            $returningRow = self::returningRow($returning, $new, $old, $incoming, $event, $ordinal, $depth, $view['trigger_source']);
            $envelope = [
                'phase' => $phase,
                'source' => $view['source'],
                'trigger_source' => $view['trigger_source'],
                'view' => $view['name'],
                'trigger' => $view['trigger'],
                'audit_label' => $view['audit_label'],
                'ordinal' => $ordinal,
                'depth' => $depth,
                'origin' => $origin,
                'event' => $event,
                'returning' => $returningRow,
            ];
            $yield[] = $envelope;
            $returningRows[] = $envelope;
            $effects[] = $envelope + [
                'option_name' => $new[$key],
                'old_value' => $old['option_value'] ?? null,
                'new_value' => $new['option_value'] ?? null,
            ];
            ++$changes;
            if ($origin === 'recursive') {
                ++$recursiveRows;
            }

            if ($recursive && $depth < $maxDepth && (($incoming['spawn_child'] ?? true) !== false)) {
                $child = $new;
                $child[$key] = (string) $new[$key] . $childSuffix;
                $child['option_value'] = (string) ($new['option_value'] ?? '') . $childSuffix;
                $child['parent_option'] = $new[$key];
                $queue[] = [$child, $ordinal, $depth + 1, 'recursive'];
            }
        }

        return [
            'rows' => array_values($rows),
            'yield_stream' => $yield,
            'returning_rows' => $returningRows,
            'trigger_effects' => $effects,
            'changes' => $changes,
            'statement_rows' => count($input),
            'recursive_rows' => $recursiveRows,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function rowIndex(array $rows, array $incoming, string $key): ?int
    {
        if (!array_key_exists($key, $incoming)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next172 incoming row key {$key} is missing");
        }
        foreach ($rows as $index => $row) {
            if (($row[$key] ?? null) == $incoming[$key]) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function normalizeRows(array $rows, string $key): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next172 rows must be a list');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next172 row key {$key} is missing");
            }
            $value = (string) $row[$key];
            if (isset($seen[$value])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next172 duplicate key {$value}");
            }
            $seen[$value] = true;
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string}
     */
    private static function normalizeView(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        $mapping = $view['mapping'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next172 {$label} columns must be a non-empty list");
        }
        if (!is_array($mapping) || $mapping === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next172 {$label} mapping must not be empty");
        }
        $normalized = [
            'name' => self::identifier((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::token((string) ($view['source'] ?? ''), $label . ' source'),
            'trigger' => self::identifier((string) ($view['trigger'] ?? ''), $label . ' trigger'),
            'trigger_source' => self::token((string) ($view['trigger_source'] ?? ''), $label . ' trigger source'),
            'columns' => array_map(static fn (mixed $column): string => self::identifier((string) $column, $label . ' column'), $columns),
            'mapping' => [],
            'audit_label' => self::token((string) ($view['audit_label'] ?? $label), $label . ' audit label'),
        ];
        foreach ($mapping as $viewColumn => $tableColumn) {
            $viewColumn = self::identifier((string) $viewColumn, $label . ' mapping view column');
            if (!in_array($viewColumn, $normalized['columns'], true)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next172 mapping column {$viewColumn} is not in the view");
            }
            $normalized['mapping'][$viewColumn] = self::identifier((string) $tableColumn, $label . ' mapping table column');
        }

        return $normalized;
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function projectViewRow(array $viewRow, array $view): array
    {
        $incoming = [];
        foreach ($view['mapping'] as $viewColumn => $tableColumn) {
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next172 row is missing view column {$viewColumn}");
            }
            $incoming[$tableColumn] = $viewRow[$viewColumn];
        }
        if (array_key_exists('spawn_child', $viewRow)) {
            $incoming['spawn_child'] = (bool) $viewRow['spawn_child'];
        }

        return $incoming;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $new, ?array $old, array $incoming, string $event, int $ordinal, int $depth, string $triggerSource): array
    {
        $row = [];
        foreach ($returning as $index => $expr) {
            if (is_callable($expr)) {
                $row['expr' . $index] = $expr($new, $old, $incoming, $event, $ordinal, $depth, $triggerSource);
                continue;
            }
            $alias = null;
            if (is_array($expr)) {
                $alias = isset($expr['as']) ? self::identifier((string) $expr['as'], 'RETURNING alias') : null;
                $expr = (string) ($expr['expr'] ?? '');
            }
            $expr = trim((string) $expr);
            $column = $alias ?? self::identifier(str_replace('.', '_', $expr), 'RETURNING expression');
            $row[$column] = match (true) {
                str_starts_with($expr, 'new.') => $new[substr($expr, 4)] ?? null,
                str_starts_with($expr, 'old.') => $old === null ? null : ($old[substr($expr, 4)] ?? null),
                str_starts_with($expr, 'incoming.') => $incoming[substr($expr, 9)] ?? null,
                $expr === 'event' => $event,
                $expr === 'ordinal' => $ordinal,
                $expr === 'depth' => $depth,
                $expr === 'trigger_source' => $triggerSource,
                default => $new[$expr] ?? $incoming[$expr] ?? null,
            };
        }

        return $row;
    }

    /**
     * @param list<array<string,mixed>> $stream
     * @return list<array<string,mixed>>
     */
    private static function returningOnly(array $stream): array
    {
        return array_values(array_map(static fn (array $row): array => (array) $row['returning'], $stream));
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function viewSummary(array $view): array
    {
        return [
            'name' => $view['name'],
            'source' => $view['source'],
            'trigger' => $view['trigger'],
            'trigger_source' => $view['trigger_source'],
            'columns' => $view['columns'],
            'mapping' => $view['mapping'],
            'audit_label' => $view['audit_label'],
        ];
    }

    private static function identifier(string $value, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next172 invalid {$label}: {$value}");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value)) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next172 invalid {$label}: {$value}");
        }

        return $value;
    }
}
