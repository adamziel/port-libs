<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalReaderRestartCheckpointPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string, mixed>
     */
    public static function plan(
        SQLiteWal $wal,
        string $databaseBytes,
        SQLiteShmIndex $shm,
        array $pageNumbers,
        string $databasePath,
        string $mode = 'restart',
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL reader restart checkpoint requires a database path');
        }

        $visibility = $wal->restartCurrentNextReaderVisibility($databaseBytes, $shm, $pageNumbers, $mode);
        $transition = $visibility['transition'];
        $checkpoint = $transition['checkpoint'];
        $currentShm = $transition['current_shm'];

        $currentPages = self::pages($visibility['current_reader']);
        $nextPages = self::pages($visibility['next_reader']);
        $changedPages = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            if (($visibility['current_reader_images'][$index] ?? null) !== ($visibility['next_reader_images'][$index] ?? null)) {
                $changedPages[] = $pageNumber;
            }
        }

        $operations = [];
        if (($checkpoint['checkpointed_frame_count'] ?? 0) > 0) {
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'bytes' => strlen((string) $checkpoint['database_bytes']),
                'reason' => 'checkpoint_committed_frames_for_next_reader',
            ];
        }
        if ($checkpoint['wal_action'] === 'restart_wal') {
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath . '-wal',
                'bytes' => $checkpoint['wal_bytes_length'],
                'reason' => 'restart_wal_header_for_next_reader',
            ];
        } elseif ($checkpoint['wal_action'] === 'truncate_wal') {
            $operations[] = [
                'op' => 'truncate',
                'path' => $databasePath . '-wal',
                'bytes' => 0,
                'reason' => 'truncate_wal_for_next_reader',
            ];
        } else {
            $operations[] = [
                'op' => 'preserve',
                'path' => $databasePath . '-wal',
                'bytes' => $checkpoint['wal_bytes_length'],
                'reason' => 'current_reader_pins_restart_checkpoint',
            ];
        }

        return [
            'status' => $visibility['status'],
            'mode' => $visibility['mode'],
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'checkpoint_busy' => $visibility['checkpoint_busy'],
            'checkpoint_reason' => $visibility['checkpoint_reason'],
            'wal_action' => $visibility['wal_action'],
            'backfilled_frame_count' => $currentShm['backfilled_frame_count'],
            'backfill_attempted_frame_count' => $currentShm['backfill_attempted_frame_count'],
            'checkpoint_pinned_frame' => $currentShm['checkpoint_pinned_frame'],
            'current_reader_end_frame' => $visibility['current_reader_end_frame'],
            'next_reader_end_frame' => $visibility['next_reader_end_frame'],
            'next_reader_slot' => $transition['next_reader_slot'],
            'next_reader_frame' => $transition['next_reader_frame'],
            'next_read_marks' => $transition['next_read_marks'],
            'current_sources' => $visibility['current_reader_sources'],
            'next_sources' => $visibility['next_reader_sources'],
            'current_frame_indexes' => $visibility['current_reader_frame_indexes'],
            'next_frame_indexes' => $visibility['next_reader_frame_indexes'],
            'current_pages' => $currentPages,
            'next_pages' => $nextPages,
            'changed_pages' => $changedPages,
            'current_reader_kept_snapshot' => $visibility['current_reader_kept_snapshot'],
            'next_reader_uses_database' => $visibility['next_reader_uses_database'],
            'next_reader_uses_restarted_wal' => $visibility['next_reader_uses_restarted_wal'],
            'images_match' => $visibility['images_match'],
            'checkpointed_frame_count' => $checkpoint['checkpointed_frame_count'],
            'remaining_committed_frame_count' => $checkpoint['remaining_committed_frame_count'],
            'total_committable_frame_count' => $checkpoint['total_committable_frame_count'],
            'wal_bytes_length' => $checkpoint['wal_bytes_length'],
            'database_page_count' => $checkpoint['database_page_count'],
            'operations' => $operations,
            'dependencies' => array_values(array_unique(array_merge(
                $visibility['dependencies'],
                $currentShm['dependencies'],
                ['sqlite-wal-reader-restart-checkpoint-current-next43']
            ))),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{page_number:int,source:string,frame_index:int|null,label:string}>
     */
    private static function pages(array $rows): array
    {
        $pages = [];
        foreach ($rows as $row) {
            $image = (string) ($row['image'] ?? '');
            $pages[] = [
                'page_number' => (int) $row['page_number'],
                'source' => (string) $row['source'],
                'frame_index' => $row['frame_index'] ?? null,
                'label' => rtrim(strtok($image, "\0.") ?: $image),
            ];
        }

        return $pages;
    }
}
