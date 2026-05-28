<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext172Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldedBeforeRollbackStatements
     * @param list<string> $discardedBeforeRollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldedBeforeRollbackStatements,
        array $discardedBeforeRollbackStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_rowvalue_yield_retry_next172',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($yieldedBeforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 needs yielded pre-rollback statements');
        }
        if ($discardedBeforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 needs discarded pre-rollback statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$yieldedAttemptCurrent, $yieldedStatements, $deliveredReturning] = self::runStatements(
            $savepointImage,
            $yieldedBeforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'yielded-before-rollback',
        );
        [$discardAttemptCurrent, $discardedStatements, $discardedReturning] = self::runStatements(
            $yieldedAttemptCurrent,
            $discardedBeforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'discarded-before-rollback',
        );

        $rollbackToCurrent = $savepointImage;
        [$retryCurrent, $retryStatementsExecuted, $retryReturning] = self::runStatements(
            $rollbackToCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-rollback-to',
        );

        $allAttempted = array_merge($yieldedStatements, $discardedStatements);
        $allSuppressed = array_merge($deliveredReturning, $discardedReturning);

        return [
            'status' => 'yielded-rowvalue-returning-stream-rolled-back-and-retried',
            'savepoint' => $savepoint,
            'returning_stream_was_observable_before_rollback' => true,
            'observable_returning_is_not_durable_after_rollback_to' => true,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'yielded_attempt_current_source_tables' => $yieldedAttemptCurrent,
            'discard_attempt_current_source_tables' => $discardAttemptCurrent,
            'rollback_to_current_source_tables' => $rollbackToCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'yielded_before_rollback_statements' => $yieldedStatements,
            'discarded_before_rollback_statements' => $discardedStatements,
            'retry_statements' => $retryStatementsExecuted,
            'delivered_before_rollback_returning' => $deliveredReturning,
            'discarded_before_rollback_returning' => $discardedReturning,
            'suppressed_by_rollback_returning' => $allSuppressed,
            'yielded_after_retry_returning' => $retryReturning,
            'delivered_before_rollback_count' => self::returningCount($deliveredReturning),
            'discarded_before_rollback_count' => self::returningCount($discardedReturning),
            'suppressed_by_rollback_count' => self::returningCount($allSuppressed),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'attempted_changes_before_rollback_to' => self::changeCount($allAttempted),
            'changes_after_retry_release' => self::changeCount($retryStatementsExecuted),
            'row_counts' => self::rowCounts($retryCurrent),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retryCurrent),
            'dependencies' => [
                'sqlite-rowvalue-returning-yield-before-savepoint-rollback-next172',
                'sqlite-rollback-to-suppresses-yielded-returning-durability-next172',
                'sqlite-rowvalue-update-delete-retry-current-source-after-yield-rollback-next172',
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
                throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value yield savepoint next172 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value yield savepoint next172 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value yield savepoint next172 rowid column {$rowIdColumn} must be int or string");
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
}
