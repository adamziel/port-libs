<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext182Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $outerStatements,
        array $innerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next182',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next182',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 needs outer statements');
        }
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 needs released inner statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 needs unique constraints');
        }
        if ($outerSavepoint === '' || $innerSavepoint === '' || $outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 needs distinct savepoint names');
        }

        $outerImage = self::normalizeTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatements($outerImage, $outerStatements, $uniqueConstraints, $rowIdColumn, 'outer-before-inner-release');

        $innerImage = $afterOuter;
        [$afterInnerRelease, $innerExecuted, $innerReturning] = self::runStatements($innerImage, $innerStatements, $uniqueConstraints, $rowIdColumn, 'inner-released-into-outer');

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements($afterOuterRollback, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-after-outer-rollback');

        $suppressedReturning = array_merge($outerReturning, $innerReturning);

        return [
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'status' => 'released-inner-returning-suppressed-by-outer-rollback-next182',
            'inner_released_into_outer_before_rollback' => true,
            'rolled_back_to_outer_savepoint' => true,
            'outer_savepoint_preserved_after_rollback_to' => true,
            'outer_released_after_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_released_current_source_tables' => $afterInnerRelease,
            'rollback_to_outer_current_source_tables' => $afterOuterRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_released_statements' => $innerExecuted,
            'retry_statements' => $retryExecuted,
            'outer_returning_before_rollback' => $outerReturning,
            'inner_returning_released_before_rollback' => $innerReturning,
            'suppressed_by_outer_rollback_returning' => $suppressedReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_returning_before_rollback_count' => self::returningCount($outerReturning),
            'inner_returning_released_before_rollback_count' => self::returningCount($innerReturning),
            'suppressed_by_outer_rollback_count' => self::returningCount($suppressedReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'outer_changes_before_rollback' => self::changeCount($outerExecuted),
            'inner_changes_released_before_rollback' => self::changeCount($innerExecuted),
            'retry_changes_after_outer_rollback' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($outerImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-returning-release-inner-merge-next182',
                'sqlite-rollback-to-outer-suppresses-released-inner-returning-next182',
                'sqlite-rowvalue-update-delete-retry-starts-from-outer-image-next182',
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
                throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value released-inner rollback next182 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value released-inner rollback next182 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value released-inner rollback next182 rowid column {$rowIdColumn} must be int or string");
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
            $count += count($statement['deleted_conflict_rows']);
            $count -= count($statement['ignored_rows']);
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
