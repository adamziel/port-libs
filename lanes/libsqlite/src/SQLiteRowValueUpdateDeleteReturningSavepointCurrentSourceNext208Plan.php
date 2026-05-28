<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext208Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $preFailStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $outerStatements,
        array $preFailStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_statement_next208',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 needs outer statements');
        }
        if ($preFailStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 needs pre-fail statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 needs unique constraints');
        }
        self::assertIdentifier($savepoint);

        $initial = self::normalizeTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatements(
            $initial,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-fail-savepoint-next208',
            false,
        );

        $savepointImage = $afterOuter;
        [$beforeFail, $preFailExecuted, $preFailReturning] = self::runStatements(
            $savepointImage,
            $preFailStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-or-fail-next208',
            false,
        );

        $failBefore = $beforeFail;
        $failResult = SQLiteUpdateDeleteReturningSql::execute($failStatement, $beforeFail, $rowIdColumn, $uniqueConstraints, true);
        $failCurrent = $failResult['tables'];
        $failSummary = self::statementSummary(
            'or-fail-partial-current-source-next208',
            0,
            $failStatement,
            $failResult,
            $failBefore,
            $rowIdColumn,
        );

        [$afterRetryFromFail, $retryFromFailExecuted, $retryFromFailReturning] = self::runStatements(
            $failCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-before-savepoint-rollback-next208',
            false,
        );

        $afterRollbackToSavepoint = $savepointImage;

        return [
            'status' => 'rowvalue-update-delete-returning-or-fail-savepoint-current-source-next208',
            'savepoint' => $savepoint,
            'or_fail_statement_preserved_prior_rows' => true,
            'or_fail_statement_stopped_at_conflict' => $failResult['failed_conflict'] !== null,
            'or_fail_returning_rows_visible_before_rollback_to_savepoint' => true,
            'retry_reads_partial_fail_current_source' => true,
            'rolled_back_to_savepoint_after_retry' => true,
            'savepoint_released_after_rollback' => true,
            'initial_tables' => $initial,
            'outer_current_source_tables' => $afterOuter,
            'savepoint_image_tables' => $savepointImage,
            'pre_fail_current_source_tables' => $beforeFail,
            'fail_statement_current_source_tables' => $failCurrent,
            'retry_current_source_before_rollback_tables' => $afterRetryFromFail,
            'rollback_to_savepoint_current_source_tables' => $afterRollbackToSavepoint,
            'current_source_tables' => $afterRollbackToSavepoint,
            'next_source_tables' => $afterRollbackToSavepoint,
            'outer_statements' => $outerExecuted,
            'pre_fail_statements' => $preFailExecuted,
            'fail_statement' => $failSummary,
            'retry_statements' => $retryFromFailExecuted,
            'outer_yielded_returning' => $outerReturning,
            'pre_fail_yielded_returning' => $preFailReturning,
            'or_fail_yielded_returning' => [[
                'phase' => 'or-fail-partial-current-source-next208',
                'ordinal' => 0,
                'action' => $failResult['action'],
                'conflict_action' => $failResult['conflict_action'],
                'rows' => $failResult['returning'],
            ]],
            'retry_yielded_returning' => $retryFromFailReturning,
            'or_fail_returning_count' => count($failResult['returning']),
            'pre_fail_yielded_count' => self::returningCount($preFailReturning),
            'retry_yielded_count_before_rollback' => self::returningCount($retryFromFailReturning),
            'changes_preserved_by_or_fail' => count($failResult['returning']),
            'changes_after_retry_before_rollback' => self::changeCount($retryFromFailExecuted),
            'changes_discarded_by_rollback_to_savepoint' => count($failResult['returning']) + self::changeCount($retryFromFailExecuted),
            'failed_conflict' => $failResult['failed_conflict'],
            'changed_tables_after_rollback' => self::changedTables($initial, $afterRollbackToSavepoint),
            'row_counts' => self::rowCounts($afterRollbackToSavepoint),
            'dependencies' => [
                'sqlite-update-or-fail-rowvalue-returning-preserves-prior-rows-next208',
                'sqlite-rowvalue-retry-reads-partial-or-fail-current-source-next208',
                'sqlite-rollback-to-savepoint-discards-or-fail-returning-current-source-next208',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase, bool $preserveFailChanges): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints, $preserveFailChanges);
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
                throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next208 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next208 rowid column {$rowIdColumn} must be int or string");
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

    private static function assertIdentifier(string $value): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next208 savepoint must be an identifier');
        }
    }
}
