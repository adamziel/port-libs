<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext161Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $key = self::identifier((string) ($options['key'] ?? 'option_name'), 'key');
        $savepoint = self::token((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_161'), 'savepoint');
        $recursive = (bool) ($options['recursive_triggers'] ?? true);
        $maxDepth = (int) ($options['max_depth'] ?? 2);
        if ($maxDepth < 0) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next161 max depth must be non-negative');
        }
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next161 projection cannot be empty');
        }

        $baseRows = self::normalizeRows($rows, $key);
        $currentView = self::normalizeView($currentView, 'current view');
        $nextView = self::normalizeView($nextView, 'next view');
        $admitNext = (bool) ($options['admit_next_source'] ?? false);

        $current = self::runViewSource($baseRows, $currentInput, $currentView, $returning, $key, $recursive, $maxDepth, 'current');
        $nextAttempt = self::runViewSource($admitNext ? $current['rows'] : $baseRows, $nextInput, $nextView, $returning, $key, $recursive, $maxDepth, 'next');

        return [
            'status' => $admitNext
                ? 'trigger-recursive-view-returning-next-source-admitted-next161'
                : 'trigger-recursive-view-returning-current-source-pinned-next161',
            'savepoint' => $savepoint,
            'key' => $key,
            'recursive_triggers' => $recursive,
            'max_depth' => $maxDepth,
            'current_view' => self::viewSummary($currentView),
            'next_view' => self::viewSummary($nextView),
            'visible_view' => self::viewSummary($admitNext ? $nextView : $currentView),
            'before_rows' => $baseRows,
            'current_rows' => $current['rows'],
            'after_savepoint' => $admitNext ? $nextAttempt['rows'] : $baseRows,
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $admitNext ? $nextAttempt['returning_rows'] : [],
            'attempted_next_returning_rows' => $nextAttempt['returning_rows'],
            'current_yield_stream' => $current['yield_stream'],
            'next_yield_stream' => $admitNext ? $nextAttempt['yield_stream'] : [],
            'attempted_next_yield_stream' => $nextAttempt['yield_stream'],
            'current_trigger_effects' => $current['trigger_effects'],
            'next_trigger_effects' => $admitNext ? $nextAttempt['trigger_effects'] : [],
            'attempted_next_trigger_effects' => $nextAttempt['trigger_effects'],
            'current_recursive_edges' => $current['recursive_edges'],
            'next_recursive_edges' => $admitNext ? $nextAttempt['recursive_edges'] : [],
            'attempted_next_recursive_edges' => $nextAttempt['recursive_edges'],
            'current_changes' => $current['changes'],
            'next_changes' => $admitNext ? $nextAttempt['changes'] : 0,
            'attempted_next_changes' => $nextAttempt['changes'],
            'changes' => $admitNext ? $current['changes'] + $nextAttempt['changes'] : 0,
            'statement_rows' => $current['statement_rows'] + ($admitNext ? $nextAttempt['statement_rows'] : 0),
            'attempted_statement_rows' => $current['statement_rows'] + $nextAttempt['statement_rows'],
            'next_source_admitted' => $admitNext,
            'trigger_source_changed' => $currentView['trigger_source'] !== $nextView['trigger_source'],
            'yield_boundary' => $admitNext
                ? 'recursive-view-returning-next-source-admitted-after-current-drain'
                : 'recursive-view-returning-current-source-drained-before-next-source',
            'dependencies' => [
                'sqlite-trigger-recursive-view-returning-current-source-next161',
                'sqlite-instead-of-view-trigger-recursive-returning',
                'sqlite-current-view-source-returning-drain',
                'sqlite-next-view-source-attempted-only',
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
            throw new InvalidArgumentException('SQLite recursive view RETURNING next161 rows must be a list');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next161 row key {$key} is missing");
            }
            $value = (string) $row[$key];
            if (isset($seen[$value])) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next161 duplicate key {$value}");
            }
            $seen[$value] = true;
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string}
     */
    private static function normalizeView(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        $mapping = $view['mapping'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} columns must be a non-empty list");
        }
        if (!is_array($mapping) || $mapping === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} mapping must not be empty");
        }

        $normalized = [
            'name' => self::identifier((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::token((string) ($view['source'] ?? ''), $label . ' source'),
            'trigger' => self::identifier((string) ($view['trigger'] ?? ''), $label . ' trigger'),
            'trigger_source' => self::token((string) ($view['trigger_source'] ?? ''), $label . ' trigger source'),
            'columns' => array_map(static fn (mixed $column): string => self::identifier((string) $column, $label . ' column'), $columns),
            'mapping' => [],
            'recursive_column' => self::identifier((string) ($view['recursive_column'] ?? 'name'), $label . ' recursive column'),
            'recursive_suffix' => self::token((string) ($view['recursive_suffix'] ?? '_child'), $label . ' recursive suffix'),
            'audit_label' => self::token((string) ($view['audit_label'] ?? $label), $label . ' audit label'),
        ];
        foreach ($mapping as $viewColumn => $tableColumn) {
            $viewColumn = self::identifier((string) $viewColumn, $label . ' mapping view column');
            if (!in_array($viewColumn, $normalized['columns'], true)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} mapping column {$viewColumn} is not visible");
            }
            $normalized['mapping'][$viewColumn] = self::identifier((string) $tableColumn, $label . ' mapping table column');
        }
        if (!array_key_exists($normalized['recursive_column'], $normalized['mapping'])) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} recursive column is not mapped");
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $input
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,recursive_edges:list<array<string,mixed>>,changes:int,statement_rows:int}
     */
    private static function runViewSource(array $rows, array $input, array $view, array $returning, string $key, bool $recursive, int $maxDepth, string $phase): array
    {
        $queue = [];
        foreach (array_values($input) as $ordinal => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING next161 input row must be an array');
            }
            $queue[] = ['view_row' => $row, 'ordinal' => (int) $ordinal, 'depth' => 0, 'parent' => null];
        }

        $yield = [];
        $returningRows = [];
        $effects = [];
        $edges = [];
        $changes = 0;
        $statementRows = count($queue);

        while ($queue !== []) {
            $item = array_shift($queue);
            $incoming = self::projectViewRow($item['view_row'], $view);
            $rowKey = (string) ($incoming[$key] ?? '');
            if ($rowKey === '') {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next161 projected key {$key} is empty");
            }
            $event = self::rowIndex($rows, $key, $rowKey) === null ? 'insert' : 'update';
            $rows = self::upsertRow($rows, $key, $incoming);
            $returningRow = self::returningRow($returning, $incoming, $item['view_row'], $event, (int) $item['ordinal'], (int) $item['depth'], $view['trigger_source']);
            $envelope = self::returningEnvelope($phase, $view, (int) $item['ordinal'], (int) $item['depth'], $event, $returningRow);
            $returningRows[] = $envelope;
            $yield[] = $envelope + [
                'status' => 'changed',
                'view_row' => $item['view_row'],
                'incoming_row' => $incoming,
                'parent_key' => $item['parent'],
            ];
            $effects[] = [
                'phase' => $phase,
                'ordinal' => (int) $item['ordinal'],
                'depth' => (int) $item['depth'],
                'trigger' => $view['trigger'],
                'trigger_source' => $view['trigger_source'],
                'audit_label' => $view['audit_label'],
                'event' => $event,
                'row_key' => $rowKey,
                'parent_key' => $item['parent'],
            ];
            ++$changes;

            if (!$recursive || (int) $item['depth'] >= $maxDepth || ($item['view_row']['spawn_child'] ?? true) === false) {
                continue;
            }

            $childViewRow = self::childViewRow($item['view_row'], $view);
            $queue[] = [
                'view_row' => $childViewRow,
                'ordinal' => $statementRows,
                'depth' => (int) $item['depth'] + 1,
                'parent' => $rowKey,
            ];
            $edges[] = [
                'phase' => $phase,
                'parent_key' => $rowKey,
                'child_key' => (string) $childViewRow[$view['recursive_column']],
                'parent_depth' => (int) $item['depth'],
                'child_depth' => (int) $item['depth'] + 1,
                'source' => $view['source'],
                'trigger_source' => $view['trigger_source'],
            ];
            ++$statementRows;
        }

        return [
            'rows' => array_values($rows),
            'yield_stream' => $yield,
            'returning_rows' => $returningRows,
            'trigger_effects' => $effects,
            'recursive_edges' => $edges,
            'changes' => $changes,
            'statement_rows' => $statementRows,
        ];
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function projectViewRow(array $viewRow, array $view): array
    {
        $row = [];
        foreach ($view['mapping'] as $viewColumn => $tableColumn) {
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING next161 missing view column {$viewColumn}");
            }
            $row[$tableColumn] = $viewRow[$viewColumn];
        }
        $row['source'] = $viewRow['origin'] ?? $view['trigger_source'];

        return $row;
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function childViewRow(array $viewRow, array $view): array
    {
        $child = $viewRow;
        $column = $view['recursive_column'];
        $child[$column] = (string) $viewRow[$column] . $view['recursive_suffix'];
        if (array_key_exists('value', $child)) {
            $child['value'] = (string) $child['value'] . '/child';
        }
        if (array_key_exists('origin', $child)) {
            $child['origin'] = (string) $child['origin'] . '/recursive';
        }

        return $child;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function upsertRow(array $rows, string $key, array $incoming): array
    {
        $index = self::rowIndex($rows, $key, (string) $incoming[$key]);
        if ($index === null) {
            $rows[] = $incoming;
            return $rows;
        }
        $rows[$index] = array_replace($rows[$index], $incoming);

        return $rows;
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

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $new, array $viewRow, string $event, int $ordinal, int $depth, string $source): array
    {
        $out = [];
        foreach (array_values($returning) as $index => $expr) {
            if (is_string($expr)) {
                $alias = str_starts_with($expr, 'new.') ? substr($expr, 4) : $expr;
                $out[$alias] = self::exprValue($expr, $new, $viewRow, $event, $ordinal, $depth, $source);
                continue;
            }
            if (is_array($expr)) {
                $sql = (string) ($expr['expr'] ?? '');
                $alias = (string) ($expr['as'] ?? (str_starts_with($sql, 'new.') ? substr($sql, 4) : $sql));
                $out[self::identifier($alias, 'RETURNING alias')] = self::exprValue($sql, $new, $viewRow, $event, $ordinal, $depth, $source);
                continue;
            }
            if (is_callable($expr)) {
                $out['expr' . $index] = $expr($new, $viewRow, $event, $ordinal, $depth, $source);
                continue;
            }
            throw new InvalidArgumentException('SQLite recursive view RETURNING next161 projection expression is unsupported');
        }

        return $out;
    }

    private static function exprValue(string $expr, array $new, array $viewRow, string $event, int $ordinal, int $depth, string $source): mixed
    {
        return match ($expr) {
            'event' => $event,
            'ordinal' => $ordinal,
            'depth' => $depth,
            'trigger_source', 'source' => $source,
            default => str_starts_with($expr, 'new.')
                ? ($new[substr($expr, 4)] ?? null)
                : (str_starts_with($expr, 'view.')
                    ? ($viewRow[substr($expr, 5)] ?? null)
                    : ($new[$expr] ?? null)),
        };
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function returningEnvelope(string $phase, array $view, int $ordinal, int $depth, string $event, array $returning): array
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

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column:string,recursive_suffix:string,audit_label:string} $view
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
            'recursive_column' => $view['recursive_column'],
            'recursive_suffix' => $view['recursive_suffix'],
            'audit_label' => $view['audit_label'],
        ];
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} is malformed");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next161 {$label} is malformed");
        }

        return $value;
    }
}
