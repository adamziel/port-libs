<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext169Plan
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
        string $savepoint = 'wp_options_rowvalue_abort_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next169 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next169 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next169 needs unique constraints');
        }

        $savepointImage = self::normalizeTables($tables);
        [$attemptedCurrent, $attempted, $attemptedReturning, $abortReason, $abortOrdinal] = self::runUntilAbort(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );
        $statementAborted = $abortReason !== null;
        [$retryCurrent, $retry, $retryReturning] = self::runRetry(
            $attemptedCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        return [
            'savepoint' => $savepoint,
            'status' => $statementAborted ? 'statement-aborted-savepoint-preserved-retried-current-source-next169' : 'released-without-abort-current-source-next169',
            'statement_aborted' => $statementAborted,
            'transaction_rolled_back' => false,
            'rolled_back_to_savepoint' => false,
            'savepoint_preserved_after_abort' => true,
            'released_after_retry' => true,
            'abort_statement_ordinal' => $abortOrdinal,
            'abort_reason' => $abortReason,
            'savepoint_image_tables' => $savepointImage,
            'attempted_current_source_tables' => $attemptedCurrent,
            'abort_current_source_tables' => $attemptedCurrent,
            'retry_base_current_source_tables' => $attemptedCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'attempt_statements' => $attempted,
            'retry_statements' => $retry,
            'yielded_before_abort' => $attemptedReturning,
            'aborted_statement_returning' => [],
            'yielded_returning' => $retryReturning,
            'yielded_before_abort_count' => self::returningCount($attemptedReturning),
            'aborted_statement_returning_count' => 0,
            'yielded_returning_count' => self::returningCount($retryReturning),
            'changes_before_abort' => self::changeCount($attempted),
            'changes_after_retry' => self::changeCount($retry),
            'total_changes_after_release' => self::changeCount($attempted) + self::changeCount($retry),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
            'dependencies' => [
                'sqlite-update-or-abort-rowvalue-conflict-rolls-back-current-statement-only',
                'sqlite-abort-conflict-preserves-savepoint-and-prior-returning-streams',
                'sqlite-rowvalue-update-delete-returning-retry-continues-from-abort-current-source-next169',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>,3:?string,4:?int}
     */
    private static function runUntilAbort(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];
        $abortReason = null;
        $abortOrdinal = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $abortReason = $exception->getMessage();
                $abortOrdinal = $ordinal;
                if (stripos($abortReason, ' using OR ABORT') === false) {
                    throw $exception;
                }
                break;
            }

            $current = $result['tables'];
            $executed[] = self::statementSummary('before-abort', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'before-abort',
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $executed, $yielded, $abortReason, $abortOrdinal];
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
            $executed[] = self::statementSummary('after-abort', $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => 'after-abort',
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
                throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next169 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next169 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next169 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next169 rowid column {$rowIdColumn} must be int or string");
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
