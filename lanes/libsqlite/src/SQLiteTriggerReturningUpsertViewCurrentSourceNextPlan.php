<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label?:string} $nextView
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>,string):mixed> $assignments
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,admit_next_source?:bool} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $uniqueColumns,
        array $assignments,
        array $returning,
        array $options = [],
    ): array {
        $key = self::identifier((string) ($options['key'] ?? 'option_name'), 'key column');
        $savepoint = self::token((string) ($options['savepoint'] ?? 'wp_view_trigger_upsert_next149'), 'savepoint');
        $admitNext = (bool) ($options['admit_next_source'] ?? false);
        self::validateColumns($uniqueColumns, 'unique column');
        self::validateColumns(array_keys($assignments), 'assignment column');
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite trigger RETURNING UPSERT view current-source next149 projection cannot be empty');
        }

        $baseRows = self::normalizeRows($rows, $key);
        $currentView = self::normalizeView($currentView, 'current view');
        $nextView = self::normalizeView($nextView, 'next view');

        $current = self::runSource($baseRows, $currentInput, $currentView, $uniqueColumns, $assignments, $returning, 'current');
        $nextBase = $admitNext ? $current['rows'] : $baseRows;
        $nextAttempt = self::runSource($nextBase, $nextInput, $nextView, $uniqueColumns, $assignments, $returning, 'next');

        return [
            'status' => $admitNext
                ? 'trigger-returning-upsert-view-next-source-admitted-next149'
                : 'trigger-returning-upsert-view-current-source-pinned-next149',
            'savepoint' => $savepoint,
            'key' => $key,
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
            'current_skipped_rows' => $current['skipped_rows'],
            'next_skipped_rows' => $admitNext ? $nextAttempt['skipped_rows'] : [],
            'attempted_next_skipped_rows' => $nextAttempt['skipped_rows'],
            'trigger_source_changed' => $currentView['trigger_source'] !== $nextView['trigger_source'],
            'next_source_admitted' => $admitNext,
            'changes' => $admitNext ? $current['changes'] + $nextAttempt['changes'] : 0,
            'current_changes' => $current['changes'],
            'next_changes' => $admitNext ? $nextAttempt['changes'] : 0,
            'attempted_next_changes' => $nextAttempt['changes'],
            'statement_rows' => $current['statement_rows'] + ($admitNext ? $nextAttempt['statement_rows'] : 0),
            'attempted_statement_rows' => $current['statement_rows'] + $nextAttempt['statement_rows'],
            'yield_boundary' => $admitNext
                ? 'instead-of-view-trigger-next-source-admitted-after-current-drain'
                : 'instead-of-view-trigger-current-source-drained-before-next-trigger-source',
            'dependencies' => [
                'sqlite-trigger-returning-upsert-view-current-source-next149',
                'sqlite-instead-of-view-trigger-source-pinning',
                'sqlite-upsert-returning-current-source-drain',
                'sqlite-next-trigger-source-attempted-only',
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
            throw new InvalidArgumentException('SQLite trigger RETURNING UPSERT view next149 rows must be a list');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                throw new InvalidArgumentException("SQLite trigger RETURNING UPSERT view next149 row key {$key} is missing");
            }
            $value = (string) $row[$key];
            if (isset($seen[$value])) {
                throw new InvalidArgumentException("SQLite trigger RETURNING UPSERT view next149 duplicate key {$value}");
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
            throw new InvalidArgumentException("SQLite trigger RETURNING UPSERT view {$label} columns must be a non-empty list");
        }
        if (!is_array($mapping) || $mapping === []) {
            throw new InvalidArgumentException("SQLite trigger RETURNING UPSERT view {$label} mapping must not be empty");
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
                throw new InvalidArgumentException("SQLite trigger RETURNING UPSERT view {$label} mapping column {$viewColumn} is not in the view");
            }
            $normalized['mapping'][$viewColumn] = self::identifier((string) $tableColumn, $label . ' mapping table column');
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $input
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string} $view
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>,string):mixed> $assignments
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,string):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,changes:int,statement_rows:int}
     */
    private static function runSource(array $rows, array $input, array $view, array $uniqueColumns, array $assignments, array $returning, string $phase): array
    {
        $yield = [];
        $returningRows = [];
        $effects = [];
        $skipped = [];
        $changes = 0;

        foreach (array_values($input) as $ordinal => $viewRow) {
            if (!is_array($viewRow)) {
                throw new InvalidArgumentException('SQLite trigger RETURNING UPSERT view next149 input row must be an array');
            }
            $incoming = self::projectViewRow($viewRow, $view);
            $index = self::conflictIndex($rows, $incoming, $uniqueColumns);
            $old = $index === null ? null : $rows[$index];
            $event = $old === null ? 'insert' : 'update';
            if (($incoming['_raise_ignore'] ?? false) === true) {
                $skippedRow = self::baseYield($phase, $view, (int) $ordinal, $event, 'skipped-raise-ignore', $viewRow, $incoming, $old, null);
                $skipped[] = $skippedRow;
                $yield[] = $skippedRow + ['returning' => null, 'changed' => false];
                continue;
            }

            $new = $old === null ? $incoming : array_replace($old, self::assignedValues($old, $incoming, $assignments, $phase));
            unset($new['_raise_ignore']);
            if ($index === null) {
                $rows[] = $new;
            } else {
                $rows[$index] = $new;
            }

            $returningRow = self::returningRow($returning, $new, $old, $incoming, $event, (int) $ordinal, $view['trigger_source']);
            $returningRows[] = self::returningEnvelope($phase, $view, (int) $ordinal, $event, $returningRow);
            $yield[] = self::baseYield($phase, $view, (int) $ordinal, $event, 'changed', $viewRow, $incoming, $old, $new) + [
                'returning' => $returningRow,
                'changed' => true,
            ];
            $effects[] = [
                'phase' => $phase,
                'ordinal' => (int) $ordinal,
                'trigger' => $view['trigger'],
                'trigger_source' => $view['trigger_source'],
                'audit_label' => $view['audit_label'],
                'event' => $event,
                'option_name' => $new['option_name'] ?? null,
                'old_option_value' => $old['option_value'] ?? null,
                'new_option_value' => $new['option_value'] ?? null,
            ];
            ++$changes;
        }

        return [
            'rows' => array_values($rows),
            'yield_stream' => $yield,
            'returning_rows' => $returningRows,
            'trigger_effects' => $effects,
            'skipped_rows' => $skipped,
            'changes' => $changes,
            'statement_rows' => count($input),
        ];
    }

    /**
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string} $view
     * @return array<string,mixed>
     */
    private static function baseYield(string $phase, array $view, int $ordinal, string $event, string $status, array $viewRow, array $incoming, ?array $old, ?array $new): array
    {
        return [
            'phase' => $phase,
            'source' => $view['source'],
            'trigger_source' => $view['trigger_source'],
            'view' => $view['name'],
            'trigger' => $view['trigger'],
            'ordinal' => $ordinal,
            'event' => $event,
            'status' => $status,
            'view_row' => $viewRow,
            'incoming_row' => $incoming,
            'current_row' => $old,
            'next_row' => $new,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function returningEnvelope(string $phase, array $view, int $ordinal, string $event, array $returning): array
    {
        return [
            'phase' => $phase,
            'source' => $view['source'],
            'trigger_source' => $view['trigger_source'],
            'view' => $view['name'],
            'trigger' => $view['trigger'],
            'ordinal' => $ordinal,
            'event' => $event,
            'returning' => $returning,
        ];
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
                throw new InvalidArgumentException("SQLite trigger RETURNING UPSERT view next149 row is missing view column {$viewColumn}");
            }
            $incoming[$tableColumn] = $viewRow[$viewColumn];
        }
        if (($viewRow['_raise_ignore'] ?? false) === true) {
            $incoming['_raise_ignore'] = true;
        }

        return $incoming;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     */
    private static function conflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new InvalidArgumentException("SQLite trigger RETURNING UPSERT view next149 unique column {$column} is missing");
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
     * @param array<string,callable(array<string,mixed>,array<string,mixed>,string):mixed> $assignments
     * @return array<string,mixed>
     */
    private static function assignedValues(array $old, array $incoming, array $assignments, string $phase): array
    {
        $values = [];
        foreach ($assignments as $column => $callback) {
            $values[$column] = $callback($old, $incoming, $phase);
        }

        return $values;
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $new, ?array $old, array $incoming, string $event, int $ordinal, string $triggerSource): array
    {
        $row = [];
        foreach ($returning as $index => $expr) {
            if (is_callable($expr)) {
                $row['expr' . $index] = $expr($new, $old, $incoming, $event, $ordinal, $triggerSource);
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
                str_starts_with($expr, 'excluded.') => $incoming[substr($expr, 9)] ?? null,
                $expr === 'event' => $event,
                $expr === 'ordinal' => $ordinal,
                $expr === 'trigger_source' => $triggerSource,
                default => $new[$expr] ?? $incoming[$expr] ?? null,
            };
        }

        return $row;
    }

    /**
     * @param list<string> $columns
     */
    private static function validateColumns(array $columns, string $label): void
    {
        if ($columns === [] || !array_is_list($columns)) {
            throw new InvalidArgumentException("SQLite trigger RETURNING UPSERT view next149 {$label} list must not be empty");
        }
        foreach ($columns as $column) {
            self::identifier((string) $column, $label);
        }
    }

    /**
     * @return array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,audit_label:string}
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
            throw new InvalidArgumentException("SQLite trigger RETURNING UPSERT view next149 invalid {$label}: {$value}");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/\s/', $value)) {
            throw new InvalidArgumentException("SQLite trigger RETURNING UPSERT view next149 invalid {$label}: {$value}");
        }

        return $value;
    }
}
