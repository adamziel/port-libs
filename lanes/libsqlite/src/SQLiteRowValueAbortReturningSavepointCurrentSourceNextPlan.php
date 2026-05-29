<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueAbortReturningSavepointCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_abort_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT RETURNING savepoint needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT RETURNING savepoint needs unique constraints');
        }

        $savepointImage = self::normalizeTables($tables);
        $current = $savepointImage;
        $attempted = $savepointImage;
        $executed = [];
        $yielded = [];
        $ignoredRows = [];
        $deletedConflictRows = [];
        $conflicts = [];
        $abortReason = null;
        $abortStatement = null;
        $abortedStatementImage = null;
        $transactionRolledBack = false;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $abortReason = $exception->getMessage();
                $abortStatement = $ordinal;
                $abortedStatementImage = $before;
                $transactionRolledBack = stripos($abortReason, ' using OR ROLLBACK') !== false;
                $current = $transactionRolledBack ? $savepointImage : $before;
                break;
            }

            $current = $result['tables'];
            $attempted = $current;
            $statement = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'table' => $result['table'],
                'selected_ids' => $result['plan']->selectedIds,
                'mutation_ids' => $result['plan']->mutationIds,
                'returning_rows' => $result['returning'],
                'ignored_rows' => $result['ignored_rows'],
                'deleted_conflict_rows' => $result['deleted_conflict_rows'],
                'conflicts' => $result['conflicts'],
            ];
            $executed[] = $statement;
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];

            foreach ($result['ignored_rows'] as $row) {
                $ignoredRows[] = ['ordinal' => $ordinal, 'row' => $row];
            }
            foreach ($result['deleted_conflict_rows'] as $row) {
                $deletedConflictRows[] = ['ordinal' => $ordinal, 'row' => $row];
            }
            foreach ($result['conflicts'] as $conflict) {
                $conflicts[] = ['ordinal' => $ordinal] + $conflict;
            }
        }

        $aborted = $abortReason !== null;

        return [
            'savepoint' => $savepoint,
            'status' => $transactionRolledBack ? 'transaction-rolled-back' : ($aborted ? 'statement-aborted-savepoint-active' : 'released'),
            'aborted' => $aborted,
            'transaction_rolled_back' => $transactionRolledBack,
            'savepoint_preserved' => $aborted && !$transactionRolledBack,
            'abort_reason' => $abortReason,
            'abort_statement_ordinal' => $abortStatement,
            'savepoint_image_tables' => $savepointImage,
            'aborted_statement_image_tables' => $abortedStatementImage,
            'current_source_tables' => $current,
            'next_source_tables' => $attempted,
            'executed_statements' => $executed,
            'yielded_returning' => $transactionRolledBack ? [] : $yielded,
            'ignored_rows' => $transactionRolledBack ? [] : $ignoredRows,
            'deleted_conflict_rows' => $transactionRolledBack ? [] : $deletedConflictRows,
            'conflicts' => $transactionRolledBack ? [] : $conflicts,
            'changes' => $transactionRolledBack ? 0 : self::changeCount($executed),
            'attempted_changes' => self::changeCount($executed),
            'row_counts' => self::rowCounts($current),
            'dependencies' => [
                'sqlite-update-or-abort-statement-rollback',
                'sqlite-row-value-returning-abort-current-source',
                'sqlite-savepoint-preserves-prior-returning-yields',
            ],
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
                throw new \InvalidArgumentException('SQLite row-value ABORT RETURNING savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ABORT RETURNING savepoint rows must be arrays');
                }
            }
        }

        return $tables;
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
