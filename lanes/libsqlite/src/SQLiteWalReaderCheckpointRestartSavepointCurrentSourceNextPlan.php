<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalReaderCheckpointRestartSavepointCurrentSourceNextPlan
{
    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $nextTransactions
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
        string $databasePath,
        array $nextTransactions,
        array $pageNumbers,
        int $readerEndFrame,
        bool $syncWal = true,
        bool $syncDirectory = true
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next127 requires a savepoint name');
        }
        if ($walBytes === '' || $readerWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next127 requires WAL bytes');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next127 requires database bytes');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next127 requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next127 requires page numbers');
        }
        if ($readerEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next127 reader frame must be non-negative');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next127 source bytes do not match parsed WAL');
        }

        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next127 pages must be integers');
            }
        }

        $pageSize = $wal->header->pageSize;
        $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
        if ($readerEndFrame > $readerWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next127 reader frame exceeds reader WAL');
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $pageSize, true);
        $retainedReaderEndFrame = min($readerEndFrame, $retainedWal->frameCount());

        $pinned = SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $databasePath,
            $nextTransactions,
            $pageNumbers,
            'restart',
            $retainedReaderEndFrame,
            $syncWal,
            $syncDirectory
        );
        $released = SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $databasePath,
            $nextTransactions,
            $pageNumbers,
            'restart',
            null,
            $syncWal,
            $syncDirectory
        );

        $staleReader = [];
        $retainedReader = [];
        foreach ($pageNumbers as $pageNumber) {
            $stale = self::safeReaderVisibility($readerWal, $databaseBytes, $pageNumber, $readerEndFrame);
            $retained = $retainedReaderEndFrame === 0
                ? self::databasePage($databaseBytes, $pageSize, $pageNumber)
                : self::safeReaderVisibility($retainedWal, $databaseBytes, $pageNumber, $retainedReaderEndFrame);
            $staleReader[] = $stale;
            $retainedReader[] = $retained;
        }

        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $rows[] = [
                'page_number' => $pageNumber,
                'stale_reader_source' => $staleReader[$index]['source'] ?? 'error',
                'retained_reader_source' => $retainedReader[$index]['source'] ?? 'error',
                'pinned_current_source' => $retainedReader[$index]['source'] ?? 'error',
                'released_next_source' => $released['next_reader_sources'][$index] ?? 'error',
                'stale_reader_frame' => $staleReader[$index]['frame_index'] ?? null,
                'retained_reader_frame' => $retainedReader[$index]['frame_index'] ?? null,
                'pinned_current_frame' => $retainedReader[$index]['frame_index'] ?? null,
                'released_next_frame' => $released['next_reader_frame_indexes'][$index] ?? null,
                'stale_reader_label' => self::label((string) ($staleReader[$index]['image'] ?? '')),
                'retained_reader_label' => self::label((string) ($retainedReader[$index]['image'] ?? '')),
                'pinned_current_label' => self::label((string) ($retainedReader[$index]['image'] ?? '')),
                'released_next_label' => self::label((string) ($released['next_reader'][$index]['image'] ?? '')),
                'rollback_changed_reader' => ($staleReader[$index]['image'] ?? null) !== ($retainedReader[$index]['image'] ?? null),
                'pinned_matches_retained' => true,
                'released_matches_retained' => ($retainedReader[$index]['image'] ?? null) === ($released['next_reader'][$index]['image'] ?? null),
            ];
            $rows[array_key_last($rows)]['source_transition'] = implode('>', [
                $rows[array_key_last($rows)]['stale_reader_source'],
                $rows[array_key_last($rows)]['retained_reader_source'],
                $rows[array_key_last($rows)]['pinned_current_source'],
                $rows[array_key_last($rows)]['released_next_source'],
            ]);
        }

        $staleFrames = self::staleReaderFrames($readerWal, $retainedWal->frameCount(), $readerEndFrame, $pageSize);

        return [
            'status' => 'wal-reader-checkpoint-restart-savepoint-current-source-next127',
            'reason' => 'savepoint_rollback_retains_current_reader_source_before_released_restart_checkpoint_accepts_next_generation',
            'savepoint' => $savepoint,
            'mode' => 'restart',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'retained_reader_end_frame' => $retainedReaderEndFrame,
            'rollback' => $rollback,
            'retained_frame_count' => $retainedWal->frameCount(),
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'discarded_frame_indexes' => array_column($rollback['discarded_wal_frames'], 'frame_index'),
            'stale_reader_frames' => $staleFrames,
            'stale_reader_tail_frame_indexes' => array_column($staleFrames, 'frame_index'),
            'stale_reader_page_numbers' => array_values(array_unique(array_map('intval', array_column($staleFrames, 'page_number')))),
            'reader_source_matches_retained' => hash_equals(hash('sha256', $readerWalBytes), hash('sha256', $retainedWalBytes)),
            'reader_wal_sha256' => hash('sha256', $readerWalBytes),
            'current_wal_sha256' => hash('sha256', $walBytes),
            'retained_wal_sha256' => hash('sha256', $retainedWalBytes),
            'stale_reader' => $staleReader,
            'retained_reader' => $retainedReader,
            'pinned_checkpoint' => $pinned,
            'released_restart' => $released,
            'pinned_status' => $pinned['status'],
            'released_status' => $released['status'],
            'pinned_checkpoint_busy' => $pinned['checkpoint']['busy'] ?? null,
            'pinned_checkpoint_reason' => $pinned['checkpoint']['reason'] ?? null,
            'released_checkpoint_busy' => $released['checkpoint']['busy'] ?? null,
            'released_checkpoint_reason' => $released['checkpoint']['reason'] ?? null,
            'released_checkpoint_action' => $released['checkpoint']['wal_action'] ?? null,
            'released_restart_header_checkpoint_sequence' => $released['checkpoint']['wal_header']['checkpoint_sequence'] ?? null,
            'next_append_frame_count' => $released['append']['appended_frame_count'] ?? 0,
            'next_append_last_commit_frame' => $released['append']['last_commit_frame'] ?? null,
            'operations' => $released['operations'],
            'stale_reader_sources' => self::visibilityColumn($staleReader, 'source'),
            'retained_reader_sources' => self::visibilityColumn($retainedReader, 'source'),
            'pinned_current_sources' => self::visibilityColumn($retainedReader, 'source'),
            'released_next_sources' => $released['next_reader_sources'],
            'stale_reader_frame_indexes' => self::visibilityColumn($staleReader, 'frame_index'),
            'retained_reader_frame_indexes' => self::visibilityColumn($retainedReader, 'frame_index'),
            'pinned_current_frame_indexes' => self::visibilityColumn($retainedReader, 'frame_index'),
            'released_next_frame_indexes' => $released['next_reader_frame_indexes'],
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'rollback_changed_pages' => array_values(array_map(
                static fn (array $row): int => (int) $row['page_number'],
                array_filter($rows, static fn (array $row): bool => (bool) $row['rollback_changed_reader'])
            )),
            'pinned_preserved_retained_images' => !in_array(false, array_column($rows, 'pinned_matches_retained'), true),
            'released_reader_uses_restarted_generation' => ($released['next_uses_appended_wal'] ?? false) === true,
            'released_reader_uses_checkpoint_database' => ($released['next_uses_checkpoint_database'] ?? false) === true,
            'reader_release_unblocked_restart' => ($pinned['status'] ?? '') === 'busy' && ($released['status'] ?? '') === 'planned',
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $pinned['dependencies'],
                $released['dependencies'],
                [
                    'sqlite-wal-reader-checkpoint-restart-savepoint-current-source-next127',
                    'sqlite-wal-reader-checkpoint-savepoint-truncate-current-source-next123',
                    'sqlite-wal-checkpoint-reader-restart-snapshot-current-source-next124',
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
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next127 database image must be page aligned');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL reader checkpoint restart savepoint current-source next127 page {$pageNumber} is outside the database image");
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

    /**
     * @param list<array<string,mixed>> $entries
     * @return list<mixed>
     */
    private static function visibilityColumn(array $entries, string $column): array
    {
        return array_map(static fn (array $entry): mixed => $entry[$column] ?? null, $entries);
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
