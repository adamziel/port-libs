<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext232Plan
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
        string $savepoint = 'wp_options_rowvalue_window_current_next232',
        string $rowIdColumn = 'option_id',
    ): array {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $savepoint) !== 1) {
            throw new \InvalidArgumentException('SQLite row-value window current-source next232 savepoint must be an identifier');
        }

        $plan = SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext229Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $retryRows = $plan['retry_rows_after_release'];
        $windowRows = self::windowRows($retryRows, $rowIdColumn);
        $currentRows = $plan['current_source_tables']['wp_options'] ?? [];

        return array_merge($plan, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next232',
            'window_current_source_next232' => true,
            'window_retry_rows_after_release_next232' => $windowRows,
            'window_retry_ids_after_release_next232' => array_column($windowRows, $rowIdColumn),
            'window_retry_row_numbers_next232' => array_column($windowRows, 'row_number'),
            'window_retry_partition_numbers_next232' => array_column($windowRows, 'partition_row_number'),
            'current_source_window_order_next232' => self::currentSourceOrder($currentRows, $rowIdColumn),
            'dependency_closure_next232' => 'no new support component needed; next232 reuses native PHP row-value UPDATE/DELETE RETURNING subquery dispatch, savepoint row images, and bounded window-style row numbering over retry RETURNING rows',
            'dependencies_next232' => [
                'sqlite-rowvalue-update-returning-window-current-source-next232',
                'sqlite-delete-returning-window-current-source-next232',
                'wordpress-rowvalue-window-savepoint-current-source-next232',
            ],
            'non_overlap_next232' => 'adds window-style row numbering over row-value UPDATE/DELETE RETURNING retry rows after rollback/release; avoids accepted next229 subquery retry image coverage, next226 DISTINCT subquery coverage, next205 release-current-source coverage, window row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function windowRows(array $rows, string $rowIdColumn): array
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
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,int|string|null>>
     */
    private static function currentSourceOrder(array $rows, string $rowIdColumn): array
    {
        $ordered = [];
        foreach (array_values($rows) as $index => $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value window current-source next232 rowid column {$rowIdColumn} is missing");
            }
            $ordered[] = [
                'ordinal' => $index + 1,
                $rowIdColumn => $row[$rowIdColumn],
                'option_name' => $row['option_name'] ?? null,
                'status' => $row['status'] ?? null,
            ];
        }

        return $ordered;
    }
}
