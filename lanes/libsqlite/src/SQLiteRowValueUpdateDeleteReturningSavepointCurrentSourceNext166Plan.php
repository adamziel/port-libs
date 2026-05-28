<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext166Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $innerStatements
     * @param list<string> $outerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $innerStatements,
        array $outerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_import_next166',
        string $innerSavepoint = 'wp_options_inner_cleanup_next166',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 needs inner statements');
        }
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 needs outer statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 needs unique constraints');
        }
        if ($outerSavepoint === '' || $innerSavepoint === '' || $outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 needs distinct savepoint names');
        }

        $outerImage = self::normalizeTables($tables);
        [$innerReleasedCurrent, $innerExecuted, $innerReturning] = self::runStatements(
            $outerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-released',
        );
        [$outerAttemptedCurrent, $outerExecuted, $outerReturning] = self::runStatements(
            $innerReleasedCurrent,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-rollback',
        );

        $rollbackToOuter = $outerImage;
        [$retryCurrent, $retryExecuted, $retryReturning] = self::runStatements(
            $rollbackToOuter,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-outer-rollback',
        );

        $discardedReturning = array_merge($innerReturning, $outerReturning);
        $discardedStatements = array_merge($innerExecuted, $outerExecuted);

        return [
            'status' => 'inner-release-discarded-by-outer-rollback-retried',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'inner_released' => true,
            'outer_rolled_back_to_savepoint' => true,
            'outer_savepoint_preserved_after_rollback_to' => true,
            'released_after_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'inner_released_current_source_tables' => $innerReleasedCurrent,
            'outer_attempted_current_source_tables' => $outerAttemptedCurrent,
            'rollback_to_outer_current_source_tables' => $rollbackToOuter,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'inner_released_statements' => $innerExecuted,
            'outer_attempted_statements' => $outerExecuted,
            'retry_statements' => $retryExecuted,
            'inner_released_returning' => $innerReturning,
            'outer_attempted_returning' => $outerReturning,
            'discarded_returning' => $discardedReturning,
            'yielded_returning' => $retryReturning,
            'discarded_returning_count' => self::returningCount($discardedReturning),
            'yielded_returning_count' => self::returningCount($retryReturning),
            'discarded_changes_before_outer_rollback_to' => self::changeCount($discardedStatements),
            'changes_after_retry_release' => self::changeCount($retryExecuted),
            'row_counts' => self::rowCounts($retryCurrent),
            'changed_tables_after_retry' => self::changedTables($outerImage, $retryCurrent),
            'dependencies' => [
                'sqlite-release-inner-savepoint-merges-rowvalue-returning-into-outer-savepoint-next166',
                'sqlite-rollback-to-outer-savepoint-discards-released-inner-returning-next166',
                'sqlite-rowvalue-update-delete-retry-after-outer-rollback-reads-original-current-source-next166',
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
                throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested savepoint next166 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value nested savepoint next166 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested savepoint next166 rowid column {$rowIdColumn} must be int or string");
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
