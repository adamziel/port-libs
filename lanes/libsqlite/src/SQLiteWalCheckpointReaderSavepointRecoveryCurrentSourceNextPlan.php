<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointReaderSavepointRecoveryCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        string $databasePath,
        array $pageNumbers,
        string $mode = 'restart',
        string $crashPhase = 'after_database_sync',
        ?int $readerEndFrame = null,
        ?string $persistedWalBytes = null
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint recovery current-source next118 requires a database path');
        }

        $checkpoint = SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan::plan(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $readerEndFrame
        );

        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        if ($persistedWalBytes !== null && $persistedWalBytes !== $retainedWalBytes) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint recovery current-source next118 persisted WAL bytes do not match the retained savepoint prefix');
        }

        $recovery = SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes(
            $retainedWalBytes,
            $databaseBytes,
            $databasePath,
            $pageNumbers,
            $mode,
            $crashPhase,
            $wal->header->pageSize
        );

        $rows = [];
        foreach ($checkpoint['rows'] as $index => $row) {
            $current = $recovery['current_reader'][$index] ?? null;
            $next = $recovery['next_reader'][$index] ?? null;
            if (!is_array($current) || !is_array($next)) {
                throw new \RuntimeException('SQLite WAL checkpoint reader savepoint recovery current-source next118 visibility row mismatch');
            }

            $rows[] = [
                'page_number' => $row['page_number'],
                'before_source' => $row['before_source'],
                'retained_source' => $row['current_source'],
                'recovery_current_source' => $current['source'],
                'recovery_next_source' => $next['source'],
                'before_frame' => $row['before_frame'],
                'retained_frame' => $row['current_frame'],
                'recovery_current_frame' => $current['frame_index'],
                'recovery_next_frame' => $next['frame_index'],
                'discarded_by_savepoint' => (bool) $row['reader_rewound_to_retained_prefix'],
                'recovery_preserves_retained_image' => $row['current_label'] === self::label((string) $current['image']),
                'next_preserves_retained_image' => $row['current_label'] === self::label((string) $next['image']),
                'transition' => $row['before_source'] . '>' . $row['current_source'] . '>' . $current['source'] . '>' . $next['source'],
                'retained_label' => $row['current_label'],
                'recovery_current_label' => self::label((string) $current['image']),
                'recovery_next_label' => self::label((string) $next['image']),
            ];
        }

        $recoveryCurrentSources = array_column($rows, 'recovery_current_source');
        $recoveryNextSources = array_column($rows, 'recovery_next_source');
        $discardedPages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => (bool) $row['discarded_by_savepoint'])
        ));

        return [
            'status' => 'reader-savepoint-checkpoint-recovery-current-source-next118',
            'savepoint' => $checkpoint['savepoint'],
            'mode' => $mode,
            'crash_phase' => $crashPhase,
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'original_reader_end_frame' => $checkpoint['original_reader_end_frame'],
            'retained_reader_end_frame' => $checkpoint['retained_reader_end_frame'],
            'retained_frame_count' => $checkpoint['retained_frame_count'],
            'discarded_frame_count' => $checkpoint['discarded_frame_count'],
            'discarded_frame_indexes' => $checkpoint['discarded_frame_indexes'],
            'discarded_page_numbers' => $checkpoint['discarded_page_numbers'],
            'discarded_reader_pages' => $discardedPages,
            'checkpoint_busy_before_release' => $checkpoint['pinned_checkpoint_busy'],
            'checkpoint_reason_before_release' => $checkpoint['pinned_checkpoint_reason'],
            'recovery_status' => $recovery['status'],
            'recovery_reason' => $recovery['reason'],
            'persisted_wal_action' => $recovery['persisted_wal_action'],
            'persisted_wal_bytes_length' => $recovery['persisted_wal_bytes_length'],
            'retained_wal_bytes_length' => strlen($retainedWalBytes),
            'next_uses_checkpoint_database' => $recovery['next_uses_checkpoint_database'],
            'next_replays_persisted_wal' => $recovery['next_replays_persisted_wal'],
            'next_uses_reset_wal' => $recovery['next_uses_reset_wal'],
            'recovery_current_sources' => $recoveryCurrentSources,
            'recovery_next_sources' => $recoveryNextSources,
            'recovery_current_source_counts' => array_count_values($recoveryCurrentSources),
            'recovery_next_source_counts' => array_count_values($recoveryNextSources),
            'recovery_current_frame_indexes' => array_column($rows, 'recovery_current_frame'),
            'recovery_next_frame_indexes' => array_column($rows, 'recovery_next_frame'),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'transition'),
            'recovery_preserves_retained_images' => !in_array(false, array_column($rows, 'recovery_preserves_retained_image'), true),
            'next_preserves_retained_images' => !in_array(false, array_column($rows, 'next_preserves_retained_image'), true),
            'discarded_frames_replayed' => count(array_intersect($checkpoint['discarded_frame_indexes'], array_filter(array_map(
                static fn (mixed $frame): ?int => is_int($frame) ? $frame : null,
                array_column($rows, 'recovery_current_frame')
            )))) > 0,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'transition'))),
            'operations_applied' => $recovery['operations_applied'],
            'operations_pending' => $recovery['operations_pending'],
            'current_source_verified' => $checkpoint['current_source_verified'],
            'persisted_source_verified' => true,
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                $recovery['dependencies'],
                ['sqlite-wal-checkpoint-reader-savepoint-recovery-current-source-next118']
            ))),
        ];
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
