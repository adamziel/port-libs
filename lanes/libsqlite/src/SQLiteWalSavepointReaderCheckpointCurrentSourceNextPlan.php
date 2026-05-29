<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalSavepointReaderCheckpointCurrentSourceNextPlan
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
        string $readerWalBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next117 requires a savepoint name');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next117 requires WAL bytes');
        }
        if ($readerWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next117 requires reader WAL bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next117 requires page numbers');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next117 requires restart or truncate mode');
        }

        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next117 source bytes do not match parsed WAL');
        }

        $pageSize = $wal->header->pageSize;
        $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
        $truncation = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $pageSize, true);

        $readerEndFrame ??= $readerWal->frameCount();
        if ($readerEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next117 reader frame must be non-negative');
        }

        $currentReaderEndFrame = min($readerEndFrame, $retainedWal->frameCount());
        $pinnedCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, $mode, $currentReaderEndFrame);
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
                throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next117 pages must be integers');
            }

            $reader = $readerWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $readerEndFrame);
            $current = $currentReaderEndFrame === 0
                ? self::databaseVisibility($databaseBytes, $pageSize, $pageNumber)
                : $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $pinned = $pinnedWal === null
                ? self::databaseVisibility((string) $pinnedCheckpoint['database_bytes'], $pageSize, $pageNumber)
                : $pinnedWal->readerSnapshotPageImage((string) $pinnedCheckpoint['database_bytes'], $pageNumber, $pinnedWal->frameCount());
            $released = $releasedWal === null
                ? self::databaseVisibility((string) $releasedCheckpoint['database_bytes'], $pageSize, $pageNumber)
                : $releasedWal->readerSnapshotPageImage((string) $releasedCheckpoint['database_bytes'], $pageNumber, $releasedWal->frameCount());

            $rows[] = [
                'page_number' => $pageNumber,
                'reader_source' => $reader['source'],
                'current_source' => $current['source'],
                'pinned_next_source' => $pinned['source'],
                'released_next_source' => $released['source'],
                'reader_frame' => $reader['frame_index'],
                'current_frame' => $current['frame_index'],
                'pinned_next_frame' => $pinned['frame_index'],
                'released_next_frame' => $released['frame_index'],
                'stale_reader_tail_ignored' => $reader['image'] !== $current['image'],
                'pinned_checkpoint_preserved_current' => $current['image'] === $pinned['image'],
                'released_checkpoint_preserved_current' => $current['image'] === $released['image'],
                'source_transition' => $reader['source'] . '>' . $current['source'] . '>' . $pinned['source'] . '>' . $released['source'],
                'reader_label' => self::label((string) $reader['image']),
                'current_label' => self::label((string) $current['image']),
                'pinned_next_label' => self::label((string) $pinned['image']),
                'released_next_label' => self::label((string) $released['image']),
            ];
        }

        $staleFrames = [];
        for ($frameIndex = $retainedWal->frameCount() + 1; $frameIndex <= min($readerEndFrame, $readerWal->frameCount()); $frameIndex++) {
            $frame = $readerWal->frames[$frameIndex - 1] ?? null;
            if ($frame === null) {
                continue;
            }
            $staleFrames[] = [
                'frame_index' => $frameIndex,
                'page_number' => $frame->pageNumber,
                'commit_frame' => $frame->databasePageCountAfterCommit > 0,
                'source_offset' => 32 + (($frameIndex - 1) * (24 + $pageSize)),
            ];
        }

        $readerSources = array_column($rows, 'reader_source');
        $currentSources = array_column($rows, 'current_source');
        $pinnedSources = array_column($rows, 'pinned_next_source');
        $releasedSources = array_column($rows, 'released_next_source');

        return [
            'status' => $pinnedCheckpoint['busy'] && !$releasedCheckpoint['busy']
                ? 'reader-stale-source-checkpoint-current-prefix-next117'
                : 'reader-stale-source-checkpoint-' . ($pinnedCheckpoint['busy'] ? 'busy' : 'ready') . '-next117',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'retained_frame_count' => $retainedWal->frameCount(),
            'discarded_frame_count' => $truncation['discarded_frame_count'],
            'discarded_frame_indexes' => array_column($truncation['discarded_wal_frames'], 'frame_index'),
            'stale_reader_frame_indexes' => array_column($staleFrames, 'frame_index'),
            'stale_reader_page_numbers' => array_values(array_unique(array_map('intval', array_column($staleFrames, 'page_number')))),
            'reader_source_matches_current' => hash_equals(hash('sha256', $readerWalBytes), hash('sha256', $retainedWalBytes)),
            'current_source_verified' => true,
            'reader_wal_sha256' => hash('sha256', $readerWalBytes),
            'retained_wal_sha256' => hash('sha256', $retainedWalBytes),
            'reader_wal_bytes_length' => strlen($readerWalBytes),
            'retained_wal_bytes_length' => strlen($retainedWalBytes),
            'pinned_checkpoint_busy' => $pinnedCheckpoint['busy'],
            'pinned_checkpoint_reason' => $pinnedCheckpoint['reason'],
            'pinned_wal_action' => $pinnedCheckpoint['wal_action'],
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'pinned_wal_bytes_length' => strlen((string) $pinnedCheckpoint['wal_bytes']),
            'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'reader_sources' => $readerSources,
            'current_sources' => $currentSources,
            'pinned_next_sources' => $pinnedSources,
            'released_next_sources' => $releasedSources,
            'reader_source_counts' => array_count_values($readerSources),
            'current_source_counts' => array_count_values($currentSources),
            'pinned_next_source_counts' => array_count_values($pinnedSources),
            'released_next_source_counts' => array_count_values($releasedSources),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'stale_reader_tail_pages' => array_values(array_map(
                static fn (array $row): int => (int) $row['page_number'],
                array_filter($rows, static fn (array $row): bool => (bool) $row['stale_reader_tail_ignored'])
            )),
            'checkpoint_used_retained_prefix' => true,
            'pinned_checkpoint_preserved_images' => !in_array(false, array_column($rows, 'pinned_checkpoint_preserved_current'), true),
            'released_checkpoint_preserved_images' => !in_array(false, array_column($rows, 'released_checkpoint_preserved_current'), true),
            'released_reader_uses_checkpoint_database' => !in_array('wal', $releasedSources, true),
            'reader_release_unblocked_checkpoint' => (bool) $pinnedCheckpoint['busy'] && !(bool) $releasedCheckpoint['busy'],
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $pinnedCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                ['sqlite-wal-savepoint-reader-checkpoint-current-source-next117']
            ))),
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databaseVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next117 database image must be page aligned');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL savepoint reader checkpoint current-source next117 page {$pageNumber} is outside the database image");
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
