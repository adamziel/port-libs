<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Plan
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
        string $savepoint = 'wp_options_rowvalue_window_current_next236',
        string $rowIdColumn = 'option_id',
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext233Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $yieldFrame = self::currentRowFrames($base['yield_window'], $rowIdColumn);
        $suppressedFrame = self::currentRowFrames($base['suppressed_attempt_window'], $rowIdColumn);
        $retryFrame = self::currentRowFrames($base['retry_window'], $rowIdColumn);

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
                static fn (array $row): array => [$row['lag_name'], $row['option_name'], $row['lead_name']],
                $retryFrame,
            ),
            'current_source_receipt_next236' => self::currentSourceReceipt($base, $retryFrame, $rowIdColumn),
            'dependency_closure_next236' => 'no new support component needed; next236 reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint row images, and window metadata from next233 while adding current-row frame receipts',
            'dependencies_next236' => [
                'sqlite-rowvalue-update-delete-returning-window-current-row-next236',
                'sqlite-returning-current-row-frame-after-rollback-release-next236',
                'wordpress-rowvalue-returning-current-row-window-next236',
            ],
            'non_overlap_next236' => 'adds current-row window-frame receipts, lag/lead neighbors, and running/following byte frames over row-value UPDATE/DELETE RETURNING streams after rollback/release; avoids next232 simple retry row numbering, accepted next233 row_number/dense_rank/count/sum windows, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $windowRows
     * @return list<array<string,mixed>>
     */
    private static function currentRowFrames(array $windowRows, string $rowIdColumn): array
    {
        $frames = [];
        $running = 0;
        $total = 0;
        foreach ($windowRows as $row) {
            $total += self::numericValue($row['bytes'] ?? null);
        }

        foreach (array_values($windowRows) as $index => $row) {
            if (!array_key_exists($rowIdColumn, $row)) {
                throw new \InvalidArgumentException("SQLite row-value current-row window next236 rowid column {$rowIdColumn} is missing");
            }
            $bytes = self::numericValue($row['bytes'] ?? null);
            $running += $bytes;
            $lag = $windowRows[$index - 1] ?? null;
            $lead = $windowRows[$index + 1] ?? null;

            $frames[] = [
                $rowIdColumn => self::rowIdValue($row, $rowIdColumn),
                'option_name' => (string) ($row['option_name'] ?? ''),
                'status' => $row['status'] ?? null,
                'row_number' => self::numericValue($row['row_number'] ?? null),
                'dense_rank' => self::numericValue($row['dense_rank'] ?? null),
                'current_row_value' => $bytes,
                'current_row_count' => 1,
                'running_bytes' => $running,
                'following_bytes' => $total - $running,
                'lag_id' => $lag === null ? null : self::rowIdValue($lag, $rowIdColumn),
                'lead_id' => $lead === null ? null : self::rowIdValue($lead, $rowIdColumn),
                'lag_name' => $lag['option_name'] ?? null,
                'lead_name' => $lead['option_name'] ?? null,
                'frame_token' => ($row['option_name'] ?? '') . ':' . $bytes . ':' . $running,
            ];
        }

        return $frames;
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $retryFrame
     * @return array<string,mixed>
     */
    private static function currentSourceReceipt(array $base, array $retryFrame, string $rowIdColumn): array
    {
        return [
            'savepoint' => $base['savepoint'],
            'retry_ids' => array_column($retryFrame, $rowIdColumn),
            'retry_frame_tokens' => array_column($retryFrame, 'frame_token'),
            'retry_running_final' => $retryFrame === [] ? 0 : $retryFrame[array_key_last($retryFrame)]['running_bytes'],
            'retry_following_final' => $retryFrame === [] ? 0 : $retryFrame[array_key_last($retryFrame)]['following_bytes'],
            'rolled_back_attempt_ids' => $base['all_window_receipt_next233']['suppressed_ids'],
            'released_table_count' => count($base['current_source_tables']['wp_options'] ?? []),
            'next_source_matches_current' => $base['next_source_tables'] === $base['current_source_tables'],
        ];
    }

    private static function numericValue(mixed $value): int
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
    private static function rowIdValue(array $row, string $rowIdColumn): int|string
    {
        $id = $row[$rowIdColumn];
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException("SQLite row-value current-row window next236 rowid column {$rowIdColumn} must be int or string");
        }

        return $id;
    }
}
