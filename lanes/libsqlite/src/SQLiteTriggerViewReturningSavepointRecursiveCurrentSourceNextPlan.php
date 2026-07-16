<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param list<string>|array<string,string|callable(array<string,mixed>):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function execute(
        SQLiteAttachedSchemaCatalog $catalog,
        string $triggerName,
        array $tables,
        array $currentRows,
        array $nextRows,
        string $savepointName,
        array $returning = ['*'],
        array $options = [],
    ): array {
        if ($currentRows === [] || !array_is_list($currentRows)) {
            throw new InvalidArgumentException('SQLite trigger-view RETURNING savepoint current-source rows must be a non-empty list');
        }
        if ($nextRows === [] || !array_is_list($nextRows)) {
            throw new InvalidArgumentException('SQLite trigger-view RETURNING savepoint next-source rows must be a non-empty list');
        }
        if ($savepointName === '') {
            throw new InvalidArgumentException('SQLite trigger-view RETURNING savepoint current-source-next requires a savepoint name');
        }

        $currentTrigger = (string) ($options['current_trigger_name'] ?? $triggerName);
        $nextTrigger = (string) ($options['next_trigger_name'] ?? $triggerName);
        if ($currentTrigger === '' || $nextTrigger === '') {
            throw new InvalidArgumentException('SQLite trigger-view RETURNING savepoint phase trigger names must be non-empty');
        }

        $current = self::runPhase($catalog, $currentTrigger, $tables, $currentRows, 'current', $savepointName, $returning, $options);
        $nextInputTables = $current['rolled_back'] ? $tables : $current['tables'];
        $next = self::runPhase($catalog, $nextTrigger, $nextInputTables, $nextRows, 'next', $savepointName, $returning, $options);

        return [
            'status' => $next['rolled_back']
                ? 'next-source-view-trigger-savepoint-rolled-back'
                : ($current['rolled_back'] ? 'current-source-view-trigger-savepoint-rolled-back' : 'current-next-view-trigger-returning-applied'),
            'savepoint' => $savepointName,
            'current' => $current,
            'next' => $next,
            'tables' => $next['tables'],
            'current_source_tables' => $tables,
            'next_source_tables' => $nextInputTables,
            'current_returning' => $current['returning_rows'],
            'next_returning' => $next['returning_rows'],
            'returning_rows' => array_merge($current['returning_rows'], $next['returning_rows']),
            'attempted_returning_rows' => array_merge($current['attempted_returning_rows'], $next['attempted_returning_rows']),
            'yield_edges' => array_merge($current['yield_edges'], $next['yield_edges']),
            'changes' => (int) $current['changes'] + (int) $next['changes'],
            'rolled_back_phases' => array_values(array_filter([
                $current['rolled_back'] ? 'current' : null,
                $next['rolled_back'] ? 'next' : null,
            ])),
            'dependencies' => [
                'sqlite-trigger-view-returning-savepoint-recursive-current-source-next123',
                'sqlite-instead-of-view-trigger-yield',
                'sqlite-returning-current-row',
                'sqlite-savepoint-current-rollback',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<array<string,mixed>> $rows
     * @param list<string>|array<string,string|callable(array<string,mixed>):mixed> $returning
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    private static function runPhase(
        SQLiteAttachedSchemaCatalog $catalog,
        string $triggerName,
        array $tables,
        array $rows,
        string $phase,
        string $savepointName,
        array $returning,
        array $options,
    ): array {
        $working = $tables;
        $returningRows = [];
        $attemptedReturningRows = [];
        $operations = [];
        $yieldEdges = [];
        $rollback = null;
        $changes = 0;

        foreach ($rows as $ordinal => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('SQLite trigger-view RETURNING savepoint source rows must be arrays');
            }

            $plan = SQLiteViewTriggerReturningSavepointPlan::insertIntoView(
                $catalog,
                $triggerName,
                $working,
                $row,
                $savepointName . '_' . $phase,
                $returning,
                $options,
            );

            foreach ($plan['operations'] as $operationIndex => $operation) {
                $operation['phase'] = $phase;
                $operation['source_ordinal'] = $ordinal;
                $operation['operation_index'] = $operationIndex;
                $operations[] = $operation;
            }
            foreach ($plan['attempted_returning_rows'] as $attempted) {
                $attemptedReturningRows[] = [
                    'phase' => $phase,
                    'source_ordinal' => $ordinal,
                    'row' => $attempted,
                ];
            }

            $yieldEdges[] = [
                'phase' => $phase,
                'source_ordinal' => $ordinal,
                'status' => $plan['rolled_back_to_savepoint'] ? 'rolled-back' : 'committed',
                'returning' => $plan['attempted_returning_rows'][0] ?? null,
                'operation_count' => count($plan['operations']),
                'rollback_to_wal_frame' => $plan['rollback_to_wal_frame'],
                'rollback_page_numbers' => $plan['rollback_page_numbers'],
            ];

            if ($plan['rolled_back_to_savepoint']) {
                $rollback = [
                    'phase' => $phase,
                    'source_ordinal' => $ordinal,
                    'reason' => $plan['rollback_reason'],
                    'rollback_page_numbers' => $plan['rollback_page_numbers'],
                    'rollback_to_wal_frame' => $plan['rollback_to_wal_frame'],
                    'discarded_wal_frames' => $plan['discarded_wal_frames'],
                ];
                break;
            }

            $working = $plan['tables'];
            $changes += (int) $plan['changes'];
            foreach ($plan['returning_rows'] as $returningRow) {
                $returningRows[] = $returningRow;
            }
        }

        return [
            'phase' => $phase,
            'source_rows' => $rows,
            'tables' => $rollback === null ? $working : $tables,
            'operations' => $operations,
            'yield_edges' => $yieldEdges,
            'returning_rows' => $rollback === null ? $returningRows : [],
            'attempted_returning_rows' => $attemptedReturningRows,
            'changes' => $rollback === null ? $changes : 0,
            'rolled_back' => $rollback !== null,
            'rollback' => $rollback,
            'rolled_back_at_ordinal' => $rollback['source_ordinal'] ?? null,
        ];
    }
}
