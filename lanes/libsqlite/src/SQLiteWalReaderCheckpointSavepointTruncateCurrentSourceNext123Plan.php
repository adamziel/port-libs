<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNext123Plan
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
        ?int $readerEndFrame = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint truncate current-source next123 requires a savepoint name');
        }
        if ($walBytes === '' || $readerWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint truncate current-source next123 requires WAL source bytes');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint truncate current-source next123 requires database bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint truncate current-source next123 requires page numbers');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint truncate current-source next123 source bytes do not match parsed WAL');
        }

        $pageSize = $wal->header->pageSize;
        $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
        $readerEndFrame ??= $readerWal->frameCount();
        if ($readerEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint truncate current-source next123 reader frame must be non-negative');
        }

        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint truncate current-source next123 pages must be integers');
            }
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $pageSize, true);
        $currentReaderEndFrame = min($readerEndFrame, $retainedWal->frameCount());

        $pinned = $retainedWal->durableCheckpointResult($databaseBytes, 'truncate', $currentReaderEndFrame);
        $released = $retainedWal->durableCheckpointResult($databaseBytes, 'truncate', null);
        $pinnedWal = $pinned['wal_bytes'] === '' ? null : SQLiteWal::parse((string) $pinned['wal_bytes'], $pageSize, true);

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $reader = $readerWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $readerEndFrame);
            $current = $currentReaderEndFrame === 0
                ? self::databasePage($databaseBytes, $pageSize, $pageNumber)
                : $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $pinnedNext = $pinnedWal === null
                ? self::databasePage((string) $pinned['database_bytes'], $pageSize, $pageNumber)
                : $pinnedWal->readerSnapshotPageImage((string) $pinned['database_bytes'], $pageNumber, $pinnedWal->frameCount());
            $releasedNext = self::databasePage((string) $released['database_bytes'], $pageSize, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'reader_source' => $reader['source'],
                'current_source' => $current['source'],
                'pinned_next_source' => $pinnedNext['source'],
                'released_next_source' => $releasedNext['source'],
                'reader_frame' => $reader['frame_index'],
                'current_frame' => $current['frame_index'],
                'pinned_next_frame' => $pinnedNext['frame_index'],
                'released_next_frame' => $releasedNext['frame_index'],
                'reader_label' => self::label((string) $reader['image']),
                'current_label' => self::label((string) $current['image']),
                'pinned_next_label' => self::label((string) $pinnedNext['image']),
                'released_next_label' => self::label((string) $releasedNext['image']),
                'rollback_changed_current' => $reader['image'] !== $current['image'],
                'pinned_preserved_current' => $current['image'] === $pinnedNext['image'],
                'released_preserved_current' => $current['image'] === $releasedNext['image'],
                'source_transition' => $reader['source'] . '>' . $current['source'] . '>' . $pinnedNext['source'] . '>' . $releasedNext['source'],
            ];
        }

        $staleFrames = self::staleReaderFrames($readerWal, $retainedWal->frameCount(), $readerEndFrame, $pageSize);
        $readerSources = array_column($rows, 'reader_source');
        $currentSources = array_column($rows, 'current_source');
        $pinnedSources = array_column($rows, 'pinned_next_source');
        $releasedSources = array_column($rows, 'released_next_source');

        return [
            'status' => $pinned['busy'] && !$released['busy'] && $released['wal_action'] === 'truncate_wal'
                ? 'reader-checkpoint-savepoint-truncate-current-source-next123'
                : 'reader-checkpoint-savepoint-truncate-current-source-next123-' . ($pinned['busy'] ? 'busy' : 'ready'),
            'savepoint' => $savepoint,
            'mode' => 'truncate',
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'retained_frame_count' => $retainedWal->frameCount(),
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'discarded_frame_indexes' => array_column($rollback['discarded_wal_frames'], 'frame_index'),
            'stale_reader_frames' => $staleFrames,
            'stale_reader_frame_indexes' => array_column($staleFrames, 'frame_index'),
            'stale_reader_page_numbers' => array_values(array_unique(array_map('intval', array_column($staleFrames, 'page_number')))),
            'reader_wal_sha256' => hash('sha256', $readerWalBytes),
            'current_wal_sha256' => hash('sha256', $walBytes),
            'retained_wal_sha256' => hash('sha256', $retainedWalBytes),
            'reader_source_matches_current' => hash_equals(hash('sha256', $readerWalBytes), hash('sha256', $retainedWalBytes)),
            'current_source_verified' => true,
            'current_source' => self::sourceSummary($wal, $walBytes),
            'reader_source' => self::sourceSummary($readerWal, $readerWalBytes),
            'retained_source' => self::sourceSummary($retainedWal, $retainedWalBytes),
            'pinned_checkpoint_busy' => $pinned['busy'],
            'pinned_checkpoint_reason' => $pinned['reason'],
            'pinned_wal_action' => $pinned['wal_action'],
            'pinned_wal_bytes_length' => strlen((string) $pinned['wal_bytes']),
            'released_checkpoint_busy' => $released['busy'],
            'released_checkpoint_reason' => $released['reason'],
            'released_wal_action' => $released['wal_action'],
            'released_wal_bytes_length' => strlen((string) $released['wal_bytes']),
            'released_database_bytes_length' => strlen((string) $released['database_bytes']),
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
                array_filter($rows, static fn (array $row): bool => (bool) $row['rollback_changed_current'])
            )),
            'pinned_checkpoint_preserved_images' => !in_array(false, array_column($rows, 'pinned_preserved_current'), true),
            'released_checkpoint_preserved_images' => !in_array(false, array_column($rows, 'released_preserved_current'), true),
            'released_reader_uses_checkpoint_database' => !in_array('wal', $releasedSources, true),
            'reader_release_unblocked_truncate' => (bool) $pinned['busy'] && !(bool) $released['busy'] && $released['wal_action'] === 'truncate_wal',
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $pinned['dependencies'],
                $released['dependencies'],
                ['sqlite-wal-reader-checkpoint-savepoint-truncate-current-source-next123']
            ))),
        ];
    }

    /**
     * @return list<array{frame_index:int,page_number:int,commit_frame:bool,source_offset:int,source_length:int,image_sha256:string}>
     */
    private static function staleReaderFrames(SQLiteWal $readerWal, int $retainedFrameCount, int $readerEndFrame, int $pageSize): array
    {
        $rows = [];
        for ($frameIndex = $retainedFrameCount + 1; $frameIndex <= min($readerEndFrame, $readerWal->frameCount()); $frameIndex++) {
            $frame = $readerWal->frames[$frameIndex - 1] ?? null;
            if ($frame === null) {
                continue;
            }

            $rows[] = [
                'frame_index' => $frameIndex,
                'page_number' => $frame->pageNumber,
                'commit_frame' => $frame->databasePageCountAfterCommit > 0,
                'source_offset' => 32 + (($frameIndex - 1) * (24 + $pageSize)),
                'source_length' => 24 + $pageSize,
                'image_sha256' => hash('sha256', $frame->pageImage),
            ];
        }

        return $rows;
    }

    /**
     * @return array{checkpoint_sequence:int,salt1:int,salt2:int,page_size:int,frame_count:int,wal_bytes_length:int,wal_sha256:string}
     */
    private static function sourceSummary(SQLiteWal $wal, string $walBytes): array
    {
        return [
            'checkpoint_sequence' => $wal->header->checkpointSequence,
            'salt1' => $wal->header->salt1,
            'salt2' => $wal->header->salt2,
            'page_size' => $wal->header->pageSize,
            'frame_count' => $wal->frameCount(),
            'wal_bytes_length' => strlen($walBytes),
            'wal_sha256' => hash('sha256', $walBytes),
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint truncate current-source next123 database image must be page aligned');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL reader checkpoint savepoint truncate current-source next123 page {$pageNumber} is outside the database image");
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
