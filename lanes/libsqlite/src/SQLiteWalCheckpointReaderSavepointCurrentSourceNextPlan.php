<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointReaderSavepointCurrentSourceNextPlan
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
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next104 requires a savepoint name');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next104 requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next104 requires restart or truncate mode');
        }

        $pageSize = $wal->header->pageSize;
        self::assertCurrentWalSource($wal, $walBytes, $pageSize);

        $readerEndFrame ??= $wal->frameCount();
        if ($readerEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next104 reader frame must be non-negative');
        }

        $truncation = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $pageSize, true);
        $retainedReaderEndFrame = min($readerEndFrame, $retainedWal->frameCount());

        $pinnedCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, $mode, $retainedReaderEndFrame);
        $releasedCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, $mode);
        $pinnedWal = $pinnedCheckpoint['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse((string) $pinnedCheckpoint['wal_bytes'], $pageSize, true);
        $releasedWal = $releasedCheckpoint['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse((string) $releasedCheckpoint['wal_bytes'], $pageSize, true);

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next104 pages must be integers');
            }

            $before = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $readerEndFrame);
            $current = $retainedReaderEndFrame === 0
                ? self::databasePageVisibility($databaseBytes, $pageSize, $pageNumber)
                : $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $retainedReaderEndFrame);
            $pinned = $pinnedWal === null
                ? self::databasePageVisibility((string) $pinnedCheckpoint['database_bytes'], $pageSize, $pageNumber)
                : $pinnedWal->readerSnapshotPageImage((string) $pinnedCheckpoint['database_bytes'], $pageNumber, $pinnedWal->frameCount());
            $released = $releasedWal === null
                ? self::databasePageVisibility((string) $releasedCheckpoint['database_bytes'], $pageSize, $pageNumber)
                : $releasedWal->readerSnapshotPageImage((string) $releasedCheckpoint['database_bytes'], $pageNumber, $releasedWal->frameCount());

            $rows[] = [
                'page_number' => $pageNumber,
                'before_source' => $before['source'],
                'current_source' => $current['source'],
                'pinned_next_source' => $pinned['source'],
                'released_next_source' => $released['source'],
                'before_frame' => $before['frame_index'],
                'current_frame' => $current['frame_index'],
                'pinned_next_frame' => $pinned['frame_index'],
                'released_next_frame' => $released['frame_index'],
                'reader_rewound_to_retained_prefix' => $before['image'] !== $current['image'],
                'pinned_checkpoint_preserved_current' => $current['image'] === $pinned['image'],
                'release_checkpoint_preserved_current' => $current['image'] === $released['image'],
                'released_uses_checkpoint_database' => $released['source'] === 'database',
                'source_transition' => $before['source'] . '>' . $current['source'] . '>' . $pinned['source'] . '>' . $released['source'],
                'before_label' => self::label((string) $before['image']),
                'current_label' => self::label((string) $current['image']),
                'pinned_next_label' => self::label((string) $pinned['image']),
                'released_next_label' => self::label((string) $released['image']),
            ];
        }

        $discardedFrames = $truncation['discarded_wal_frames'];
        $currentSources = array_column($rows, 'current_source');
        $pinnedSources = array_column($rows, 'pinned_next_source');
        $releasedSources = array_column($rows, 'released_next_source');

        return [
            'status' => $pinnedCheckpoint['busy'] && !$releasedCheckpoint['busy']
                ? 'reader-savepoint-current-source-release-unblocks-checkpoint-next104'
                : 'reader-savepoint-current-source-' . ($pinnedCheckpoint['busy'] ? 'busy' : 'ready') . '-next104',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'page_size' => $pageSize,
            'original_reader_end_frame' => $readerEndFrame,
            'retained_reader_end_frame' => $retainedReaderEndFrame,
            'retained_frame_count' => $truncation['retained_frame_count'],
            'discarded_frame_count' => $truncation['discarded_frame_count'],
            'discarded_frame_indexes' => array_column($discardedFrames, 'frame_index'),
            'discarded_page_numbers' => array_values(array_unique(array_map('intval', array_column($discardedFrames, 'page_number')))),
            'pinned_checkpoint_busy' => $pinnedCheckpoint['busy'],
            'pinned_checkpoint_reason' => $pinnedCheckpoint['reason'],
            'pinned_wal_action' => $pinnedCheckpoint['wal_action'],
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'retained_wal_bytes_length' => strlen($retainedWalBytes),
            'pinned_wal_bytes_length' => strlen((string) $pinnedCheckpoint['wal_bytes']),
            'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'current_sources' => $currentSources,
            'pinned_next_sources' => $pinnedSources,
            'released_next_sources' => $releasedSources,
            'current_source_counts' => array_count_values($currentSources),
            'pinned_next_source_counts' => array_count_values($pinnedSources),
            'released_next_source_counts' => array_count_values($releasedSources),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'reader_rewound_pages' => array_values(array_map(
                static fn (array $row): int => (int) $row['page_number'],
                array_filter($rows, static fn (array $row): bool => (bool) $row['reader_rewound_to_retained_prefix'])
            )),
            'pinned_checkpoint_preserved_images' => !in_array(false, array_column($rows, 'pinned_checkpoint_preserved_current'), true),
            'released_checkpoint_preserved_images' => !in_array(false, array_column($rows, 'release_checkpoint_preserved_current'), true),
            'released_reader_uses_checkpoint_database' => !in_array('wal', $releasedSources, true),
            'reader_release_unblocked_checkpoint' => (bool) $pinnedCheckpoint['busy'] && !(bool) $releasedCheckpoint['busy'],
            'current_source_verified' => true,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $pinnedCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                ['sqlite-wal-checkpoint-reader-savepoint-current-source-next104']
            ))),
        ];
    }

    private static function assertCurrentWalSource(SQLiteWal $wal, string $walBytes, int $pageSize): void
    {
        $parsed = SQLiteWal::parse($walBytes, $pageSize, true);
        if ($parsed->header != $wal->header || $parsed->frameCount() !== $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next104 source header mismatch');
        }

        foreach ($parsed->frames as $index => $frame) {
            $current = $wal->frames[$index] ?? null;
            if ($current === null || $current != $frame) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next104 source frame ' . ($index + 1) . ' mismatch');
            }
        }
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePageVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next104 database image must be page aligned');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL checkpoint reader savepoint current-source next104 page {$pageNumber} is outside the database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $databasePageCount,
        ];
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
