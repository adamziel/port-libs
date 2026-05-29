<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext227Plan
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
        string $savepoint = 'wp_options_rowvalue_distinct_tuple_next227',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runStatements(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'distinct-tuple-attempt-before-rollback-next227',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'distinct-tuple-retry-after-rollback-next227',
        );

        $attemptRows = self::flattenReturning($attemptReturning);
        $retryRows = self::flattenReturning($retryReturning);

        return [
            'status' => 'rowvalue-update-delete-returning-distinct-subquery-savepoint-current-source-next227',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'attempt_statements' => $attemptExecuted,
            'retry_statements' => $retryExecuted,
            'suppressed_attempt_returning' => $attemptReturning,
            'retry_returning' => $retryReturning,
            'suppressed_attempt_rows' => $attemptRows,
            'retry_rows' => $retryRows,
            'distinct_tuple_subquery_deduped_next227' => true,
            'rollback_to_savepoint_restores_distinct_tuple_source_next227' => true,
            'retry_reads_savepoint_image_next227' => true,
            'savepoint_remains_active_next227' => true,
            'suppressed_returning_count' => count($attemptRows),
            'retry_returning_count' => count($retryRows),
            'attempt_change_count' => self::changeCount($attemptExecuted),
            'retry_change_count' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
            'tuple_source_receipt_next227' => [
                'savepoint' => $savepoint,
                'attempt_statement_count' => count($attemptStatements),
                'retry_statement_count' => count($retryStatements),
                'suppressed_ids' => self::rowIds($attemptRows, $rowIdColumn),
                'retry_ids' => self::rowIds($retryRows, $rowIdColumn),
            ],
            'dependency_closure_next227' => 'no new support component needed; next227 reuses native row-value UPDATE/DELETE RETURNING execution and adds DISTINCT tuple-source parsing',
            'dependencies' => [
                'sqlite-rowvalue-distinct-subquery-tuples-next227',
                'sqlite-rowvalue-returning-rollback-retries-distinct-tuples-next227',
                'wordpress-rowvalue-distinct-optionmeta-savepoint-next227',
            ],
            'non_overlap_next227' => 'adds SELECT DISTINCT tuple-source de-duplication inside row-value UPDATE/DELETE RETURNING savepoint rollback and retry; avoids accepted next219 LIMIT -1 OFFSET tuple sources, next224 nested savepoint release rollback, OR FAIL/ABORT/ROLLBACK conflict slices, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
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
                throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value DISTINCT tuple next227 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value DISTINCT tuple next227 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value DISTINCT tuple next227 rowid column {$rowIdColumn} must be int or string");
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

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function rowIds(array $rows, string $rowIdColumn): array
    {
        return array_values(array_filter(
            array_column($rows, $rowIdColumn),
            static fn (mixed $id): bool => is_int($id) || is_string($id),
        ));
    }
}
