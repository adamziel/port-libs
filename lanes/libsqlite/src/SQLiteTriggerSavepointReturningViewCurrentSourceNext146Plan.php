<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerSavepointReturningViewCurrentSourceNext146Plan
{
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
    public static function execute(
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

        $savepoint = self::identifier((string) ($options['savepoint'] ?? 'wp_view_returning_146'), 'savepoint');
        $view = self::identifier((string) ($options['view'] ?? 'wp_option_import_view'), 'view');
        $currentSource = self::source((string) ($options['current_source'] ?? 'current-view-returning'));
        $nextSource = self::source((string) ($options['next_source'] ?? 'next-view-returning'));
        $rollbackCurrent = (bool) ($options['rollback_current'] ?? false);
        self::validateMapping($viewToBase);
        self::validateColumns($uniqueColumns, 'unique column');

        $savepointRows = array_values($baseRows);
        $current = self::runSource($savepointRows, $currentViewRows, $viewToBase, $uniqueColumns, $triggers, $returning, $view, $currentSource);
        $currentRolledBack = $rollbackCurrent || $current['rolled_back'];
        $nextStartRows = $currentRolledBack ? $savepointRows : $current['rows'];
        $next = self::runSource($nextStartRows, $nextViewRows, $viewToBase, $uniqueColumns, $triggers, $returning, $view, $nextSource);

        return [
            'status' => $currentRolledBack
                ? 'trigger-savepoint-returning-view-current-source-next146-current-rolled-back'
                : 'trigger-savepoint-returning-view-current-source-next146-released',
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
                'sqlite-trigger-savepoint-returning-view-current-source-next146',
                'sqlite-instead-of-view-trigger-returning-current-source-next',
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
    private static function runSource(
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
            $incoming = self::mapViewRow($viewRow, $viewToBase);
            $attempts[] = ['source' => $source, 'view' => $view, 'ordinal' => $ordinal, 'view_row' => $viewRow, 'incoming_row' => $incoming];
            $oldIndex = self::conflictIndex($rows, $incoming, $uniqueColumns);
            $old = $oldIndex === null ? null : $rows[$oldIndex];
            $event = $old === null ? 'insert' : 'update';
            $new = $old === null ? $incoming : array_replace($old, $incoming);

            $before = self::fireTriggers($triggers, 'before', $event, $old, $new, $viewRow, $source, $ordinal);
            if ($before['rollback']) {
                return self::rolledBack($rows, $returningRows, $attempts, $yields, array_merge($effects, $before['effects']), $changes, $before['reason']);
            }
            $new = $before['row'];
            $effects = array_merge($effects, $before['effects']);

            if ($oldIndex === null) {
                $rows[] = $new;
            } else {
                $rows[$oldIndex] = $new;
            }
            ++$changes;

            $after = self::fireTriggers($triggers, 'after', $event, $old, $new, $viewRow, $source, $ordinal);
            if ($after['rollback']) {
                return self::rolledBack($rows, $returningRows, $attempts, $yields, array_merge($effects, $after['effects']), $changes, $after['reason']);
            }
            $new = $after['row'];
            if ($oldIndex === null) {
                $rows[array_key_last($rows)] = $new;
            } else {
                $rows[$oldIndex] = $new;
            }
            $effects = array_merge($effects, $after['effects']);

            $returningRow = self::returningRow($returning, $new, $old, $viewRow, $event, $ordinal);
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
    private static function rolledBack(array $rows, array $returningRows, array $attempts, array $yields, array $effects, int $changes, string $reason): array
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
    private static function mapViewRow(array $viewRow, array $viewToBase): array
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
    private static function fireTriggers(array $triggers, string $timing, string $event, ?array $old, array $new, array $viewRow, string $source, int $ordinal): array
    {
        $effects = [];
        foreach ($triggers as $trigger) {
            if (strtolower((string) ($trigger['timing'] ?? '')) !== $timing || strtolower((string) ($trigger['event'] ?? '')) !== $event) {
                continue;
            }
            if (!self::whenMatches($trigger['when'] ?? true, $old, $new, $viewRow)) {
                continue;
            }

            $action = strtolower((string) ($trigger['action'] ?? 'audit'));
            if ($action === 'set-new') {
                foreach ((array) ($trigger['set'] ?? []) as $column => $value) {
                    $new[self::identifier((string) $column, 'trigger set column')] = self::value($value, $old, $new, $viewRow);
                }
            } elseif ($action === 'raise-rollback') {
                $effects[] = self::effect($trigger, $timing, $event, $action, $source, $ordinal, $old, $new, $viewRow);

                return ['row' => $new, 'effects' => $effects, 'rollback' => true, 'reason' => (string) ($trigger['reason'] ?? 'view-trigger-raise-rollback-current-savepoint')];
            } elseif ($action !== 'audit') {
                throw new InvalidArgumentException('SQLite trigger savepoint RETURNING view trigger action is unsupported');
            }

            $effects[] = self::effect($trigger, $timing, $event, $action, $source, $ordinal, $old, $new, $viewRow);
        }

        return ['row' => $new, 'effects' => $effects, 'rollback' => false, 'reason' => ''];
    }

    /**
     * @param array<string,mixed> $trigger
     * @return array<string,mixed>
     */
    private static function effect(array $trigger, string $timing, string $event, string $action, string $source, int $ordinal, ?array $old, array $new, array $viewRow): array
    {
        return [
            'trigger' => (string) ($trigger['name'] ?? ''),
            'timing' => $timing,
            'event' => $event,
            'action' => $action,
            'source' => $source,
            'ordinal' => $ordinal,
            'row' => self::project((array) ($trigger['values'] ?? []), $old, $new, $viewRow),
        ];
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
    private static function returningRow(array $returning, array $new, ?array $old, array $viewRow, string $event, int $ordinal): array
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
                $row[self::identifier($alias, 'RETURNING alias')] = self::exprValue($expr, $new, $old, $viewRow);
                continue;
            }
            if (!is_string($term) || $term === '') {
                throw new InvalidArgumentException('SQLite trigger view RETURNING term is malformed');
            }
            $alias = str_contains($term, '.') ? substr($term, (int) strrpos($term, '.') + 1) : $term;
            $row[self::identifier($alias, 'RETURNING alias')] = self::exprValue($term, $new, $old, $viewRow);
        }

        return $row;
    }

    private static function exprValue(string $expr, array $new, ?array $old, array $viewRow): mixed
    {
        $expr = trim($expr);
        if (str_starts_with($expr, 'new.')) {
            return self::rowValue($new, substr($expr, 4), 'NEW row');
        }
        if (str_starts_with($expr, 'old.')) {
            if ($old === null) {
                return null;
            }

            return self::rowValue($old, substr($expr, 4), 'OLD row');
        }
        if (str_starts_with($expr, 'view.')) {
            return self::rowValue($viewRow, substr($expr, 5), 'view row');
        }

        return self::rowValue($new, $expr, 'RETURNING row');
    }

    private static function whenMatches(mixed $when, ?array $old, array $new, array $viewRow): bool
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
        $leftValue = self::value($left, $old, $new, $viewRow);
        $rightValue = self::value($right, $old, $new, $viewRow);

        return match (strtoupper((string) $operator)) {
            '=', '==' => $leftValue == $rightValue,
            '!=', '<>' => $leftValue != $rightValue,
            'IS' => $leftValue === $rightValue,
            'IS NOT' => $leftValue !== $rightValue,
            default => throw new InvalidArgumentException('SQLite trigger view RETURNING WHEN operator is unsupported'),
        };
    }

    private static function value(mixed $value, ?array $old, array $new, array $viewRow): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        if (str_starts_with($value, 'concat:')) {
            $parts = explode(':', substr($value, 7));

            return implode('', array_map(static fn (string $part): string => (string) self::value($part, $old, $new, $viewRow), $parts));
        }
        if (str_starts_with($value, 'new.')) {
            return self::rowValue($new, substr($value, 4), 'NEW row');
        }
        if (str_starts_with($value, 'old.')) {
            if ($old === null) {
                return null;
            }

            return self::rowValue($old, substr($value, 4), 'OLD row');
        }
        if (str_starts_with($value, 'view.')) {
            return self::rowValue($viewRow, substr($value, 5), 'view row');
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $terms
     * @return array<string,mixed>
     */
    private static function project(array $terms, ?array $old, array $new, array $viewRow): array
    {
        $row = [];
        foreach ($terms as $column => $value) {
            $row[self::identifier((string) $column, 'projection column')] = self::value($value, $old, $new, $viewRow);
        }

        return $row;
    }

    private static function rowValue(array $row, string $column, string $context): mixed
    {
        self::identifier($column, $context . ' column');
        if (!array_key_exists($column, $row)) {
            throw new InvalidArgumentException("SQLite trigger view RETURNING {$context} is missing {$column}");
        }

        return $row[$column];
    }

    /**
     * @param array<string,string> $mapping
     */
    private static function validateMapping(array $mapping): void
    {
        if ($mapping === []) {
            throw new InvalidArgumentException('SQLite trigger view RETURNING mapping cannot be empty');
        }
        foreach ($mapping as $viewColumn => $baseColumn) {
            self::identifier((string) $viewColumn, 'view column');
            self::identifier((string) $baseColumn, 'base column');
        }
    }

    /**
     * @param list<string> $columns
     */
    private static function validateColumns(array $columns, string $context): void
    {
        if ($columns === []) {
            throw new InvalidArgumentException("SQLite trigger view RETURNING {$context} list cannot be empty");
        }
        foreach ($columns as $column) {
            self::identifier((string) $column, $context);
        }
    }

    private static function identifier(string $value, string $context): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("SQLite trigger view RETURNING {$context} is malformed");
        }

        return $value;
    }

    private static function source(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]+$/', $value)) {
            throw new InvalidArgumentException('SQLite trigger view RETURNING source token is malformed');
        }

        return $value;
    }
}
