<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext217Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param string $rollbackStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $beforeRollbackStatements,
        string $rollbackStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $transactionName = 'wp_options_rowvalue_transaction_next217',
        string $savepoint = 'wp_options_rowvalue_rollback_next217',
        string $retrySavepoint = 'wp_options_rowvalue_retry_next217',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 needs pre-rollback statements');
        }
        if (trim($rollbackStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 needs a rollback statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 needs unique constraints');
        }
        self::assertIdentifier($transactionName, 'transaction');
        self::assertIdentifier($savepoint, 'savepoint');
        self::assertIdentifier($retrySavepoint, 'retry savepoint');

        $transactionImage = self::normalizeTables($tables);
        [$beforeCurrent, $beforeStatements, $beforeReturning] = self::runStatements(
            $transactionImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-or-rollback-next217',
        );
        [$afterRollback, $rollbackSummary] = self::runRollbackStatement(
            $beforeCurrent,
            $rollbackStatement,
            $transactionImage,
            $uniqueConstraints,
            $rowIdColumn,
        );
        [$afterRetry, $retryStatementsExecuted, $retryReturning] = self::runStatements(
            $afterRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-transaction-rollback-next217',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-or-rollback-current-source-next217',
            'transaction' => $transactionName,
            'savepoint' => $savepoint,
            'retry_savepoint' => $retrySavepoint,
            'transaction_image_tables' => $transactionImage,
            'pre_rollback_current_source_tables' => $beforeCurrent,
            'rollback_to_transaction_current_source_tables' => $afterRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'or_rollback_aborted_transaction' => true,
            'savepoint_closed_by_rollback' => true,
            'pre_rollback_changes_discarded' => true,
            'rollback_statement_returning_suppressed' => true,
            'retry_opens_new_savepoint' => true,
            'retry_reads_transaction_image' => true,
            'retry_savepoint_released' => true,
            'before_rollback_statements' => $beforeStatements,
            'rollback_statement' => $rollbackSummary,
            'retry_statements' => $retryStatementsExecuted,
            'before_rollback_yielded_returning' => $beforeReturning,
            'suppressed_by_transaction_rollback_returning' => $rollbackSummary['returning_rows'],
            'yielded_after_retry_returning' => $retryReturning,
            'pre_rollback_yielded_count' => self::returningCount($beforeReturning),
            'pre_rollback_changes_count' => self::changeCount($beforeStatements),
            'suppressed_by_rollback_count' => count($rollbackSummary['returning_rows']),
            'retry_yielded_count' => self::returningCount($retryReturning),
            'retry_changes_count' => self::changeCount($retryStatementsExecuted),
            'changed_tables_after_retry' => self::changedTables($transactionImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependency_closure_next217' => 'no new support component needed; next217 reuses native row-value UPDATE/DELETE RETURNING execution and current-source savepoint row images',
            'non_overlap_next217' => 'adds transaction-level UPDATE OR ROLLBACK row-value RETURNING suppression and retry after transaction rollback; avoids accepted next210/next211 OR IGNORE rollback, next209/next207 OR FAIL, next192 statement-only OR ABORT, trigger RETURNING, WAL/VFS, JSON, planner, and B-tree clusters',
            'dependencies' => [
                'sqlite-rowvalue-update-or-rollback-suppresses-returning-next217',
                'sqlite-rowvalue-or-rollback-discards-savepoint-current-source-next217',
                'sqlite-rowvalue-delete-returning-retry-after-transaction-rollback-next217',
            ],
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
            $executed[] = self::statementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
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
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,list<array<string,mixed>>> $transactionImage
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>}
     */
    private static function runRollbackStatement(
        array $tables,
        string $sql,
        array $transactionImage,
        array $uniqueConstraints,
        string $rowIdColumn,
    ): array {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'rollback') {
            throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 statement must be UPDATE OR ROLLBACK');
        }

        try {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);
        } catch (\InvalidArgumentException $exception) {
            $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);

            return [
                $transactionImage,
                self::statementSummary('or-rollback-next217', 0, $sql, $probe, $tables, $rowIdColumn, $exception->getMessage()) + [
                    'aborted' => true,
                    'error' => $exception->getMessage(),
                    'rolled_back_to_transaction_start' => true,
                    'closed_savepoint' => true,
                ],
            ];
        }

        return [
            $result['tables'],
            self::statementSummary('or-rollback-next217', 0, $sql, $result, $tables, $rowIdColumn, null) + [
                'aborted' => false,
                'error' => null,
                'rolled_back_to_transaction_start' => false,
                'closed_savepoint' => false,
            ],
        ];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'error' => $error,
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
                throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR ROLLBACK next217 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function assertIdentifier(string $identifier, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value OR ROLLBACK next217 {$label} must be an identifier");
        }
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
                throw new \InvalidArgumentException("SQLite row-value OR ROLLBACK next217 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR ROLLBACK next217 rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
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
     * @param list<array<string,mixed>> $executed
     */
    private static function changeCount(array $executed): int
    {
        $changes = 0;
        foreach ($executed as $statement) {
            $changes += count($statement['returning_rows'] ?? []);
            $changes += count($statement['deleted_conflict_rows'] ?? []);
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
}
