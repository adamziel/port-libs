<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointSnapshotRecoveryPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,mode:string,reader_end_frame:int,checkpoint:array<string,mixed>,recovery_status:string,recovery_error:string|null,recovered_wal_frame_count:int,recovered_wal_last_commit:int|null,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_images:list<string|null>,next_reader_images:list<string|null>,current_reader_keeps_precrash_snapshot:bool,next_reader_uses_recovered_wal:bool,next_reader_uses_checkpoint_database:bool,next_matches_checkpoint_durable:bool,wal_bytes_length:int,dependencies:list<string>}
     */
    public static function currentNextAfterCheckpointRecovery(
        SQLiteWal $preCrashWal,
        string $databaseBytes,
        string $recoveredWalBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint snapshot recovery requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['passive', 'full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL checkpoint snapshot recovery mode: {$mode}");
        }

        $readerEndFrame ??= $preCrashWal->frameCount();
        if ($readerEndFrame < 0 || $readerEndFrame > $preCrashWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint snapshot recovery reader frame is outside the WAL frame range');
        }

        $checkpoint = $preCrashWal->durableCheckpointResult($databaseBytes, $mode);

        $recoveredWal = null;
        $recoveryStatus = 'empty-wal';
        $recoveryError = null;
        if ($recoveredWalBytes !== '') {
            try {
                $recoveredWal = SQLiteWal::parse($recoveredWalBytes, $preCrashWal->header->pageSize, true);
                $recoveryStatus = 'valid-wal';
            } catch (\Throwable $exception) {
                $recoveryStatus = 'invalid-wal-fallback-database';
                $recoveryError = $exception->getMessage();
            }
        }

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint snapshot recovery pages must be integers');
            }

            $current[] = self::safeReaderVisibility($preCrashWal, $databaseBytes, $pageNumber, $readerEndFrame);
            if ($recoveredWal === null || $recoveredWal->frameCount() === 0) {
                $next[] = self::databasePageVisibilityOrError($checkpoint['database_bytes'], $preCrashWal->header->pageSize, $pageNumber);
                continue;
            }

            $next[] = self::safeReaderVisibility($recoveredWal, $checkpoint['database_bytes'], $pageNumber, $recoveredWal->frameCount());
        }

        $checkpointNext = [];
        $checkpointWal = null;
        if ($checkpoint['wal_bytes'] !== '') {
            try {
                $checkpointWal = SQLiteWal::parse($checkpoint['wal_bytes'], $preCrashWal->header->pageSize, true);
            } catch (\Throwable) {
                $checkpointWal = null;
            }
        }
        foreach ($pageNumbers as $pageNumber) {
            if ($checkpointWal === null || $checkpointWal->frameCount() === 0) {
                $checkpointNext[] = self::databasePageVisibilityOrError($checkpoint['database_bytes'], $preCrashWal->header->pageSize, $pageNumber);
                continue;
            }

            $checkpointNext[] = self::safeReaderVisibility($checkpointWal, $checkpoint['database_bytes'], $pageNumber, $checkpointWal->frameCount());
        }

        $currentImages = self::visibilityColumn($current, 'image');
        $nextImages = self::visibilityColumn($next, 'image');
        $checkpointImages = self::visibilityColumn($checkpointNext, 'image');

        return [
            'status' => $recoveryStatus === 'invalid-wal-fallback-database' ? 'recovered-with-database-fallback' : 'recovered',
            'mode' => $mode,
            'reader_end_frame' => $readerEndFrame,
            'checkpoint' => $checkpoint,
            'recovery_status' => $recoveryStatus,
            'recovery_error' => $recoveryError,
            'recovered_wal_frame_count' => $recoveredWal?->frameCount() ?? 0,
            'recovered_wal_last_commit' => $recoveredWal?->lastCommitFrame()?->index,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_images' => $currentImages,
            'next_reader_images' => $nextImages,
            'current_reader_keeps_precrash_snapshot' => $currentImages !== $nextImages || $readerEndFrame < $preCrashWal->frameCount(),
            'next_reader_uses_recovered_wal' => $recoveredWal !== null && $recoveredWal->frameCount() > 0 && in_array('wal', self::visibilityColumn($next, 'source'), true),
            'next_reader_uses_checkpoint_database' => !in_array('wal', self::visibilityColumn($next, 'source'), true),
            'next_matches_checkpoint_durable' => $nextImages === $checkpointImages,
            'wal_bytes_length' => strlen($recoveredWalBytes),
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                ['sqlite-wal-checkpoint-snapshot-recovery-current-next']
            ))),
        ];
    }

    private static function safeReaderVisibility(SQLiteWal $wal, string $databaseBytes, int $pageNumber, ?int $snapshotEndFrame): array
    {
        try {
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $snapshotEndFrame);
        } catch (\Throwable $exception) {
            return [
                'page_number' => $pageNumber,
                'source' => 'error',
                'frame_index' => null,
                'database_offset' => max(0, ($pageNumber - 1) * $wal->header->pageSize),
                'image' => null,
                'snapshot_end_frame' => $snapshotEndFrame ?? $wal->frameCount(),
                'snapshot_commit_frame' => $wal->lastCommitFrame()?->index,
                'database_page_count' => 0,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private static function databasePageVisibilityOrError(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        try {
            return self::databasePageVisibility($databaseBytes, $pageSize, $pageNumber);
        } catch (\Throwable $exception) {
            return [
                'page_number' => $pageNumber,
                'source' => 'error',
                'frame_index' => null,
                'database_offset' => max(0, ($pageNumber - 1) * $pageSize),
                'image' => null,
                'snapshot_end_frame' => 0,
                'snapshot_commit_frame' => null,
                'database_page_count' => intdiv(strlen($databaseBytes), $pageSize),
                'error' => $exception->getMessage(),
            ];
        }
    }

    private static function databasePageVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL reader page numbers are one-based');
        }
        if ($pageSize < 1 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader requires a database image aligned to the page size');
        }

        $offset = ($pageNumber - 1) * $pageSize;
        if ($offset + $pageSize > strlen($databaseBytes)) {
            throw new \OutOfBoundsException("SQLite WAL reader base page {$pageNumber} is missing from the database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => $offset,
            'image' => substr($databaseBytes, $offset, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => intdiv(strlen($databaseBytes), $pageSize),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function visibilityColumn(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }
}
