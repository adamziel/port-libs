<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext206Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $releasedInnerStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $outerStatements,
        array $releasedInnerStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next206',
        string $innerSavepoint = 'wp_options_inner_released_rowvalue_next206',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback next206 needs outer statements');
        }
        if ($releasedInnerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback next206 needs released inner statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback next206 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback next206 needs unique constraints');
        }
        self::assertIdentifier($outerSavepoint, 'outer savepoint');
        self::assertIdentifier($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value outer rollback next206 savepoint names must differ');
        }

        $outerImage = self::normalizeTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatements(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner-release-next206',
        );

        $innerImage = $afterOuter;
        [$afterInnerRelease, $innerExecuted, $innerReturning] = self::runStatements(
            $innerImage,
            $releasedInnerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-released-before-outer-rollback-next206',
        );

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements(
            $afterOuterRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-outer-rollback-next206',
        );

        $discardedReturning = array_merge($outerReturning, $innerReturning);

        return [
            'status' => 'rowvalue-update-delete-returning-released-inner-outer-rollback-current-source-next206',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'inner_released_before_outer_rollback' => true,
            'rolled_back_to_outer_savepoint' => true,
            'outer_savepoint_preserved_after_rollback_to' => true,
            'inner_savepoint_available_after_release' => false,
            'retry_reads_outer_savepoint_image' => true,
            'outer_savepoint_released_after_retry' => true,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_released_current_source_tables' => $afterInnerRelease,
            'rollback_to_outer_current_source_tables' => $afterOuterRollback,
            'retry_current_source_tables' => $afterRetry,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'inner_released_statements' => $innerExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_released_yielded_returning' => $innerReturning,
            'discarded_by_outer_rollback_returning' => $discardedReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_count' => self::returningCount($outerReturning),
            'inner_released_yielded_count' => self::returningCount($innerReturning),
            'discarded_by_outer_rollback_count' => self::returningCount($discardedReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'changes_discarded_by_outer_rollback' => self::changeCount(array_merge($outerExecuted, $innerExecuted)),
            'changes_after_retry' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($outerImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependencies' => [
                'sqlite-release-inner-savepoint-merges-rowvalue-returning-next206',
                'sqlite-rollback-to-outer-savepoint-discards-released-inner-returning-next206',
                'sqlite-rowvalue-retry-after-outer-rollback-reads-outer-image-next206',
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
                throw new \InvalidArgumentException('SQLite row-value outer rollback next206 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value outer rollback next206 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value outer rollback next206 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value outer rollback next206 rowid column {$rowIdColumn} must be int or string");
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

    private static function assertIdentifier(string $value, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value outer rollback next206 {$label} must be an identifier");
        }
    }
}
