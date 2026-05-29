<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueYieldReturningSavepointCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_yield_next223',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($yieldStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value yield next223 needs yield statements');
        }
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value yield next223 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value yield next223 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value yield next223 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value yield next223 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$yieldCurrent, $yieldExecuted, $yieldedReturning] = self::runStatements(
            $savepointImage,
            $yieldStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'yield-before-rollback-to-next223',
        );
        [$attemptCurrent, $attemptExecuted, $attemptedReturning] = self::runStatements(
            $yieldCurrent,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-after-yield-before-rollback-to-next223',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-yield-rollback-to-next223',
        );

        $yieldedRows = self::flattenReturning($yieldedReturning);
        $suppressedRows = self::flattenReturning($attemptedReturning);
        $retryRows = self::flattenReturning($retryReturning);

        return [
            'status' => 'rowvalue-update-delete-returning-yield-savepoint-current-source-next223',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'yield_current_source_tables' => $yieldCurrent,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'yielded_returning_before_rollback' => $yieldedReturning,
            'suppressed_returning_after_rollback' => $attemptedReturning,
            'retry_returning_after_rollback' => $retryReturning,
            'yielded_rows_before_rollback' => $yieldedRows,
            'suppressed_rows_after_rollback' => $suppressedRows,
            'retry_rows_after_rollback' => $retryRows,
            'yield_statements' => $yieldExecuted,
            'attempt_statements' => $attemptExecuted,
            'retry_statements' => $retryExecuted,
            'yielded_returning_count' => count($yieldedRows),
            'suppressed_returning_count' => count($suppressedRows),
            'retry_returning_count' => count($retryRows),
            'yield_change_count' => self::changeCount($yieldExecuted),
            'attempt_change_count' => self::changeCount($attemptExecuted),
            'retry_change_count' => self::changeCount($retryExecuted),
            'rollback_to_savepoint_next223' => true,
            'yielded_rows_survive_rollback_next223' => true,
            'attempted_rows_suppressed_next223' => true,
            'retry_reads_savepoint_image_next223' => true,
            'savepoint_remains_active_next223' => true,
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
            'yield_receipt_next223' => [
                'savepoint' => $savepoint,
                'yielded_count' => count($yieldedRows),
                'suppressed_count' => count($suppressedRows),
                'retry_count' => count($retryRows),
                'yielded_ids' => array_values(array_filter(array_column($yieldedRows, $rowIdColumn), static fn ($id): bool => is_int($id) || is_string($id))),
                'suppressed_ids' => array_values(array_filter(array_column($suppressedRows, $rowIdColumn), static fn ($id): bool => is_int($id) || is_string($id))),
                'retry_ids' => array_values(array_filter(array_column($retryRows, $rowIdColumn), static fn ($id): bool => is_int($id) || is_string($id))),
            ],
            'dependency_closure_next223' => 'no new support component needed; next223 reuses native row-value UPDATE/DELETE RETURNING execution and row-array savepoint images',
            'dependencies' => [
                'sqlite-rowvalue-returning-yield-before-rollback-next223',
                'sqlite-rowvalue-returning-suppressed-after-rollback-to-next223',
                'wordpress-rowvalue-update-delete-returning-yield-savepoint-current-source-next223',
            ],
            'non_overlap_next223' => 'adds RETURNING-yield fencing across ROLLBACK TO for row-value UPDATE/DELETE retries; avoids accepted next218 rollback-to-current-source, next217 transaction OR ROLLBACK, next211 OR IGNORE, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'phase' => $phase,
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $result['action'],
            'conflict_action' => $result['conflict_action'],
            'table' => $result['table'],
            'selected_ids' => $result['plan']->selectedIds,
            'mutation_ids' => $result['plan']->mutationIds,
            'source_rows' => self::rowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
            'returning_rows' => $result['returning'],
            'ignored_rows' => $result['ignored_rows'],
            'deleted_conflict_rows' => $result['deleted_conflict_rows'],
            'conflicts' => $result['conflicts'],
            'failed_conflict' => $result['failed_conflict'] ?? null,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value yield next223 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value yield next223 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int|string> $ids
     * @return list<array<string,mixed>>
     */
    private static function rowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value yield next223 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value yield next223 rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     * @return list<array<string,mixed>>
     */
    private static function flattenReturning(array $yielded): array
    {
        $rows = [];
        foreach ($yielded as $stream) {
            foreach ($stream['rows'] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function changeCount(array $executed): int
    {
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids'] ?? []);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTables(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
            if (($before[$table] ?? null) !== ($after[$table] ?? null)) {
                $changed[] = $table;
            }
        }

        return $changed;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,int>
     */
    private static function rowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $name => $rows) {
            $counts[$name] = count($rows);
        }

        return $counts;
    }
}
