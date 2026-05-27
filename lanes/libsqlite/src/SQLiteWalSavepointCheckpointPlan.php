<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalSavepointCheckpointPlan
{
    /**
     * @return array{status:string,savepoint:string,mode:string,reader_end_frame:int|null,original_frame_count:int,retained_frame_count:int,discarded_frame_count:int,truncate_to_bytes:int,current_wal_bytes:string,current_wal_bytes_length:int,current_checkpoint:array<string, mixed>,current_durable:array<string, mixed>,can_checkpoint:bool,can_reset:bool,can_truncate:bool,busy:bool,reason:string,dependencies:list<string>}
     */
    public static function afterRollbackTo(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        string $mode = 'passive',
        ?int $readerEndFrame = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint requires a savepoint name');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint requires database bytes');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['passive', 'full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite checkpoint mode after savepoint rollback: {$mode}");
        }

        $truncation = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $currentWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $currentWal = SQLiteWal::parse($currentWalBytes, $wal->header->pageSize, true);
        $checkpoint = $currentWal->checkpointModePlan($databaseBytes, $mode, $readerEndFrame);
        $durable = $currentWal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);

        return [
            'status' => $checkpoint['busy'] ? 'busy' : 'ready',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'reader_end_frame' => $readerEndFrame,
            'original_frame_count' => $truncation['original_frame_count'],
            'retained_frame_count' => $truncation['retained_frame_count'],
            'discarded_frame_count' => $truncation['discarded_frame_count'],
            'truncate_to_bytes' => $truncation['truncate_to_bytes'],
            'current_wal_bytes' => $currentWalBytes,
            'current_wal_bytes_length' => strlen($currentWalBytes),
            'current_checkpoint' => $checkpoint,
            'current_durable' => $durable,
            'can_checkpoint' => $checkpoint['total_committable_frame_count'] > 0,
            'can_reset' => $checkpoint['can_reset'],
            'can_truncate' => $checkpoint['can_truncate'],
            'busy' => $checkpoint['busy'],
            'reason' => $checkpoint['reason'],
            'dependencies' => array_values(array_unique(array_merge(
                $truncation['discarded_frame_count'] > 0 ? ['sqlite-savepoint-wal-current-prefix'] : [],
                $durable['dependencies'],
                ['sqlite-wal-savepoint-checkpoint-current']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,current_reader_end_frame:int|null,next_reader_end_frame:int|null,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,retained_frame_count:int,discarded_frame_count:int,current_reader:list<array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}>,next_reader:list<array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_images:list<string>,next_reader_images:list<string>,next_reader_uses_checkpoint_database:bool,current_reader_kept_wal_snapshot:bool,images_match:bool,dependencies:list<string>}
     */
    public static function readerBoundaryAfterRollbackTo(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'truncate',
        ?int $currentReaderEndFrame = null,
        ?int $nextReaderEndFrame = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader boundary requires at least one page number');
        }

        $checkpoint = self::afterRollbackTo($savepoints, $savepoint, $wal, $walBytes, $databaseBytes, $mode, $currentReaderEndFrame);
        $currentWal = SQLiteWal::parse($checkpoint['current_wal_bytes'], $wal->header->pageSize, true);
        $currentReaderEndFrame ??= $currentWal->frameCount();

        $current = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader boundary pages must be integers');
            }
            $current[] = $currentWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
        }

        $durable = $checkpoint['current_durable'];
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($durable['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame ??= $nextWal?->frameCount() ?? 0;

        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if ($nextWal === null) {
                $next[] = self::databasePageVisibility($durable['database_bytes'], $wal->header->pageSize, $pageNumber);
                continue;
            }
            $next[] = $nextWal->readerSnapshotPageImage($durable['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        $currentSources = self::visibilityColumn($current, 'source');
        $nextSources = self::visibilityColumn($next, 'source');
        $currentFrames = self::visibilityColumn($current, 'frame_index');
        $nextFrames = self::visibilityColumn($next, 'frame_index');
        $currentImages = self::visibilityColumn($current, 'image');
        $nextImages = self::visibilityColumn($next, 'image');

        return [
            'status' => $checkpoint['status'],
            'savepoint' => $savepoint,
            'mode' => $checkpoint['mode'],
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'wal_action' => $durable['wal_action'],
            'checkpoint_busy' => $checkpoint['busy'],
            'checkpoint_reason' => $checkpoint['reason'],
            'retained_frame_count' => $checkpoint['retained_frame_count'],
            'discarded_frame_count' => $checkpoint['discarded_frame_count'],
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => $currentSources,
            'next_reader_sources' => $nextSources,
            'current_reader_frame_indexes' => $currentFrames,
            'next_reader_frame_indexes' => $nextFrames,
            'current_reader_images' => $currentImages,
            'next_reader_images' => $nextImages,
            'next_reader_uses_checkpoint_database' => !in_array('wal', $nextSources, true),
            'current_reader_kept_wal_snapshot' => in_array('wal', $currentSources, true),
            'images_match' => $currentImages === $nextImages,
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                ['sqlite-wal-reader-checkpoint-boundary-current-next']
            ))),
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:int|null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:int|null,database_page_count:int}
     */
    private static function databasePageVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL reader page numbers are one-based');
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader requires a database image aligned to the page size');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL reader page {$pageNumber} is beyond the committed database size");
        }

        $offset = ($pageNumber - 1) * $pageSize;

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => $offset,
            'image' => substr($databaseBytes, $offset, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $databasePageCount,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<mixed>
     */
    private static function visibilityColumn(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column], $rows);
    }
}
