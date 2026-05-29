<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext214Plan
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
        string $savepoint = 'wp_options_rowvalue_ordered_subquery_next214',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$attemptCurrent, $attemptExecuted, $attemptReturning] = self::runStatements(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-ordered-subquery-before-rollback-next214',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-ordered-subquery-after-rollback-next214',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-ordered-subquery-savepoint-current-source-next214',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'ordered_subquery_limit_respected' => true,
            'retry_reads_savepoint_image' => true,
            'savepoint_released_after_retry' => true,
            'attempt_statements' => $attemptExecuted,
            'retry_statements' => $retryExecuted,
            'discarded_attempt_returning' => $attemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'discarded_attempt_returning_count' => self::returningCount($attemptReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'attempt_changes_before_rollback' => self::changeCount($attemptExecuted),
            'retry_changes_after_rollback' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
            'dependencies' => [
                'sqlite-rowvalue-in-select-order-limit-update-returning-next214',
                'sqlite-rowvalue-not-in-select-order-limit-delete-returning-next214',
                'sqlite-rowvalue-ordered-subquery-savepoint-current-source-next214',
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
            $executed[] = [
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
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ordered subquery next214 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value ordered subquery next214 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ordered subquery next214 rowid column {$rowIdColumn} must be int or string");
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
            $count += count($statement['mutation_ids']);
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
        foreach ($after as $table => $rows) {
            if (($before[$table] ?? null) !== $rows) {
                $changed[] = $table;
            }
        }
        sort($changed);

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
        ksort($counts);

        return $counts;
    }
}
