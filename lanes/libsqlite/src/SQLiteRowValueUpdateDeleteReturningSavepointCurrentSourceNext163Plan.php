<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext163Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $beforeRollbackStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_rowvalue_between_retry',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 needs pre-rollback statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 needs unique constraints');
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint)) {
            throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 savepoint name must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$attemptedTables, $attemptedStatements, $discardedReturning] = self::runStatements(
            $savepointImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-rollback-to',
        );

        $rollbackToTables = $savepointImage;
        [$currentTables, $retryExecuted, $yieldedReturning] = self::runStatements(
            $rollbackToTables,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-rollback-to',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'released-after-rowvalue-between-retry',
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempted_before_rollback_tables' => $attemptedTables,
            'rollback_to_current_source_tables' => $rollbackToTables,
            'current_source_tables' => $currentTables,
            'next_source_tables' => $currentTables,
            'pre_rollback_statements' => $attemptedStatements,
            'retry_statements' => $retryExecuted,
            'discarded_returning' => $discardedReturning,
            'yielded_returning' => $yieldedReturning,
            'discarded_returning_count' => self::returningCount($discardedReturning),
            'yielded_returning_count' => self::returningCount($yieldedReturning),
            'discarded_changes_before_rollback_to' => self::changeCount($attemptedStatements),
            'changes_after_release' => self::changeCount($retryExecuted),
            'row_counts' => self::rowCounts($currentTables),
            'dependencies' => [
                'sqlite-row-value-between-returning-expression',
                'sqlite-update-delete-returning-rollback-to-discards-current-stream',
                'sqlite-retry-after-rollback-to-reads-restored-current-source',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{0:array<string,list<array<string,mixed>>>,1:list<array<string,mixed>>,2:list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}>}
     */
    private static function runStatements(
        array $tables,
        array $statements,
        array $uniqueConstraints,
        string $rowIdColumn,
        string $phase,
    ): array {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummary($phase, $ordinal, $result, $before, $rowIdColumn);
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
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummary(string $phase, int $ordinal, array $result, array $before, string $rowIdColumn): array
    {
        return [
            'phase' => $phase,
            'ordinal' => $ordinal,
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
                throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value BETWEEN savepoint next163 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value BETWEEN savepoint next163 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value BETWEEN savepoint next163 rowid column {$rowIdColumn} must be int or string");
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
}
