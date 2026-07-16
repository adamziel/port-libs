<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueConflictSavepointReturningCurrentSourceNextPlan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $statements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_retry_batch',
        string $rowIdColumn = 'setting_id',
    ): array {
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite row-value conflict savepoint RETURNING needs statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value conflict savepoint RETURNING needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value conflict savepoint RETURNING needs unique constraints');
        }

        $savepointImage = self::normalizeTables($tables);
        $rowIdColumn = SQLiteRowIdColumn::resolveTables($savepointImage, $rowIdColumn, $uniqueConstraints);
        $current = $savepointImage;
        $executed = [];
        $yielded = [];
        $failed = null;

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            try {
                $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            } catch (\InvalidArgumentException $exception) {
                $failed = [
                    'ordinal' => $ordinal,
                    'sql' => $sql,
                    'reason' => $exception->getMessage(),
                    'statement_source_tables' => $before,
                    'attempted_current_source_tables' => $current,
                ];
                break;
            }

            $current = $result['tables'];
            $executed[] = self::statementSummary($ordinal, $result, $rowIdColumn, $before);
            $yielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        $rolledBack = $failed !== null;
        $rollbackImage = $rolledBack ? $savepointImage : $current;
        $retryCurrent = $rollbackImage;
        $retryExecuted = [];
        $retryYielded = [];

        foreach ($retryStatements as $ordinal => $sql) {
            $before = $retryCurrent;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $retryCurrent, $rowIdColumn, $uniqueConstraints);
            $retryCurrent = $result['tables'];
            $retryExecuted[] = self::statementSummary($ordinal, $result, $rowIdColumn, $before);
            $retryYielded[] = [
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [
            'savepoint' => $savepoint,
            'status' => $rolledBack ? 'rolled-back-to-savepoint-then-retried' : 'released-without-conflict',
            'rolled_back_to_savepoint' => $rolledBack,
            'failed_statement' => $failed,
            'savepoint_image_tables' => $savepointImage,
            'pre_rollback_current_source_tables' => $current,
            'rollback_current_source_tables' => $rollbackImage,
            'post_retry_current_source_tables' => $retryCurrent,
            'executed_statements' => $executed,
            'yielded_returning_before_rollback' => $yielded,
            'yielded_returning_after_rollback' => $retryYielded,
            'retry_statements' => $retryExecuted,
            'discarded_returning_count' => self::returningCount($yielded),
            'retry_returning_count' => self::returningCount($retryYielded),
            'changes_before_rollback' => self::changeCount($executed),
            'changes_after_retry' => self::changeCount($retryExecuted),
            'row_counts' => self::rowCounts($retryCurrent),
            'dependencies' => [
                'sqlite-rollback-to-savepoint-restores-current-source',
                'sqlite-row-value-returning-yields-before-savepoint-rollback',
                'sqlite-row-value-conflict-retry-after-rollback-to-savepoint',
            ],
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
                throw new \InvalidArgumentException('SQLite row-value conflict savepoint RETURNING tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value conflict savepoint RETURNING rows must be arrays');
                }
            }
        }

        return $tables;
    }

    /**
     * @param array{action:string,table:string,conflict_action:string,plan:SQLiteUpdateDeleteLimitPlan,tables:array<string,list<array<string,mixed>>>,returning:list<array<string,mixed>>,ignored_rows:list<array<string,mixed>>,deleted_conflict_rows:list<array<string,mixed>>,conflicts:list<array<string,mixed>>} $result
     * @param array<string,list<array<string,mixed>>> $before
     * @return array<string,mixed>
     */
    private static function statementSummary(int $ordinal, array $result, string $rowIdColumn, array $before): array
    {
        return [
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
                throw new \InvalidArgumentException("SQLite row-value conflict savepoint RETURNING rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value conflict savepoint RETURNING rowid column {$rowIdColumn} must be int or string");
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
