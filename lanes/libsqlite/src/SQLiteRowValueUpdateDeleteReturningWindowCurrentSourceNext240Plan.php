<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext240Plan
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
        string $savepoint = 'wp_options_rowvalue_window_groups_next240',
        string $rowIdColumn = 'option_id',
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $yieldGroups = self::peerGroupRows($base['yield_current_row_frames_next236'], $rowIdColumn);
        $suppressedGroups = self::peerGroupRows($base['suppressed_current_row_frames_next236'], $rowIdColumn);
        $retryGroups = self::peerGroupRows($base['retry_current_row_frames_next236'], $rowIdColumn);

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
            'retry_peer_group_receipt_next240' => self::receipt($base, $retryGroups, $rowIdColumn),
            'dependency_closure_next240' => 'no new support component needed; next240 reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint row images, and next236 current-row window metadata while adding peer-group exclusion receipts',
            'dependencies_next240' => [
                'sqlite-rowvalue-returning-window-peer-groups-next240',
                'sqlite-rowvalue-returning-window-exclude-current-row-next240',
                'sqlite-rowvalue-returning-window-exclude-ties-next240',
                'wordpress-rowvalue-returning-window-current-source-next240',
            ],
            'non_overlap_next240' => 'adds peer-group GROUPS-style window receipts, EXCLUDE CURRENT ROW, EXCLUDE TIES, percent_rank, cume_dist, and ntile metadata over row-value UPDATE/DELETE RETURNING streams after rollback/release; avoids accepted next236 current-row frames, next235 stream row numbers, next233 aggregate window receipts, row-value savepoint retry variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, PRAGMA, and encoding clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return list<array<string,mixed>>
     */
    private static function peerGroupRows(array $frames, string $rowIdColumn): array
    {
        $totalRows = count($frames);
        $totalBytes = array_sum(array_map(static fn (array $row): int => self::intValue($row['current_row_value'] ?? null), $frames));
        $peerCounts = [];
        $peerSums = [];

        foreach ($frames as $row) {
            $key = self::peerKey($row);
            $peerCounts[$key] = ($peerCounts[$key] ?? 0) + 1;
            $peerSums[$key] = ($peerSums[$key] ?? 0) + self::intValue($row['current_row_value'] ?? null);
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

            $key = self::peerKey($row);
            $seenInGroup[$key] = ($seenInGroup[$key] ?? 0) + 1;
            $value = self::intValue($row['current_row_value'] ?? null);
            $rank = $rankByGroup[$key];
            $cumeRows = $rank + $peerCounts[$key] - 1;

            $rows[] = [
                $rowIdColumn => $id,
                'option_name' => (string) ($row['option_name'] ?? ''),
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
    private static function peerKey(array $row): string
    {
        return ((string) ($row['status'] ?? '')) . '|' . self::intValue($row['current_row_value'] ?? null);
    }

    /**
     * @param array<string,mixed> $base
     * @param list<array<string,mixed>> $retryGroups
     * @return array<string,mixed>
     */
    private static function receipt(array $base, array $retryGroups, string $rowIdColumn): array
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

    private static function intValue(mixed $value): int
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
}
