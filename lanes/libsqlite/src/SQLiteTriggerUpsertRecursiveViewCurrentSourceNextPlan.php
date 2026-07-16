<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerUpsertRecursiveViewCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentViewRows
     * @param list<array<string,mixed>> $nextViewRows
     * @param array{name:string,source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @param array{release_next?:bool,recursive_triggers?:bool,savepoint?:string,key_column?:string,value_column?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentViewRows,
        array $nextViewRows,
        array $view,
        array $uniqueColumns,
        array $triggers,
        array $options = [],
    ): array {
        self::validateColumns($uniqueColumns, 'unique column');
        $view = self::normalizeView($view);
        $savepoint = self::identifier((string) ($options['savepoint'] ?? 'app_view_recursive_148'), 'savepoint');
        $keyColumn = self::identifier((string) ($options['key_column'] ?? ($uniqueColumns[0] ?? 'key_name')), 'key column');
        $valueColumn = self::identifier((string) ($options['value_column'] ?? 'key_value'), 'value column');
        $recursive = (bool) ($options['recursive_triggers'] ?? true);
        $releaseNext = (bool) ($options['release_next'] ?? false);
        $baseRows = array_values($rows);

        $current = self::runSource($baseRows, $currentViewRows, $view, $uniqueColumns, $triggers, 'current', $recursive, $keyColumn, $valueColumn);
        if ($releaseNext) {
            $nextView = array_replace($view, ['source' => $view['source'] . '@next']);
            $next = self::runSource($current['rows'], $nextViewRows, $nextView, $uniqueColumns, $triggers, 'next', $recursive, $keyColumn, $valueColumn);
            $afterSavepoint = $next['rows'];
            $visibleSource = $view['source'] . '@next';
            $status = 'trigger-upsert-recursive-view-next-source-admitted-next148';
        } else {
            $nextView = array_replace($view, ['source' => $view['source'] . '@next']);
            $attemptedNext = self::runSource($baseRows, $nextViewRows, $nextView, $uniqueColumns, $triggers, 'next', $recursive, $keyColumn, $valueColumn);
            $next = [
                'rows' => $baseRows,
                'yield_stream' => [],
                'returning_rows' => [],
                'trigger_effects' => [],
                'changes' => 0,
                'attempted_next' => $attemptedNext,
            ];
            $afterSavepoint = $baseRows;
            $visibleSource = $view['source'];
            $status = 'trigger-upsert-recursive-view-current-source-retained-next148';
        }

        return [
            'status' => $status,
            'savepoint' => $savepoint,
            'view' => $view['name'],
            'current_source' => $view['source'],
            'visible_source' => $visibleSource,
            'next_source_admitted' => $releaseNext,
            'recursive_triggers' => $recursive,
            'before_rows' => $baseRows,
            'current_rows' => $current['rows'],
            'after_savepoint' => $afterSavepoint,
            'current_yield_stream' => $current['yield_stream'],
            'next_yield_stream' => $next['yield_stream'],
            'attempted_next_yield_stream' => $next['attempted_next']['yield_stream'] ?? $next['yield_stream'],
            'current_returning_rows' => $current['returning_rows'],
            'next_returning_rows' => $next['returning_rows'],
            'attempted_next_returning_rows' => $next['attempted_next']['returning_rows'] ?? $next['returning_rows'],
            'current_trigger_effects' => $current['trigger_effects'],
            'next_trigger_effects' => $next['trigger_effects'],
            'attempted_next_trigger_effects' => $next['attempted_next']['trigger_effects'] ?? $next['trigger_effects'],
            'changes' => $releaseNext ? $current['changes'] + $next['changes'] : 0,
            'current_changes' => $current['changes'],
            'next_changes' => $next['changes'],
            'yield_boundary' => $releaseNext
                ? 'recursive-view-upsert-release-admits-next-source'
                : 'recursive-view-upsert-current-source-yield-before-next-source',
            'dependencies' => [
                'sqlite-trigger-upsert-recursive-view-current-source-next148',
                'sqlite-instead-of-view-upsert-recursive-trigger',
                'sqlite-recursive-trigger-side-effect-current-source-yield',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $viewRows
     * @param array{name:string,source:string,mapping:array<string,string>} $view
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @return array{rows:list<array<string,mixed>>,yield_stream:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>,trigger_effects:list<array<string,mixed>>,changes:int}
     */
    private static function runSource(array $rows, array $viewRows, array $view, array $uniqueColumns, array $triggers, string $phase, bool $recursive, string $keyColumn, string $valueColumn): array
    {
        $yield = [];
        $returning = [];
        $effects = [];
        $changes = 0;

        foreach (array_values($viewRows) as $ordinal => $viewRow) {
            $incoming = self::project($viewRow, $view['mapping']);
            [$rows, $stepYield, $stepReturning, $stepEffects, $stepChanges] = self::applyUpsert($rows, $incoming, $uniqueColumns, $triggers, $phase, $view, (int) $ordinal, 0, null, $recursive, $keyColumn, $valueColumn);
            $yield = array_merge($yield, $stepYield);
            $returning = array_merge($returning, $stepReturning);
            $effects = array_merge($effects, $stepEffects);
            $changes += $stepChanges;
        }

        return [
            'rows' => array_values($rows),
            'yield_stream' => $yield,
            'returning_rows' => $returning,
            'trigger_effects' => $effects,
            'changes' => $changes,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $uniqueColumns
     * @param list<array{name:string,when:string,target:string,value:string,recursive?:bool}> $triggers
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>,2:list<array<string,mixed>>,3:list<array<string,mixed>>,4:int}
     */
    private static function applyUpsert(array $rows, array $incoming, array $uniqueColumns, array $triggers, string $phase, array $view, int $ordinal, int $depth, ?string $trigger, bool $recursive, string $keyColumn, string $valueColumn): array
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

        $returning = [[
            'phase' => $phase,
            'source' => $view['source'],
            'ordinal' => $ordinal,
            'depth' => $depth,
            'event' => $event,
            'trigger' => $trigger,
            'key_name' => $new[$keyColumn] ?? null,
            'key_value' => $new[$valueColumn] ?? null,
            'old_value' => $old[$valueColumn] ?? null,
        ]];
        $yield = [[
            'phase' => $phase,
            'source' => $view['source'],
            'view' => $view['name'],
            'ordinal' => $ordinal,
            'depth' => $depth,
            'event' => $event,
            'trigger' => $trigger,
            'status' => 'changed',
            'incoming_row' => $incoming,
            'current_row' => $old,
            'next_row' => $new,
            'returning' => $returning[0],
        ]];
        $effects = [];
        $changes = 1;

        foreach ($triggers as $rowTrigger) {
            self::validateTrigger($rowTrigger);
            if (($rowTrigger['when'] ?? '') !== ($new[$keyColumn] ?? null)) {
                continue;
            }
            $effect = [
                'phase' => $phase,
                'source' => $view['source'],
                'view_ordinal' => $ordinal,
                'depth' => $depth,
                'trigger' => $rowTrigger['name'],
                'source_key' => $new[$keyColumn] ?? null,
                'target_key' => $rowTrigger['target'],
                'result' => $recursive && (bool) ($rowTrigger['recursive'] ?? true) ? 'recursive-upsert' : 'recursive-suppressed',
            ];
            $effects[] = $effect;
            if (!$recursive || !(bool) ($rowTrigger['recursive'] ?? true)) {
                continue;
            }
            if ($depth >= 8) {
                throw new InvalidArgumentException('SQLite recursive view trigger depth limit exceeded');
            }
            [$rows, $subYield, $subReturning, $subEffects, $subChanges] = self::applyUpsert(
                $rows,
                [$keyColumn => $rowTrigger['target'], $valueColumn => str_replace('{value}', (string) ($new[$valueColumn] ?? ''), $rowTrigger['value'])],
                $uniqueColumns,
                $triggers,
                $phase,
                $view,
                $ordinal,
                $depth + 1,
                $rowTrigger['name'],
                $recursive,
                $keyColumn,
                $valueColumn,
            );
            $yield = array_merge($yield, $subYield);
            $returning = array_merge($returning, $subReturning);
            $effects = array_merge($effects, $subEffects);
            $changes += $subChanges;
        }

        return [$rows, $yield, $returning, $effects, $changes];
    }

    /**
     * @param array<string,mixed> $view
     * @return array{name:string,source:string,mapping:array<string,string>}
     */
    private static function normalizeView(array $view): array
    {
        if (!is_array($view['mapping'] ?? null) || $view['mapping'] === []) {
            throw new InvalidArgumentException('SQLite recursive view UPSERT mapping must not be empty');
        }
        $mapping = [];
        foreach ($view['mapping'] as $viewColumn => $tableColumn) {
            $mapping[self::identifier((string) $viewColumn, 'view column')] = self::identifier((string) $tableColumn, 'table column');
        }
        return [
            'name' => self::identifier((string) ($view['name'] ?? ''), 'view name'),
            'source' => self::source((string) ($view['source'] ?? ''), 'view source'),
            'mapping' => $mapping,
        ];
    }

    /** @param array<string,mixed> $viewRow @param array<string,string> $mapping @return array<string,mixed> */
    private static function project(array $viewRow, array $mapping): array
    {
        $incoming = [];
        foreach ($mapping as $viewColumn => $tableColumn) {
            if (!array_key_exists($viewColumn, $viewRow)) {
                throw new InvalidArgumentException("SQLite recursive view UPSERT row is missing {$viewColumn}");
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
                    throw new InvalidArgumentException("SQLite recursive view UPSERT unique column {$column} is missing");
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
            throw new InvalidArgumentException("SQLite recursive view UPSERT {$label} list must not be empty");
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
            throw new InvalidArgumentException('SQLite recursive view UPSERT trigger is incomplete');
        }
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT {$label} is malformed");
        }
        return $value;
    }

    private static function source(string $value, string $label): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_@.-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view UPSERT {$label} is malformed");
        }
        return $value;
    }
}
