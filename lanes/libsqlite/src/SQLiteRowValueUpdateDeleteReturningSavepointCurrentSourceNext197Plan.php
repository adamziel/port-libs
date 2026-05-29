<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext197Plan
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
        string $outerSavepoint = 'wp_options_rowvalue_rollback_outer_next197',
        string $innerSavepoint = 'wp_options_rowvalue_rollback_inner_next197',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to next197 needs outer statements');
        }
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to next197 needs inner statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to next197 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to next197 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $outerSavepoint) !== 1 || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $innerSavepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value rollback-to next197 savepoint names must be identifiers');
        }

        $outerImage = self::normalizeTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatements(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner-rollback-next197',
        );

        $innerImage = $afterOuter;
        [$afterInner, $innerExecuted, $innerReturning] = self::runStatements(
            $innerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-rollback-to-next197',
        );

        $rolledBack = $innerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements(
            $rolledBack,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-rollback-to-next197',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-rollback-to-current-source-next197',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'rollback_to_inner_savepoint' => true,
            'inner_savepoint_preserved_after_rollback_to' => true,
            'inner_released_after_retry' => true,
            'outer_released_after_inner_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_attempt_current_source_tables' => $afterInner,
            'rollback_to_current_source_tables' => $rolledBack,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_statements' => $innerExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_rolled_back_returning' => $innerReturning,
            'suppressed_by_rollback_to_returning' => $innerReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCount($outerReturning),
            'inner_rolled_back_returning_count' => self::returningCount($innerReturning),
            'suppressed_by_rollback_to_count' => self::returningCount($innerReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'outer_changes_preserved' => self::changeCount($outerExecuted),
            'inner_changes_rolled_back' => self::changeCount($innerExecuted),
            'retry_changes_after_rollback_to' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($outerImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-returning-rollback-to-savepoint-next197',
                'sqlite-rowvalue-delete-returning-rollback-to-restores-current-source-next197',
                'sqlite-rowvalue-update-returning-retry-after-rollback-to-next197',
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
                throw new \InvalidArgumentException('SQLite row-value rollback-to next197 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value rollback-to next197 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value rollback-to next197 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value rollback-to next197 rowid column {$rowIdColumn} must be int or string");
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
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }

        return $counts;
    }
}
