<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext290293Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_source_next290293',
        string $rowIdColumn = 'option_id',
    ): array {
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value window current-source next290293 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value window current-source next290293 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value window current-source next290293 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value window current-source next290293 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeTables($tables);
        [$attemptCurrent, $attemptSummaries, $attemptReturning] = self::runStatements(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-window-rollback-next290293',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retrySummaries, $retryReturning] = self::runStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-window-rollback-next290293',
        );

        $attemptWindow = self::windowRows(self::flattenReturning($attemptReturning), $rowIdColumn, 'attempt-all-next290293');
        $retryWindow = self::windowRows(self::flattenReturning($retryReturning), $rowIdColumn, 'retry-all-next290293');
        $retryStatementWindows = self::statementWindowRows($retryReturning, $rowIdColumn);

        return [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next290293',
            'savepoint' => $savepoint,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attemptCurrent,
            'rollback_current_source_tables' => $rollbackCurrent,
            'current_source_tables' => $retryCurrent,
            'next_source_tables' => $retryCurrent,
            'rolled_back_to_savepoint' => true,
            'savepoint_preserved_after_rollback_to' => true,
            'attempt_returning_window_suppressed_by_rollback' => true,
            'retry_returning_window_yielded_from_current_source' => true,
            'window_order_columns_next290293' => [$rowIdColumn],
            'attempt_statements' => $attemptSummaries,
            'retry_statements' => $retrySummaries,
            'discarded_attempt_returning' => $attemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'discarded_attempt_window_rows' => $attemptWindow,
            'yielded_retry_window_rows' => $retryWindow,
            'yielded_retry_statement_window_rows' => $retryStatementWindows,
            'discarded_attempt_returning_count' => self::returningCount($attemptReturning),
            'yielded_after_retry_count' => self::returningCount($retryReturning),
            'yielded_retry_statement_window_count' => count($retryStatementWindows),
            'attempt_changes_before_rollback' => self::changeCount($attemptSummaries),
            'retry_changes_after_rollback' => self::changeCount($retrySummaries),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retryCurrent),
            'row_counts' => self::rowCounts($retryCurrent),
            'dependency_closure_next290293' => 'no new support component needed; next290-293 reuses native row-value UPDATE/DELETE RETURNING execution and adds statement-partitioned current-source RETURNING window receipts after savepoint retry',
            'non_overlap_next290293' => 'adds statement-partitioned row_number/lag/lead receipts over UPDATE/DELETE RETURNING rows after rollback and retry; avoids accepted next219 negative LIMIT/OFFSET, next224/230 nested savepoints, next231 compound tuple sources, next289 all-stream windows, JSON table, WAL/VFS, planner, trigger, and B-tree clusters',
            'dependencies' => [
                'sqlite-rowvalue-update-returning-window-current-source-next290293',
                'sqlite-rowvalue-delete-returning-window-current-source-next290293',
                'sqlite-rowvalue-returning-window-savepoint-retry-current-source-next290293',
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
                throw new \InvalidArgumentException('SQLite row-value window current-source next290293 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value window current-source next290293 rows must be arrays');
                }
            }
        }

        return $tables;
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
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summaries[] = self::statementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
            $yielded[] = [
                'phase' => $phase,
                'ordinal' => $ordinal,
                'action' => $result['action'],
                'conflict_action' => $result['conflict_action'],
                'rows' => $result['returning'],
            ];
        }

        return [$current, $summaries, $yielded];
    }

    /**
     * @param array<string,mixed> $result
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
                throw new \InvalidArgumentException("SQLite row-value window current-source next290293 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value window current-source next290293 rowid column {$rowIdColumn} must be int or string");
            }
            if (isset($wanted[(string) $id])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     * @return list<array<string,mixed>>
     */
    private static function flattenReturning(array $yielded): array
    {
        $rows = [];
        foreach ($yielded as $stream) {
            foreach ($stream['rows'] as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function windowRows(array $rows, string $rowIdColumn, string $source): array
    {
        usort($rows, static fn (array $left, array $right): int => ($left[$rowIdColumn] ?? 0) <=> ($right[$rowIdColumn] ?? 0));
        $windowRows = [];
        $count = count($rows);

        foreach ($rows as $index => $row) {
            $windowRows[] = [
                'row_number' => $index + 1,
                'current_rowid' => $row[$rowIdColumn] ?? null,
                'previous_rowid' => $rows[$index - 1][$rowIdColumn] ?? null,
                'next_rowid' => $rows[$index + 1][$rowIdColumn] ?? null,
                'peer_count' => $count,
                'status' => $row['status'] ?? null,
                'option_name' => $row['option_name'] ?? null,
                'source' => $source,
            ];
        }

        return $windowRows;
    }

    /**
     * @param list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}> $yielded
     * @return list<array<string,mixed>>
     */
    private static function statementWindowRows(array $yielded, string $rowIdColumn): array
    {
        $windows = [];
        foreach ($yielded as $stream) {
            $rows = $stream['rows'];
            usort($rows, static fn (array $left, array $right): int => ($left[$rowIdColumn] ?? 0) <=> ($right[$rowIdColumn] ?? 0));
            $count = count($rows);

            foreach ($rows as $index => $row) {
                $windows[] = [
                    'statement_ordinal' => $stream['ordinal'],
                    'action' => $stream['action'],
                    'conflict_action' => $stream['conflict_action'],
                    'row_number_in_statement' => $index + 1,
                    'current_rowid' => $row[$rowIdColumn] ?? null,
                    'previous_rowid_in_statement' => $rows[$index - 1][$rowIdColumn] ?? null,
                    'next_rowid_in_statement' => $rows[$index + 1][$rowIdColumn] ?? null,
                    'statement_peer_count' => $count,
                    'status' => $row['status'] ?? null,
                    'option_name' => $row['option_name'] ?? null,
                    'source' => 'retry-statement-current-source-next290293',
                ];
            }
        }

        return $windows;
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
     * @param list<array<string,mixed>> $summaries
     */
    private static function changeCount(array $summaries): int
    {
        $count = 0;
        foreach ($summaries as $summary) {
            $count += count($summary['mutation_ids'] ?? []);
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
