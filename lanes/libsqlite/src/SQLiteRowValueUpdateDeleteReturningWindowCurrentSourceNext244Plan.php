<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext244Plan
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
        string $savepoint = 'wp_options_rowvalue_returning_window_next244',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext241Plan::execute(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $chains = self::transitionChains($plan['window_current_row_frames_next241'], $rowIdColumn);
        $summary = self::transitionSummary($chains);

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next244';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next244'] = true;
        $plan['window_transition_chains_next244'] = $chains;
        $plan['window_transition_chain_count_next244'] = count($chains);
        $plan['window_transition_summary_next244'] = $summary;
        $plan['window_transition_replayed_ids_next244'] = self::idsForClass($chains, 'replayed-after-rollback');
        $plan['window_transition_restart_ids_next244'] = self::idsForClass($chains, 'restart-only');
        $plan['window_transition_discarded_ids_next244'] = self::idsForClass($chains, 'discarded-only');
        $plan['window_transition_edge_keys_next244'] = array_column($chains, 'transition_edge_key_next244');
        $plan['window_transition_partition_keys_next244'] = array_values(array_unique(array_column($chains, 'transition_partition_next244')));
        $plan['window_transition_fence_next244'] = [
            'savepoint' => $savepoint,
            'frame_mode' => 'ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING',
            'source_frame_count' => $plan['window_current_row_frame_count_next241'],
            'transition_count' => count($chains),
            'transition_digest' => self::digest($chains),
            'current_row_frame_digest' => $plan['window_current_row_fence_next241']['frame_digest'],
            'pair_digest' => $plan['window_source_fence_next238']['pair_digest'],
            'rolled_back_to_savepoint' => $plan['rolled_back_to_savepoint'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
        ];
        $plan['dependencies'] = [
            'sqlite-rowvalue-returning-window-transition-chain-next244',
            'sqlite-rowvalue-returning-lag-lead-current-source-next244',
            'wordpress-rowvalue-returning-window-current-source-next244',
        ];
        $plan['dependency_closure_next244'] = 'no new support component needed; next244 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next238 pair classification, and next241 CURRENT ROW frame isolation.';
        $plan['non_overlap_next244'] = 'adds lag/lead transition-chain windows across isolated current/next row-value RETURNING pairs; avoids next238 pair classification, next239 statement partitions, next240 peer exclusions, next241 CURRENT ROW frames, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner, encoding, and suite-evidence clusters.';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return list<array<string,mixed>>
     */
    private static function transitionChains(array $frames, string $rowIdColumn): array
    {
        $partitions = [];
        foreach ($frames as $frame) {
            $action = self::stringValue($frame['frame_action_next241'] ?? null, 'action');
            $partitions[$action][] = $frame;
        }

        $chains = [];
        foreach ($partitions as $action => $rows) {
            usort($rows, static fn (array $left, array $right): int => self::compareRowIds($left['frame_rowid_next241'] ?? null, $right['frame_rowid_next241'] ?? null));
            $partitionCount = count($rows);
            foreach ($rows as $index => $row) {
                $rowid = self::rowId($row['frame_rowid_next241'] ?? null, $rowIdColumn);
                $class = self::stringValue($row['frame_class_next241'] ?? null, 'class');
                $previous = $rows[$index - 1] ?? null;
                $next = $rows[$index + 1] ?? null;
                $previousClass = $previous === null ? null : self::stringValue($previous['frame_class_next241'] ?? null, 'previous class');
                $nextClass = $next === null ? null : self::stringValue($next['frame_class_next241'] ?? null, 'next class');
                $previousId = $previous === null ? null : self::rowId($previous['frame_rowid_next241'] ?? null, $rowIdColumn);
                $nextId = $next === null ? null : self::rowId($next['frame_rowid_next241'] ?? null, $rowIdColumn);

                $chains[] = [
                    'transition_ordinal_next244' => count($chains),
                    'transition_partition_next244' => $action,
                    'transition_partition_ordinal_next244' => $index + 1,
                    'transition_partition_count_next244' => $partitionCount,
                    'transition_rowid_next244' => $rowid,
                    'transition_pair_key_next244' => self::stringValue($row['frame_pair_key_next241'] ?? null, 'pair key'),
                    'transition_edge_key_next244' => $action . ':' . (string) $previousId . '>' . (string) $rowid . '>' . (string) $nextId,
                    'transition_class_next244' => $class,
                    'transition_previous_class_next244' => $previousClass,
                    'transition_next_class_next244' => $nextClass,
                    'transition_previous_rowid_next244' => $previousId,
                    'transition_next_rowid_next244' => $nextId,
                    'transition_lag_class_changed_next244' => $previousClass !== null && $previousClass !== $class,
                    'transition_lead_class_changed_next244' => $nextClass !== null && $nextClass !== $class,
                    'transition_boundary_next244' => self::boundary($previous, $next),
                    'transition_frame_rowids_next244' => self::frameRowIds($previousId, $rowid, $nextId),
                    'transition_frame_classes_next244' => self::frameClasses($previousClass, $class, $nextClass),
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
    private static function transitionSummary(array $chains): array
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
            $partition = self::stringValue($chain['transition_partition_next244'] ?? null, 'partition');
            $class = self::stringValue($chain['transition_class_next244'] ?? null, 'class');
            $boundary = self::stringValue($chain['transition_boundary_next244'] ?? null, 'boundary');
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
    private static function idsForClass(array $chains, string $class): array
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

    private static function boundary(?array $previous, ?array $next): string
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
    private static function frameRowIds(int|string|null $previous, int|string $current, int|string|null $next): array
    {
        return array_values(array_filter([$previous, $current, $next], static fn (mixed $value): bool => is_int($value) || is_string($value)));
    }

    /**
     * @return list<string>
     */
    private static function frameClasses(?string $previous, string $current, ?string $next): array
    {
        return array_values(array_filter([$previous, $current, $next], static fn (mixed $value): bool => is_string($value)));
    }

    private static function rowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value RETURNING transition next244 rowid column {$rowIdColumn} must be int or string");
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

    private static function stringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value RETURNING transition next244 {$name} is missing");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $chains
     */
    private static function digest(array $chains): string
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
}
