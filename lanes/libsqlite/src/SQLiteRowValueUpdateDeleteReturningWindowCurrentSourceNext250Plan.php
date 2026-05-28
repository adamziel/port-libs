<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext250Plan
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
        string $savepoint = 'wp_options_rowvalue_returning_window_next250',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan::execute(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $rows = self::excludeTiesRows($plan['window_exclude_group_rows_next247'], $rowIdColumn);
        $summary = self::summary($rows);

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next250';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next250'] = true;
        $plan['window_exclude_ties_rows_next250'] = $rows;
        $plan['window_exclude_ties_count_next250'] = count($rows);
        $plan['window_exclude_ties_summary_next250'] = $summary;
        $plan['window_exclude_ties_replayed_ids_next250'] = self::idsForClass($rows, 'replayed-after-rollback');
        $plan['window_exclude_ties_restart_ids_next250'] = self::idsForClass($rows, 'restart-only');
        $plan['window_exclude_ties_discarded_ids_next250'] = self::idsForClass($rows, 'discarded-only');
        $plan['window_exclude_ties_receipts_next250'] = array_column($rows, 'exclude_ties_receipt_next250');
        $plan['window_exclude_ties_fence_next250'] = [
            'savepoint' => $savepoint,
            'frame_mode' => 'GROUPS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE TIES',
            'source_transition_count' => $plan['window_transition_chain_count_next244'],
            'exclude_group_count' => $plan['window_exclude_group_count_next247'],
            'exclude_ties_count' => count($rows),
            'exclude_ties_digest' => self::digest($rows),
            'exclude_group_digest' => $plan['window_exclude_group_fence_next247']['excluded_group_digest'],
            'transition_digest' => $plan['window_transition_fence_next244']['transition_digest'],
            'current_row_preserved' => self::allCurrentRowsPreserved($rows),
            'peer_ties_removed' => self::allPeerTiesRemoved($rows),
            'rolled_back_to_savepoint' => $plan['rolled_back_to_savepoint'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
        ];
        $plan['dependencies'] = [
            'sqlite-rowvalue-returning-window-exclude-ties-next250',
            'sqlite-rowvalue-returning-current-row-preserved-next250',
            'wordpress-rowvalue-returning-window-current-source-next250',
        ];
        $plan['dependency_closure_next250'] = 'no new support component needed; next250 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next244 transition chains, and next247 peer-group partitions.';
        $plan['non_overlap_next250'] = 'adds GROUPS EXCLUDE TIES accounting where the current RETURNING row remains visible and same-class peers are removed; avoids next247 EXCLUDE GROUP, next244 lag/lead transition chains, next243 tuple frames, next241 CURRENT ROW frames, row-value savepoint retry variants, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner, encoding, and suite-evidence clusters.';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $groups
     * @return list<array<string,mixed>>
     */
    private static function excludeTiesRows(array $groups, string $rowIdColumn): array
    {
        $rowsByPartition = [];
        foreach ($groups as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite row-value RETURNING EXCLUDE TIES next250 rows are malformed');
            }
            $partition = self::stringValue($row['exclude_group_partition_next247'] ?? null, 'partition');
            $rowsByPartition[$partition][] = $row;
        }

        $rows = [];
        foreach ($rowsByPartition as $partition => $partitionRows) {
            foreach ($partitionRows as $row) {
                $currentId = self::rowId($row['exclude_group_rowid_next247'] ?? null, $rowIdColumn);
                $class = self::stringValue($row['exclude_group_class_next247'] ?? null, 'class');
                $frameRows = [];
                $removedTieIds = [];
                foreach ($partitionRows as $candidate) {
                    $candidateId = self::rowId($candidate['exclude_group_rowid_next247'] ?? null, $rowIdColumn);
                    $candidateClass = self::stringValue($candidate['exclude_group_class_next247'] ?? null, 'candidate class');
                    if ($candidateClass === $class && $candidateId !== $currentId) {
                        $removedTieIds[] = $candidateId;
                        continue;
                    }
                    $frameRows[] = $candidate;
                }

                $frameIds = self::rowIds($frameRows, 'exclude_group_rowid_next247', $rowIdColumn);
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
    private static function summary(array $rows): array
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
            $partition = self::stringValue($row['exclude_ties_partition_next250'] ?? null, 'partition');
            $class = self::stringValue($row['exclude_ties_class_next250'] ?? null, 'class');
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
    private static function idsForClass(array $rows, string $class): array
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
    private static function rowIds(array $rows, string $column, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = self::rowId($row[$column] ?? null, $rowIdColumn);
        }

        return $ids;
    }

    private static function allCurrentRowsPreserved(array $rows): bool
    {
        foreach ($rows as $row) {
            if (($row['exclude_ties_current_row_preserved_next250'] ?? null) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    private static function allPeerTiesRemoved(array $rows): bool
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

    private static function rowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING EXCLUDE TIES next250 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function stringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value RETURNING EXCLUDE TIES next250 {$name} is missing");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function digest(array $rows): string
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
}
