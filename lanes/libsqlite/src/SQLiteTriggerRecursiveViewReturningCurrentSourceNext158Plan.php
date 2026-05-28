<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext158Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentViewRows
     * @param list<array<string,mixed>> $nextViewRows
     * @param array{name:string,current_source:string,next_source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string,?string):mixed> $returning
     * @param array{release_current?:bool,rollback_next?:bool,recursive_triggers?:bool,savepoint?:string,max_depth?:int} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentViewRows,
        array $nextViewRows,
        array $view,
        array $uniqueColumns,
        array $triggers,
        array $returning,
        array $options = [],
    ): array {
        self::validateColumns($uniqueColumns, 'unique column');
        if ($returning === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING current-source projection must not be empty');
        }

        $view = self::normalizeView($view);
        $savepoint = self::token((string) ($options['savepoint'] ?? 'wp_recursive_view_returning_158'), 'savepoint');
        $recursive = (bool) ($options['recursive_triggers'] ?? true);
        $releaseCurrent = (bool) ($options['release_current'] ?? false);
        $rollbackNext = (bool) ($options['rollback_next'] ?? false);
        $maxDepth = (int) ($options['max_depth'] ?? 8);
        if ($maxDepth < 0 || $maxDepth > 100) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING max depth is malformed');
        }

        $baseRows = self::normalizeRows($rows);
        $current = self::runSource($baseRows, $currentViewRows, $view, $view['current_source'], $uniqueColumns, $triggers, $returning, 'current', $recursive, $maxDepth);
        $nextInput = $releaseCurrent ? $current['rows'] : $baseRows;
        $next = self::runSource($nextInput, $nextViewRows, $view, $view['next_source'], $uniqueColumns, $triggers, $returning, 'next', $recursive, $maxDepth);

        $currentAdmitted = $releaseCurrent;
        $nextAdmitted = !$rollbackNext;
        $finalRows = $baseRows;
        $visibleSource = $view['current_source'];
        if ($releaseCurrent) {
            $finalRows = $current['rows'];
            $visibleSource = $view['current_source'];
        }
        if (!$rollbackNext) {
            $finalRows = $next['rows'];
            $visibleSource = $view['next_source'];
        }

        $admittedReturning = [];
        $suppressedReturning = [];
        $currentStream = self::tagAdmission($current['yield_stream'], $currentAdmitted);
        $nextStream = self::tagAdmission($next['yield_stream'], $nextAdmitted);
        if ($currentAdmitted) {
            $admittedReturning = array_merge($admittedReturning, $current['returning_rows']);
        } else {
            $suppressedReturning = array_merge($suppressedReturning, $current['returning_rows']);
        }
        if ($nextAdmitted) {
            $admittedReturning = array_merge($admittedReturning, $next['returning_rows']);
        } else {
            $suppressedReturning = array_merge($suppressedReturning, $next['returning_rows']);
        }

        return [
            'status' => self::status($releaseCurrent, $rollbackNext),
            'savepoint' => $savepoint,
            'view' => $view['name'],
            'current_source' => $view['current_source'],
            'next_source' => $view['next_source'],
            'visible_source' => $visibleSource,
            'current_source_admitted' => $currentAdmitted,
            'next_source_admitted' => $nextAdmitted,
            'recursive_triggers' => $recursive,
            'before_rows' => $baseRows,
            'current_rows' => $current['rows'],
            'next_rows' => $next['rows'],
            'after_savepoint' => $finalRows,
            'returning_rows' => $admittedReturning,
            'suppressed_returning_rows' => $suppressedReturning,
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $next['returning_rows'],
            'current_yield_stream' => $currentStream,
            'next_yield_stream' => $nextStream,
            'attempted_source_stream' => array_merge($currentStream, $nextStream),
            'current_trigger_effects' => $current['trigger_effects'],
            'next_trigger_effects' => $next['trigger_effects'],
            'changes' => ($currentAdmitted ? $current['changes'] : 0) + ($nextAdmitted ? $next['changes'] : 0),
            'current_changes' => $current['changes'],
            'next_changes' => $next['changes'],
            'discarded_returning_count' => count($suppressedReturning),
            'yield_boundary' => $releaseCurrent
                ? ($rollbackNext ? 'recursive-view-returning-current-source-admitted-next-rolled-back' : 'recursive-view-returning-current-next-sources-admitted')
                : 'recursive-view-returning-current-source-yield-before-next-source',
            'next_input' => $releaseCurrent ? 'current-phase-output' : 'saved-current-source',
            'dependency_closure' => 'reuses-native-recursive-trigger-returning-view-current-source-plans',
            'dependencies' => [
                'sqlite-trigger-recursive-view-returning-current-source-next158',
                'sqlite-instead-of-view-trigger-returning',
                'sqlite-recursive-trigger-side-effect-current-source-yield',
                'sqlite-returning-current-source-before-next-source',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $viewRows
     * @param array{name:string,current_source:string,next_source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string,?string):mixed> $returning
     * @return array{rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int}
     */
    private static function runSource(array $rows, array $viewRows, array $view, string $source, array $uniqueColumns, array $triggers, array $returning, string $phase, bool $recursive, int $maxDepth): array
    {
        $yield = [];
        $returningRows = [];
        $effects = [];
        $changes = 0;

        foreach (array_values($viewRows) as $ordinal => $viewRow) {
            if (!is_array($viewRow)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING row must be an array');
            }
            $incoming = self::project($viewRow, $view['mapping']);
            [$rows, $stepYield, $stepReturning, $stepEffects, $stepChanges] = self::applyUpsert($rows, $incoming, $uniqueColumns, $triggers, $returning, $phase, $view['name'], $source, (int) $ordinal, 0, null, $recursive, $maxDepth);
            $yield = array_merge($yield, $stepYield);
            $returningRows = array_merge($returningRows, $stepReturning);
            $effects = array_merge($effects, $stepEffects);
            $changes += $stepChanges;
        }

        return [
            'rows' => array_values($rows),
            'yield_stream' => $yield,
            'returning_rows' => $returningRows,
            'trigger_effects' => $effects,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string,?string):mixed> $returning
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>,2:list<array<string,mixed>>,3:list<array<string,mixed>>,4:int}
     */
    private static function applyUpsert(array $rows, array $incoming, array $uniqueColumns, array $triggers, array $returning, string $phase, string $viewName, string $source, int $ordinal, int $depth, ?string $trigger, bool $recursive, int $maxDepth): array
    {
        $index = self::conflictIndex($rows, $incoming, $uniqueColumns);
        $old = $index === null ? null : $rows[$index];
        $event = $old === null ? 'insert' : 'update';
        $new = $old === null ? $incoming : array_replace($old, $incoming);
        if ($index === null) {
            $rows[] = $new;
        } else {
            $rows[$index] = $new;
        }

        $returningRow = self::returningRow($returning, $new, $old, $incoming, $event, $ordinal, $depth, $source, $trigger);
        $returningRows = [[
            'phase' => $phase,
            'source' => $source,
            'view' => $viewName,
            'ordinal' => $ordinal,
            'depth' => $depth,
            'event' => $event,
            'trigger' => $trigger,
            'returning' => $returningRow,
        ]];
        $yield = [[
            'phase' => $phase,
            'source' => $source,
            'view' => $viewName,
            'ordinal' => $ordinal,
            'depth' => $depth,
            'event' => $event,
            'trigger' => $trigger,
            'status' => 'changed',
            'incoming_row' => $incoming,
            'current_row' => $old,
            'next_row' => $new,
            'returning' => $returningRow,
        ]];
        $effects = [];
        $changes = 1;

        foreach ($triggers as $rowTrigger) {
            self::validateTrigger($rowTrigger);
            if (($rowTrigger['when'] ?? null) !== ($new['option_name'] ?? null)) {
                continue;
            }
            $effect = [
                'phase' => $phase,
                'source' => $source,
                'view' => $viewName,
                'view_ordinal' => $ordinal,
                'depth' => $depth,
                'trigger' => $rowTrigger['name'],
                'source_option' => $new['option_name'] ?? null,
                'target_option' => $rowTrigger['target'],
                'result' => $recursive && (bool) ($rowTrigger['recursive'] ?? true) ? 'recursive-upsert' : 'recursive-suppressed',
            ];
            $effects[] = $effect;
            if (!$recursive || !(bool) ($rowTrigger['recursive'] ?? true)) {
                continue;
            }
            if ($depth >= $maxDepth) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING trigger depth limit exceeded');
            }
            [$rows, $subYield, $subReturning, $subEffects, $subChanges] = self::applyUpsert(
                $rows,
                ['option_name' => $rowTrigger['target'], 'option_value' => str_replace('{value}', (string) ($new['option_value'] ?? ''), $rowTrigger['value']), 'autoload' => $new['autoload'] ?? null],
                $uniqueColumns,
                $triggers,
                $returning,
                $phase,
                $viewName,
                $source,
                $ordinal,
                $depth + 1,
                $rowTrigger['name'],
                $recursive,
                $maxDepth,
            );
            $yield = array_merge($yield, $subYield);
            $returningRows = array_merge($returningRows, $subReturning);
            $effects = array_merge($effects, $subEffects);
            $changes += $subChanges;
        }

        return [$rows, $yield, $returningRows, $effects, $changes];
    }

    /**
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,?array<string,mixed>,array<string,mixed>,string,int,int,string,?string):mixed> $returning
     * @return array<string,mixed>
     */
    private static function returningRow(array $returning, array $new, ?array $old, array $incoming, string $event, int $ordinal, int $depth, string $source, ?string $trigger): array
    {
        $row = [];
        foreach ($returning as $index => $term) {
            if (is_callable($term)) {
                $row['expr' . $index] = $term($new, $old, $incoming, $event, $ordinal, $depth, $source, $trigger);
                continue;
            }
            $expr = is_array($term) ? (string) ($term['expr'] ?? '') : (string) $term;
            $alias = is_array($term) && isset($term['as']) ? self::identifier((string) $term['as'], 'returning alias') : self::alias($expr, $index);
            $row[$alias] = self::exprValue($expr, $new, $old, $incoming, $event, $ordinal, $depth, $source, $trigger);
        }

        return $row;
    }

    private static function exprValue(string $expr, array $new, ?array $old, array $incoming, string $event, int $ordinal, int $depth, string $source, ?string $trigger): mixed
    {
        return match ($expr) {
            '*' => $new,
            'event' => $event,
            'ordinal' => $ordinal,
            'depth' => $depth,
            'source' => $source,
            'trigger' => $trigger,
            default => self::columnExpr($expr, $new, $old, $incoming),
        };
    }

    private static function columnExpr(string $expr, array $new, ?array $old, array $incoming): mixed
    {
        if (str_starts_with($expr, 'new.')) {
            return $new[substr($expr, 4)] ?? null;
        }
        if (str_starts_with($expr, 'old.')) {
            if ($old === null) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING old column is unavailable for insert');
            }
            return $old[substr($expr, 4)] ?? null;
        }
        if (str_starts_with($expr, 'old_or_null.')) {
            return $old[substr($expr, 12)] ?? null;
        }
        if (str_starts_with($expr, 'excluded.')) {
            return $incoming[substr($expr, 9)] ?? null;
        }

        return $new[$expr] ?? null;
    }

    /** @param list<array<string,mixed>> $stream @return list<array<string,mixed>> */
    private static function tagAdmission(array $stream, bool $admitted): array
    {
        foreach ($stream as $index => $row) {
            $stream[$index]['admitted'] = $admitted;
            $stream[$index]['rolled_back_after_yield'] = !$admitted;
        }

        return $stream;
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function normalizeRows(array $rows): array
    {
        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING rows must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite recursive view RETURNING row must be an array');
            }
        }

        return array_values($rows);
    }

    /** @param array<string,mixed> $view @return array{name:string,current_source:string,next_source:string,mapping:array<string,string>} */
    private static function normalizeView(array $view): array
    {
        if (!is_array($view['mapping'] ?? null) || $view['mapping'] === []) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING mapping must not be empty');
        }
        $mapping = [];
        foreach ($view['mapping'] as $viewColumn => $tableColumn) {
            $mapping[self::identifier((string) $viewColumn, 'view column')] = self::identifier((string) $tableColumn, 'table column');
        }

        return [
            'name' => self::identifier((string) ($view['name'] ?? ''), 'view name'),
            'current_source' => self::token((string) ($view['current_source'] ?? ''), 'current source'),
            'next_source' => self::token((string) ($view['next_source'] ?? ''), 'next source'),
            'mapping' => $mapping,
        ];
    }

    /** @param array<string,mixed> $viewRow @param array<string,string> $mapping @return array<string,mixed> */
    private static function project(array $viewRow, array $mapping): array
    {
        $incoming = [];
        foreach ($mapping as $viewColumn => $tableColumn) {
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new InvalidArgumentException("SQLite recursive view RETURNING row is missing {$viewColumn}");
            }
            $incoming[$tableColumn] = $viewRow[$viewColumn];
        }

        return $incoming;
    }

    /** @param list<array<string,mixed>> $rows @param list<string> $uniqueColumns */
    private static function conflictIndex(array $rows, array $incoming, array $uniqueColumns): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($uniqueColumns as $column) {
                if (!array_key_exists($column, $row) || !array_key_exists($column, $incoming)) {
                    throw new InvalidArgumentException("SQLite recursive view RETURNING unique column {$column} is missing");
                }
                if ($row[$column] === null || $incoming[$column] === null || $row[$column] != $incoming[$column]) {
                    continue 2;
                }
            }

            return (int) $index;
        }

        return null;
    }

    /** @param list<string> $columns */
    private static function validateColumns(array $columns, string $label): void
    {
        if ($columns === []) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING {$label} list must not be empty");
        }
        foreach ($columns as $column) {
            self::identifier((string) $column, $label);
        }
    }

    /** @param array<string,mixed> $trigger */
    private static function validateTrigger(array $trigger): void
    {
        self::identifier((string) ($trigger['name'] ?? ''), 'trigger name');
        self::identifier((string) ($trigger['target'] ?? ''), 'trigger target');
        if (!array_key_exists('when', $trigger) || !array_key_exists('value', $trigger)) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING trigger is incomplete');
        }
    }

    private static function status(bool $releaseCurrent, bool $rollbackNext): string
    {
        if ($releaseCurrent && $rollbackNext) {
            return 'trigger-recursive-view-returning-current-source-admitted-next-rolled-back-next158';
        }
        if ($releaseCurrent) {
            return 'trigger-recursive-view-returning-current-next-source-admitted-next158';
        }

        return 'trigger-recursive-view-returning-current-source-retained-next158';
    }

    private static function alias(string $expr, int $index): string
    {
        if ($expr === '*') {
            return 'row';
        }
        $last = str_contains($expr, '.') ? substr($expr, (int) strrpos($expr, '.') + 1) : $expr;

        return self::identifier($last !== '' ? $last : 'expr' . $index, 'returning alias');
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING {$label} is malformed");
        }

        return $value;
    }

    private static function token(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_@.-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING {$label} is malformed");
        }

        return $value;
    }
}
