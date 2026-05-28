<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext218Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $savepointStatements
     * @param list<string> $attemptedStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $savepointStatements,
        array $attemptedStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_rollback_to_next218',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback next218 needs savepoint statements');
        }
        if ($attemptedStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback next218 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback next218 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback next218 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value rollback next218 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$attemptSource, $savepointExecuted, $savepointReturning] = self::runStatements(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-rollback-to-next218',
        );
        [$attemptCurrent, $attemptedExecuted, $attemptedReturning] = self::runStatements(
            $attemptSource,
            $attemptedStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-rollback-to-next218',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-rollback-to-next218',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-rollback-to-current-source-next218',
            'savepoint' => $savepoint,
            'rollback_to_savepoint_next218' => true,
            'savepoint_remains_active_next218' => true,
            'attempted_returning_suppressed_by_rollback_next218' => true,
            'retry_reads_savepoint_image_next218' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempt_source_tables' => $attemptSource,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'savepoint_statements' => $savepointExecuted,
            'attempted_statements' => $attemptedExecuted,
            'retry_statements' => $retryExecuted,
            'savepoint_returning' => $savepointReturning,
            'suppressed_attempted_returning' => $attemptedReturning,
            'retry_returning' => $retryReturning,
            'savepoint_returning_count' => self::returningCount($savepointReturning),
            'suppressed_attempted_returning_count' => self::returningCount($attemptedReturning),
            'retry_returning_count' => self::returningCount($retryReturning),
            'attempted_change_count' => self::changeCount($attemptedExecuted),
            'retry_change_count' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
            'rollback_receipt_next218' => [
                'savepoint' => $savepoint,
                'restored_tables' => array_keys($rollbackCurrent),
                'suppressed_returning_count' => self::returningCount($attemptedReturning),
                'retry_statement_count' => count($retryStatements),
            ],
            'dependency_closure_next218' => 'no new support component needed; next218 reuses native row-value UPDATE/DELETE RETURNING execution and row-array savepoint images',
            'dependencies' => [
                'sqlite-rowvalue-rollback-to-restores-savepoint-image-next218',
                'sqlite-rowvalue-returning-suppressed-after-rollback-to-next218',
                'wordpress-rowvalue-update-delete-returning-savepoint-rollback-next218',
            ],
            'non_overlap_next218' => 'models explicit ROLLBACK TO savepoint image restoration after successful row-value UPDATE/DELETE RETURNING attempts; avoids accepted next200 statement ABORT preservation, next205 RELEASE current-source admission, next211 OR IGNORE/savepoint behavior, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
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
                throw new \InvalidArgumentException('SQLite row-value rollback next218 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value rollback next218 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value rollback next218 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value rollback next218 rowid column {$rowIdColumn} must be int or string");
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
        $count = 0;
        foreach ($executed as $statement) {
            $count += count($statement['mutation_ids'] ?? []);
        }

        return $count;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTables(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
            if (($before[$table] ?? null) !== ($after[$table] ?? null)) {
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
