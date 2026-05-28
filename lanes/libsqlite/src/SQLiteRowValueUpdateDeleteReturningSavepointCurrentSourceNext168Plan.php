<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext168Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerAttemptStatements
     * @param list<string> $innerRetryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $outerStatements,
        array $innerAttemptStatements,
        array $innerRetryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next168',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next168',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 needs outer statements');
        }
        if ($innerAttemptStatements === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 needs inner attempt statements');
        }
        if ($innerRetryStatements === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 needs inner retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 needs unique constraints');
        }

        $outerImage = self::normalizeTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatements(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner',
        );

        $innerImage = $afterOuter;
        [$attemptedInner, $innerAttempted, $innerAttemptReturning] = self::runStatements(
            $innerImage,
            $innerAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-rollback-to',
        );

        $afterRollbackToInner = $innerImage;
        [$afterRetry, $innerRetry, $innerRetryReturning] = self::runStatements(
            $afterRollbackToInner,
            $innerRetryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-after-rollback-to',
        );

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'outer-released-after-inner-rollback-to-retry-current-source-next168',
            'rolled_back_to_inner_savepoint' => true,
            'inner_savepoint_preserved_after_rollback_to' => true,
            'inner_released_after_retry' => true,
            'outer_released' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'inner_savepoint_image_tables' => $innerImage,
            'attempted_inner_current_source_tables' => $attemptedInner,
            'rollback_to_inner_current_source_tables' => $afterRollbackToInner,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_attempt_statements' => $innerAttempted,
            'inner_retry_statements' => $innerRetry,
            'yielded_outer_returning' => $outerReturning,
            'discarded_inner_returning' => $innerAttemptReturning,
            'yielded_inner_retry_returning' => $innerRetryReturning,
            'yielded_outer_returning_count' => self::returningCount($outerReturning),
            'discarded_inner_returning_count' => self::returningCount($innerAttemptReturning),
            'yielded_inner_retry_returning_count' => self::returningCount($innerRetryReturning),
            'outer_changes' => self::changeCount($outerExecuted),
            'discarded_inner_changes' => self::changeCount($innerAttempted),
            'changes_after_inner_retry' => self::changeCount($innerRetry),
            'total_released_changes' => self::changeCount($outerExecuted) + self::changeCount($innerRetry),
            'changed_tables_after_release' => self::changedTables($outerImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependencies' => [
                'sqlite-nested-savepoint-rollback-to-preserves-outer-rowvalue-returning-next168',
                'sqlite-rowvalue-update-delete-returning-discards-inner-rollback-stream-next168',
                'sqlite-rowvalue-retry-after-inner-rollback-reads-inner-savepoint-image-next168',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatements(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $rowIdColumn,
        string $phase,
    ): array {
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
                throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite nested row-value savepoint next168 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite nested row-value savepoint next168 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite nested row-value savepoint next168 rowid column {$rowIdColumn} must be int or string");
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
