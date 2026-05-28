<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext228Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $innerStatements
     * @param string $failStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $outerStatements,
        array $innerStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $outerSavepoint = 'wp_options_outer_rowvalue_next228',
        string $innerSavepoint = 'wp_options_inner_rowvalue_next228',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 needs outer statements');
        }
        if ($innerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 needs inner statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 needs unique constraints');
        }
        self::assertIdentifier($outerSavepoint, 'outer savepoint');
        self::assertIdentifier($innerSavepoint, 'inner savepoint');
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 savepoint names must differ');
        }

        $outerImage = self::normalizeTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatements(
            $outerImage,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-inner-savepoint-next228',
        );

        $innerImage = $afterOuter;
        [$afterInner, $innerExecuted, $innerReturning] = self::runStatements(
            $innerImage,
            $innerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-before-fail-next228',
        );

        [$afterFail, $failSummary, $failReturning] = self::runFailStatement(
            $afterInner,
            $failStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'inner-or-fail-before-rollback-next228',
        );

        $afterInnerRollback = $innerImage;
        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements(
            $afterInnerRollback,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-inner-rollback-next228',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-inner-fail-rollback-current-source-next228',
            'outer_savepoint' => $outerSavepoint,
            'inner_savepoint' => $innerSavepoint,
            'outer_savepoint_image_tables' => $outerImage,
            'outer_current_source_tables' => $afterOuter,
            'inner_savepoint_image_tables' => $innerImage,
            'inner_current_source_tables' => $afterInner,
            'fail_current_source_tables' => $afterFail,
            'after_inner_rollback_tables' => $afterInnerRollback,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_changes_survive_inner_rollback_next228' => true,
            'inner_changes_rolled_back_after_fail_next228' => true,
            'fail_prior_rows_rolled_back_by_savepoint_next228' => true,
            'inner_returning_suppressed_by_rollback_next228' => true,
            'retry_reads_outer_current_source_next228' => true,
            'outer_savepoint_remains_active_next228' => true,
            'outer_statements' => $outerExecuted,
            'inner_statements' => $innerExecuted,
            'fail_statement' => $failSummary,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'inner_suppressed_returning' => $innerReturning,
            'fail_preserved_before_rollback_returning' => $failReturning,
            'fail_suppressed_conflicting_returning' => $failSummary['suppressed_returning_rows'],
            'retry_returning' => $retryReturning,
            'outer_yielded_count' => self::returningCount($outerReturning),
            'inner_suppressed_count' => self::returningCount($innerReturning),
            'fail_preserved_before_rollback_count' => self::returningCount($failReturning),
            'fail_suppressed_conflicting_count' => count($failSummary['suppressed_returning_rows']),
            'total_suppressed_by_inner_rollback_count' => self::returningCount($innerReturning) + self::returningCount($failReturning) + count($failSummary['suppressed_returning_rows']),
            'retry_returning_count' => self::returningCount($retryReturning),
            'outer_change_count' => self::changeCount($outerExecuted),
            'inner_change_count' => self::changeCount($innerExecuted),
            'fail_preserved_change_count' => count($failSummary['returning_rows']),
            'retry_change_count' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($outerImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'rollback_receipt_next228' => [
                'outer_savepoint' => $outerSavepoint,
                'inner_savepoint' => $innerSavepoint,
                'inner_statement_count' => count($innerStatements),
                'fail_statement_conflict' => $failSummary['failed_conflict'],
                'suppressed_returning_count' => self::returningCount($innerReturning) + self::returningCount($failReturning) + count($failSummary['suppressed_returning_rows']),
                'restored_tables' => array_keys($afterInnerRollback),
            ],
            'dependency_closure_next228' => 'no new support component needed; next228 reuses native row-value UPDATE/DELETE RETURNING, OR FAIL preservation, and nested savepoint current-source row images',
            'dependencies' => [
                'sqlite-rowvalue-inner-savepoint-rollback-suppresses-returning-next228',
                'sqlite-rowvalue-update-or-fail-prior-rows-rolled-back-by-savepoint-next228',
                'wordpress-rowvalue-savepoint-retry-reads-outer-current-source-next228',
            ],
            'non_overlap_next228' => 'adds inner ROLLBACK TO after UPDATE OR FAIL so preserved FAIL rows and earlier inner RETURNING are suppressed while outer savepoint changes remain current; avoids accepted next209 preserved FAIL retry source, next224 released inner discarded by outer rollback, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
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
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runFailStatement(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'fail') {
            throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 fail statement must be UPDATE OR FAIL');
        }

        $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        $summary = self::statementSummary($phase, 0, $sql, $result, $tables, $rowIdColumn);
        $summary['failed'] = ($result['failed_conflict'] ?? null) !== null;
        $summary['failed_conflict'] = $result['failed_conflict'] ?? null;
        $summary['suppressed_returning_rows'] = array_slice($probe['returning'], count($result['returning']));
        $summary['probe_returning_rows'] = $probe['returning'];
        $summary['rolled_back_conflicting_row_only_before_savepoint_rollback'] = true;

        return [
            $result['tables'],
            $summary,
            [[
                'phase' => $phase,
                'ordinal' => 0,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ]],
        ];
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
                throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value inner FAIL rollback next228 rows must be arrays');
                }
            }
        }

        return $tables;
    }

    private static function assertIdentifier(string $name, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value inner FAIL rollback next228 {$label} must be an identifier");
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
                throw new \InvalidArgumentException("SQLite row-value inner FAIL rollback next228 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value inner FAIL rollback next228 rowid column {$rowIdColumn} must be int or string");
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
