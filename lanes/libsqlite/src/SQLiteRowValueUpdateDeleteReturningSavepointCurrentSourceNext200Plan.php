<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext200Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $outerStatements
     * @param list<string> $savepointStatements
     * @param list<string> $abortStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $outerStatements,
        array $savepointStatements,
        array $abortStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_abort_statement_next200',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($outerStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 needs outer statements');
        }
        if ($savepointStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 needs savepoint statements');
        }
        if ($abortStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 needs abort statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 needs unique constraints');
        }
        self::assertIdentifier($savepoint, 'savepoint');

        $initialTables = self::normalizeTables($tables);
        [$afterOuter, $outerExecuted, $outerReturning] = self::runStatements(
            $initialTables,
            $outerStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'outer-before-savepoint-next200',
        );

        $savepointImage = $afterOuter;
        [$afterSavepoint, $savepointExecuted, $savepointReturning] = self::runStatements(
            $savepointImage,
            $savepointStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'savepoint-before-abort-next200',
        );

        [$afterAbort, $abortExecuted, $abortReason, $abortOrdinal] = self::runAbortStatements(
            $afterSavepoint,
            $abortStatements,
            $uniqueConstraints,
            $rowIdColumn,
        );

        [$afterRetry, $retryExecuted, $retryReturning] = self::runStatements(
            $afterAbort,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-abort-next200',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'rowvalue-update-delete-returning-abort-statement-current-source-next200',
            'statement_aborted' => $abortReason !== null,
            'rolled_back_to_savepoint' => false,
            'savepoint_preserved_after_abort' => true,
            'savepoint_released_after_retry' => true,
            'abort_statement_ordinal' => $abortOrdinal,
            'abort_reason' => $abortReason,
            'initial_tables' => $initialTables,
            'outer_current_source_tables' => $afterOuter,
            'savepoint_image_tables' => $savepointImage,
            'savepoint_current_source_tables' => $afterSavepoint,
            'abort_current_source_tables' => $afterAbort,
            'retry_current_source_tables' => $afterRetry,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'outer_statements' => $outerExecuted,
            'savepoint_statements' => $savepointExecuted,
            'abort_statements' => $abortExecuted,
            'retry_statements' => $retryExecuted,
            'outer_yielded_returning' => $outerReturning,
            'savepoint_yielded_returning' => $savepointReturning,
            'abort_suppressed_returning' => [],
            'yielded_after_retry_returning' => $retryReturning,
            'outer_yielded_returning_count' => self::returningCount($outerReturning),
            'savepoint_yielded_returning_count' => self::returningCount($savepointReturning),
            'abort_suppressed_returning_count' => 0,
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'changes_preserved_before_abort' => self::changeCount(array_merge($outerExecuted, $savepointExecuted)),
            'changes_after_retry' => self::changeCount($retryExecuted),
            'changed_tables_after_retry' => self::changedTables($initialTables, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependencies' => [
                'sqlite-update-or-abort-rowvalue-returning-discards-failed-statement-next200',
                'sqlite-savepoint-current-source-survives-abort-statement-next200',
                'sqlite-rowvalue-update-delete-retry-reads-post-abort-current-source-next200',
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
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:?string,3:?int}
     */
    private static function runAbortStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn): array
    {
        $current = $tables;
        $executed = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $executed[] = self::abortedStatementSummary($sql, $ordinal, $before, $rowIdColumn, $exception->getMessage());

                return [$current, $executed, $exception->getMessage(), $ordinal];
            }

            $current = $result['tables'];
            $executed[] = self::statementSummary('abort-attempt-before-conflict-next200', $ordinal, $sql, $result, $before, $rowIdColumn, null);
        }

        return [$current, $executed, null, null];
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>,failed_conflict?:?array<string,mixed>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn, ?string $failedMessage): array
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
            'failed_conflict' => $failedMessage === null ? ($result['failed_conflict'] ?? null) : ['message' => $failedMessage],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function abortedStatementSummary(string $sql, int $ordinal, array $before, string $rowIdColumn, string $message): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        $table = $parsed['table'];
        $where = self::wherePredicate($parsed['where']);
        if ($parsed['action'] === 'delete') {
            $plan = SQLiteUpdateDeleteLimitPlan::delete($before[$table] ?? [], $where, $parsed['order_by'], $parsed['limit'], $parsed['offset'], $rowIdColumn);
        } else {
            $plan = SQLiteUpdateDeleteLimitPlan::update(
                $before[$table] ?? [],
                $where,
                self::assignmentCallbacks($parsed['assignments']),
                $parsed['order_by'],
                $parsed['limit'],
                $parsed['offset'],
                $rowIdColumn,
            );
        }

        return [
            'phase' => 'abort-conflict-suppressed-next200',
            'ordinal' => $ordinal,
            'sql' => $sql,
            'action' => $parsed['action'],
            'conflict_action' => $parsed['conflict_action'],
            'table' => $table,
            'selected_ids' => $plan->selectedIds,
            'mutation_ids' => $plan->mutationIds,
            'source_rows' => self::rowsByIds($before[$table] ?? [], $plan->selectedIds, $rowIdColumn),
            'returning_rows' => [],
            'ignored_rows' => [],
            'deleted_conflict_rows' => [],
            'conflicts' => [],
            'failed_conflict' => ['message' => $message],
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
                throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ABORT savepoint next200 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next200 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next200 rowid column {$rowIdColumn} must be int or string");
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
            throw new \InvalidArgumentException("SQLite row-value ABORT savepoint next200 {$label} must be an identifier");
        }
    }

    /**
     * @return callable(array<string,mixed>):bool
     */
    private static function wherePredicate(?string $where): callable
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'wherePredicate');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $where);
    }

    /**
     * @param array<string,string> $assignments
     * @return array<string,callable(array<string,mixed>):mixed>
     */
    private static function assignmentCallbacks(array $assignments): array
    {
        $reflection = new \ReflectionMethod(SQLiteUpdateDeleteReturningSql::class, 'assignmentCallbacks');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $assignments);
    }
}
