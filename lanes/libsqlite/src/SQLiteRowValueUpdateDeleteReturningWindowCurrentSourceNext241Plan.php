<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext241Plan
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
        string $savepoint = 'wp_options_rowvalue_returning_window_next241',
        string $rowIdColumn = 'option_id',
    ): array {
        $plan = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext238Plan::execute(
            $tables,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
        );

        $frames = self::currentRowFrames($plan['window_pair_rows_next238'], $rowIdColumn);
        $summary = self::frameSummary($frames);
        $fence = [
            'savepoint' => $savepoint,
            'frame_mode' => 'ROWS BETWEEN CURRENT ROW AND CURRENT ROW',
            'pair_count' => count($plan['window_pair_rows_next238']),
            'frame_count' => count($frames),
            'frame_digest' => self::digest($frames, 'frame_key_next241'),
            'source_pair_digest' => $plan['window_source_fence_next238']['pair_digest'],
            'current_source_digest' => $plan['window_source_fence_next238']['current_source_digest'],
            'next_source_digest' => $plan['window_source_fence_next238']['next_source_digest'],
            'retry_reads_savepoint_image' => $plan['retry_reads_savepoint_image'],
            'rolled_back_to_savepoint' => $plan['rolled_back_to_savepoint'],
        ];

        $plan['status'] = 'rowvalue-update-delete-returning-window-current-source-next241';
        $plan['savepoint'] = $savepoint;
        $plan['returning_window_current_source_next241'] = true;
        $plan['window_current_row_frames_next241'] = $frames;
        $plan['window_current_row_frame_count_next241'] = count($frames);
        $plan['window_current_row_summary_next241'] = $summary;
        $plan['window_current_row_fence_next241'] = $fence;
        $plan['window_current_row_replayed_ids_next241'] = self::idsForFrameClass($frames, 'replayed-after-rollback');
        $plan['window_current_row_restart_ids_next241'] = self::idsForFrameClass($frames, 'restart-only');
        $plan['window_current_row_discarded_ids_next241'] = self::idsForFrameClass($frames, 'discarded-only');
        $plan['window_current_row_actions_next241'] = array_values(array_unique(array_column($frames, 'frame_action_next241')));
        $plan['window_current_row_classes_next241'] = array_values(array_unique(array_column($frames, 'frame_class_next241')));
        $plan['dependencies'] = [
            'sqlite-rowvalue-returning-window-current-row-frame-next241',
            'sqlite-rowvalue-update-delete-returning-current-source-fence-next241',
            'wordpress-rowvalue-returning-window-current-source-next241',
        ];
        $plan['dependency_closure_next241'] = 'no new support component needed; next241 reuses native row-value UPDATE/DELETE RETURNING execution, savepoint rollback, next235 window rows, and next238 current/next pair classification.';
        $plan['non_overlap_next241'] = 'adds CURRENT ROW frame isolation over next238 current/next source pairs; avoids accepted next237 EXCLUDE CURRENT ROW retry windows, next238 pair classification, next235 window materialization, row-value savepoint conflict variants, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner, and encoding clusters.';

        return $plan;
    }

    /**
     * @param list<array<string,mixed>> $pairs
     * @return list<array<string,mixed>>
     */
    private static function currentRowFrames(array $pairs, string $rowIdColumn): array
    {
        $ordered = $pairs;
        usort($ordered, static function (array $left, array $right): int {
            $action = ((string) $left['action_next238']) <=> ((string) $right['action_next238']);
            if ($action !== 0) {
                return $action;
            }

            return self::compareRowIds($left['rowid_next238'], $right['rowid_next238']);
        });

        $actionOrdinals = [];
        $frames = [];
        foreach ($ordered as $ordinal => $pair) {
            $action = (string) $pair['action_next238'];
            $actionOrdinals[$action] = ($actionOrdinals[$action] ?? 0) + 1;
            $rowid = $pair['rowid_next238'];
            if (!is_int($rowid) && !is_string($rowid)) {
                throw new \InvalidArgumentException("SQLite row-value RETURNING window next241 rowid column {$rowIdColumn} must be int or string");
            }

            $class = (string) $pair['pair_class_next238'];
            $frameKey = $action . ':' . $rowid . ':' . $class;
            $frames[] = [
                'frame_ordinal_next241' => $ordinal,
                'frame_key_next241' => $frameKey,
                'frame_action_next241' => $action,
                'frame_class_next241' => $class,
                'frame_rowid_next241' => $rowid,
                'frame_pair_key_next241' => $pair['pair_key_next238'],
                'frame_action_ordinal_next241' => $actionOrdinals[$action],
                'frame_count_next241' => 1,
                'frame_rowids_next241' => [$rowid],
                'frame_classes_next241' => [$class],
                'frame_current_present_next241' => (bool) $pair['current_present_next238'],
                'frame_next_present_next241' => (bool) $pair['next_present_next238'],
                'frame_replayed_next241' => (bool) $pair['retry_replayed_next238'],
                'frame_restart_only_next241' => (bool) $pair['retry_restart_only_next238'],
                'frame_discarded_only_next241' => (bool) $pair['rollback_preserved_current_next238'],
                'frame_current_status_next241' => $pair['current_status_next238'],
                'frame_next_status_next241' => $pair['next_status_next238'],
                'frame_current_value_next241' => $pair['current_option_value_next238'],
                'frame_next_value_next241' => $pair['next_option_value_next238'],
                'frame_source_isolated_next241' => true,
                'frame_current_row_boundary_next241' => 'current-row-only',
            ];
        }

        return $frames;
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return array<string,mixed>
     */
    private static function frameSummary(array $frames): array
    {
        $summary = [
            'frame_count' => count($frames),
            'current_row_only_frames' => 0,
            'replayed-after-rollback' => 0,
            'restart-only' => 0,
            'discarded-only' => 0,
            'update' => 0,
            'delete' => 0,
            'rowids_by_action' => [],
            'classes_by_action' => [],
        ];

        foreach ($frames as $frame) {
            $action = (string) $frame['frame_action_next241'];
            $class = (string) $frame['frame_class_next241'];
            $summary['current_row_only_frames'] += (int) ((bool) $frame['frame_source_isolated_next241']);
            $summary[$class] = ((int) ($summary[$class] ?? 0)) + 1;
            $summary[$action] = ((int) ($summary[$action] ?? 0)) + 1;
            $summary['rowids_by_action'][$action][] = $frame['frame_rowid_next241'];
            $summary['classes_by_action'][$action][] = $class;
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $frames
     * @return list<int|string>
     */
    private static function idsForFrameClass(array $frames, string $class): array
    {
        $ids = [];
        foreach ($frames as $frame) {
            if ($frame['frame_class_next241'] === $class) {
                $id = $frame['frame_rowid_next241'];
                if (is_int($id) || is_string($id)) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    private static function compareRowIds(mixed $left, mixed $right): int
    {
        if ((is_int($left) || ctype_digit((string) $left)) && (is_int($right) || ctype_digit((string) $right))) {
            return (int) $left <=> (int) $right;
        }

        return ((string) $left) <=> ((string) $right);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function digest(array $rows, string $keyColumn): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = implode(':', [
                (string) ($row[$keyColumn] ?? ''),
                (string) ($row['frame_ordinal_next241'] ?? ''),
                (string) ($row['frame_action_ordinal_next241'] ?? ''),
                (string) ($row['frame_current_row_boundary_next241'] ?? ''),
            ]);
        }

        return hash('sha256', implode('|', $parts));
    }
}
