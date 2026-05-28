<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Plan
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
        string $savepoint = 'wp_options_rowvalue_window_current_next246',
        string $rowIdColumn = 'option_id',
    ): array {
        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext242Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $retryRows = self::annotateFilteredRows($base['retry_chained_windows_next242'], 'retry-release-current-source-next246', $rowIdColumn);
        $suppressedRows = self::annotateFilteredRows($base['suppressed_chained_windows_next242'], 'suppressed-rollback-current-source-next246', $rowIdColumn);
        $yieldRows = self::annotateFilteredRows($base['yield_chained_windows_next242'], 'yield-before-rollback-current-source-next246', $rowIdColumn);
        $audit = self::releaseAudit($base, $retryRows, $suppressedRows, $yieldRows, $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next246',
            'savepoint' => $savepoint,
            'returning_window_current_source_next246' => true,
            'retry_filter_windows_next246' => $retryRows,
            'suppressed_filter_windows_next246' => $suppressedRows,
            'yield_filter_windows_next246' => $yieldRows,
            'retry_filter_summary_next246' => self::summaryByPartition($retryRows),
            'suppressed_filter_summary_next246' => self::summaryByPartition($suppressedRows),
            'yield_filter_summary_next246' => self::summaryByPartition($yieldRows),
            'release_filter_audit_next246' => $audit,
            'dependencies_next246' => [
                'sqlite-returning-window-filter-release-current-source-next246',
                'sqlite-rowvalue-update-delete-returning-retry-filter-next246',
                'wordpress-rowvalue-returning-filtered-window-next246',
            ],
            'dependency_closure_next246' => 'no new support component needed; next246 reuses row-value UPDATE/DELETE RETURNING execution, next242 released current-source windows, and bounded PHP window FILTER receipt calculation.',
            'non_overlap_next246' => 'adds FILTER-style retry/yield/suppressed release receipts over row-value UPDATE/DELETE RETURNING windows; avoids accepted next242 lag/lead and ROWS/GROUPS frames, next239 ntile/percent/cume windows, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner range-cost, and encoding clusters.',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<array<string,mixed>>>
     */
    private static function annotateFilteredRows(array $partitions, string $sourceTag, string $rowIdColumn): array
    {
        $annotated = [];
        foreach ($partitions as $key => $rows) {
            if (!is_string($key) || !array_is_list($rows)) {
                throw new \InvalidArgumentException('SQLite row-value window next246 partitions are malformed');
            }

            $updateRows = self::rowsForAction($rows, 'update');
            $deleteRows = self::rowsForAction($rows, 'delete');
            $updateIds = self::rowIds($updateRows, $rowIdColumn);
            $deleteIds = self::rowIds($deleteRows, $rowIdColumn);
            $allIds = self::rowIds($rows, $rowIdColumn);
            $updateBytes = self::sumBytes($updateRows);
            $deleteBytes = self::sumBytes($deleteRows);
            $totalBytes = self::sumBytes($rows);

            $partitionRows = [];
            foreach ($rows as $ordinal => $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite row-value window next246 rows are malformed');
                }
                $action = (string) ($row['statement_action'] ?? '');
                $id = self::rowIdValue($row, $rowIdColumn);
                $isUpdate = $action === 'update';
                $isDelete = $action === 'delete';

                $partitionRows[] = [
                    $rowIdColumn => $id,
                    'filter_source_next246' => $sourceTag,
                    'filter_partition_key_next246' => $key,
                    'filter_ordinal_next246' => $ordinal,
                    'filter_action_next246' => $action,
                    'filter_status_next246' => (string) ($row['lead_status'] ?? $row['lag_status'] ?? ''),
                    'filter_bytes_next246' => self::currentBytes($row),
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
    private static function releaseAudit(array $base, array $retryRows, array $suppressedRows, array $yieldRows, string $rowIdColumn): array
    {
        $retryIds = self::flatIds($retryRows, $rowIdColumn);
        $suppressedIds = self::flatIds($suppressedRows, $rowIdColumn);
        $yieldIds = self::flatIds($yieldRows, $rowIdColumn);
        $finalRows = $base['current_source_tables']['wp_options'] ?? [];
        if (!is_array($finalRows) || !array_is_list($finalRows)) {
            throw new \InvalidArgumentException('SQLite row-value window next246 final source rows are malformed');
        }
        $finalIds = self::rowIds($finalRows, $rowIdColumn);
        $retryDeleteIds = self::deleteIds($base['retry_returning'] ?? [], $rowIdColumn);
        $retryUpdateIds = array_values(array_diff($retryIds, $retryDeleteIds));

        return [
            'savepoint' => (string) ($base['savepoint'] ?? ''),
            'retry_ids' => $retryIds,
            'retry_update_ids' => $retryUpdateIds,
            'retry_delete_ids' => $retryDeleteIds,
            'suppressed_ids' => $suppressedIds,
            'yield_ids' => $yieldIds,
            'final_ids' => $finalIds,
            'retry_updates_visible_after_release' => self::containsAll($finalIds, $retryUpdateIds),
            'retry_deletes_absent_after_release' => count(array_intersect($finalIds, $retryDeleteIds)) === 0,
            'suppressed_only_visible_after_release' => self::containsAll($finalIds, array_values(array_diff($suppressedIds, $retryIds))),
            'yield_delete_restored_by_rollback' => self::containsAll($finalIds, array_values(array_diff($yieldIds, $retryDeleteIds))),
            'retry_filter_digest' => self::digest($retryRows),
            'suppressed_filter_digest' => self::digest($suppressedRows),
            'yield_filter_digest' => self::digest($yieldRows),
            'digests_are_isolated' => count(array_unique([self::digest($retryRows), self::digest($suppressedRows), self::digest($yieldRows)])) === 3,
        ];
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,array<string,mixed>>
     */
    private static function summaryByPartition(array $partitions): array
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
    private static function rowsForAction(array $rows, string $action): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => ($row['statement_action'] ?? null) === $action));
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
     * @param list<array<string,mixed>> $rows
     */
    private static function sumBytes(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += self::currentBytes($row);
        }

        return $sum;
    }

    /**
     * @param mixed $statements
     * @return list<int|string>
     */
    private static function deleteIds(mixed $statements, string $rowIdColumn): array
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
            throw new \InvalidArgumentException("SQLite row-value window next246 rowid column {$rowIdColumn} is missing");
        }
        $value = $row[$rowIdColumn];
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value window next246 rowid column {$rowIdColumn} must be int or string");
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

        throw new \InvalidArgumentException('SQLite row-value window next246 byte values must be integer-like');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function currentBytes(array $row): int
    {
        if (array_key_exists('bytes', $row)) {
            return self::numericValue($row['bytes']);
        }

        $peerCount = count($row['groups_frame_ids'] ?? []);
        if ($peerCount > 0) {
            return intdiv(self::numericValue($row['groups_frame_sum'] ?? 0), $peerCount);
        }

        return self::numericValue($row['rows_frame_sum'] ?? 0);
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function digest(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
