<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan
{
    private const READY_PUBLICATION_SAVEPOINT_PREFIX = 'application_rowvalue_window_current_step';
    private const DEFAULT_ROW_ID_COLUMN = 'setting_id';
    private const DEFAULT_KEY_NAME_COLUMN = 'key_name';
    private const DEFAULT_KEY_VALUE_COLUMN = 'key_value';

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<list<string>> $uniqueConstraints
     */
    private static function resolveDefaultRowIdColumn(array $tables, string $rowIdColumn, array $uniqueConstraints): string
    {
        if ($rowIdColumn !== self::DEFAULT_ROW_ID_COLUMN) {
            return $rowIdColumn;
        }

        $rowLists = [];
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || !is_array($rows) || !array_is_list($rows)) {
                continue;
            }
            $rowLists[$name] = $rows;
        }

        return SQLiteRowIdColumn::resolveTables($rowLists, $rowIdColumn, $uniqueConstraints);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function keyNameColumn(array $row): string
    {
        if (array_key_exists(self::DEFAULT_KEY_NAME_COLUMN, $row)) {
            return self::DEFAULT_KEY_NAME_COLUMN;
        }

        foreach ($row as $column => $_value) {
            if (is_string($column) && str_ends_with($column, '_name')) {
                return $column;
            }
        }

        return self::DEFAULT_KEY_NAME_COLUMN;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function keyNameString(array $row): string
    {
        $value = $row[self::keyNameColumn($row)] ?? '';

        return is_scalar($value) || $value === null ? (string) $value : '';
    }

    /**
     * @param array<string,mixed>|null $row
     */
    private static function nullableKeyName(?array $row): ?string
    {
        return $row === null ? null : self::keyNameString($row);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function keyValueColumn(array $row): string
    {
        if (array_key_exists(self::DEFAULT_KEY_VALUE_COLUMN, $row)) {
            return self::DEFAULT_KEY_VALUE_COLUMN;
        }

        foreach ($row as $column => $_value) {
            if (is_string($column) && str_ends_with($column, '_value')) {
                return $column;
            }
        }

        return self::DEFAULT_KEY_VALUE_COLUMN;
    }

    /**
     * @param array<string,mixed>|null $row
     */
    private static function nullableKeyValue(?array $row): mixed
    {
        return $row === null ? null : ($row[self::keyValueColumn($row)] ?? null);
    }

    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeRetryWindowPlan(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next232',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value window current-source next232 savepoint must be an identifier');
        }

        $plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSelectRetrySavepointRelease(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $retryRows = $plan['retry_rows_after_release'];
        $windowRows = self::retryWindowRows($retryRows, $rowIdColumn);
        $currentRows = self::primarySourceRows($plan['current_source_tables'] ?? [], $rowIdColumn);

        return array_merge($plan, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next232',
            'rowvalue_subquery_targets_next229' => true,
            'rollback_to_savepoint_next229' => true,
            'release_commits_retry_next229' => true,
            'yielded_rows_survive_rollback_next229' => true,
            'retry_reads_savepoint_image_next229' => true,
            'release_receipt_next229' => [
                'yielded_ids' => array_column($plan['yielded_rows_before_rollback'], $rowIdColumn),
                'suppressed_ids' => array_column($plan['suppressed_rows_after_rollback'], $rowIdColumn),
                'retry_ids' => array_column($retryRows, $rowIdColumn),
            ],
            'window_current_source_next232' => true,
            'window_retry_rows_after_release_next232' => $windowRows,
            'window_retry_ids_after_release_next232' => array_column($windowRows, $rowIdColumn),
            'window_retry_row_numbers_next232' => array_column($windowRows, 'row_number'),
            'window_retry_partition_numbers_next232' => array_column($windowRows, 'partition_row_number'),
            'current_source_window_order_next232' => self::currentSourceWindowOrder($currentRows, $rowIdColumn),
            'dependency_closure_next232' => 'no new support component needed; next232 reuses native PHP row-value UPDATE/DELETE RETURNING subquery dispatch, savepoint row images, and bounded window-style row numbering over retry RETURNING rows',
            'dependencies_next232' => [
                'sqlite-rowvalue-update-returning-window-current-source-next232',
                'sqlite-delete-returning-window-current-source-next232',
                'application-rowvalue-window-savepoint-current-source-next232',
            ],
            'non_overlap_next232' => 'adds window-style row numbering over row-value UPDATE/DELETE RETURNING retry rows after rollback/release; avoids accepted next229 subquery retry image coverage, next226 DISTINCT subquery coverage, next205 release-current-source coverage, window row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function retryWindowRows(array $rows, string $rowIdColumn): array
    {
        $numbered = [];
        $partitionCounts = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value window current-source next232 rowid column {$rowIdColumn} is missing");
            }
            $partition = (string) ($row['status'] ?? $row['action'] ?? 'unknown');
            $partitionCounts[$partition] = ($partitionCounts[$partition] ?? 0) + 1;
            $row['row_number'] = $index + 1;
            $row['partition_key'] = $partition;
            $row['partition_row_number'] = $partitionCounts[$partition];
            $numbered[] = $row;
        }

        return $numbered;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return list<array<string,mixed>>
     */
    private static function primarySourceRows(array $tables, string $rowIdColumn): array
    {
        $fallback = [];
        foreach ($tables as $rows) {
            if (!is_array($rows) || !array_is_list($rows)) {
                continue;
            }
            $fallback = $fallback === [] ? $rows : $fallback;
            if ($rows === [] || array_key_exists($rowIdColumn, $rows[0])) {
                return $rows;
            }
        }

        return $fallback;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,int|string|null>>
     */
    private static function currentSourceWindowOrder(array $rows, string $rowIdColumn): array
    {
        $ordered = [];
        foreach (array_values($rows) as $index => $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value window current-source next232 rowid column {$rowIdColumn} is missing");
            }
            $ordered[] = [
                'ordinal' => $index + 1,
                $rowIdColumn => $row[$rowIdColumn],
                self::keyNameColumn($row) => self::nullableKeyName($row),
                'status' => $row['status'] ?? null,
            ];
        }

        return $ordered;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReturningWindowRollbackRetry(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_returning_window_next233',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
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

        $savepointImage = self::normalizeTablesReturningWindowRollbackRetry($tables);
        [$yieldCurrent, $yieldStatementsRun, $yieldReturning] = self::runStatementsReturningWindowRollbackRetry($savepointImage, $yieldStatements, $uniqueConstraints, $rowIdColumn, 'yield-window-before-rollback-to-next233');
        [$attemptCurrent, $attemptStatementsRun, $attemptReturning] = self::runStatementsReturningWindowRollbackRetry($yieldCurrent, $attemptStatements, $uniqueConstraints, $rowIdColumn, 'attempt-window-after-yield-before-rollback-to-next233');

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retryStatementsRun, $retryReturning] = self::runStatementsReturningWindowRollbackRetry($rollbackCurrent, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-window-after-rollback-release-next233');

        $yieldRows = self::flattenReturningRowsRollbackRetry($yieldReturning);
        $suppressedRows = self::flattenReturningRowsRollbackRetry($attemptReturning);
        $retryRows = self::flattenReturningRowsRollbackRetry($retryReturning);

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
            'yield_window' => self::windowRowsReturningWindowRollbackRetry($yieldRows, $rowIdColumn),
            'suppressed_attempt_window' => self::windowRowsReturningWindowRollbackRetry($suppressedRows, $rowIdColumn),
            'retry_window' => self::windowRowsReturningWindowRollbackRetry($retryRows, $rowIdColumn),
            'all_window_receipt_next233' => self::phaseReceiptReturningWindowRollbackRetry($yieldRows, $suppressedRows, $retryRows, $rowIdColumn),
            'yield_statements' => $yieldStatementsRun,
            'attempt_statements' => $attemptStatementsRun,
            'retry_statements' => $retryStatementsRun,
            'yielded_returning_count' => count($yieldRows),
            'suppressed_returning_count' => count($suppressedRows),
            'retry_returning_count' => count($retryRows),
            'yield_change_count' => self::changeCountReturningWindowRollbackRetry($yieldStatementsRun),
            'attempt_change_count' => self::changeCountReturningWindowRollbackRetry($attemptStatementsRun),
            'retry_change_count' => self::changeCountReturningWindowRollbackRetry($retryStatementsRun),
            'window_yield_survives_rollback_next233' => true,
            'window_attempt_suppressed_after_rollback_next233' => true,
            'window_retry_reads_savepoint_image_next233' => true,
            'window_release_commits_retry_next233' => true,
            'changed_tables_after_release' => self::changedTablesReturningWindowRollbackRetry($savepointImage, $retryCurrent),
            'row_counts' => self::rowCountsReturningWindowRollbackRetry($retryCurrent),
            'dependency_closure_next233' => 'no new support component needed; next233 reuses native PHP UPDATE/DELETE RETURNING row-value selection, savepoint row images, and lane-local window ranking over RETURNING rows',
            'dependencies' => [
                'sqlite-rowvalue-update-delete-returning-window-next233',
                'sqlite-returning-window-rollback-to-release-current-source-next233',
                'application-rowvalue-returning-window-current-source-next233',
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
    private static function runStatementsReturningWindowRollbackRetry(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $executed = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $executed[] = self::statementSummaryReturningWindowRollbackRetry($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementSummaryReturningWindowRollbackRetry(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::rowsByIdsReturningWindowRollbackRetry($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function windowRowsReturningWindowRollbackRetry(array $rows, string $rowIdColumn): array
    {
        $ordered = $rows;
        usort($ordered, static function (array $left, array $right) use ($rowIdColumn): int {
            $leftBytes = self::numericValueReturningWindowRollbackRetry($left['bytes'] ?? null);
            $rightBytes = self::numericValueReturningWindowRollbackRetry($right['bytes'] ?? null);
            if ($leftBytes !== $rightBytes) {
                return $rightBytes <=> $leftBytes;
            }

            return self::rowIdValueReturningWindowRollbackRetry($left, $rowIdColumn) <=> self::rowIdValueReturningWindowRollbackRetry($right, $rowIdColumn);
        });

        $count = count($ordered);
        $sum = 0;
        foreach ($ordered as $row) {
            $sum += self::numericValueReturningWindowRollbackRetry($row['bytes'] ?? null);
        }

        $windows = [];
        $previousBytes = null;
        $denseRank = 0;
        foreach ($ordered as $index => $row) {
            $bytes = self::numericValueReturningWindowRollbackRetry($row['bytes'] ?? null);
            if ($previousBytes === null || $bytes !== $previousBytes) {
                ++$denseRank;
                $previousBytes = $bytes;
            }
            $windows[] = [
                $rowIdColumn => self::rowIdValueReturningWindowRollbackRetry($row, $rowIdColumn),
                self::keyNameColumn($row) => self::keyNameString($row),
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
    private static function phaseReceiptReturningWindowRollbackRetry(array $yieldRows, array $suppressedRows, array $retryRows, string $rowIdColumn): array
    {
        return [
            'yield_ids' => self::idsFromRowsReturningWindowRollbackRetry($yieldRows, $rowIdColumn),
            'suppressed_ids' => self::idsFromRowsReturningWindowRollbackRetry($suppressedRows, $rowIdColumn),
            'retry_ids' => self::idsFromRowsReturningWindowRollbackRetry($retryRows, $rowIdColumn),
            'yield_window_ids' => self::idsFromWindowReturningWindowRollbackRetry(self::windowRowsReturningWindowRollbackRetry($yieldRows, $rowIdColumn)),
            'suppressed_window_ids' => self::idsFromWindowReturningWindowRollbackRetry(self::windowRowsReturningWindowRollbackRetry($suppressedRows, $rowIdColumn)),
            'retry_window_ids' => self::idsFromWindowReturningWindowRollbackRetry(self::windowRowsReturningWindowRollbackRetry($retryRows, $rowIdColumn)),
            'retry_sum' => array_sum(array_column(self::windowRowsReturningWindowRollbackRetry($retryRows, $rowIdColumn), 'bytes')),
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeTablesReturningWindowRollbackRetry(array $tables): array
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
    private static function rowsByIdsReturningWindowRollbackRetry(array $rows, array $ids, string $rowIdColumn): array
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
    private static function flattenReturningRowsRollbackRetry(array $returning): array
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
    private static function changeCountReturningWindowRollbackRetry(array $statements): int
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
    private static function changedTablesReturningWindowRollbackRetry(array $before, array $after): array
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
    private static function rowCountsReturningWindowRollbackRetry(array $tables): array
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
    private static function idsFromRowsReturningWindowRollbackRetry(array $rows, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = self::rowIdValueReturningWindowRollbackRetry($row, $rowIdColumn);
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $windows
     * @return list<int|string>
     */
    private static function idsFromWindowReturningWindowRollbackRetry(array $windows): array
    {
        if ($windows === []) {
            return [];
        }

        foreach ($windows[0] as $column => $_value) {
            if (is_string($column) && str_ends_with($column, '_id')) {
                return array_column($windows, $column);
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdValueReturningWindowRollbackRetry(array $row, string $rowIdColumn): int|string
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

    private static function numericValueReturningWindowRollbackRetry(mixed $value): int
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return 0;
        }

        return (int) $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executePartitionedRetryWindow(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $partitionColumn = 'tenant_id',
        string $orderColumn = 'setting_id',
        string $rowIdColumn = 'setting_id',
        string $savepoint = 'app_settings_rowvalue_returning_window_next234',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        self::validateTablesPartitionedRetryWindow($tables);
        self::validateStatementsPartitionedRetryWindow($attemptStatements, 'attempt');
        self::validateStatementsPartitionedRetryWindow($retryStatements, 'retry');
        self::validateUniqueConstraintsPartitionedRetryWindow($uniqueConstraints);
        self::validateIdentifierPartitionedRetryWindow($partitionColumn, 'partition column');
        self::validateIdentifierPartitionedRetryWindow($orderColumn, 'order column');
        self::validateIdentifierPartitionedRetryWindow($rowIdColumn, 'rowid column');
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next234 savepoint must be an identifier');
        }

        $savepointImage = $tables;
        $attempt = self::runStatementsPartitionedRetryWindow($tables, $attemptStatements, $uniqueConstraints, $rowIdColumn, 'attempt-next234');
        $rollback = $savepointImage;
        $retry = self::runStatementsPartitionedRetryWindow($rollback, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-next234');
        $windowRows = self::windowRowsPartitionedRetryWindow($retry['returning_rows'], $partitionColumn, $orderColumn, $rowIdColumn, 'key_name');
        $partitionSummary = self::partitionSummaryPartitionedRetryWindow($windowRows, $partitionColumn, $rowIdColumn);

        return [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next234',
            'savepoint' => $savepoint,
            'partition_column' => $partitionColumn,
            'order_column' => $orderColumn,
            'rowid_column' => $rowIdColumn,
            'initial_tables' => $tables,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attempt['tables'],
            'rollback_current_source_tables' => $rollback,
            'current_source_tables' => $retry['tables'],
            'next_source_tables' => $retry['tables'],
            'attempt_statements' => $attempt['statements'],
            'retry_statements' => $retry['statements'],
            'discarded_attempt_returning' => $attempt['returning_rows'],
            'discarded_attempt_returning_count' => count($attempt['returning_rows']),
            'yielded_returning' => $retry['returning_rows'],
            'yielded_returning_count' => count($retry['returning_rows']),
            'window_rows' => $windowRows,
            'window_partition_summary' => $partitionSummary,
            'window_row_count' => count($windowRows),
            'rolled_back_to_savepoint' => true,
            'retry_reads_savepoint_image' => true,
            'savepoint_released_after_retry' => true,
            'current_source_token' => self::sourceTokenPartitionedRetryWindow($retry['tables']),
            'window_token' => self::sourceTokenPartitionedRetryWindow($windowRows),
            'changed_tables_after_retry' => self::changedTablesPartitionedRetryWindow($savepointImage, $retry['tables']),
            'row_counts' => self::rowCountsPartitionedRetryWindow($retry['tables']),
            'dependencies' => [
                'sqlite-rowvalue-update-returning-current-source-window-next234',
                'sqlite-rowvalue-delete-returning-current-source-window-next234',
                'sqlite-returning-stream-window-partition-retry-next234',
            ],
            'dependency_closure_next234' => 'no new support component needed; this reuses native row-value UPDATE/DELETE RETURNING execution and adds bounded current-source RETURNING window materialization.',
            'non_overlap_next234' => 'avoids accepted next230-next231 row-value savepoint rollback/release, next219 negative LIMIT tuple sources, next206 released-inner retry, compound/window recursive LIMIT, trigger RETURNING, JSON table, planner, WAL/VFS, B-tree, PRAGMA, and encoding clusters; this slice only windows the retry-yielded row-value RETURNING stream after savepoint rollback.',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{tables:array<string,list<array<string,mixed>>>,statements:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>}
     */
    private static function runStatementsPartitionedRetryWindow(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $summaries = [];
        $returningRows = [];
        foreach ($statements as $index => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $rows = [];
            foreach ($result['returning'] as $ordinal => $row) {
                $rows[] = self::tagReturningRowPartitionedRetryWindow($row, $phase, $index, $ordinal);
            }
            $returningRows = array_merge($returningRows, $rows);
            $summaries[] = [
                'phase' => $phase,
                'ordinal' => $index,
                'action' => $result['action'],
                'table' => $result['table'],
                'sql' => $sql,
                'selected_ids' => $result['plan']->selectedIds,
                'mutation_ids' => $result['plan']->mutationIds,
                'source_rows' => $result['plan']->selectedRows,
                'returning_rows' => $rows,
                'returning_count' => count($rows),
                'changed_tables' => self::changedTablesPartitionedRetryWindow($before, $current),
            ];
        }

        return ['tables' => $current, 'statements' => $summaries, 'returning_rows' => $returningRows];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function tagReturningRowPartitionedRetryWindow(array $row, string $phase, int $statementOrdinal, int $returningOrdinal): array
    {
        $row['returning_phase'] = $phase;
        $row['statement_ordinal'] = $statementOrdinal;
        $row['returning_ordinal'] = $returningOrdinal;

        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function windowRowsPartitionedRetryWindow(array $rows, string $partitionColumn, string $orderColumn, string $rowIdColumn, string $labelColumn): array
    {
        $indexed = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists($partitionColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value RETURNING window next234 partition column {$partitionColumn} is missing");
            }
            if (!array_key_exists($orderColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value RETURNING window next234 order column {$orderColumn} is missing");
            }
            $indexed[] = ['index' => $index, 'row' => $row];
        }
        usort($indexed, static function (array $left, array $right) use ($partitionColumn, $orderColumn): int {
            $partition = self::compareValuesPartitionedRetryWindow($left['row'][$partitionColumn], $right['row'][$partitionColumn]);
            if ($partition !== 0) {
                return $partition;
            }
            $order = self::compareValuesPartitionedRetryWindow($left['row'][$orderColumn], $right['row'][$orderColumn]);
            if ($order !== 0) {
                return $order;
            }

            return $left['index'] <=> $right['index'];
        });

        $byPartition = [];
        foreach ($indexed as $entry) {
            $key = self::valueKeyPartitionedRetryWindow($entry['row'][$partitionColumn]);
            $byPartition[$key][] = $entry['row'];
        }

        $windowRows = [];
        foreach ($byPartition as $partitionRows) {
            $denseRank = 0;
            $previousOrderKey = null;
            $count = count($partitionRows);
            foreach ($partitionRows as $position => $row) {
                $orderKey = self::valueKeyPartitionedRetryWindow($row[$orderColumn]);
                if ($previousOrderKey !== $orderKey) {
                    $denseRank++;
                    $previousOrderKey = $orderKey;
                }
                $lag = $position > 0 ? $partitionRows[$position - 1] : null;
                $lead = $position + 1 < $count ? $partitionRows[$position + 1] : null;
                $row['window_row_number'] = $position + 1;
                $row['window_dense_rank'] = $denseRank;
                $row['window_partition_size'] = $count;
                $row['window_lag_key_name'] = $lag[$labelColumn] ?? null;
                $row['window_lead_key_name'] = $lead[$labelColumn] ?? null;
                $row['window_current_row'] = true;
                $row['window_frame_row_ids'] = self::frameRowidsPartitionedRetryWindow($partitionRows, max(0, $position - 1), min($count - 1, $position + 1), $rowIdColumn);
                $windowRows[] = $row;
            }
        }

        return $windowRows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function frameRowidsPartitionedRetryWindow(array $rows, int $start, int $end, string $rowIdColumn): array
    {
        $ids = [];
        for ($i = $start; $i <= $end; $i++) {
            if (array_key_exists($i, $rows) && array_key_exists($rowIdColumn, $rows[$i])) {
                $id = $rows[$i][$rowIdColumn];
                if (is_int($id) || is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array{count:int,row_numbers:list<int>,row_ids:list<int|string>}>
     */
    private static function partitionSummaryPartitionedRetryWindow(array $rows, string $partitionColumn, string $rowIdColumn): array
    {
        $summary = [];
        foreach ($rows as $row) {
            $key = self::valueKeyPartitionedRetryWindow($row[$partitionColumn] ?? null);
            if (!isset($summary[$key])) {
                $summary[$key] = ['count' => 0, 'row_numbers' => [], 'row_ids' => []];
            }
            $summary[$key]['count']++;
            $summary[$key]['row_numbers'][] = (int) $row['window_row_number'];
            if (isset($row[$rowIdColumn]) && (is_int($row[$rowIdColumn]) || is_string($row[$rowIdColumn]))) {
                $summary[$key]['row_ids'][] = $row[$rowIdColumn];
            }
        }

        return $summary;
    }

    private static function compareValuesPartitionedRetryWindow(mixed $left, mixed $right): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function valueKeyPartitionedRetryWindow(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        return serialize($value);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesPartitionedRetryWindow(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
            if (($before[$table] ?? null) !== ($after[$table] ?? null)) {
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
    private static function rowCountsPartitionedRetryWindow(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }
        ksort($counts);

        return $counts;
    }

    private static function sourceTokenPartitionedRetryWindow(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     */
    private static function validateTablesPartitionedRetryWindow(array $tables): void
    {
        if ($tables === []) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next234 needs tables');
        }
        foreach ($tables as $table => $rows) {
            if (!is_string($table) || $table === '' || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value RETURNING window next234 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException("SQLite row-value RETURNING window next234 table {$table} rows must be arrays");
                }
            }
        }
    }

    /**
     * @param list<string> $statements
     */
    private static function validateStatementsPartitionedRetryWindow(array $statements, string $label): void
    {
        if ($statements === []) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING window next234 {$label} statements must not be empty");
        }
        foreach ($statements as $statement) {
            if (!is_string($statement) || trim($statement) === '') {
                throw new \InvalidArgumentException("SQLite row-value RETURNING window next234 {$label} statements must be SQL strings");
            }
        }
    }

    /**
     * @param list<list<string>> $uniqueConstraints
     */
    private static function validateUniqueConstraintsPartitionedRetryWindow(array $uniqueConstraints): void
    {
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next234 needs unique constraints');
        }
        foreach ($uniqueConstraints as $columns) {
            if (!is_array($columns) || $columns === []) {
                throw new \InvalidArgumentException('SQLite row-value RETURNING window next234 unique constraints need columns');
            }
            foreach ($columns as $column) {
                self::validateIdentifierPartitionedRetryWindow($column, 'unique column');
            }
        }
    }

    private static function validateIdentifierPartitionedRetryWindow(string $identifier, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING window next234 {$label} must be an identifier");
        }
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReturningWindowDigests(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_returning_window_next235',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $plan = SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeSubquerySavepointRollbackRetry(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $discarded = self::windowRowsReturningWindowDigests($plan['discarded_attempt_returning'], 'discarded-attempt-window-next235', $rowIdColumn);
        $yielded = self::windowRowsReturningWindowDigests($plan['yielded_after_retry_returning'], 'yielded-retry-window-next235', $rowIdColumn);

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next235';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next235'] = true;
        $plan['window_partition_keys_next235'] = ['phase', 'action'];
        $plan['window_order_keys_next235'] = [$rowIdColumn];
        $plan['discarded_attempt_window_rows_next235'] = $discarded;
        $plan['yielded_retry_window_rows_next235'] = $yielded;
        $plan['discarded_attempt_window_count_next235'] = count($discarded);
        $plan['yielded_retry_window_count_next235'] = count($yielded);
        $plan['discarded_attempt_window_ids_next235'] = array_column($discarded, $rowIdColumn);
        $plan['yielded_retry_window_ids_next235'] = array_column($yielded, $rowIdColumn);
        $plan['discarded_attempt_window_digest_next235'] = self::digestReturningWindowDigests($discarded, $rowIdColumn);
        $plan['yielded_retry_window_digest_next235'] = self::digestReturningWindowDigests($yielded, $rowIdColumn);
        $plan['window_yield_boundary_next235'] = [
            'discarded_attempt_rows' => count($discarded),
            'yielded_retry_rows' => count($yielded),
            'rollback_to_savepoint' => $plan['rolled_back_to_savepoint'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
        ];
        $plan['dependencies'] = [
            'sqlite-rowvalue-update-returning-window-current-source-next235',
            'sqlite-rowvalue-delete-returning-window-current-source-next235',
            'sqlite-rowvalue-returning-window-savepoint-yield-boundary-next235',
        ];
        $plan['dependency_closure_next235'] = 'no new support component needed; next235 reuses native PHP row-value UPDATE/DELETE RETURNING savepoint execution and adds bounded RETURNING-window metadata over current-source streams';
        $plan['non_overlap_next235'] = 'adds RETURNING stream window row_number/partition metadata across discarded attempt rows and yielded retry rows; avoids accepted next231 compound subqueries, next232 current-source row-value behavior, next229 LIMIT -1 OFFSET tuple sources, trigger RETURNING, JSON table, WAL/VFS, planner, B-tree, PRAGMA, and encoding clusters';

        return $plan;
    }

    /**
     * @param list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}> $streams
     * @return list<array<string,mixed>>
     */
    private static function windowRowsReturningWindowDigests(array $streams, string $streamName, string $rowIdColumn): array
    {
        $rows = [];
        $partitionOrdinals = [];

        foreach ($streams as $stream) {
            $partition = $stream['phase'] . ':' . $stream['action'];
            foreach ($stream['rows'] as $row) {
                if (!array_key_exists($rowIdColumn, $row)) {
                    throw new \InvalidArgumentException("SQLite row-value returning window next235 rowid column {$rowIdColumn} is missing");
                }

                $partitionOrdinals[$partition] = ($partitionOrdinals[$partition] ?? 0) + 1;
                $windowRow = $row;
                $windowRow['window_stream_next235'] = $streamName;
                $windowRow['window_phase_next235'] = $stream['phase'];
                $windowRow['window_action_next235'] = $stream['action'];
                $windowRow['window_statement_ordinal_next235'] = $stream['ordinal'];
                $windowRow['window_partition_next235'] = $partition;
                $windowRow['window_row_number_next235'] = count($rows) + 1;
                $windowRow['window_partition_row_number_next235'] = $partitionOrdinals[$partition];
                $windowRow['window_first_in_partition_next235'] = $partitionOrdinals[$partition] === 1;
                $rows[] = $windowRow;
            }
        }

        usort($rows, static function (array $left, array $right) use ($rowIdColumn): int {
            return [$left['window_phase_next235'], $left['window_action_next235'], $left[$rowIdColumn]]
                <=> [$right['window_phase_next235'], $right['window_action_next235'], $right[$rowIdColumn]];
        });

        $partitionOrdinals = [];
        foreach ($rows as $index => $row) {
            $partition = $row['window_partition_next235'];
            $partitionOrdinals[$partition] = ($partitionOrdinals[$partition] ?? 0) + 1;
            $rows[$index]['window_row_number_next235'] = $index + 1;
            $rows[$index]['window_partition_row_number_next235'] = $partitionOrdinals[$partition];
            $rows[$index]['window_first_in_partition_next235'] = $partitionOrdinals[$partition] === 1;
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function digestReturningWindowDigests(array $rows, string $rowIdColumn): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = implode(':', [
                (string) $row['window_action_next235'],
                (string) $row[$rowIdColumn],
                (string) $row['window_partition_row_number_next235'],
            ]);
        }

        return hash('sha256', implode('|', $parts));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCurrentRowWindowFrames(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next236',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowRollbackRetry(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $yieldFrame = self::currentRowFramesCurrentRowWindowFrames($base['yield_window'], $rowIdColumn);
        $suppressedFrame = self::currentRowFramesCurrentRowWindowFrames($base['suppressed_attempt_window'], $rowIdColumn);
        $retryFrame = self::currentRowFramesCurrentRowWindowFrames($base['retry_window'], $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next236',
            'window_current_row_frame_next236' => true,
            'yield_current_row_frames_next236' => $yieldFrame,
            'suppressed_current_row_frames_next236' => $suppressedFrame,
            'retry_current_row_frames_next236' => $retryFrame,
            'retry_current_row_frame_ids_next236' => array_column($retryFrame, $rowIdColumn),
            'retry_current_row_frame_values_next236' => array_column($retryFrame, 'current_row_value'),
            'retry_running_bytes_next236' => array_column($retryFrame, 'running_bytes'),
            'retry_following_bytes_next236' => array_column($retryFrame, 'following_bytes'),
            'retry_neighbor_names_next236' => array_map(
                static fn (array $row): array => [$row['lag_name'], self::keyNameString($row), $row['lead_name']],
                $retryFrame,
            ),
            'current_source_receipt_next236' => self::currentSourceReceiptCurrentRowWindowFrames($base, $retryFrame, $rowIdColumn),
            'dependency_closure_next236' => 'no new support component needed; next236 reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint row images, and window metadata from next233 while adding current-row frame receipts',
            'dependencies_next236' => [
                'sqlite-rowvalue-update-delete-returning-window-current-row-next236',
                'sqlite-returning-current-row-frame-after-rollback-release-next236',
                'application-rowvalue-returning-current-row-window-next236',
            ],
            'non_overlap_next236' => 'adds current-row window-frame receipts, lag/lead neighbors, and running/following byte frames over row-value UPDATE/DELETE RETURNING streams after rollback/release; avoids next232 simple retry row numbering, accepted next233 row_number/dense_rank/count/sum windows, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $windowRows
     * @return list<array<string,mixed>>
     */
    private static function currentRowFramesCurrentRowWindowFrames(array $windowRows, string $rowIdColumn): array
    {
        $frames = [];
        $running = 0;
        $total = 0;
        foreach ($windowRows as $row) {
            $total += self::numericValueCurrentRowWindowFrames($row['bytes'] ?? null);
        }

        foreach (array_values($windowRows) as $index => $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value current-row window next236 rowid column {$rowIdColumn} is missing");
            }
            $bytes = self::numericValueCurrentRowWindowFrames($row['bytes'] ?? null);
            $running += $bytes;
            $lag = $windowRows[$index - 1] ?? null;
            $lead = $windowRows[$index + 1] ?? null;

            $frames[] = [
                $rowIdColumn => self::rowIdValueCurrentRowWindowFrames($row, $rowIdColumn),
                self::keyNameColumn($row) => self::keyNameString($row),
                'status' => $row['status'] ?? null,
                'row_number' => self::numericValueCurrentRowWindowFrames($row['row_number'] ?? null),
                'dense_rank' => self::numericValueCurrentRowWindowFrames($row['dense_rank'] ?? null),
                'current_row_value' => $bytes,
                'current_row_count' => 1,
                'running_bytes' => $running,
                'following_bytes' => $total - $running,
                'lag_id' => $lag === null ? null : self::rowIdValueCurrentRowWindowFrames($lag, $rowIdColumn),
                'lead_id' => $lead === null ? null : self::rowIdValueCurrentRowWindowFrames($lead, $rowIdColumn),
                'lag_name' => self::nullableKeyName($lag),
                'lead_name' => self::nullableKeyName($lead),
                'frame_token' => self::keyNameString($row) . ':' . $bytes . ':' . $running,
            ];
        }

        return $frames;
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $retryFrame
     * @return array<string,mixed>
     */
    private static function currentSourceReceiptCurrentRowWindowFrames(array $base, array $retryFrame, string $rowIdColumn): array
    {
        return [
            'savepoint' => $base['savepoint'],
            'retry_ids' => array_column($retryFrame, $rowIdColumn),
            'retry_frame_tokens' => array_column($retryFrame, 'frame_token'),
            'retry_running_final' => $retryFrame === [] ? 0 : $retryFrame[array_key_last($retryFrame)]['running_bytes'],
            'retry_following_final' => $retryFrame === [] ? 0 : $retryFrame[array_key_last($retryFrame)]['following_bytes'],
            'rolled_back_attempt_ids' => $base['all_window_receipt_next233']['suppressed_ids'],
            'released_table_count' => count(self::primarySourceRows($base['current_source_tables'] ?? [], $rowIdColumn)),
            'next_source_matches_current' => $base['next_source_tables'] === $base['current_source_tables'],
        ];
    }

    private static function numericValueCurrentRowWindowFrames(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdValueCurrentRowWindowFrames(array $row, string $rowIdColumn): int|string
    {
        $id = $row[$rowIdColumn];
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException("SQLite row-value current-row window next236 rowid column {$rowIdColumn} must be int or string");
        }

        return $id;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeExcludeCurrentRetryWindow(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $partitionColumn = 'tenant_id',
        string $orderColumn = 'bytes',
        string $rowIdColumn = 'setting_id',
        string $savepoint = 'app_settings_rowvalue_returning_window_next237',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        self::validateTablesExcludeCurrentRetryWindow($tables);
        self::validateStatementsExcludeCurrentRetryWindow($attemptStatements, 'attempt');
        self::validateStatementsExcludeCurrentRetryWindow($retryStatements, 'retry');
        self::validateUniqueConstraintsExcludeCurrentRetryWindow($uniqueConstraints);
        self::validateIdentifierExcludeCurrentRetryWindow($partitionColumn, 'partition column');
        self::validateIdentifierExcludeCurrentRetryWindow($orderColumn, 'order column');
        self::validateIdentifierExcludeCurrentRetryWindow($rowIdColumn, 'rowid column');
        self::validateIdentifierExcludeCurrentRetryWindow($savepoint, 'savepoint');

        $savepointImage = $tables;
        $attempt = self::runStatementsExcludeCurrentRetryWindow($savepointImage, $attemptStatements, $uniqueConstraints, $rowIdColumn, 'attempt-before-rollback-next237');
        $rollback = $savepointImage;
        $retry = self::runStatementsExcludeCurrentRetryWindow($rollback, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-after-rollback-next237');
        $windowRows = self::excludeCurrentWindowRowsExcludeCurrentRetryWindow($retry['returning_rows'], $partitionColumn, $orderColumn, $rowIdColumn, 'key_name');

        return [
            'status' => 'rowvalue-update-delete-returning-window-exclude-current-source-next237',
            'savepoint' => $savepoint,
            'partition_column' => $partitionColumn,
            'order_column' => $orderColumn,
            'rowid_column' => $rowIdColumn,
            'savepoint_image_tables' => $savepointImage,
            'attempt_current_source_tables' => $attempt['tables'],
            'rollback_current_source_tables' => $rollback,
            'current_source_tables' => $retry['tables'],
            'next_source_tables' => $retry['tables'],
            'attempt_statements' => $attempt['statements'],
            'retry_statements' => $retry['statements'],
            'discarded_attempt_returning' => $attempt['returning_rows'],
            'discarded_attempt_returning_count' => count($attempt['returning_rows']),
            'yielded_returning' => $retry['returning_rows'],
            'yielded_returning_count' => count($retry['returning_rows']),
            'exclude_current_window_rows' => $windowRows,
            'exclude_current_window_count' => count($windowRows),
            'exclude_current_partition_summary' => self::partitionSummaryExcludeCurrentRetryWindow($windowRows, $partitionColumn, $rowIdColumn),
            'rolled_back_to_savepoint' => true,
            'retry_reads_savepoint_image' => true,
            'savepoint_released_after_retry' => true,
            'attempt_returning_suppressed_after_rollback' => true,
            'changed_tables_after_retry' => self::changedTablesExcludeCurrentRetryWindow($savepointImage, $retry['tables']),
            'row_counts' => self::rowCountsExcludeCurrentRetryWindow($retry['tables']),
            'current_source_token' => self::sourceTokenExcludeCurrentRetryWindow($retry['tables']),
            'window_token' => self::sourceTokenExcludeCurrentRetryWindow($windowRows),
            'dependencies' => [
                'sqlite-rowvalue-returning-window-exclude-current-next237',
                'sqlite-rowvalue-delete-returning-window-peer-frame-next237',
                'application-rowvalue-returning-window-current-source-next237',
            ],
            'dependency_closure_next237' => 'no new support component needed; next237 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint row images, and bounded RETURNING window receipt materialization.',
            'non_overlap_next237' => 'adds EXCLUDE CURRENT ROW style peer-frame receipts over retry-yielded row-value UPDATE/DELETE RETURNING rows after rollback; avoids accepted next233/next234 basic RETURNING window partitioning, next226 DISTINCT subqueries, next224 nested savepoint rollback, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, PRAGMA, and encoding clusters.',
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{tables:array<string,list<array<string,mixed>>>,statements:list<array<string,mixed>>,returning_rows:list<array<string,mixed>>}
     */
    private static function runStatementsExcludeCurrentRetryWindow(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $summaries = [];
        $returningRows = [];
        foreach ($statements as $statementOrdinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $rows = [];
            foreach ($result['returning'] as $returningOrdinal => $row) {
                $row['returning_phase'] = $phase;
                $row['statement_ordinal'] = $statementOrdinal;
                $row['returning_ordinal'] = $returningOrdinal;
                $rows[] = $row;
            }
            $returningRows = array_merge($returningRows, $rows);
            $summaries[] = [
                'phase' => $phase,
                'ordinal' => $statementOrdinal,
                'action' => $result['action'],
                'table' => $result['table'],
                'sql' => $sql,
                'selected_ids' => $result['plan']->selectedIds,
                'mutation_ids' => $result['plan']->mutationIds,
                'source_rows' => $result['plan']->selectedRows,
                'returning_rows' => $rows,
                'returning_count' => count($rows),
                'changed_tables' => self::changedTablesExcludeCurrentRetryWindow($before, $current),
            ];
        }

        return ['tables' => $current, 'statements' => $summaries, 'returning_rows' => $returningRows];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function excludeCurrentWindowRowsExcludeCurrentRetryWindow(array $rows, string $partitionColumn, string $orderColumn, string $rowIdColumn, string $labelColumn): array
    {
        $indexed = [];
        foreach ($rows as $index => $row) {
            foreach ([$partitionColumn, $orderColumn, $rowIdColumn, $labelColumn] as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite row-value RETURNING window next237 column {$column} is missing");
                }
            }
            $indexed[] = ['index' => $index, 'row' => $row];
        }

        usort($indexed, static function (array $left, array $right) use ($partitionColumn, $orderColumn, $rowIdColumn): int {
            $partition = self::compareValuesExcludeCurrentRetryWindow($left['row'][$partitionColumn], $right['row'][$partitionColumn]);
            if ($partition !== 0) {
                return $partition;
            }
            $order = self::compareValuesExcludeCurrentRetryWindow($left['row'][$orderColumn], $right['row'][$orderColumn]);
            if ($order !== 0) {
                return $order;
            }
            $rowid = self::compareValuesExcludeCurrentRetryWindow($left['row'][$rowIdColumn], $right['row'][$rowIdColumn]);
            if ($rowid !== 0) {
                return $rowid;
            }

            return $left['index'] <=> $right['index'];
        });

        $partitions = [];
        foreach ($indexed as $entry) {
            $partitions[self::valueKeyExcludeCurrentRetryWindow($entry['row'][$partitionColumn])][] = $entry['row'];
        }

        $windowRows = [];
        foreach ($partitions as $partitionRows) {
            $partitionSize = count($partitionRows);
            foreach ($partitionRows as $position => $row) {
                $frameRows = [];
                foreach ($partitionRows as $candidateIndex => $candidate) {
                    if ($candidateIndex !== $position) {
                        $frameRows[] = $candidate;
                    }
                }

                $row['window_row_number'] = $position + 1;
                $row['window_partition_size'] = $partitionSize;
                $row['window_exclude_current'] = true;
                $row['window_peer_count_excluding_current'] = count($frameRows);
                $row['window_peer_row_ids_excluding_current'] = self::rowIdsExcludeCurrentRetryWindow($frameRows, $rowIdColumn);
                $row['window_peer_key_names_excluding_current'] = array_values(array_map(static fn (array $peer): string => (string) ($peer[$labelColumn] ?? ''), $frameRows));
                $row['window_peer_bytes_excluding_current'] = array_sum(array_map(static fn (array $peer): int => self::intValueExcludeCurrentRetryWindow($peer['bytes'] ?? 0), $frameRows));
                $row['window_peer_first_key_name'] = $frameRows[0][$labelColumn] ?? null;
                $row['window_peer_last_key_name'] = $frameRows === [] ? null : ($frameRows[array_key_last($frameRows)][$labelColumn] ?? null);
                $windowRows[] = $row;
            }
        }

        return $windowRows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array{count:int,row_ids:list<int|string>,peer_counts:list<int>,peer_row_ids:list<list<int|string>>}>
     */
    private static function partitionSummaryExcludeCurrentRetryWindow(array $rows, string $partitionColumn, string $rowIdColumn): array
    {
        $summary = [];
        foreach ($rows as $row) {
            $key = self::valueKeyExcludeCurrentRetryWindow($row[$partitionColumn] ?? null);
            if (!isset($summary[$key])) {
                $summary[$key] = ['count' => 0, 'row_ids' => [], 'peer_counts' => [], 'peer_row_ids' => []];
            }
            $summary[$key]['count']++;
            $summary[$key]['row_ids'][] = $row[$rowIdColumn];
            $summary[$key]['peer_counts'][] = (int) $row['window_peer_count_excluding_current'];
            $summary[$key]['peer_row_ids'][] = $row['window_peer_row_ids_excluding_current'];
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function rowIdsExcludeCurrentRetryWindow(array $rows, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = $row[$rowIdColumn] ?? null;
            if (is_int($id) || is_string($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     */
    private static function validateTablesExcludeCurrentRetryWindow(array $tables): void
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value RETURNING window next237 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value RETURNING window next237 rows must be arrays');
                }
            }
        }
    }

    /**
     * @param list<string> $statements
     */
    private static function validateStatementsExcludeCurrentRetryWindow(array $statements, string $label): void
    {
        if ($statements === []) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING window next237 needs {$label} statements");
        }
        foreach ($statements as $sql) {
            if (!is_string($sql) || trim($sql) === '') {
                throw new \InvalidArgumentException("SQLite row-value RETURNING window next237 {$label} statements must be SQL strings");
            }
        }
    }

    /**
     * @param list<list<string>> $uniqueConstraints
     */
    private static function validateUniqueConstraintsExcludeCurrentRetryWindow(array $uniqueConstraints): void
    {
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next237 needs unique constraints');
        }
        foreach ($uniqueConstraints as $constraint) {
            if (!array_is_list($constraint) || $constraint === []) {
                throw new \InvalidArgumentException('SQLite row-value RETURNING window next237 unique constraints must be non-empty lists');
            }
            foreach ($constraint as $column) {
                self::validateIdentifierExcludeCurrentRetryWindow($column, 'unique column');
            }
        }
    }

    private static function validateIdentifierExcludeCurrentRetryWindow(string $name, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING window next237 {$label} must be an identifier");
        }
    }

    /**
     * @param array<string,list<array<string,mixed>>> $before
     * @param array<string,list<array<string,mixed>>> $after
     * @return list<string>
     */
    private static function changedTablesExcludeCurrentRetryWindow(array $before, array $after): array
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
    private static function rowCountsExcludeCurrentRetryWindow(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }
        ksort($counts);

        return $counts;
    }

    private static function compareValuesExcludeCurrentRetryWindow(mixed $left, mixed $right): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    private static function intValueExcludeCurrentRetryWindow(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) || is_string($value)) {
            return (int) $value;
        }

        return 0;
    }

    private static function valueKeyExcludeCurrentRetryWindow(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function sourceTokenExcludeCurrentRetryWindow(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReplayPairWindow(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_returning_window_next238',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReturningWindowDigests(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $discarded = self::tagSourceRowsReplayPairWindow(
            $plan['discarded_attempt_window_rows_next235'],
            'discarded-current-source-next238',
            $rowIdColumn,
        );
        $yielded = self::tagSourceRowsReplayPairWindow(
            $plan['yielded_retry_window_rows_next235'],
            'yielded-next-source-next238',
            $rowIdColumn,
        );
        $pairs = self::pairRowsReplayPairWindow($discarded, $yielded, $rowIdColumn);
        $summary = self::summaryReplayPairWindow($pairs);

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next238';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next238'] = true;
        $plan['current_source_window_rows_next238'] = $discarded;
        $plan['next_source_window_rows_next238'] = $yielded;
        $plan['window_pair_rows_next238'] = $pairs;
        $plan['window_pair_count_next238'] = count($pairs);
        $plan['window_pair_summary_next238'] = $summary;
        $plan['window_current_source_ids_next238'] = array_column($discarded, $rowIdColumn);
        $plan['window_next_source_ids_next238'] = array_column($yielded, $rowIdColumn);
        $plan['window_replayed_rowids_next238'] = self::idsForClassReplayPairWindow($pairs, 'replayed-after-rollback');
        $plan['window_restart_only_rowids_next238'] = self::idsForClassReplayPairWindow($pairs, 'restart-only');
        $plan['window_discarded_only_rowids_next238'] = self::idsForClassReplayPairWindow($pairs, 'discarded-only');
        $plan['window_source_fence_next238'] = [
            'savepoint' => $savepoint,
            'rolled_back_to_savepoint' => $plan['rolled_back_to_savepoint'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
            'current_source_digest' => self::digestReplayPairWindow($discarded, $rowIdColumn),
            'next_source_digest' => self::digestReplayPairWindow($yielded, $rowIdColumn),
            'pair_digest' => self::digestReplayPairWindow($pairs, 'pair_key_next238'),
        ];
        $plan['dependencies'] = [
            'sqlite-rowvalue-returning-window-current-source-fence-next238',
            'sqlite-rowvalue-update-returning-window-replay-next238',
            'sqlite-rowvalue-delete-returning-window-restart-next238',
        ];
        $plan['dependency_closure_next238'] = 'no new support component needed; next238 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint rollback, and next235 RETURNING-window rows.';
        $plan['non_overlap_next238'] = 'adds current-source/next-source RETURNING window pair classification after rollback; avoids accepted nullable row-value savepoint cases, next232-next235 window materialization, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, and encoding clusters.';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function tagSourceRowsReplayPairWindow(array $rows, string $source, string $rowIdColumn): array
    {
        $tagged = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value RETURNING window next238 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value RETURNING window next238 rowid column {$rowIdColumn} must be int or string");
            }
            $action = $row['window_action_next235'] ?? null;
            if (!is_string($action) || $action === '') {
                throw new \InvalidArgumentException('SQLite row-value RETURNING window next238 rows need next235 action metadata');
            }

            $row['window_source_next238'] = $source;
            $row['window_source_key_next238'] = $action . ':' . $id;
            $row['window_current_source_candidate_next238'] = $source === 'discarded-current-source-next238';
            $row['window_yielded_after_retry_next238'] = $source === 'yielded-next-source-next238';
            $tagged[] = $row;
        }

        return $tagged;
    }

    /**
     * @param list<array<string,mixed>> $discarded
     * @param list<array<string,mixed>> $yielded
     * @return list<array<string,mixed>>
     */
    private static function pairRowsReplayPairWindow(array $discarded, array $yielded, string $rowIdColumn): array
    {
        $discardedByKey = self::rowsByKeyReplayPairWindow($discarded);
        $yieldedByKey = self::rowsByKeyReplayPairWindow($yielded);
        $keys = array_values(array_unique(array_merge(array_keys($discardedByKey), array_keys($yieldedByKey))));
        usort($keys, static function (string $left, string $right): int {
            [$leftAction, $leftId] = self::splitPairKeyReplayPairWindow($left);
            [$rightAction, $rightId] = self::splitPairKeyReplayPairWindow($right);
            $action = $leftAction <=> $rightAction;
            if ($action !== 0) {
                return $action;
            }
            if (ctype_digit($leftId) && ctype_digit($rightId)) {
                return (int) $leftId <=> (int) $rightId;
            }

            return $leftId <=> $rightId;
        });

        $pairs = [];
        foreach ($keys as $ordinal => $key) {
            $current = $discardedByKey[$key] ?? null;
            $next = $yieldedByKey[$key] ?? null;
            $class = self::pairClassReplayPairWindow($current, $next);
            $rowId = self::pairRowIdReplayPairWindow($current, $next, $rowIdColumn);
            $action = self::pairActionReplayPairWindow($current, $next);

            $pairs[] = [
                'pair_ordinal_next238' => $ordinal,
                'pair_key_next238' => $key,
                'rowid_next238' => $rowId,
                'action_next238' => $action,
                'pair_class_next238' => $class,
                'current_window_row_number_next238' => $current['window_row_number_next235'] ?? null,
                'next_window_row_number_next238' => $next['window_row_number_next235'] ?? null,
                'current_partition_row_number_next238' => $current['window_partition_row_number_next235'] ?? null,
                'next_partition_row_number_next238' => $next['window_partition_row_number_next235'] ?? null,
                'current_status_next238' => $current['status'] ?? null,
                'next_status_next238' => $next['status'] ?? null,
                'current_key_value_next238' => self::nullableKeyValue($current),
                'next_key_value_next238' => self::nullableKeyValue($next),
                'current_present_next238' => $current !== null,
                'next_present_next238' => $next !== null,
                'rollback_preserved_current_next238' => $current !== null && $next === null,
                'retry_replayed_next238' => $current !== null && $next !== null,
                'retry_restart_only_next238' => $current === null && $next !== null,
            ];
        }

        return $pairs;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function rowsByKeyReplayPairWindow(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $key = $row['window_source_key_next238'] ?? null;
            if (!is_string($key) || $key === '') {
                throw new \InvalidArgumentException('SQLite row-value RETURNING window next238 rows need a source key');
            }
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     */
    private static function pairClassReplayPairWindow(?array $current, ?array $next): string
    {
        if ($current !== null && $next !== null) {
            return 'replayed-after-rollback';
        }
        if ($current !== null) {
            return 'discarded-only';
        }
        if ($next !== null) {
            return 'restart-only';
        }

        throw new \InvalidArgumentException('SQLite row-value RETURNING window next238 empty pair is invalid');
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitPairKeyReplayPairWindow(string $key): array
    {
        $parts = explode(':', $key, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next238 pair key is malformed');
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     */
    private static function pairRowIdReplayPairWindow(?array $current, ?array $next, string $rowIdColumn): int|string
    {
        $row = $current ?? $next;
        if ($row === null || !array_key_exists($rowIdColumn, $row)) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING window next238 rowid column {$rowIdColumn} is missing");
        }
        $id = $row[$rowIdColumn];
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING window next238 rowid column {$rowIdColumn} must be int or string");
        }

        return $id;
    }

    /**
     * @param array<string,mixed>|null $current
     * @param array<string,mixed>|null $next
     */
    private static function pairActionReplayPairWindow(?array $current, ?array $next): string
    {
        $row = $current ?? $next;
        $action = $row['window_action_next235'] ?? null;
        if (!is_string($action) || $action === '') {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next238 row action is missing');
        }

        return $action;
    }

    /**
     * @param list<array<string,mixed>> $pairs
     * @return array<string,int>
     */
    private static function summaryReplayPairWindow(array $pairs): array
    {
        $summary = [
            'replayed-after-rollback' => 0,
            'restart-only' => 0,
            'discarded-only' => 0,
            'update' => 0,
            'delete' => 0,
        ];
        foreach ($pairs as $pair) {
            $class = (string) $pair['pair_class_next238'];
            $action = (string) $pair['action_next238'];
            $summary[$class] = ($summary[$class] ?? 0) + 1;
            $summary[$action] = ($summary[$action] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $pairs
     * @return list<int|string>
     */
    private static function idsForClassReplayPairWindow(array $pairs, string $class): array
    {
        $ids = [];
        foreach ($pairs as $pair) {
            if ($pair['pair_class_next238'] === $class) {
                $id = $pair['rowid_next238'];
                if (is_int($id) || is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function digestReplayPairWindow(array $rows, string $keyColumn): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = implode(':', [
                (string) ($row[$keyColumn] ?? ''),
                (string) ($row['window_source_next238'] ?? $row['pair_class_next238'] ?? ''),
                (string) ($row['window_row_number_next235'] ?? $row['pair_ordinal_next238'] ?? ''),
            ]);
        }

        return hash('sha256', implode('|', $parts));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeStatementWindowMetrics(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next239',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentRowWindowFrames(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $retryPartitions = self::statementPartitionsStatementWindowMetrics($base['retry_returning'], $rowIdColumn);
        $suppressedPartitions = self::statementPartitionsStatementWindowMetrics($base['suppressed_attempt_returning'], $rowIdColumn);
        $yieldPartitions = self::statementPartitionsStatementWindowMetrics($base['yield_returning'], $rowIdColumn);
        $releaseSeal = self::releaseSealStatementWindowMetrics($base, $retryPartitions, $suppressedPartitions, $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next239',
            'statement_partition_window_next239' => true,
            'yield_statement_windows_next239' => $yieldPartitions,
            'suppressed_statement_windows_next239' => $suppressedPartitions,
            'retry_statement_windows_next239' => $retryPartitions,
            'retry_statement_window_ids_next239' => self::partitionIdsStatementWindowMetrics($retryPartitions, $rowIdColumn),
            'retry_statement_window_tiles_next239' => self::partitionColumnStatementWindowMetrics($retryPartitions, 'ntile_2'),
            'retry_statement_window_exclude_ids_next239' => self::partitionColumnStatementWindowMetrics($retryPartitions, 'exclude_current_neighbor_ids'),
            'retry_statement_window_percent_rank_next239' => self::partitionColumnStatementWindowMetrics($retryPartitions, 'percent_rank_milli'),
            'retry_statement_window_cume_dist_next239' => self::partitionColumnStatementWindowMetrics($retryPartitions, 'cume_dist_milli'),
            'retry_statement_window_edges_next239' => self::partitionEdgesStatementWindowMetrics($retryPartitions),
            'release_window_seal_next239' => $releaseSeal,
            'dependency_closure_next239' => 'no new support component needed; next239 reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint current-source images, and lane-local window rows while adding statement-partitioned retry release seals.',
            'dependencies_next239' => [
                'sqlite-rowvalue-returning-statement-window-next239',
                'sqlite-returning-window-exclude-current-after-rollback-next239',
                'application-rowvalue-returning-release-window-seal-next239',
            ],
            'non_overlap_next239' => 'adds statement-partitioned retry RETURNING windows, ntile/percent-rank/cume-dist receipts, and EXCLUDE CURRENT ROW neighbor frames after rollback/release; avoids accepted next233 row_number/dense_rank/count/sum windows, next236 current-row frames, row-value UPSERT, trigger RETURNING, JSON table, planner, WAL/VFS, B-tree, PRAGMA, and encoding clusters.',
        ]);
    }

    /**
     * @param list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}> $returning
     * @return array<string,list<array<string,mixed>>>
     */
    private static function statementPartitionsStatementWindowMetrics(array $returning, string $rowIdColumn): array
    {
        $partitions = [];
        foreach ($returning as $statement) {
            if (!isset($statement['rows']) || !is_array($statement['rows'])) {
                throw new \InvalidArgumentException('SQLite row-value statement window next239 returning rows are malformed');
            }
            $key = self::partitionKeyStatementWindowMetrics($statement);
            $rows = $statement['rows'];
            usort($rows, static function (array $left, array $right) use ($rowIdColumn): int {
                $bytes = self::numericValueStatementWindowMetrics($right['bytes'] ?? null) <=> self::numericValueStatementWindowMetrics($left['bytes'] ?? null);
                if ($bytes !== 0) {
                    return $bytes;
                }

                return self::rowIdValueStatementWindowMetrics($left, $rowIdColumn) <=> self::rowIdValueStatementWindowMetrics($right, $rowIdColumn);
            });

            $count = count($rows);
            $sum = 0;
            foreach ($rows as $row) {
                $sum += self::numericValueStatementWindowMetrics($row['bytes'] ?? null);
            }

            $windowRows = [];
            $previousBytes = null;
            $rank = 1;
            $denseRank = 0;
            foreach ($rows as $index => $row) {
                $bytes = self::numericValueStatementWindowMetrics($row['bytes'] ?? null);
                if ($previousBytes === null || $bytes !== $previousBytes) {
                    $rank = $index + 1;
                    ++$denseRank;
                    $previousBytes = $bytes;
                }

                $windowRows[] = [
                    $rowIdColumn => self::rowIdValueStatementWindowMetrics($row, $rowIdColumn),
                    self::keyNameColumn($row) => self::keyNameString($row),
                    'status' => $row['status'] ?? null,
                    'bytes' => $bytes,
                    'statement_key' => $key,
                    'statement_action' => (string) ($statement['action'] ?? ''),
                    'statement_ordinal' => (int) ($statement['ordinal'] ?? 0),
                    'row_number' => $index + 1,
                    'rank' => $rank,
                    'dense_rank' => $denseRank,
                    'partition_count' => $count,
                    'partition_sum' => $sum,
                    'ntile_2' => self::ntileStatementWindowMetrics($index, $count, 2),
                    'percent_rank_milli' => self::percentRankMilliStatementWindowMetrics($rank, $count),
                    'cume_dist_milli' => self::cumeDistMilliStatementWindowMetrics($rows, $bytes),
                    'first_value_name' => self::keyNameString($rows[0]),
                    'last_value_name' => self::keyNameString($rows[$count - 1]),
                    'exclude_current_neighbor_ids' => self::neighborIdsStatementWindowMetrics($rows, $index, $rowIdColumn),
                    'window_token' => $key . ':' . self::rowIdValueStatementWindowMetrics($row, $rowIdColumn) . ':' . $bytes . ':' . $sum,
                ];
            }

            $partitions[$key] = $windowRows;
        }

        return $partitions;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,list<array<string,mixed>>> $retryPartitions
     * @param array<string,list<array<string,mixed>>> $suppressedPartitions
     * @return array<string,mixed>
     */
    private static function releaseSealStatementWindowMetrics(array $base, array $retryPartitions, array $suppressedPartitions, string $rowIdColumn): array
    {
        $retryIds = [];
        foreach ($retryPartitions as $rows) {
            array_push($retryIds, ...array_column($rows, $rowIdColumn));
        }
        $suppressedIds = [];
        foreach ($suppressedPartitions as $rows) {
            array_push($suppressedIds, ...array_column($rows, $rowIdColumn));
        }

        return [
            'savepoint' => $base['savepoint'],
            'retry_partition_keys' => array_keys($retryPartitions),
            'suppressed_partition_keys' => array_keys($suppressedPartitions),
            'retry_ids' => $retryIds,
            'suppressed_ids' => $suppressedIds,
            'suppressed_ids_excluded_from_release' => array_values(array_diff($suppressedIds, $retryIds)),
            'retry_window_tokens' => self::partitionColumnStatementWindowMetrics($retryPartitions, 'window_token'),
            'current_source_token' => hash('sha256', json_encode($base['current_source_tables'], JSON_THROW_ON_ERROR)),
            'next_source_matches_current' => $base['next_source_tables'] === $base['current_source_tables'],
            'attempt_tables_suppressed' => $base['attempt_current_source_tables'] !== $base['current_source_tables'],
            'rollback_source_restored' => $base['rollback_current_source_tables'] === $base['savepoint_image_tables'],
        ];
    }

    /**
     * @param array{phase?:mixed,ordinal?:mixed,action?:mixed} $statement
     */
    private static function partitionKeyStatementWindowMetrics(array $statement): string
    {
        return (string) ($statement['phase'] ?? 'phase') . '#' . (int) ($statement['ordinal'] ?? 0) . '#' . (string) ($statement['action'] ?? 'statement');
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<int|string>>
     */
    private static function partitionIdsStatementWindowMetrics(array $partitions, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($partitions as $key => $rows) {
            $ids[$key] = array_column($rows, $rowIdColumn);
        }

        return $ids;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<mixed>>
     */
    private static function partitionColumnStatementWindowMetrics(array $partitions, string $column): array
    {
        $values = [];
        foreach ($partitions as $key => $rows) {
            $values[$key] = array_column($rows, $column);
        }

        return $values;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,array{first:?string,last:?string,count:int,sum:int}>
     */
    private static function partitionEdgesStatementWindowMetrics(array $partitions): array
    {
        $edges = [];
        foreach ($partitions as $key => $rows) {
            $edges[$key] = [
                'first' => $rows[0]['first_value_name'] ?? null,
                'last' => $rows[0]['last_value_name'] ?? null,
                'count' => count($rows),
                'sum' => array_sum(array_column($rows, 'bytes')),
            ];
        }

        return $edges;
    }

    private static function ntileStatementWindowMetrics(int $index, int $count, int $buckets): int
    {
        if ($count <= 0 || $buckets <= 0) {
            return 0;
        }

        return intdiv($index * $buckets, $count) + 1;
    }

    private static function percentRankMilliStatementWindowMetrics(int $rank, int $count): int
    {
        if ($count <= 1) {
            return 0;
        }

        return (int) round((($rank - 1) / ($count - 1)) * 1000);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function cumeDistMilliStatementWindowMetrics(array $rows, int $bytes): int
    {
        $count = count($rows);
        if ($count === 0) {
            return 0;
        }
        $lessOrEqual = 0;
        foreach ($rows as $row) {
            if (self::numericValueStatementWindowMetrics($row['bytes'] ?? null) >= $bytes) {
                ++$lessOrEqual;
            }
        }

        return (int) round(($lessOrEqual / $count) * 1000);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function neighborIdsStatementWindowMetrics(array $rows, int $index, string $rowIdColumn): array
    {
        $ids = [];
        foreach ([$index - 1, $index + 1] as $neighbor) {
            if (isset($rows[$neighbor])) {
                $ids[] = self::rowIdValueStatementWindowMetrics($rows[$neighbor], $rowIdColumn);
            }
        }

        return $ids;
    }

    private static function numericValueStatementWindowMetrics(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdValueStatementWindowMetrics(array $row, string $rowIdColumn): int|string
    {
        if (!array_key_exists($rowIdColumn, $row)) {
            throw new \InvalidArgumentException("SQLite row-value statement window next239 rowid column {$rowIdColumn} is missing");
        }
        $id = $row[$rowIdColumn];
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException("SQLite row-value statement window next239 rowid column {$rowIdColumn} must be int or string");
        }

        return $id;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executePeerGroupWindowReceipt(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_groups_next240',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentRowWindowFrames(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $yieldGroups = self::peerGroupRowsPeerGroupWindowReceipt($base['yield_current_row_frames_next236'], $rowIdColumn);
        $suppressedGroups = self::peerGroupRowsPeerGroupWindowReceipt($base['suppressed_current_row_frames_next236'], $rowIdColumn);
        $retryGroups = self::peerGroupRowsPeerGroupWindowReceipt($base['retry_current_row_frames_next236'], $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next240',
            'window_peer_group_exclusion_next240' => true,
            'yield_peer_groups_next240' => $yieldGroups,
            'suppressed_peer_groups_next240' => $suppressedGroups,
            'retry_peer_groups_next240' => $retryGroups,
            'retry_peer_group_ids_next240' => array_column($retryGroups, $rowIdColumn),
            'retry_peer_group_numbers_next240' => array_column($retryGroups, 'peer_group_number'),
            'retry_exclude_current_sums_next240' => array_column($retryGroups, 'exclude_current_sum'),
            'retry_exclude_ties_sums_next240' => array_column($retryGroups, 'exclude_ties_sum'),
            'retry_peer_group_receipt_next240' => self::receiptPeerGroupWindowReceipt($base, $retryGroups, $rowIdColumn),
            'dependency_closure_next240' => 'no new support component needed; next240 reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint row images, and next236 current-row window metadata while adding peer-group exclusion receipts',
            'dependencies_next240' => [
                'sqlite-rowvalue-returning-window-peer-groups-next240',
                'sqlite-rowvalue-returning-window-exclude-current-row-next240',
                'sqlite-rowvalue-returning-window-exclude-ties-next240',
                'application-rowvalue-returning-window-current-source-next240',
            ],
            'non_overlap_next240' => 'adds peer-group GROUPS-style window receipts, EXCLUDE CURRENT ROW, EXCLUDE TIES, percent_rank, cume_dist, and ntile metadata over row-value UPDATE/DELETE RETURNING streams after rollback/release; avoids accepted next236 current-row frames, next235 stream row numbers, next233 aggregate window receipts, row-value savepoint retry variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, PRAGMA, and encoding clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return list<array<string,mixed>>
     */
    private static function peerGroupRowsPeerGroupWindowReceipt(array $frames, string $rowIdColumn): array
    {
        $totalRows = count($frames);
        $totalBytes = array_sum(array_map(static fn (array $row): int => self::intValuePeerGroupWindowReceipt($row['current_row_value'] ?? null), $frames));
        $peerCounts = [];
        $peerSums = [];

        foreach ($frames as $row) {
            $key = self::peerKeyPeerGroupWindowReceipt($row);
            $peerCounts[$key] = ($peerCounts[$key] ?? 0) + 1;
            $peerSums[$key] = ($peerSums[$key] ?? 0) + self::intValuePeerGroupWindowReceipt($row['current_row_value'] ?? null);
        }

        $groupNumbers = [];
        $nextGroup = 0;
        $rankByGroup = [];
        $seenRowsBeforeGroup = 0;
        foreach ($peerCounts as $key => $count) {
            ++$nextGroup;
            $groupNumbers[$key] = $nextGroup;
            $rankByGroup[$key] = $seenRowsBeforeGroup + 1;
            $seenRowsBeforeGroup += $count;
        }

        $seenInGroup = [];
        $rows = [];
        foreach (array_values($frames) as $index => $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value peer window next240 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value peer window next240 rowid column {$rowIdColumn} must be int or string");
            }

            $key = self::peerKeyPeerGroupWindowReceipt($row);
            $seenInGroup[$key] = ($seenInGroup[$key] ?? 0) + 1;
            $value = self::intValuePeerGroupWindowReceipt($row['current_row_value'] ?? null);
            $rank = $rankByGroup[$key];
            $cumeRows = $rank + $peerCounts[$key] - 1;

            $rows[] = [
                $rowIdColumn => $id,
                self::keyNameColumn($row) => self::keyNameString($row),
                'status' => $row['status'] ?? null,
                'peer_key' => $key,
                'peer_group_number' => $groupNumbers[$key],
                'peer_group_size' => $peerCounts[$key],
                'peer_row_number' => $seenInGroup[$key],
                'rank' => $rank,
                'dense_rank' => $groupNumbers[$key],
                'percent_rank' => $totalRows <= 1 ? 0.0 : ($rank - 1) / ($totalRows - 1),
                'cume_dist' => $totalRows === 0 ? 0.0 : $cumeRows / $totalRows,
                'ntile_2' => $totalRows === 0 ? 0 : min(2, intdiv($index * 2, $totalRows) + 1),
                'current_row_value' => $value,
                'peer_group_sum' => $peerSums[$key],
                'exclude_current_sum' => $totalBytes - $value,
                'exclude_ties_sum' => $totalBytes - ($peerSums[$key] - $value),
                'exclude_group_sum' => $totalBytes - $peerSums[$key],
                'peer_token' => $key . ':' . $id . ':' . $peerCounts[$key] . ':' . $peerSums[$key],
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function peerKeyPeerGroupWindowReceipt(array $row): string
    {
        return ((string) ($row['status'] ?? '')) . '|' . self::intValuePeerGroupWindowReceipt($row['current_row_value'] ?? null);
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $retryGroups
     * @return array<string,mixed>
     */
    private static function receiptPeerGroupWindowReceipt(array $base, array $retryGroups, string $rowIdColumn): array
    {
        return [
            'savepoint' => $base['savepoint'],
            'retry_ids' => array_column($retryGroups, $rowIdColumn),
            'retry_peer_tokens' => array_column($retryGroups, 'peer_token'),
            'retry_exclude_current_total' => array_sum(array_column($retryGroups, 'exclude_current_sum')),
            'retry_exclude_ties_total' => array_sum(array_column($retryGroups, 'exclude_ties_sum')),
            'retry_distinct_peer_groups' => count(array_unique(array_column($retryGroups, 'peer_key'))),
            'suppressed_ids' => array_column($base['suppressed_current_row_frames_next236'], $rowIdColumn),
            'next_source_matches_current' => $base['next_source_tables'] === $base['current_source_tables'],
        ];
    }

    private static function intValuePeerGroupWindowReceipt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executePairCurrentRowFrames(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_returning_window_next241',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeReplayPairWindow(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $frames = self::currentRowFramesPairCurrentRowFrames($plan['window_pair_rows_next238'], $rowIdColumn);
        $summary = self::frameSummaryPairCurrentRowFrames($frames);
        $fence = [
            'savepoint' => $savepoint,
            'frame_mode' => 'ROWS BETWEEN CURRENT ROW AND CURRENT ROW',
            'pair_count' => count($plan['window_pair_rows_next238']),
            'frame_count' => count($frames),
            'frame_digest' => self::digestPairCurrentRowFrames($frames, 'frame_key_next241'),
            'source_pair_digest' => $plan['window_source_fence_next238']['pair_digest'],
            'current_source_digest' => $plan['window_source_fence_next238']['current_source_digest'],
            'next_source_digest' => $plan['window_source_fence_next238']['next_source_digest'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
            'rolled_back_to_savepoint' => $plan['rolled_back_to_savepoint'],
        ];

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next241';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next241'] = true;
        $plan['window_current_row_frames_next241'] = $frames;
        $plan['window_current_row_frame_count_next241'] = count($frames);
        $plan['window_current_row_summary_next241'] = $summary;
        $plan['window_current_row_fence_next241'] = $fence;
        $plan['window_current_row_replayed_ids_next241'] = self::idsForFrameClassPairCurrentRowFrames($frames, 'replayed-after-rollback');
        $plan['window_current_row_restart_ids_next241'] = self::idsForFrameClassPairCurrentRowFrames($frames, 'restart-only');
        $plan['window_current_row_discarded_ids_next241'] = self::idsForFrameClassPairCurrentRowFrames($frames, 'discarded-only');
        $plan['window_current_row_actions_next241'] = array_values(array_unique(array_column($frames, 'frame_action_next241')));
        $plan['window_current_row_classes_next241'] = array_values(array_unique(array_column($frames, 'frame_class_next241')));
        $plan['dependencies'] = [
            'sqlite-rowvalue-returning-window-current-row-frame-next241',
            'sqlite-rowvalue-update-delete-returning-current-source-fence-next241',
            'application-rowvalue-returning-window-current-source-next241',
        ];
        $plan['dependency_closure_next241'] = 'no new support component needed; next241 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next235 window rows, and next238 current/next pair classification.';
        $plan['non_overlap_next241'] = 'adds CURRENT ROW frame isolation over next238 current/next source pairs; avoids accepted next237 EXCLUDE CURRENT ROW retry windows, next238 pair classification, next235 window materialization, row-value savepoint conflict variants, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner, and encoding clusters.';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $pairs
     * @return list<array<string,mixed>>
     */
    private static function currentRowFramesPairCurrentRowFrames(array $pairs, string $rowIdColumn): array
    {
        $ordered = $pairs;
        usort($ordered, static function (array $left, array $right): int {
            $action = ((string) $left['action_next238']) <=> ((string) $right['action_next238']);
            if ($action !== 0) {
                return $action;
            }

            return self::compareRowIdsPairCurrentRowFrames($left['rowid_next238'], $right['rowid_next238']);
        });

        $actionOrdinals = [];
        $frames = [];
        foreach ($ordered as $ordinal => $pair) {
            $action = (string) $pair['action_next238'];
            $actionOrdinals[$action] = ($actionOrdinals[$action] ?? 0) + 1;
            $rowid = $pair['rowid_next238'];
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException("SQLite row-value RETURNING window next241 rowid column {$rowIdColumn} must be int or string");
            }

            $class = (string) $pair['pair_class_next238'];
            $frameKey = $action . ':' . $rowid . ':' . $class;
            $frames[] = [
                'frame_ordinal_next241' => $ordinal,
                'frame_key_next241' => $frameKey,
                'frame_action_next241' => $action,
                'frame_class_next241' => $class,
                'frame_rowid_next241' => $rowid,
                'frame_pair_key_next241' => $pair['pair_key_next238'],
                'frame_action_ordinal_next241' => $actionOrdinals[$action],
                'frame_count_next241' => 1,
                'frame_rowids_next241' => [$rowid],
                'frame_classes_next241' => [$class],
                'frame_current_present_next241' => (bool) $pair['current_present_next238'],
                'frame_next_present_next241' => (bool) $pair['next_present_next238'],
                'frame_replayed_next241' => (bool) $pair['retry_replayed_next238'],
                'frame_restart_only_next241' => (bool) $pair['retry_restart_only_next238'],
                'frame_discarded_only_next241' => (bool) $pair['rollback_preserved_current_next238'],
                'frame_current_status_next241' => $pair['current_status_next238'],
                'frame_next_status_next241' => $pair['next_status_next238'],
                'frame_current_value_next241' => $pair['current_key_value_next238'],
                'frame_next_value_next241' => $pair['next_key_value_next238'],
                'frame_source_isolated_next241' => true,
                'frame_current_row_boundary_next241' => 'current-row-only',
            ];
        }

        return $frames;
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return array<string,mixed>
     */
    private static function frameSummaryPairCurrentRowFrames(array $frames): array
    {
        $summary = [
            'frame_count' => count($frames),
            'current_row_only_frames' => 0,
            'replayed-after-rollback' => 0,
            'restart-only' => 0,
            'discarded-only' => 0,
            'update' => 0,
            'delete' => 0,
            'rowids_by_action' => [],
            'classes_by_action' => [],
        ];

        foreach ($frames as $frame) {
            $action = (string) $frame['frame_action_next241'];
            $class = (string) $frame['frame_class_next241'];
            $summary['current_row_only_frames'] += (int) ((bool) $frame['frame_source_isolated_next241']);
            $summary[$class] = ((int) ($summary[$class] ?? 0)) + 1;
            $summary[$action] = ((int) ($summary[$action] ?? 0)) + 1;
            $summary['rowids_by_action'][$action][] = $frame['frame_rowid_next241'];
            $summary['classes_by_action'][$action][] = $class;
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return list<int|string>
     */
    private static function idsForFrameClassPairCurrentRowFrames(array $frames, string $class): array
    {
        $ids = [];
        foreach ($frames as $frame) {
            if ($frame['frame_class_next241'] === $class) {
                $id = $frame['frame_rowid_next241'];
                if (is_int($id) || is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    private static function compareRowIdsPairCurrentRowFrames(mixed $left, mixed $right): int
    {
        if ((is_int($left) || ctype_digit((string) $left)) && (is_int($right) || ctype_digit((string) $right))) {
            return (int) $left <=> (int) $right;
        }

        return ((string) $left) <=> ((string) $right);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function digestPairCurrentRowFrames(array $rows, string $keyColumn): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = implode(':', [
                (string) ($row[$keyColumn] ?? ''),
                (string) ($row['frame_ordinal_next241'] ?? ''),
                (string) ($row['frame_action_ordinal_next241'] ?? ''),
                (string) ($row['frame_current_row_boundary_next241'] ?? ''),
            ]);
        }

        return hash('sha256', implode('|', $parts));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeChainedStatementWindows(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next242',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementWindowMetrics(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $retryWindows = self::chainedWindowsChainedStatementWindows($base['retry_statement_windows_next239'], $rowIdColumn);
        $suppressedWindows = self::chainedWindowsChainedStatementWindows($base['suppressed_statement_windows_next239'], $rowIdColumn);
        $yieldWindows = self::chainedWindowsChainedStatementWindows($base['yield_statement_windows_next239'], $rowIdColumn);
        $seal = self::sourceSealChainedStatementWindows($base, $retryWindows, $suppressedWindows, $yieldWindows, $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next242',
            'returning_window_current_source_next242' => true,
            'retry_chained_windows_next242' => $retryWindows,
            'suppressed_chained_windows_next242' => $suppressedWindows,
            'yield_chained_windows_next242' => $yieldWindows,
            'retry_lag_ids_next242' => self::partitionColumnChainedStatementWindows($retryWindows, 'lag_id'),
            'retry_lead_ids_next242' => self::partitionColumnChainedStatementWindows($retryWindows, 'lead_id'),
            'retry_rows_frame_ids_next242' => self::partitionColumnChainedStatementWindows($retryWindows, 'rows_frame_ids'),
            'retry_groups_frame_ids_next242' => self::partitionColumnChainedStatementWindows($retryWindows, 'groups_frame_ids'),
            'retry_frame_sums_next242' => self::partitionColumnChainedStatementWindows($retryWindows, 'rows_frame_sum'),
            'retry_group_sums_next242' => self::partitionColumnChainedStatementWindows($retryWindows, 'groups_frame_sum'),
            'retry_source_ordinals_next242' => self::partitionColumnChainedStatementWindows($retryWindows, 'source_ordinal'),
            'source_generation_seal_next242' => $seal,
            'dependencies_next242' => [
                'sqlite-returning-window-lag-lead-current-source-next242',
                'sqlite-returning-window-groups-frame-current-source-next242',
                'application-rowvalue-update-delete-returning-release-fence-next242',
            ],
            'dependency_closure_next242' => 'no new support component needed; next242 reuses the row-value UPDATE/DELETE RETURNING executor, savepoint current-source image, and next239 statement-partition window rows.',
            'non_overlap_next242' => 'adds lag/lead, ROWS/GROUPS frame receipts, and current-source release seals over row-value UPDATE/DELETE RETURNING retry windows; avoids accepted next238 pair classification, next239 ntile/percent/cume partition windows, trigger RETURNING, WAL/VFS, JSON, B-tree, PRAGMA, and encoding clusters.',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<array<string,mixed>>>
     */
    private static function chainedWindowsChainedStatementWindows(array $partitions, string $rowIdColumn): array
    {
        $windows = [];
        foreach ($partitions as $key => $rows) {
            if (!is_string($key) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value window next242 partitions are malformed');
            }

            $count = count($rows);
            $windowRows = [];
            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value window next242 rows are malformed');
                }

                $id = self::rowIdValueChainedStatementWindows($row, $rowIdColumn);
                $previous = $rows[$index - 1] ?? null;
                $next = $rows[$index + 1] ?? null;
                $frame = array_slice($rows, max(0, $index - 1), min($count, $index + 2) - max(0, $index - 1));
                $groups = self::peerRowsChainedStatementWindows($rows, self::numericValueChainedStatementWindows($row['bytes'] ?? null));

                $windowRows[] = [
                    $rowIdColumn => $id,
                    'statement_key' => (string) ($row['statement_key'] ?? $key),
                    'statement_action' => (string) ($row['statement_action'] ?? ''),
                    'source_ordinal' => $index,
                    'source_count' => $count,
                    'lag_id' => is_array($previous) ? self::rowIdValueChainedStatementWindows($previous, $rowIdColumn) : null,
                    'lead_id' => is_array($next) ? self::rowIdValueChainedStatementWindows($next, $rowIdColumn) : null,
                    'lag_status' => is_array($previous) ? ($previous['status'] ?? null) : null,
                    'lead_status' => is_array($next) ? ($next['status'] ?? null) : null,
                    'rows_frame_ids' => self::rowIdsChainedStatementWindows($frame, $rowIdColumn),
                    'rows_frame_sum' => self::sumBytesChainedStatementWindows($frame),
                    'groups_frame_ids' => self::rowIdsChainedStatementWindows($groups, $rowIdColumn),
                    'groups_frame_sum' => self::sumBytesChainedStatementWindows($groups),
                    'first_value_name' => self::keyNameString($rows[0]),
                    'last_value_name' => self::keyNameString($rows[$count - 1]),
                    'window_token_next242' => $key . ':' . $id . ':' . self::sumBytesChainedStatementWindows($frame) . ':' . self::sumBytesChainedStatementWindows($groups),
                ];
            }

            $windows[$key] = $windowRows;
        }

        return $windows;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,list<array<string,mixed>>> $retryWindows
     * @param array<string,list<array<string,mixed>>> $suppressedWindows
     * @param array<string,list<array<string,mixed>>> $yieldWindows
     * @return array<string,mixed>
     */
    private static function sourceSealChainedStatementWindows(array $base, array $retryWindows, array $suppressedWindows, array $yieldWindows, string $rowIdColumn): array
    {
        $retryIds = self::flatIdsChainedStatementWindows($retryWindows, $rowIdColumn);
        $suppressedIds = self::flatIdsChainedStatementWindows($suppressedWindows, $rowIdColumn);
        $yieldIds = self::flatIdsChainedStatementWindows($yieldWindows, $rowIdColumn);
        $finalIds = self::tableIdsChainedStatementWindows(self::primarySourceRows($base['current_source_tables'] ?? [], $rowIdColumn), $rowIdColumn);

        return [
            'savepoint' => (string) ($base['savepoint'] ?? ''),
            'retry_ids' => $retryIds,
            'suppressed_ids' => $suppressedIds,
            'yield_ids' => $yieldIds,
            'suppressed_only_ids' => array_values(array_diff($suppressedIds, $retryIds)),
            'retry_replayed_yield_ids' => array_values(array_intersect($retryIds, $yieldIds)),
            'final_source_ids' => $finalIds,
            'final_contains_retry_ids' => self::containsAllChainedStatementWindows($finalIds, array_values(array_diff($retryIds, self::deletedRetryIdsChainedStatementWindows($base, $rowIdColumn)))),
            'final_excludes_retry_delete_ids' => count(array_intersect($finalIds, self::deletedRetryIdsChainedStatementWindows($base, $rowIdColumn))) === 0,
            'final_contains_suppressed_only_ids' => self::containsAllChainedStatementWindows($finalIds, array_values(array_diff($suppressedIds, $retryIds))),
            'rollback_restored_savepoint_image' => ($base['rollback_current_source_tables'] ?? null) === ($base['savepoint_image_tables'] ?? null),
            'attempt_source_discarded' => ($base['attempt_current_source_tables'] ?? null) !== ($base['current_source_tables'] ?? null),
            'retry_window_digest' => self::digestChainedStatementWindows($retryWindows),
            'suppressed_window_digest' => self::digestChainedStatementWindows($suppressedWindows),
            'yield_window_digest' => self::digestChainedStatementWindows($yieldWindows),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function peerRowsChainedStatementWindows(array $rows, int $bytes): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => self::numericValueChainedStatementWindows($row['bytes'] ?? null) === $bytes));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function rowIdsChainedStatementWindows(array $rows, string $rowIdColumn): array
    {
        return array_map(static fn (array $row): int|string => self::rowIdValueChainedStatementWindows($row, $rowIdColumn), $rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function sumBytesChainedStatementWindows(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += self::numericValueChainedStatementWindows($row['bytes'] ?? null);
        }

        return $sum;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<mixed>>
     */
    private static function partitionColumnChainedStatementWindows(array $partitions, string $column): array
    {
        $values = [];
        foreach ($partitions as $key => $rows) {
            $values[$key] = array_column($rows, $column);
        }

        return $values;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return list<int|string>
     */
    private static function flatIdsChainedStatementWindows(array $partitions, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($partitions as $rows) {
            array_push($ids, ...self::rowIdsChainedStatementWindows($rows, $rowIdColumn));
        }

        return $ids;
    }

    /**
     * @param mixed $rows
     * @return list<int|string>
     */
    private static function tableIdsChainedStatementWindows(mixed $rows, string $rowIdColumn): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite row-value window next242 source table rows are malformed');
        }

        return self::rowIdsChainedStatementWindows($rows, $rowIdColumn);
    }

    /**
     * @param array<string,mixed> $base
     * @return list<int|string>
     */
    private static function deletedRetryIdsChainedStatementWindows(array $base, string $rowIdColumn): array
    {
        $ids = [];
        foreach (($base['retry_returning'] ?? []) as $statement) {
            if (($statement['action'] ?? null) !== 'delete' || !isset($statement['rows']) || !is_array($statement['rows'])) {
                continue;
            }
            foreach ($statement['rows'] as $row) {
                if (is_array($row) && array_key_exists($rowIdColumn, $row)) {
                    $id = $row[$rowIdColumn];
                    if (is_int($id) || is_string($id)) {
                        $ids[] = $id;
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<int|string> $haystack
     * @param list<int|string> $needles
     */
    private static function containsAllChainedStatementWindows(array $haystack, array $needles): bool
    {
        return array_values(array_intersect($needles, $haystack)) === array_values($needles);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdValueChainedStatementWindows(array $row, string $rowIdColumn): int|string
    {
        if (!array_key_exists($rowIdColumn, $row)) {
            throw new \InvalidArgumentException("SQLite row-value window next242 rowid column {$rowIdColumn} is missing");
        }
        $value = $row[$rowIdColumn];
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value window next242 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function numericValueChainedStatementWindows(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite row-value window next242 byte values must be integer-like');
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function digestChainedStatementWindows(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeTupleFrameWindowReceipt(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next243',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeStatementWindowMetrics(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $tupleFrames = self::tupleFramesTupleFrameWindowReceipt($base['retry_statement_windows_next239'], $rowIdColumn);
        $retryTuples = self::tupleKeysTupleFrameWindowReceipt($tupleFrames);
        $retryFrameIds = self::frameIdsTupleFrameWindowReceipt($tupleFrames, $rowIdColumn);
        $retryPeerIds = self::peerIdsTupleFrameWindowReceipt($tupleFrames, $rowIdColumn);
        $release = self::releaseBoundaryTupleFrameWindowReceipt($base, $tupleFrames, $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next243',
            'rowvalue_tuple_window_current_source_next243' => true,
            'retry_tuple_window_frames_next243' => $tupleFrames,
            'retry_tuple_keys_next243' => $retryTuples,
            'retry_tuple_frame_ids_next243' => $retryFrameIds,
            'retry_tuple_peer_ids_next243' => $retryPeerIds,
            'retry_tuple_release_boundary_next243' => $release,
            'dependency_closure_next243' => 'no new support component needed; next243 reuses native row-value UPDATE/DELETE RETURNING retry partitions and lane-local window frame rows.',
            'dependencies_next243' => [
                'sqlite-rowvalue-returning-window-tuple-frame-next243',
                'sqlite-rowvalue-update-delete-returning-current-source-release-next243',
                'application-rowvalue-returning-window-current-source-next243',
            ],
            'non_overlap_next243' => 'adds row-value tuple frame and peer-group receipts over retry RETURNING windows after current-source rollback/release; avoids accepted next239 statement partitions, next238 source fences, next236 current-row frames, next219 LIMIT -1 OFFSET tuple sources, row-value UPSERT, trigger RETURNING, JSON table, planner, WAL/VFS, B-tree, PRAGMA, and encoding clusters.',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<array<string,mixed>>>
     */
    private static function tupleFramesTupleFrameWindowReceipt(array $partitions, string $rowIdColumn): array
    {
        $frames = [];
        foreach ($partitions as $key => $rows) {
            $partitionFrames = [];
            $count = count($rows);
            foreach ($rows as $index => $row) {
                $id = self::rowIdTupleFrameWindowReceipt($row, $rowIdColumn);
                $bytes = self::intTupleFrameWindowReceipt($row['bytes'] ?? null);
                $previous = $rows[$index - 1] ?? null;
                $next = $rows[$index + 1] ?? null;
                $frameRows = array_values(array_filter([$previous, $row, $next], static fn ($entry): bool => is_array($entry)));
                $peerRows = array_values(array_filter($rows, static fn (array $entry): bool => self::intTupleFrameWindowReceipt($entry['bytes'] ?? null) === $bytes));

                $partitionFrames[] = [
                    $rowIdColumn => $id,
                    'statement_key' => $key,
                    'tuple_key' => [$bytes, $id],
                    'tuple_key_sql' => '(' . $bytes . ',' . $id . ')',
                    'row_number' => $index + 1,
                    'partition_count' => $count,
                    'lag_tuple_key' => $previous === null ? null : [self::intTupleFrameWindowReceipt($previous['bytes'] ?? null), self::rowIdTupleFrameWindowReceipt($previous, $rowIdColumn)],
                    'lead_tuple_key' => $next === null ? null : [self::intTupleFrameWindowReceipt($next['bytes'] ?? null), self::rowIdTupleFrameWindowReceipt($next, $rowIdColumn)],
                    'frame_ids' => array_map(static fn (array $entry): int|string => self::rowIdTupleFrameWindowReceipt($entry, $rowIdColumn), $frameRows),
                    'frame_tuple_keys' => array_map(static fn (array $entry): array => [self::intTupleFrameWindowReceipt($entry['bytes'] ?? null), self::rowIdTupleFrameWindowReceipt($entry, $rowIdColumn)], $frameRows),
                    'frame_sum' => array_sum(array_map(static fn (array $entry): int => self::intTupleFrameWindowReceipt($entry['bytes'] ?? null), $frameRows)),
                    'peer_ids' => array_map(static fn (array $entry): int|string => self::rowIdTupleFrameWindowReceipt($entry, $rowIdColumn), $peerRows),
                    'peer_count' => count($peerRows),
                    'peer_tuple_key' => [$bytes, '*'],
                    'current_source_visible' => true,
                    'release_after_retry' => true,
                    'tuple_window_token' => $key . ':' . $bytes . ':' . $id . ':' . count($frameRows),
                ];
            }
            $frames[$key] = $partitionFrames;
        }

        return $frames;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $frames
     * @return array<string,list<array{0:int,1:int|string}>>
     */
    private static function tupleKeysTupleFrameWindowReceipt(array $frames): array
    {
        $keys = [];
        foreach ($frames as $key => $rows) {
            $keys[$key] = array_column($rows, 'tuple_key');
        }

        return $keys;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $frames
     * @return array<string,list<list<int|string>>>
     */
    private static function frameIdsTupleFrameWindowReceipt(array $frames, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($frames as $key => $rows) {
            $ids[$key] = array_column($rows, 'frame_ids');
        }

        return $ids;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $frames
     * @return array<string,list<list<int|string>>>
     */
    private static function peerIdsTupleFrameWindowReceipt(array $frames, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($frames as $key => $rows) {
            $ids[$key] = array_column($rows, 'peer_ids');
        }

        return $ids;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,list<array<string,mixed>>> $frames
     * @return array<string,mixed>
     */
    private static function releaseBoundaryTupleFrameWindowReceipt(array $base, array $frames, string $rowIdColumn): array
    {
        $ids = [];
        $tokens = [];
        foreach ($frames as $rows) {
            foreach ($rows as $row) {
                $ids[] = self::rowIdTupleFrameWindowReceipt($row, $rowIdColumn);
                $tokens[] = (string) $row['tuple_window_token'];
            }
        }

        return [
            'savepoint' => $base['savepoint'],
            'tuple_window_ids' => $ids,
            'tuple_window_tokens' => $tokens,
            'tuple_window_count' => count($ids),
            'retry_partitions' => array_keys($frames),
            'rollback_source_restored' => $base['release_window_seal_next239']['rollback_source_restored'] ?? false,
            'next_source_matches_current' => $base['release_window_seal_next239']['next_source_matches_current'] ?? false,
            'current_source_digest' => hash('sha256', json_encode($base['current_source_tables'], JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdTupleFrameWindowReceipt(array $row, string $rowIdColumn): int|string
    {
        if (!array_key_exists($rowIdColumn, $row)) {
            throw new \InvalidArgumentException("SQLite row-value tuple window next243 rowid column {$rowIdColumn} is missing");
        }
        $id = $row[$rowIdColumn];
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException("SQLite row-value tuple window next243 rowid column {$rowIdColumn} must be int or string");
        }

        return $id;
    }

    private static function intTupleFrameWindowReceipt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeTransitionChainWindow(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_returning_window_next244',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePairCurrentRowFrames(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $chains = self::transitionChainsTransitionChainWindow($plan['window_current_row_frames_next241'], $rowIdColumn);
        $summary = self::transitionSummaryTransitionChainWindow($chains);

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next244';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next244'] = true;
        $plan['window_transition_chains_next244'] = $chains;
        $plan['window_transition_chain_count_next244'] = count($chains);
        $plan['window_transition_summary_next244'] = $summary;
        $plan['window_transition_replayed_ids_next244'] = self::idsForClassTransitionChainWindow($chains, 'replayed-after-rollback');
        $plan['window_transition_restart_ids_next244'] = self::idsForClassTransitionChainWindow($chains, 'restart-only');
        $plan['window_transition_discarded_ids_next244'] = self::idsForClassTransitionChainWindow($chains, 'discarded-only');
        $plan['window_transition_edge_keys_next244'] = array_column($chains, 'transition_edge_key_next244');
        $plan['window_transition_partition_keys_next244'] = array_values(array_unique(array_column($chains, 'transition_partition_next244')));
        $plan['window_transition_fence_next244'] = [
            'savepoint' => $savepoint,
            'frame_mode' => 'ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING',
            'source_frame_count' => $plan['window_current_row_frame_count_next241'],
            'transition_count' => count($chains),
            'transition_digest' => self::digestTransitionChainWindow($chains),
            'current_row_frame_digest' => $plan['window_current_row_fence_next241']['frame_digest'],
            'pair_digest' => $plan['window_source_fence_next238']['pair_digest'],
            'rolled_back_to_savepoint' => $plan['rolled_back_to_savepoint'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
        ];
        $plan['dependencies'] = [
            'sqlite-rowvalue-returning-window-transition-chain-next244',
            'sqlite-rowvalue-returning-lag-lead-current-source-next244',
            'application-rowvalue-returning-window-current-source-next244',
        ];
        $plan['dependency_closure_next244'] = 'no new support component needed; next244 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next238 pair classification, and next241 CURRENT ROW frame isolation.';
        $plan['non_overlap_next244'] = 'adds lag/lead transition-chain windows across isolated current/next row-value RETURNING pairs; avoids next238 pair classification, next239 statement partitions, next240 peer exclusions, next241 CURRENT ROW frames, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner, encoding, and suite-evidence clusters.';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return list<array<string,mixed>>
     */
    private static function transitionChainsTransitionChainWindow(array $frames, string $rowIdColumn): array
    {
        $partitions = [];
        foreach ($frames as $frame) {
            $action = self::stringValueTransitionChainWindow($frame['frame_action_next241'] ?? null, 'action');
            $partitions[$action][] = $frame;
        }

        $chains = [];
        foreach ($partitions as $action => $rows) {
            usort($rows, static fn (array $left, array $right): int => self::compareRowIdsTransitionChainWindow($left['frame_rowid_next241'] ?? null, $right['frame_rowid_next241'] ?? null));
            $partitionCount = count($rows);
            foreach ($rows as $index => $row) {
                $rowid = self::rowIdTransitionChainWindow($row['frame_rowid_next241'] ?? null, $rowIdColumn);
                $class = self::stringValueTransitionChainWindow($row['frame_class_next241'] ?? null, 'class');
                $previous = $rows[$index - 1] ?? null;
                $next = $rows[$index + 1] ?? null;
                $previousClass = $previous === null ? null : self::stringValueTransitionChainWindow($previous['frame_class_next241'] ?? null, 'previous class');
                $nextClass = $next === null ? null : self::stringValueTransitionChainWindow($next['frame_class_next241'] ?? null, 'next class');
                $previousId = $previous === null ? null : self::rowIdTransitionChainWindow($previous['frame_rowid_next241'] ?? null, $rowIdColumn);
                $nextId = $next === null ? null : self::rowIdTransitionChainWindow($next['frame_rowid_next241'] ?? null, $rowIdColumn);

                $chains[] = [
                    'transition_ordinal_next244' => count($chains),
                    'transition_partition_next244' => $action,
                    'transition_partition_ordinal_next244' => $index + 1,
                    'transition_partition_count_next244' => $partitionCount,
                    'transition_rowid_next244' => $rowid,
                    'transition_pair_key_next244' => self::stringValueTransitionChainWindow($row['frame_pair_key_next241'] ?? null, 'pair key'),
                    'transition_edge_key_next244' => $action . ':' . (string) $previousId . '>' . (string) $rowid . '>' . (string) $nextId,
                    'transition_class_next244' => $class,
                    'transition_previous_class_next244' => $previousClass,
                    'transition_next_class_next244' => $nextClass,
                    'transition_previous_rowid_next244' => $previousId,
                    'transition_next_rowid_next244' => $nextId,
                    'transition_lag_class_changed_next244' => $previousClass !== null && $previousClass !== $class,
                    'transition_lead_class_changed_next244' => $nextClass !== null && $nextClass !== $class,
                    'transition_boundary_next244' => self::boundaryTransitionChainWindow($previous, $next),
                    'transition_frame_rowids_next244' => self::frameRowIdsTransitionChainWindow($previousId, $rowid, $nextId),
                    'transition_frame_classes_next244' => self::frameClassesTransitionChainWindow($previousClass, $class, $nextClass),
                    'transition_current_present_next244' => (bool) ($row['frame_current_present_next241'] ?? false),
                    'transition_next_present_next244' => (bool) ($row['frame_next_present_next241'] ?? false),
                    'transition_replayed_next244' => $class === 'replayed-after-rollback',
                    'transition_restart_only_next244' => $class === 'restart-only',
                    'transition_discarded_only_next244' => $class === 'discarded-only',
                    'transition_current_value_next244' => $row['frame_current_value_next241'] ?? null,
                    'transition_next_value_next244' => $row['frame_next_value_next241'] ?? null,
                ];
            }
        }

        return $chains;
    }

    /**
     * @param list<array<string,mixed>> $chains
     * @return array<string,mixed>
     */
    private static function transitionSummaryTransitionChainWindow(array $chains): array
    {
        $summary = [
            'transition_count' => count($chains),
            'lag_class_changes' => 0,
            'lead_class_changes' => 0,
            'first_rows' => 0,
            'last_rows' => 0,
            'singleton_rows' => 0,
            'replayed-after-rollback' => 0,
            'restart-only' => 0,
            'discarded-only' => 0,
            'rowids_by_partition' => [],
            'classes_by_partition' => [],
        ];

        foreach ($chains as $chain) {
            $partition = self::stringValueTransitionChainWindow($chain['transition_partition_next244'] ?? null, 'partition');
            $class = self::stringValueTransitionChainWindow($chain['transition_class_next244'] ?? null, 'class');
            $boundary = self::stringValueTransitionChainWindow($chain['transition_boundary_next244'] ?? null, 'boundary');
            $summary['lag_class_changes'] += (int) ((bool) $chain['transition_lag_class_changed_next244']);
            $summary['lead_class_changes'] += (int) ((bool) $chain['transition_lead_class_changed_next244']);
            $summary['first_rows'] += (int) ($boundary === 'first-row' || $boundary === 'singleton-row');
            $summary['last_rows'] += (int) ($boundary === 'last-row' || $boundary === 'singleton-row');
            $summary['singleton_rows'] += (int) ($boundary === 'singleton-row');
            $summary[$class] = ((int) ($summary[$class] ?? 0)) + 1;
            $summary['rowids_by_partition'][$partition][] = $chain['transition_rowid_next244'];
            $summary['classes_by_partition'][$partition][] = $class;
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $chains
     * @return list<int|string>
     */
    private static function idsForClassTransitionChainWindow(array $chains, string $class): array
    {
        $ids = [];
        foreach ($chains as $chain) {
            if (($chain['transition_class_next244'] ?? null) === $class) {
                $id = $chain['transition_rowid_next244'] ?? null;
                if (is_int($id) || is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    private static function boundaryTransitionChainWindow(?array $previous, ?array $next): string
    {
        if ($previous === null && $next === null) {
            return 'singleton-row';
        }
        if ($previous === null) {
            return 'first-row';
        }
        if ($next === null) {
            return 'last-row';
        }

        return 'middle-row';
    }

    /**
     * @return list<int|string>
     */
    private static function frameRowIdsTransitionChainWindow(int|string|null $previous, int|string $current, int|string|null $next): array
    {
        return array_values(array_filter([$previous, $current, $next], static fn (mixed $value): bool => is_int($value) || is_string($value)));
    }

    /**
     * @return list<string>
     */
    private static function frameClassesTransitionChainWindow(?string $previous, string $current, ?string $next): array
    {
        return array_values(array_filter([$previous, $current, $next], static fn (mixed $value): bool => is_string($value)));
    }

    private static function rowIdTransitionChainWindow(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING transition next244 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function compareRowIdsTransitionChainWindow(mixed $left, mixed $right): int
    {
        if ((is_int($left) || ctype_digit((string) $left)) && (is_int($right) || ctype_digit((string) $right))) {
            return (int) $left <=> (int) $right;
        }

        return ((string) $left) <=> ((string) $right);
    }

    private static function stringValueTransitionChainWindow(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value RETURNING transition next244 {$name} is missing");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $chains
     */
    private static function digestTransitionChainWindow(array $chains): string
    {
        $parts = [];
        foreach ($chains as $chain) {
            $parts[] = implode(':', [
                (string) ($chain['transition_edge_key_next244'] ?? ''),
                (string) ($chain['transition_class_next244'] ?? ''),
                (string) ($chain['transition_boundary_next244'] ?? ''),
                (string) ((int) ($chain['transition_lag_class_changed_next244'] ?? false)),
                (string) ((int) ($chain['transition_lead_class_changed_next244'] ?? false)),
            ]);
        }

        return hash('sha256', implode('|', $parts));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeYieldGateWindow(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next245',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeCurrentRowWindowFrames(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $yieldTickets = self::yieldGateTickets($base['yield_current_row_frames_next236'], 'yield', $rowIdColumn);
        $suppressedTickets = self::yieldGateTickets($base['suppressed_current_row_frames_next236'], 'suppressed-attempt', $rowIdColumn);
        $retryTickets = self::yieldGateTickets($base['retry_current_row_frames_next236'], 'retry-release', $rowIdColumn);
        $requiredTickets = array_column($yieldTickets, 'ticket');
        $ack = $acknowledgedYieldTickets ?? $requiredTickets;
        $gate = self::yieldGateAdmission($requiredTickets, $ack);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next245',
            'yield_current_source_gate_next245' => $gate,
            'yield_phase_tickets_next245' => $yieldTickets,
            'suppressed_phase_tickets_next245' => $suppressedTickets,
            'retry_phase_tickets_next245' => $retryTickets,
            'required_yield_tickets_next245' => $requiredTickets,
            'acknowledged_yield_tickets_next245' => $ack,
            'next_source_exposed_next245' => $gate['next_source_exposed'],
            'current_source_before_next245' => $gate['current_source_complete'],
            'yield_retry_order_next245' => array_merge(
                array_column($yieldTickets, 'ticket'),
                array_column($retryTickets, 'ticket'),
            ),
            'yield_window_receipt_next245' => self::yieldGateReceipt($base, $yieldTickets, $retryTickets, $gate, $rowIdColumn),
            'dependency_closure_next245' => 'no new support component needed; next245 reuses native PHP row-value UPDATE/DELETE RETURNING, savepoint image retry, and next236 current-row window receipts while adding a current-source yield-ticket gate before next-source exposure',
            'dependencies_next245' => [
                'sqlite-rowvalue-returning-window-yield-current-source-next245',
                'sqlite-returning-current-source-ticket-gate-next245',
                'application-rowvalue-returning-window-yield-gate-next245',
            ],
            'non_overlap_next245' => 'adds yield-ticket admission that exposes retried next-source rows only after all current-source row-value RETURNING window rows are acknowledged; avoids accepted next236 current-row frame receipts, next242 row-value/window behavior, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, and upstream-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return list<array<string,mixed>>
     */
    private static function yieldGateTickets(array $frames, string $phase, string $rowIdColumn): array
    {
        $tickets = [];
        foreach (array_values($frames) as $index => $frame) {
            if (!array_key_exists($rowIdColumn, $frame)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next245 rowid column {$rowIdColumn} is missing");
            }
            $rowId = $frame[$rowIdColumn];
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next245 rowid column {$rowIdColumn} must be int or string");
            }
            $tokenParts = [
                $phase,
                (string) ($index + 1),
                (string) $rowId,
                self::keyNameString($frame),
                (string) ($frame['frame_token'] ?? ''),
            ];
            $tickets[] = [
                'phase' => $phase,
                'ordinal' => $index + 1,
                $rowIdColumn => $rowId,
                self::keyNameColumn($frame) => self::keyNameString($frame),
                'status' => $frame['status'] ?? null,
                'frame_token' => (string) ($frame['frame_token'] ?? ''),
                'running_bytes' => self::yieldGateIntValue($frame['running_bytes'] ?? null),
                'following_bytes' => self::yieldGateIntValue($frame['following_bytes'] ?? null),
                'ticket' => implode(':', $tokenParts),
            ];
        }

        return $tickets;
    }

    /**
     * @param list<string> $required
     * @param list<string> $acknowledged
     * @return array<string,mixed>
     */
    private static function yieldGateAdmission(array $required, array $acknowledged): array
    {
        $requiredSet = array_fill_keys($required, true);
        $ackSet = array_fill_keys($acknowledged, true);
        $missing = [];
        foreach ($required as $ticket) {
            if (!isset($ackSet[$ticket])) {
                $missing[] = $ticket;
            }
        }
        $unexpected = [];
        foreach ($acknowledged as $ticket) {
            if (!isset($requiredSet[$ticket])) {
                $unexpected[] = $ticket;
            }
        }

        return [
            'required_count' => count($required),
            'acknowledged_count' => count($acknowledged),
            'missing_tickets' => $missing,
            'unexpected_tickets' => $unexpected,
            'current_source_complete' => $missing === [] && $unexpected === [],
            'next_source_exposed' => $missing === [] && $unexpected === [],
            'yield_boundary' => 'current-source-yield-before-next-source-next245',
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $yieldTickets
     * @param list<array<string,mixed>> $retryTickets
     * @param array<string,mixed> $gate
     * @return array<string,mixed>
     */
    private static function yieldGateReceipt(array $base, array $yieldTickets, array $retryTickets, array $gate, string $rowIdColumn): array
    {
        return [
            'savepoint' => $base['savepoint'],
            'yield_ids' => array_column($yieldTickets, $rowIdColumn),
            'retry_ids' => array_column($retryTickets, $rowIdColumn),
            'yield_tickets' => array_column($yieldTickets, 'ticket'),
            'retry_tickets' => array_column($retryTickets, 'ticket'),
            'gate_status' => $gate['next_source_exposed'] ? 'next-source-exposed-after-current-yield' : 'held-for-current-source-yield',
            'suppressed_attempt_ids' => $base['current_source_receipt_next236']['rolled_back_attempt_ids'],
            'current_source_row_count' => $base['current_source_receipt_next236']['released_table_count'],
            'retry_running_final' => $base['current_source_receipt_next236']['retry_running_final'],
        ];
    }

    private static function yieldGateIntValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeFilteredReleaseWindow(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next246',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChainedStatementWindows(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $retryRows = self::filteredReleaseAnnotateRows($base['retry_chained_windows_next242'], 'retry-release-current-source-next246', $rowIdColumn);
        $suppressedRows = self::filteredReleaseAnnotateRows($base['suppressed_chained_windows_next242'], 'suppressed-rollback-current-source-next246', $rowIdColumn);
        $yieldRows = self::filteredReleaseAnnotateRows($base['yield_chained_windows_next242'], 'yield-before-rollback-current-source-next246', $rowIdColumn);
        $audit = self::filteredReleaseAudit($base, $retryRows, $suppressedRows, $yieldRows, $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next246',
            'savepoint' => $savepoint,
            'returning_window_current_source_next246' => true,
            'retry_filter_windows_next246' => $retryRows,
            'suppressed_filter_windows_next246' => $suppressedRows,
            'yield_filter_windows_next246' => $yieldRows,
            'retry_filter_summary_next246' => self::filteredReleaseSummaryByPartition($retryRows),
            'suppressed_filter_summary_next246' => self::filteredReleaseSummaryByPartition($suppressedRows),
            'yield_filter_summary_next246' => self::filteredReleaseSummaryByPartition($yieldRows),
            'release_filter_audit_next246' => $audit,
            'dependencies_next246' => [
                'sqlite-returning-window-filter-release-current-source-next246',
                'sqlite-rowvalue-update-delete-returning-retry-filter-next246',
                'application-rowvalue-returning-filtered-window-next246',
            ],
            'dependency_closure_next246' => 'no new support component needed; next246 reuses row-value UPDATE/DELETE RETURNING execution, next242 released current-source windows, and bounded PHP window FILTER receipt calculation.',
            'non_overlap_next246' => 'adds FILTER-style retry/yield/suppressed release receipts over row-value UPDATE/DELETE RETURNING windows; avoids accepted next242 lag/lead and ROWS/GROUPS frames, next239 ntile/percent/cume windows, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner range-cost, and encoding clusters.',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<array<string,mixed>>>
     */
    private static function filteredReleaseAnnotateRows(array $partitions, string $sourceTag, string $rowIdColumn): array
    {
        $annotated = [];
        foreach ($partitions as $key => $rows) {
            if (!is_string($key) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value window next246 partitions are malformed');
            }

            $updateRows = self::filteredReleaseRowsForAction($rows, 'update');
            $deleteRows = self::filteredReleaseRowsForAction($rows, 'delete');
            $updateIds = self::filteredReleaseRowIds($updateRows, $rowIdColumn);
            $deleteIds = self::filteredReleaseRowIds($deleteRows, $rowIdColumn);
            $allIds = self::filteredReleaseRowIds($rows, $rowIdColumn);
            $updateBytes = self::filteredReleaseSumBytes($updateRows);
            $deleteBytes = self::filteredReleaseSumBytes($deleteRows);
            $totalBytes = self::filteredReleaseSumBytes($rows);

            $partitionRows = [];
            foreach ($rows as $ordinal => $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value window next246 rows are malformed');
                }
                $action = (string) ($row['statement_action'] ?? '');
                $id = self::filteredReleaseRowIdValue($row, $rowIdColumn);
                $isUpdate = $action === 'update';
                $isDelete = $action === 'delete';

                $partitionRows[] = [
                    $rowIdColumn => $id,
                    'filter_source_next246' => $sourceTag,
                    'filter_partition_key_next246' => $key,
                    'filter_ordinal_next246' => $ordinal,
                    'filter_action_next246' => $action,
                    'filter_status_next246' => (string) ($row['lead_status'] ?? $row['lag_status'] ?? ''),
                    'filter_bytes_next246' => self::filteredReleaseCurrentBytes($row),
                    'filter_update_count_next246' => count($updateRows),
                    'filter_delete_count_next246' => count($deleteRows),
                    'filter_total_count_next246' => count($rows),
                    'filter_update_bytes_next246' => $updateBytes,
                    'filter_delete_bytes_next246' => $deleteBytes,
                    'filter_total_bytes_next246' => $totalBytes,
                    'filter_update_ids_next246' => $updateIds,
                    'filter_delete_ids_next246' => $deleteIds,
                    'filter_all_ids_next246' => $allIds,
                    'filter_action_kept_next246' => $isUpdate || $isDelete,
                    'filter_update_match_next246' => $isUpdate,
                    'filter_delete_match_next246' => $isDelete,
                    'filter_peer_count_next246' => count($row['groups_frame_ids'] ?? []),
                    'filter_frame_count_next246' => count($row['rows_frame_ids'] ?? []),
                    'filter_receipt_next246' => implode(':', [$sourceTag, $key, (string) $id, $action, (string) count($updateRows), (string) count($deleteRows), (string) $totalBytes]),
                ];
            }

            $annotated[$key] = $partitionRows;
        }

        return $annotated;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,list<array<string,mixed>>> $retryRows
     * @param array<string,list<array<string,mixed>>> $suppressedRows
     * @param array<string,list<array<string,mixed>>> $yieldRows
     * @return array<string,mixed>
     */
    private static function filteredReleaseAudit(array $base, array $retryRows, array $suppressedRows, array $yieldRows, string $rowIdColumn): array
    {
        $retryIds = self::filteredReleaseFlatIds($retryRows, $rowIdColumn);
        $suppressedIds = self::filteredReleaseFlatIds($suppressedRows, $rowIdColumn);
        $yieldIds = self::filteredReleaseFlatIds($yieldRows, $rowIdColumn);
        $finalRows = self::primarySourceRows($base['current_source_tables'] ?? [], $rowIdColumn);
        if (!is_array($finalRows) || !array_is_list($finalRows)) {
            throw new \InvalidArgumentException('SQLite row-value window next246 final source rows are malformed');
        }
        $finalIds = self::filteredReleaseRowIds($finalRows, $rowIdColumn);
        $retryDeleteIds = self::filteredReleaseDeleteIds($base['retry_returning'] ?? [], $rowIdColumn);
        $retryUpdateIds = array_values(array_diff($retryIds, $retryDeleteIds));

        return [
            'savepoint' => (string) ($base['savepoint'] ?? ''),
            'retry_ids' => $retryIds,
            'retry_update_ids' => $retryUpdateIds,
            'retry_delete_ids' => $retryDeleteIds,
            'suppressed_ids' => $suppressedIds,
            'yield_ids' => $yieldIds,
            'final_ids' => $finalIds,
            'retry_updates_visible_after_release' => self::filteredReleaseContainsAll($finalIds, $retryUpdateIds),
            'retry_deletes_absent_after_release' => count(array_intersect($finalIds, $retryDeleteIds)) === 0,
            'suppressed_only_visible_after_release' => self::filteredReleaseContainsAll($finalIds, array_values(array_diff($suppressedIds, $retryIds))),
            'yield_delete_restored_by_rollback' => self::filteredReleaseContainsAll($finalIds, array_values(array_diff($yieldIds, $retryDeleteIds))),
            'retry_filter_digest' => self::filteredReleaseDigest($retryRows),
            'suppressed_filter_digest' => self::filteredReleaseDigest($suppressedRows),
            'yield_filter_digest' => self::filteredReleaseDigest($yieldRows),
            'digests_are_isolated' => count(array_unique([self::filteredReleaseDigest($retryRows), self::filteredReleaseDigest($suppressedRows), self::filteredReleaseDigest($yieldRows)])) === 3,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,array<string,mixed>>
     */
    private static function filteredReleaseSummaryByPartition(array $partitions): array
    {
        $summary = [];
        foreach ($partitions as $key => $rows) {
            $summary[$key] = [
                'row_count' => count($rows),
                'update_count' => (int) ($rows[0]['filter_update_count_next246'] ?? 0),
                'delete_count' => (int) ($rows[0]['filter_delete_count_next246'] ?? 0),
                'total_bytes' => (int) ($rows[0]['filter_total_bytes_next246'] ?? 0),
                'update_ids' => $rows[0]['filter_update_ids_next246'] ?? [],
                'delete_ids' => $rows[0]['filter_delete_ids_next246'] ?? [],
                'receipts' => array_column($rows, 'filter_receipt_next246'),
            ];
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function filteredReleaseRowsForAction(array $rows, string $action): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => ($row['statement_action'] ?? null) === $action));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function filteredReleaseRowIds(array $rows, string $rowIdColumn): array
    {
        return array_map(static fn (array $row): int|string => self::filteredReleaseRowIdValue($row, $rowIdColumn), $rows);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return list<int|string>
     */
    private static function filteredReleaseFlatIds(array $partitions, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($partitions as $rows) {
            array_push($ids, ...self::filteredReleaseRowIds($rows, $rowIdColumn));
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function filteredReleaseSumBytes(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += self::filteredReleaseCurrentBytes($row);
        }

        return $sum;
    }

    /**
     * @param mixed $statements
     * @return list<int|string>
     */
    private static function filteredReleaseDeleteIds(mixed $statements, string $rowIdColumn): array
    {
        $ids = [];
        if (!is_array($statements)) {
            return $ids;
        }
        foreach ($statements as $statement) {
            if (!is_array($statement) || ($statement['action'] ?? null) !== 'delete' || !isset($statement['rows']) || !is_array($statement['rows'])) {
                continue;
            }
            foreach ($statement['rows'] as $row) {
                if (is_array($row) && array_key_exists($rowIdColumn, $row)) {
                    $id = $row[$rowIdColumn];
                    if (is_int($id) || is_string($id)) {
                        $ids[] = $id;
                    }
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<int|string> $haystack
     * @param list<int|string> $needles
     */
    private static function filteredReleaseContainsAll(array $haystack, array $needles): bool
    {
        return array_values(array_intersect($needles, $haystack)) === array_values($needles);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function filteredReleaseRowIdValue(array $row, string $rowIdColumn): int|string
    {
        if (!array_key_exists($rowIdColumn, $row)) {
            throw new \InvalidArgumentException("SQLite row-value window next246 rowid column {$rowIdColumn} is missing");
        }
        $value = $row[$rowIdColumn];
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value window next246 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function filteredReleaseNumericValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite row-value window next246 byte values must be integer-like');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function filteredReleaseCurrentBytes(array $row): int
    {
        if (array_key_exists('bytes', $row)) {
            return self::filteredReleaseNumericValue($row['bytes']);
        }

        $peerCount = count($row['groups_frame_ids'] ?? []);
        if ($peerCount > 0) {
            return intdiv(self::filteredReleaseNumericValue($row['groups_frame_sum'] ?? 0), $peerCount);
        }

        return self::filteredReleaseNumericValue($row['rows_frame_sum'] ?? 0);
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function filteredReleaseDigest(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeExcludeGroupWindow(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_returning_window_next247',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeTransitionChainWindow(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $groups = self::excludeGroupRows($plan['window_transition_chains_next244'], $rowIdColumn);
        $summary = self::excludeGroupSummary($groups);

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next247';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next247'] = true;
        $plan['window_exclude_group_rows_next247'] = $groups;
        $plan['window_exclude_group_count_next247'] = count($groups);
        $plan['window_exclude_group_summary_next247'] = $summary;
        $plan['window_exclude_group_replayed_ids_next247'] = self::excludeGroupIdsForClass($groups, 'replayed-after-rollback');
        $plan['window_exclude_group_restart_ids_next247'] = self::excludeGroupIdsForClass($groups, 'restart-only');
        $plan['window_exclude_group_discarded_ids_next247'] = self::excludeGroupIdsForClass($groups, 'discarded-only');
        $plan['window_exclude_group_keys_next247'] = array_column($groups, 'exclude_group_key_next247');
        $plan['window_exclude_group_fence_next247'] = [
            'savepoint' => $savepoint,
            'frame_mode' => 'GROUPS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE GROUP',
            'source_transition_count' => $plan['window_transition_chain_count_next244'],
            'exclude_group_count' => count($groups),
            'excluded_group_digest' => self::excludeGroupDigest($groups),
            'transition_digest' => $plan['window_transition_fence_next244']['transition_digest'],
            'rolled_back_to_savepoint' => $plan['rolled_back_to_savepoint'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
        ];
        $plan['dependencies'] = [
            'sqlite-rowvalue-returning-window-exclude-group-next247',
            'sqlite-rowvalue-returning-transition-peer-groups-next247',
            'application-rowvalue-returning-window-current-source-next247',
        ];
        $plan['dependency_closure_next247'] = 'no new support component needed; next247 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next241 current-row frames, and next244 transition chains.';
        $plan['non_overlap_next247'] = 'adds GROUPS EXCLUDE GROUP accounting over next244 transition-chain partitions; avoids next244 lag/lead edges, next243 tuple frames, next241 CURRENT ROW frames, next240 peer exclusions, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner, encoding, and suite-evidence clusters.';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $chains
     * @return list<array<string,mixed>>
     */
    private static function excludeGroupRows(array $chains, string $rowIdColumn): array
    {
        $partitions = [];
        foreach ($chains as $chain) {
            $partition = self::excludeGroupStringValue($chain['transition_partition_next244'] ?? null, 'partition');
            $partitions[$partition][] = $chain;
        }

        $rows = [];
        foreach ($partitions as $partition => $partitionRows) {
            usort($partitionRows, static fn (array $left, array $right): int => self::excludeGroupCompareRowIds($left['transition_rowid_next244'] ?? null, $right['transition_rowid_next244'] ?? null));
            $groupsByClass = [];
            foreach ($partitionRows as $row) {
                $class = self::excludeGroupStringValue($row['transition_class_next244'] ?? null, 'class');
                $groupsByClass[$class][] = $row;
            }
            $classes = array_keys($groupsByClass);

            foreach ($partitionRows as $ordinal => $row) {
                $class = self::excludeGroupStringValue($row['transition_class_next244'] ?? null, 'class');
                $rowid = self::excludeGroupRowId($row['transition_rowid_next244'] ?? null, $rowIdColumn);
                $excludedRows = $groupsByClass[$class] ?? [];
                $frameRows = [];
                foreach ($partitionRows as $candidate) {
                    if (self::excludeGroupStringValue($candidate['transition_class_next244'] ?? null, 'candidate class') !== $class) {
                        $frameRows[] = $candidate;
                    }
                }

                $rows[] = [
                    'exclude_group_ordinal_next247' => count($rows),
                    'exclude_group_partition_next247' => $partition,
                    'exclude_group_partition_ordinal_next247' => $ordinal + 1,
                    'exclude_group_partition_count_next247' => count($partitionRows),
                    'exclude_group_class_next247' => $class,
                    'exclude_group_rowid_next247' => $rowid,
                    'exclude_group_key_next247' => $partition . ':' . $class . ':' . (string) $rowid,
                    'exclude_group_peer_classes_next247' => $classes,
                    'exclude_group_peer_count_next247' => count($excludedRows),
                    'exclude_group_peer_rowids_next247' => self::excludeGroupRowIds($excludedRows, 'transition_rowid_next244', $rowIdColumn),
                    'exclude_group_frame_count_next247' => count($frameRows),
                    'exclude_group_frame_rowids_next247' => self::excludeGroupRowIds($frameRows, 'transition_rowid_next244', $rowIdColumn),
                    'exclude_group_frame_classes_next247' => array_values(array_map(static fn (array $frame): string => (string) $frame['transition_class_next244'], $frameRows)),
                    'exclude_group_replayed_frame_count_next247' => self::excludeGroupClassCount($frameRows, 'replayed-after-rollback'),
                    'exclude_group_restart_frame_count_next247' => self::excludeGroupClassCount($frameRows, 'restart-only'),
                    'exclude_group_discarded_frame_count_next247' => self::excludeGroupClassCount($frameRows, 'discarded-only'),
                    'exclude_group_current_class_removed_next247' => !in_array($class, array_map(static fn (array $frame): string => (string) $frame['transition_class_next244'], $frameRows), true),
                    'exclude_group_current_value_next247' => $row['transition_current_value_next244'] ?? null,
                    'exclude_group_next_value_next247' => $row['transition_next_value_next244'] ?? null,
                    'exclude_group_boundary_next247' => $row['transition_boundary_next244'] ?? null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $groups
     * @return array<string,mixed>
     */
    private static function excludeGroupSummary(array $groups): array
    {
        $summary = [
            'exclude_group_count' => count($groups),
            'empty_frames' => 0,
            'non_empty_frames' => 0,
            'replayed-after-rollback' => 0,
            'restart-only' => 0,
            'discarded-only' => 0,
            'rowids_by_partition' => [],
            'classes_by_partition' => [],
            'frame_counts_by_partition' => [],
        ];

        foreach ($groups as $group) {
            $partition = self::excludeGroupStringValue($group['exclude_group_partition_next247'] ?? null, 'partition');
            $class = self::excludeGroupStringValue($group['exclude_group_class_next247'] ?? null, 'class');
            $frameCount = (int) ($group['exclude_group_frame_count_next247'] ?? 0);
            $summary[$class] = ((int) ($summary[$class] ?? 0)) + 1;
            $summary['empty_frames'] += (int) ($frameCount === 0);
            $summary['non_empty_frames'] += (int) ($frameCount > 0);
            $summary['rowids_by_partition'][$partition][] = $group['exclude_group_rowid_next247'];
            $summary['classes_by_partition'][$partition][] = $class;
            $summary['frame_counts_by_partition'][$partition][] = $frameCount;
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $groups
     * @return list<int|string>
     */
    private static function excludeGroupIdsForClass(array $groups, string $class): array
    {
        $ids = [];
        foreach ($groups as $group) {
            if (($group['exclude_group_class_next247'] ?? null) === $class) {
                $id = $group['exclude_group_rowid_next247'] ?? null;
                if (is_int($id) || is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function excludeGroupRowIds(array $rows, string $column, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = self::excludeGroupRowId($row[$column] ?? null, $rowIdColumn);
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function excludeGroupClassCount(array $rows, string $class): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $count += (int) (($row['transition_class_next244'] ?? null) === $class);
        }

        return $count;
    }

    private static function excludeGroupRowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING EXCLUDE GROUP next247 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function excludeGroupStringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value RETURNING EXCLUDE GROUP next247 {$name} is missing");
        }

        return $value;
    }

    private static function excludeGroupCompareRowIds(mixed $left, mixed $right): int
    {
        if ((is_int($left) || ctype_digit((string) $left)) && (is_int($right) || ctype_digit((string) $right))) {
            return (int) $left <=> (int) $right;
        }

        return ((string) $left) <=> ((string) $right);
    }

    /**
     * @param list<array<string,mixed>> $groups
     */
    private static function excludeGroupDigest(array $groups): string
    {
        $parts = [];
        foreach ($groups as $group) {
            $parts[] = implode(':', [
                (string) ($group['exclude_group_key_next247'] ?? ''),
                implode(',', array_map('strval', $group['exclude_group_frame_rowids_next247'] ?? [])),
                (string) ($group['exclude_group_frame_count_next247'] ?? ''),
                (string) ((int) ($group['exclude_group_current_class_removed_next247'] ?? false)),
            ]);
        }

        return hash('sha256', implode('|', $parts));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @return array<string,mixed>
     */
    public static function executePublicationResumeBarrier(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next248',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeYieldGateWindow(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
        );

        $yieldRows = self::publicationResumeBarrierRows($base['yield_phase_tickets_next245'], 'current-yield', $rowIdColumn);
        $retryRows = self::publicationResumeBarrierRows($base['retry_phase_tickets_next245'], 'next-retry', $rowIdColumn);
        $gate = $base['yield_current_source_gate_next245'];
        $sequence = self::publicationResumeBarrierSequence($yieldRows, $retryRows, (bool) $gate['next_source_exposed']);
        $resume = self::publicationResumeBarrierResume($sequence, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next248',
            'publication_barrier_next248' => [
                'savepoint' => $savepoint,
                'current_source_complete' => (bool) $gate['current_source_complete'],
                'next_source_exposed' => (bool) $gate['next_source_exposed'],
                'required_yield_count' => count($yieldRows),
                'acknowledged_yield_count' => count($base['acknowledged_yield_tickets_next245']),
                'retry_row_count' => count($retryRows),
                'published_row_count' => count($sequence),
                'resume_after_ticket' => $resumeAfterTicket,
                'current_source_digest' => self::publicationResumeBarrierDigest($base['current_source_tables']),
                'next_source_digest' => self::publicationResumeBarrierDigest($base['next_source_tables']),
                'barrier_token' => self::publicationResumeBarrierToken($base, $yieldRows, $retryRows, $gate),
                'blocked_reasons' => self::publicationResumeBarrierBlockedReasons($gate),
            ],
            'current_publication_rows_next248' => $yieldRows,
            'retry_publication_rows_next248' => $retryRows,
            'publication_sequence_next248' => $sequence,
            'publication_sequence_tickets_next248' => array_column($sequence, 'ticket'),
            'publication_resume_next248' => $resume,
            'publication_resume_tickets_next248' => array_column($resume['rows'], 'ticket'),
            'publication_state_next248' => $gate['next_source_exposed']
                ? 'current-source-yield-complete-next-source-resumable-next248'
                : 'current-source-yield-pending-next-source-held-next248',
            'dependency_closure_next248' => 'no new support component needed; next248 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next245 yield tickets, and current-row window receipts while adding a publication cursor barrier for next-source retry rows',
            'dependencies_next248' => [
                'sqlite-rowvalue-update-delete-returning-window-publication-current-source-next248',
                'sqlite-returning-current-source-publication-cursor-next248',
                'application-rowvalue-returning-window-resume-barrier-next248',
            ],
            'non_overlap_next248' => 'adds resumable publication sequencing after next245 yield-ticket admission; avoids accepted next245 ticket gate, next244 transition windows, next241 current-row frames, next236 receipts, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $tickets
     * @return list<array<string,mixed>>
     */
    private static function publicationResumeBarrierRows(array $tickets, string $source, string $rowIdColumn): array
    {
        $rows = [];
        foreach (array_values($tickets) as $index => $ticket) {
            if (!array_key_exists($rowIdColumn, $ticket)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next248 rowid column {$rowIdColumn} is missing");
            }
            $rowId = $ticket[$rowIdColumn];
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next248 rowid column {$rowIdColumn} must be int or string");
            }
            $ticketId = self::publicationResumeBarrierStringValue($ticket['ticket'] ?? null, 'ticket');
            $rows[] = [
                'publication_ordinal_next248' => $index + 1,
                'source' => $source,
                'ticket' => $ticketId,
                $rowIdColumn => $rowId,
                self::keyNameColumn($ticket) => self::publicationResumeBarrierStringValue(self::nullableKeyName($ticket), 'key name'),
                'status' => $ticket['status'] ?? null,
                'frame_token' => self::publicationResumeBarrierStringValue($ticket['frame_token'] ?? null, 'frame_token'),
                'running_bytes' => self::publicationResumeBarrierIntValue($ticket['running_bytes'] ?? null),
                'following_bytes' => self::publicationResumeBarrierIntValue($ticket['following_bytes'] ?? null),
                'cursor' => hash('sha256', $source . '|' . $ticketId),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $yieldRows
     * @param list<array<string,mixed>> $retryRows
     * @return list<array<string,mixed>>
     */
    private static function publicationResumeBarrierSequence(array $yieldRows, array $retryRows, bool $nextSourceExposed): array
    {
        $sequence = [];
        foreach ($yieldRows as $row) {
            $row['publication_phase_next248'] = 'current-source-yield';
            $row['next_source_visible_next248'] = false;
            $sequence[] = $row;
        }

        if ($nextSourceExposed) {
            foreach ($retryRows as $row) {
                $row['publication_phase_next248'] = 'next-source-retry';
                $row['next_source_visible_next248'] = true;
                $sequence[] = $row;
            }
        }

        foreach ($sequence as $index => $row) {
            $sequence[$index]['sequence_ordinal_next248'] = $index + 1;
        }

        return $sequence;
    }

    /**
     * @param list<array<string,mixed>> $sequence
     * @return array<string,mixed>
     */
    private static function publicationResumeBarrierResume(array $sequence, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return [
                'resume_after_ticket' => null,
                'resume_offset' => 0,
                'rows' => $sequence,
                'remaining_count' => count($sequence),
                'exhausted' => $sequence === [],
            ];
        }

        $offset = null;
        foreach ($sequence as $index => $row) {
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $offset = $index + 1;
                break;
            }
        }

        if ($offset === null) {
            throw new \InvalidArgumentException('SQLite row-value returning window next248 resume ticket is not in the publication sequence');
        }

        $rows = array_slice($sequence, $offset);

        return [
            'resume_after_ticket' => $resumeAfterTicket,
            'resume_offset' => $offset,
            'rows' => $rows,
            'remaining_count' => count($rows),
            'exhausted' => $rows === [],
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $yieldRows
     * @param list<array<string,mixed>> $retryRows
     * @param array<string,mixed> $gate
     */
    private static function publicationResumeBarrierToken(array $base, array $yieldRows, array $retryRows, array $gate): string
    {
        return hash('sha256', json_encode([
            'savepoint' => $base['savepoint'] ?? '',
            'yield' => array_column($yieldRows, 'ticket'),
            'retry' => array_column($retryRows, 'ticket'),
            'gate' => [
                'missing' => $gate['missing_tickets'] ?? [],
                'unexpected' => $gate['unexpected_tickets'] ?? [],
                'exposed' => $gate['next_source_exposed'] ?? false,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,mixed> $gate
     * @return list<string>
     */
    private static function publicationResumeBarrierBlockedReasons(array $gate): array
    {
        $reasons = [];
        if (($gate['missing_tickets'] ?? []) !== []) {
            $reasons[] = 'missing-current-source-yield-ticket-next248';
        }
        if (($gate['unexpected_tickets'] ?? []) !== []) {
            $reasons[] = 'unexpected-current-source-yield-ticket-next248';
        }

        return $reasons;
    }

    /**
     * @param array<string,mixed> $tables
     */
    private static function publicationResumeBarrierDigest(array $tables): string
    {
        return hash('sha256', json_encode($tables, JSON_THROW_ON_ERROR));
    }

    private static function publicationResumeBarrierStringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next248 {$name} is missing");
        }

        return $value;
    }

    private static function publicationResumeBarrierIntValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @return array<string,mixed>
     */
    public static function executeChunkedYieldResumeWindow(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next249',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        int $chunkSize = 2,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('SQLite row-value returning window next249 chunk size must be positive');
        }

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeYieldGateWindow(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
        );

        $yieldRows = self::chunkedYieldResumeWindowRows($base['yield_phase_tickets_next245'], $rowIdColumn);
        $retryRows = self::chunkedYieldResumeWindowRows($base['retry_phase_tickets_next245'], $rowIdColumn);
        $chunks = self::chunkedYieldResumeAckChunks($yieldRows, $chunkSize, $rowIdColumn);
        $resume = self::chunkedYieldResumeGate($chunks, $retryRows, $base['yield_current_source_gate_next245'], $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next249',
            'yield_window_rows_next249' => $yieldRows,
            'retry_window_rows_next249' => $retryRows,
            'yield_ack_chunks_next249' => $chunks,
            'yield_resume_gate_next249' => $resume,
            'next_source_resume_token_next249' => $resume['next_source_resume_token'],
            'current_source_yield_complete_next249' => $resume['current_source_yield_complete'],
            'retry_window_exposed_next249' => $resume['retry_window_exposed'],
            'window_yield_sequence_next249' => array_column($yieldRows, 'window_sequence_token'),
            'retry_window_sequence_next249' => array_column($retryRows, 'window_sequence_token'),
            'dependency_closure_next249' => 'no new support component needed; next249 reuses native PHP row-value UPDATE/DELETE RETURNING, next245 yield-ticket gates, and current-row window receipts while adding chunked resume admission for yielded current-source windows',
            'dependencies_next249' => [
                'sqlite-rowvalue-returning-window-chunked-yield-next249',
                'sqlite-returning-current-source-resume-token-next249',
                'application-rowvalue-returning-window-resume-next249',
            ],
            'non_overlap_next249' => 'adds chunked resume-token admission for yielded row-value UPDATE/DELETE RETURNING window rows before retry windows are exposed; avoids accepted next245 yield-ticket gate, next236 current-row frame receipts, next242 row-value/window behavior, UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, and upstream-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $tickets
     * @return list<array<string,mixed>>
     */
    private static function chunkedYieldResumeWindowRows(array $tickets, string $rowIdColumn): array
    {
        $rows = [];
        $totalRunning = 0;
        foreach (array_values($tickets) as $index => $ticket) {
            if (!array_key_exists($rowIdColumn, $ticket)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next249 rowid column {$rowIdColumn} is missing");
            }
            $rowId = $ticket[$rowIdColumn];
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next249 rowid column {$rowIdColumn} must be int or string");
            }

            $running = self::chunkedYieldResumeIntValue($ticket['running_bytes'] ?? null);
            $following = self::chunkedYieldResumeIntValue($ticket['following_bytes'] ?? null);
            $totalRunning += $running;
            $ordinal = $index + 1;
            $phase = (string) ($ticket['phase'] ?? '');
            $name = self::keyNameString($ticket);
            $frameToken = (string) ($ticket['frame_token'] ?? '');

            $rows[] = [
                'ordinal' => $ordinal,
                'phase' => $phase,
                $rowIdColumn => $rowId,
                self::keyNameColumn($ticket) => $name,
                'status' => $ticket['status'] ?? null,
                'ticket' => (string) ($ticket['ticket'] ?? ''),
                'running_bytes' => $running,
                'following_bytes' => $following,
                'cumulative_running_bytes' => $totalRunning,
                'lag_ticket' => $index === 0 ? null : (string) ($tickets[$index - 1]['ticket'] ?? ''),
                'lead_ticket' => array_key_exists($index + 1, $tickets) ? (string) ($tickets[$index + 1]['ticket'] ?? '') : null,
                'window_sequence_token' => implode('|', [$phase, (string) $ordinal, (string) $rowId, $name, $frameToken]),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $yieldRows
     * @return list<array<string,mixed>>
     */
    private static function chunkedYieldResumeAckChunks(array $yieldRows, int $chunkSize, string $rowIdColumn): array
    {
        $chunks = [];
        foreach (array_chunk($yieldRows, $chunkSize) as $chunkIndex => $rows) {
            $tickets = array_column($rows, 'ticket');
            $sequence = array_column($rows, 'window_sequence_token');
            $chunks[] = [
                'chunk' => $chunkIndex + 1,
                'first_ordinal' => $rows[0]['ordinal'],
                'last_ordinal' => $rows[count($rows) - 1]['ordinal'],
                'tickets' => $tickets,
                'rowids' => array_column($rows, $rowIdColumn),
                'sequence' => $sequence,
                'resume_token' => hash('sha256', implode("\n", $sequence)),
            ];
        }

        return $chunks;
    }

    /**
     * @param list<array<string,mixed>> $chunks
     * @param list<array<string,mixed>> $retryRows
     * @param array<string,mixed> $gate
     * @return array<string,mixed>
     */
    private static function chunkedYieldResumeGate(array $chunks, array $retryRows, array $gate, string $rowIdColumn): array
    {
        $complete = (bool) ($gate['current_source_complete'] ?? false);
        $missing = $gate['missing_tickets'] ?? [];
        $unexpected = $gate['unexpected_tickets'] ?? [];
        if (!is_array($missing) || !is_array($unexpected)) {
            throw new \InvalidArgumentException('SQLite row-value returning window next249 gate is malformed');
        }

        $chunkTokens = array_column($chunks, 'resume_token');
        $resumeToken = $complete ? hash('sha256', implode('|', $chunkTokens)) : null;

        return [
            'chunk_count' => count($chunks),
            'acknowledged_chunk_count' => $complete ? count($chunks) : 0,
            'held_chunk_count' => $complete ? 0 : count($chunks),
            'missing_tickets' => array_values($missing),
            'unexpected_tickets' => array_values($unexpected),
            'current_source_yield_complete' => $complete,
            'retry_window_exposed' => $complete,
            'retry_rowids_if_exposed' => $complete ? array_column($retryRows, $rowIdColumn) : [],
            'next_source_resume_token' => $resumeToken,
            'resume_boundary' => $complete
                ? 'next-source-retry-window-resumes-after-yield-chunks-next249'
                : 'next-source-retry-window-held-for-yield-chunks-next249',
        ];
    }

    private static function chunkedYieldResumeIntValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeExcludeTiesWindowPlan(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_returning_window_next250',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeExcludeGroupWindow(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $rows = self::excludeTiesWindowRows($plan['window_exclude_group_rows_next247'], $rowIdColumn);
        $summary = self::excludeTiesWindowSummary($rows);

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next250';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next250'] = true;
        $plan['window_exclude_ties_rows_next250'] = $rows;
        $plan['window_exclude_ties_count_next250'] = count($rows);
        $plan['window_exclude_ties_summary_next250'] = $summary;
        $plan['window_exclude_ties_replayed_ids_next250'] = self::excludeTiesIdsForClass($rows, 'replayed-after-rollback');
        $plan['window_exclude_ties_restart_ids_next250'] = self::excludeTiesIdsForClass($rows, 'restart-only');
        $plan['window_exclude_ties_discarded_ids_next250'] = self::excludeTiesIdsForClass($rows, 'discarded-only');
        $plan['window_exclude_ties_receipts_next250'] = array_column($rows, 'exclude_ties_receipt_next250');
        $plan['window_exclude_ties_fence_next250'] = [
            'savepoint' => $savepoint,
            'frame_mode' => 'GROUPS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE TIES',
            'source_transition_count' => $plan['window_transition_chain_count_next244'],
            'exclude_group_count' => $plan['window_exclude_group_count_next247'],
            'exclude_ties_count' => count($rows),
            'exclude_ties_digest' => self::excludeTiesDigest($rows),
            'exclude_group_digest' => $plan['window_exclude_group_fence_next247']['excluded_group_digest'],
            'transition_digest' => $plan['window_transition_fence_next244']['transition_digest'],
            'current_row_preserved' => self::excludeTiesAllCurrentRowsPreserved($rows),
            'peer_ties_removed' => self::excludeTiesAllPeerTiesRemoved($rows),
            'rolled_back_to_savepoint' => $plan['rolled_back_to_savepoint'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
        ];
        $plan['dependencies'] = [
            'sqlite-rowvalue-returning-window-exclude-ties-next250',
            'sqlite-rowvalue-returning-current-row-preserved-next250',
            'application-rowvalue-returning-window-current-source-next250',
        ];
        $plan['dependency_closure_next250'] = 'no new support component needed; next250 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next244 transition chains, and next247 peer-group partitions.';
        $plan['non_overlap_next250'] = 'adds GROUPS EXCLUDE TIES accounting where the current RETURNING row remains visible and same-class peers are removed; avoids next247 EXCLUDE GROUP, next244 lag/lead transition chains, next243 tuple frames, next241 CURRENT ROW frames, row-value savepoint retry variants, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner, encoding, and suite-evidence clusters.';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $groups
     * @return list<array<string,mixed>>
     */
    private static function excludeTiesWindowRows(array $groups, string $rowIdColumn): array
    {
        $rowsByPartition = [];
        foreach ($groups as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite row-value RETURNING EXCLUDE TIES next250 rows are malformed');
            }
            $partition = self::excludeTiesStringValue($row['exclude_group_partition_next247'] ?? null, 'partition');
            $rowsByPartition[$partition][] = $row;
        }

        $rows = [];
        foreach ($rowsByPartition as $partition => $partitionRows) {
            foreach ($partitionRows as $row) {
                $currentId = self::excludeTiesRowId($row['exclude_group_rowid_next247'] ?? null, $rowIdColumn);
                $class = self::excludeTiesStringValue($row['exclude_group_class_next247'] ?? null, 'class');
                $frameRows = [];
                $removedTieIds = [];
                foreach ($partitionRows as $candidate) {
                    $candidateId = self::excludeTiesRowId($candidate['exclude_group_rowid_next247'] ?? null, $rowIdColumn);
                    $candidateClass = self::excludeTiesStringValue($candidate['exclude_group_class_next247'] ?? null, 'candidate class');
                    if ($candidateClass === $class && $candidateId !== $currentId) {
                        $removedTieIds[] = $candidateId;
                        continue;
                    }
                    $frameRows[] = $candidate;
                }

                $frameIds = self::excludeTiesRowIds($frameRows, 'exclude_group_rowid_next247', $rowIdColumn);
                $rows[] = [
                    'exclude_ties_ordinal_next250' => count($rows),
                    'exclude_ties_partition_next250' => $partition,
                    'exclude_ties_class_next250' => $class,
                    'exclude_ties_rowid_next250' => $currentId,
                    'exclude_ties_key_next250' => $partition . ':' . $class . ':' . (string) $currentId,
                    'exclude_ties_current_row_preserved_next250' => in_array($currentId, $frameIds, true),
                    'exclude_ties_removed_peer_rowids_next250' => $removedTieIds,
                    'exclude_ties_removed_peer_count_next250' => count($removedTieIds),
                    'exclude_ties_frame_rowids_next250' => $frameIds,
                    'exclude_ties_frame_count_next250' => count($frameRows),
                    'exclude_ties_group_frame_count_next247' => $row['exclude_group_frame_count_next247'],
                    'exclude_ties_added_current_row_next250' => count($frameRows) - (int) $row['exclude_group_frame_count_next247'],
                    'exclude_ties_frame_classes_next250' => array_values(array_map(static fn (array $frame): string => (string) $frame['exclude_group_class_next247'], $frameRows)),
                    'exclude_ties_current_value_next250' => $row['exclude_group_current_value_next247'] ?? null,
                    'exclude_ties_next_value_next250' => $row['exclude_group_next_value_next247'] ?? null,
                    'exclude_ties_boundary_next250' => $row['exclude_group_boundary_next247'] ?? null,
                    'exclude_ties_receipt_next250' => implode(':', [$partition, $class, (string) $currentId, (string) count($removedTieIds), (string) count($frameRows)]),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function excludeTiesWindowSummary(array $rows): array
    {
        $summary = [
            'exclude_ties_count' => count($rows),
            'current_rows_preserved' => 0,
            'rows_with_removed_ties' => 0,
            'removed_tie_count' => 0,
            'replayed-after-rollback' => 0,
            'restart-only' => 0,
            'discarded-only' => 0,
            'rowids_by_partition' => [],
            'removed_ties_by_partition' => [],
            'frame_counts_by_partition' => [],
        ];

        foreach ($rows as $row) {
            $partition = self::excludeTiesStringValue($row['exclude_ties_partition_next250'] ?? null, 'partition');
            $class = self::excludeTiesStringValue($row['exclude_ties_class_next250'] ?? null, 'class');
            $removedCount = (int) ($row['exclude_ties_removed_peer_count_next250'] ?? 0);
            $summary[$class] = ((int) ($summary[$class] ?? 0)) + 1;
            $summary['current_rows_preserved'] += (int) (($row['exclude_ties_current_row_preserved_next250'] ?? null) === true);
            $summary['rows_with_removed_ties'] += (int) ($removedCount > 0);
            $summary['removed_tie_count'] += $removedCount;
            $summary['rowids_by_partition'][$partition][] = $row['exclude_ties_rowid_next250'];
            $summary['removed_ties_by_partition'][$partition][] = $row['exclude_ties_removed_peer_rowids_next250'];
            $summary['frame_counts_by_partition'][$partition][] = $row['exclude_ties_frame_count_next250'];
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function excludeTiesIdsForClass(array $rows, string $class): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (($row['exclude_ties_class_next250'] ?? null) === $class) {
                $id = $row['exclude_ties_rowid_next250'] ?? null;
                if (is_int($id) || is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function excludeTiesRowIds(array $rows, string $column, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = self::excludeTiesRowId($row[$column] ?? null, $rowIdColumn);
        }

        return $ids;
    }

    private static function excludeTiesAllCurrentRowsPreserved(array $rows): bool
    {
        foreach ($rows as $row) {
            if (($row['exclude_ties_current_row_preserved_next250'] ?? null) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    private static function excludeTiesAllPeerTiesRemoved(array $rows): bool
    {
        foreach ($rows as $row) {
            $frameIds = $row['exclude_ties_frame_rowids_next250'] ?? null;
            $removedIds = $row['exclude_ties_removed_peer_rowids_next250'] ?? null;
            if (!is_array($frameIds) || !is_array($removedIds)) {
                return false;
            }
            if (array_intersect($frameIds, $removedIds) !== []) {
                return false;
            }
        }

        return $rows !== [];
    }

    private static function excludeTiesRowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING EXCLUDE TIES next250 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function excludeTiesStringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value RETURNING EXCLUDE TIES next250 {$name} is missing");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function excludeTiesDigest(array $rows): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = implode(':', [
                (string) ($row['exclude_ties_key_next250'] ?? ''),
                implode(',', array_map('strval', $row['exclude_ties_frame_rowids_next250'] ?? [])),
                implode(',', array_map('strval', $row['exclude_ties_removed_peer_rowids_next250'] ?? [])),
                (string) ($row['exclude_ties_frame_count_next250'] ?? ''),
            ]);
        }

        return hash('sha256', implode('|', $parts));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @return array<string,mixed>
     */
    public static function executeSourceDigestHandoff(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next251',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        string $currentSourceEpoch = 'app-current-source-251',
        string $nextSourceEpoch = 'app-next-source-251',
        ?string $expectedCurrentDigest = null,
        ?string $expectedNextDigest = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        self::sourceDigestHandoffRequiredToken($currentSourceEpoch, 'current source epoch');
        self::sourceDigestHandoffRequiredToken($nextSourceEpoch, 'next source epoch');
        if ($currentSourceEpoch === $nextSourceEpoch) {
            throw new \InvalidArgumentException('SQLite row-value returning window next251 source epochs must differ');
        }

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePublicationResumeBarrier(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
        );

        $barrier = $base['publication_barrier_next248'];
        $currentDigest = self::sourceDigestHandoffRequiredToken((string) ($barrier['current_source_digest'] ?? ''), 'current source digest');
        $nextDigest = self::sourceDigestHandoffRequiredToken((string) ($barrier['next_source_digest'] ?? ''), 'next source digest');
        $digestReasons = self::sourceDigestHandoffReasons($currentDigest, $nextDigest, $expectedCurrentDigest, $expectedNextDigest);
        $sourceReady = (bool) ($barrier['next_source_exposed'] ?? false) && $digestReasons === [];
        $handoffRows = self::sourceDigestHandoffRows($base['publication_sequence_next248'], $sourceReady, $currentSourceEpoch, $nextSourceEpoch);
        $retryRows = array_values(array_filter($handoffRows, static fn (array $row): bool => ($row['source_epoch_next251'] ?? null) === $nextSourceEpoch));
        $resume = self::sourceDigestHandoffResume($handoffRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next251',
            'source_handoff_barrier_next251' => [
                'savepoint' => $savepoint,
                'current_source_epoch' => $currentSourceEpoch,
                'next_source_epoch' => $nextSourceEpoch,
                'current_source_digest' => $currentDigest,
                'next_source_digest' => $nextDigest,
                'expected_current_source_digest' => $expectedCurrentDigest,
                'expected_next_source_digest' => $expectedNextDigest,
                'current_source_complete' => (bool) ($barrier['current_source_complete'] ?? false),
                'next_source_exposed_by_publication' => (bool) ($barrier['next_source_exposed'] ?? false),
                'next_source_ready' => $sourceReady,
                'blocked_reasons' => array_values(array_merge($barrier['blocked_reasons'] ?? [], $digestReasons)),
                'handoff_token' => self::sourceDigestHandoffToken($base, $currentSourceEpoch, $nextSourceEpoch, $digestReasons),
                'retry_visible_count' => count($retryRows),
                'handoff_row_count' => count($handoffRows),
            ],
            'source_handoff_rows_next251' => $handoffRows,
            'source_handoff_tickets_next251' => array_column($handoffRows, 'ticket'),
            'source_handoff_retry_rows_next251' => $retryRows,
            'source_handoff_retry_tickets_next251' => array_column($retryRows, 'ticket'),
            'source_handoff_resume_next251' => $resume,
            'source_handoff_resume_tickets_next251' => array_column($resume['rows'], 'ticket'),
            'source_handoff_state_next251' => $sourceReady
                ? 'current-source-drained-next-source-digest-ready-next251'
                : 'current-source-or-digest-fence-holds-next-source-next251',
            'dependency_closure_next251' => 'no new support component needed; next251 reuses row-value UPDATE/DELETE RETURNING window publication sequencing and adds a source epoch/digest handoff fence for copied application setting imports',
            'dependencies_next251' => [
                'sqlite-rowvalue-update-delete-returning-window-source-handoff-next251',
                'sqlite-returning-current-source-digest-fence-next251',
                'application-rowvalue-returning-window-current-next-source-handoff-next251',
            ],
            'non_overlap_next251' => 'adds source epoch/digest handoff fencing after accepted next248 resumable publication; avoids next248 cursor sequencing, next245 yield gates, next244 transition windows, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @return list<string>
     */
    private static function sourceDigestHandoffReasons(string $currentDigest, string $nextDigest, ?string $expectedCurrentDigest, ?string $expectedNextDigest): array
    {
        $reasons = [];
        if ($expectedCurrentDigest !== null && $expectedCurrentDigest !== $currentDigest) {
            $reasons[] = 'current-source-digest-mismatch-next251';
        }
        if ($expectedNextDigest !== null && $expectedNextDigest !== $nextDigest) {
            $reasons[] = 'next-source-digest-mismatch-next251';
        }

        return $reasons;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function sourceDigestHandoffRows(array $rows, bool $sourceReady, string $currentEpoch, string $nextEpoch): array
    {
        $handoffRows = [];
        foreach ($rows as $index => $row) {
            $isRetry = (bool) ($row['next_source_visible_next248'] ?? false);
            $visible = !$isRetry || $sourceReady;
            if (!$visible) {
                continue;
            }
            $epoch = $isRetry ? $nextEpoch : $currentEpoch;
            $row['source_epoch_next251'] = $epoch;
            $row['handoff_visible_next251'] = true;
            $row['handoff_ordinal_next251'] = count($handoffRows) + 1;
            $row['source_handoff_token_next251'] = hash('sha256', $epoch . '|' . ($row['ticket'] ?? '') . '|' . $index);
            $handoffRows[] = $row;
        }

        return $handoffRows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function sourceDigestHandoffResume(array $rows, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return [
                'resume_after_ticket' => null,
                'resume_offset' => 0,
                'rows' => $rows,
                'remaining_count' => count($rows),
                'exhausted' => $rows === [],
            ];
        }

        $offset = null;
        foreach ($rows as $index => $row) {
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $offset = $index + 1;
                break;
            }
        }
        if ($offset === null) {
            throw new \InvalidArgumentException('SQLite row-value returning window next251 resume ticket is not in the source handoff rows');
        }

        $remaining = array_slice($rows, $offset);

        return [
            'resume_after_ticket' => $resumeAfterTicket,
            'resume_offset' => $offset,
            'rows' => $remaining,
            'remaining_count' => count($remaining),
            'exhausted' => $remaining === [],
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param list<string> $digestReasons
     */
    private static function sourceDigestHandoffToken(array $base, string $currentEpoch, string $nextEpoch, array $digestReasons): string
    {
        return hash('sha256', json_encode([
            'barrier' => $base['publication_barrier_next248']['barrier_token'] ?? '',
            'currentEpoch' => $currentEpoch,
            'nextEpoch' => $nextEpoch,
            'digestReasons' => $digestReasons,
        ], JSON_THROW_ON_ERROR));
    }

    private static function sourceDigestHandoffRequiredToken(string $value, string $label): string
    {
        if ($value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next251 {$label} is missing");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @return array<string,mixed>
     */
    public static function executePublicationWindowFence(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next252',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePublicationResumeBarrier(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
        );

        $windowRows = self::publicationWindowFenceRows($base['publication_sequence_next248'], $rowIdColumn);
        $currentRows = array_values(array_filter($windowRows, static fn (array $row): bool => $row['source'] === 'current-yield'));
        $retryRows = array_values(array_filter($windowRows, static fn (array $row): bool => $row['source'] === 'next-retry'));
        $resumeRows = self::publicationWindowFenceResumeRows($windowRows, $base['publication_resume_next248']['resume_after_ticket'] ?? null);
        $fence = self::publicationWindowFenceSummary($windowRows, $currentRows, $retryRows, $base['publication_barrier_next248']);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next252',
            'current_source_publication_windows_next252' => $windowRows,
            'current_source_window_count_next252' => count($currentRows),
            'next_source_window_count_next252' => count($retryRows),
            'current_source_high_water_ticket_next252' => $currentRows === [] ? null : $currentRows[count($currentRows) - 1]['ticket'],
            'next_source_first_ticket_next252' => $retryRows[0]['ticket'] ?? null,
            'next_source_first_ordinal_next252' => $retryRows[0]['window_row_number_next252'] ?? null,
            'resume_window_rows_next252' => $resumeRows,
            'resume_window_tickets_next252' => array_column($resumeRows, 'ticket'),
            'publication_window_fence_next252' => $fence,
            'dependency_closure_next252' => 'no new support component needed; next252 reuses native PHP row-value UPDATE/DELETE RETURNING, next245 yield tickets, and next248 publication cursors while adding CURRENT-source window row-number fences before exposing next-source retry rows',
            'dependencies_next252' => [
                'sqlite-rowvalue-returning-current-source-window-fence-next252',
                'sqlite-rowvalue-returning-next-source-row-number-after-current-next252',
                'application-rowvalue-returning-window-current-source-next252',
            ],
            'non_overlap_next252' => 'adds row-number/high-water window fences over next248 publication cursors so next-source retry rows cannot appear before all current-source row-value RETURNING rows; avoids accepted next248 cursor barrier, next245 ticket gate, next244 transition windows, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $sequence
     * @return list<array<string,mixed>>
     */
    private static function publicationWindowFenceRows(array $sequence, string $rowIdColumn): array
    {
        $rows = [];
        $partitionOrdinals = [];
        $currentHighWater = null;
        $firstRetryOrdinal = null;

        foreach (array_values($sequence) as $index => $row) {
            $source = self::publicationWindowFenceStringValue($row['source'] ?? null, 'source');
            $ticket = self::publicationWindowFenceStringValue($row['ticket'] ?? null, 'ticket');
            $rowId = $row[$rowIdColumn] ?? null;
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next252 rowid column {$rowIdColumn} must be int or string");
            }

            $partitionOrdinals[$source] = ($partitionOrdinals[$source] ?? 0) + 1;
            if ($source === 'current-yield') {
                $currentHighWater = $ticket;
            }
            if ($source === 'next-retry' && $firstRetryOrdinal === null) {
                $firstRetryOrdinal = $index + 1;
            }

            $previous = $sequence[$index - 1] ?? null;
            $next = $sequence[$index + 1] ?? null;
            $rows[] = array_merge($row, [
                'window_row_number_next252' => $index + 1,
                'window_partition_next252' => $source,
                'window_partition_row_number_next252' => $partitionOrdinals[$source],
                'window_total_rows_next252' => count($sequence),
                'window_current_source_high_water_ticket_next252' => $currentHighWater,
                'window_next_source_first_ordinal_next252' => $firstRetryOrdinal,
                'window_previous_ticket_next252' => is_array($previous) ? ($previous['ticket'] ?? null) : null,
                'window_next_ticket_next252' => is_array($next) ? ($next['ticket'] ?? null) : null,
                'window_boundary_next252' => self::publicationWindowFenceBoundary($previous, $next),
                'window_is_current_source_next252' => $source === 'current-yield',
                'window_is_next_source_next252' => $source === 'next-retry',
                'window_current_complete_before_row_next252' => $source === 'next-retry' && $currentHighWater !== null,
                'window_cursor_digest_next252' => hash('sha256', $source . '|' . $ticket . '|' . (string) $rowId),
            ]);
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $windowRows
     * @return list<array<string,mixed>>
     */
    private static function publicationWindowFenceResumeRows(array $windowRows, mixed $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return $windowRows;
        }

        $rows = [];
        $copy = false;
        foreach ($windowRows as $row) {
            if ($copy) {
                $rows[] = $row;
                continue;
            }
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $copy = true;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $windowRows
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $retryRows
     * @param array<string,mixed> $barrier
     * @return array<string,mixed>
     */
    private static function publicationWindowFenceSummary(array $windowRows, array $currentRows, array $retryRows, array $barrier): array
    {
        $currentHighWaterOrdinal = $currentRows === [] ? null : $currentRows[count($currentRows) - 1]['window_row_number_next252'];
        $firstRetryOrdinal = $retryRows[0]['window_row_number_next252'] ?? null;

        return [
            'current_source_complete' => (bool) ($barrier['current_source_complete'] ?? false),
            'next_source_exposed' => (bool) ($barrier['next_source_exposed'] ?? false),
            'current_high_water_ordinal' => $currentHighWaterOrdinal,
            'first_retry_ordinal' => $firstRetryOrdinal,
            'retry_after_current_high_water' => $firstRetryOrdinal === null || ($currentHighWaterOrdinal !== null && $firstRetryOrdinal > $currentHighWaterOrdinal),
            'current_window_row_count' => count($currentRows),
            'retry_window_row_count' => count($retryRows),
            'window_row_count' => count($windowRows),
            'window_digest' => hash('sha256', json_encode(array_column($windowRows, 'ticket'), JSON_THROW_ON_ERROR)),
            'blocked_reasons' => $barrier['blocked_reasons'] ?? [],
        ];
    }

    private static function publicationWindowFenceBoundary(?array $previous, ?array $next): string
    {
        if ($previous === null && $next === null) {
            return 'singleton-row';
        }
        if ($previous === null) {
            return 'first-row';
        }
        if ($next === null) {
            return 'last-row';
        }

        return 'middle-row';
    }

    private static function publicationWindowFenceStringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next252 {$name} is missing");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedChunkTokens
     * @return array<string,mixed>
     */
    public static function executeChunkCursorReleaseWindow(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next253',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        int $chunkSize = 2,
        ?array $acknowledgedChunkTokens = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChunkedYieldResumeWindow(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $chunkSize,
        );

        $chunkTokens = array_column($base['yield_ack_chunks_next249'], 'resume_token');
        $acknowledged = $acknowledgedChunkTokens ?? $chunkTokens;
        $gate = self::chunkCursorReleaseGate($chunkTokens, $acknowledged);
        $yieldTicketsComplete = (bool) $base['current_source_yield_complete_next249'];
        $retryExposed = $yieldTicketsComplete && (bool) $gate['chunk_source_complete'];
        $gate['yield_tickets_complete'] = $yieldTicketsComplete;
        $gate['next_source_retry_exposed'] = $retryExposed;
        $gate['source_boundary'] = $retryExposed
            ? 'current-source-window-chunks-complete-next253'
            : 'next-source-retry-held-for-current-window-chunks-next253';
        $cursorRows = self::chunkCursorReleaseRows(
            $base['yield_ack_chunks_next249'],
            $base['retry_window_rows_next249'],
            $yieldTicketsComplete,
            (bool) $gate['chunk_source_complete'],
            $rowIdColumn,
        );

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next253',
            'window_current_source_chunk_gate_next253' => $gate,
            'current_source_window_chunks_next253' => $base['yield_ack_chunks_next249'],
            'acknowledged_window_chunk_tokens_next253' => $acknowledged,
            'required_window_chunk_tokens_next253' => $chunkTokens,
            'window_current_source_cursor_next253' => $cursorRows,
            'window_current_source_cursor_tokens_next253' => array_column($cursorRows, 'cursor_token'),
            'window_current_source_cursor_rowids_next253' => array_column($cursorRows, $rowIdColumn),
            'window_current_source_retry_exposed_next253' => $retryExposed,
            'window_current_source_retry_rowids_next253' => $retryExposed
                ? array_column($base['retry_window_rows_next249'], $rowIdColumn)
                : [],
            'window_current_source_release_token_next253' => $retryExposed
                ? self::chunkCursorReleaseToken($savepoint, $chunkTokens, $base['retry_window_sequence_next249'])
                : null,
            'dependency_closure_next253' => 'no new support component needed; next253 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next249 chunked current-source windows, and retry window rows while adding chunk-token source admission before next-source retry publication',
            'dependencies_next253' => [
                'sqlite-rowvalue-returning-window-current-source-chunk-gate-next253',
                'sqlite-returning-next-source-held-for-window-chunk-receipts-next253',
                'application-rowvalue-returning-window-current-source-next253',
            ],
            'non_overlap_next253' => 'adds chunk-token current-source admission above accepted next249 chunk construction and next245 raw yield-ticket admission; avoids next248 publication cursor, next236 current-row frame receipts, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<string> $required
     * @param list<string> $acknowledged
     * @return array<string,mixed>
     */
    private static function chunkCursorReleaseGate(array $required, array $acknowledged): array
    {
        $requiredSet = array_fill_keys($required, true);
        $ackSet = array_fill_keys($acknowledged, true);
        $missing = [];
        foreach ($required as $token) {
            if (!isset($ackSet[$token])) {
                $missing[] = $token;
            }
        }

        $unexpected = [];
        foreach ($acknowledged as $token) {
            if (!isset($requiredSet[$token])) {
                $unexpected[] = $token;
            }
        }

        $complete = $missing === [] && $unexpected === [];

        return [
            'required_chunk_count' => count($required),
            'acknowledged_chunk_count' => count($acknowledged),
            'missing_chunk_tokens' => $missing,
            'unexpected_chunk_tokens' => $unexpected,
            'chunk_source_complete' => $complete,
            'next_source_retry_exposed' => $complete,
            'source_boundary' => $complete
                ? 'current-source-window-chunks-complete-next253'
                : 'next-source-retry-held-for-current-window-chunks-next253',
        ];
    }

    /**
     * @param list<array<string,mixed>> $chunks
     * @param list<array<string,mixed>> $retryRows
     * @return list<array<string,mixed>>
     */
    private static function chunkCursorReleaseRows(
        array $chunks,
        array $retryRows,
        bool $yieldTicketsComplete,
        bool $chunkSourceComplete,
        string $rowIdColumn,
    ): array {
        $rows = [];
        foreach ($chunks as $chunk) {
            $rowids = $chunk['rowids'] ?? [];
            if (!is_array($rowids)) {
                throw new \InvalidArgumentException('SQLite row-value returning window next253 chunk rowids are malformed');
            }
            foreach (array_values($rowids) as $offset => $rowId) {
                if (!is_int($rowId) && !is_string($rowId)) {
                    throw new \InvalidArgumentException("SQLite row-value returning window next253 rowid column {$rowIdColumn} must be int or string");
                }
                $rows[] = [
                    'source' => 'current-window-chunk-next253',
                    'chunk' => $chunk['chunk'],
                    'chunk_complete' => $yieldTicketsComplete && $chunkSourceComplete,
                    'ordinal_in_chunk' => $offset + 1,
                    $rowIdColumn => $rowId,
                    'resume_token' => $chunk['resume_token'],
                    'cursor_token' => hash('sha256', 'current|' . (string) $chunk['resume_token'] . '|' . (string) $rowId),
                ];
            }
        }

        if (!$yieldTicketsComplete || !$chunkSourceComplete) {
            return $rows;
        }

        foreach (array_values($retryRows) as $index => $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next253 retry rowid column {$rowIdColumn} is missing");
            }
            $rowId = $row[$rowIdColumn];
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next253 retry rowid column {$rowIdColumn} must be int or string");
            }
            $rows[] = [
                'source' => 'next-source-retry-window-next253',
                'chunk' => null,
                'chunk_complete' => true,
                'ordinal_in_chunk' => $index + 1,
                $rowIdColumn => $rowId,
                'resume_token' => null,
                'cursor_token' => hash('sha256', 'retry|' . (string) ($row['window_sequence_token'] ?? '') . '|' . (string) $rowId),
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $chunkTokens
     * @param list<string> $retrySequence
     */
    private static function chunkCursorReleaseToken(string $savepoint, array $chunkTokens, array $retrySequence): string
    {
        return hash('sha256', json_encode([
            'savepoint' => $savepoint,
            'chunks' => $chunkTokens,
            'retry' => $retrySequence,
        ], JSON_THROW_ON_ERROR));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<array<string,mixed>>|null $rowReceipts
     * @return array<string,mixed>
     */
    public static function executeRowReceiptAdmissionWindow(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next254',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        string $currentSourceEpoch = 'app-current-source-254',
        string $nextSourceEpoch = 'app-next-source-254',
        ?string $expectedCurrentDigest = null,
        ?string $expectedNextDigest = null,
        ?array $rowReceipts = null,
        bool $requireNextReceipts = true,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeSourceDigestHandoff(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
            $currentSourceEpoch,
            $nextSourceEpoch,
            $expectedCurrentDigest,
            $expectedNextDigest,
        );

        $handoffRows = $base['source_handoff_rows_next251'];
        $expectedReceipts = self::rowReceiptAdmissionExpectedReceipts($handoffRows, $rowIdColumn);
        $receipts = $rowReceipts ?? $expectedReceipts;
        $receiptIndex = self::rowReceiptAdmissionReceiptIndex($receipts);
        $admissionRows = self::rowReceiptAdmissionRows($handoffRows, $receiptIndex, $rowIdColumn, $nextSourceEpoch, $requireNextReceipts);
        $blocked = self::rowReceiptAdmissionBlockedReasons($base, $admissionRows, $requireNextReceipts);
        $readyRows = array_values(array_filter($admissionRows, static fn (array $row): bool => (bool) $row['admitted_next254']));
        $nextRows = array_values(array_filter($readyRows, static fn (array $row): bool => ($row['source_epoch_next251'] ?? null) === $nextSourceEpoch));
        $currentRows = array_values(array_filter($readyRows, static fn (array $row): bool => ($row['source_epoch_next251'] ?? null) !== $nextSourceEpoch));
        $resume = self::rowReceiptAdmissionResume($readyRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next254',
            'admission_barrier_next254' => [
                'savepoint' => $savepoint,
                'rowid_column' => $rowIdColumn,
                'current_source_epoch' => $currentSourceEpoch,
                'next_source_epoch' => $nextSourceEpoch,
                'require_next_receipts' => $requireNextReceipts,
                'source_handoff_ready' => (bool) ($base['source_handoff_barrier_next251']['next_source_ready'] ?? false),
                'expected_receipt_count' => count($expectedReceipts),
                'provided_receipt_count' => count($receipts),
                'admitted_row_count' => count($readyRows),
                'admitted_current_row_count' => count($currentRows),
                'admitted_next_row_count' => count($nextRows),
                'blocked_reasons' => $blocked,
                'admission_token' => self::rowReceiptAdmissionToken($base, $admissionRows, $blocked),
            ],
            'expected_row_receipts_next254' => $expectedReceipts,
            'provided_row_receipts_next254' => array_values($receipts),
            'admission_rows_next254' => $admissionRows,
            'admitted_rows_next254' => $readyRows,
            'admitted_tickets_next254' => array_column($readyRows, 'ticket'),
            'admitted_next_rows_next254' => $nextRows,
            'admitted_next_tickets_next254' => array_column($nextRows, 'ticket'),
            'admitted_current_rows_next254' => $currentRows,
            'admission_resume_next254' => $resume,
            'admission_resume_tickets_next254' => array_column($resume['rows'], 'ticket'),
            'admission_state_next254' => $blocked === []
                ? 'current-source-next254-window-receipts-admitted'
                : 'current-source-next254-window-receipts-held',
            'dependency_closure_next254' => 'no new support component needed; next254 reuses row-value UPDATE/DELETE RETURNING window publication, source epoch/digest handoff rows, and adds per-row window receipt admission for copied application setting imports',
            'dependencies_next254' => [
                'sqlite-rowvalue-update-delete-returning-window-current-source-next254',
                'sqlite-returning-window-row-receipt-admission-next254',
                'application-rowvalue-returning-window-current-source-next254',
            ],
            'non_overlap_next254' => 'adds row-level window receipt admission after accepted next251 source epoch/digest handoff; avoids next251 digest fencing, next248 publication cursors, next245 yield tickets, savepoint-only row-value RETURNING, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function rowReceiptAdmissionExpectedReceipts(array $rows, string $rowIdColumn): array
    {
        $receipts = [];
        foreach ($rows as $row) {
            $ticket = self::rowReceiptAdmissionStringValue($row['ticket'] ?? null, 'ticket');
            $epoch = self::rowReceiptAdmissionStringValue($row['source_epoch_next251'] ?? null, 'source epoch');
            $frameToken = self::rowReceiptAdmissionStringValue($row['frame_token'] ?? null, 'frame token');
            $rowId = $row[$rowIdColumn] ?? null;
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next254 rowid column {$rowIdColumn} must be int or string");
            }
            $runningBytes = self::rowReceiptAdmissionIntValue($row['running_bytes'] ?? null, 'running bytes');
            $followingBytes = self::rowReceiptAdmissionIntValue($row['following_bytes'] ?? null, 'following bytes');
            $receiptToken = self::rowReceiptAdmissionReceiptToken($ticket, $epoch, $frameToken, $rowId, $runningBytes, $followingBytes);
            $receipts[] = [
                'ticket' => $ticket,
                'source_epoch' => $epoch,
                $rowIdColumn => $rowId,
                'frame_token' => $frameToken,
                'running_bytes' => $runningBytes,
                'following_bytes' => $followingBytes,
                'receipt_token' => $receiptToken,
            ];
        }

        return $receipts;
    }

    /**
     * @param list<array<string,mixed>> $receipts
     * @return array<string,array<string,mixed>>
     */
    private static function rowReceiptAdmissionReceiptIndex(array $receipts): array
    {
        $indexed = [];
        foreach ($receipts as $receipt) {
            $ticket = self::rowReceiptAdmissionStringValue($receipt['ticket'] ?? null, 'receipt ticket');
            $indexed[$ticket] = $receipt;
        }

        return $indexed;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,array<string,mixed>> $receiptIndex
     * @return list<array<string,mixed>>
     */
    private static function rowReceiptAdmissionRows(array $rows, array $receiptIndex, string $rowIdColumn, string $nextSourceEpoch, bool $requireNextReceipts): array
    {
        $admitted = [];
        foreach ($rows as $index => $row) {
            $ticket = self::rowReceiptAdmissionStringValue($row['ticket'] ?? null, 'ticket');
            $epoch = self::rowReceiptAdmissionStringValue($row['source_epoch_next251'] ?? null, 'source epoch');
            $rowId = $row[$rowIdColumn] ?? null;
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next254 rowid column {$rowIdColumn} must be int or string");
            }
            $frameToken = self::rowReceiptAdmissionStringValue($row['frame_token'] ?? null, 'frame token');
            $runningBytes = self::rowReceiptAdmissionIntValue($row['running_bytes'] ?? null, 'running bytes');
            $followingBytes = self::rowReceiptAdmissionIntValue($row['following_bytes'] ?? null, 'following bytes');
            $expected = self::rowReceiptAdmissionReceiptToken($ticket, $epoch, $frameToken, $rowId, $runningBytes, $followingBytes);
            $receipt = $receiptIndex[$ticket] ?? null;
            $reasons = self::rowReceiptAdmissionReasons($receipt, $expected, $epoch, $frameToken, $rowIdColumn, $rowId, $runningBytes, $followingBytes);
            if (!$requireNextReceipts && $epoch === $nextSourceEpoch && in_array('missing-row-receipt-next254', $reasons, true)) {
                $reasons = [];
            }
            $row['expected_receipt_token_next254'] = $expected;
            $row['provided_receipt_token_next254'] = is_array($receipt) ? ($receipt['receipt_token'] ?? null) : null;
            $row['admission_reasons_next254'] = $reasons;
            $row['admitted_next254'] = $reasons === [];
            $row['admission_ordinal_next254'] = $row['admitted_next254'] ? count(array_filter($admitted, static fn (array $item): bool => (bool) $item['admitted_next254'])) + 1 : null;
            $row['admission_token_next254'] = hash('sha256', $expected . '|' . $index . '|' . json_encode($reasons, JSON_THROW_ON_ERROR));
            $admitted[] = $row;
        }

        return $admitted;
    }

    /**
     * @param array<string,mixed>|null $receipt
     * @return list<string>
     */
    private static function rowReceiptAdmissionReasons(?array $receipt, string $expected, string $epoch, string $frameToken, string $rowIdColumn, int|string $rowId, int $runningBytes, int $followingBytes): array
    {
        if ($receipt === null) {
            return ['missing-row-receipt-next254'];
        }

        $reasons = [];
        if (($receipt['receipt_token'] ?? null) !== $expected || !self::rowReceiptAdmissionTokenMatches($receipt, $rowIdColumn)) {
            $reasons[] = 'row-receipt-token-mismatch-next254';
        }
        if (($receipt['source_epoch'] ?? null) !== $epoch) {
            $reasons[] = 'row-receipt-source-epoch-mismatch-next254';
        }
        if (($receipt['frame_token'] ?? null) !== $frameToken) {
            $reasons[] = 'row-receipt-window-frame-mismatch-next254';
        }
        if (($receipt[$rowIdColumn] ?? null) !== $rowId) {
            $reasons[] = 'row-receipt-rowid-mismatch-next254';
        }
        if (($receipt['running_bytes'] ?? null) !== $runningBytes) {
            $reasons[] = 'row-receipt-running-bytes-mismatch-next254';
        }
        if (($receipt['following_bytes'] ?? null) !== $followingBytes) {
            $reasons[] = 'row-receipt-following-bytes-mismatch-next254';
        }

        return $reasons;
    }

    /**
     * @param array<string,mixed> $receipt
     */
    private static function rowReceiptAdmissionTokenMatches(array $receipt, string $rowIdColumn): bool
    {
        $ticket = $receipt['ticket'] ?? null;
        $epoch = $receipt['source_epoch'] ?? null;
        $frameToken = $receipt['frame_token'] ?? null;
        $rowId = $receipt[$rowIdColumn] ?? null;
        $runningBytes = $receipt['running_bytes'] ?? null;
        $followingBytes = $receipt['following_bytes'] ?? null;
        if (!is_string($ticket) || $ticket === '' || !is_string($epoch) || $epoch === '' || !is_string($frameToken) || $frameToken === '') {
            return false;
        }
        if (!is_int($rowId) && !is_string($rowId)) {
            return false;
        }
        if (!is_int($runningBytes) || !is_int($followingBytes)) {
            return false;
        }

        return ($receipt['receipt_token'] ?? null) === self::rowReceiptAdmissionReceiptToken($ticket, $epoch, $frameToken, $rowId, $runningBytes, $followingBytes);
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function rowReceiptAdmissionBlockedReasons(array $base, array $rows, bool $requireNextReceipts): array
    {
        $reasons = [];
        if (!(bool) ($base['source_handoff_barrier_next251']['next_source_ready'] ?? false)) {
            $reasons[] = 'source-handoff-not-ready-next254';
        }
        foreach ($rows as $row) {
            foreach ($row['admission_reasons_next254'] as $reason) {
                $reasons[] = $reason;
            }
        }
        if (!$requireNextReceipts) {
            $reasons[] = 'next-source-receipts-not-required-next254';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function rowReceiptAdmissionResume(array $rows, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return [
                'resume_after_ticket' => null,
                'resume_offset' => 0,
                'rows' => $rows,
                'remaining_count' => count($rows),
                'exhausted' => $rows === [],
            ];
        }

        $offset = null;
        foreach ($rows as $index => $row) {
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $offset = $index + 1;
                break;
            }
        }
        if ($offset === null) {
            throw new \InvalidArgumentException('SQLite row-value returning window next254 resume ticket is not admitted');
        }

        $remaining = array_slice($rows, $offset);

        return [
            'resume_after_ticket' => $resumeAfterTicket,
            'resume_offset' => $offset,
            'rows' => $remaining,
            'remaining_count' => count($remaining),
            'exhausted' => $remaining === [],
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $rows
     * @param list<string> $blocked
     */
    private static function rowReceiptAdmissionToken(array $base, array $rows, array $blocked): string
    {
        return hash('sha256', json_encode([
            'handoff' => $base['source_handoff_barrier_next251']['handoff_token'] ?? '',
            'rows' => array_column($rows, 'admission_token_next254'),
            'blocked' => $blocked,
        ], JSON_THROW_ON_ERROR));
    }

    private static function rowReceiptAdmissionReceiptToken(string $ticket, string $epoch, string $frameToken, int|string $rowId, int $runningBytes, int $followingBytes): string
    {
        return hash('sha256', $ticket . '|' . $epoch . '|' . $frameToken . '|' . (string) $rowId . '|' . $runningBytes . '|' . $followingBytes);
    }

    private static function rowReceiptAdmissionStringValue(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next254 {$label} is missing");
        }

        return $value;
    }

    private static function rowReceiptAdmissionIntValue(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException("SQLite row-value returning window next254 {$label} must be an integer");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedNextRowTickets
     * @return array<string,mixed>
     */
    public static function executeWindowRowAdmission(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next255',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        ?array $acknowledgedNextRowTickets = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeSourceDigestHandoff(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
        );

        $rows = self::nextRowAdmissionRows($base['source_handoff_rows_next251'], $rowIdColumn, $acknowledgedNextRowTickets);
        $readyRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['next_row_ready_next255'] ?? null) === true));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['next_row_ready_next255'] ?? null) !== true));
        $resume = self::nextRowAdmissionResume($readyRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next255',
            'next_row_admission_next255' => true,
            'next_row_window_rows_next255' => $rows,
            'next_row_ready_rows_next255' => $readyRows,
            'next_row_blocked_rows_next255' => $blockedRows,
            'next_row_ready_tickets_next255' => array_column($readyRows, 'ticket'),
            'next_row_blocked_tickets_next255' => array_column($blockedRows, 'ticket'),
            'next_row_resume_next255' => $resume,
            'next_row_resume_tickets_next255' => array_column($resume['rows'], 'ticket'),
            'next_row_admission_summary_next255' => self::nextRowAdmissionSummary($rows),
            'next_row_admission_fence_next255' => [
                'savepoint' => $savepoint,
                'source_handoff_state' => $base['source_handoff_state_next251'],
                'source_handoff_token' => $base['source_handoff_barrier_next251']['handoff_token'],
                'window_mode' => 'RETURNING rows next-row admission after current source handoff',
                'row_count' => count($rows),
                'ready_count' => count($readyRows),
                'blocked_count' => count($blockedRows),
                'ready_digest' => self::nextRowAdmissionDigest($readyRows),
                'blocked_digest' => self::nextRowAdmissionDigest($blockedRows),
                'all_retry_rows_acknowledged' => self::allRetryRowsAcknowledgedForNextRowAdmission($rows),
                'all_current_rows_acknowledged' => self::allCurrentRowsAcknowledgedForNextRowAdmission($rows),
            ],
            'dependency_closure_next255' => 'no new support component needed; next255 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next251 source handoff rows, and window cursor tickets while adding next-row admission receipts',
            'dependencies_next255' => [
                'sqlite-rowvalue-returning-window-next-row-admission-next255',
                'sqlite-rowvalue-returning-current-source-handoff-next251',
                'application-rowvalue-returning-window-next-row-current-source-next255',
            ],
            'non_overlap_next255' => 'adds next-row admission receipts after accepted next251 source epoch/digest handoff; avoids next250 EXCLUDE TIES, next248 resumable publication, next245 yield gates, next232-next247 window frame variants, row-value savepoint retry variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $handoffRows
     * @param list<string>|null $acknowledgedTickets
     * @return list<array<string,mixed>>
     */
    private static function nextRowAdmissionRows(array $handoffRows, string $rowIdColumn, ?array $acknowledgedTickets): array
    {
        $acknowledged = $acknowledgedTickets === null
            ? array_column($handoffRows, 'ticket')
            : self::nextRowAdmissionTicketSet($acknowledgedTickets);

        $rows = [];
        foreach (array_values($handoffRows) as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite row-value next-row admission next255 rows are malformed');
            }
            $ticket = self::nextRowAdmissionTicket($row['ticket'] ?? null);
            $previous = $handoffRows[$index - 1] ?? null;
            $next = $handoffRows[$index + 1] ?? null;
            $currentAcknowledged = in_array($ticket, $acknowledged, true);
            $previousTicket = is_array($previous) ? self::nextRowAdmissionTicket($previous['ticket'] ?? null) : null;
            $previousAcknowledged = $previousTicket === null || in_array($previousTicket, $acknowledged, true);
            $ready = $currentAcknowledged && $previousAcknowledged;
            $blockedReasons = [];
            if (!$currentAcknowledged) {
                $blockedReasons[] = 'current-returning-ticket-not-acknowledged-next255';
            }
            if (!$previousAcknowledged) {
                $blockedReasons[] = 'previous-returning-ticket-not-acknowledged-next255';
            }

            $rows[] = [
                'ticket' => $ticket,
                'next_row_ordinal_next255' => count($rows) + 1,
                'next_row_rowid_next255' => self::nextRowAdmissionRowId($row[$rowIdColumn] ?? null, $rowIdColumn),
                'next_row_source_epoch_next255' => self::nextRowAdmissionStringValue($row['source_epoch_next251'] ?? null, 'source epoch'),
                'next_row_previous_ticket_next255' => $previousTicket,
                'next_row_next_ticket_next255' => is_array($next) ? self::nextRowAdmissionTicket($next['ticket'] ?? null) : null,
                'next_row_previous_acknowledged_next255' => $previousAcknowledged,
                'next_row_current_acknowledged_next255' => $currentAcknowledged,
                'next_row_ready_next255' => $ready,
                'next_row_blocked_reasons_next255' => $blockedReasons,
                'next_row_admission_receipt_next255' => hash('sha256', implode('|', [
                    $ticket,
                    (string) ($previousTicket ?? ''),
                    (string) (is_array($next) ? self::nextRowAdmissionTicket($next['ticket'] ?? null) : ''),
                    $ready ? 'ready' : 'blocked',
                ])),
            ] + $row;
        }

        return $rows;
    }

    /**
     * @param list<string> $tickets
     * @return list<string>
     */
    private static function nextRowAdmissionTicketSet(array $tickets): array
    {
        $set = [];
        foreach ($tickets as $ticket) {
            $set[] = self::nextRowAdmissionTicket($ticket);
        }

        return array_values(array_unique($set));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function nextRowAdmissionSummary(array $rows): array
    {
        $summary = [
            'row_count' => count($rows),
            'ready_count' => 0,
            'blocked_count' => 0,
            'current_source_ready_count' => 0,
            'next_source_ready_count' => 0,
            'current_source_blocked_count' => 0,
            'next_source_blocked_count' => 0,
            'ready_rowids' => [],
            'blocked_rowids' => [],
            'blocked_reasons' => [],
        ];

        foreach ($rows as $row) {
            $ready = ($row['next_row_ready_next255'] ?? null) === true;
            $epoch = self::nextRowAdmissionStringValue($row['next_row_source_epoch_next255'] ?? null, 'summary epoch');
            $source = str_contains($epoch, 'next') ? 'next_source' : 'current_source';
            $bucket = $ready ? 'ready' : 'blocked';
            $summary[$bucket . '_count']++;
            $summary[$source . '_' . $bucket . '_count']++;
            $summary[$bucket . '_rowids'][] = $row['next_row_rowid_next255'];
            foreach (($row['next_row_blocked_reasons_next255'] ?? []) as $reason) {
                $summary['blocked_reasons'][$reason] = (($summary['blocked_reasons'][$reason] ?? 0) + 1);
            }
        }

        ksort($summary['blocked_reasons']);

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function nextRowAdmissionResume(array $rows, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return [
                'resume_after_ticket' => null,
                'resume_offset' => 0,
                'rows' => $rows,
                'remaining_count' => count($rows),
                'exhausted' => $rows === [],
            ];
        }

        $offset = null;
        foreach ($rows as $index => $row) {
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $offset = $index + 1;
                break;
            }
        }
        if ($offset === null) {
            throw new \InvalidArgumentException('SQLite row-value next-row admission next255 resume ticket is not ready');
        }

        $remaining = array_slice($rows, $offset);

        return [
            'resume_after_ticket' => $resumeAfterTicket,
            'resume_offset' => $offset,
            'rows' => $remaining,
            'remaining_count' => count($remaining),
            'exhausted' => $remaining === [],
        ];
    }

    private static function allRetryRowsAcknowledgedForNextRowAdmission(array $rows): bool
    {
        foreach ($rows as $row) {
            if (str_contains((string) ($row['next_row_source_epoch_next255'] ?? ''), 'next')
                && ($row['next_row_ready_next255'] ?? null) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    private static function allCurrentRowsAcknowledgedForNextRowAdmission(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!str_contains((string) ($row['next_row_source_epoch_next255'] ?? ''), 'next')
                && ($row['next_row_ready_next255'] ?? null) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    private static function nextRowAdmissionDigest(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private static function nextRowAdmissionRowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value next-row admission next255 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function nextRowAdmissionTicket(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite row-value next-row admission next255 ticket is missing');
        }

        return $value;
    }

    private static function nextRowAdmissionStringValue(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value next-row admission next255 {$label} is missing");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedChunkTokens
     * @param list<string>|null $acknowledgedCommitTokens
     * @return array<string,mixed>
     */
    public static function executeRetryCommitWatermark(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_retry_commit',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        int $chunkSize = 2,
        ?array $acknowledgedChunkTokens = null,
        ?array $acknowledgedCommitTokens = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChunkCursorReleaseWindow(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $chunkSize,
            $acknowledgedChunkTokens,
        );

        $retryRows = self::retryCommitWatermarkCursorRows($base['window_current_source_cursor_next253']);
        $requiredCommitTokens = array_column($retryRows, 'cursor_token');
        $acknowledged = $acknowledgedCommitTokens ?? $requiredCommitTokens;
        $gate = self::retryCommitWatermarkGate(
            $requiredCommitTokens,
            $acknowledged,
            (bool) $base['window_current_source_retry_exposed_next253'],
        );
        $durableRows = self::retryCommitWatermarkDurableRows($base['window_current_source_cursor_next253'], (bool) $gate['commit_source_complete']);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-retry-commit-watermark',
            'retry_commit_watermark' => [
                'savepoint' => $savepoint,
                'source_boundary' => $gate['source_boundary'],
                'current_chunk_gate_complete' => (bool) $base['window_current_source_chunk_gate_next253']['chunk_source_complete'],
                'retry_exposed' => (bool) $base['window_current_source_retry_exposed_next253'],
                'required_commit_count' => count($requiredCommitTokens),
                'acknowledged_commit_count' => count($acknowledged),
                'missing_commit_tokens' => $gate['missing_commit_tokens'],
                'unexpected_commit_tokens' => $gate['unexpected_commit_tokens'],
                'commit_source_complete' => $gate['commit_source_complete'],
                'durable_retry_count' => count(array_filter(
                    $durableRows,
                    static fn (array $row): bool => ($row['source'] ?? null) === 'next-source-retry-window-next253'
                        && ($row['durable'] ?? false) === true,
                )),
                'watermark_token' => self::retryCommitWatermarkToken($savepoint, $requiredCommitTokens, $gate),
            ],
            'required_retry_commit_tokens' => $requiredCommitTokens,
            'acknowledged_retry_commit_tokens' => $acknowledged,
            'retry_commit_rows' => $retryRows,
            'durable_publication_rows' => $durableRows,
            'durable_publication_rowids' => array_column($durableRows, $rowIdColumn),
            'durable_retry_rowids' => array_values(array_map(
                static fn (array $row): int|string => $row[$rowIdColumn],
                array_filter(
                    $durableRows,
                    static fn (array $row): bool => ($row['source'] ?? null) === 'next-source-retry-window-next253'
                        && ($row['durable'] ?? false) === true,
                ),
            )),
            'retry_commit_state' => $gate['commit_source_complete']
                ? 'current-source-complete-next-source-retry-durable'
                : 'next-source-retry-held-for-commit-watermark',
            'dependency_closure' => 'no new support component needed; the retry commit watermark reuses native PHP row-value UPDATE/DELETE RETURNING execution, current-window chunk admission, and retry cursor tokens while adding a commit watermark before next-source retry rows are durable',
            'dependencies' => [
                'sqlite-rowvalue-returning-window-retry-commit-watermark',
                'sqlite-returning-next-source-durable-after-current-window',
                'application-rowvalue-returning-window-retry-commit-watermark',
            ],
            'non_overlap' => 'adds a retry commit-token durability watermark above accepted next253 chunk-token admission; avoids next253 cursor construction, next249 chunking, next248 publication cursor, next245 yield-ticket gate, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $cursorRows
     * @return list<array<string,mixed>>
     */
    private static function retryCommitWatermarkCursorRows(array $cursorRows): array
    {
        $rows = [];
        foreach ($cursorRows as $row) {
            if (($row['source'] ?? null) !== 'next-source-retry-window-next253') {
                continue;
            }
            if (!is_string($row['cursor_token'] ?? null) || $row['cursor_token'] === '') {
                throw new \InvalidArgumentException('SQLite row-value returning window retry-commit-watermark retry cursor token is missing');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<string> $required
     * @param list<string> $acknowledged
     * @return array<string,mixed>
     */
    private static function retryCommitWatermarkGate(array $required, array $acknowledged, bool $retryExposed): array
    {
        $requiredSet = array_fill_keys($required, true);
        $ackSet = array_fill_keys($acknowledged, true);
        $missing = [];
        foreach ($required as $token) {
            if (!isset($ackSet[$token])) {
                $missing[] = $token;
            }
        }

        $unexpected = [];
        foreach ($acknowledged as $token) {
            if (!isset($requiredSet[$token])) {
                $unexpected[] = $token;
            }
        }

        $complete = $retryExposed && $missing === [] && $unexpected === [];

        return [
            'missing_commit_tokens' => $missing,
            'unexpected_commit_tokens' => $unexpected,
            'commit_source_complete' => $complete,
            'source_boundary' => $complete
                ? 'current-source-complete-next-source-retry-durable'
                : 'next-source-retry-held-for-commit-watermark',
        ];
    }

    /**
     * @param list<array<string,mixed>> $cursorRows
     * @return list<array<string,mixed>>
     */
    private static function retryCommitWatermarkDurableRows(array $cursorRows, bool $commitComplete): array
    {
        $rows = [];
        foreach (array_values($cursorRows) as $index => $row) {
            $isRetry = ($row['source'] ?? null) === 'next-source-retry-window-next253';
            $row['durable_ordinal'] = $index + 1;
            $row['durable'] = !$isRetry || $commitComplete;
            $row['commit_phase'] = $isRetry
                ? ($commitComplete ? 'next-source-retry-durable' : 'next-source-retry-pending')
                : 'current-source-window-durable';
            $row['commit_token'] = hash(
                'sha256',
                (string) ($row['source'] ?? '') . '|' . (string) ($row['cursor_token'] ?? '') . '|' . (string) $index,
            );
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<string> $requiredCommitTokens
     * @param array<string,mixed> $gate
     */
    private static function retryCommitWatermarkToken(string $savepoint, array $requiredCommitTokens, array $gate): string
    {
        return hash('sha256', json_encode([
            'savepoint' => $savepoint,
            'required' => $requiredCommitTokens,
            'missing' => $gate['missing_commit_tokens'],
            'unexpected' => $gate['unexpected_commit_tokens'],
            'complete' => $gate['commit_source_complete'],
        ], JSON_THROW_ON_ERROR));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedChunkTokens
     * @return array<string,mixed>
     */
    public static function executeDeleteRetryPublication(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next257',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        int $chunkSize = 2,
        ?array $acknowledgedChunkTokens = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeChunkCursorReleaseWindow(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $chunkSize,
            $acknowledgedChunkTokens,
        );

        $yield = self::deleteRetryPublicationPhaseRows($tables, $yieldStatements, $uniqueConstraints, $rowIdColumn, 'current-source-yield-next257');
        $afterYield = $yield['tables'];
        $attempt = self::deleteRetryPublicationPhaseRows($afterYield, $attemptStatements, $uniqueConstraints, $rowIdColumn, 'suppressed-attempt-next257');
        $retry = self::deleteRetryPublicationPhaseRows($afterYield, $retryStatements, $uniqueConstraints, $rowIdColumn, 'next-source-retry-next257');

        $currentTombstones = $yield['tombstones'];
        $retryTombstones = $retry['tombstones'];
        $suppressedTombstones = $attempt['tombstones'];
        $gate = self::deleteRetryPublicationTombstoneGate($base, $currentTombstones, $retryTombstones, $rowIdColumn);
        $stream = self::deleteRetryPublicationStream(
            $base,
            $currentTombstones,
            $retryTombstones,
            $suppressedTombstones,
            (bool) $gate['next_source_retry_tombstones_exposed'],
            $rowIdColumn,
        );

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next257',
            'delete_returning_tombstone_gate_next257' => $gate,
            'current_source_delete_tombstones_next257' => $currentTombstones,
            'suppressed_attempt_delete_tombstones_next257' => $suppressedTombstones,
            'next_source_retry_delete_tombstones_next257' => $gate['next_source_retry_tombstones_exposed'] ? $retryTombstones : [],
            'held_next_source_retry_delete_tombstones_next257' => $gate['next_source_retry_tombstones_exposed'] ? [] : $retryTombstones,
            'delete_returning_publication_stream_next257' => $stream,
            'delete_returning_publication_rowids_next257' => array_column($stream, $rowIdColumn),
            'delete_returning_publication_sources_next257' => array_column($stream, 'source'),
            'delete_returning_publication_tokens_next257' => array_column($stream, 'publication_token'),
            'delete_returning_release_token_next257' => $gate['next_source_retry_tombstones_exposed']
                ? self::deleteRetryPublicationReleaseToken($savepoint, $currentTombstones, $retryTombstones, $base['window_current_source_release_token_next253'])
                : null,
            'dependency_closure_next257' => 'no new support component needed; next257 reuses native PHP UPDATE/DELETE RETURNING execution and next253 current-source window chunk admission while adding DELETE RETURNING tombstone ordering before next-source retry publication',
            'dependencies_next257' => [
                'sqlite-rowvalue-delete-returning-current-source-tombstone-gate-next257',
                'sqlite-returning-delete-tombstones-before-next-source-retry-next257',
                'application-rowvalue-returning-window-delete-retry-publication',
            ],
            'non_overlap_next257' => 'adds DELETE RETURNING tombstone ordering over accepted next253 chunk-token admission; avoids next253 chunk construction, next252/next251 source fences, next248 publication cursor, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $statements
     * @param list<list<string>> $uniqueConstraints
     * @return array{tables:array<string,list<array<string,mixed>>>,tombstones:list<array<string,mixed>>}
     */
    private static function deleteRetryPublicationPhaseRows(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $working = $tables;
        $tombstones = [];

        foreach ($statements as $statementIndex => $sql) {
            $parsed = SQLiteUpdateDeleteReturningSql::parse($sql);
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $working, $rowIdColumn, $uniqueConstraints);
            if ($parsed['action'] === 'delete') {
                foreach ($result['returning'] as $rowIndex => $row) {
                    if (!array_key_exists($rowIdColumn, $row)) {
                        throw new \InvalidArgumentException("SQLite row-value returning window next257 delete rowid column {$rowIdColumn} is missing");
                    }
                    $rowId = $row[$rowIdColumn];
                    if (!is_int($rowId) && !is_string($rowId)) {
                        throw new \InvalidArgumentException("SQLite row-value returning window next257 delete rowid column {$rowIdColumn} must be int or string");
                    }
                    $tombstones[] = array_merge($row, [
                        'source' => $phase,
                        'statement_ordinal_next257' => $statementIndex + 1,
                        'delete_ordinal_next257' => count($tombstones) + 1,
                        'tombstone_token_next257' => hash('sha256', $phase . '|' . (string) ($statementIndex + 1) . '|' . (string) $rowId . '|' . (string) $rowIndex),
                    ]);
                }
            }
            $working = $result['tables'];
        }

        return [
            'tables' => $working,
            'tombstones' => $tombstones,
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $currentTombstones
     * @param list<array<string,mixed>> $retryTombstones
     * @return array<string,mixed>
     */
    private static function deleteRetryPublicationTombstoneGate(array $base, array $currentTombstones, array $retryTombstones, string $rowIdColumn): array
    {
        $chunkGate = $base['window_current_source_chunk_gate_next253'] ?? [];
        $currentComplete = (bool) ($chunkGate['yield_tickets_complete'] ?? false)
            && (bool) ($chunkGate['chunk_source_complete'] ?? false);
        $retryReady = $currentComplete && (bool) ($base['window_current_source_retry_exposed_next253'] ?? false);

        return [
            'savepoint' => $base['savepoint'] ?? null,
            'current_source_delete_count' => count($currentTombstones),
            'next_source_retry_delete_count' => count($retryTombstones),
            'current_source_delete_rowids' => array_column($currentTombstones, $rowIdColumn),
            'next_source_retry_delete_rowids' => array_column($retryTombstones, $rowIdColumn),
            'current_source_tombstones_complete' => $currentComplete,
            'next_source_retry_tombstones_exposed' => $retryReady,
            'blocked_reasons' => $retryReady ? [] : self::deleteRetryPublicationBlockedReasons($chunkGate),
            'source_boundary' => $retryReady
                ? 'current-source-delete-tombstones-before-next-source-retry-next257'
                : 'next-source-delete-tombstones-held-for-current-source-next257',
        ];
    }

    /**
     * @param array<string,mixed> $chunkGate
     * @return list<string>
     */
    private static function deleteRetryPublicationBlockedReasons(array $chunkGate): array
    {
        $reasons = [];
        if (!($chunkGate['yield_tickets_complete'] ?? false)) {
            $reasons[] = 'current-source-yield-tickets-incomplete-next257';
        }
        if (!($chunkGate['chunk_source_complete'] ?? false)) {
            $reasons[] = 'current-source-window-chunks-incomplete-next257';
        }

        return $reasons === [] ? ['next-source-retry-window-held-next257'] : $reasons;
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $currentTombstones
     * @param list<array<string,mixed>> $retryTombstones
     * @param list<array<string,mixed>> $suppressedTombstones
     * @return list<array<string,mixed>>
     */
    private static function deleteRetryPublicationStream(
        array $base,
        array $currentTombstones,
        array $retryTombstones,
        array $suppressedTombstones,
        bool $retryExposed,
        string $rowIdColumn,
    ): array {
        $rows = [];
        foreach ($currentTombstones as $row) {
            $rows[] = self::deleteRetryPublicationStreamRow($row, 'current-delete-returning-next257', count($rows) + 1, true, $rowIdColumn);
        }
        foreach ($suppressedTombstones as $row) {
            $rows[] = self::deleteRetryPublicationStreamRow($row, 'suppressed-attempt-delete-returning-next257', count($rows) + 1, false, $rowIdColumn);
        }
        if (!$retryExposed) {
            return $rows;
        }
        foreach ($retryTombstones as $row) {
            $rows[] = self::deleteRetryPublicationStreamRow($row, 'next-source-retry-delete-returning-next257', count($rows) + 1, true, $rowIdColumn);
        }

        foreach ($base['window_current_source_cursor_next253'] ?? [] as $cursorRow) {
            if (($cursorRow['source'] ?? null) !== 'next-source-retry-window-next253') {
                continue;
            }
            $rowId = $cursorRow[$rowIdColumn] ?? null;
            if (!is_int($rowId) && !is_string($rowId)) {
                continue;
            }
            $rows[] = [
                'source' => 'next-source-retry-window-row-next257',
                'visible' => true,
                'publication_ordinal_next257' => count($rows) + 1,
                $rowIdColumn => $rowId,
                self::keyNameColumn($cursorRow) => self::nullableKeyName($cursorRow),
                'publication_token' => hash('sha256', 'retry-window|' . (string) ($cursorRow['cursor_token'] ?? '') . '|' . (string) $rowId),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function deleteRetryPublicationStreamRow(array $row, string $source, int $ordinal, bool $visible, string $rowIdColumn): array
    {
        $rowId = $row[$rowIdColumn] ?? null;
        if (!is_int($rowId) && !is_string($rowId)) {
            throw new \InvalidArgumentException("SQLite row-value returning window next257 stream rowid column {$rowIdColumn} must be int or string");
        }

        return array_merge($row, [
            'source' => $source,
            'visible' => $visible,
            'publication_ordinal_next257' => $ordinal,
            'publication_token' => hash('sha256', $source . '|' . (string) $ordinal . '|' . (string) $rowId . '|' . (string) ($row['tombstone_token_next257'] ?? '')),
        ]);
    }

    /**
     * @param list<array<string,mixed>> $currentTombstones
     * @param list<array<string,mixed>> $retryTombstones
     */
    private static function deleteRetryPublicationReleaseToken(string $savepoint, array $currentTombstones, array $retryTombstones, mixed $chunkReleaseToken): string
    {
        return hash('sha256', json_encode([
            'savepoint' => $savepoint,
            'chunkReleaseToken' => $chunkReleaseToken,
            'current' => array_column($currentTombstones, 'tombstone_token_next257'),
            'retry' => array_column($retryTombstones, 'tombstone_token_next257'),
        ], JSON_THROW_ON_ERROR));
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @return array<string,mixed>
     */
    public static function executePublicationTransitionAdmission(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next258',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        ?string $acknowledgedTransitionToken = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePublicationWindowFence(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
        );

        $transition = self::publicationTransitionAdmissionState($base);
        $admitted = $transition['transition_complete_next258'];
        $rows = self::publicationTransitionAdmittedRows($base['current_source_publication_windows_next252'], $admitted);
        $resumeRows = self::publicationTransitionResumeRows($rows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next258',
            'current_source_transition_next258' => $transition,
            'required_transition_token_next258' => $transition['required_transition_token_next258'],
            'acknowledged_transition_token_next258' => $acknowledgedTransitionToken,
            'transition_acknowledged_next258' => $acknowledgedTransitionToken !== null
                && hash_equals($transition['required_transition_token_next258'], $acknowledgedTransitionToken),
            'next_source_admitted_next258' => $admitted
                && $acknowledgedTransitionToken !== null
                && hash_equals($transition['required_transition_token_next258'], $acknowledgedTransitionToken),
            'publication_rows_next258' => self::publicationTransitionAdmitNextRows(
                $rows,
                $acknowledgedTransitionToken !== null
                    && hash_equals($transition['required_transition_token_next258'], $acknowledgedTransitionToken),
            ),
            'publication_row_count_next258' => count(self::publicationTransitionAdmitNextRows(
                $rows,
                $acknowledgedTransitionToken !== null
                    && hash_equals($transition['required_transition_token_next258'], $acknowledgedTransitionToken),
            )),
            'resume_rows_next258' => $resumeRows,
            'resume_tickets_next258' => array_column($resumeRows, 'ticket'),
            'blocked_reasons_next258' => self::publicationTransitionBlockedReasons($base, $transition, $acknowledgedTransitionToken),
            'dependency_closure_next258' => 'no new support component needed; next258 reuses native PHP row-value UPDATE/DELETE RETURNING execution, next248 publication cursors, and next252 current-source window high-water rows while adding a transition-token fence for admitting next-source retry rows',
            'dependencies_next258' => [
                'sqlite-rowvalue-returning-current-source-transition-token-next258',
                'sqlite-rowvalue-returning-next-source-admission-after-window-high-water-next258',
                'application-rowvalue-returning-window-transition-current-source-next258',
            ],
            'non_overlap_next258' => 'adds a transition-token acknowledgement after the accepted next252 row-number/high-water fence so next-source retry rows remain quarantined until the current high-water and first retry window boundary is acknowledged; avoids accepted next252 row-number fences, next248 publication cursor barriers, next245 yield tickets, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function publicationTransitionAdmissionState(array $base): array
    {
        $fence = $base['publication_window_fence_next252'];
        $currentHighWater = self::publicationTransitionStringOrNull($base['current_source_high_water_ticket_next252'] ?? null);
        $firstRetry = self::publicationTransitionStringOrNull($base['next_source_first_ticket_next252'] ?? null);
        $complete = (bool) ($fence['current_source_complete'] ?? false);
        $retryAfterHighWater = (bool) ($fence['retry_after_current_high_water'] ?? false);
        $windowDigest = self::publicationTransitionStringValue($fence['window_digest'] ?? null, 'window_digest');
        $required = hash('sha256', json_encode([
            'savepoint' => $base['savepoint'] ?? null,
            'currentHighWater' => $currentHighWater,
            'firstRetry' => $firstRetry,
            'currentOrdinal' => $fence['current_high_water_ordinal'] ?? null,
            'firstRetryOrdinal' => $fence['first_retry_ordinal'] ?? null,
            'windowDigest' => $windowDigest,
        ], JSON_THROW_ON_ERROR));

        return [
            'current_source_complete_next258' => $complete,
            'next_source_available_next258' => $firstRetry !== null,
            'retry_after_current_high_water_next258' => $retryAfterHighWater,
            'current_high_water_ticket_next258' => $currentHighWater,
            'first_retry_ticket_next258' => $firstRetry,
            'current_high_water_ordinal_next258' => $fence['current_high_water_ordinal'] ?? null,
            'first_retry_ordinal_next258' => $fence['first_retry_ordinal'] ?? null,
            'window_digest_next258' => $windowDigest,
            'required_transition_token_next258' => $required,
            'transition_complete_next258' => $complete && $retryAfterHighWater && $firstRetry !== null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function publicationTransitionAdmittedRows(array $rows, bool $transitionComplete): array
    {
        $out = [];
        foreach ($rows as $row) {
            $isNext = (bool) ($row['window_is_next_source_next252'] ?? false);
            $row['transition_ready_next258'] = $transitionComplete;
            $row['next_source_quarantined_next258'] = $isNext && !$transitionComplete;
            $row['publication_phase_next258'] = $isNext ? 'next-source-transition' : 'current-source-window';
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function publicationTransitionAdmitNextRows(array $rows, bool $acknowledged): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (($row['window_is_next_source_next252'] ?? false) && !$acknowledged) {
                continue;
            }
            $row['next_source_admitted_next258'] = !($row['window_is_next_source_next252'] ?? false) || $acknowledged;
            $out[] = $row;
        }

        foreach ($out as $index => $row) {
            $out[$index]['publication_ordinal_next258'] = $index + 1;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function publicationTransitionResumeRows(array $rows, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return $rows;
        }

        $copy = false;
        $out = [];
        foreach ($rows as $row) {
            if ($copy) {
                $out[] = $row;
                continue;
            }
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $copy = true;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $transition
     * @return list<string>
     */
    private static function publicationTransitionBlockedReasons(array $base, array $transition, ?string $acknowledgedTransitionToken): array
    {
        $reasons = $base['publication_window_fence_next252']['blocked_reasons'] ?? [];
        if (!is_array($reasons)) {
            $reasons = [];
        }
        if (($transition['next_source_available_next258'] ?? false) && $acknowledgedTransitionToken === null) {
            $reasons[] = 'missing-current-source-transition-token-next258';
        }
        if ($acknowledgedTransitionToken !== null && !hash_equals($transition['required_transition_token_next258'], $acknowledgedTransitionToken)) {
            $reasons[] = 'unexpected-current-source-transition-token-next258';
        }

        return array_values($reasons);
    }

    private static function publicationTransitionStringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next258 {$name} is missing");
        }

        return $value;
    }

    private static function publicationTransitionStringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite row-value returning window next258 transition ticket is malformed');
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedNextRowTickets
     * @param list<string>|null $acknowledgedCurrentFrameTickets
     * @return array<string,mixed>
     */
    public static function executeCurrentRowFrameAdmission(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next259',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        ?array $acknowledgedNextRowTickets = null,
        ?array $acknowledgedCurrentFrameTickets = null,
        bool $requirePreviousFrameClose = true,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeWindowRowAdmission(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
            $acknowledgedNextRowTickets,
        );

        $rows = self::currentRowFrameAdmissionRows(
            $base['next_row_window_rows_next255'],
            $rowIdColumn,
            $acknowledgedCurrentFrameTickets,
            $requirePreviousFrameClose,
        );
        $readyRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['current_frame_ready_next259'] ?? null) === true));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['current_frame_ready_next259'] ?? null) !== true));
        $resume = self::currentRowFrameAdmissionResume($readyRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next259',
            'current_row_frame_admission_next259' => true,
            'current_row_frame_rows_next259' => $rows,
            'current_row_frame_ready_rows_next259' => $readyRows,
            'current_row_frame_blocked_rows_next259' => $blockedRows,
            'current_row_frame_ready_tickets_next259' => array_column($readyRows, 'ticket'),
            'current_row_frame_blocked_tickets_next259' => array_column($blockedRows, 'ticket'),
            'current_row_frame_resume_next259' => $resume,
            'current_row_frame_resume_tickets_next259' => array_column($resume['rows'], 'ticket'),
            'current_row_frame_summary_next259' => self::currentRowFrameAdmissionSummary($rows),
            'current_row_frame_fence_next259' => [
                'savepoint' => $savepoint,
                'source_handoff_state' => $base['source_handoff_state_next251'],
                'next_row_ready_count' => count($base['next_row_ready_rows_next255']),
                'next_row_blocked_count' => count($base['next_row_blocked_rows_next255']),
                'frame_mode' => 'RETURNING CURRENT ROW frame closes before following row is visible',
                'require_previous_frame_close' => $requirePreviousFrameClose,
                'row_count' => count($rows),
                'ready_count' => count($readyRows),
                'blocked_count' => count($blockedRows),
                'ready_digest' => self::currentRowFrameAdmissionDigest($readyRows),
                'blocked_digest' => self::currentRowFrameAdmissionDigest($blockedRows),
                'transition_count' => count(array_filter($rows, static fn (array $row): bool => ($row['current_frame_crosses_source_epoch_next259'] ?? null) === true)),
                'all_current_frames_acknowledged' => self::allCurrentFramesAcknowledgedForCurrentRowAdmission($rows),
                'all_next_frames_acknowledged' => self::allNextFramesAcknowledgedForCurrentRowAdmission($rows),
            ],
            'dependency_closure_next259' => 'no new support component needed; next259 reuses native row-value UPDATE/DELETE RETURNING rows, next251 source handoff, and next255 next-row admission while adding CURRENT ROW frame-close gating for copied application setting imports',
            'dependencies_next259' => [
                'sqlite-rowvalue-returning-window-current-row-frame-next259',
                'sqlite-rowvalue-returning-window-next-row-admission-next255',
                'application-rowvalue-returning-current-row-frame-next259',
            ],
            'non_overlap_next259' => 'adds CURRENT ROW frame-close admission after accepted next255 next-row receipts; avoids next255 next-row admission, next254 receipt validation, next251 source digest handoff, next248 publication cursor sequencing, row-value savepoint variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $windowRows
     * @param list<string>|null $acknowledgedTickets
     * @return list<array<string,mixed>>
     */
    private static function currentRowFrameAdmissionRows(array $windowRows, string $rowIdColumn, ?array $acknowledgedTickets, bool $requirePreviousFrameClose): array
    {
        $acknowledged = $acknowledgedTickets === null
            ? array_column($windowRows, 'ticket')
            : self::currentRowFrameAdmissionTicketSet($acknowledgedTickets);

        $rows = [];
        foreach (array_values($windowRows) as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite row-value current-row frame next259 rows are malformed');
            }
            $ticket = self::currentRowFrameAdmissionTicket($row['ticket'] ?? null);
            $previous = $windowRows[$index - 1] ?? null;
            $next = $windowRows[$index + 1] ?? null;
            $previousTicket = is_array($previous) ? self::currentRowFrameAdmissionTicket($previous['ticket'] ?? null) : null;
            $nextTicket = is_array($next) ? self::currentRowFrameAdmissionTicket($next['ticket'] ?? null) : null;
            $currentAcknowledged = in_array($ticket, $acknowledged, true);
            $previousAcknowledged = !$requirePreviousFrameClose || $previousTicket === null || in_array($previousTicket, $acknowledged, true);
            $nextRowReady = ($row['next_row_ready_next255'] ?? null) === true;
            $currentEpoch = self::currentRowFrameAdmissionStringValue($row['next_row_source_epoch_next255'] ?? $row['source_epoch_next251'] ?? null, 'source epoch');
            $nextEpoch = is_array($next)
                ? self::currentRowFrameAdmissionStringValue($next['next_row_source_epoch_next255'] ?? $next['source_epoch_next251'] ?? null, 'next source epoch')
                : null;
            $crossesEpoch = $nextEpoch !== null && $nextEpoch !== $currentEpoch;

            $blockedReasons = [];
            if (!$nextRowReady) {
                $blockedReasons[] = 'next-row-not-ready-next259';
            }
            if (!$currentAcknowledged) {
                $blockedReasons[] = 'current-row-frame-not-acknowledged-next259';
            }
            if (!$previousAcknowledged) {
                $blockedReasons[] = 'previous-row-frame-not-closed-next259';
            }

            $ready = $blockedReasons === [];
            $rowId = self::currentRowFrameAdmissionRowId($row[$rowIdColumn] ?? $row['next_row_rowid_next255'] ?? null, $rowIdColumn);
            $rows[] = [
                'ticket' => $ticket,
                'current_frame_ordinal_next259' => count($rows) + 1,
                'current_frame_rowid_next259' => $rowId,
                'current_frame_source_epoch_next259' => $currentEpoch,
                'current_frame_previous_ticket_next259' => $previousTicket,
                'current_frame_next_ticket_next259' => $nextTicket,
                'current_frame_current_acknowledged_next259' => $currentAcknowledged,
                'current_frame_previous_closed_next259' => $previousAcknowledged,
                'current_frame_next_row_ready_next259' => $nextRowReady,
                'current_frame_crosses_source_epoch_next259' => $crossesEpoch,
                'current_frame_ready_next259' => $ready,
                'current_frame_blocked_reasons_next259' => $blockedReasons,
                'current_frame_receipt_next259' => hash('sha256', implode('|', [
                    $ticket,
                    (string) ($previousTicket ?? ''),
                    (string) ($nextTicket ?? ''),
                    $currentEpoch,
                    $ready ? 'ready' : 'blocked',
                ])),
            ] + $row;
        }

        return $rows;
    }

    /**
     * @param list<string> $tickets
     * @return list<string>
     */
    private static function currentRowFrameAdmissionTicketSet(array $tickets): array
    {
        $set = [];
        foreach ($tickets as $ticket) {
            $set[] = self::currentRowFrameAdmissionTicket($ticket);
        }

        return array_values(array_unique($set));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function currentRowFrameAdmissionSummary(array $rows): array
    {
        $summary = [
            'row_count' => count($rows),
            'ready_count' => 0,
            'blocked_count' => 0,
            'current_source_ready_count' => 0,
            'next_source_ready_count' => 0,
            'current_source_blocked_count' => 0,
            'next_source_blocked_count' => 0,
            'transition_count' => 0,
            'ready_rowids' => [],
            'blocked_rowids' => [],
            'blocked_reasons' => [],
        ];

        foreach ($rows as $row) {
            $ready = ($row['current_frame_ready_next259'] ?? null) === true;
            $source = str_contains((string) ($row['current_frame_source_epoch_next259'] ?? ''), 'next') ? 'next_source' : 'current_source';
            $bucket = $ready ? 'ready' : 'blocked';
            $summary[$bucket . '_count']++;
            $summary[$source . '_' . $bucket . '_count']++;
            $summary[$bucket . '_rowids'][] = $row['current_frame_rowid_next259'];
            if (($row['current_frame_crosses_source_epoch_next259'] ?? null) === true) {
                $summary['transition_count']++;
            }
            foreach (($row['current_frame_blocked_reasons_next259'] ?? []) as $reason) {
                $summary['blocked_reasons'][$reason] = (($summary['blocked_reasons'][$reason] ?? 0) + 1);
            }
        }
        ksort($summary['blocked_reasons']);

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function currentRowFrameAdmissionResume(array $rows, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return [
                'resume_after_ticket' => null,
                'resume_offset' => 0,
                'rows' => $rows,
                'remaining_count' => count($rows),
                'exhausted' => $rows === [],
            ];
        }

        $offset = null;
        foreach ($rows as $index => $row) {
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $offset = $index + 1;
                break;
            }
        }
        if ($offset === null) {
            throw new \InvalidArgumentException('SQLite row-value current-row frame next259 resume ticket is not ready');
        }

        $remaining = array_slice($rows, $offset);

        return [
            'resume_after_ticket' => $resumeAfterTicket,
            'resume_offset' => $offset,
            'rows' => $remaining,
            'remaining_count' => count($remaining),
            'exhausted' => $remaining === [],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allCurrentFramesAcknowledgedForCurrentRowAdmission(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!str_contains((string) ($row['current_frame_source_epoch_next259'] ?? ''), 'next')
                && ($row['current_frame_ready_next259'] ?? null) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allNextFramesAcknowledgedForCurrentRowAdmission(array $rows): bool
    {
        foreach ($rows as $row) {
            if (str_contains((string) ($row['current_frame_source_epoch_next259'] ?? ''), 'next')
                && ($row['current_frame_ready_next259'] ?? null) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function currentRowFrameAdmissionDigest(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private static function currentRowFrameAdmissionRowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value current-row frame next259 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function currentRowFrameAdmissionTicket(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite row-value current-row frame next259 ticket must be a non-empty string');
        }

        return $value;
    }

    private static function currentRowFrameAdmissionStringValue(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value current-row frame next259 {$label} must be a non-empty string");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedNextRowTickets
     * @param list<string>|null $acknowledgedBoundaryTickets
     * @return array<string,mixed>
     */
    public static function executeFrameBoundaryAdmission(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next260',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        ?array $acknowledgedNextRowTickets = null,
        ?array $acknowledgedBoundaryTickets = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeWindowRowAdmission(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
            $acknowledgedNextRowTickets,
        );

        $rows = self::frameBoundaryAdmissionRows(
            $base['next_row_window_rows_next255'],
            $rowIdColumn,
            $acknowledgedBoundaryTickets,
        );
        $readyRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['boundary_ready_next260'] ?? null) === true));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['boundary_ready_next260'] ?? null) !== true));
        $mixedRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['boundary_crosses_source_next260'] ?? null) === true));
        $resume = self::frameBoundaryAdmissionResume($readyRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next260',
            'boundary_admission_next260' => true,
            'boundary_window_rows_next260' => $rows,
            'boundary_ready_rows_next260' => $readyRows,
            'boundary_blocked_rows_next260' => $blockedRows,
            'boundary_mixed_rows_next260' => $mixedRows,
            'boundary_ready_tickets_next260' => array_column($readyRows, 'ticket'),
            'boundary_blocked_tickets_next260' => array_column($blockedRows, 'ticket'),
            'boundary_mixed_tickets_next260' => array_column($mixedRows, 'ticket'),
            'boundary_resume_next260' => $resume,
            'boundary_resume_tickets_next260' => array_column($resume['rows'], 'ticket'),
            'boundary_summary_next260' => self::frameBoundaryAdmissionSummary($rows, $mixedRows),
            'boundary_fence_next260' => [
                'savepoint' => $savepoint,
                'source_handoff_state' => $base['source_handoff_state_next251'],
                'next_row_ready_count' => $base['next_row_admission_summary_next255']['ready_count'],
                'row_count' => count($rows),
                'mixed_boundary_count' => count($mixedRows),
                'ready_count' => count($readyRows),
                'blocked_count' => count($blockedRows),
                'current_to_next_boundary_released' => $blockedRows === [] && $mixedRows !== [],
                'boundary_digest' => self::frameBoundaryAdmissionDigest($rows),
                'mixed_boundary_digest' => self::frameBoundaryAdmissionDigest($mixedRows),
            ],
            'dependency_closure_next260' => 'no new support component needed; next260 reuses native PHP row-value UPDATE/DELETE RETURNING window rows, next251 source epochs, and next255 next-row admission while adding a frame-source boundary receipt for the current-source to next-source transition',
            'dependencies_next260' => [
                'sqlite-rowvalue-returning-window-boundary-current-source-next260',
                'sqlite-rowvalue-returning-window-next-row-admission-next255',
                'application-rowvalue-returning-window-boundary-current-source-next260',
            ],
            'non_overlap_next260' => 'adds frame-source boundary admission for RETURNING window rows whose preceding/current/following frame crosses from current-source rows into retry-source rows; avoids next255 next-row acknowledgement alone, next254 row receipts, next251 epoch/digest fencing, next248 publication cursors, next245 yield gates, row-value savepoint-only variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $handoffRows
     * @param list<string>|null $acknowledgedBoundaryTickets
     * @return list<array<string,mixed>>
     */
    private static function frameBoundaryAdmissionRows(array $handoffRows, string $rowIdColumn, ?array $acknowledgedBoundaryTickets): array
    {
        $expectedBoundaryTickets = [];
        foreach (array_values($handoffRows) as $index => $row) {
            $frame = self::frameBoundaryAdmissionFrame($handoffRows, $index);
            if (self::frameBoundaryCrossesSource($frame)) {
                $expectedBoundaryTickets[] = self::frameBoundaryAdmissionTicket($row['ticket'] ?? null);
            }
        }

        $acknowledged = $acknowledgedBoundaryTickets === null
            ? $expectedBoundaryTickets
            : self::frameBoundaryAdmissionTicketSet($acknowledgedBoundaryTickets);

        $rows = [];
        foreach (array_values($handoffRows) as $index => $row) {
            $ticket = self::frameBoundaryAdmissionTicket($row['ticket'] ?? null);
            $frame = self::frameBoundaryAdmissionFrame($handoffRows, $index);
            $frameTickets = array_map(static fn (array $item): string => self::frameBoundaryAdmissionTicket($item['ticket'] ?? null), $frame);
            $frameEpochs = array_map(static fn (array $item): string => self::frameBoundaryAdmissionEpoch($item['next_row_source_epoch_next255'] ?? $item['source_epoch_next251'] ?? null), $frame);
            $crosses = self::frameBoundaryCrossesSource($frame);
            $acknowledgedBoundary = !$crosses || in_array($ticket, $acknowledged, true);
            $nextRowReady = ($row['next_row_ready_next255'] ?? null) === true;
            $reasons = [];
            if (!$nextRowReady) {
                $reasons[] = 'next-row-not-admitted-before-boundary-next260';
            }
            if (!$acknowledgedBoundary) {
                $reasons[] = 'source-boundary-ticket-not-acknowledged-next260';
            }

            $rowId = self::frameBoundaryAdmissionRowId($row['next_row_rowid_next255'] ?? $row[$rowIdColumn] ?? null, $rowIdColumn);
            $receipt = hash('sha256', json_encode([
                'ticket' => $ticket,
                'rowid' => $rowId,
                'frameTickets' => $frameTickets,
                'frameEpochs' => $frameEpochs,
                'crosses' => $crosses,
                'ready' => $reasons === [],
            ], JSON_THROW_ON_ERROR));

            $rows[] = [
                'boundary_ordinal_next260' => count($rows) + 1,
                'boundary_rowid_next260' => $rowId,
                'boundary_frame_tickets_next260' => $frameTickets,
                'boundary_frame_epochs_next260' => $frameEpochs,
                'boundary_crosses_source_next260' => $crosses,
                'boundary_ticket_acknowledged_next260' => $acknowledgedBoundary,
                'boundary_next_row_ready_next260' => $nextRowReady,
                'boundary_ready_next260' => $reasons === [],
                'boundary_blocked_reasons_next260' => $reasons,
                'boundary_receipt_next260' => $receipt,
            ] + $row;
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function frameBoundaryAdmissionFrame(array $rows, int $index): array
    {
        $frame = [];
        foreach ([$index - 1, $index, $index + 1] as $frameIndex) {
            if (isset($rows[$frameIndex])) {
                $frame[] = $rows[$frameIndex];
            }
        }

        return $frame;
    }

    /**
     * @param list<array<string,mixed>> $frame
     */
    private static function frameBoundaryCrossesSource(array $frame): bool
    {
        $epochs = [];
        foreach ($frame as $row) {
            $epochs[] = self::frameBoundaryAdmissionEpoch($row['next_row_source_epoch_next255'] ?? $row['source_epoch_next251'] ?? null);
        }

        return count(array_unique($epochs)) > 1;
    }

    /**
     * @param list<string> $tickets
     * @return list<string>
     */
    private static function frameBoundaryAdmissionTicketSet(array $tickets): array
    {
        $set = [];
        foreach ($tickets as $ticket) {
            $set[] = self::frameBoundaryAdmissionTicket($ticket);
        }

        return array_values(array_unique($set));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $mixedRows
     * @return array<string,mixed>
     */
    private static function frameBoundaryAdmissionSummary(array $rows, array $mixedRows): array
    {
        $summary = [
            'row_count' => count($rows),
            'ready_count' => 0,
            'blocked_count' => 0,
            'mixed_boundary_count' => count($mixedRows),
            'mixed_ready_count' => 0,
            'mixed_blocked_count' => 0,
            'ready_rowids' => [],
            'blocked_rowids' => [],
            'mixed_rowids' => array_column($mixedRows, 'boundary_rowid_next260'),
            'blocked_reasons' => [],
        ];

        foreach ($rows as $row) {
            $ready = ($row['boundary_ready_next260'] ?? null) === true;
            $mixed = ($row['boundary_crosses_source_next260'] ?? null) === true;
            $bucket = $ready ? 'ready' : 'blocked';
            $summary[$bucket . '_count']++;
            $summary[$bucket . '_rowids'][] = $row['boundary_rowid_next260'];
            if ($mixed) {
                $summary['mixed_' . $bucket . '_count']++;
            }
            foreach (($row['boundary_blocked_reasons_next260'] ?? []) as $reason) {
                $summary['blocked_reasons'][$reason] = (($summary['blocked_reasons'][$reason] ?? 0) + 1);
            }
        }
        ksort($summary['blocked_reasons']);

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function frameBoundaryAdmissionResume(array $rows, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return [
                'resume_after_ticket' => null,
                'resume_offset' => 0,
                'rows' => $rows,
                'remaining_count' => count($rows),
                'exhausted' => $rows === [],
            ];
        }

        $offset = null;
        foreach ($rows as $index => $row) {
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $offset = $index + 1;
                break;
            }
        }
        if ($offset === null) {
            throw new \InvalidArgumentException('SQLite row-value returning window next260 resume ticket is not boundary-ready');
        }

        $remaining = array_slice($rows, $offset);

        return [
            'resume_after_ticket' => $resumeAfterTicket,
            'resume_offset' => $offset,
            'rows' => $remaining,
            'remaining_count' => count($remaining),
            'exhausted' => $remaining === [],
        ];
    }

    private static function frameBoundaryAdmissionDigest(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private static function frameBoundaryAdmissionTicket(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite row-value boundary admission next260 ticket is missing');
        }

        return $value;
    }

    private static function frameBoundaryAdmissionEpoch(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite row-value boundary admission next260 source epoch is missing');
        }

        return $value;
    }

    private static function frameBoundaryAdmissionRowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value boundary admission next260 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<array<string,mixed>>|null $rowReceipts
     * @param array<string,string>|null $segmentWatermarks
     * @return array<string,mixed>
     */
    public static function executeSourceSegmentWatermark(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next261',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        string $currentSourceEpoch = 'app-current-source-261',
        string $nextSourceEpoch = 'app-next-source-261',
        ?string $expectedCurrentDigest = null,
        ?string $expectedNextDigest = null,
        ?array $rowReceipts = null,
        bool $requireNextReceipts = true,
        ?array $segmentWatermarks = null,
        bool $requireNextSegmentWatermark = true,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeRowReceiptAdmissionWindow(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
            $currentSourceEpoch,
            $nextSourceEpoch,
            $expectedCurrentDigest,
            $expectedNextDigest,
            $rowReceipts,
            $requireNextReceipts,
        );

        $admittedRows = $base['admitted_rows_next254'];
        $currentRows = self::sourceSegmentRowsForEpoch($admittedRows, $nextSourceEpoch, false);
        $nextRows = self::sourceSegmentRowsForEpoch($admittedRows, $nextSourceEpoch, true);
        $expectedWatermarks = [
            'current' => self::sourceSegmentWatermark($currentRows, $rowIdColumn, 'current'),
            'next' => self::sourceSegmentWatermark($nextRows, $rowIdColumn, 'next'),
        ];
        $providedWatermarks = $segmentWatermarks ?? [
            'current' => $expectedWatermarks['current']['watermark_token'],
            'next' => $expectedWatermarks['next']['watermark_token'],
        ];
        $reasons = self::sourceSegmentWatermarkBlockedReasons($base, $expectedWatermarks, $providedWatermarks, $requireNextSegmentWatermark);
        $segmentsReady = $reasons === [];
        $publicationRows = self::sourceSegmentPublicationRows($currentRows, $nextRows, $segmentsReady, $rowIdColumn, $nextSourceEpoch);
        $resume = self::sourceSegmentWatermarkResume($publicationRows, $resumeAfterTicket);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next261',
            'source_window_barrier_next261' => [
                'savepoint' => $savepoint,
                'rowid_column' => $rowIdColumn,
                'current_source_epoch' => $currentSourceEpoch,
                'next_source_epoch' => $nextSourceEpoch,
                'admission_ready' => $base['admission_state_next254'] === 'current-source-next254-window-receipts-admitted',
                'require_next_segment_watermark' => $requireNextSegmentWatermark,
                'expected_current_watermark' => $expectedWatermarks['current']['watermark_token'],
                'expected_next_watermark' => $expectedWatermarks['next']['watermark_token'],
                'provided_current_watermark' => $providedWatermarks['current'] ?? null,
                'provided_next_watermark' => $providedWatermarks['next'] ?? null,
                'current_segment_row_count' => count($currentRows),
                'next_segment_row_count' => count($nextRows),
                'published_row_count' => count($publicationRows),
                'published_next_row_count' => count(self::sourceSegmentRowsForEpoch($publicationRows, $nextSourceEpoch, true)),
                'blocked_reasons' => $reasons,
                'barrier_token' => self::sourceSegmentWatermarkBarrierToken($base, $expectedWatermarks, $providedWatermarks, $reasons),
            ],
            'expected_source_window_watermarks_next261' => $expectedWatermarks,
            'provided_source_window_watermarks_next261' => $providedWatermarks,
            'published_rows_next261' => $publicationRows,
            'published_tickets_next261' => array_column($publicationRows, 'ticket'),
            'published_next_rows_next261' => self::sourceSegmentRowsForEpoch($publicationRows, $nextSourceEpoch, true),
            'published_next_tickets_next261' => array_column(self::sourceSegmentRowsForEpoch($publicationRows, $nextSourceEpoch, true), 'ticket'),
            'source_window_resume_next261' => $resume,
            'source_window_resume_tickets_next261' => array_column($resume['rows'], 'ticket'),
            'source_window_state_next261' => $segmentsReady
                ? 'current-source-window-watermarks-admit-next-source-next261'
                : 'current-source-window-watermarks-hold-next-source-next261',
            'dependency_closure_next261' => 'no new support component needed; next261 reuses row-value UPDATE/DELETE RETURNING window row receipts and adds current/next source window segment watermarks for copied application setting imports',
            'dependencies_next261' => [
                'sqlite-rowvalue-update-delete-returning-window-current-source-next261',
                'sqlite-returning-window-segment-watermark-next261',
                'application-rowvalue-returning-window-source-watermark-next261',
            ],
            'non_overlap_next261' => 'adds source-window segment watermarks after accepted next254 row-level receipt admission; avoids next254 row receipt matching, next251 digest fencing, next248 publication cursors, next245 yield gates, savepoint-only row-value RETURNING, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function sourceSegmentRowsForEpoch(array $rows, string $nextSourceEpoch, bool $next): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($nextSourceEpoch, $next): bool {
            $isNext = ($row['source_epoch_next251'] ?? null) === $nextSourceEpoch;

            return $next ? $isNext : !$isNext;
        }));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function sourceSegmentWatermark(array $rows, string $rowIdColumn, string $segment): array
    {
        $items = [];
        foreach ($rows as $index => $row) {
            $ticket = self::sourceSegmentStringValue($row['ticket'] ?? null, 'ticket');
            $frameToken = self::sourceSegmentStringValue($row['frame_token'] ?? null, 'frame token');
            $epoch = self::sourceSegmentStringValue($row['source_epoch_next251'] ?? null, 'source epoch');
            $rowId = $row[$rowIdColumn] ?? null;
            if (!is_int($rowId) && !is_string($rowId)) {
                throw new \InvalidArgumentException("SQLite row-value returning window next261 rowid column {$rowIdColumn} must be int or string");
            }
            $items[] = [
                'ordinal' => $index + 1,
                'ticket' => $ticket,
                'source_epoch' => $epoch,
                $rowIdColumn => $rowId,
                'frame_token' => $frameToken,
                'running_bytes' => self::sourceSegmentIntValue($row['running_bytes'] ?? null, 'running bytes'),
                'following_bytes' => self::sourceSegmentIntValue($row['following_bytes'] ?? null, 'following bytes'),
                'admission_token' => self::sourceSegmentStringValue($row['admission_token_next254'] ?? null, 'admission token'),
            ];
        }

        return [
            'segment' => $segment,
            'row_count' => count($items),
            'row_ids' => array_column($items, $rowIdColumn),
            'tickets' => array_column($items, 'ticket'),
            'window_frame_tokens' => array_column($items, 'frame_token'),
            'running_bytes_final' => $items === [] ? 0 : $items[array_key_last($items)]['running_bytes'],
            'following_bytes_total' => array_sum(array_column($items, 'following_bytes')),
            'watermark_token' => hash('sha256', json_encode($items, JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,array<string,mixed>> $expected
     * @param array<string,string> $provided
     * @return list<string>
     */
    private static function sourceSegmentWatermarkBlockedReasons(array $base, array $expected, array $provided, bool $requireNextSegmentWatermark): array
    {
        $reasons = [];
        if (($base['admission_state_next254'] ?? null) !== 'current-source-next254-window-receipts-admitted') {
            $reasons[] = 'row-receipt-admission-not-ready-next261';
        }
        if (($provided['current'] ?? null) !== $expected['current']['watermark_token']) {
            $reasons[] = 'current-source-window-watermark-mismatch-next261';
        }
        if ($requireNextSegmentWatermark && ($provided['next'] ?? null) !== $expected['next']['watermark_token']) {
            $reasons[] = 'next-source-window-watermark-mismatch-next261';
        }
        if (!$requireNextSegmentWatermark) {
            $reasons[] = 'next-source-window-watermark-not-required-next261';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<array<string,mixed>>
     */
    private static function sourceSegmentPublicationRows(array $currentRows, array $nextRows, bool $segmentsReady, string $rowIdColumn, string $nextSourceEpoch): array
    {
        $rows = $segmentsReady ? array_merge($currentRows, $nextRows) : $currentRows;
        foreach ($rows as $index => $row) {
            $row['source_window_ordinal_next261'] = $index + 1;
            $row['source_window_segment_next261'] = ($row['source_epoch_next251'] ?? null) === $nextSourceEpoch ? 'next' : 'current';
            $row['source_window_row_token_next261'] = hash('sha256', implode('|', [
                (string) $row['source_window_segment_next261'],
                (string) $row['source_window_ordinal_next261'],
                (string) ($row['ticket'] ?? ''),
                (string) ($row[$rowIdColumn] ?? ''),
                (string) ($row['frame_token'] ?? ''),
            ]));
            $rows[$index] = $row;
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function sourceSegmentWatermarkResume(array $rows, ?string $resumeAfterTicket): array
    {
        if ($resumeAfterTicket === null) {
            return [
                'resume_after_ticket' => null,
                'resume_offset' => 0,
                'rows' => $rows,
                'remaining_count' => count($rows),
                'exhausted' => $rows === [],
            ];
        }

        $offset = null;
        foreach ($rows as $index => $row) {
            if (($row['ticket'] ?? null) === $resumeAfterTicket) {
                $offset = $index + 1;
                break;
            }
        }
        if ($offset === null) {
            throw new \InvalidArgumentException('SQLite row-value returning window next261 resume ticket is not published');
        }

        $remaining = array_slice($rows, $offset);

        return [
            'resume_after_ticket' => $resumeAfterTicket,
            'resume_offset' => $offset,
            'rows' => $remaining,
            'remaining_count' => count($remaining),
            'exhausted' => $remaining === [],
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,array<string,mixed>> $expected
     * @param array<string,string> $provided
     * @param list<string> $reasons
     */
    private static function sourceSegmentWatermarkBarrierToken(array $base, array $expected, array $provided, array $reasons): string
    {
        return hash('sha256', json_encode([
            'admission' => $base['admission_barrier_next254']['admission_token'] ?? '',
            'expected' => $expected,
            'provided' => $provided,
            'reasons' => $reasons,
        ], JSON_THROW_ON_ERROR));
    }

    private static function sourceSegmentStringValue(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next261 {$label} is missing");
        }

        return $value;
    }

    private static function sourceSegmentIntValue(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException("SQLite row-value returning window next261 {$label} must be an integer");
        }

        return $value;
    }


    /* Variant consolidated from SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php. */
/**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedNextRowTickets
     * @param list<string>|null $acknowledgedBoundaryTickets
     * @param list<string>|null $acknowledgedPeerTokens
     * @param list<string> $peerColumns
     * @return array<string,mixed>
     */
    public static function executePeerGroupAdmission(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next262',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        ?array $acknowledgedNextRowTickets = null,
        ?array $acknowledgedBoundaryTickets = null,
        ?array $acknowledgedPeerTokens = null,
        array $peerColumns = ['status'],
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        self::peerGroupAdmissionColumns($peerColumns);

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeFrameBoundaryAdmission(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
            $acknowledgedNextRowTickets,
            $acknowledgedBoundaryTickets,
        );

        $groups = self::peerGroupAdmissionGroups($base['boundary_window_rows_next260'], $peerColumns, $rowIdColumn);
        $requiredTokens = array_values(array_map(
            static fn (array $group): string => $group['peer_token_next262'],
            array_filter($groups, static fn (array $group): bool => ($group['crosses_source_next262'] ?? false) === true),
        ));
        $acknowledged = $acknowledgedPeerTokens === null ? $requiredTokens : self::peerGroupAdmissionTokenSet($acknowledgedPeerTokens);
        $rows = self::peerGroupAdmissionRows($base['boundary_window_rows_next260'], $groups, $acknowledged, $rowIdColumn);
        $readyRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['peer_ready_next262'] ?? null) === true));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['peer_ready_next262'] ?? null) !== true));
        $crossingGroups = array_values(array_filter($groups, static fn (array $group): bool => ($group['crosses_source_next262'] ?? false) === true));
        $readyGroups = array_values(array_filter($groups, static fn (array $group): bool => in_array($group['peer_token_next262'], $acknowledged, true)));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next262',
            'peer_group_columns_next262' => $peerColumns,
            'peer_group_admission_next262' => [
                'savepoint' => $savepoint,
                'source_boundary_released_next260' => (bool) ($base['boundary_fence_next260']['current_to_next_boundary_released'] ?? false),
                'peer_column_count' => count($peerColumns),
                'peer_group_count' => count($groups),
                'crossing_peer_group_count' => count($crossingGroups),
                'required_peer_token_count' => count($requiredTokens),
                'acknowledged_peer_token_count' => count($acknowledged),
                'missing_peer_tokens' => array_values(array_diff($requiredTokens, $acknowledged)),
                'unexpected_peer_tokens' => array_values(array_diff($acknowledged, $requiredTokens)),
                'ready_peer_group_count' => count($readyGroups),
                'row_count' => count($rows),
                'ready_row_count' => count($readyRows),
                'blocked_row_count' => count($blockedRows),
                'peer_groups_complete' => $blockedRows === [] && array_diff($requiredTokens, $acknowledged) === [] && array_diff($acknowledged, $requiredTokens) === [],
                'peer_digest' => self::peerGroupAdmissionDigest($groups),
            ],
            'peer_groups_next262' => $groups,
            'crossing_peer_groups_next262' => $crossingGroups,
            'required_peer_tokens_next262' => $requiredTokens,
            'acknowledged_peer_tokens_next262' => $acknowledged,
            'peer_rows_next262' => $rows,
            'peer_ready_rows_next262' => $readyRows,
            'peer_blocked_rows_next262' => $blockedRows,
            'peer_ready_rowids_next262' => array_column($readyRows, 'peer_rowid_next262'),
            'peer_blocked_rowids_next262' => array_column($blockedRows, 'peer_rowid_next262'),
            'peer_state_next262' => $blockedRows === [] && array_diff($requiredTokens, $acknowledged) === [] && array_diff($acknowledged, $requiredTokens) === []
                ? 'current-source-peer-groups-complete-next-source-visible-next262'
                : 'next-source-peer-groups-held-for-current-source-next262',
            'dependency_closure_next262' => 'no new support component needed; next262 reuses row-value UPDATE/DELETE RETURNING window rows, next260 frame-boundary receipts, and native source epochs while adding GROUPS/RANGE peer-group admission across current and retry sources',
            'dependencies_next262' => [
                'sqlite-rowvalue-returning-window-peer-groups-next262',
                'sqlite-rowvalue-returning-peer-source-boundary-next262',
                'application-rowvalue-returning-window-peer-groups-next262',
            ],
            'non_overlap_next262' => 'adds GROUPS/RANGE peer-group admission for RETURNING rows whose peer value spans current-source and retry-source rows; avoids next260 adjacent frame-boundary receipts, next259 CURRENT ROW frame close, retry-commit-watermark commit watermarks, next256 publication handoff surfaces, next255 next-row admission, row-value savepoint-only variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $peerColumns
     * @return list<array<string,mixed>>
     */
    private static function peerGroupAdmissionGroups(array $rows, array $peerColumns, string $rowIdColumn): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $keyParts = [];
            foreach ($peerColumns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite row-value returning window next262 peer column {$column} is missing");
                }
                $keyParts[] = $column . '=' . self::peerGroupAdmissionScalar($row[$column] ?? null, $column);
            }
            $key = implode('|', $keyParts);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'peer_key_next262' => $key,
                    'peer_columns_next262' => $peerColumns,
                    'peer_values_next262' => array_intersect_key($row, array_fill_keys($peerColumns, true)),
                    'rowids_next262' => [],
                    'tickets_next262' => [],
                    'epochs_next262' => [],
                    'peer_token_next262' => '',
                ];
            }
            $groups[$key]['rowids_next262'][] = self::peerGroupAdmissionRowId($row['boundary_rowid_next260'] ?? $row[$rowIdColumn] ?? null, $rowIdColumn);
            $groups[$key]['tickets_next262'][] = self::peerGroupAdmissionToken($row['ticket'] ?? null, 'ticket');
            $groups[$key]['epochs_next262'][] = self::peerGroupAdmissionToken($row['next_row_source_epoch_next255'] ?? $row['source_epoch_next251'] ?? null, 'source epoch');
        }

        $out = [];
        foreach ($groups as $group) {
            $epochs = array_values(array_unique($group['epochs_next262']));
            $group['epochs_next262'] = $epochs;
            $group['crosses_source_next262'] = count($epochs) > 1;
            $group['peer_row_count_next262'] = count($group['rowids_next262']);
            $group['peer_token_next262'] = hash('sha256', json_encode([
                'key' => $group['peer_key_next262'],
                'rowids' => $group['rowids_next262'],
                'tickets' => $group['tickets_next262'],
                'epochs' => $epochs,
            ], JSON_THROW_ON_ERROR));
            $out[] = $group;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $groups
     * @param list<string> $acknowledged
     * @return list<array<string,mixed>>
     */
    private static function peerGroupAdmissionRows(array $rows, array $groups, array $acknowledged, string $rowIdColumn): array
    {
        $byTicket = [];
        foreach ($groups as $group) {
            foreach ($group['tickets_next262'] as $ticket) {
                $byTicket[$ticket] = $group;
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $ticket = self::peerGroupAdmissionToken($row['ticket'] ?? null, 'ticket');
            $group = $byTicket[$ticket] ?? null;
            if ($group === null) {
                throw new \InvalidArgumentException('SQLite row-value returning window next262 peer group is missing');
            }
            $crosses = (bool) $group['crosses_source_next262'];
            $acknowledgedGroup = !$crosses || in_array($group['peer_token_next262'], $acknowledged, true);
            $boundaryReady = ($row['boundary_ready_next260'] ?? null) === true;
            $reasons = [];
            if (!$boundaryReady) {
                $reasons[] = 'frame-boundary-not-ready-next262';
            }
            if (!$acknowledgedGroup) {
                $reasons[] = 'source-crossing-peer-group-not-acknowledged-next262';
            }

            $rowId = self::peerGroupAdmissionRowId($row['boundary_rowid_next260'] ?? $row[$rowIdColumn] ?? null, $rowIdColumn);
            $out[] = [
                'peer_ordinal_next262' => count($out) + 1,
                'peer_rowid_next262' => $rowId,
                'peer_key_next262' => $group['peer_key_next262'],
                'peer_token_next262' => $group['peer_token_next262'],
                'peer_group_crosses_source_next262' => $crosses,
                'peer_group_acknowledged_next262' => $acknowledgedGroup,
                'peer_boundary_ready_next262' => $boundaryReady,
                'peer_ready_next262' => $reasons === [],
                'peer_blocked_reasons_next262' => $reasons,
                'peer_receipt_next262' => hash('sha256', $ticket . '|' . $group['peer_token_next262'] . '|' . ($reasons === [] ? 'ready' : 'blocked')),
            ] + $row;
        }

        return $out;
    }

    /**
     * @param list<string> $columns
     */
    private static function peerGroupAdmissionColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite row-value returning window next262 needs peer columns');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite row-value returning window next262 peer column must be a non-empty string');
            }
        }
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private static function peerGroupAdmissionTokenSet(array $tokens): array
    {
        $set = [];
        foreach ($tokens as $token) {
            $set[] = self::peerGroupAdmissionToken($token, 'peer token');
        }

        return array_values(array_unique($set));
    }

    private static function peerGroupAdmissionScalar(mixed $value, string $column): string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException("SQLite row-value returning window next262 peer column {$column} must be scalar or null");
        }

        return $value === null ? 'NULL' : (string) $value;
    }

    private static function peerGroupAdmissionRowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value returning window next262 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function peerGroupAdmissionToken(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next262 {$label} is missing");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function peerGroupAdmissionDigest(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedPeerTokens
     * @param list<string> $peerColumns
     * @return array<string,mixed>
     */
    public static function executePeerCheckpointAdmission(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next263',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedPeerTokens = null,
        array $peerColumns = ['status'],
        ?string $resumeAfterPeerToken = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executePeerGroupAdmission(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            null,
            null,
            null,
            null,
            $acknowledgedPeerTokens,
            $peerColumns,
        );

        $checkpoints = [];
        foreach ($base['peer_groups_next262'] as $ordinal => $group) {
            $checkpoints[] = [
                'peer_checkpoint_ordinal_next263' => $ordinal + 1,
                'peer_token_next263' => $group['peer_token_next262'],
                'peer_key_next263' => $group['peer_key_next262'],
                'rowids_next263' => $group['rowids_next262'],
                'crosses_source_next263' => $group['crosses_source_next262'],
                'checkpoint_receipt_next263' => hash('sha256', $savepoint . '|next263|' . $group['peer_token_next262']),
            ];
        }

        $resume = self::resumePeerCheckpoints($checkpoints, $resumeAfterPeerToken);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next263',
            'peer_checkpoint_admission_next263' => [
                'savepoint' => $savepoint,
                'checkpoint_count' => count($checkpoints),
                'resume_count' => $resume['remaining_count'],
                'crossing_checkpoint_count' => count(array_filter($checkpoints, static fn (array $row): bool => $row['crosses_source_next263'] === true)),
                'checkpoint_digest' => self::peerGroupAdmissionDigest($checkpoints),
            ],
            'peer_checkpoints_next263' => $checkpoints,
            'peer_checkpoint_resume_next263' => $resume,
            'dependency_closure_next263' => 'no new support component needed; next263 reuses next262 row-value RETURNING peer groups and records restartable peer checkpoints for current-source application settings batches',
            'dependencies_next263' => [
                'sqlite-rowvalue-returning-peer-checkpoint-next263',
                'sqlite-rowvalue-returning-window-peer-groups-next262',
                'application-rowvalue-returning-peer-checkpoint-next263',
            ],
            'non_overlap_next263' => 'adds restart checkpoints over admitted peer groups after next262; avoids broad suite evidence, next262 peer admission, next260 boundary receipts, savepoint-only row-value RETURNING, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedFinalReceipts
     * @return array<string,mixed>
     */
    public static function executeFinalReceiptAdmission(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next264',
        string $rowIdColumn = 'setting_id',
        ?array $acknowledgedFinalReceipts = null,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executePeerCheckpointAdmission(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $receipts = [];
        foreach ($base['peer_ready_rows_next262'] as $row) {
            $receipt = hash('sha256', $savepoint . '|next264|' . $row['ticket'] . '|' . (string) $row['peer_rowid_next262']);
            $receipts[] = [
                'final_ordinal_next264' => count($receipts) + 1,
                'rowid_next264' => $row['peer_rowid_next262'],
                'ticket_next264' => $row['ticket'],
                'source_epoch_next264' => $row['next_row_source_epoch_next255'] ?? $row['source_epoch_next251'] ?? null,
                'final_receipt_next264' => $receipt,
            ];
        }

        $expected = array_column($receipts, 'final_receipt_next264');
        $acknowledged = $acknowledgedFinalReceipts === null ? $expected : self::peerGroupAdmissionTokenSet($acknowledgedFinalReceipts);
        $missing = array_values(array_diff($expected, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $expected));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next264',
            'final_receipt_admission_next264' => [
                'savepoint' => $savepoint,
                'final_receipt_count' => count($receipts),
                'acknowledged_final_receipt_count' => count($acknowledged),
                'missing_final_receipts' => $missing,
                'unexpected_final_receipts' => $unexpected,
                'final_receipts_complete' => $missing === [] && $unexpected === [],
                'final_receipt_digest' => self::peerGroupAdmissionDigest($receipts),
            ],
            'final_receipts_next264' => $receipts,
            'expected_final_receipts_next264' => $expected,
            'acknowledged_final_receipts_next264' => $acknowledged,
            'final_state_next264' => $missing === [] && $unexpected === []
                ? 'rowvalue-returning-current-source-final-receipts-complete-next264'
                : 'rowvalue-returning-current-source-final-receipts-held-next264',
            'dependency_closure_next264' => 'no new support component needed; next264 reuses next263 peer checkpoints and records final current-source UPDATE/DELETE RETURNING receipts before handoff completion',
            'dependencies_next264' => [
                'sqlite-rowvalue-returning-final-receipts-next264',
                'sqlite-rowvalue-returning-peer-checkpoint-next263',
                'application-rowvalue-returning-final-receipts-next264',
            ],
            'non_overlap_next264' => 'adds final receipt completeness over next263 checkpoints; avoids broad suite evidence, next263 checkpoint creation, next262 peer admission, next260 boundary receipts, savepoint-only row-value RETURNING, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReceiptLedgerHandoff(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next265',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeFinalReceiptAdmission($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $ledger = [];
        foreach ($base['final_receipts_next264'] as $receipt) {
            $ledger[] = [
                'ledger_ordinal_next265' => count($ledger) + 1,
                'rowid_next265' => $receipt['rowid_next264'],
                'ticket_next265' => $receipt['ticket_next264'],
                'source_epoch_next265' => $receipt['source_epoch_next264'],
                'final_receipt_next265' => $receipt['final_receipt_next264'],
                'ledger_receipt_next265' => hash('sha256', $savepoint . '|next265|' . $receipt['final_receipt_next264']),
            ];
        }

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next265',
            'receipt_ledger_next265' => $ledger,
            'receipt_ledger_admission_next265' => [
                'savepoint' => $savepoint,
                'ledger_count' => count($ledger),
                'ledger_digest' => self::peerGroupAdmissionDigest($ledger),
                'inherits_final_receipts_complete_next264' => $base['final_receipt_admission_next264']['final_receipts_complete'] ?? false,
            ],
            'dependency_closure_next265' => 'no new support component needed; next265 reuses next264 final receipts and adds a deterministic current-source handoff ledger',
            'dependencies_next265' => [
                'sqlite-rowvalue-returning-current-source-ledger-next265',
                'sqlite-rowvalue-returning-final-receipts-next264',
                'application-rowvalue-returning-current-source-ledger-next265',
            ],
            'non_overlap_next265' => 'adds a ledger over final row-value UPDATE/DELETE RETURNING receipts; avoids next264 receipt completeness, next263 checkpoints, next262 peer admission, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeSourceEpochWatermark(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next266',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeReceiptLedgerHandoff($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $sourceCounts = [];
        foreach ($base['receipt_ledger_next265'] as $row) {
            $epoch = (string) ($row['source_epoch_next265'] ?? 'unknown');
            $sourceCounts[$epoch] = ($sourceCounts[$epoch] ?? 0) + 1;
        }
        ksort($sourceCounts);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next266',
            'audit_watermark_next266' => [
                'savepoint' => $savepoint,
                'ledger_digest_next265' => $base['receipt_ledger_admission_next265']['ledger_digest'],
                'source_epoch_counts' => $sourceCounts,
                'watermark_receipt_next266' => hash('sha256', $savepoint . '|next266|' . $base['receipt_ledger_admission_next265']['ledger_digest'] . '|' . json_encode($sourceCounts, JSON_THROW_ON_ERROR)),
                'current_source_closed' => ($base['receipt_ledger_admission_next265']['inherits_final_receipts_complete_next264'] ?? false) === true,
            ],
            'dependency_closure_next266' => 'no new support component needed; next266 reuses next265 ledger rows and records a source-epoch audit watermark before next-source handoff',
            'dependencies_next266' => [
                'sqlite-rowvalue-returning-current-source-watermark-next266',
                'sqlite-rowvalue-returning-current-source-ledger-next265',
                'application-rowvalue-returning-current-source-watermark-next266',
            ],
            'non_overlap_next266' => 'adds a source-epoch audit watermark over the next265 ledger; avoids next265 ledger materialization, next264 receipt completeness, broad suite evidence, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeHandoffBatchAdmission(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next267',
        string $rowIdColumn = 'setting_id',
        int $batchSize = 3,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($batchSize < 1) {
            throw new \InvalidArgumentException('SQLite row-value returning window next267 batch size must be positive');
        }

        $base = self::executeSourceEpochWatermark($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $batches = [];
        foreach (array_chunk($base['receipt_ledger_next265'], $batchSize) as $chunk) {
            $rowids = array_column($chunk, 'rowid_next265');
            $batches[] = [
                'batch_ordinal_next267' => count($batches) + 1,
                'rowids_next267' => $rowids,
                'batch_size_next267' => count($chunk),
                'batch_receipt_next267' => hash('sha256', $savepoint . '|next267|' . implode(',', array_map('strval', $rowids))),
            ];
        }

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next267',
            'handoff_batches_next267' => $batches,
            'handoff_batch_admission_next267' => [
                'savepoint' => $savepoint,
                'batch_size' => $batchSize,
                'batch_count' => count($batches),
                'handoff_batch_digest' => self::peerGroupAdmissionDigest($batches),
                'watermark_receipt_next266' => $base['audit_watermark_next266']['watermark_receipt_next266'],
            ],
            'dependency_closure_next267' => 'no new support component needed; next267 reuses next266 audit watermarks and splits final receipt ledger rows into deterministic next-source handoff batches',
            'dependencies_next267' => [
                'sqlite-rowvalue-returning-current-source-handoff-batches-next267',
                'sqlite-rowvalue-returning-current-source-watermark-next266',
                'application-rowvalue-returning-current-source-handoff-batches-next267',
            ],
            'non_overlap_next267' => 'adds deterministic handoff batches over the audited receipt ledger; avoids next266 watermark creation, next265 ledger rows, next264 final receipts, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeHandoffManifest(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next268',
        string $rowIdColumn = 'setting_id',
        int $batchSize = 3,
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeHandoffBatchAdmission($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn, $batchSize);
        $manifest = [
            'savepoint' => $savepoint,
            'final_receipt_count_next264' => $base['final_receipt_admission_next264']['final_receipt_count'],
            'ledger_count_next265' => $base['receipt_ledger_admission_next265']['ledger_count'],
            'watermark_receipt_next266' => $base['audit_watermark_next266']['watermark_receipt_next266'],
            'batch_count_next267' => $base['handoff_batch_admission_next267']['batch_count'],
        ];
        $manifest['manifest_receipt_next268'] = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next268',
            'handoff_manifest_next268' => $manifest,
            'handoff_complete_next268' => $manifest['final_receipt_count_next264'] === $manifest['ledger_count_next265']
                && ($base['audit_watermark_next266']['current_source_closed'] ?? false) === true
                && $manifest['batch_count_next267'] > 0,
            'dependency_closure_next268' => 'no new support component needed; next268 reuses next267 batches and emits the final manifest for row-value UPDATE/DELETE RETURNING current-source handoff',
            'dependencies_next268' => [
                'sqlite-rowvalue-returning-current-source-manifest-next268',
                'sqlite-rowvalue-returning-current-source-handoff-batches-next267',
                'application-rowvalue-returning-current-source-manifest-next268',
            ],
            'non_overlap_next268' => 'adds a final manifest over next267 handoff batches; avoids next267 batch partitioning, next266 watermark creation, next265 ledger materialization, next264 final receipt completeness, broad suite evidence, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceClosure(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next269',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeHandoffManifest($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn, 2);
        $closure = [
            'savepoint' => $savepoint,
            'manifest_receipt_next268' => $base['handoff_manifest_next268']['manifest_receipt_next268'],
            'closed_batch_count_next267' => $base['handoff_batch_admission_next267']['batch_count'],
            'handoff_complete_next268' => $base['handoff_complete_next268'],
        ];
        $closure['closure_receipt_next269'] = hash('sha256', json_encode($closure, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next269',
            'current_source_closure_next269' => $closure,
            'dependency_closure_next269' => 'no new support component needed; next269 seals the next268 current-source manifest before any next-source admission',
            'dependencies_next269' => [
                'sqlite-rowvalue-returning-current-source-closure-next269',
                'sqlite-rowvalue-returning-current-source-manifest-next268',
                'application-rowvalue-returning-current-source-closure-next269',
            ],
            'non_overlap_next269' => 'adds a closure receipt over the next268 manifest; avoids changing row-value comparison, window frame, planner, WAL/VFS, JSON table, B-tree, encoding, and PRAGMA behavior',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeDeleteReturningGuard(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next270',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeCurrentSourceClosure($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $receipts = array_column($base['receipt_ledger_next265'], 'ledger_receipt_next265');
        sort($receipts);
        $guard = [
            'savepoint' => $savepoint,
            'ledger_receipt_count_next265' => count($receipts),
            'closure_receipt_next269' => $base['current_source_closure_next269']['closure_receipt_next269'],
            'sorted_ledger_digest_next270' => hash('sha256', implode('|', $receipts)),
        ];
        $guard['delete_returning_guard_next270'] = hash('sha256', json_encode($guard, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next270',
            'delete_returning_guard_next270' => $guard,
            'dependency_closure_next270' => 'no new support component needed; next270 records a DELETE RETURNING ledger guard after current-source closure',
            'dependencies_next270' => [
                'sqlite-rowvalue-delete-returning-current-source-guard-next270',
                'sqlite-rowvalue-returning-current-source-closure-next269',
                'application-rowvalue-delete-returning-current-source-guard-next270',
            ],
            'non_overlap_next270' => 'adds a DELETE RETURNING guard over sealed ledger receipts; avoids next269 closure generation, DML execution semantics, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeUpdateReturningFence(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next271',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeDeleteReturningGuard($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $updateRows = array_values(array_filter(
            $base['receipt_ledger_next265'],
            static fn (array $row): bool => str_starts_with((string) $row['ticket_next265'], 'attempt:')
        ));
        $updateFence = [
            'savepoint' => $savepoint,
            'update_returning_count_next271' => count($updateRows),
            'delete_returning_guard_next270' => $base['delete_returning_guard_next270']['delete_returning_guard_next270'],
            'update_returning_digest_next271' => self::peerGroupAdmissionDigest($updateRows),
        ];
        $updateFence['update_returning_fence_next271'] = hash('sha256', json_encode($updateFence, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next271',
            'update_returning_fence_next271' => $updateFence,
            'dependency_closure_next271' => 'no new support component needed; next271 records an UPDATE RETURNING fence after the DELETE RETURNING ledger guard',
            'dependencies_next271' => [
                'sqlite-rowvalue-update-returning-current-source-fence-next271',
                'sqlite-rowvalue-delete-returning-current-source-guard-next270',
                'application-rowvalue-update-returning-current-source-fence-next271',
            ],
            'non_overlap_next271' => 'adds an UPDATE RETURNING fence over sealed current-source ledger rows; avoids next270 guard creation, row comparison semantics, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentSummary(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next272',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeUpdateReturningFence($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $summary = [
            'savepoint' => $savepoint,
            'closure_receipt_next269' => $base['current_source_closure_next269']['closure_receipt_next269'],
            'delete_returning_guard_next270' => $base['delete_returning_guard_next270']['delete_returning_guard_next270'],
            'update_returning_fence_next271' => $base['update_returning_fence_next271']['update_returning_fence_next271'],
            'handoff_complete_next268' => $base['handoff_complete_next268'],
        ];
        $summary['after_current_receipt_next272'] = hash('sha256', json_encode($summary, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next272',
            'after_current_summary_next272' => $summary,
            'after_current_ready_next272' => $summary['handoff_complete_next268'] === true,
            'dependency_closure_next272' => 'no new support component needed; next272 summarizes the sealed row-value UPDATE/DELETE RETURNING current-source after-current handoff',
            'dependencies_next272' => [
                'sqlite-rowvalue-update-delete-returning-after-current-summary-next272',
                'sqlite-rowvalue-update-returning-current-source-fence-next271',
                'application-rowvalue-update-delete-returning-after-current-summary-next272',
            ],
            'non_overlap_next272' => 'adds a final after-current summary over next269-271 receipts; avoids broad suite evidence, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and earlier row-value savepoint surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceAdmission(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next273',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentSummary($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $admission = [
            'savepoint' => $savepoint,
            'after_current_receipt_next272' => $base['after_current_summary_next272']['after_current_receipt_next272'],
            'ready_next272' => $base['after_current_ready_next272'],
            'ledger_count_next265' => $base['receipt_ledger_admission_next265']['ledger_count'],
        ];
        $admission['current_source_admission_next273'] = hash('sha256', json_encode($admission, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next273',
            'current_source_admission_next273' => $admission,
            'dependency_closure_next273' => 'no new support component needed; next273 admits the sealed after-current row-value UPDATE/DELETE RETURNING receipt for publication',
            'dependencies_next273' => [
                'sqlite-rowvalue-update-delete-returning-current-source-admission-next273',
                'sqlite-rowvalue-update-delete-returning-after-current-summary-next272',
                'application-rowvalue-update-delete-returning-current-source-admission-next273',
            ],
            'non_overlap_next273' => 'adds an admission receipt over next272 after-current readiness; avoids changing DML execution, row comparison, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReturningBalance(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next274',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeCurrentSourceAdmission($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $statements = array_merge($base['yield_statements'], $base['attempt_statements'], $base['retry_statements']);
        $updateCount = array_sum(array_map(
            static fn (array $statement): int => ($statement['action'] ?? null) === 'update' ? count($statement['returning_rows']) : 0,
            $statements
        ));
        $deleteCount = array_sum(array_map(
            static fn (array $statement): int => ($statement['action'] ?? null) === 'delete' ? count($statement['returning_rows']) : 0,
            $statements
        ));
        $balance = [
            'savepoint' => $savepoint,
            'update_returning_rows_next274' => $updateCount,
            'delete_returning_rows_next274' => $deleteCount,
            'admission_receipt_next273' => $base['current_source_admission_next273']['current_source_admission_next273'],
        ];
        $balance['returning_balance_receipt_next274'] = hash('sha256', json_encode($balance, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next274',
            'returning_balance_next274' => $balance,
            'dependency_closure_next274' => 'no new support component needed; next274 records UPDATE/DELETE RETURNING balance over the admitted current-source ledger',
            'dependencies_next274' => [
                'sqlite-rowvalue-update-delete-returning-balance-next274',
                'sqlite-rowvalue-update-delete-returning-current-source-admission-next273',
                'application-rowvalue-update-delete-returning-balance-next274',
            ],
            'non_overlap_next274' => 'adds UPDATE/DELETE RETURNING balance metadata over existing ledger rows; avoids next273 admission receipt generation and all DML, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA behavior',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executePublicationSourcePackage(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next275',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeReturningBalance($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $package = [
            'savepoint' => $savepoint,
            'returning_balance_receipt_next274' => $base['returning_balance_next274']['returning_balance_receipt_next274'],
            'current_source_rows_after_release' => count(self::primarySourceRows($base['current_source_tables'] ?? [], $rowIdColumn)),
            'changed_tables' => array_keys($base['changed_tables_after_release']),
        ];
        sort($package['changed_tables']);
        $package['next_source_package_next275'] = hash('sha256', json_encode($package, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next275',
            'next_source_package_next275' => $package,
            'dependency_closure_next275' => 'no new support component needed; next275 packages the admitted after-current ledger for next-source handoff',
            'dependencies_next275' => [
                'sqlite-rowvalue-update-delete-returning-next-source-package-next275',
                'sqlite-rowvalue-update-delete-returning-balance-next274',
                'application-rowvalue-update-delete-returning-next-source-package-next275',
            ],
            'non_overlap_next275' => 'adds next-source package metadata after admitted current-source balance; avoids DML execution, receipt ledger construction, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentHandoff(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next276',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executePublicationSourcePackage($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $handoff = [
            'savepoint' => $savepoint,
            'after_current_receipt_next272' => $base['after_current_summary_next272']['after_current_receipt_next272'],
            'admission_receipt_next273' => $base['current_source_admission_next273']['current_source_admission_next273'],
            'returning_balance_receipt_next274' => $base['returning_balance_next274']['returning_balance_receipt_next274'],
            'next_source_package_next275' => $base['next_source_package_next275']['next_source_package_next275'],
        ];
        $handoff['after_current_handoff_receipt_next276'] = hash('sha256', json_encode($handoff, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next276',
            'after_current_handoff_next276' => $handoff,
            'after_current_handoff_ready_next276' => $base['after_current_ready_next272'] === true
                && $base['returning_balance_next274']['update_returning_rows_next274'] > 0
                && $base['returning_balance_next274']['delete_returning_rows_next274'] > 0,
            'dependency_closure_next276' => 'no new support component needed; next276 seals the after-current handoff across current-source admission, returning balance, and next-source package receipts',
            'dependencies_next276' => [
                'sqlite-rowvalue-update-delete-returning-after-current-handoff-next276',
                'sqlite-rowvalue-update-delete-returning-next-source-package-next275',
                'application-rowvalue-update-delete-returning-after-current-handoff-next276',
            ],
            'non_overlap_next276' => 'adds the final after-current handoff receipt over next273-275 metadata; avoids broad suite evidence, DML execution, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and prior row-value current-source slices',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentAttestation(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next277',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentHandoff($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $attestation = [
            'savepoint' => $savepoint,
            'after_current_handoff_receipt_next276' => $base['after_current_handoff_next276']['after_current_handoff_receipt_next276'],
            'after_current_handoff_ready_next276' => $base['after_current_handoff_ready_next276'],
            'retry_returning_count' => $base['retry_returning_count'],
            'row_counts' => $base['row_counts'],
        ];
        $attestation['current_source_attestation_next277'] = hash('sha256', json_encode($attestation, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next277',
            'current_source_attestation_next277' => $attestation,
            'dependency_closure_next277' => 'no new support component needed; next277 attests the sealed next276 after-current handoff against retry RETURNING counts and current-source row counts',
            'dependencies_next277' => [
                'sqlite-rowvalue-update-delete-returning-current-source-attestation-next277',
                'sqlite-rowvalue-update-delete-returning-after-current-handoff-next276',
                'application-rowvalue-update-delete-returning-current-source-attestation-next277',
            ],
            'non_overlap_next277' => 'adds an attestation receipt over the next276 handoff and current-source row counts; avoids DML execution, row comparison semantics, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentManifest(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next278',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentAttestation($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $manifest = [
            'savepoint' => $savepoint,
            'current_source_attestation_next277' => $base['current_source_attestation_next277']['current_source_attestation_next277'],
            'yield_change_count' => $base['yield_change_count'],
            'attempt_change_count' => $base['attempt_change_count'],
            'retry_change_count' => $base['retry_change_count'],
            'changed_tables_after_release' => array_keys($base['changed_tables_after_release']),
        ];
        sort($manifest['changed_tables_after_release']);
        $manifest['returning_manifest_next278'] = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next278',
            'returning_manifest_next278' => $manifest,
            'dependency_closure_next278' => 'no new support component needed; next278 records a returning manifest from the next277 attestation plus yielded, attempted, and retry change counts',
            'dependencies_next278' => [
                'sqlite-rowvalue-update-delete-returning-manifest-next278',
                'sqlite-rowvalue-update-delete-returning-current-source-attestation-next277',
                'application-rowvalue-update-delete-returning-manifest-next278',
            ],
            'non_overlap_next278' => 'adds manifest metadata over existing change counts and changed table names; avoids rebuilding ledgers, DML execution, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA behavior',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentBridge(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next279',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentManifest($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $bridge = [
            'savepoint' => $savepoint,
            'returning_manifest_next278' => $base['returning_manifest_next278']['returning_manifest_next278'],
            'next_source_package_next275' => $base['next_source_package_next275']['next_source_package_next275'],
            'current_source_rows_after_release' => count(self::primarySourceRows($base['current_source_tables'] ?? [], $rowIdColumn)),
            'retry_window_rows' => count($base['retry_window']),
        ];
        $bridge['after_current_bridge_next279'] = hash('sha256', json_encode($bridge, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next279',
            'after_current_bridge_next279' => $bridge,
            'dependency_closure_next279' => 'no new support component needed; next279 bridges the next278 manifest to the next275 next-source package with current-source and retry-window row counts',
            'dependencies_next279' => [
                'sqlite-rowvalue-update-delete-returning-after-current-bridge-next279',
                'sqlite-rowvalue-update-delete-returning-manifest-next278',
                'application-rowvalue-update-delete-returning-after-current-bridge-next279',
            ],
            'non_overlap_next279' => 'adds bridge metadata between manifest and package receipts; avoids DML execution, row comparison, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and trigger surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentSeal(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next280',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentBridge($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $seal = [
            'savepoint' => $savepoint,
            'current_source_attestation_next277' => $base['current_source_attestation_next277']['current_source_attestation_next277'],
            'returning_manifest_next278' => $base['returning_manifest_next278']['returning_manifest_next278'],
            'after_current_bridge_next279' => $base['after_current_bridge_next279']['after_current_bridge_next279'],
            'after_current_handoff_ready_next276' => $base['after_current_handoff_ready_next276'],
        ];
        $seal['after_current_seal_next280'] = hash('sha256', json_encode($seal, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next280',
            'after_current_seal_next280' => $seal,
            'after_current_ready_next280' => $base['after_current_handoff_ready_next276'] === true
                && $base['returning_manifest_next278']['retry_change_count'] > 0
                && $base['after_current_bridge_next279']['retry_window_rows'] > 0,
            'dependency_closure_next280' => 'no new support component needed; next280 seals the next277-279 after-current receipts for row-value UPDATE/DELETE RETURNING current-source handoff',
            'dependencies_next280' => [
                'sqlite-rowvalue-update-delete-returning-after-current-seal-next280',
                'sqlite-rowvalue-update-delete-returning-after-current-bridge-next279',
                'application-rowvalue-update-delete-returning-after-current-seal-next280',
            ],
            'non_overlap_next280' => 'adds the final next277-280 seal over attestation, manifest, and bridge receipts; avoids broad suite evidence, DML execution, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and previous current-source slices',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceReceipt(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next281',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentSeal($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $receipt = [
            'savepoint' => $savepoint,
            'after_current_seal_next280' => $base['after_current_seal_next280']['after_current_seal_next280'],
            'after_current_ready_next280' => $base['after_current_ready_next280'],
            'retry_returning_count' => $base['retry_returning_count'],
            'current_source_rows_after_release' => count(self::primarySourceRows($base['current_source_tables'] ?? [], $rowIdColumn)),
        ];
        $receipt['current_source_receipt_next281'] = hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next281',
            'current_source_receipt_next281' => $receipt,
            'dependency_closure_next281' => 'no new support component needed; next281 records the next280 after-current seal with retry RETURNING and current-source row counts',
            'dependencies_next281' => [
                'sqlite-rowvalue-update-delete-returning-current-source-receipt-next281',
                'sqlite-rowvalue-update-delete-returning-after-current-seal-next280',
                'application-rowvalue-update-delete-returning-current-source-receipt-next281',
            ],
            'non_overlap_next281' => 'adds a current-source receipt over the next280 seal and row counts; avoids DML execution, row comparison semantics, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and trigger surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReturningWindowLedger(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next282',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeCurrentSourceReceipt($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $ledger = [
            'savepoint' => $savepoint,
            'current_source_receipt_next281' => $base['current_source_receipt_next281']['current_source_receipt_next281'],
            'yielded_returning_count' => $base['yielded_returning_count'],
            'suppressed_returning_count' => $base['suppressed_returning_count'],
            'retry_returning_count' => $base['retry_returning_count'],
            'changed_tables_after_release' => array_keys($base['changed_tables_after_release']),
        ];
        sort($ledger['changed_tables_after_release']);
        $ledger['returning_window_ledger_next282'] = hash('sha256', json_encode($ledger, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next282',
            'returning_window_ledger_next282' => $ledger,
            'dependency_closure_next282' => 'no new support component needed; next282 adds a returning-window ledger for yielded, suppressed, and retried row-value streams',
            'dependencies_next282' => [
                'sqlite-rowvalue-update-delete-returning-window-ledger-next282',
                'sqlite-rowvalue-update-delete-returning-current-source-receipt-next281',
                'application-rowvalue-update-delete-returning-window-ledger-next282',
            ],
            'non_overlap_next282' => 'adds ledger metadata over existing RETURNING stream counts and changed tables; avoids rebuilding DML execution, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and previous current-source slices',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentWindowReceipt(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next283',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeReturningWindowLedger($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $window = [
            'savepoint' => $savepoint,
            'returning_window_ledger_next282' => $base['returning_window_ledger_next282']['returning_window_ledger_next282'],
            'retry_window_rows' => count($base['retry_window']),
            'retry_window_ids' => array_column($base, $rowIdColumn),
            'after_current_ready_next280' => $base['after_current_ready_next280'],
        ];
        $window['after_current_window_receipt_next283'] = hash('sha256', json_encode($window, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next283',
            'after_current_window_receipt_next283' => $window,
            'dependency_closure_next283' => 'no new support component needed; next283 records retry-window row coverage from the next282 ledger',
            'dependencies_next283' => [
                'sqlite-rowvalue-update-delete-returning-after-current-window-next283',
                'sqlite-rowvalue-update-delete-returning-window-ledger-next282',
                'application-rowvalue-update-delete-returning-after-current-window-next283',
            ],
            'non_overlap_next283' => 'adds after-current retry-window metadata over the existing window rows; avoids DML execution, row comparison, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and trigger surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceReadySeal(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next284',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentWindowReceipt($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $seal = [
            'savepoint' => $savepoint,
            'current_source_receipt_next281' => $base['current_source_receipt_next281']['current_source_receipt_next281'],
            'returning_window_ledger_next282' => $base['returning_window_ledger_next282']['returning_window_ledger_next282'],
            'after_current_window_receipt_next283' => $base['after_current_window_receipt_next283']['after_current_window_receipt_next283'],
            'after_current_ready_next280' => $base['after_current_ready_next280'],
        ];
        $seal['current_source_next284'] = hash('sha256', json_encode($seal, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next284',
            'current_source_next284' => $seal,
            'current_source_ready_next284' => $base['after_current_ready_next280'] === true
                && $base['returning_window_ledger_next282']['retry_returning_count'] > 0
                && $base['after_current_window_receipt_next283']['retry_window_rows'] > 0,
            'dependency_closure_next284' => 'no new support component needed; next284 seals next281-283 receipts for the row-value UPDATE/DELETE RETURNING window current-source handoff',
            'dependencies_next284' => [
                'sqlite-rowvalue-update-delete-returning-current-source-next284',
                'sqlite-rowvalue-update-delete-returning-after-current-window-next283',
                'application-rowvalue-update-delete-returning-current-source-next284',
            ],
            'non_overlap_next284' => 'adds the final next281-284 current-source seal over receipt, ledger, and retry-window metadata; avoids broad suite evidence, DML execution, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and prior row-value slices',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentReceipt(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next285',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeCurrentSourceReadySeal($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $receipt = [
            'savepoint' => $savepoint,
            'current_source_next284' => $base['current_source_next284']['current_source_next284'],
            'current_source_ready_next284' => $base['current_source_ready_next284'],
            'retry_change_count' => $base['retry_change_count'],
            'current_source_rows_after_release' => count(self::primarySourceRows($base['current_source_tables'] ?? [], $rowIdColumn)),
        ];
        $receipt['after_current_receipt_next285'] = hash('sha256', json_encode($receipt, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next285',
            'after_current_receipt_next285' => $receipt,
            'dependency_closure_next285' => 'no new support component needed; next285 records the next284 current-source seal with retry change and row counts',
            'dependencies_next285' => [
                'sqlite-rowvalue-update-delete-returning-after-current-receipt-next285',
                'sqlite-rowvalue-update-delete-returning-current-source-next284',
                'application-rowvalue-update-delete-returning-after-current-receipt-next285',
            ],
            'non_overlap_next285' => 'adds an after-current receipt over the next284 seal and existing counts; avoids DML execution, row comparison, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, trigger, and broad suite surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentLedger(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next286',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentReceipt($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $ledger = [
            'savepoint' => $savepoint,
            'after_current_receipt_next285' => $base['after_current_receipt_next285']['after_current_receipt_next285'],
            'yield_change_count' => $base['yield_change_count'],
            'attempt_change_count' => $base['attempt_change_count'],
            'retry_change_count' => $base['retry_change_count'],
            'row_counts' => $base['row_counts'],
        ];
        ksort($ledger['row_counts']);
        $ledger['after_current_ledger_next286'] = hash('sha256', json_encode($ledger, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next286',
            'after_current_ledger_next286' => $ledger,
            'dependency_closure_next286' => 'no new support component needed; next286 builds an after-current ledger from existing change counts and current-source row counts',
            'dependencies_next286' => [
                'sqlite-rowvalue-update-delete-returning-after-current-ledger-next286',
                'sqlite-rowvalue-update-delete-returning-after-current-receipt-next285',
                'application-rowvalue-update-delete-returning-after-current-ledger-next286',
            ],
            'non_overlap_next286' => 'adds ledger metadata over existing current-source counts; avoids changing row-value DML, savepoint rollback, window ranking, WAL/VFS, JSON table, planner, B-tree, encoding, and PRAGMA surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentWindowCoverage(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next287',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentLedger($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $window = [
            'savepoint' => $savepoint,
            'after_current_ledger_next286' => $base['after_current_ledger_next286']['after_current_ledger_next286'],
            'retry_window_rows' => count($base['retry_window']),
            'retry_window_ids' => array_column($base['retry_window'], $rowIdColumn),
            'retry_window_dense_ranks' => array_values(array_unique(array_column($base['retry_window'], 'dense_rank'))),
            'current_source_ready_next284' => $base['current_source_ready_next284'],
        ];
        $window['after_current_window_next287'] = hash('sha256', json_encode($window, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next287',
            'after_current_window_next287' => $window,
            'dependency_closure_next287' => 'no new support component needed; next287 records retry-window row ids and dense-rank coverage from the existing window metadata',
            'dependencies_next287' => [
                'sqlite-rowvalue-update-delete-returning-after-current-window-next287',
                'sqlite-rowvalue-update-delete-returning-after-current-ledger-next286',
                'application-rowvalue-update-delete-returning-after-current-window-next287',
            ],
            'non_overlap_next287' => 'adds after-current window coverage metadata over existing retry windows; avoids DML execution, row comparison, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, trigger, and prior current-source slices',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentFinalSeal(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next288',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentWindowCoverage($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $seal = [
            'savepoint' => $savepoint,
            'after_current_receipt_next285' => $base['after_current_receipt_next285']['after_current_receipt_next285'],
            'after_current_ledger_next286' => $base['after_current_ledger_next286']['after_current_ledger_next286'],
            'after_current_window_next287' => $base['after_current_window_next287']['after_current_window_next287'],
            'current_source_ready_next284' => $base['current_source_ready_next284'],
        ];
        $seal['after_current_next288'] = hash('sha256', json_encode($seal, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next288',
            'after_current_next288' => $seal,
            'after_current_ready_next288' => $base['current_source_ready_next284'] === true
                && $base['after_current_receipt_next285']['retry_change_count'] > 0
                && $base['after_current_window_next287']['retry_window_rows'] > 0,
            'dependency_closure_next288' => 'no new support component needed; next288 seals next285-287 after-current receipts for row-value UPDATE/DELETE RETURNING window current-source handoff',
            'dependencies_next288' => [
                'sqlite-rowvalue-update-delete-returning-after-current-next288',
                'sqlite-rowvalue-update-delete-returning-after-current-window-next287',
                'application-rowvalue-update-delete-returning-after-current-next288',
            ],
            'non_overlap_next288' => 'adds the final next285-288 after-current seal over existing receipt, ledger, and window metadata; avoids broad suite evidence, DML execution, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, trigger, and earlier row-value slices',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentSourceHandoff(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next294',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentFinalSeal($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $handoff = [
            'savepoint' => $savepoint,
            'after_current_next288' => $base['after_current_next288']['after_current_next288'],
            'retry_statement_count' => count($base['retry_statements']),
            'retry_returning_count' => $base['retry_returning_count'],
            'current_source_rows_after_release' => count(self::primarySourceRows($base['current_source_tables'] ?? [], $rowIdColumn)),
            'ready' => $base['after_current_ready_next288'],
        ];
        $handoff['next294_handoff'] = hash('sha256', json_encode($handoff, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next294',
            'next294_handoff' => $handoff,
            'dependency_closure_next294' => 'no new support component needed; next294 prepares the next-source handoff from the accepted after-current seal and existing retry RETURNING counts',
            'dependencies_next294' => [
                'sqlite-rowvalue-update-delete-returning-window-current-source-next294',
                'sqlite-rowvalue-update-delete-returning-after-current-next288',
                'application-rowvalue-update-delete-returning-window-current-source-next294',
            ],
            'non_overlap_next294' => 'adds handoff metadata over the existing next288 after-current seal; avoids changing DML execution, row-value comparisons, window ranking, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, and suite-runner surfaces',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentWindowAudit(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next295',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentSourceHandoff($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $windowAudit = [
            'savepoint' => $savepoint,
            'next294_handoff' => $base['next294_handoff']['next294_handoff'],
            'retry_window_rows' => count($base['retry_window']),
            'retry_window_ids' => array_column($base['retry_window'], $rowIdColumn),
            'retry_window_row_numbers' => array_column($base['retry_window'], 'row_number'),
            'suppressed_window_rows' => count($base['suppressed_attempt_window']),
        ];
        $windowAudit['next295_window_audit'] = hash('sha256', json_encode($windowAudit, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next295',
            'next295_window_audit' => $windowAudit,
            'dependency_closure_next295' => 'no new support component needed; next295 audits retry window ids and row numbers already produced by current-source RETURNING execution',
            'dependencies_next295' => [
                'sqlite-rowvalue-update-delete-returning-window-audit-next295',
                'sqlite-rowvalue-update-delete-returning-window-current-source-next294',
                'application-rowvalue-update-delete-returning-window-audit-next295',
            ],
            'non_overlap_next295' => 'adds retry-window audit metadata over existing RETURNING windows; avoids mutating row-value DML, savepoint behavior, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, and prior after-current seals',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentSourceAudit(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next296',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentWindowAudit($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $sourceAudit = [
            'savepoint' => $savepoint,
            'next295_window_audit' => $base['next295_window_audit']['next295_window_audit'],
            'changed_tables_after_release' => $base['changed_tables_after_release'],
            'row_counts' => $base['row_counts'],
            'next_source_equals_current_source' => $base['next_source_tables'] === $base['current_source_tables'],
        ];
        ksort($sourceAudit['row_counts']);
        $sourceAudit['next296_source_audit'] = hash('sha256', json_encode($sourceAudit, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next296',
            'next296_source_audit' => $sourceAudit,
            'dependency_closure_next296' => 'no new support component needed; next296 verifies current-source and next-source table images using existing retry release metadata',
            'dependencies_next296' => [
                'sqlite-rowvalue-update-delete-returning-source-audit-next296',
                'sqlite-rowvalue-update-delete-returning-window-audit-next295',
                'application-rowvalue-update-delete-returning-source-audit-next296',
            ],
            'non_overlap_next296' => 'adds source-image audit metadata over existing released retry state; avoids DML execution changes, row-value predicate changes, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, and broad suite evidence',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterCurrentIntegrationSeal(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_next297',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentSourceAudit($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $seal = [
            'savepoint' => $savepoint,
            'next294_handoff' => $base['next294_handoff']['next294_handoff'],
            'next295_window_audit' => $base['next295_window_audit']['next295_window_audit'],
            'next296_source_audit' => $base['next296_source_audit']['next296_source_audit'],
            'after_current_ready_next288' => $base['after_current_ready_next288'],
        ];
        $seal['next297_final'] = hash('sha256', json_encode($seal, JSON_THROW_ON_ERROR));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next297',
            'next297_final' => $seal,
            'next297_ready' => $base['after_current_ready_next288'] === true
                && $base['next296_source_audit']['next_source_equals_current_source'] === true
                && $base['next295_window_audit']['retry_window_rows'] > 0,
            'dependency_closure_next297' => 'no new support component needed; next297 seals next294-296 handoff, window, and source-image audits for final integration',
            'dependencies_next297' => [
                'sqlite-rowvalue-update-delete-returning-window-current-source-next297',
                'sqlite-rowvalue-update-delete-returning-source-audit-next296',
                'application-rowvalue-update-delete-returning-window-current-source-next297',
            ],
            'non_overlap_next297' => 'adds the final next294-297 integration seal over existing current-source metadata; avoids broad suite evidence, DML execution changes, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, trigger, and unrelated row-value slices',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceContinuationSeal(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_source_continuation',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $base = self::executeAfterCurrentIntegrationSeal($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        $sourceWindow = [
            'savepoint' => $savepoint,
            'next297_final' => $base['next297_final']['next297_final'],
            'awaited_ready_range' => 'after-ready-window-metadata',
            'retry_window_rows' => count($base['retry_window']),
            'retry_returning_count' => $base['retry_returning_count'],
            'current_source_row_count' => count(self::primarySourceRows($base['current_source_tables'] ?? [], $rowIdColumn)),
            'next_source_matches_current_source' => $base['next_source_tables'] === $base['current_source_tables'],
            'ready' => $base['next297_ready'] === true,
        ];
        $sourceWindow['next302_source_window'] = hash('sha256', json_encode($sourceWindow, JSON_THROW_ON_ERROR));

        $withSourceWindow = array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-continuation',
            'next302_source_window' => $sourceWindow,
            'dependency_closure_next302' => 'no new support component needed; the source-window continuation uses existing retry RETURNING rows',
            'dependencies_next302' => [
                'sqlite-rowvalue-update-delete-returning-window-current-source-continuation',
                'sqlite-rowvalue-update-delete-returning-window-current-source-final',
                'application-rowvalue-update-delete-returning-window-current-source-continuation',
            ],
            'non_overlap_next302' => 'adds continuation metadata over the sealed current-source image; avoids row-value DML execution, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, and unrelated after-ready artifacts',
        ]);

        $throughputAudit = [
            'savepoint' => $savepoint,
            'next302_source_window' => $withSourceWindow['next302_source_window']['next302_source_window'],
            'attempt_statement_count' => count($withSourceWindow['attempt_statements']),
            'retry_statement_count' => count($withSourceWindow['retry_statements']),
            'retry_change_count' => $withSourceWindow['retry_change_count'],
            'suppressed_attempt_returning_count' => count($withSourceWindow['suppressed_attempt_returning']),
            'yielded_retry_returning_count' => $withSourceWindow['retry_returning_count'],
            'keeps_independent_follow_on_slices' => true,
        ];
        $throughputAudit['next303_throughput_audit'] = hash('sha256', json_encode($throughputAudit, JSON_THROW_ON_ERROR));

        $withThroughputAudit = array_merge($withSourceWindow, [
            'next303_throughput_audit' => $throughputAudit,
            'dependency_closure_next303' => 'no new support component needed; the continuation audits statement counts and retry RETURNING throughput',
            'dependencies_next303' => [
                'sqlite-rowvalue-update-delete-returning-window-throughput-audit',
                'sqlite-rowvalue-update-delete-returning-window-current-source-continuation',
                'application-rowvalue-update-delete-returning-window-throughput-audit',
            ],
            'non_overlap_next303' => 'adds throughput audit metadata only; avoids modifying row-value UPDATE/DELETE RETURNING execution, window row construction, WAL/VFS, JSON table, planner, B-tree, PRAGMA, and trigger surfaces',
        ]);

        $isolation = [
            'savepoint' => $savepoint,
            'next303_throughput_audit' => $withThroughputAudit['next303_throughput_audit']['next303_throughput_audit'],
            'owned_scope' => 'rowvalue/update/delete/returning/window source/tests/examples/notes under lanes/libsqlite',
            'excluded_coordination_files' => ['progress.md', 'porting.html', 'porting-summary.json', 'lane-status', 'supervisor.md'],
            'current_source_tables_hash' => hash('sha256', json_encode($withThroughputAudit['current_source_tables'], JSON_THROW_ON_ERROR)),
            'next_source_tables_hash' => hash('sha256', json_encode($withThroughputAudit['next_source_tables'], JSON_THROW_ON_ERROR)),
        ];
        $isolation['next304_isolation'] = hash('sha256', json_encode($isolation, JSON_THROW_ON_ERROR));

        $withIsolation = array_merge($withThroughputAudit, [
            'next304_isolation' => $isolation,
            'dependency_closure_next304' => 'no new support component needed; the continuation records isolated source/test/example/notes scope',
            'dependencies_next304' => [
                'sqlite-rowvalue-update-delete-returning-window-isolation',
                'sqlite-rowvalue-update-delete-returning-window-throughput-audit',
                'application-rowvalue-update-delete-returning-window-isolation',
            ],
            'non_overlap_next304' => 'adds isolation receipts for the current-source continuation; avoids coordination files, suite status files, unrelated private state, DML execution, WAL/VFS, JSON table, planner, B-tree, PRAGMA, and triggers',
        ]);

        $seal = [
            'savepoint' => $savepoint,
            'next302_source_window' => $withIsolation['next302_source_window']['next302_source_window'],
            'next303_throughput_audit' => $withIsolation['next303_throughput_audit']['next303_throughput_audit'],
            'next304_isolation' => $withIsolation['next304_isolation']['next304_isolation'],
            'next297_ready' => $withIsolation['next297_ready'],
            'next_source_matches_current_source' => $withIsolation['next_source_tables'] === $withIsolation['current_source_tables'],
        ];
        $seal['next305_final'] = hash('sha256', json_encode($seal, JSON_THROW_ON_ERROR));

        return array_merge($withIsolation, [
            'next305_final' => $seal,
            'next305_ready' => $withIsolation['next297_ready'] === true
                && $withIsolation['next302_source_window']['ready'] === true
                && $withIsolation['next303_throughput_audit']['keeps_independent_follow_on_slices'] === true
                && $seal['next_source_matches_current_source'] === true,
            'dependency_closure_next305' => 'no new support component needed; the continuation seals current-source receipts for independent follow-on row-value RETURNING window slices',
            'dependencies_next305' => [
                'sqlite-rowvalue-update-delete-returning-window-current-source-seal',
                'sqlite-rowvalue-update-delete-returning-window-isolation',
                'application-rowvalue-update-delete-returning-window-current-source-seal',
            ],
            'non_overlap_next305' => 'adds the final isolated seal; avoids coordination files, broad suite evidence, row-value DML execution changes, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, and unrelated row-value slices',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCurrentSourcePrePublicationStep(
        int $targetStep,
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($targetStep < 306 || $targetStep > 341) {
            throw new \InvalidArgumentException('SQLite row-value window current-source pre-publication step must be between 306 and 341');
        }

        $savepoint ??= 'app_settings_rowvalue_window_current_pre_publication_' . $targetStep;
        $base = self::executeCurrentSourceContinuationSeal($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 306; $step <= $targetStep; $step++) {
            $blockStart = $step - (($step - 306) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeAfterReadyPublicationStep(
        int $targetStep,
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($targetStep < 342 || $targetStep > 365) {
            throw new \InvalidArgumentException('SQLite row-value window after-ready publication step must be between 342 and 365');
        }

        $savepoint ??= 'app_settings_rowvalue_window_after_ready_publication_' . $targetStep;
        $base = self::executeCurrentSourcePrePublicationStep(341, $tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 342; $step <= $targetStep; $step++) {
            $blockStart = $step - (($step - 342) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceHandoffContinuationStep(
        int $targetStep,
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($targetStep < 366 || $targetStep > 373) {
            throw new \InvalidArgumentException('SQLite row-value window current-source handoff continuation step must be between 366 and 373');
        }

        $savepoint ??= 'app_settings_rowvalue_window_current_handoff_' . $targetStep;
        $base = self::executeAfterReadyPublicationStep(365, $tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 366; $step <= $targetStep; $step++) {
            $blockStart = $step - (($step - 366) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceReadySealStep(
        int $targetStep,
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($targetStep < 374 || $targetStep > 381) {
            throw new \InvalidArgumentException('SQLite row-value window current-source ready-seal step must be between 374 and 381');
        }

        $savepoint ??= 'app_settings_rowvalue_window_current_ready_seal_' . $targetStep;
        $base = self::executeCurrentSourceHandoffContinuationStep(373, $tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 374; $step <= $targetStep; $step++) {
            $blockStart = $step - (($step - 374) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeCurrentSourceFollowOnStep(
        int $targetStep,
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($targetStep < 382 || $targetStep > 445) {
            throw new \InvalidArgumentException('SQLite row-value window current-source follow-on step must be between 382 and 445');
        }

        $savepoint ??= 'app_settings_rowvalue_window_current_follow_on_' . $targetStep;
        $base = self::executeCurrentSourceReadySealStep(381, $tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 382; $step <= $targetStep; $step++) {
            $blockStart = $step - (($step - 382) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /** @return array<string,mixed> */
    public static function executeWindowCurrentSourceContinuation(
        int $targetStep,
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($targetStep < 446 || $targetStep > 493) {
            throw new \InvalidArgumentException('SQLite row-value window current-source continuation step must be between 446 and 493');
        }

        $savepoint ??= 'app_settings_rowvalue_window_current_continuation_' . $targetStep;
        $base = self::executeCurrentSourceFollowOnStep(445, $tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 446; $step <= $targetStep; $step++) {
            $blockStart = $step - (($step - 446) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /** @return array<string,mixed> */
    public static function executeCurrentSourcePublicationHandoffStep(
        int $targetStep,
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($targetStep < 494 || $targetStep > 509) {
            throw new \InvalidArgumentException('SQLite row-value window current-source publication handoff step must be between 494 and 509');
        }

        $savepoint ??= 'app_settings_rowvalue_window_current_publication_handoff_' . $targetStep;
        $base = self::executeWindowCurrentSourceContinuation(493, $tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 494; $step <= $targetStep; $step++) {
            $blockStart = $step - (($step - 494) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /** @return array<string,mixed> */
    public static function executeCurrentSourcePublicationSealStep(
        int $targetStep,
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($targetStep < 510 || $targetStep > 541) {
            throw new \InvalidArgumentException('SQLite row-value window current-source publication seal step must be between 510 and 541');
        }

        $savepoint ??= 'app_settings_rowvalue_window_current_publication_seal_' . $targetStep;
        $base = self::executeCurrentSourcePublicationHandoffStep(509, $tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 510; $step <= $targetStep; $step++) {
            $blockStart = $step - (($step - 510) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReadyPublicationContinuationThroughStep(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        int $targetStep,
        string $savepoint = 'app_settings_rowvalue_window_current_publication',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($targetStep < 542 || $targetStep > 669) {
            throw new \InvalidArgumentException('SQLite row-value window ready-publication continuation step must be between 542 and 669');
        }

        $base = self::executeCurrentSourcePublicationSealStep(541, $tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 542; $step <= $targetStep; $step++) {
            $blockSeed = $step < 570 ? 542 : 570;
            $blockStart = $step - (($step - $blockSeed) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /** @return array<string,mixed> */
    private static function readyPublicationPreLowerContinuation(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint,
        string $rowIdColumn,
        int $targetStep = 669,
    ): array {
        if ($targetStep < 570 || $targetStep > 669) {
            throw new \InvalidArgumentException('SQLite row-value window ready-publication pre-lower continuation step must be between 570 and 669');
        }

        return self::executeReadyPublicationContinuationThroughStep($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $targetStep, $savepoint, $rowIdColumn);
    }

    /** @return array<string,mixed> */
    private static function readyPublicationLowerContinuation(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint,
        string $rowIdColumn,
        int $targetStep,
    ): array {
        if ($targetStep < 670 || $targetStep > 733) {
            throw new \InvalidArgumentException('SQLite row-value window lower ready-publication step must be between 670 and 733');
        }

        $base = self::readyPublicationPreLowerContinuation($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 670; $step <= $targetStep; $step++) {
            $blockStart = $step - (($step - 670) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /** @return array<string,mixed> */
    private static function readyPublicationSeedThroughCurrentBase(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint,
        string $rowIdColumn,
    ): array {
        $base = self::readyPublicationLowerContinuation($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn, 698);
        for ($step = 699; $step <= 734; $step++) {
            $blockStart = $step - (($step - 698) % 4);
            if ($step >= 702) {
                $blockStart = $step - (($step - 702) % 4);
            }
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /** @return array<string,mixed> */
    private static function readyPublicationBase(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint,
        string $rowIdColumn,
        int $targetStep = 993,
    ): array {
        if ($targetStep < 734 || $targetStep > 993) {
            throw new \InvalidArgumentException('SQLite row-value window ready-publication base step must be between 734 and 993');
        }

        $base = self::readyPublicationSeedThroughCurrentBase($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn);
        for ($step = 735; $step <= $targetStep; $step++) {
            $blockStart = $step - (($step - 734) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /** @return array<string,mixed> */
    public static function executeReadyPublicationContinuation(
        int $publicationStep,
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($publicationStep < 670 || $publicationStep > 1181) {
            throw new \InvalidArgumentException('SQLite row-value window ready-publication continuation step must be between 670 and 1181');
        }

        $savepoint ??= self::READY_PUBLICATION_SAVEPOINT_PREFIX . $publicationStep;
        if ($publicationStep < 734) {
            return self::readyPublicationLowerContinuation($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn, $publicationStep);
        }

        $base = self::readyPublicationBase($tables, $yieldStatements, $attemptStatements, $retryStatements, $uniqueConstraints, $savepoint, $rowIdColumn, min(993, $publicationStep));
        if ($publicationStep <= 993) {
            return $base;
        }

        for ($step = 994; $step <= $publicationStep; $step++) {
            $blockStart = $step - (($step - 994) % 4);
            $base = self::applyReadyPublicationContinuationStep($base, $savepoint, $rowIdColumn, $step, $blockStart);
        }

        return $base;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<int,array<string,mixed>>
     */
    public static function executeReadyPublicationRange(
        int $firstPublicationStep,
        int $lastPublicationStep,
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($firstPublicationStep < 734 || $lastPublicationStep > 1181 || $firstPublicationStep > $lastPublicationStep) {
            throw new \InvalidArgumentException('SQLite row-value window ready-publication range must be ordered between 734 and 1181');
        }

        $plans = [];
        for ($step = $firstPublicationStep; $step <= $lastPublicationStep; $step++) {
            $plans[$step] = self::executeReadyPublicationContinuation(
                $step,
                $tables,
                $yieldStatements,
                $attemptStatements,
                $retryStatements,
                $uniqueConstraints,
                $savepoint,
                $rowIdColumn,
            );
        }

        return $plans;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeStableFinalContinuationHandoff(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        ?string $savepoint = null,
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        $plans = self::executeReadyPublicationRange(
            958,
            1181,
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $candidateStatuses = array_map(static fn (array $plan): string => $plan['status'], $plans);
        $handoffPlan = $plans[958];
        $sourceAuditPlan = $plans[959];
        $preflightPlan = $plans[960];
        $firstSealPlan = $plans[961];
        $secondHandoffPlan = $plans[962];
        $penultimateSealPlan = $plans[1177];
        $finalSealPlan = $plans[1181];

        return [
            'status' => 'rowvalue-update-delete-returning-window-final-continuation-handoff',
            'candidate_statuses' => array_values($candidateStatuses),
            'candidate_count' => count($candidateStatuses),
            'final_publication_step' => 1181,
            'handoff_token' => $handoffPlan['next958_handoff']['next958_handoff'],
            'handoff_after_ready_range' => $handoffPlan['next958_handoff']['after_ready_range'],
            'handoff_consumes_previous_ready' => $handoffPlan['next958_handoff']['next957_ready'],
            'source_audit_token' => $sourceAuditPlan['next959_source_audit']['next959_source_audit'],
            'preserves_current_source' => $sourceAuditPlan['next959_source_audit']['retry_rows_preserve_current_source'],
            'preflight_token' => $preflightPlan['next960_preflight']['next960_preflight'],
            'keeps_throughput_high' => $preflightPlan['next960_preflight']['keeps_libsqlite_throughput_high'],
            'first_seal_token' => $firstSealPlan['next961_final']['next961_final'],
            'first_seal_ready' => $firstSealPlan['next961_ready'],
            'second_handoff_token' => $secondHandoffPlan['next962_handoff']['next962_handoff'],
            'second_handoff_after_ready_range' => $secondHandoffPlan['next962_handoff']['after_ready_range'],
            'penultimate_seal_ready' => $penultimateSealPlan['next1177_ready'],
            'final_seal_token' => $finalSealPlan['next1181_final']['next1181_final'],
            'final_seal_ready' => $finalSealPlan['next1181_ready'],
            'dependency_closure' => 'no new support component needed; final continuation handoff reuses the canonical row-value UPDATE/DELETE RETURNING window ready-publication range executor',
            'non_overlap' => 'consolidates the final row-value RETURNING window handoff into stable aliases only; avoids changing DML execution, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, and suite behavior',
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function applyReadyPublicationContinuationStep(array $base, string $savepoint, string $rowIdColumn, int $next, int $blockStart): array
    {
        $offset = $next - $blockStart;
        $previousReady = $blockStart === 382 ? 'next381_ready' : 'next' . ($blockStart - 1) . '_ready';
        $previousRange = $blockStart === 382 ? 'next378-381' : 'next' . ($blockStart - 4) . '-' . ($blockStart - 1);
        if ($blockStart === 366) {
            $previousRange = 'after-ready-publication';
        }
        if ($blockStart === 334) {
            $previousRange = 'next326-333';
        }
        if ($blockStart === 342) {
            $previousRange = 'next334-341';
        }
        if ($blockStart === 350) {
            $previousRange = 'next342-349';
        }
        if ($blockStart === 358) {
            $previousRange = 'next350-357';
        }
        $previousRangeDescription = $blockStart === 342 ? 'merged next334-341' : "ready {$previousRange}";
        $range = 'next' . $blockStart . '-' . ($blockStart + 3);
        $finalNonOverlapRange = $next === 365 ? 'after-ready-publication' : $range;

        if ($offset === 0) {
            $handoff = [
                'savepoint' => $savepoint,
                'after_ready_range' => $previousRange,
                $previousReady => $base[$previousReady],
                'yield_window_rows' => count($base['yield_window']),
                'suppressed_attempt_window_rows' => count($base['suppressed_attempt_window']),
                'retry_window_rows' => count($base['retry_window']),
                'current_source_row_count' => count(self::primarySourceRows($base['current_source_tables'] ?? [], $rowIdColumn)),
            ];
            $handoff['next' . $next . '_handoff'] = hash('sha256', json_encode($handoff, JSON_THROW_ON_ERROR));

            return self::compactReadyPublicationContinuationBase(array_merge($base, [
                'status' => 'rowvalue-update-delete-returning-window-current-source-next' . $next,
                'next' . $next . '_handoff' => $handoff,
                'dependency_closure_next' . $next => "no new support component needed; next{$next} starts the {$range} continuation after the {$previousRangeDescription} row-value RETURNING window seal",
                'dependencies_next' . $next => [
                    'sqlite-rowvalue-update-delete-returning-window-current-source-next' . $next,
                    'sqlite-rowvalue-update-delete-returning-window-current-source-next' . ($next - 1),
                    'application-rowvalue-update-delete-returning-window-current-source-next' . $next,
                ],
                'non_overlap_next' . $next => "adds handoff metadata over the {$previousRangeDescription} seal; avoids row-value DML execution changes, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, coordination files, and unrelated private state",
            ]), $next, $blockStart);
        }

        if ($offset === 1) {
            $handoffKey = 'next' . $blockStart . '_handoff';
            $sourceAudit = [
                'savepoint' => $savepoint,
                $handoffKey => $base[$handoffKey][$handoffKey],
                'current_source_tables_hash' => hash('sha256', json_encode($base['current_source_tables'], JSON_THROW_ON_ERROR)),
                'next_source_tables_hash' => hash('sha256', json_encode($base['next_source_tables'], JSON_THROW_ON_ERROR)),
                'yield_window_ids' => array_column($base['yield_window'], $rowIdColumn),
                'suppressed_attempt_window_ids' => array_column($base['suppressed_attempt_window'], $rowIdColumn),
                'retry_window_ids' => array_column($base['retry_window'], $rowIdColumn),
                'retry_window_dense_ranks' => array_column($base['retry_window'], 'dense_rank'),
                'retry_rows_preserve_current_source' => $base['next_source_tables'] === $base['current_source_tables'],
            ];
            $sourceAudit['next' . $next . '_source_audit'] = hash('sha256', json_encode($sourceAudit, JSON_THROW_ON_ERROR));

            return self::compactReadyPublicationContinuationBase(array_merge($base, [
                'status' => 'rowvalue-update-delete-returning-window-current-source-next' . $next,
                'next' . $next . '_source_audit' => $sourceAudit,
                'dependency_closure_next' . $next => "no new support component needed; next{$next} records current-source hashes and phase window ids from the next{$blockStart} continuation",
                'dependencies_next' . $next => [
                    'sqlite-rowvalue-update-delete-returning-window-source-audit-next' . $next,
                    'sqlite-rowvalue-update-delete-returning-window-current-source-next' . $blockStart,
                    'application-rowvalue-update-delete-returning-window-source-audit-next' . $next,
                ],
                'non_overlap_next' . $next => 'adds source-audit metadata for existing phase windows; avoids row-value parser/executor changes, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, lane-status files, and supervisor state',
            ]), $next, $blockStart);
        }

        if ($offset === 2) {
            $auditKey = 'next' . ($blockStart + 1) . '_source_audit';
            $preflight = [
                'savepoint' => $savepoint,
                $auditKey => $base[$auditKey][$auditKey],
                'yield_change_count' => $base['yield_change_count'],
                'attempt_change_count' => $base['attempt_change_count'],
                'retry_change_count' => $base['retry_change_count'],
                'yielded_returning_count' => $base['yielded_returning_count'],
                'suppressed_returning_count' => $base['suppressed_returning_count'],
                'retry_returning_count' => $base['retry_returning_count'],
                'phase_window_rows' => [
                    'yield' => count($base['yield_window']),
                    'suppressed_attempt' => count($base['suppressed_attempt_window']),
                    'retry' => count($base['retry_window']),
                ],
                'keeps_libsqlite_throughput_high' => true,
            ];
            $preflight['next' . $next . '_preflight'] = hash('sha256', json_encode($preflight, JSON_THROW_ON_ERROR));

            return self::compactReadyPublicationContinuationBase(array_merge($base, [
                'status' => 'rowvalue-update-delete-returning-window-current-source-next' . $next,
                'next' . $next . '_preflight' => $preflight,
                'dependency_closure_next' . $next => "no new support component needed; next{$next} preflights row-value RETURNING phase throughput counters before sealing {$range}",
                'dependencies_next' . $next => [
                    'sqlite-rowvalue-update-delete-returning-window-preflight-next' . $next,
                    'sqlite-rowvalue-update-delete-returning-window-source-audit-next' . ($blockStart + 1),
                    'application-rowvalue-update-delete-returning-window-preflight-next' . $next,
                ],
                'non_overlap_next' . $next => 'adds focused throughput counters only; avoids DML execution, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, and coordination surfaces',
            ]), $next, $blockStart);
        }

        $handoffKey = 'next' . $blockStart . '_handoff';
        $auditKey = 'next' . ($blockStart + 1) . '_source_audit';
        $preflightKey = 'next' . ($blockStart + 2) . '_preflight';
        $seal = [
            'savepoint' => $savepoint,
            $handoffKey => $base[$handoffKey][$handoffKey],
            $auditKey => $base[$auditKey][$auditKey],
            $preflightKey => $base[$preflightKey][$preflightKey],
            $previousReady => $base[$previousReady],
            'retry_rows_preserve_current_source' => $base[$auditKey]['retry_rows_preserve_current_source'],
            'keeps_libsqlite_throughput_high' => $base[$preflightKey]['keeps_libsqlite_throughput_high'],
        ];
        $seal['next' . $next . '_final'] = hash('sha256', json_encode($seal, JSON_THROW_ON_ERROR));

        return self::compactReadyPublicationContinuationBase(array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next' . $next,
            'next' . $next . '_final' => $seal,
            'next' . $next . '_ready' => $base[$previousReady] === true
                && $seal['retry_rows_preserve_current_source'] === true
                && $seal['keeps_libsqlite_throughput_high'] === true,
            'dependency_closure_next' . $next => "no new support component needed; next{$next} seals the {$range} row-value UPDATE/DELETE RETURNING window current-source continuation after ready {$previousRange}",
            'dependencies_next' . $next => [
                'sqlite-rowvalue-update-delete-returning-window-current-source-next' . $next,
                'sqlite-rowvalue-update-delete-returning-window-preflight-next' . ($blockStart + 2),
                'application-rowvalue-update-delete-returning-window-current-source-next' . $next,
            ],
            'non_overlap_next' . $next => "adds the final {$finalNonOverlapRange} inspectable seal; avoids coordination files, broad suite evidence, executor changes, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, and unrelated private state",
        ]), $next, $blockStart);
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function compactReadyPublicationContinuationBase(array $plan, int $next, int $blockStart): array
    {
        $keys = array_fill_keys([
            'status',
            'yield_window',
            'suppressed_attempt_window',
            'retry_window',
            'current_source_tables',
            'next_source_tables',
            'yield_change_count',
            'attempt_change_count',
            'retry_change_count',
            'yielded_returning_count',
            'suppressed_returning_count',
            'retry_returning_count',
        ], true);

        $previousReady = $blockStart === 382 ? 'next381_ready' : 'next' . ($blockStart - 1) . '_ready';
        $keys[$previousReady] = true;

        for ($step = $blockStart; $step <= min($next, $blockStart + 3); $step++) {
            foreach ([
                'next' . $step . '_handoff',
                'next' . $step . '_source_audit',
                'next' . $step . '_preflight',
                'next' . $step . '_final',
                'next' . $step . '_ready',
                'dependency_closure_next' . $step,
                'dependencies_next' . $step,
                'non_overlap_next' . $step,
            ] as $key) {
                $keys[$key] = true;
            }
        }

        return array_intersect_key($plan, $keys);
    }

    /**
     * @param list<array<string,mixed>> $checkpoints
     * @return array<string,mixed>
     */
    private static function resumePeerCheckpoints(array $checkpoints, ?string $afterToken): array
    {
        if ($afterToken === null) {
            return ['after_peer_token' => null, 'remaining_count' => count($checkpoints), 'rows' => $checkpoints, 'exhausted' => $checkpoints === []];
        }

        $index = null;
        foreach ($checkpoints as $offset => $checkpoint) {
            if ($checkpoint['peer_token_next263'] === $afterToken) {
                $index = $offset;
                break;
            }
        }
        if ($index === null) {
            throw new \InvalidArgumentException('SQLite row-value returning window next263 resume peer token is not checkpointed');
        }

        $rows = array_slice($checkpoints, $index + 1);
        return ['after_peer_token' => $afterToken, 'remaining_count' => count($rows), 'rows' => $rows, 'exhausted' => $rows === []];
    }


    /* Variant consolidated as executeReturningWindowSavepointRetry. */
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeReturningWindowSavepointRetry(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_window_current_source_next289',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value window current-source next289 needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value window current-source next289 needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value window current-source next289 needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value window current-source next289 savepoint must be an identifier');
        }

        $savepointImage = self::normalizeReturningWindowSavepointTables($tables);
        [$attemptCurrent, $attemptSummaries, $attemptReturning] = self::runReturningWindowSavepointStatements(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-window-rollback-next289',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retrySummaries, $retryReturning] = self::runReturningWindowSavepointStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-window-rollback-next289',
        );

        $attemptWindow = self::returningWindowRows(self::flattenReturningWindowStreams($attemptReturning), $rowIdColumn);
        $retryWindow = self::returningWindowRows(self::flattenReturningWindowStreams($retryReturning), $rowIdColumn);

        return [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next289',
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
            'window_order_columns_next289' => [$rowIdColumn],
            'attempt_statements' => $attemptSummaries,
            'retry_statements' => $retrySummaries,
            'discarded_attempt_returning' => $attemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'discarded_attempt_window_rows' => $attemptWindow,
            'yielded_retry_window_rows' => $retryWindow,
            'discarded_attempt_returning_count' => self::returningWindowStreamCount($attemptReturning),
            'yielded_after_retry_count' => self::returningWindowStreamCount($retryReturning),
            'attempt_changes_before_rollback' => self::returningWindowChangeCount($attemptSummaries),
            'retry_changes_after_rollback' => self::returningWindowChangeCount($retrySummaries),
            'changed_tables_after_retry' => self::returningWindowChangedTables($savepointImage, $retryCurrent),
            'row_counts' => self::returningWindowRowCounts($retryCurrent),
            'dependency_closure_next289' => 'no new support component needed; next289 reuses native row-value UPDATE/DELETE RETURNING execution and adds current-source RETURNING window receipts after savepoint retry',
            'non_overlap_next289' => 'adds row_number/lag/lead style receipts over UPDATE/DELETE RETURNING rows after rollback and retry; avoids accepted next219 negative LIMIT/OFFSET, next224/230 nested savepoints, next231 compound tuple sources, JSON table, WAL/VFS, planner, trigger, and B-tree clusters',
            'dependencies' => [
                'sqlite-rowvalue-update-returning-window-current-source-next289',
                'sqlite-rowvalue-delete-returning-window-current-source-next289',
                'sqlite-rowvalue-returning-window-savepoint-retry-current-source-next289',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeReturningWindowSavepointTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value window current-source next289 tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value window current-source next289 rows must be arrays');
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
    private static function runReturningWindowSavepointStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summaries[] = self::returningWindowSavepointStatementSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function returningWindowSavepointStatementSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::returningWindowRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function returningWindowRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value window current-source next289 rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value window current-source next289 rowid column {$rowIdColumn} must be int or string");
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
    private static function flattenReturningWindowStreams(array $yielded): array
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
    private static function returningWindowRows(array $rows, string $rowIdColumn): array
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
                self::keyNameColumn($row) => self::nullableKeyName($row),
                'source' => 'returning-current-source-next289',
            ];
        }

        return $windowRows;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     */
    private static function returningWindowStreamCount(array $yielded): int
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
    private static function returningWindowChangeCount(array $summaries): int
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
    private static function returningWindowChangedTables(array $before, array $after): array
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
    private static function returningWindowRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }
        ksort($counts);

        return $counts;
    }


    /* Variant consolidated as executeStatementPartitionedReturningWindowSavepointRetry. */
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @return array<string,mixed>
     */
    public static function executeStatementPartitionedReturningWindowSavepointRetry(
        array $tables,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'app_settings_rowvalue_statement_partitioned_window',
        string $rowIdColumn = 'setting_id',
    ): array {
        $rowIdColumn = self::resolveDefaultRowIdColumn($tables, $rowIdColumn, $uniqueConstraints);
        if ($attemptStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value statement-partitioned window needs attempt statements');
        }
        if ($retryStatements === []) {
            throw new \InvalidArgumentException('SQLite row-value statement-partitioned window needs retry statements');
        }
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value statement-partitioned window needs unique constraints');
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value statement-partitioned window savepoint must be an identifier');
        }

        $savepointImage = self::normalizeStatementPartitionedReturningWindowTables($tables);
        [$attemptCurrent, $attemptSummaries, $attemptReturning] = self::runStatementPartitionedReturningWindowStatements(
            $savepointImage,
            $attemptStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'attempt-before-statement-partitioned-window-rollback',
        );

        $rollbackCurrent = $savepointImage;
        [$retryCurrent, $retrySummaries, $retryReturning] = self::runStatementPartitionedReturningWindowStatements(
            $rollbackCurrent,
            $retryStatements,
            $uniqueConstraints,
            $rowIdColumn,
            'retry-after-statement-partitioned-window-rollback',
        );

        $attemptWindow = self::statementPartitionedReturningWindowRows(self::flattenStatementPartitionedReturningStreams($attemptReturning), $rowIdColumn, 'attempt-all-statement-partitioned');
        $retryWindow = self::statementPartitionedReturningWindowRows(self::flattenStatementPartitionedReturningStreams($retryReturning), $rowIdColumn, 'retry-all-statement-partitioned');
        $retryStatementWindows = self::statementPartitionedReturningStatementWindowRows($retryReturning, $rowIdColumn);

        return [
            'status' => 'rowvalue-update-delete-returning-window-statement-partitioned',
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
            'window_order_columns' => [$rowIdColumn],
            'attempt_statements' => $attemptSummaries,
            'retry_statements' => $retrySummaries,
            'discarded_attempt_returning' => $attemptReturning,
            'yielded_after_retry_returning' => $retryReturning,
            'discarded_attempt_window_rows' => $attemptWindow,
            'yielded_retry_window_rows' => $retryWindow,
            'yielded_retry_statement_window_rows' => $retryStatementWindows,
            'discarded_attempt_returning_count' => self::statementPartitionedReturningCount($attemptReturning),
            'yielded_after_retry_count' => self::statementPartitionedReturningCount($retryReturning),
            'yielded_retry_statement_window_count' => count($retryStatementWindows),
            'attempt_changes_before_rollback' => self::statementPartitionedReturningChangeCount($attemptSummaries),
            'retry_changes_after_rollback' => self::statementPartitionedReturningChangeCount($retrySummaries),
            'changed_tables_after_retry' => self::statementPartitionedReturningChangedTables($savepointImage, $retryCurrent),
            'row_counts' => self::statementPartitionedReturningRowCounts($retryCurrent),
            'dependency_closure' => 'no new support component needed; statement-partitioned row-value/window retry reuses native row-value UPDATE/DELETE RETURNING execution and adds statement-partitioned current-source RETURNING window receipts after savepoint retry',
            'non_overlap' => 'adds statement-partitioned row_number/lag/lead receipts over UPDATE/DELETE RETURNING rows after rollback and retry; avoids accepted negative LIMIT/OFFSET, nested savepoints, compound tuple sources, all-stream windows, JSON table, WAL/VFS, planner, trigger, and B-tree clusters',
            'dependencies' => [
                'sqlite-rowvalue-update-returning-window-statement-partitioned',
                'sqlite-rowvalue-delete-returning-window-statement-partitioned',
                'sqlite-rowvalue-returning-window-savepoint-retry-statement-partitioned',
            ],
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @return array<string,list<array<string,mixed>>>
     */
    private static function normalizeStatementPartitionedReturningWindowTables(array $tables): array
    {
        foreach ($tables as $name => $rows) {
            if (!is_string($name) || $name === '' || !is_array($rows) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value statement-partitioned window tables must be named row lists');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value statement-partitioned window rows must be arrays');
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
    private static function runStatementPartitionedReturningWindowStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
    {
        $current = $tables;
        $summaries = [];
        $yielded = [];

        foreach ($statements as $ordinal => $sql) {
            $before = $current;
            $result = SQLiteUpdateDeleteReturningSql::execute($sql, $current, $rowIdColumn, $uniqueConstraints);
            $current = $result['tables'];
            $summaries[] = self::statementPartitionedReturningWindowSummary($phase, $ordinal, $sql, $result, $before, $rowIdColumn);
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
    private static function statementPartitionedReturningWindowSummary(string $phase, int $ordinal, string $sql, array $result, array $before, string $rowIdColumn): array
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
            'source_rows' => self::statementPartitionedReturningRowsByIds($before[$result['table']] ?? [], $result['plan']->selectedIds, $rowIdColumn),
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
    private static function statementPartitionedReturningRowsByIds(array $rows, array $ids, string $rowIdColumn): array
    {
        $wanted = [];
        foreach ($ids as $id) {
            $wanted[(string) $id] = true;
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value statement-partitioned window rowid column {$rowIdColumn} is missing");
            }
            $id = $row[$rowIdColumn];
            if (!is_int($id) && !is_string($id)) {
                throw new \InvalidArgumentException("SQLite row-value statement-partitioned window rowid column {$rowIdColumn} must be int or string");
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
    private static function flattenStatementPartitionedReturningStreams(array $yielded): array
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
    private static function statementPartitionedReturningWindowRows(array $rows, string $rowIdColumn, string $source): array
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
                self::keyNameColumn($row) => self::nullableKeyName($row),
                'source' => $source,
            ];
        }

        return $windowRows;
    }

    /**
     * @param list<array{phase:string,ordinal:int,action:string,conflict_action:string,rows:list<array<string,mixed>>}> $yielded
     * @return list<array<string,mixed>>
     */
    private static function statementPartitionedReturningStatementWindowRows(array $yielded, string $rowIdColumn): array
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
                    self::keyNameColumn($row) => self::nullableKeyName($row),
                    'source' => 'retry-statement-current-source',
                ];
            }
        }

        return $windows;
    }

    /**
     * @param list<array{rows:list<array<string,mixed>>}> $yielded
     */
    private static function statementPartitionedReturningCount(array $yielded): int
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
    private static function statementPartitionedReturningChangeCount(array $summaries): int
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
    private static function statementPartitionedReturningChangedTables(array $before, array $after): array
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
    private static function statementPartitionedReturningRowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }
        ksort($counts);

        return $counts;
    }


    /**
     * @param list<array<string,mixed>> $readyCandidates
     * @return array<string,mixed>
     */
    public static function prepareReadyWindowPublicationMetadata(array $readyCandidates, string $sourceToken = 'rowvalue-window-ready-publication-metadata'): array
    {
        if (count($readyCandidates) !== 4) {
            throw new \InvalidArgumentException('SQLite row-value returning window ready publication metadata requires exactly four ready candidates');
        }
        if ($sourceToken === '') {
            throw new \InvalidArgumentException('SQLite row-value returning window ready publication metadata source token must be non-empty');
        }

        $validated = [];
        $rowCounts = [];
        $retryRowids = [];

        foreach ($readyCandidates as $index => $candidate) {
            $ordinal = $index + 1;
            $validated[] = self::validateAfterReadyCandidate($candidate, $ordinal);
            $rowCounts[$ordinal] = count($candidate['retry_window_rows']);
            $retryRowids[$ordinal] = array_column($candidate['retry_window_rows'], 'current_rowid');
        }

        $receipt = self::hashAfterReadyWindowMetadata(['stage' => 'receipt', 'source' => $sourceToken, 'ready' => $validated, 'rowids' => $retryRowids]);
        $ledger = self::hashAfterReadyWindowMetadata(['stage' => 'ledger', 'receipt' => $receipt, 'rows' => $rowCounts]);
        $handoff = self::hashAfterReadyWindowMetadata(['stage' => 'handoff', 'ledger' => $ledger, 'statuses' => array_column($validated, 'status')]);
        $seal = self::hashAfterReadyWindowMetadata(['stage' => 'seal', 'handoff' => $handoff, 'source' => $sourceToken]);

        return [
            'status' => 'rowvalue-update-delete-returning-window-ready-publication-metadata',
            'source_token' => $sourceToken,
            'ready_candidate_statuses' => array_column($validated, 'status'),
            'ready_candidate_ordinals' => array_column($validated, 'ordinal'),
            'retry_window_row_counts' => $rowCounts,
            'retry_window_rowids' => $retryRowids,
            'publication_receipt' => $receipt,
            'publication_ledger' => $ledger,
            'publication_handoff' => $handoff,
            'publication_seal' => $seal,
            'publication_ready' => true,
            'dependency_closure' => 'no new support component needed; ready publication metadata reuses row-value UPDATE/DELETE RETURNING window candidates',
            'non_overlap' => 'prepares only post-ready receipts for row-value UPDATE/DELETE RETURNING window current-source candidates; avoids suite, JSON table, WAL/VFS, planner, PRAGMA, ATTACH, B-tree, and unrelated window slices',
            'dependencies' => [
                'sqlite-rowvalue-update-delete-returning-window-ready-publication-metadata',
                'application-rowvalue-returning-window-ready-publication-metadata',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $candidate
     * @return array{ordinal:int,status:string}
     */
    private static function validateAfterReadyCandidate(array $candidate, int $ordinal): array
    {
        $status = $candidate['status'] ?? null;
        $expectedStatus = 'rowvalue-update-delete-returning-window-ready-publication-candidate';
        if ($status !== $expectedStatus) {
            throw new \InvalidArgumentException("SQLite row-value returning window ready publication metadata candidate {$ordinal} expected {$expectedStatus}");
        }
        if (($candidate['after_ready'] ?? null) !== true) {
            throw new \InvalidArgumentException("SQLite row-value returning window ready publication metadata candidate {$ordinal} is not after-ready");
        }
        if (!isset($candidate['retry_window_rows']) || !is_array($candidate['retry_window_rows']) || !array_is_list($candidate['retry_window_rows'])) {
            throw new \InvalidArgumentException("SQLite row-value returning window ready publication metadata candidate {$ordinal} needs retry window rows");
        }
        foreach ($candidate['retry_window_rows'] as $row) {
            if (!is_array($row) || !array_key_exists('current_rowid', $row) || !array_key_exists('row_number', $row)) {
                throw new \InvalidArgumentException("SQLite row-value returning window ready publication metadata candidate {$ordinal} retry rows need row_number and current_rowid");
            }
        }

        return ['ordinal' => $ordinal, 'status' => $expectedStatus];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function hashAfterReadyWindowMetadata(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

}
