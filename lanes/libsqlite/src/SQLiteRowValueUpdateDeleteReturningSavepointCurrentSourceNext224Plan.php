<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext224Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $innerStatements
     * @param list<string> $outerAttemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $innerStatements,
        array $outerAttemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next224',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next224',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 needs inner statements');
        }
        if ($outerAttemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 needs outer attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 needs unique constraints');
        }
        self::assertIdentifier($outerSavepoint, 'outer savepoint');
        self::assertIdentifier($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value nested rollback next224 savepoint names must differ');
        }

        $outerImage = self::normalizeTables($tables);
        [$afterInner, $innerExecuted, $innerReturning] = self::runStatements(
            $outerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-released-before-outer-rollback-next224',
        );
        [$afterAttempt, $outerAttemptExecuted, $outerAttemptReturning] = self::runStatements(
            $afterInner,
            $outerAttemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-attempt-before-rollback-next224',
        );

        $afterOuterRollback = $outerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements(
            $afterOuterRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-outer-rollback-next224',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-nested-release-rollback-current-source-next224',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'outer_savepoint_image_tables' => $outerImage,
            'after_inner_release_tables' => $afterInner,
            'outer_attempt_current_source_tables' => $afterAttempt,
            'after_outer_rollback_tables' => $afterOuterRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'inner_release_merged_into_outer_next224' => true,
            'outer_rollback_discards_released_inner_next224' => true,
            'released_inner_returning_suppressed_by_outer_rollback_next224' => true,
            'outer_attempt_returning_suppressed_by_rollback_next224' => true,
            'retry_reads_outer_savepoint_image_next224' => true,
            'outer_savepoint_remains_active_next224' => true,
            'inner_statements' => $innerExecuted,
            'outer_attempt_statements' => $outerAttemptExecuted,
            'retry_statements' => $retryExecuted,
            'released_inner_returning' => $innerReturning,
            'suppressed_outer_attempt_returning' => $outerAttemptReturning,
            'retry_returning' => $retryReturning,
            'released_inner_returning_count' => self::returningCount($innerReturning),
            'outer_attempt_returning_count' => self::returningCount($outerAttemptReturning),
            'suppressed_returning_count' => self::returningCount($innerReturning) + self::returningCount($outerAttemptReturning),
            'retry_returning_count' => self::returningCount($retryReturning),
            'released_inner_change_count' => self::changeCount($innerExecuted),
            'outer_attempt_change_count' => self::changeCount($outerAttemptExecuted),
            'retry_change_count' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($outerImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'rollback_receipt_next224' => [
                'outer_savepoint' => $outerSavepoint,
                'inner_savepoint' => $innerSavepoint,
                'released_inner_statement_count' => count($innerStatements),
                'outer_attempt_statement_count' => count($outerAttemptStatements),
                'retry_statement_count' => count($retryStatements),
                'suppressed_returning_count' => self::returningCount($innerReturning) + self::returningCount($outerAttemptReturning),
                'restored_tables' => array_keys($afterOuterRollback),
            ],
            'dependency_closure_next224' => 'no new support component needed; next224 reuses native row-value UPDATE/DELETE RETURNING execution and nested savepoint row images',
            'dependencies' => [
                'sqlite-rowvalue-nested-release-rolled-back-by-outer-savepoint-next224',
                'sqlite-rowvalue-returning-suppressed-after-outer-rollback-next224',
                'wordpress-rowvalue-nested-savepoint-retry-current-source-next224',
            ],
            'non_overlap_next224' => 'adds nested savepoint RELEASE rows being discarded by a later outer ROLLBACK TO before retry; avoids accepted next218 explicit rollback image restoration, next217 OR ROLLBACK transaction abort, next211 OR IGNORE, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
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
                throw new \InvalidArgumentException('SQLite row-value nested rollback next224 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value nested rollback next224 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function assertIdentifier(string $name, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value nested rollback next224 {$label} must be an identifier");
        }
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
                throw new \InvalidArgumentException("SQLite row-value nested rollback next224 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value nested rollback next224 rowid column {$rowIdColumn} must be int or string");
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
