<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext204Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $rollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $outerStatements,
        array $savepointStatements,
        array $rollbackStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $transaction = 'wp_options_rowvalue_rollback_txn_next204',
        string $savepoint = 'wp_options_rowvalue_rollback_savepoint_next204',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 needs outer statements');
        }
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 needs savepoint statements');
        }
        if ($rollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 needs rollback statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 needs unique constraints');
        }
        self::assertIdentifier($transaction, 'transaction');
        self::assertIdentifier($savepoint, 'savepoint');

        $transactionImage = self::normalizeTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatements(
            $transactionImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-rollback-savepoint-next204',
        );

        $savepointImage = $afterOuter;
        [$afterSavepoint, $savepointExecuted, $savepointReturning] = self::runStatements(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-rollback-conflict-next204',
        );

        [$rollbackAttempt, $rollbackExecuted, $rollbackReason, $rollbackOrdinal] = self::runRollbackStatements(
            $afterSavepoint,
            $rollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );
        if ($rollbackReason === null) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 expected UPDATE OR ROLLBACK conflict');
        }

        $afterRollback = $transactionImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements(
            $afterRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-transaction-rollback-next204',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-rollback-savepoint-current-source-next204',
            'transaction' => $transaction,
            'savepoint' => $savepoint,
            'transaction_rolled_back' => true,
            'savepoint_invalidated_by_rollback' => true,
            'retry_started_from_transaction_image' => true,
            'retry_transaction_released' => true,
            'rollback_statement_ordinal' => $rollbackOrdinal,
            'rollback_reason' => $rollbackReason,
            'initial_tables' => $transactionImage,
            'outer_current_source_tables' => $afterOuter,
            'savepoint_image_tables' => $savepointImage,
            'savepoint_current_source_tables' => $afterSavepoint,
            'rollback_attempt_tables' => $rollbackAttempt,
            'rollback_to_transaction_tables' => $afterRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'savepoint_statements' => $savepointExecuted,
            'rollback_statements' => $rollbackExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'savepoint_yielded_returning' => $savepointReturning,
            'suppressed_by_transaction_rollback_returning' => array_merge($outerReturning, $savepointReturning),
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCount($outerReturning),
            'savepoint_yielded_returning_count' => self::returningCount($savepointReturning),
            'suppressed_by_transaction_rollback_count' => self::returningCount(array_merge($outerReturning, $savepointReturning)),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'changes_before_rollback' => self::changeCount(array_merge($outerExecuted, $savepointExecuted)),
            'changes_after_rollback_retry' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($transactionImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-update-or-rollback-conflict-rolls-back-transaction-next204',
                'sqlite-rowvalue-returning-stream-suppressed-by-transaction-rollback-next204',
                'sqlite-rowvalue-update-delete-retry-reads-transaction-image-next204',
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
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:?string,3:?int}
     */
    private static function runRollbackStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $executed[] = self::abortedStatementSummary($sql, $ordinal, $before, $rowIdColumn, $exception->getMessage());

                return [$before, $executed, $exception->getMessage(), $ordinal];
            }

            $current = $result['tables'];
            $executed[] = self::statementSummary('rollback-attempt-before-conflict-next204', $ordinal, $sql, $result, $before, $rowIdColumn, null);
        }

        return [$current, $executed, null, null];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $failedMessage): array
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
            'failed_conflict' => $failedMessage === null ? ($result['failed_conflict'] ?? null) : ['message' => $failedMessage],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function abortedStatementSummary(string $sql, int $ordinal, array $before, string $rowIdColumn, string $message): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        $table = $parsed['table'];
        $where = self::wherePredicate($parsed['where']);
        if ($parsed['action'] === 'delete') {
            $plan = SQLiteUpdateDeleteLimitPlan::delete($before[$table] ?? [], $where, $parsed['order_by'], $parsed['limit'], $parsed['offset'], $rowIdColumn);
        } else {
            $plan = SQLiteUpdateDeleteLimitPlan::update(
                $before[$table] ?? [],
                $where,
                self::assignmentCallbacks($parsed['assignments']),
                $parsed['order_by'],
                $parsed['limit'],
                $parsed['offset'],
                $rowIdColumn,
            );
        }

        return [
            'phase' => 'rollback-conflict-suppressed-next204',
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $parsed['action'],
            'conflict_action' => $parsed['conflict_action'],
            'table' => $table,
            'selected_ids' => $plan->selectedIds,
            'mutation_ids' => $plan->mutationIds,
            'source_rows' => self::rowsByIds($before[$table] ?? [], $plan->selectedIds, $rowIdColumn),
            'returning_rows' => [],
            'ignored_rows' => [],
            'deleted_conflict_rows' => [],
            'conflicts' => [],
            'failed_conflict' => ['message' => $message],
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
                throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ROLLBACK savepoint next204 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value ROLLBACK savepoint next204 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ROLLBACK savepoint next204 rowid column {$rowIdColumn} must be int or string");
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

    private static function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value ROLLBACK savepoint next204 {$label} must be an identifier");
        }
    }

    /**
     * @return callable(array<string,mixed>):bool
     */
    private static function wherePredicate(?string $where): callable
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'wherePredicate');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $where);
    }

    /**
     * @param array<string,string> $assignments
     * @return array<string,callable(array<string,mixed>):mixed>
     */
    private static function assignmentCallbacks(array $assignments): array
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'assignmentCallbacks');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $assignments);
    }
}
