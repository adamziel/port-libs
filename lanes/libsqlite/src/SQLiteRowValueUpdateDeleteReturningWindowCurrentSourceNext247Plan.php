<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext247Plan
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
        string $savepoint = 'wp_options_rowvalue_returning_window_next247',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext244Plan::execute(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $groups = self::excludeGroups($plan['window_transition_chains_next244'], $rowIdColumn);
        $summary = self::excludeGroupSummary($groups);

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next247';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next247'] = true;
        $plan['window_exclude_group_rows_next247'] = $groups;
        $plan['window_exclude_group_count_next247'] = count($groups);
        $plan['window_exclude_group_summary_next247'] = $summary;
        $plan['window_exclude_group_replayed_ids_next247'] = self::idsForClass($groups, 'replayed-after-rollback');
        $plan['window_exclude_group_restart_ids_next247'] = self::idsForClass($groups, 'restart-only');
        $plan['window_exclude_group_discarded_ids_next247'] = self::idsForClass($groups, 'discarded-only');
        $plan['window_exclude_group_keys_next247'] = array_column($groups, 'exclude_group_key_next247');
        $plan['window_exclude_group_fence_next247'] = [
            'savepoint' => $savepoint,
            'frame_mode' => 'GROUPS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING EXCLUDE GROUP',
            'source_transition_count' => $plan['window_transition_chain_count_next244'],
            'exclude_group_count' => count($groups),
            'excluded_group_digest' => self::digest($groups),
            'transition_digest' => $plan['window_transition_fence_next244']['transition_digest'],
            'rolled_back_to_savepoint' => $plan['rolled_back_to_savepoint'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
        ];
        $plan['dependencies'] = [
            'sqlite-rowvalue-returning-window-exclude-group-next247',
            'sqlite-rowvalue-returning-transition-peer-groups-next247',
            'wordpress-rowvalue-returning-window-current-source-next247',
        ];
        $plan['dependency_closure_next247'] = 'no new support component needed; next247 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next241 current-row frames, and next244 transition chains.';
        $plan['non_overlap_next247'] = 'adds GROUPS EXCLUDE GROUP accounting over next244 transition-chain partitions; avoids next244 lag/lead edges, next243 tuple frames, next241 CURRENT ROW frames, next240 peer exclusions, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner, encoding, and suite-evidence clusters.';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $chains
     * @return list<array<string,mixed>>
     */
    private static function excludeGroups(array $chains, string $rowIdColumn): array
    {
        $partitions = [];
        foreach ($chains as $chain) {
            $partition = self::stringValue($chain['transition_partition_next244'] ?? null, 'partition');
            $partitions[$partition][] = $chain;
        }

        $rows = [];
        foreach ($partitions as $partition => $partitionRows) {
            usort($partitionRows, static fn (array $left, array $right): int => self::compareRowIds($left['transition_rowid_next244'] ?? null, $right['transition_rowid_next244'] ?? null));
            $groupsByClass = [];
            foreach ($partitionRows as $row) {
                $class = self::stringValue($row['transition_class_next244'] ?? null, 'class');
                $groupsByClass[$class][] = $row;
            }
            $classes = array_keys($groupsByClass);

            foreach ($partitionRows as $ordinal => $row) {
                $class = self::stringValue($row['transition_class_next244'] ?? null, 'class');
                $rowid = self::rowId($row['transition_rowid_next244'] ?? null, $rowIdColumn);
                $excludedRows = $groupsByClass[$class] ?? [];
                $frameRows = [];
                foreach ($partitionRows as $candidate) {
                    if (self::stringValue($candidate['transition_class_next244'] ?? null, 'candidate class') !== $class) {
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
                    'exclude_group_peer_rowids_next247' => self::rowIds($excludedRows, 'transition_rowid_next244', $rowIdColumn),
                    'exclude_group_frame_count_next247' => count($frameRows),
                    'exclude_group_frame_rowids_next247' => self::rowIds($frameRows, 'transition_rowid_next244', $rowIdColumn),
                    'exclude_group_frame_classes_next247' => array_values(array_map(static fn (array $frame): string => (string) $frame['transition_class_next244'], $frameRows)),
                    'exclude_group_replayed_frame_count_next247' => self::countClass($frameRows, 'replayed-after-rollback'),
                    'exclude_group_restart_frame_count_next247' => self::countClass($frameRows, 'restart-only'),
                    'exclude_group_discarded_frame_count_next247' => self::countClass($frameRows, 'discarded-only'),
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
            $partition = self::stringValue($group['exclude_group_partition_next247'] ?? null, 'partition');
            $class = self::stringValue($group['exclude_group_class_next247'] ?? null, 'class');
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
    private static function idsForClass(array $groups, string $class): array
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
    private static function rowIds(array $rows, string $column, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = self::rowId($row[$column] ?? null, $rowIdColumn);
        }

        return $ids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function countClass(array $rows, string $class): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $count += (int) (($row['transition_class_next244'] ?? null) === $class);
        }

        return $count;
    }

    private static function rowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING EXCLUDE GROUP next247 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function stringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value RETURNING EXCLUDE GROUP next247 {$name} is missing");
        }

        return $value;
    }

    private static function compareRowIds(mixed $left, mixed $right): int
    {
        if ((is_int($left) || ctype_digit((string) $left)) && (is_int($right) || ctype_digit((string) $right))) {
            return (int) $left <=> (int) $right;
        }

        return ((string) $left) <=> ((string) $right);
    }

    /**
     * @param list<array<string,mixed>> $groups
     */
    private static function digest(array $groups): string
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
}
