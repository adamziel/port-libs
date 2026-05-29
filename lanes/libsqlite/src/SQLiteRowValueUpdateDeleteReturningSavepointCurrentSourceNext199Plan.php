<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext199Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        string $savepoint = 'wp_options_rowvalue_order_expr_next199',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ORDER BY expression next199 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value ORDER BY expression next199 needs retry statements');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value ORDER BY expression next199 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$attemptedTables, $attemptedStatements, $attemptedReturning] = self::runStatements(
            $savepointImage,
            $attemptStatements,
            $rowIdColumn,
            'attempt-order-expression-before-rollback-next199',
        );
        [$retryTables, $retryStatementsSummary, $retryReturning] = self::runStatements(
            $savepointImage,
            $retryStatements,
            $rowIdColumn,
            'retry-order-expression-after-rollback-next199',
        );

        return [
            'status' => 'rowvalue-order-expression-returning-rolled-back-retried-next199',
            'savepoint' => $savepoint,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released_after_retry' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptedTables,
            'rollback_to_current_source_tables' => $savepointImage,
            'current_source_tables' => $retryTables,
            'next_source_tables' => $retryTables,
            'attempt_statements' => $attemptedStatements,
            'retry_statements' => $retryStatementsSummary,
            'attempt_returning' => $attemptedReturning,
            'suppressed_by_rollback_returning' => $attemptedReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'attempt_returning_count' => self::returningCount($attemptedReturning),
            'suppressed_by_rollback_count' => self::returningCount($attemptedReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'attempt_changes_before_rollback_to' => self::changeCount($attemptedStatements),
            'changes_after_retry_release' => self::changeCount($retryStatementsSummary),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retryTables),
            'row_counts' => self::rowCounts($retryTables),
            'dependencies' => [
                'sqlite-update-delete-order-by-rowvalue-expression-next199',
                'sqlite-rowvalue-order-expression-limit-before-source-mutation-next199',
                'sqlite-rowvalue-order-expression-returning-rollback-current-source-next199',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatements(array $tables, array $statements, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn);
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
            'order_by' => $result['plan']->toArray()['order_by'],
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
                throw new \InvalidArgumentException('SQLite row-value ORDER BY expression next199 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value ORDER BY expression next199 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value ORDER BY expression next199 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value ORDER BY expression next199 rowid column {$rowIdColumn} must be int or string");
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
