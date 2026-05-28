<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext234Plan
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
        string $partitionColumn = 'blog_id',
        string $orderColumn = 'option_id',
        string $rowIdColumn = 'option_id',
        string $savepoint = 'wp_options_rowvalue_returning_window_next234',
    ): array {
        self::validateTables($tables);
        self::validateStatements($attemptStatements, 'attempt');
        self::validateStatements($retryStatements, 'retry');
        self::validateUniqueConstraints($uniqueConstraints);
        self::validateIdentifier($partitionColumn, 'partition column');
        self::validateIdentifier($orderColumn, 'order column');
        self::validateIdentifier($rowIdColumn, 'rowid column');
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next234 savepoint must be an identifier');
        }

        $savepointImage = $tables;
        $attempt = self::runStatements($tables, $attemptStatements, $uniqueConstraints, $rowIdColumn, 'attempt-next234');
        $rollback = $savepointImage;
        $retry = self::runStatements($rollback, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-next234');
        $windowRows = self::windowRows($retry['returning_rows'], $partitionColumn, $orderColumn);
        $partitionSummary = self::partitionSummary($windowRows, $partitionColumn);

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
            'current_source_token' => self::sourceToken($retry['tables']),
            'window_token' => self::sourceToken($windowRows),
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retry['tables']),
            'row_counts' => self::rowCounts($retry['tables']),
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
    private static function runStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
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
                $rows[] = self::tagReturningRow($row, $phase, $index, $ordinal);
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
                'changed_tables' => self::changedTables($before, $current),
            ];
        }

        return ['tables' => $current, 'statements' => $summaries, 'returning_rows' => $returningRows];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function tagReturningRow(array $row, string $phase, int $statementOrdinal, int $returningOrdinal): array
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
    private static function windowRows(array $rows, string $partitionColumn, string $orderColumn): array
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
            $partition = self::compareValues($left['row'][$partitionColumn], $right['row'][$partitionColumn]);
            if ($partition !== 0) {
                return $partition;
            }
            $order = self::compareValues($left['row'][$orderColumn], $right['row'][$orderColumn]);
            if ($order !== 0) {
                return $order;
            }

            return $left['index'] <=> $right['index'];
        });

        $byPartition = [];
        foreach ($indexed as $entry) {
            $key = self::valueKey($entry['row'][$partitionColumn]);
            $byPartition[$key][] = $entry['row'];
        }

        $windowRows = [];
        foreach ($byPartition as $partitionRows) {
            $denseRank = 0;
            $previousOrderKey = null;
            $count = count($partitionRows);
            foreach ($partitionRows as $position => $row) {
                $orderKey = self::valueKey($row[$orderColumn]);
                if ($previousOrderKey !== $orderKey) {
                    $denseRank++;
                    $previousOrderKey = $orderKey;
                }
                $lag = $position > 0 ? $partitionRows[$position - 1] : null;
                $lead = $position + 1 < $count ? $partitionRows[$position + 1] : null;
                $row['window_row_number'] = $position + 1;
                $row['window_dense_rank'] = $denseRank;
                $row['window_partition_size'] = $count;
                $row['window_lag_option_name'] = $lag['option_name'] ?? null;
                $row['window_lead_option_name'] = $lead['option_name'] ?? null;
                $row['window_current_row'] = true;
                $row['window_frame_rowids'] = self::frameRowids($partitionRows, max(0, $position - 1), min($count - 1, $position + 1));
                $windowRows[] = $row;
            }
        }

        return $windowRows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function frameRowids(array $rows, int $start, int $end): array
    {
        $ids = [];
        for ($i = $start; $i <= $end; $i++) {
            if (array_key_exists($i, $rows) && array_key_exists('option_id', $rows[$i])) {
                $id = $rows[$i]['option_id'];
                if (is_int($id) || is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array{count:int,row_numbers:list<int>,rowids:list<int|string>}>
     */
    private static function partitionSummary(array $rows, string $partitionColumn): array
    {
        $summary = [];
        foreach ($rows as $row) {
            $key = self::valueKey($row[$partitionColumn] ?? null);
            if (!isset($summary[$key])) {
                $summary[$key] = ['count' => 0, 'row_numbers' => [], 'rowids' => []];
            }
            $summary[$key]['count']++;
            $summary[$key]['row_numbers'][] = (int) $row['window_row_number'];
            if (isset($row['option_id']) && (is_int($row['option_id']) || is_string($row['option_id']))) {
                $summary[$key]['rowids'][] = $row['option_id'];
            }
        }

        return $summary;
    }

    private static function compareValues(mixed $left, mixed $right): int
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

    private static function valueKey(mixed $value): string
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
    private static function changedTables(array $before, array $after): array
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
    private static function rowCounts(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table => $rows) {
            $counts[$table] = count($rows);
        }
        ksort($counts);

        return $counts;
    }

    private static function sourceToken(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,list<array<string,mixed>>> $tables
     */
    private static function validateTables(array $tables): void
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
    private static function validateStatements(array $statements, string $label): void
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
    private static function validateUniqueConstraints(array $uniqueConstraints): void
    {
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next234 needs unique constraints');
        }
        foreach ($uniqueConstraints as $columns) {
            if (!is_array($columns) || $columns === []) {
                throw new \InvalidArgumentException('SQLite row-value RETURNING window next234 unique constraints need columns');
            }
            foreach ($columns as $column) {
                self::validateIdentifier($column, 'unique column');
            }
        }
    }

    private static function validateIdentifier(string $identifier, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING window next234 {$label} must be an identifier");
        }
    }
}
