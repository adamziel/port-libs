<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalReaderSavepointRestartCurrentSourceNextPlan
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
        string $staleReaderWalBytes,
        string $restartedWalBytes,
        string $databaseBytes,
        array $pageNumbers,
        ?int $staleReaderEndFrame = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL reader savepoint restart current-source next121 requires a savepoint name');
        }
        if ($walBytes === '' || $staleReaderWalBytes === '' || $restartedWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader savepoint restart current-source next121 requires source, reader, and restarted WAL bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader savepoint restart current-source next121 requires page numbers');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL reader savepoint restart current-source next121 source bytes do not match parsed WAL');
        }

        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL reader savepoint restart current-source next121 requires a concrete page size');
        }

        $truncation = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $pageSize, true);
        $staleReaderWal = SQLiteWal::parse($staleReaderWalBytes, $pageSize, true);
        $restartedWal = SQLiteWal::parse($restartedWalBytes, $pageSize, true);

        if (!str_starts_with($restartedWalBytes, $retainedWalBytes)) {
            throw new \InvalidArgumentException('SQLite WAL reader savepoint restart current-source next121 restarted WAL must begin with the retained savepoint prefix');
        }

        $retainedFrameCount = $retainedWal->frameCount();
        $staleReaderEndFrame ??= $staleReaderWal->frameCount();
        if ($staleReaderEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL reader savepoint restart current-source next121 reader frame must be non-negative');
        }
        if ($restartedWal->frameCount() <= $retainedFrameCount) {
            throw new \InvalidArgumentException('SQLite WAL reader savepoint restart current-source next121 restarted WAL needs at least one new writer frame');
        }

        $nextFrames = [];
        for ($frameIndex = $retainedFrameCount + 1; $frameIndex <= $restartedWal->frameCount(); $frameIndex++) {
            $frame = $restartedWal->frames[$frameIndex - 1];
            $nextFrames[] = [
                'frame_index' => $frameIndex,
                'page_number' => $frame->pageNumber,
                'commit_frame' => $frame->databasePageCountAfterCommit > 0,
                'source_offset' => 32 + (($frameIndex - 1) * (24 + $pageSize)),
            ];
        }

        $staleFrames = [];
        for ($frameIndex = $retainedFrameCount + 1; $frameIndex <= min($staleReaderEndFrame, $staleReaderWal->frameCount()); $frameIndex++) {
            $frame = $staleReaderWal->frames[$frameIndex - 1] ?? null;
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

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL reader savepoint restart current-source next121 pages must be integers');
            }

            $stale = $staleReaderWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $staleReaderEndFrame);
            $current = $retainedFrameCount === 0
                ? self::databaseVisibility($databaseBytes, $pageSize, $pageNumber)
                : $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $retainedFrameCount);
            $next = $restartedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $restartedWal->frameCount());
            $staleTailIgnored = $stale['image'] !== $current['image'];
            $nextReplacedStaleTail = $staleTailIgnored && $stale['image'] !== $next['image'] && ($next['frame_index'] ?? 0) > $retainedFrameCount;
            $rows[] = [
                'page_number' => $pageNumber,
                'stale_reader_source' => $stale['source'],
                'current_source' => $current['source'],
                'next_source' => $next['source'],
                'stale_reader_frame' => $stale['frame_index'],
                'current_frame' => $current['frame_index'],
                'next_frame' => $next['frame_index'],
                'stale_reader_tail_ignored' => $staleTailIgnored,
                'next_preserved_current_prefix' => $current['image'] === $next['image'] || ($next['frame_index'] ?? 0) > $retainedFrameCount,
                'next_replaced_stale_tail' => $nextReplacedStaleTail,
                'source_transition' => $stale['source'] . '>' . $current['source'] . '>' . $next['source'],
                'stale_reader_label' => self::label((string) $stale['image']),
                'current_label' => self::label((string) $current['image']),
                'next_label' => self::label((string) $next['image']),
            ];
        }

        $stalePages = array_values(array_unique(array_map('intval', array_column($staleFrames, 'page_number'))));
        $nextPages = array_values(array_unique(array_map('intval', array_column($nextFrames, 'page_number'))));
        sort($stalePages, SORT_NUMERIC);
        sort($nextPages, SORT_NUMERIC);

        return [
            'status' => 'reader-savepoint-restart-current-source-next121',
            'savepoint' => $savepoint,
            'page_size' => $pageSize,
            'retained_frame_count' => $retainedFrameCount,
            'discarded_frame_count' => $truncation['discarded_frame_count'],
            'discarded_frame_indexes' => array_column($truncation['discarded_wal_frames'], 'frame_index'),
            'stale_reader_end_frame' => $staleReaderEndFrame,
            'stale_reader_frame_count' => $staleReaderWal->frameCount(),
            'next_writer_frame_count' => $restartedWal->frameCount(),
            'next_writer_first_frame' => $retainedFrameCount + 1,
            'next_writer_frame_indexes' => array_column($nextFrames, 'frame_index'),
            'next_writer_page_numbers' => $nextPages,
            'stale_reader_tail_frame_indexes' => array_column($staleFrames, 'frame_index'),
            'stale_reader_tail_page_numbers' => $stalePages,
            'retained_wal_bytes_length' => strlen($retainedWalBytes),
            'stale_reader_wal_bytes_length' => strlen($staleReaderWalBytes),
            'restarted_wal_bytes_length' => strlen($restartedWalBytes),
            'restarted_extends_retained_prefix' => true,
            'reader_source_matches_current' => hash_equals(hash('sha256', $staleReaderWalBytes), hash('sha256', $retainedWalBytes)),
            'retained_wal_sha256' => hash('sha256', $retainedWalBytes),
            'stale_reader_wal_sha256' => hash('sha256', $staleReaderWalBytes),
            'restarted_wal_sha256' => hash('sha256', $restartedWalBytes),
            'stale_reader_sources' => array_column($rows, 'stale_reader_source'),
            'current_sources' => array_column($rows, 'current_source'),
            'next_sources' => array_column($rows, 'next_source'),
            'stale_reader_frame_indexes' => array_column($rows, 'stale_reader_frame'),
            'current_frame_indexes' => array_column($rows, 'current_frame'),
            'next_frame_indexes' => array_column($rows, 'next_frame'),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'stale_reader_tail_pages_ignored' => array_values(array_map(
                static fn (array $row): int => (int) $row['page_number'],
                array_filter($rows, static fn (array $row): bool => (bool) $row['stale_reader_tail_ignored'])
            )),
            'next_replaced_stale_tail_pages' => array_values(array_map(
                static fn (array $row): int => (int) $row['page_number'],
                array_filter($rows, static fn (array $row): bool => (bool) $row['next_replaced_stale_tail'])
            )),
            'current_source_verified' => true,
            'next_writer_restarted_after_retained_prefix' => array_column($nextFrames, 'frame_index') === range($retainedFrameCount + 1, $restartedWal->frameCount()),
            'next_writer_uses_current_source' => !in_array(false, array_column($rows, 'next_preserved_current_prefix'), true),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => [
                'sqlite-savepoint-wal-byte-truncation',
                'sqlite-wal-reader-savepoint-restart-current-source-next121',
            ],
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databaseVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader savepoint restart current-source next121 database image must be page aligned');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL reader savepoint restart current-source next121 page {$pageNumber} is outside the database image");
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
