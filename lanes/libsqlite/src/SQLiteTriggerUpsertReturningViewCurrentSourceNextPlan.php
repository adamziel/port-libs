<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentViewRows
     * @param list<array<string,mixed>> $nextViewRows
     * @param array{name:string,source:string,columns:list<string>,mapping:array<string,string>,where?:callable(array<string,mixed>,array<string,mixed>):bool} $currentView
     * @param array{name:string,source:string,columns:list<string>,mapping:array<string,string>,where?:callable(array<string,mixed>,array<string,mixed>):bool} $nextView
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,trigger?:string,release_current?:bool} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentViewRows,
        array $nextViewRows,
        array $currentView,
        array $nextView,
        array $uniqueColumns,
        array $assignments,
        array $returning,
        array $options = [],
    ): array {
        $key = self::identifier((string) ($options['key'] ?? 'option_name'), 'key column');
        $savepoint = self::token((string) ($options['savepoint'] ?? 'wp_view_upsert'), 'savepoint');
        $trigger = self::identifier((string) ($options['trigger'] ?? 'wp_options_view_io_upsert'), 'trigger');
        $releaseCurrent = (bool) ($options['release_current'] ?? false);
        self::validateColumns($uniqueColumns, 'unique column');
        self::validateColumns(array_keys($assignments), 'assignment column');
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite trigger UPSERT RETURNING view current-source projection cannot be empty');
        }

        $baseRows = self::normalizeRows($rows, $key);
        $currentView = self::normalizeView($currentView, 'current view');
        $nextView = self::normalizeView($nextView, 'next view');
        $current = self::runPhase($baseRows, $currentViewRows, $currentView, $uniqueColumns, $assignments, $returning, 'current', $trigger);

        if ($releaseCurrent) {
            $next = self::runPhase($current['rows'], $nextViewRows, $nextView, $uniqueColumns, $assignments, $returning, 'next', $trigger);
            $finalRows = $next['rows'];
            $visibleView = $nextView;
            $status = 'trigger-upsert-returning-view-next-source-admitted-next144';
        } else {
            $attemptedNext = self::runPhase($baseRows, $nextViewRows, $nextView, $uniqueColumns, $assignments, $returning, 'next', $trigger);
            $next = [
                'rows' => $baseRows,
                'attempted_rows' => $attemptedNext['attempted_rows'],
                'yield_stream' => [],
                'returning_rows' => [],
                'skipped_rows' => [],
                'changes' => 0,
                'statement_rows' => 0,
                'attempted_next' => $attemptedNext,
            ];
            $finalRows = $baseRows;
            $visibleView = $currentView;
            $status = 'trigger-upsert-returning-view-current-source-retained-next144';
        }

        return [
            'status' => $status,
            'savepoint' => $savepoint,
            'trigger' => $trigger,
            'key' => $key,
            'current_view' => self::viewSummary($currentView),
            'next_view' => self::viewSummary($nextView),
            'visible_view' => self::viewSummary($visibleView),
            'before_rows' => $baseRows,
            'current_rows' => $current['rows'],
            'after_savepoint' => $finalRows,
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $next['returning_rows'],
            'attempted_next_returning_rows' => $next['attempted_next']['returning_rows'] ?? $next['returning_rows'],
            'yield_stream' => array_merge($current['yield_stream'], $next['yield_stream']),
            'current_yield_stream' => $current['yield_stream'],
            'next_yield_stream' => $next['yield_stream'],
            'attempted_next_yield_stream' => $next['attempted_next']['yield_stream'] ?? $next['yield_stream'],
            'current_skipped_rows' => $current['skipped_rows'],
            'next_skipped_rows' => $next['skipped_rows'],
            'attempted_next_skipped_rows' => $next['attempted_next']['skipped_rows'] ?? $next['skipped_rows'],
            'next_source_admitted' => $releaseCurrent,
            'returning_suppressed_for_skipped_count' => count($current['skipped_rows']) + count($next['skipped_rows']),
            'changes' => $releaseCurrent ? $current['changes'] + $next['changes'] : 0,
            'current_changes' => $current['changes'],
            'next_changes' => $next['changes'],
            'statement_rows' => $current['statement_rows'] + ($releaseCurrent ? $next['statement_rows'] : 0),
            'attempted_statement_rows' => $current['statement_rows'] + (($next['attempted_next']['statement_rows'] ?? $next['statement_rows'])),
            'yield_boundary' => $releaseCurrent
                ? 'view-upsert-returning-release-admits-next-source'
                : 'view-upsert-returning-current-source-retained-before-next-source',
            'dependencies' => [
                'sqlite-trigger-upsert-returning-view-current-source-next144',
                'sqlite-instead-of-view-trigger-upsert-returning',
                'sqlite-upsert-do-update-where-skips-returning',
                'sqlite-current-next-view-source-mapping',
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
            throw new InvalidArgumentException('SQLite trigger UPSERT RETURNING view rows must be a list');
        }
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view row key {$key} is missing");
            }
            $value = (string) $row[$key];
            if (isset($seen[$value])) {
                throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view duplicate key {$value}");
            }
            $seen[$value] = true;
        }

        return array_values($rows);
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,source:string,columns:list<string>,mapping:array<string,string>,where?:callable(array<string,mixed>,array<string,mixed>):bool}
     */
    private static function normalizeView(array $view, string $label): array
    {
        $columns = $view['columns'] ?? null;
        $mapping = $view['mapping'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view {$label} columns must be a non-empty list");
        }
        if (!is_array($mapping) || $mapping === []) {
            throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view {$label} mapping must not be empty");
        }
        $normalized = [
            'name' => self::identifier((string) ($view['name'] ?? ''), $label . ' name'),
            'source' => self::token((string) ($view['source'] ?? ''), $label . ' source'),
            'columns' => array_map(static fn (mixed $column): string => self::identifier((string) $column, $label . ' column'), $columns),
            'mapping' => [],
        ];
        foreach ($mapping as $viewColumn => $tableColumn) {
            $viewColumn = self::identifier((string) $viewColumn, $label . ' mapping view column');
            if (!in_array($viewColumn, $normalized['columns'], true)) {
                throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view {$label} mapping column {$viewColumn} is not in the view");
            }
            $normalized['mapping'][$viewColumn] = self::identifier((string) $tableColumn, $label . ' mapping table column');
        }
        if (array_key_exists('where', $view)) {
            if (!is_callable($view['where'])) {
                throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view {$label} WHERE callback is malformed");
            }
            $normalized['where'] = $view['where'];
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $viewRows
     * @param array{name:string,source:string,columns:list<string>,mapping:array<string,string>,where?:callable(array<string,mixed>,array<string,mixed>):bool} $view
     * @param list<string> $uniqueColumns
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,string):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,attempted_rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,skipped_rows:list<array<string,mixed>>,changes:int,statement_rows:int}
     */
    private static function runPhase(array $rows, array $viewRows, array $view, array $uniqueColumns, array $assignments, array $returning, string $phase, string $trigger): array
    {
        $yield = [];
        $returningRows = [];
        $skipped = [];
        $changes = 0;

        foreach (array_values($viewRows) as $ordinal => $viewRow) {
            if (!is_array($viewRow)) {
                throw new InvalidArgumentException('SQLite trigger UPSERT RETURNING view input row must be an array');
            }
            $incoming = self::projectViewRow($viewRow, $view);
            $index = self::conflictIndex($rows, $incoming, $uniqueColumns);
            $old = $index === null ? null : $rows[$index];
            $event = $old === null ? 'insert' : 'update';
            $new = $old === null ? $incoming : array_replace($old, self::assignedValues($old, $incoming, $assignments));
            $wherePasses = !isset($view['where']) || $view['where']($old ?? [], $incoming);

            if ($old !== null && !$wherePasses) {
                $skippedRow = [
                    'phase' => $phase,
                    'source' => $view['source'],
                    'view' => $view['name'],
                    'ordinal' => (int) $ordinal,
                    'event' => 'update',
                    'status' => 'skipped-do-update-where',
                    'view_row' => $viewRow,
                    'incoming_row' => $incoming,
                    'current_row' => $old,
                ];
                $skipped[] = $skippedRow;
                $yield[] = $skippedRow + ['returning' => null, 'changed' => false, 'trigger' => $trigger];
                continue;
            }

            if ($index === null) {
                $rows[] = $new;
            } else {
                $rows[$index] = $new;
            }
            $returningRow = self::returningRow($returning, $new, $old, $incoming, $event, (int) $ordinal, $view['source']);
            $returningRows[] = [
                'phase' => $phase,
                'source' => $view['source'],
                'view' => $view['name'],
                'ordinal' => (int) $ordinal,
                'event' => $event,
                'returning' => $returningRow,
            ];
            $yield[] = [
                'phase' => $phase,
                'source' => $view['source'],
                'view' => $view['name'],
                'ordinal' => (int) $ordinal,
                'event' => $event,
                'status' => 'changed',
                'trigger' => $trigger,
                'view_row' => $viewRow,
                'incoming_row' => $incoming,
                'current_row' => $old,
                'next_row' => $new,
                'returning' => $returningRow,
                'changed' => true,
            ];
            ++$changes;
        }

        return [
            'rows' => array_values($rows),
            'attempted_rows' => array_values($rows),
            'yield_stream' => $yield,
            'returning_rows' => $returningRows,
            'skipped_rows' => $skipped,
            'changes' => $changes,
            'statement_rows' => count($viewRows),
        ];
    }

    /**
     * @param array{name:string,source:string,columns:list<string>,mapping:array<string,string>,where?:callable(array<string,mixed>,array<string,mixed>):bool} $view
     * @return array<string,mixed>
     */
    private static function projectViewRow(array $viewRow, array $view): array
    {
        $incoming = [];
        foreach ($view['mapping'] as $viewColumn => $tableColumn) {
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view row is missing view column {$viewColumn}");
            }
            $incoming[$tableColumn] = $viewRow[$viewColumn];
        }

        return $incoming;
    }

    /**
     * @param array<string,callable(array<string,mixed>,array<string,mixed>):mixed> $assignments
     * @return array<string,mixed>
     */
    private static function assignedValues(array $old, array $incoming, array $assignments): array
    {
        $values = [];
        foreach ($assignments as $column => $assignment) {
            $values[$column] = $assignment($old, $incoming);
        }

        return $values;
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
                    throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view unique column {$column} is missing");
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
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $new, ?array $old, array $incoming, string $event, int $ordinal, string $source): array
    {
        $row = [];
        foreach ($returning as $index => $term) {
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $incoming, $event, $ordinal, $source);
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) && isset($term['as']) ? self::identifier((string) $term['as'], 'returning alias') : self::alias($expr, $index);
            $row[$alias] = self::exprValue($expr, $new, $old, $incoming, $event, $ordinal, $source);
        }

        return $row;
    }

    private static function exprValue(string $expr, array $new, ?array $old, array $incoming, string $event, int $ordinal, string $source): mixed
    {
        return match ($expr) {
            'event' => $event,
            'ordinal' => $ordinal,
            'source' => $source,
            default => self::columnExpr($expr, $new, $old, $incoming),
        };
    }

    private static function columnExpr(string $expr, array $new, ?array $old, array $incoming): mixed
    {
        if (str_starts_with($expr, 'new.')) {
            return $new[substr($expr, 4)] ?? null;
        }
        if (str_starts_with($expr, 'excluded.')) {
            return $incoming[substr($expr, 9)] ?? null;
        }
        if (str_starts_with($expr, 'old.')) {
            if ($old === null) {
                throw new InvalidArgumentException('SQLite trigger UPSERT RETURNING view old column is unavailable for insert');
            }

            return $old[substr($expr, 4)] ?? null;
        }
        if (str_starts_with($expr, 'old_or_null.')) {
            return $old[substr($expr, 12)] ?? null;
        }

        return $new[$expr] ?? null;
    }

    private static function alias(string $expr, int $index): string
    {
        $last = str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr;
        return self::identifier($last !== '' ? $last : 'expr' . $index, 'returning alias');
    }

    /**
     * @return array{name:string,source:string,columns:list<string>,mapping:array<string,string>}
     */
    private static function viewSummary(array $view): array
    {
        return [
            'name' => $view['name'],
            'source' => $view['source'],
            'columns' => $view['columns'],
            'mapping' => $view['mapping'],
        ];
    }

    /**
     * @param list<string> $columns
     */
    private static function validateColumns(array $columns, string $label): void
    {
        if ($columns === []) {
            throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view {$label} list cannot be empty");
        }
        foreach ($columns as $column) {
            self::identifier((string) $column, $label);
        }
    }

    private static function identifier(string $value, string $label): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view {$label} is malformed");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if (!preg_match('/^[A-Za-z0-9_@.-]+$/', $value)) {
            throw new InvalidArgumentException("SQLite trigger UPSERT RETURNING view {$label} is malformed");
        }

        return $value;
    }
}
