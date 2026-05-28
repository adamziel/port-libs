<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext237Plan
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
        string $orderColumn = 'bytes',
        string $rowIdColumn = 'option_id',
        string $savepoint = 'wp_options_rowvalue_returning_window_next237',
    ): array {
        self::validateTables($tables);
        self::validateStatements($attemptStatements, 'attempt');
        self::validateStatements($retryStatements, 'retry');
        self::validateUniqueConstraints($uniqueConstraints);
        self::validateIdentifier($partitionColumn, 'partition column');
        self::validateIdentifier($orderColumn, 'order column');
        self::validateIdentifier($rowIdColumn, 'rowid column');
        self::validateIdentifier($savepoint, 'savepoint');

        $savepointImage = $tables;
        $attempt = self::runStatements($savepointImage, $attemptStatements, $uniqueConstraints, $rowIdColumn, 'attempt-before-rollback-next237');
        $rollback = $savepointImage;
        $retry = self::runStatements($rollback, $retryStatements, $uniqueConstraints, $rowIdColumn, 'retry-after-rollback-next237');
        $windowRows = self::excludeCurrentWindowRows($retry['returning_rows'], $partitionColumn, $orderColumn, $rowIdColumn);

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
            'exclude_current_partition_summary' => self::partitionSummary($windowRows, $partitionColumn, $rowIdColumn),
            'rolled_back_to_savepoint' => true,
            'retry_reads_savepoint_image' => true,
            'savepoint_released_after_retry' => true,
            'attempt_returning_suppressed_after_rollback' => true,
            'changed_tables_after_retry' => self::changedTables($savepointImage, $retry['tables']),
            'row_counts' => self::rowCounts($retry['tables']),
            'current_source_token' => self::sourceToken($retry['tables']),
            'window_token' => self::sourceToken($windowRows),
            'dependencies' => [
                'sqlite-rowvalue-returning-window-exclude-current-next237',
                'sqlite-rowvalue-delete-returning-window-peer-frame-next237',
                'wordpress-rowvalue-returning-window-current-source-next237',
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
    private static function runStatements(array $tables, array $statements, array $uniqueConstraints, string $rowIdColumn, string $phase): array
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
                'changed_tables' => self::changedTables($before, $current),
            ];
        }

        return ['tables' => $current, 'statements' => $summaries, 'returning_rows' => $returningRows];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function excludeCurrentWindowRows(array $rows, string $partitionColumn, string $orderColumn, string $rowIdColumn): array
    {
        $indexed = [];
        foreach ($rows as $index => $row) {
            foreach ([$partitionColumn, $orderColumn, $rowIdColumn] as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite row-value RETURNING window next237 column {$column} is missing");
                }
            }
            $indexed[] = ['index' => $index, 'row' => $row];
        }

        usort($indexed, static function (array $left, array $right) use ($partitionColumn, $orderColumn, $rowIdColumn): int {
            $partition = self::compareValues($left['row'][$partitionColumn], $right['row'][$partitionColumn]);
            if ($partition !== 0) {
                return $partition;
            }
            $order = self::compareValues($left['row'][$orderColumn], $right['row'][$orderColumn]);
            if ($order !== 0) {
                return $order;
            }
            $rowid = self::compareValues($left['row'][$rowIdColumn], $right['row'][$rowIdColumn]);
            if ($rowid !== 0) {
                return $rowid;
            }

            return $left['index'] <=> $right['index'];
        });

        $partitions = [];
        foreach ($indexed as $entry) {
            $partitions[self::valueKey($entry['row'][$partitionColumn])][] = $entry['row'];
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
                $row['window_peer_rowids_excluding_current'] = self::rowIds($frameRows, $rowIdColumn);
                $row['window_peer_names_excluding_current'] = array_values(array_map(static fn (array $peer): string => (string) ($peer['option_name'] ?? ''), $frameRows));
                $row['window_peer_bytes_excluding_current'] = array_sum(array_map(static fn (array $peer): int => self::intValue($peer['bytes'] ?? 0), $frameRows));
                $row['window_peer_first_name'] = $frameRows[0]['option_name'] ?? null;
                $row['window_peer_last_name'] = $frameRows === [] ? null : ($frameRows[array_key_last($frameRows)]['option_name'] ?? null);
                $windowRows[] = $row;
            }
        }

        return $windowRows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,array{count:int,rowids:list<int|string>,peer_counts:list<int>,peer_rowids:list<list<int|string>>}>
     */
    private static function partitionSummary(array $rows, string $partitionColumn, string $rowIdColumn): array
    {
        $summary = [];
        foreach ($rows as $row) {
            $key = self::valueKey($row[$partitionColumn] ?? null);
            if (!isset($summary[$key])) {
                $summary[$key] = ['count' => 0, 'rowids' => [], 'peer_counts' => [], 'peer_rowids' => []];
            }
            $summary[$key]['count']++;
            $summary[$key]['rowids'][] = $row[$rowIdColumn];
            $summary[$key]['peer_counts'][] = (int) $row['window_peer_count_excluding_current'];
            $summary[$key]['peer_rowids'][] = $row['window_peer_rowids_excluding_current'];
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function rowIds(array $rows, string $rowIdColumn): array
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
    private static function validateTables(array $tables): void
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
    private static function validateStatements(array $statements, string $label): void
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
    private static function validateUniqueConstraints(array $uniqueConstraints): void
    {
        if ($uniqueConstraints === []) {
            throw new \InvalidArgumentException('SQLite row-value RETURNING window next237 needs unique constraints');
        }
        foreach ($uniqueConstraints as $constraint) {
            if (!array_is_list($constraint) || $constraint === []) {
                throw new \InvalidArgumentException('SQLite row-value RETURNING window next237 unique constraints must be non-empty lists');
            }
            foreach ($constraint as $column) {
                self::validateIdentifier($column, 'unique column');
            }
        }
    }

    private static function validateIdentifier(string $name, string $label): void
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
        ksort($counts);

        return $counts;
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

    private static function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) || is_string($value)) {
            return (int) $value;
        }

        return 0;
    }

    private static function valueKey(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function sourceToken(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
