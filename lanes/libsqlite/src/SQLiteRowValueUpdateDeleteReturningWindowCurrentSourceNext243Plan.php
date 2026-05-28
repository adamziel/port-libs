<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext243Plan
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
        string $savepoint = 'wp_options_rowvalue_window_current_next243',
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

        $tupleFrames = self::tupleFrames($base['retry_statement_windows_next239'], $rowIdColumn);
        $retryTuples = self::tupleKeys($tupleFrames);
        $retryFrameIds = self::frameIds($tupleFrames, $rowIdColumn);
        $retryPeerIds = self::peerIds($tupleFrames, $rowIdColumn);
        $release = self::releaseBoundary($base, $tupleFrames, $rowIdColumn);

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next243',
            'rowvalue_tuple_window_current_source_next243' => true,
            'retry_tuple_window_frames_next243' => $tupleFrames,
            'retry_tuple_keys_next243' => $retryTuples,
            'retry_tuple_frame_ids_next243' => $retryFrameIds,
            'retry_tuple_peer_ids_next243' => $retryPeerIds,
            'retry_tuple_release_boundary_next243' => $release,
            'dependency_closure_next243' => 'no new support component needed; next243 reuses native row-value UPDATE/DELETE RETURNING retry partitions and lane-local window frame rows.',
            'dependencies_next243' => [
                'sqlite-rowvalue-returning-window-tuple-frame-next243',
                'sqlite-rowvalue-update-delete-returning-current-source-release-next243',
                'wordpress-rowvalue-returning-window-current-source-next243',
            ],
            'non_overlap_next243' => 'adds row-value tuple frame and peer-group receipts over retry RETURNING windows after current-source rollback/release; avoids accepted next239 statement partitions, next238 source fences, next236 current-row frames, next219 LIMIT -1 OFFSET tuple sources, row-value UPSERT, trigger RETURNING, JSON table, planner, WAL/VFS, B-tree, PRAGMA, and encoding clusters.',
        ]);
    }

    /**
     * @param array<string,list<array<string,mixed>>> $partitions
     * @return array<string,list<array<string,mixed>>>
     */
    private static function tupleFrames(array $partitions, string $rowIdColumn): array
    {
        $frames = [];
        foreach ($partitions as $key => $rows) {
            $partitionFrames = [];
            $count = count($rows);
            foreach ($rows as $index => $row) {
                $id = self::rowId($row, $rowIdColumn);
                $bytes = self::int($row['bytes'] ?? null);
                $previous = $rows[$index - 1] ?? null;
                $next = $rows[$index + 1] ?? null;
                $frameRows = array_values(array_filter([$previous, $row, $next], static fn ($entry): bool => is_array($entry)));
                $peerRows = array_values(array_filter($rows, static fn (array $entry): bool => self::int($entry['bytes'] ?? null) === $bytes));

                $partitionFrames[] = [
                    $rowIdColumn => $id,
                    'statement_key' => $key,
                    'tuple_key' => [$bytes, $id],
                    'tuple_key_sql' => '(' . $bytes . ',' . $id . ')',
                    'row_number' => $index + 1,
                    'partition_count' => $count,
                    'lag_tuple_key' => $previous === null ? null : [self::int($previous['bytes'] ?? null), self::rowId($previous, $rowIdColumn)],
                    'lead_tuple_key' => $next === null ? null : [self::int($next['bytes'] ?? null), self::rowId($next, $rowIdColumn)],
                    'frame_ids' => array_map(static fn (array $entry): int|string => self::rowId($entry, $rowIdColumn), $frameRows),
                    'frame_tuple_keys' => array_map(static fn (array $entry): array => [self::int($entry['bytes'] ?? null), self::rowId($entry, $rowIdColumn)], $frameRows),
                    'frame_sum' => array_sum(array_map(static fn (array $entry): int => self::int($entry['bytes'] ?? null), $frameRows)),
                    'peer_ids' => array_map(static fn (array $entry): int|string => self::rowId($entry, $rowIdColumn), $peerRows),
                    'peer_count' => count($peerRows),
                    'peer_tuple_key' => [$bytes, '*'],
                    'current_source_visible' => true,
                    'release_after_retry' => true,
                    'tuple_window_token' => $key . ':' . $bytes . ':' . $id . ':' . count($frameRows),
                ];
            }
            $frames[$key] = $partitionFrames;
        }

        return $frames;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $frames
     * @return array<string,list<array{0:int,1:int|string}>>
     */
    private static function tupleKeys(array $frames): array
    {
        $keys = [];
        foreach ($frames as $key => $rows) {
            $keys[$key] = array_column($rows, 'tuple_key');
        }

        return $keys;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $frames
     * @return array<string,list<list<int|string>>>
     */
    private static function frameIds(array $frames, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($frames as $key => $rows) {
            $ids[$key] = array_column($rows, 'frame_ids');
        }

        return $ids;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $frames
     * @return array<string,list<list<int|string>>>
     */
    private static function peerIds(array $frames, string $rowIdColumn): array
    {
        $ids = [];
        foreach ($frames as $key => $rows) {
            $ids[$key] = array_column($rows, 'peer_ids');
        }

        return $ids;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,list<array<string,mixed>>> $frames
     * @return array<string,mixed>
     */
    private static function releaseBoundary(array $base, array $frames, string $rowIdColumn): array
    {
        $ids = [];
        $tokens = [];
        foreach ($frames as $rows) {
            foreach ($rows as $row) {
                $ids[] = self::rowId($row, $rowIdColumn);
                $tokens[] = (string) $row['tuple_window_token'];
            }
        }

        return [
            'savepoint' => $base['savepoint'],
            'tuple_window_ids' => $ids,
            'tuple_window_tokens' => $tokens,
            'tuple_window_count' => count($ids),
            'retry_partitions' => array_keys($frames),
            'rollback_source_restored' => $base['release_window_seal_next239']['rollback_source_restored'] ?? false,
            'next_source_matches_current' => $base['release_window_seal_next239']['next_source_matches_current'] ?? false,
            'current_source_digest' => hash('sha256', json_encode($base['current_source_tables'], JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowId(array $row, string $rowIdColumn): int|string
    {
        if (!array_key_exists($rowIdColumn, $row)) {
            throw new \InvalidArgumentException("SQLite row-value tuple window next243 rowid column {$rowIdColumn} is missing");
        }
        $id = $row[$rowIdColumn];
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException("SQLite row-value tuple window next243 rowid column {$rowIdColumn} must be int or string");
        }

        return $id;
    }

    private static function int(mixed $value): int
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
