<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueConflictReturningSavepointCurrentSourceNextPlan
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
        string $savepoint = 'app_settings_rowvalue_conflict_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value conflict RETURNING savepoint needs statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value conflict RETURNING savepoint needs unique constraints');
        }

        $savepointImage = self::normalizeTables($tables);
        $attempted = $savepointImage;
        $executed = [];
        $yielded = [];
        $ignoredRows = [];
        $deletedConflictRows = [];
        $conflicts = [];
        $rollbackReason = null;
        $rollbackStatement = null;
        $transactionAborted = false;

        foreach ($statements as $ordinal => $sql) {
            try {
                $before = $attempted;
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $attempted, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $rollbackReason = $exception->getMessage();
                $rollbackStatement = $ordinal;
                $transactionAborted = stripos($exception->getMessage(), ' using OR ROLLBACK') !== false;
                break;
            }

            $attempted = $result['tables'];
            $statement = [
                'ordinal' => $ordinal,
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

        $rolledBack = $rollbackReason !== null;
        $current = $rolledBack ? $savepointImage : $attempted;

        return [
            'savepoint' => $savepoint,
            'status' => $transactionAborted ? 'transaction-rolled-back' : ($rolledBack ? 'rolled-back-to-savepoint' : 'released'),
            'rolled_back' => $rolledBack,
            'transaction_aborted' => $transactionAborted,
            'rollback_reason' => $rollbackReason,
            'rollback_statement_ordinal' => $rollbackStatement,
            'savepoint_image_tables' => $savepointImage,
            'current_source_tables' => $current,
            'next_source_tables' => $attempted,
            'executed_statements' => $executed,
            'yielded_returning' => $transactionAborted ? [] : $yielded,
            'ignored_rows' => $transactionAborted ? [] : $ignoredRows,
            'deleted_conflict_rows' => $transactionAborted ? [] : $deletedConflictRows,
            'conflicts' => $transactionAborted ? [] : $conflicts,
            'changes' => $rolledBack ? 0 : self::changeCount($executed),
            'attempted_changes' => self::changeCount($executed),
            'row_counts' => self::rowCounts($current),
            'dependencies' => [
                'sqlite-row-value-conflict-algorithms-returning',
                'sqlite-update-or-ignore-skips-returning-row',
                'sqlite-update-or-replace-conflict-delete-before-returning',
                'sqlite-update-or-rollback-aborts-savepoint-transaction',
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
                throw new \InvalidArgumentException('SQLite row-value conflict RETURNING savepoint tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value conflict RETURNING savepoint rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value conflict RETURNING rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value conflict RETURNING rowid column {$rowIdColumn} must be int or string");
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
