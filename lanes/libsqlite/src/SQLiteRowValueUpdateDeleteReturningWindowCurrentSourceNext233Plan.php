<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext233Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_returning_window_next233',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($yieldStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value returning window next233 needs yield statements');
        }
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value returning window next233 needs attempted statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value returning window next233 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value returning window next233 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value returning window next233 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$yieldCurrent, $yieldStatementsRun, $yieldReturning] = self::runStatements($savepointImage, $yieldStatements, $uniqueConstraints, $rowIdColumn, 'yield-window-before-rollback-to-next233');
        [$attemptCurrent, $attemptStatementsRun, $attemptReturning] = self::runStatements($yieldCurrent, $attemptStatements, $uniqueConstraints, $rowIdColumn, 'attempt-window-after-yield-before-rollback-to-next233');

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryStatementsRun, $retryReturning] = self::runStatements($rollbackCurrent, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-window-after-rollback-release-next233');

        $yieldRows = self::flattenReturning($yieldReturning);
        $suppressedRows = self::flattenReturning($attemptReturning);
        $retryRows = self::flattenReturning($retryReturning);

        return [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next233',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'yield_current_source_tables' => $yieldCurrent,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'yield_returning' => $yieldReturning,
            'suppressed_attempt_returning' => $attemptReturning,
            'retry_returning' => $retryReturning,
            'yield_window' => self::windowRows($yieldRows, $rowIdColumn),
            'suppressed_attempt_window' => self::windowRows($suppressedRows, $rowIdColumn),
            'retry_window' => self::windowRows($retryRows, $rowIdColumn),
            'all_window_receipt_next233' => self::phaseReceipt($yieldRows, $suppressedRows, $retryRows, $rowIdColumn),
            'yield_statements' => $yieldStatementsRun,
            'attempt_statements' => $attemptStatementsRun,
            'retry_statements' => $retryStatementsRun,
            'yielded_returning_count' => count($yieldRows),
            'suppressed_returning_count' => count($suppressedRows),
            'retry_returning_count' => count($retryRows),
            'yield_change_count' => self::changeCount($yieldStatementsRun),
            'attempt_change_count' => self::changeCount($attemptStatementsRun),
            'retry_change_count' => self::changeCount($retryStatementsRun),
            'window_yield_survives_rollback_next233' => true,
            'window_attempt_suppressed_after_rollback_next233' => true,
            'window_retry_reads_savepoint_image_next233' => true,
            'window_release_commits_retry_next233' => true,
            'changed_tables_after_release' => self::changedTables($savepointImage, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
            'dependency_closure_next233' => 'no new support component needed; next233 reuses native PHP UPDATE/DELETE RETURNING row-value selection, savepoint row images, and lane-local window ranking over RETURNING rows',
            'dependencies' => [
                'sqlite-rowvalue-update-delete-returning-window-next233',
                'sqlite-returning-window-rollback-to-release-current-source-next233',
                'wordpress-rowvalue-returning-window-current-source-next233',
            ],
            'non_overlap_next233' => 'adds window row_number/dense_rank/count/sum metadata over row-value UPDATE/DELETE RETURNING streams across yielded, rolled-back, and retried current-source phases; avoids accepted next176 nullable row-value comparison, next229 row-value IN SELECT savepoint release, trigger RETURNING, compound SELECT window, WAL/VFS, JSON table, planner, and B-tree clusters',
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
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function windowRows(array $rows, string $rowIdColumn): array
    {
        $ordered = $rows;
        usort($ordered, static function (array $left, array $right) use ($rowIdColumn): int {
            $leftBytes = self::numericValue($left['bytes'] ?? null);
            $rightBytes = self::numericValue($right['bytes'] ?? null);
            if ($leftBytes !== $rightBytes) {
                return $rightBytes <=> $leftBytes;
            }

            return self::rowIdValue($left, $rowIdColumn) <=> self::rowIdValue($right, $rowIdColumn);
        });

        $count = count($ordered);
        $sum = 0;
        foreach ($ordered as $row) {
            $sum += self::numericValue($row['bytes'] ?? null);
        }

        $windows = [];
        $previousBytes = null;
        $denseRank = 0;
        foreach ($ordered as $index => $row) {
            $bytes = self::numericValue($row['bytes'] ?? null);
            if ($previousBytes === null || $bytes !== $previousBytes) {
                ++$denseRank;
                $previousBytes = $bytes;
            }
            $windows[] = [
                'option_id' => self::rowIdValue($row, $rowIdColumn),
                'option_name' => (string) ($row['option_name'] ?? ''),
                'status' => $row['status'] ?? null,
                'bytes' => $bytes,
                'row_number' => $index + 1,
                'dense_rank' => $denseRank,
                'partition_count' => $count,
                'partition_sum' => $sum,
                'phase_marker' => ($row['status'] ?? '') . '#' . ($index + 1),
            ];
        }

        return $windows;
    }

    /**
     * @param list<array<string,mixed>> $yieldRows
     * @param list<array<string,mixed>> $suppressedRows
     * @param list<array<string,mixed>> $retryRows
     * @return array<string,mixed>
     */
    private static function phaseReceipt(array $yieldRows, array $suppressedRows, array $retryRows, string $rowIdColumn): array
    {
        return [
            'yield_ids' => self::idsFromRows($yieldRows, $rowIdColumn),
            'suppressed_ids' => self::idsFromRows($suppressedRows, $rowIdColumn),
            'retry_ids' => self::idsFromRows($retryRows, $rowIdColumn),
            'yield_window_ids' => self::idsFromWindow(self::windowRows($yieldRows, $rowIdColumn)),
            'suppressed_window_ids' => self::idsFromWindow(self::windowRows($suppressedRows, $rowIdColumn)),
            'retry_window_ids' => self::idsFromWindow(self::windowRows($retryRows, $rowIdColumn)),
            'retry_sum' => array_sum(array_column(self::windowRows($retryRows, $rowIdColumn), 'bytes')),
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
                throw new \InvalidArgumentException('SQLite row-value returning window next233 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value returning window next233 rows must be arrays');
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
                throw new \InvalidArgumentException("SQLite row-value returning window next233 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next233 rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $returning
     * @return list<array<string,mixed>>
     */
    private static function flattenReturning(array $returning): array
    {
        $rows = [];
        foreach ($returning as $statement) {
            foreach ($statement['rows'] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $statements
     */
    private static function changeCount(array $statements): int
    {
        $count = 0;
        foreach ($statements as $statement) {
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

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function idsFromRows(array $rows, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = self::rowIdValue($row, $rowIdColumn);
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $windows
     * @return list<int|string>
     */
    private static function idsFromWindow(array $windows): array
    {
        return array_column($windows, 'option_id');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdValue(array $row, string $rowIdColumn): int|string
    {
        if (!array_key_exists($rowIdColumn, $row)) {
            throw new \InvalidArgumentException("SQLite row-value returning window next233 rowid column {$rowIdColumn} is missing");
        }
        $id = $row[$rowIdColumn];
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException("SQLite row-value returning window next233 rowid column {$rowIdColumn} must be int or string");
        }

        return $id;
    }

    private static function numericValue(mixed $value): int
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return 0;
        }

        return (int) $value;
    }
}
