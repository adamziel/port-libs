<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext158Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $beforeRollbackStatements
     * @param list<string> $afterRollbackStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $beforeRollbackStatements,
        array $afterRollbackStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_retry_batch',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($beforeRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next158 needs pre-rollback statements');
        }
        if ($afterRollbackStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next158 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next158 needs unique constraints');
        }

        $savepointImage = self::normalizeTables($tables);
        [$attemptedBeforeRollback, $preRollbackExecuted, $preRollbackYielded] = self::runStatements(
            $savepointImage,
            $beforeRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'before-rollback',
        );

        $rollbackToImage = $savepointImage;
        [$retryCurrent, $retryExecuted, $retryYielded] = self::runStatements(
            $rollbackToImage,
            $afterRollbackStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'after-rollback',
        );

        return [
            'savepoint' => $savepoint,
            'status' => 'released-after-rollback-to-retry',
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'released' => true,
            'savepoint_image_tables' => $savepointImage,
            'attempted_before_rollback_tables' => $attemptedBeforeRollback,
            'rollback_to_current_source_tables' => $rollbackToImage,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'pre_rollback_statements' => $preRollbackExecuted,
            'retry_statements' => $retryExecuted,
            'discarded_returning' => $preRollbackYielded,
            'yielded_returning' => $retryYielded,
            'discarded_returning_count' => self::returningCount($preRollbackYielded),
            'changes_after_release' => self::changeCount($retryExecuted),
            'discarded_changes_before_rollback_to' => self::changeCount($preRollbackExecuted),
            'row_counts' => self::rowCounts($retryCurrent),
            'dependencies' => [
                'sqlite-rollback-to-savepoint-keeps-savepoint-active',
                'sqlite-row-value-update-delete-returning-discarded-on-rollback-to',
                'sqlite-retry-statements-read-restored-current-source',
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
                throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next158 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value UPDATE/DELETE RETURNING savepoint next158 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING savepoint next158 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value UPDATE/DELETE RETURNING savepoint next158 rowid column {$rowIdColumn} must be int or string");
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
