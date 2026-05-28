<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext196Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $preFailStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $preFailStatements,
        string $failStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_fail_next196',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($preFailStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 needs pre-fail statements');
        }
        if (trim($failStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 needs a fail statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$beforeFail, $preFailExecuted, $preFailReturning] = self::runStatements(
            $savepointImage,
            $preFailStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-fail-statement',
        );

        [$afterFail, $failSummary] = self::runFailStatement(
            $beforeFail,
            $failStatement,
            $uniqueConstraints,
            $rowIdColumn,
            'rowvalue-or-fail-statement',
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements(
            $afterFail,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-or-fail',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'rowvalue-or-fail-preserves-statement-prefix-next196',
            'savepoint_active_after_fail' => true,
            'savepoint_released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'pre_fail_current_source_tables' => $beforeFail,
            'fail_partial_current_source_tables' => $afterFail,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'pre_fail_statements' => $preFailExecuted,
            'fail_statement' => $failSummary,
            'retry_statements' => $retryExecuted,
            'pre_fail_returning' => $preFailReturning,
            'yielded_before_fail_count' => self::returningCount($preFailReturning),
            'yielded_by_fail_before_conflict' => $failSummary['returning_rows'],
            'yielded_by_fail_before_conflict_count' => count($failSummary['returning_rows']),
            'yielded_after_retry_returning' => $retryReturning,
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'pre_fail_changes_preserved' => self::changeCount($preFailExecuted),
            'fail_prefix_changes_preserved' => count($failSummary['returning_rows']),
            'retry_changes_after_fail' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependencies' => [
                'sqlite-rowvalue-update-or-fail-prefix-preserved-next196',
                'sqlite-rowvalue-savepoint-current-source-after-fail-next196',
                'sqlite-rowvalue-delete-returning-retry-after-fail-next196',
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
            $executed[] = self::statementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn, null) + [
                'failed' => false,
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
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>}
     */
    private static function runFailStatement(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'fail') {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 fail statement must be UPDATE OR FAIL');
        }

        $thrown = null;
        try {
            SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);
        } catch (\InvalidArgumentException $exception) {
            $thrown = $exception->getMessage();
        }
        if ($thrown === null) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 expected a unique conflict');
        }

        $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints, true);
        if (($result['failed_conflict'] ?? null) === null) {
            throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 expected preserved failed conflict metadata');
        }

        return [
            $result['tables'],
            self::statementSummary($phase, 0, $sql, $result, $tables, $rowIdColumn, $thrown) + [
                'failed' => true,
                'statement_rolled_back' => false,
                'prefix_changes_preserved' => count($result['returning']),
            ],
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
                throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR FAIL next196 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next196 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR FAIL next196 rowid column {$rowIdColumn} must be int or string");
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
