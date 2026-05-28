<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext187Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $outerStatements,
        array $savepointStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $transaction = 'wp_options_rowvalue_abort_txn_next187',
        string $savepoint = 'wp_options_rowvalue_abort_savepoint_next187',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === [] || $savepointStatements === [] || $retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next187 needs outer, savepoint, and retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next187 needs unique constraints');
        }

        $transactionImage = self::normalizeTables($tables);
        [$outerCurrent, $outerExecuted, $outerReturning] = self::runStatements(
            $transactionImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer',
        );

        $savepointImage = $outerCurrent;
        [$failedCurrent, $savepointExecuted, $savepointReturning, $rollbackReason, $rollbackOrdinal] = self::runSavepointUntilAbort(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        $rolledBackSavepoint = $rollbackReason !== null;
        $retrySource = $rolledBackSavepoint ? $savepointImage : $failedCurrent;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatements(
            $retrySource,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry',
        );

        $discardedReturning = $rolledBackSavepoint ? $savepointReturning : [];

        return [
            'transaction' => $transaction,
            'savepoint' => $savepoint,
            'status' => $rolledBackSavepoint ? 'savepoint-rolled-back-retried-current-source-next187' : 'savepoint-released-retried-current-source-next187',
            'rolled_back_transaction' => false,
            'rolled_back_savepoint' => $rolledBackSavepoint,
            'savepoint_preserved_after_rollback' => $rolledBackSavepoint,
            'rollback_statement_ordinal' => $rollbackOrdinal,
            'rollback_reason' => $rollbackReason,
            'transaction_image_tables' => $transactionImage,
            'outer_current_source_tables' => $outerCurrent,
            'savepoint_image_tables' => $savepointImage,
            'failed_current_source_tables' => $failedCurrent,
            'rollback_to_current_source_tables' => $retrySource,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'outer_statements' => $outerExecuted,
            'savepoint_statements' => $savepointExecuted,
            'retry_statements' => $retryExecuted,
            'outer_returning' => $outerReturning,
            'discarded_returning' => $discardedReturning,
            'yielded_returning' => $retryReturning,
            'outer_returning_count' => self::returningCount($outerReturning),
            'discarded_returning_count' => self::returningCount($discardedReturning),
            'yielded_returning_count' => self::returningCount($retryReturning),
            'attempted_changes_before_rollback' => self::changeCount($savepointExecuted),
            'outer_changes_preserved' => self::changeCount($outerExecuted),
            'changes_after_retry' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($transactionImage, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
            'dependencies' => [
                'sqlite-rowvalue-update-delete-returning-abort-preserves-outer-transaction-next187',
                'sqlite-rowvalue-abort-savepoint-discards-attempted-returning-next187',
                'sqlite-rowvalue-abort-retry-reads-savepoint-image-next187',
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
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?string,4:?int}
     */
    private static function runSavepointUntilAbort(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                if (!str_contains($exception->getMessage(), ' using OR ABORT')) {
                    throw $exception;
                }
                $executed[] = [
                    'phase' => 'savepoint',
                    'ordinal' => $ordinal,
                    'sql' => $sql,
                    'action' => str_starts_with(strtoupper(ltrim($sql)), 'DELETE') ? 'delete' : 'update',
                    'conflict_action' => 'abort',
                    'table' => self::statementTableName($sql),
                    'selected_ids' => [],
                    'mutation_ids' => [],
                    'source_rows' => [],
                    'returning_rows' => [],
                    'ignored_rows' => [],
                    'deleted_conflict_rows' => [],
                    'conflicts' => [],
                    'failed_conflict' => ['message' => $exception->getMessage()],
                ];

                return [$current, $executed, $yielded, $exception->getMessage(), $ordinal];
            }

            $current = $result['tables'];
            $executed[] = self::statementSummary('savepoint', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'savepoint',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded, null, null];
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

    private static function statementTableName(string $sql): string
    {
        if (preg_match('/^\s*DELETE\s+FROM\s+([A-Za-z_][A-Za-z0-9_]*)/i', $sql, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/^\s*UPDATE(?:\s+OR\s+[A-Z]+)?\s+([A-Za-z_][A-Za-z0-9_]*)/i', $sql, $match) === 1) {
            return $match[1];
        }

        return '';
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next187 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next187 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next187 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next187 rowid column {$rowIdColumn} must be int or string");
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
        $names = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        sort($names);
        $changed = [];
        foreach ($names as $name) {
            if (($before[$name] ?? null) !== ($after[$name] ?? null)) {
                $changed[] = $name;
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
