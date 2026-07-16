<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointReaderRestartSnapshotCurrentSourceNextPlan
{
    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $transactions,
        array $pageNumbers,
        int $readerEndFrame,
        bool $syncWal = true,
        bool $syncDirectory = true
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart snapshot next124 requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart snapshot next124 requires page numbers');
        }
        if ($readerEndFrame < 0 || $readerEndFrame > $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart snapshot next124 reader frame is outside the WAL frame range');
        }

        $source = SQLiteWal::parse($walBytes, $wal->header->pageSize, $wal->checksumsValidated);
        if ($source->header != $wal->header || $source->frameCount() !== $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart snapshot next124 source header mismatch');
        }
        foreach ($source->frames as $index => $frame) {
            if (!isset($wal->frames[$index]) || $wal->frames[$index] != $frame) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart snapshot next124 source frame ' . ($index + 1) . ' mismatch');
            }
        }

        $pinnedCheckpoint = $wal->durableCheckpointResult($databaseBytes, 'restart', $readerEndFrame);
        $releasedCheckpoint = $wal->durableCheckpointResult($databaseBytes, 'restart', null);
        $restartWal = SQLiteWal::parse((string) $releasedCheckpoint['wal_bytes'], $wal->header->pageSize, $wal->checksumsValidated);
        $append = SQLiteWalAppendPlan::appendTransactions($restartWal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse((string) $append['wal_bytes'], $wal->header->pageSize, $wal->checksumsValidated);
        $nextReaderEndFrame = $append['last_commit_frame'] ?? $nextWal->frameCount();

        $current = [];
        $pinned = [];
        $released = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart snapshot next124 pages must be integers');
            }

            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $readerEndFrame);
            $pinned[] = self::safeReaderVisibility($wal, (string) $pinnedCheckpoint['database_bytes'], $pageNumber, $readerEndFrame);
            $released[] = self::databasePageVisibility((string) $releasedCheckpoint['database_bytes'], $wal->header->pageSize, $pageNumber);
            $next[] = self::safeReaderVisibility($nextWal, (string) $releasedCheckpoint['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        $currentImages = self::visibilityImages($current);
        $pinnedImages = self::visibilityImages($pinned);
        $releasedImages = self::visibilityImages($released);
        $nextImages = self::visibilityImages($next);

        return [
            'status' => 'wal-checkpoint-reader-restart-snapshot-current-source-next124',
            'reason' => 'reader_pins_current_source_while_released_restart_generation_accepts_next_writer',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'page_size' => $wal->header->pageSize,
            'source_status' => 'current-source',
            'source_frame_count' => $wal->frameCount(),
            'parsed_frame_count' => $source->frameCount(),
            'reader_end_frame' => $readerEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'pinned_checkpoint' => $pinnedCheckpoint,
            'released_checkpoint' => $releasedCheckpoint,
            'append' => $append,
            'restart_wal_header' => $releasedCheckpoint['wal_header'],
            'current_reader' => $current,
            'pinned_reader' => $pinned,
            'released_database_reader' => $released,
            'next_reader' => $next,
            'current_sources' => self::visibilityColumn($current, 'source'),
            'pinned_sources' => self::visibilityColumn($pinned, 'source'),
            'released_sources' => self::visibilityColumn($released, 'source'),
            'next_sources' => self::visibilityColumn($next, 'source'),
            'current_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'pinned_frame_indexes' => self::visibilityColumn($pinned, 'frame_index'),
            'released_frame_indexes' => self::visibilityColumn($released, 'frame_index'),
            'next_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_errors' => self::visibilityErrors($current),
            'pinned_errors' => self::visibilityErrors($pinned),
            'released_errors' => self::visibilityErrors($released),
            'next_errors' => self::visibilityErrors($next),
            'reader_pin_blocks_restart_reset' => (bool) $pinnedCheckpoint['busy'],
            'reader_release_restarts_generation' => !(bool) $releasedCheckpoint['busy'] && $releasedCheckpoint['wal_action'] === 'restart_wal',
            'current_reader_stable_after_pinned_checkpoint' => $currentImages === $pinnedImages,
            'released_database_has_checkpointed_frames' => $releasedCheckpoint['checkpointed_frame_count'] === $releasedCheckpoint['total_committable_frame_count'],
            'next_uses_restarted_wal_generation' => $restartWal->header->checkpointSequence === (($wal->header->checkpointSequence + 1) & 0xffffffff),
            'next_uses_appended_wal' => $nextWal->frameCount() > 0,
            'current_next_images_match' => $currentImages === $nextImages,
            'released_next_images_match' => $releasedImages === $nextImages,
            'source_transitions' => self::sourceTransitions($current, $pinned, $released, $next),
            'source_digest' => hash('sha256', implode('|', self::sourceTransitions($current, $pinned, $released, $next))),
            'operations' => $append['operations'],
            'dependencies' => array_values(array_unique(array_merge(
                $pinnedCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                $append['dependencies'],
                [
                    'sqlite-wal-checkpoint-reader-restart-snapshot-current-source-next124',
                    'sqlite-wal-reader-checkpoint-snapshot-current-source-next108',
                ]
            ))),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function safeReaderVisibility(SQLiteWal $wal, string $databaseBytes, int $pageNumber, ?int $snapshotEndFrame): array
    {
        try {
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $snapshotEndFrame);
        } catch (\Throwable $throwable) {
            return [
                'page_number' => $pageNumber,
                'source' => 'error',
                'frame_index' => null,
                'database_offset' => null,
                'image' => '',
                'snapshot_end_frame' => $snapshotEndFrame,
                'snapshot_commit_frame' => null,
                'database_page_count' => null,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function databasePageVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart snapshot next124 pages must be one-based');
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader restart snapshot next124 database bytes must be page-size aligned');
        }

        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL checkpoint reader restart snapshot next124 page {$pageNumber} is outside the checkpoint database");
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $pageCount,
        ];
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return list<mixed>
     */
    private static function visibilityColumn(array $entries, string $column): array
    {
        return array_map(static fn (array $entry): mixed => $entry[$column] ?? null, $entries);
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return list<string>
     */
    private static function visibilityErrors(array $entries): array
    {
        return array_values(array_filter(array_map(static fn (array $entry): string => (string) ($entry['error'] ?? ''), $entries)));
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return list<string>
     */
    private static function visibilityImages(array $entries): array
    {
        return array_map(static fn (array $entry): string => (string) ($entry['image'] ?? ''), $entries);
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $pinned
     * @param list<array<string,mixed>> $released
     * @param list<array<string,mixed>> $next
     * @return list<string>
     */
    private static function sourceTransitions(array $current, array $pinned, array $released, array $next): array
    {
        $rows = [];
        foreach ($current as $index => $entry) {
            $rows[] = ($entry['source'] ?? 'error')
                . '>' . ($pinned[$index]['source'] ?? 'error')
                . '>' . ($released[$index]['source'] ?? 'error')
                . '>' . ($next[$index]['source'] ?? 'error');
        }

        return $rows;
    }
}
