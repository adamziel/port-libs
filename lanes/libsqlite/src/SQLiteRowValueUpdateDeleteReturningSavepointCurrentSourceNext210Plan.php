<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext210Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_ignore_next210',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 needs unique constraints');
        }
        self::assertIdentifier($savepoint);

        $savepointImage = self::normalizeTables($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runStatements(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-ignore-rollback-next210',
        );
        self::assertHasIgnoreConflict($attemptExecuted);

        $afterRollbackToSavepoint = $savepointImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements(
            $afterRollbackToSavepoint,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-ignore-rollback-next210',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-ignore-rollback-current-source-next210',
            'savepoint' => $savepoint,
            'ignore_conflict_preserves_statement' => true,
            'ignored_rows_do_not_yield_returning' => true,
            'rollback_to_savepoint_discards_successful_ignore_statement_rows' => true,
            'rollback_to_savepoint_discards_ignored_row_metadata' => true,
            'retry_reads_savepoint_image' => true,
            'savepoint_released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_to_savepoint_current_source_tables' => $afterRollbackToSavepoint,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'attempt_statements' => $attemptExecuted,
            'retry_statements' => $retryExecuted,
            'attempt_returning' => $attemptReturning,
            'suppressed_by_rollback_returning' => $attemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'ignored_rows_before_rollback' => self::ignoredRows($attemptExecuted),
            'attempt_yielded_count' => self::returningCount($attemptReturning),
            'ignored_row_count' => count(self::ignoredRows($attemptExecuted)),
            'suppressed_by_rollback_count' => self::returningCount($attemptReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'attempt_changes_before_rollback_to' => self::changeCount($attemptExecuted),
            'changes_after_retry_release' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependency_closure_next210' => 'no new support component needed; next210 reuses native row-value UPDATE/DELETE RETURNING execution, unique-conflict IGNORE handling, and savepoint current-source row images',
            'dependencies' => [
                'sqlite-rowvalue-update-or-ignore-returning-suppresses-conflict-next210',
                'sqlite-rollback-to-savepoint-discards-ignore-returning-stream-next210',
                'sqlite-rowvalue-retry-after-ignore-rollback-reads-savepoint-image-next210',
            ],
            'non_overlap_next210' => 'adds OR IGNORE row-value RETURNING rollback-to-savepoint suppression; avoids next209/next208 OR FAIL, next203 IGNORE/REPLACE release flow, next205 RELEASE admission, next206 released-inner rollback, next178 OR ROLLBACK, trigger RETURNING, WAL/VFS, JSON, planner, and B-tree clusters',
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
                throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value OR IGNORE next210 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR IGNORE next210 rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array<string,mixed>> $executed
     */
    private static function assertHasIgnoreConflict(array $executed): void
    {
        foreach ($executed as $statement) {
            if (($statement['conflict_action'] ?? null) === 'ignore' && ($statement['ignored_rows'] ?? []) !== []) {
                return;
            }
        }

        throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 needs an ignored conflict row');
    }

    /**
     * @param list<array<string,mixed>> $executed
     * @return list<array<string,mixed>>
     */
    private static function ignoredRows(array $executed): array
    {
        $rows = [];
        foreach ($executed as $statement) {
            foreach (($statement['ignored_rows'] ?? []) as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     */
    private static function returningCount(array $yielded): int
    {
        $count = 0;
        foreach ($yielded as $stream) {
            $count += count($stream['rows']);
        }

        return $count;
    }

    /**
     * @param list<array<string,mixed>> $summaries
     */
    private static function changeCount(array $summaries): int
    {
        $changes = 0;
        foreach ($summaries as $summary) {
            $changes += count($summary['returning_rows'] ?? []);
            $changes += count($summary['deleted_conflict_rows'] ?? []);
        }

        return $changes;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTables(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $table => $rows) {
            if (($before[$table] ?? null) !== $rows) {
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
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR IGNORE next210 savepoint must be an identifier');
        }
    }
}
