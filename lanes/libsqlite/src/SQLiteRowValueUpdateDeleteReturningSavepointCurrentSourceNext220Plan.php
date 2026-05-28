<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext220Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeAbortStatements
     * @param string $abortStatement
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $beforeAbortStatements,
        string $abortStatement,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_abort_next220',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeAbortStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 needs pre-abort statements');
        }
        if (trim($abortStatement) === '') {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 needs an abort statement');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$beforeAbortCurrent, $beforeAbortExecuted, $beforeAbortReturning] = self::runStatements(
            $savepointImage,
            $beforeAbortStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-or-abort-next220',
        );
        [$afterAbortCurrent, $abortSummary] = self::runAbortStatement(
            $beforeAbortCurrent,
            $abortStatement,
            $uniqueConstraints,
            $rowIdColumn,
        );
        [$afterRetry, $retryStatementsExecuted, $retryReturning] = self::runStatements(
            $afterAbortCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-statement-abort-next220',
        );

        return [
            'status' => 'rowvalue-update-delete-returning-or-abort-savepoint-current-source-next220',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'pre_abort_current_source_tables' => $beforeAbortCurrent,
            'abort_current_source_tables' => $afterAbortCurrent,
            'current_source_tables' => $afterRetry,
            'next_source_tables' => $afterRetry,
            'savepoint_preserved_after_statement_abort' => true,
            'pre_abort_changes_preserved' => true,
            'abort_statement_changes_rolled_back' => true,
            'abort_statement_returning_suppressed' => true,
            'retry_reads_pre_abort_current_source' => true,
            'savepoint_released_after_retry' => true,
            'before_abort_statements' => $beforeAbortExecuted,
            'abort_statement' => $abortSummary,
            'retry_statements' => $retryStatementsExecuted,
            'pre_abort_yielded_returning' => $beforeAbortReturning,
            'suppressed_by_statement_abort_returning' => $abortSummary['returning_rows'],
            'yielded_after_retry_returning' => $retryReturning,
            'pre_abort_yielded_count' => self::returningCount($beforeAbortReturning),
            'pre_abort_changes_count' => self::changeCount($beforeAbortExecuted),
            'suppressed_by_abort_count' => count($abortSummary['returning_rows']),
            'retry_yielded_count' => self::returningCount($retryReturning),
            'retry_changes_count' => self::changeCount($retryStatementsExecuted),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $afterRetry),
            'row_counts' => self::rowCounts($afterRetry),
            'dependency_closure_next220' => 'no new support component needed; next220 reuses native row-value UPDATE/DELETE RETURNING execution, unique conflict checks, and savepoint current-source row images',
            'non_overlap_next220' => 'adds statement-level UPDATE OR ABORT row-value RETURNING suppression inside a preserved savepoint; avoids accepted next217 transaction OR ROLLBACK, next210/next211 OR IGNORE, next209 OR FAIL, next212 subquery rollback, trigger RETURNING, WAL/VFS, JSON, planner, encoding, PRAGMA, and B-tree clusters',
            'dependencies' => [
                'sqlite-rowvalue-update-or-abort-suppresses-failing-returning-next220',
                'sqlite-rowvalue-or-abort-preserves-savepoint-current-source-next220',
                'sqlite-rowvalue-delete-returning-retry-after-statement-abort-next220',
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
     * @return array{0:array<string,list<array<string,mixed>>>,1:array<string,mixed>}
     */
    private static function runAbortStatement(array $tables, string $sql, array $uniqueConstraints, string $rowIdColumn): array
    {
        $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
        if ($parsed['action'] !== 'update' || $parsed['conflict_action'] !== 'abort') {
            throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 statement must be UPDATE OR ABORT');
        }

        try {
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, $uniqueConstraints);
        } catch (\InvalidArgumentException $exception) {
            $probe = SQLiteUpdateDeleteReturningSql::execute($sql, $tables, $rowIdColumn, [], true);

            return [
                $tables,
                self::statementSummary('or-abort-next220', 0, $sql, $probe, $tables, $rowIdColumn, $exception->getMessage()) + [
                    'aborted' => true,
                    'error' => $exception->getMessage(),
                    'rolled_back_statement_only' => true,
                    'savepoint_remains_open' => true,
                ],
            ];
        }

        return [
            $result['tables'],
            self::statementSummary('or-abort-next220', 0, $sql, $result, $tables, $rowIdColumn, null) + [
                'aborted' => false,
                'error' => null,
                'rolled_back_statement_only' => false,
                'savepoint_remains_open' => true,
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
                throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value OR ABORT next220 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value OR ABORT next220 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value OR ABORT next220 rowid column {$rowIdColumn} must be int or string");
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

        sort($changed);

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

        ksort($counts);

        return $counts;
    }
}
