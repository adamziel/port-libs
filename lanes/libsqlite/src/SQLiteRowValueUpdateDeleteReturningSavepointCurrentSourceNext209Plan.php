<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext209Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeFailStatements
     * @param string $failStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $beforeFailStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_next209',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeFailStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 needs pre-fail statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$beforeFailCurrent, $beforeFailExecuted, $beforeFailReturning] = self::runStatements(
            $savepointImage,
            $beforeFailStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-fail-next209',
        );

        [$afterFailCurrent, $failSummary, $failReturning] = self::runFailStatement(
            $beforeFailCurrent,
            $failStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'or-fail-next209',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements(
            $afterFailCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-fail-next209',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-or-fail-current-source-next209',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'pre_fail_current_source_tables' => $beforeFailCurrent,
            'fail_current_source_tables' => $afterFailCurrent,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'savepoint_preserved_after_fail' => true,
            'pre_fail_changes_preserved' => true,
            'failing_row_restored_to_statement_start' => true,
            'failed_statement_prior_rows_preserved' => true,
            'failed_statement_returning_suppressed' => true,
            'retry_reads_fail_current_source' => true,
            'savepoint_released_after_retry' => true,
            'pre_fail_statements' => $beforeFailExecuted,
            'fail_statement' => $failSummary,
            'retry_statements' => $retryExecuted,
            'pre_fail_yielded_returning' => $beforeFailReturning,
            'fail_preserved_returning' => $failReturning,
            'suppressed_by_fail_returning' => $failSummary['suppressed_returning_rows'],
            'yielded_after_retry_returning' => $retryReturning,
            'pre_fail_yielded_count' => self::returningCount($beforeFailReturning),
            'fail_preserved_yielded_count' => self::returningCount($failReturning),
            'suppressed_by_fail_count' => count($failSummary['suppressed_returning_rows']),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'pre_fail_changes_preserved_count' => self::changeCount($beforeFailExecuted),
            'fail_changes_preserved_count' => count($failSummary['returning_rows']),
            'retry_changes_after_fail' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-update-or-fail-preserves-prior-returning-next209',
                'sqlite-rowvalue-update-or-fail-suppresses-conflicting-returning-next209',
                'sqlite-rowvalue-delete-returning-retry-after-fail-next209',
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
            $executed[] = self::statementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null);
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
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 fail statement must be UPDATE OR FAIL');
        }

        $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);
        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        $summary = self::statementSummary($phase, 0, $sql, $result, $tables, $rowIdColumn, null);
        $summary['failed'] = ($result['failed_conflict'] ?? null) !== null;
        $summary['failed_conflict'] = $result['failed_conflict'] ?? null;
        $summary['suppressed_returning_rows'] = array_slice($probe['returning'], count($result['returning']));
        $summary['probe_returning_rows'] = $probe['returning'];
        $summary['rolled_back_conflicting_row_only'] = true;

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
    private static function statementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $error): array
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
            'error' => $error,
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
                throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL next209 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next209 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next209 rowid column {$rowIdColumn} must be int or string");
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
}
