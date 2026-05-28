<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext164Plan
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
        string $savepoint = 'wp_options_rowvalue_rollback_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK retry next164 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK retry next164 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ROLLBACK retry next164 needs unique constraints');
        }

        $transactionImage = self::normalizeTables($tables);
        [$attemptedCurrent, $attempted, $attemptedReturning, $rollbackReason, $rollbackOrdinal] = self::runUntilRollback(
            $transactionImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );
        $rolledBack = $rollbackReason !== null;
        $retryBase = $rolledBack ? $transactionImage : $attemptedCurrent;
        [$retryCurrent, $retry, $retryReturning] = self::runRetry(
            $retryBase,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        return [
            'savepoint' => $savepoint,
            'status' => $rolledBack ? 'transaction-rolled-back-retried-current-source-next164' : 'released-without-rollback-current-source-next164',
            'transaction_rolled_back' => $rolledBack,
            'savepoint_preserved_after_rollback' => false,
            'rollback_statement_ordinal' => $rollbackOrdinal,
            'rollback_reason' => $rollbackReason,
            'transaction_image_tables' => $transactionImage,
            'attempted_current_source_tables' => $attemptedCurrent,
            'rollback_current_source_tables' => $retryBase,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'attempt_statements' => $attempted,
            'retry_statements' => $retry,
            'attempted_returning_before_rollback' => $attemptedReturning,
            'discarded_returning' => $rolledBack ? $attemptedReturning : [],
            'yielded_returning' => $retryReturning,
            'discarded_returning_count' => $rolledBack ? self::returningCount($attemptedReturning) : 0,
            'yielded_returning_count' => self::returningCount($retryReturning),
            'attempted_changes_before_rollback' => self::changeCount($attempted),
            'changes_after_retry' => self::changeCount($retry),
            'changed_tables_after_retry' => self::changedTables($transactionImage, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
            'dependencies' => [
                'sqlite-update-or-rollback-rowvalue-returning-cancels-savepoint-transaction',
                'sqlite-rollback-conflict-discards-attempted-returning-streams',
                'sqlite-rowvalue-update-delete-returning-retry-starts-from-transaction-image-next164',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?string,4:?int}
     */
    private static function runUntilRollback(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];
        $rollbackReason = null;
        $rollbackOrdinal = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $rollbackReason = $exception->getMessage();
                $rollbackOrdinal = $ordinal;
                if (stripos($rollbackReason, ' using OR ROLLBACK') === false) {
                    throw $exception;
                }
                break;
            }

            $current = $result['tables'];
            $executed[] = self::statementSummary('before-rollback', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'before-rollback',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded, $rollbackReason, $rollbackOrdinal];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runRetry(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummary('after-rollback', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'after-rollback',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
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
                throw new \InvalidArgumentException('SQLite row-value ROLLBACK retry next164 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ROLLBACK retry next164 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value ROLLBACK retry next164 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ROLLBACK retry next164 rowid column {$rowIdColumn} must be int or string");
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
