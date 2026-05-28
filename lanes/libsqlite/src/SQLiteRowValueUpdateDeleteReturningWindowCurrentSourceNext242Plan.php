<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext242Plan
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
        string $savepoint = 'wp_options_rowvalue_window_current_next242',
        string $rowIdColumn = 'option_id',
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext239Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $retryWindows = self::chainedWindows($base['retry_statement_windows_next239'], $rowIdColumn);
        $suppressedWindows = self::chainedWindows($base['suppressed_statement_windows_next239'], $rowIdColumn);
        $yieldWindows = self::chainedWindows($base['yield_statement_windows_next239'], $rowIdColumn);
        $seal = self::sourceSeal($base, $retryWindows, $suppressedWindows, $yieldWindows, $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next242',
            'returning_window_current_source_next242' => true,
            'retry_chained_windows_next242' => $retryWindows,
            'suppressed_chained_windows_next242' => $suppressedWindows,
            'yield_chained_windows_next242' => $yieldWindows,
            'retry_lag_ids_next242' => self::partitionColumn($retryWindows, 'lag_id'),
            'retry_lead_ids_next242' => self::partitionColumn($retryWindows, 'lead_id'),
            'retry_rows_frame_ids_next242' => self::partitionColumn($retryWindows, 'rows_frame_ids'),
            'retry_groups_frame_ids_next242' => self::partitionColumn($retryWindows, 'groups_frame_ids'),
            'retry_frame_sums_next242' => self::partitionColumn($retryWindows, 'rows_frame_sum'),
            'retry_group_sums_next242' => self::partitionColumn($retryWindows, 'groups_frame_sum'),
            'retry_source_ordinals_next242' => self::partitionColumn($retryWindows, 'source_ordinal'),
            'source_generation_seal_next242' => $seal,
            'dependencies_next242' => [
                'sqlite-returning-window-lag-lead-current-source-next242',
                'sqlite-returning-window-groups-frame-current-source-next242',
                'wordpress-rowvalue-update-delete-returning-release-fence-next242',
            ],
            'dependency_closure_next242' => 'no new support component needed; next242 reuses the row-value UPDATE/DELETE RETURNING executor, savepoint current-source image, and next239 statement-partition window rows.',
            'non_overlap_next242' => 'adds lag/lead, ROWS/GROUPS frame receipts, and current-source release seals over row-value UPDATE/DELETE RETURNING retry windows; avoids accepted next238 pair classification, next239 ntile/percent/cume partition windows, trigger RETURNING, WAL/VFS, JSON, B-tree, PRAGMA, and encoding clusters.',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<array<string,mixed>>>
     */
    private static function chainedWindows(array $partitions, string $rowIdColumn): array
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

                $id = self::rowIdValue($row, $rowIdColumn);
                $previous = $rows[$index - 1] ?? null;
                $next = $rows[$index + 1] ?? null;
                $frame = array_slice($rows, max(0, $index - 1), min($count, $index + 2) - max(0, $index - 1));
                $groups = self::peerRows($rows, self::numericValue($row['bytes'] ?? null));

                $windowRows[] = [
                    $rowIdColumn => $id,
                    'statement_key' => (string) ($row['statement_key'] ?? $key),
                    'statement_action' => (string) ($row['statement_action'] ?? ''),
                    'source_ordinal' => $index,
                    'source_count' => $count,
                    'lag_id' => is_array($previous) ? self::rowIdValue($previous, $rowIdColumn) : null,
                    'lead_id' => is_array($next) ? self::rowIdValue($next, $rowIdColumn) : null,
                    'lag_status' => is_array($previous) ? ($previous['status'] ?? null) : null,
                    'lead_status' => is_array($next) ? ($next['status'] ?? null) : null,
                    'rows_frame_ids' => self::rowIds($frame, $rowIdColumn),
                    'rows_frame_sum' => self::sumBytes($frame),
                    'groups_frame_ids' => self::rowIds($groups, $rowIdColumn),
                    'groups_frame_sum' => self::sumBytes($groups),
                    'first_value_name' => (string) ($rows[0]['option_name'] ?? ''),
                    'last_value_name' => (string) ($rows[$count - 1]['option_name'] ?? ''),
                    'window_token_next242' => $key . ':' . $id . ':' . self::sumBytes($frame) . ':' . self::sumBytes($groups),
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
    private static function sourceSeal(array $base, array $retryWindows, array $suppressedWindows, array $yieldWindows, string $rowIdColumn): array
    {
        $retryIds = self::flatIds($retryWindows, $rowIdColumn);
        $suppressedIds = self::flatIds($suppressedWindows, $rowIdColumn);
        $yieldIds = self::flatIds($yieldWindows, $rowIdColumn);
        $finalIds = self::tableIds($base['current_source_tables']['wp_options'] ?? [], $rowIdColumn);

        return [
            'savepoint' => (string) ($base['savepoint'] ?? ''),
            'retry_ids' => $retryIds,
            'suppressed_ids' => $suppressedIds,
            'yield_ids' => $yieldIds,
            'suppressed_only_ids' => array_values(array_diff($suppressedIds, $retryIds)),
            'retry_replayed_yield_ids' => array_values(array_intersect($retryIds, $yieldIds)),
            'final_source_ids' => $finalIds,
            'final_contains_retry_ids' => self::containsAll($finalIds, array_values(array_diff($retryIds, self::deletedRetryIds($base)))),
            'final_excludes_retry_delete_ids' => count(array_intersect($finalIds, self::deletedRetryIds($base))) === 0,
            'final_contains_suppressed_only_ids' => self::containsAll($finalIds, array_values(array_diff($suppressedIds, $retryIds))),
            'rollback_restored_savepoint_image' => ($base['rollback_current_source_tables'] ?? null) === ($base['savepoint_image_tables'] ?? null),
            'attempt_source_discarded' => ($base['attempt_current_source_tables'] ?? null) !== ($base['current_source_tables'] ?? null),
            'retry_window_digest' => self::digest($retryWindows),
            'suppressed_window_digest' => self::digest($suppressedWindows),
            'yield_window_digest' => self::digest($yieldWindows),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function peerRows(array $rows, int $bytes): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => self::numericValue($row['bytes'] ?? null) === $bytes));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int|string>
     */
    private static function rowIds(array $rows, string $rowIdColumn): array
    {
        return array_map(static fn (array $row): int|string => self::rowIdValue($row, $rowIdColumn), $rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function sumBytes(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += self::numericValue($row['bytes'] ?? null);
        }

        return $sum;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<mixed>>
     */
    private static function partitionColumn(array $partitions, string $column): array
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
    private static function flatIds(array $partitions, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($partitions as $rows) {
            array_push($ids, ...self::rowIds($rows, $rowIdColumn));
        }

        return $ids;
    }

    /**
     * @param mixed $rows
     * @return list<int|string>
     */
    private static function tableIds(mixed $rows, string $rowIdColumn): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite row-value window next242 source table rows are malformed');
        }

        return self::rowIds($rows, $rowIdColumn);
    }

    /**
     * @param array<string,mixed> $base
     * @return list<int|string>
     */
    private static function deletedRetryIds(array $base): array
    {
        $ids = [];
        foreach (($base['retry_returning'] ?? []) as $statement) {
            if (($statement['action'] ?? null) !== 'delete' || !isset($statement['rows']) || !is_array($statement['rows'])) {
                continue;
            }
            foreach ($statement['rows'] as $row) {
                if (is_array($row) && array_key_exists('option_id', $row)) {
                    $id = $row['option_id'];
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
    private static function containsAll(array $haystack, array $needles): bool
    {
        return array_values(array_intersect($needles, $haystack)) === array_values($needles);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowIdValue(array $row, string $rowIdColumn): int|string
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

    private static function numericValue(mixed $value): int
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
    private static function digest(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
