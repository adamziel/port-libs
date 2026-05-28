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

        self::assertCurrentWalSource($wal, $walBytes);
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
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,crash_phase:string,rollback_to_frame:int,retained_frame_count:int,discarded_frame_count:int,discarded_wal_frames:list<array<string,mixed>>,current_wal_bytes:string,current_wal_bytes_length:int,checkpoint_recovery:array<string,mixed>,current_reader_sources:list<string|null>,next_reader_sources:list<string|null>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,images_match:bool,next_uses_checkpoint_database:bool,next_replays_persisted_wal:bool,next_uses_reset_wal:bool,operations_applied:list<array<string,mixed>>,operations_pending:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function crashRecoveryCurrentNextAfterRollbackTo(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        string $databasePath,
        array $pageNumbers,
        string $mode = 'restart',
        string $crashPhase = 'after_database_sync',
        ?int $databasePageSize = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint crash recovery requires a savepoint name');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint crash recovery requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint crash recovery requires at least one page number');
        }

        self::assertCurrentWalSource($wal, $walBytes);
        $truncation = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $currentWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $pageSize = $databasePageSize ?? $wal->header->pageSize;
        $recovery = SQLiteWalCheckpointCrashRecoveryPlan::recoverFromWalBytes(
            $currentWalBytes,
            $databaseBytes,
            $databasePath,
            $pageNumbers,
            $mode,
            $crashPhase,
            $pageSize
        );

        return [
            'status' => $recovery['status'],
            'savepoint' => $savepoint,
            'mode' => $recovery['mode'],
            'crash_phase' => $recovery['crash_phase'],
            'rollback_to_frame' => $truncation['rollback_to_frame'],
            'retained_frame_count' => $truncation['retained_frame_count'],
            'discarded_frame_count' => $truncation['discarded_frame_count'],
            'discarded_wal_frames' => $truncation['discarded_wal_frames'],
            'current_wal_bytes' => $currentWalBytes,
            'current_wal_bytes_length' => strlen($currentWalBytes),
            'checkpoint_recovery' => $recovery,
            'current_reader_sources' => $recovery['current_reader_sources'],
            'next_reader_sources' => $recovery['next_reader_sources'],
            'current_reader_frame_indexes' => $recovery['current_reader_frame_indexes'],
            'next_reader_frame_indexes' => $recovery['next_reader_frame_indexes'],
            'images_match' => $recovery['images_match'],
            'next_uses_checkpoint_database' => $recovery['next_uses_checkpoint_database'],
            'next_replays_persisted_wal' => $recovery['next_replays_persisted_wal'],
            'next_uses_reset_wal' => $recovery['next_uses_reset_wal'],
            'operations_applied' => $recovery['operations_applied'],
            'operations_pending' => $recovery['operations_pending'],
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                ['sqlite-wal-savepoint-crash-recovery-checkpoint-current-next75']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,rollback:array<string,mixed>,release:array<string,mixed>,checkpoint:array<string,mixed>,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_kept_rollback_wal_prefix:bool,release_allows_checkpoint_reset:bool,next_reader_uses_checkpoint_database:bool,images_match:bool,dependencies:list<string>}
     */
    public static function releaseAfterRollbackCheckpointCurrentNext81(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $currentReaderEndFrame = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint release checkpoint requires a savepoint name');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint release checkpoint current/next requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL savepoint release checkpoint requires restart or truncate mode');
        }

        $working = clone $savepoints;
        $rollback = $working->rollbackToWithPlan($savepoint);
        $release = $working->releaseWithPlan($savepoint);
        $currentWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $currentWal = SQLiteWal::parse($currentWalBytes, $wal->header->pageSize, true);
        $currentReaderEndFrame ??= $currentWal->frameCount();
        if ($currentReaderEndFrame > $currentWal->frameCount()) {
            $currentReaderEndFrame = $currentWal->frameCount();
        }

        $checkpoint = $currentWal->durableCheckpointResult($databaseBytes, $mode);
        $nextWal = $checkpoint['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($checkpoint['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame = $nextWal?->frameCount() ?? 0;

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint release checkpoint pages must be integers');
            }

            $current[] = $currentWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $next[] = $nextWal === null
                ? self::databasePageVisibility($checkpoint['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $nextWal->readerSnapshotPageImage($checkpoint['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        $currentSources = self::visibilityColumn($current, 'source');
        $nextSources = self::visibilityColumn($next, 'source');
        $currentImages = self::visibilityColumn($current, 'image');
        $nextImages = self::visibilityColumn($next, 'image');

        return [
            'status' => $checkpoint['busy'] ? 'busy' : 'released-checkpointed',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'rollback' => $rollback,
            'release' => $release,
            'checkpoint' => $checkpoint,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => $currentSources,
            'next_reader_sources' => $nextSources,
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_kept_rollback_wal_prefix' => in_array('wal', $currentSources, true),
            'release_allows_checkpoint_reset' => !$checkpoint['busy'] && ($checkpoint['can_reset'] || $checkpoint['can_truncate']),
            'next_reader_uses_checkpoint_database' => !in_array('wal', $nextSources, true),
            'images_match' => $currentImages === $nextImages,
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                [
                    'sqlite-savepoint-rollback-to-current-keeps-savepoint',
                    'sqlite-savepoint-release-after-rollback-current-next81',
                    'sqlite-wal-savepoint-release-checkpoint-current-next81',
                ]
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<int|null> $currentReadMarks
     * @return array{status:string,savepoint:string,mode:string,current_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,retained_frame_count:int,discarded_frame_count:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_read_marks:array<string,mixed>,next_read_marks:array<string,mixed>,current_reader_kept_wal_snapshot:bool,next_reader_uses_checkpoint_database:bool,next_reader_uses_preserved_wal:bool,images_match:bool,dependencies:list<string>}
     */
    public static function readerPinCurrentNextAfterRollbackTo(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        array $currentReadMarks,
        string $mode = 'restart'
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader pin requires at least one page number');
        }
        if ($currentReadMarks === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader pin requires read marks');
        }

        $currentReadPlan = $wal->readMarkPlan($currentReadMarks);
        $currentReaderEndFrame = $currentReadPlan['checkpoint_pinned_frame']
            ?? $currentReadPlan['last_commit_frame']
            ?? $wal->frameCount();
        if ($currentReaderEndFrame < 1) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader pin requires a positive reader frame');
        }

        $checkpoint = self::afterRollbackTo($savepoints, $savepoint, $wal, $walBytes, $databaseBytes, $mode, $currentReaderEndFrame);
        $durable = $checkpoint['current_durable'];
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($durable['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame = $nextWal?->frameCount() ?? 0;
        $nextReadPlan = $nextWal?->readMarkPlan($nextReaderEndFrame > 0 ? [$nextReaderEndFrame] : [0]) ?? [
            'mx_frame' => 0,
            'last_commit_frame' => null,
            'checkpoint_pinned_frame' => null,
            'checkpoint_can_finish' => true,
            'reset_blocked' => false,
            'read_marks' => [[
                'slot' => 0,
                'frame' => 0,
                'active' => true,
                'valid' => true,
                'stale' => false,
                'pins_checkpoint' => false,
                'reason' => 'database_only_reader_after_wal_reset',
            ]],
            'reusable_slots' => [],
            'recommended_reader_slot' => null,
            'recommended_reader_frame' => null,
        ];

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint reader pin pages must be integers');
            }

            $current[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $next[] = $nextWal === null
                ? self::databasePageVisibility($durable['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $nextWal->readerSnapshotPageImage($durable['database_bytes'], $pageNumber, $nextReaderEndFrame);
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
            'current_read_marks' => $currentReadPlan,
            'next_read_marks' => $nextReadPlan,
            'current_reader_kept_wal_snapshot' => in_array('wal', $currentSources, true),
            'next_reader_uses_checkpoint_database' => !in_array('wal', $nextSources, true),
            'next_reader_uses_preserved_wal' => $durable['wal_action'] === 'preserve_wal',
            'images_match' => $currentImages === $nextImages,
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                ['sqlite-wal-savepoint-reader-pin-current-next']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<int|null> $currentReadMarks
     * @return array{status:string,savepoint:string,mode:string,released_frame_names:list<string>,merged_page_numbers:list<int>,target_is_transaction:bool,result_depth:int,current_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_images:list<string>,next_reader_images:list<string>,current_read_marks:array<string,mixed>,current_reader_kept_snapshot:bool,next_reader_sees_released_savepoint:bool,next_reader_uses_checkpoint_database:bool,next_reader_uses_preserved_wal:bool,images_match:bool,dependencies:list<string>}
     */
    public static function readerCurrentNextAfterRelease(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $databaseBytes,
        array $pageNumbers,
        array $currentReadMarks,
        string $mode = 'passive'
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint release reader boundary requires at least one page number');
        }
        if ($currentReadMarks === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint release reader boundary requires read marks');
        }

        $release = $savepoints->releasePlan($savepoint);
        $readMarkPlan = $wal->readMarkPlan($currentReadMarks);
        $currentReaderEndFrame = $readMarkPlan['checkpoint_pinned_frame']
            ?? $readMarkPlan['recommended_reader_frame']
            ?? $wal->frameCount();
        if ($currentReaderEndFrame === null || $currentReaderEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL savepoint release reader frame must be non-negative');
        }

        $checkpoint = $wal->durableCheckpointResult($databaseBytes, $mode, $readMarkPlan['checkpoint_pinned_frame']);
        $nextWal = $checkpoint['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($checkpoint['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame = $nextWal?->frameCount() ?? 0;

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint release reader boundary pages must be integers');
            }

            $current[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $next[] = $nextWal === null
                ? self::databasePageVisibility($checkpoint['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $nextWal->readerSnapshotPageImage($checkpoint['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        $currentSources = self::visibilityColumn($current, 'source');
        $nextSources = self::visibilityColumn($next, 'source');
        $currentFrames = self::visibilityColumn($current, 'frame_index');
        $nextFrames = self::visibilityColumn($next, 'frame_index');
        $currentImages = self::visibilityColumn($current, 'image');
        $nextImages = self::visibilityColumn($next, 'image');
        return [
            'status' => $checkpoint['busy'] ? 'busy' : 'ready',
            'savepoint' => $savepoint,
            'mode' => $checkpoint['mode'],
            'released_frame_names' => $release['released_frame_names'],
            'merged_page_numbers' => $release['merged_page_numbers'],
            'target_is_transaction' => $release['target_is_transaction'],
            'result_depth' => $release['result_depth'],
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'wal_action' => $checkpoint['wal_action'],
            'checkpoint_busy' => $checkpoint['busy'],
            'checkpoint_reason' => $checkpoint['reason'],
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => $currentSources,
            'next_reader_sources' => $nextSources,
            'current_reader_frame_indexes' => $currentFrames,
            'next_reader_frame_indexes' => $nextFrames,
            'current_reader_images' => $currentImages,
            'next_reader_images' => $nextImages,
            'current_read_marks' => $readMarkPlan,
            'current_reader_kept_snapshot' => $currentImages !== $nextImages || $currentReaderEndFrame !== $nextReaderEndFrame,
            'next_reader_sees_released_savepoint' => (bool) array_intersect($release['merged_page_numbers'], array_column($next, 'page_number')),
            'next_reader_uses_checkpoint_database' => !in_array('wal', $nextSources, true),
            'next_reader_uses_preserved_wal' => $checkpoint['wal_action'] === 'preserve_wal',
            'images_match' => $currentImages === $nextImages,
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                ['sqlite-wal-savepoint-release-reader-current-next', 'wordpress-import-release-reader-current-next']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,original_reader_end_frame:int,current_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,retained_frame_count:int,discarded_frame_count:int,stages:list<array{stage:string,reader:string,end_frame:int,wal_bytes_length:int,wal_action:string|null,sources:list<string>,frame_indexes:list<int|null>,page_numbers:list<int>,images:list<string>}>,before_reader:list<array<string,mixed>>,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,before_reader_sources:list<string>,current_reader_sources:list<string>,next_reader_sources:list<string>,before_reader_frame_indexes:list<int|null>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,before_to_current_images_match:bool,current_to_next_images_match:bool,rolled_back_frame_indexes:list<int>,rolled_back_page_numbers:list<int>,yield_count:int,dependencies:list<string>}
     */
    public static function yieldReaderSavepointCurrentNext(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'truncate',
        ?int $originalReaderEndFrame = null,
        ?int $nextReaderEndFrame = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint yield requires at least one page number');
        }

        $originalReaderEndFrame ??= $wal->frameCount();
        if ($originalReaderEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint yield reader frame must be non-negative');
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $currentWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $currentWal = SQLiteWal::parse($currentWalBytes, $wal->header->pageSize, true);
        $currentReaderEndFrame = min($originalReaderEndFrame, $currentWal->frameCount());
        $durable = $currentWal->durableCheckpointResult($databaseBytes, $mode, $currentReaderEndFrame);
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($durable['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame ??= $nextWal?->frameCount() ?? 0;

        $before = [];
        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint yield pages must be integers');
            }

            $before[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $originalReaderEndFrame);
            $current[] = $currentWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $next[] = $nextWal === null
                ? self::databasePageVisibility($durable['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $nextWal->readerSnapshotPageImage($durable['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        $beforeSources = self::visibilityColumn($before, 'source');
        $currentSources = self::visibilityColumn($current, 'source');
        $nextSources = self::visibilityColumn($next, 'source');
        $beforeFrames = self::visibilityColumn($before, 'frame_index');
        $currentFrames = self::visibilityColumn($current, 'frame_index');
        $nextFrames = self::visibilityColumn($next, 'frame_index');
        $beforeImages = self::visibilityColumn($before, 'image');
        $currentImages = self::visibilityColumn($current, 'image');
        $nextImages = self::visibilityColumn($next, 'image');
        $pageNumbersOut = self::visibilityColumn($current, 'page_number');

        return [
            'status' => $durable['busy'] ? 'busy' : 'ready',
            'savepoint' => $savepoint,
            'mode' => $durable['mode'],
            'original_reader_end_frame' => $originalReaderEndFrame,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'wal_action' => $durable['wal_action'],
            'checkpoint_busy' => $durable['busy'],
            'checkpoint_reason' => $durable['reason'],
            'retained_frame_count' => $rollback['retained_frame_count'],
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'stages' => [
                self::yieldStage('before_rollback', 'current_reader_original_savepoint', $originalReaderEndFrame, strlen($walBytes), null, $pageNumbersOut, $beforeSources, $beforeFrames, $beforeImages),
                self::yieldStage('after_rollback', 'current_reader_after_rollback_to', $currentReaderEndFrame, strlen($currentWalBytes), 'truncate_to_savepoint_prefix', $pageNumbersOut, $currentSources, $currentFrames, $currentImages),
                self::yieldStage('after_checkpoint', 'next_reader_after_checkpoint', $nextReaderEndFrame, strlen($durable['wal_bytes']), $durable['wal_action'], $pageNumbersOut, $nextSources, $nextFrames, $nextImages),
            ],
            'before_reader' => $before,
            'current_reader' => $current,
            'next_reader' => $next,
            'before_reader_sources' => $beforeSources,
            'current_reader_sources' => $currentSources,
            'next_reader_sources' => $nextSources,
            'before_reader_frame_indexes' => $beforeFrames,
            'current_reader_frame_indexes' => $currentFrames,
            'next_reader_frame_indexes' => $nextFrames,
            'before_to_current_images_match' => $beforeImages === $currentImages,
            'current_to_next_images_match' => $currentImages === $nextImages,
            'rolled_back_frame_indexes' => array_map(static fn (array $frame): int => $frame['frame_index'], $rollback['discarded_wal_frames']),
            'rolled_back_page_numbers' => self::discardedPageNumbers($rollback['discarded_wal_frames']),
            'yield_count' => 3 * count($pageNumbers),
            'dependencies' => array_values(array_unique(array_merge(
                $durable['dependencies'],
                ['sqlite-wal-savepoint-checkpoint-yield-current-next', 'wordpress-import-yield-savepoint-current-next']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,original_reader_end_frame:int,current_reader_end_frame:int,next_reader_end_frame:int,retained_frame_count:int,discarded_frame_count:int,current_source_rows:list<array{page_number:int,before_source:string,current_source:string,next_source:string,before_frame:int|null,current_frame:int|null,next_frame:int|null,rollback_changed_current:bool,checkpoint_changed_next:bool,source_transition:string,current_label:string,next_label:string}>,current_sources:list<string>,next_sources:list<string>,source_transitions:list<string>,current_source_counts:array<string,int>,next_source_counts:array<string,int>,rolled_back_page_numbers:list<int>,rolled_back_frame_indexes:list<int>,current_uses_rollback_prefix:bool,next_uses_checkpoint_database:bool,next_uses_preserved_wal:bool,images_match:bool,yield_count:int,dependencies:list<string>}
     */
    public static function checkpointReaderSavepointCurrentSourceNext85(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $originalReaderEndFrame = null,
        ?int $nextReaderEndFrame = null
    ): array {
        $yield = self::yieldReaderSavepointCurrentNext(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $originalReaderEndFrame,
            $nextReaderEndFrame
        );

        $rows = [];
        $transitions = [];
        foreach ($yield['current_reader'] as $index => $current) {
            $before = $yield['before_reader'][$index];
            $next = $yield['next_reader'][$index];
            $beforeSource = (string) $before['source'];
            $currentSource = (string) $current['source'];
            $nextSource = (string) $next['source'];
            $transition = $beforeSource . '>' . $currentSource . '>' . $nextSource;
            $transitions[] = $transition;

            $rows[] = [
                'page_number' => (int) $current['page_number'],
                'before_source' => $beforeSource,
                'current_source' => $currentSource,
                'next_source' => $nextSource,
                'before_frame' => $before['frame_index'] ?? null,
                'current_frame' => $current['frame_index'] ?? null,
                'next_frame' => $next['frame_index'] ?? null,
                'rollback_changed_current' => $before['image'] !== $current['image'],
                'checkpoint_changed_next' => $current['image'] !== $next['image'],
                'source_transition' => $transition,
                'current_label' => rtrim(substr((string) $current['image'], 0, 64), ".\0"),
                'next_label' => rtrim(substr((string) $next['image'], 0, 64), ".\0"),
            ];
        }

        return [
            'status' => $yield['status'],
            'savepoint' => $yield['savepoint'],
            'mode' => $yield['mode'],
            'wal_action' => $yield['wal_action'],
            'checkpoint_busy' => $yield['checkpoint_busy'],
            'checkpoint_reason' => $yield['checkpoint_reason'],
            'original_reader_end_frame' => $yield['original_reader_end_frame'],
            'current_reader_end_frame' => $yield['current_reader_end_frame'],
            'next_reader_end_frame' => $yield['next_reader_end_frame'],
            'retained_frame_count' => $yield['retained_frame_count'],
            'discarded_frame_count' => $yield['discarded_frame_count'],
            'current_source_rows' => $rows,
            'current_sources' => $yield['current_reader_sources'],
            'next_sources' => $yield['next_reader_sources'],
            'source_transitions' => $transitions,
            'current_source_counts' => array_count_values($yield['current_reader_sources']),
            'next_source_counts' => array_count_values($yield['next_reader_sources']),
            'rolled_back_page_numbers' => $yield['rolled_back_page_numbers'],
            'rolled_back_frame_indexes' => $yield['rolled_back_frame_indexes'],
            'current_uses_rollback_prefix' => in_array('wal', $yield['current_reader_sources'], true),
            'next_uses_checkpoint_database' => !in_array('wal', $yield['next_reader_sources'], true),
            'next_uses_preserved_wal' => $yield['wal_action'] === 'preserve_wal',
            'images_match' => $yield['current_to_next_images_match'],
            'yield_count' => $yield['yield_count'],
            'dependencies' => array_values(array_unique(array_merge(
                $yield['dependencies'],
                ['sqlite-wal-checkpoint-reader-savepoint-current-source-next85']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,current_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,retained_frame_count:int,discarded_frame_count:int,truncated_wal_bytes:int,restarted_wal_bytes:int,restarted_checkpoint_sequence:int|null,restarted_salt1:int|null,restarted_salt2:int|null,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_images:list<string>,next_reader_images:list<string>,current_reader_kept_retained_wal:bool,next_reader_uses_checkpoint_database:bool,next_reader_uses_restarted_header:bool,images_match:bool,dependencies:list<string>}
     */
    public static function readerRestartCurrentNextAfterRollbackTo(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart'
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader restart requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader restart requires restart or truncate mode');
        }

        $checkpoint = self::afterRollbackTo($savepoints, $savepoint, $wal, $walBytes, $databaseBytes, $mode);
        $currentWal = SQLiteWal::parse($checkpoint['current_wal_bytes'], $wal->header->pageSize, true);
        $currentReaderEndFrame = $currentWal->frameCount();
        $durable = $checkpoint['current_durable'];
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($durable['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame = $nextWal?->frameCount() ?? 0;

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint reader restart pages must be integers');
            }

            $current[] = $currentWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $next[] = $nextWal === null || $nextReaderEndFrame === 0
                ? self::databasePageVisibility($durable['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $nextWal->readerSnapshotPageImage($durable['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        $currentSources = self::visibilityColumn($current, 'source');
        $nextSources = self::visibilityColumn($next, 'source');
        $currentFrames = self::visibilityColumn($current, 'frame_index');
        $nextFrames = self::visibilityColumn($next, 'frame_index');
        $currentImages = self::visibilityColumn($current, 'image');
        $nextImages = self::visibilityColumn($next, 'image');
        $restartedHeader = $durable['wal_header'];

        return [
            'status' => $checkpoint['status'],
            'savepoint' => $savepoint,
            'mode' => $mode,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'wal_action' => $durable['wal_action'],
            'checkpoint_busy' => $checkpoint['busy'],
            'checkpoint_reason' => $checkpoint['reason'],
            'retained_frame_count' => $checkpoint['retained_frame_count'],
            'discarded_frame_count' => $checkpoint['discarded_frame_count'],
            'truncated_wal_bytes' => $checkpoint['current_wal_bytes_length'],
            'restarted_wal_bytes' => $durable['wal_bytes_length'],
            'restarted_checkpoint_sequence' => is_array($restartedHeader) ? (int) $restartedHeader['checkpoint_sequence'] : null,
            'restarted_salt1' => is_array($restartedHeader) ? (int) $restartedHeader['salt1'] : null,
            'restarted_salt2' => is_array($restartedHeader) ? (int) $restartedHeader['salt2'] : null,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => $currentSources,
            'next_reader_sources' => $nextSources,
            'current_reader_frame_indexes' => $currentFrames,
            'next_reader_frame_indexes' => $nextFrames,
            'current_reader_images' => $currentImages,
            'next_reader_images' => $nextImages,
            'current_reader_kept_retained_wal' => in_array('wal', $currentSources, true),
            'next_reader_uses_checkpoint_database' => !in_array('wal', $nextSources, true),
            'next_reader_uses_restarted_header' => $durable['wal_action'] === 'restart_wal' && $nextWal !== null && $nextWal->frameCount() === 0,
            'images_match' => $currentImages === $nextImages,
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                ['sqlite-wal-savepoint-reader-restart-current-next']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,before_reader_end_frame:int,after_release_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,release:array<string,mixed>,before_reader:list<array<string,mixed>>,after_release_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,before_reader_sources:list<string>,after_release_reader_sources:list<string>,next_reader_sources:list<string>,before_reader_frame_indexes:list<int|null>,after_release_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,before_to_release_images_match:bool,release_to_next_images_match:bool,merged_page_numbers:list<int>,released_frame_names:list<string>,yield_count:int,dependencies:list<string>}
     */
    public static function releaseReaderCheckpointCurrentNext(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $beforeReaderEndFrame = null,
        ?int $nextReaderEndFrame = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint release checkpoint requires at least one page number');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint release checkpoint requires database bytes');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['passive', 'full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite checkpoint mode after savepoint release: {$mode}");
        }

        $release = $savepoints->releasePlan($savepoint);
        $readerWasExplicit = $beforeReaderEndFrame !== null;
        $beforeReaderEndFrame ??= $wal->frameCount();
        if ($beforeReaderEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL savepoint release reader frame must be non-negative');
        }
        $afterReleaseReaderEndFrame = $wal->frameCount();
        $checkpointReaderEndFrame = $readerWasExplicit && $beforeReaderEndFrame < $wal->frameCount()
            ? $beforeReaderEndFrame
            : null;
        $durable = $wal->durableCheckpointResult($databaseBytes, $mode, $checkpointReaderEndFrame);
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($durable['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame ??= $nextWal?->frameCount() ?? 0;

        $before = [];
        $afterRelease = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint release checkpoint pages must be integers');
            }

            $before[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $beforeReaderEndFrame);
            $afterRelease[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $afterReleaseReaderEndFrame);
            $next[] = $nextWal === null
                ? self::databasePageVisibility($durable['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $nextWal->readerSnapshotPageImage($durable['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        $beforeSources = self::visibilityColumn($before, 'source');
        $afterReleaseSources = self::visibilityColumn($afterRelease, 'source');
        $nextSources = self::visibilityColumn($next, 'source');
        $beforeFrames = self::visibilityColumn($before, 'frame_index');
        $afterReleaseFrames = self::visibilityColumn($afterRelease, 'frame_index');
        $nextFrames = self::visibilityColumn($next, 'frame_index');
        $beforeImages = self::visibilityColumn($before, 'image');
        $afterReleaseImages = self::visibilityColumn($afterRelease, 'image');
        $nextImages = self::visibilityColumn($next, 'image');

        return [
            'status' => $durable['busy'] ? 'busy' : 'ready',
            'savepoint' => $savepoint,
            'mode' => $durable['mode'],
            'before_reader_end_frame' => $beforeReaderEndFrame,
            'after_release_reader_end_frame' => $afterReleaseReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'wal_action' => $durable['wal_action'],
            'checkpoint_busy' => $durable['busy'],
            'checkpoint_reason' => $durable['reason'],
            'release' => $release,
            'before_reader' => $before,
            'after_release_reader' => $afterRelease,
            'next_reader' => $next,
            'before_reader_sources' => $beforeSources,
            'after_release_reader_sources' => $afterReleaseSources,
            'next_reader_sources' => $nextSources,
            'before_reader_frame_indexes' => $beforeFrames,
            'after_release_reader_frame_indexes' => $afterReleaseFrames,
            'next_reader_frame_indexes' => $nextFrames,
            'before_to_release_images_match' => $beforeImages === $afterReleaseImages,
            'release_to_next_images_match' => $afterReleaseImages === $nextImages,
            'merged_page_numbers' => $release['merged_page_numbers'],
            'released_frame_names' => $release['released_frame_names'],
            'yield_count' => 3 * count($pageNumbers),
            'dependencies' => array_values(array_unique(array_merge(
                $durable['dependencies'],
                ['sqlite-wal-savepoint-release-checkpoint-current-next', 'wordpress-import-release-savepoint-current-next']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,before_reader_end_frame:int,after_release_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,release:array<string,mixed>,before_reader:list<array<string,mixed>>,after_release_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,before_reader_sources:list<string>,after_release_reader_sources:list<string>,next_reader_sources:list<string>,before_reader_frame_indexes:list<int|null>,after_release_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,before_to_release_images_match:bool,release_to_next_images_match:bool,merged_page_numbers:list<int>,released_frame_names:list<string>,yield_count:int,current_wal_bytes_length:int,current_wal_frame_count:int,current_wal_checkpoint_sequence:int,current_wal_salt1:int,current_wal_salt2:int,current_source_verified:bool,dependencies:list<string>}
     */
    public static function releaseReaderCheckpointCurrentSourceNext84(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $beforeReaderEndFrame = null,
        ?int $nextReaderEndFrame = null
    ): array {
        self::assertCurrentWalSource($wal, $walBytes);

        $plan = self::releaseReaderCheckpointCurrentNext(
            $savepoints,
            $savepoint,
            $wal,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $beforeReaderEndFrame,
            $nextReaderEndFrame
        );

        $plan['current_wal_bytes_length'] = strlen($walBytes);
        $plan['current_wal_frame_count'] = $wal->frameCount();
        $plan['current_wal_checkpoint_sequence'] = $wal->header->checkpointSequence;
        $plan['current_wal_salt1'] = $wal->header->salt1;
        $plan['current_wal_salt2'] = $wal->header->salt2;
        $plan['current_source_verified'] = true;
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-wal-savepoint-release-checkpoint-current-source-next84']
        )));

        return $plan;
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,current_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,retained_frame_count:int,discarded_frame_count:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_images:list<string>,next_reader_images:list<string>,next_reader_uses_checkpoint_database:bool,current_reader_kept_wal_snapshot:bool,images_match:bool,current_source_verified:bool,current_source:array{checkpoint_sequence:int,salt1:int,salt2:int,page_size:int,frame_count:int,wal_bytes_length:int},retained_source:array{checkpoint_sequence:int,salt1:int,salt2:int,page_size:int,frame_count:int,wal_bytes_length:int},next_source:array{kind:string,checkpoint_sequence:int|null,salt1:int|null,salt2:int|null,page_size:int,frame_count:int,wal_bytes_length:int,database_bytes_length:int},dependencies:list<string>}
     */
    public static function readerBoundaryCurrentSourceNext87(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $currentReaderEndFrame = null,
        ?int $nextReaderEndFrame = null
    ): array {
        self::assertCurrentWalSource($wal, $walBytes);

        $boundary = self::readerBoundaryAfterRollbackTo(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $currentReaderEndFrame,
            $nextReaderEndFrame
        );
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $durable = self::afterRollbackTo($savepoints, $savepoint, $wal, $walBytes, $databaseBytes, $mode, $currentReaderEndFrame)['current_durable'];
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($durable['wal_bytes'], $wal->header->pageSize, true);

        $boundary['current_source_verified'] = true;
        $boundary['current_source'] = self::walSourceSummary($wal, strlen($walBytes));
        $boundary['retained_source'] = self::walSourceSummary($retainedWal, strlen($retainedWalBytes));
        $boundary['next_source'] = [
            'kind' => $nextWal === null ? 'checkpoint_database' : $durable['wal_action'],
            'checkpoint_sequence' => $nextWal?->header->checkpointSequence,
            'salt1' => $nextWal?->header->salt1,
            'salt2' => $nextWal?->header->salt2,
            'page_size' => $nextWal?->header->pageSize ?? $wal->header->pageSize,
            'frame_count' => $nextWal?->frameCount() ?? 0,
            'wal_bytes_length' => strlen($durable['wal_bytes']),
            'database_bytes_length' => strlen($durable['database_bytes']),
        ];
        $boundary['dependencies'] = array_values(array_unique(array_merge(
            $boundary['dependencies'],
            ['sqlite-wal-savepoint-reader-current-source-next87']
        )));

        return $boundary;
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,original_reader_end_frame:int,current_reader_end_frame:int,next_reader_end_frame:int,retained_frame_count:int,discarded_frame_count:int,current_source_rows:list<array{page_number:int,before_source:string,current_source:string,next_source:string,before_frame:int|null,current_frame:int|null,next_frame:int|null,rollback_changed_current:bool,checkpoint_changed_next:bool,source_transition:string,current_label:string,next_label:string}>,current_sources:list<string>,next_sources:list<string>,source_transitions:list<string>,current_source_counts:array<string,int>,next_source_counts:array<string,int>,rolled_back_page_numbers:list<int>,rolled_back_frame_indexes:list<int>,current_uses_rollback_prefix:bool,next_uses_checkpoint_database:bool,next_uses_preserved_wal:bool,images_match:bool,yield_count:int,current_source_verified:bool,current_source:array{checkpoint_sequence:int,salt1:int,salt2:int,page_size:int,frame_count:int,wal_bytes_length:int},retained_source:array{checkpoint_sequence:int,salt1:int,salt2:int,page_size:int,frame_count:int,wal_bytes_length:int},frame_source_rows:list<array{frame_index:int,page_number:int,commit_frame:bool,database_page_count_after_commit:int,image_sha256:string,source_offset:int,source_length:int,matched_current_wal:bool}>,commit_frame_indexes:list<int>,dependencies:list<string>}
     */
    public static function checkpointReaderSavepointCurrentSourceNext90(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $originalReaderEndFrame = null,
        ?int $nextReaderEndFrame = null
    ): array {
        self::assertCurrentWalSource($wal, $walBytes);

        $plan = self::checkpointReaderSavepointCurrentSourceNext85(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $originalReaderEndFrame,
            $nextReaderEndFrame
        );
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $frameRows = self::exactWalFrameSourceRows($wal, $walBytes);

        $plan['current_source_verified'] = true;
        $plan['current_source'] = self::walSourceSummary($wal, strlen($walBytes));
        $plan['retained_source'] = self::walSourceSummary($retainedWal, strlen($retainedWalBytes));
        $plan['frame_source_rows'] = $frameRows;
        $plan['commit_frame_indexes'] = array_values(array_map(
            static fn (array $row): int => $row['frame_index'],
            array_filter($frameRows, static fn (array $row): bool => $row['commit_frame'])
        ));
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-wal-checkpoint-savepoint-reader-current-source-next90']
        )));

        return $plan;
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function checkpointReaderSavepointReleaseCurrentSourceNext94(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $pinnedReaderEndFrame = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next94 requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next94 requires restart or truncate mode');
        }

        self::assertCurrentWalSource($wal, $walBytes);

        $pinned = self::checkpointReaderSavepointCurrentSourceNext90(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $pinnedReaderEndFrame
        );
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $releasedDurable = $retainedWal->durableCheckpointResult($databaseBytes, $mode);
        $releasedWal = $releasedDurable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($releasedDurable['wal_bytes'], $wal->header->pageSize, true);
        $releasedReaderEndFrame = $releasedWal?->frameCount() ?? 0;

        $released = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next94 pages must be integers');
            }

            $released[] = $releasedWal === null || $releasedReaderEndFrame === 0
                ? self::databasePageVisibility($releasedDurable['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $releasedWal->readerSnapshotPageImage($releasedDurable['database_bytes'], $pageNumber, $releasedReaderEndFrame);
        }

        $releasedSources = self::visibilityColumn($released, 'source');
        $releasedImages = self::visibilityColumn($released, 'image');
        $rows = [];
        foreach ($pinned['current_source_rows'] as $index => $row) {
            $releasedRow = $released[$index];
            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'current_source' => (string) $row['current_source'],
                'pinned_next_source' => (string) $row['next_source'],
                'released_next_source' => (string) $releasedRow['source'],
                'current_frame' => $row['current_frame'],
                'pinned_next_frame' => $row['next_frame'],
                'released_next_frame' => $releasedRow['frame_index'] ?? null,
                'pinned_matches_current' => $row['checkpoint_changed_next'] === false,
                'released_changed_from_pinned' => $row['next_label'] !== rtrim(substr((string) $releasedRow['image'], 0, 64), ".\0"),
                'source_transition' => $row['current_source'] . '>' . $row['next_source'] . '>' . $releasedRow['source'],
                'released_label' => rtrim(substr((string) $releasedRow['image'], 0, 64), ".\0"),
            ];
        }

        return [
            'status' => $pinned['checkpoint_busy'] && !$releasedDurable['busy']
                ? 'reader-release-checkpoint-ready-current-source-next94'
                : 'reader-release-' . ($releasedDurable['busy'] ? 'busy' : 'ready'),
            'savepoint' => $savepoint,
            'mode' => $mode,
            'pinned_status' => $pinned['status'],
            'pinned_checkpoint_busy' => $pinned['checkpoint_busy'],
            'pinned_checkpoint_reason' => $pinned['checkpoint_reason'],
            'pinned_wal_action' => $pinned['wal_action'],
            'released_checkpoint_busy' => $releasedDurable['busy'],
            'released_checkpoint_reason' => $releasedDurable['reason'],
            'released_wal_action' => $releasedDurable['wal_action'],
            'original_reader_end_frame' => $pinned['original_reader_end_frame'],
            'current_reader_end_frame' => $pinned['current_reader_end_frame'],
            'pinned_next_reader_end_frame' => $pinned['next_reader_end_frame'],
            'released_next_reader_end_frame' => $releasedReaderEndFrame,
            'retained_frame_count' => $pinned['retained_frame_count'],
            'discarded_frame_count' => $pinned['discarded_frame_count'],
            'current_source_verified' => true,
            'current_source' => $pinned['current_source'],
            'retained_source' => $pinned['retained_source'],
            'released_source' => [
                'kind' => $releasedDurable['wal_action'],
                'checkpoint_sequence' => is_array($releasedDurable['wal_header']) ? $releasedDurable['wal_header']['checkpoint_sequence'] : null,
                'salt1' => is_array($releasedDurable['wal_header']) ? $releasedDurable['wal_header']['salt1'] : null,
                'salt2' => is_array($releasedDurable['wal_header']) ? $releasedDurable['wal_header']['salt2'] : null,
                'wal_bytes_length' => $releasedDurable['wal_bytes_length'],
                'database_bytes_length' => strlen((string) $releasedDurable['database_bytes']),
            ],
            'current_source_rows' => $rows,
            'current_sources' => $pinned['current_sources'],
            'pinned_next_sources' => $pinned['next_sources'],
            'released_next_sources' => $releasedSources,
            'source_transitions' => array_column($rows, 'source_transition'),
            'released_next_source_counts' => array_count_values($releasedSources),
            'released_next_reader' => $released,
            'released_next_images' => $releasedImages,
            'current_reader_preserved_by_pinned_checkpoint' => $pinned['images_match'],
            'released_reader_uses_checkpoint_database' => !in_array('wal', $releasedSources, true),
            'released_reader_uses_reset_source' => in_array($releasedDurable['wal_action'], ['restart_wal', 'truncate_wal'], true),
            'reader_release_unblocked_checkpoint' => $pinned['checkpoint_busy'] && !$releasedDurable['busy'],
            'rolled_back_page_numbers' => $pinned['rolled_back_page_numbers'],
            'rolled_back_frame_indexes' => $pinned['rolled_back_frame_indexes'],
            'yield_count' => $pinned['yield_count'] + count($pageNumbers),
            'dependencies' => array_values(array_unique(array_merge(
                $pinned['dependencies'],
                $releasedDurable['dependencies'],
                ['sqlite-wal-savepoint-reader-checkpoint-current-source-next94']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function checkpointReaderSavepointCurrentSourceNext99(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $pinnedReaderEndFrame = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next99 requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL savepoint reader checkpoint current-source next99 requires restart or truncate mode');
        }

        self::assertCurrentWalSource($wal, $walBytes);

        $pinned = self::checkpointReaderSavepointCurrentSourceNext90(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $pinnedReaderEndFrame
        );
        $released = self::checkpointReaderSavepointReleaseCurrentSourceNext94(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $pinnedReaderEndFrame
        );

        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $retainedDurable = $retainedWal->durableCheckpointResult($databaseBytes, $mode, $pinned['current_reader_end_frame']);
        $releasedDurable = $retainedWal->durableCheckpointResult($databaseBytes, $mode);

        $rows = [];
        foreach ($pinned['current_source_rows'] as $index => $row) {
            $releasedRow = $released['current_source_rows'][$index];
            $rows[] = [
                'page_number' => (int) $row['page_number'],
                'before_source' => (string) $row['before_source'],
                'current_source' => (string) $row['current_source'],
                'pinned_next_source' => (string) $row['next_source'],
                'released_next_source' => (string) $releasedRow['released_next_source'],
                'before_frame' => $row['before_frame'],
                'current_frame' => $row['current_frame'],
                'pinned_next_frame' => $row['next_frame'],
                'released_next_frame' => $releasedRow['released_next_frame'],
                'rollback_changed_current' => (bool) $row['rollback_changed_current'],
                'pinned_checkpoint_changed_next' => (bool) $row['checkpoint_changed_next'],
                'released_changed_from_pinned' => (bool) $releasedRow['released_changed_from_pinned'],
                'source_transition' => $row['before_source'] . '>' . $row['current_source'] . '>' . $row['next_source'] . '>' . $releasedRow['released_next_source'],
                'current_label' => (string) $row['current_label'],
                'pinned_next_label' => (string) $row['next_label'],
                'released_next_label' => (string) $releasedRow['released_label'],
            ];
        }

        $pinnedNextSource = [
            'kind' => $retainedDurable['wal_action'],
            'checkpoint_sequence' => is_array($retainedDurable['wal_header']) ? $retainedDurable['wal_header']['checkpoint_sequence'] : null,
            'salt1' => is_array($retainedDurable['wal_header']) ? $retainedDurable['wal_header']['salt1'] : null,
            'salt2' => is_array($retainedDurable['wal_header']) ? $retainedDurable['wal_header']['salt2'] : null,
            'page_size' => $wal->header->pageSize,
            'frame_count' => $retainedDurable['wal_bytes'] === '' ? 0 : SQLiteWal::parse($retainedDurable['wal_bytes'], $wal->header->pageSize, true)->frameCount(),
            'wal_bytes_length' => strlen((string) $retainedDurable['wal_bytes']),
            'database_bytes_length' => strlen((string) $retainedDurable['database_bytes']),
        ];

        return [
            'status' => $pinned['checkpoint_busy'] && !$released['released_checkpoint_busy']
                ? 'reader-release-checkpoint-current-source-next99'
                : 'reader-checkpoint-' . ($pinned['checkpoint_busy'] ? 'busy' : 'ready'),
            'savepoint' => $savepoint,
            'mode' => $mode,
            'pinned_checkpoint_busy' => $pinned['checkpoint_busy'],
            'pinned_checkpoint_reason' => $pinned['checkpoint_reason'],
            'pinned_wal_action' => $pinned['wal_action'],
            'released_checkpoint_busy' => $released['released_checkpoint_busy'],
            'released_checkpoint_reason' => $released['released_checkpoint_reason'],
            'released_wal_action' => $released['released_wal_action'],
            'original_reader_end_frame' => $pinned['original_reader_end_frame'],
            'current_reader_end_frame' => $pinned['current_reader_end_frame'],
            'pinned_next_reader_end_frame' => $pinned['next_reader_end_frame'],
            'released_next_reader_end_frame' => $released['released_next_reader_end_frame'],
            'retained_frame_count' => $pinned['retained_frame_count'],
            'discarded_frame_count' => $pinned['discarded_frame_count'],
            'current_source_verified' => true,
            'current_source' => $pinned['current_source'] + ['wal_sha256' => hash('sha256', $walBytes)],
            'retained_source' => $pinned['retained_source'] + ['wal_sha256' => hash('sha256', $retainedWalBytes)],
            'pinned_next_source' => $pinnedNextSource,
            'released_source' => $released['released_source'],
            'frame_source_rows' => $pinned['frame_source_rows'],
            'commit_frame_indexes' => $pinned['commit_frame_indexes'],
            'current_source_rows' => $rows,
            'current_sources' => $pinned['current_sources'],
            'pinned_next_sources' => $pinned['next_sources'],
            'released_next_sources' => $released['released_next_sources'],
            'source_transitions' => array_column($rows, 'source_transition'),
            'pinned_next_source_counts' => array_count_values($pinned['next_sources']),
            'released_next_source_counts' => array_count_values($released['released_next_sources']),
            'rolled_back_page_numbers' => $pinned['rolled_back_page_numbers'],
            'rolled_back_frame_indexes' => $pinned['rolled_back_frame_indexes'],
            'current_uses_rollback_prefix' => $pinned['current_uses_rollback_prefix'],
            'pinned_reader_preserves_wal' => $pinned['next_uses_preserved_wal'],
            'released_reader_uses_checkpoint_database' => $released['released_reader_uses_checkpoint_database'],
            'reader_release_unblocked_checkpoint' => $released['reader_release_unblocked_checkpoint'],
            'pinned_images_match' => $pinned['images_match'],
            'released_images_match' => $released['current_reader_preserved_by_pinned_checkpoint'],
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'yield_count' => $released['yield_count'] + count($pinned['frame_source_rows']),
            'dependencies' => array_values(array_unique(array_merge(
                $pinned['dependencies'],
                $released['dependencies'],
                ['sqlite-wal-savepoint-reader-checkpoint-current-source-next99']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,released_savepoint:string,rollback_savepoint:string,release:array<string,mixed>,boundary:array<string,mixed>,released_frame_names:list<string>,merged_page_numbers:list<int>,retained_frame_count:int,discarded_frame_count:int,rolled_back_released_frames:list<int>,rolled_back_released_pages:list<int>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,images_match:bool,dependencies:list<string>}
     */
    public static function releaseThenRollbackCheckpointCurrentNext(
        SQLiteSavepointStack $savepoints,
        string $releasedSavepoint,
        string $rollbackSavepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'truncate',
        ?int $currentReaderEndFrame = null,
        ?int $nextReaderEndFrame = null
    ): array {
        if ($releasedSavepoint === '' || $rollbackSavepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL release/checkpoint requires savepoint names');
        }
        if ($releasedSavepoint === $rollbackSavepoint) {
            throw new \InvalidArgumentException('SQLite WAL release/checkpoint requires distinct savepoints');
        }

        $releasedWalPlan = $savepoints->walRollbackToPlan($releasedSavepoint);
        $working = clone $savepoints;
        $release = $working->releaseWithPlan($releasedSavepoint);
        $boundary = self::readerBoundaryAfterRollbackTo(
            $working,
            $rollbackSavepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $currentReaderEndFrame,
            $nextReaderEndFrame
        );
        $rollback = $working->walRollbackToPlan($rollbackSavepoint);
        $releasedNames = $release['released_frame_names'];
        $releasedFrameIndexes = [];
        $releasedPages = [];
        $releasedWalFrameIndexes = [];
        foreach ($releasedWalPlan['discarded_wal_frames'] as $frame) {
            $releasedWalFrameIndexes[$frame['frame_index']] = true;
        }
        foreach ($rollback['discarded_wal_frames'] as $frame) {
            if (!isset($releasedWalFrameIndexes[$frame['frame_index']])) {
                continue;
            }
            $releasedFrameIndexes[] = $frame['frame_index'];
            $releasedPages[$frame['page_number']] = true;
        }

        $releasedPageNumbers = array_keys($releasedPages);
        sort($releasedPageNumbers, SORT_NUMERIC);

        return [
            'status' => $boundary['status'],
            'released_savepoint' => $releasedSavepoint,
            'rollback_savepoint' => $rollbackSavepoint,
            'release' => $release,
            'boundary' => $boundary,
            'released_frame_names' => $releasedNames,
            'merged_page_numbers' => $release['merged_page_numbers'],
            'retained_frame_count' => $boundary['retained_frame_count'],
            'discarded_frame_count' => $boundary['discarded_frame_count'],
            'rolled_back_released_frames' => $releasedFrameIndexes,
            'rolled_back_released_pages' => $releasedPageNumbers,
            'current_reader_sources' => $boundary['current_reader_sources'],
            'next_reader_sources' => $boundary['next_reader_sources'],
            'current_reader_frame_indexes' => $boundary['current_reader_frame_indexes'],
            'next_reader_frame_indexes' => $boundary['next_reader_frame_indexes'],
            'images_match' => $boundary['images_match'],
            'dependencies' => array_values(array_unique(array_merge(
                $boundary['dependencies'],
                ['sqlite-wal-release-rollback-checkpoint-current-next']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,released_savepoint:string,rollback_savepoint:string,release:array<string,mixed>,boundary:array<string,mixed>,released_frame_names:list<string>,merged_page_numbers:list<int>,retained_frame_count:int,discarded_frame_count:int,rolled_back_released_frames:list<int>,rolled_back_released_pages:list<int>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,images_match:bool,current_source_verified:bool,current_source:array{checkpoint_sequence:int,salt1:int,salt2:int,page_size:int,frame_count:int,wal_bytes_length:int},retained_source:array{checkpoint_sequence:int,salt1:int,salt2:int,page_size:int,frame_count:int,wal_bytes_length:int},next_source:array{kind:string,checkpoint_sequence:int|null,salt1:int|null,salt2:int|null,page_size:int,frame_count:int,wal_bytes_length:int,database_bytes_length:int},dependencies:list<string>}
     */
    public static function releaseThenRollbackCheckpointCurrentSourceNext91(
        SQLiteSavepointStack $savepoints,
        string $releasedSavepoint,
        string $rollbackSavepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'truncate',
        ?int $currentReaderEndFrame = null,
        ?int $nextReaderEndFrame = null
    ): array {
        self::assertCurrentWalSource($wal, $walBytes);

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL release/rollback current-source checkpoint requires restart or truncate mode');
        }

        $working = clone $savepoints;
        $working->release($releasedSavepoint);
        $retainedWalBytes = $working->walRollbackToWalBytes($rollbackSavepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $checkpoint = self::afterRollbackTo($working, $rollbackSavepoint, $wal, $walBytes, $databaseBytes, $mode, $currentReaderEndFrame);
        $durable = $checkpoint['current_durable'];
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($durable['wal_bytes'], $wal->header->pageSize, true);

        $plan = self::releaseThenRollbackCheckpointCurrentNext(
            $savepoints,
            $releasedSavepoint,
            $rollbackSavepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $currentReaderEndFrame,
            $nextReaderEndFrame
        );

        $plan['current_source_verified'] = true;
        $plan['current_source'] = self::walSourceSummary($wal, strlen($walBytes));
        $plan['retained_source'] = self::walSourceSummary($retainedWal, strlen($retainedWalBytes));
        $plan['next_source'] = [
            'kind' => $nextWal === null ? 'checkpoint_database' : $durable['wal_action'],
            'checkpoint_sequence' => $nextWal?->header->checkpointSequence,
            'salt1' => $nextWal?->header->salt1,
            'salt2' => $nextWal?->header->salt2,
            'page_size' => $nextWal?->header->pageSize ?? $wal->header->pageSize,
            'frame_count' => $nextWal?->frameCount() ?? 0,
            'wal_bytes_length' => strlen($durable['wal_bytes']),
            'database_bytes_length' => strlen($durable['database_bytes']),
        ];
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-wal-release-rollback-checkpoint-current-source-next91']
        )));

        return $plan;
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,rollback_to_frame:int,retained_frame_count:int,discarded_frame_count:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}>,committed_frame_names:list<string>,committed_page_numbers:list<int>,released_savepoint_count:int,transaction_active_after:bool,current_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_images:list<string>,next_reader_images:list<string>,current_reader_kept_retained_wal:bool,next_reader_uses_checkpoint_database:bool,images_match:bool,current_durable:array<string,mixed>,dependencies:list<string>}
     */
    public static function commitCurrentAfterRollbackTo(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $currentReaderEndFrame = null,
        ?int $nextReaderEndFrame = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint commit-current requires at least one page number');
        }

        $checkpoint = self::afterRollbackTo($savepoints, $savepoint, $wal, $walBytes, $databaseBytes, $mode, $currentReaderEndFrame);
        $rollback = $savepoints->walRollbackToPlan($savepoint);
        $currentWal = SQLiteWal::parse($checkpoint['current_wal_bytes'], $wal->header->pageSize, true);
        $currentReaderEndFrame ??= $currentWal->frameCount();
        if ($currentReaderEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL savepoint commit-current reader frame must be non-negative');
        }

        $working = clone $savepoints;
        $working->rollbackTo($savepoint);
        $commit = $working->commitWithPlan();

        $durable = $checkpoint['current_durable'];
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($durable['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame ??= $nextWal?->frameCount() ?? 0;

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint commit-current pages must be integers');
            }

            $current[] = $currentWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $currentReaderEndFrame);
            $next[] = $nextWal === null
                ? self::databasePageVisibility($durable['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $nextWal->readerSnapshotPageImage($durable['database_bytes'], $pageNumber, $nextReaderEndFrame);
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
            'rollback_to_frame' => $rollback['rollback_to_frame'],
            'retained_frame_count' => $checkpoint['retained_frame_count'],
            'discarded_frame_count' => $checkpoint['discarded_frame_count'],
            'discarded_wal_frames' => $rollback['discarded_wal_frames'],
            'committed_frame_names' => $commit['committed_frame_names'],
            'committed_page_numbers' => $commit['committed_page_numbers'],
            'released_savepoint_count' => $commit['released_savepoint_count'],
            'transaction_active_after' => $commit['transaction_active_after'],
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'wal_action' => $durable['wal_action'],
            'checkpoint_busy' => $checkpoint['busy'],
            'checkpoint_reason' => $checkpoint['reason'],
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => $currentSources,
            'next_reader_sources' => $nextSources,
            'current_reader_frame_indexes' => $currentFrames,
            'next_reader_frame_indexes' => $nextFrames,
            'current_reader_images' => $currentImages,
            'next_reader_images' => $nextImages,
            'current_reader_kept_retained_wal' => in_array('wal', $currentSources, true),
            'next_reader_uses_checkpoint_database' => !in_array('wal', $nextSources, true),
            'images_match' => $currentImages === $nextImages,
            'current_durable' => $durable,
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                ['sqlite-wal-savepoint-commit-current-next72', 'wordpress-import-savepoint-commit-current-next72']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<string> $sources
     * @param list<int|null> $frameIndexes
     * @param list<string> $images
     * @return array{stage:string,reader:string,end_frame:int,wal_bytes_length:int,wal_action:string|null,sources:list<string>,frame_indexes:list<int|null>,page_numbers:list<int>,images:list<string>}
     */
    private static function yieldStage(
        string $stage,
        string $reader,
        int $endFrame,
        int $walBytesLength,
        ?string $walAction,
        array $pageNumbers,
        array $sources,
        array $frameIndexes,
        array $images
    ): array {
        return [
            'stage' => $stage,
            'reader' => $reader,
            'end_frame' => $endFrame,
            'wal_bytes_length' => $walBytesLength,
            'wal_action' => $walAction,
            'sources' => $sources,
            'frame_indexes' => $frameIndexes,
            'page_numbers' => $pageNumbers,
            'images' => $images,
        ];
    }

    /**
     * @param list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}> $discardedFrames
     * @return list<int>
     */
    private static function discardedPageNumbers(array $discardedFrames): array
    {
        $pages = [];
        foreach ($discardedFrames as $frame) {
            $pages[$frame['page_number']] = true;
        }

        $pageNumbers = array_keys($pages);
        sort($pageNumbers, SORT_NUMERIC);

        return $pageNumbers;
    }

    private static function assertCurrentWalSource(SQLiteWal $wal, string $walBytes): void
    {
        $source = SQLiteWal::parse($walBytes, $wal->header->pageSize, $wal->checksumsValidated);
        if ($source->header->pageSize !== $wal->header->pageSize) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint current source page size mismatch');
        }
        if ($source->header->checkpointSequence !== $wal->header->checkpointSequence) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint current source checkpoint sequence mismatch');
        }
        if ($source->header->salt1 !== $wal->header->salt1 || $source->header->salt2 !== $wal->header->salt2) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint current source salt mismatch');
        }
        if ($source->frameCount() !== $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint current source frame count mismatch');
        }
        self::exactWalFrameSourceRows($wal, $walBytes);
    }

    /**
     * @return array{checkpoint_sequence:int,salt1:int,salt2:int,page_size:int,frame_count:int,wal_bytes_length:int}
     */
    private static function walSourceSummary(SQLiteWal $wal, int $walBytesLength): array
    {
        return [
            'checkpoint_sequence' => $wal->header->checkpointSequence,
            'salt1' => $wal->header->salt1,
            'salt2' => $wal->header->salt2,
            'page_size' => $wal->header->pageSize,
            'frame_count' => $wal->frameCount(),
            'wal_bytes_length' => $walBytesLength,
        ];
    }

    /**
     * @return list<array{frame_index:int,page_number:int,commit_frame:bool,database_page_count_after_commit:int,image_sha256:string,source_offset:int,source_length:int,matched_current_wal:bool}>
     */
    private static function exactWalFrameSourceRows(SQLiteWal $wal, string $walBytes): array
    {
        $source = SQLiteWal::parse($walBytes, $wal->header->pageSize, $wal->checksumsValidated);
        $pageSize = $wal->header->pageSize;
        $frameSize = 24 + $pageSize;
        $rows = [];

        foreach ($source->frames as $index => $sourceFrame) {
            $walFrame = $wal->frames[$index] ?? null;
            if ($walFrame === null) {
                throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint current source frame count mismatch');
            }
            if (
                $sourceFrame->pageNumber !== $walFrame->pageNumber
                || $sourceFrame->databasePageCountAfterCommit !== $walFrame->databasePageCountAfterCommit
                || $sourceFrame->pageImage !== $walFrame->pageImage
            ) {
                throw new \InvalidArgumentException("SQLite WAL savepoint checkpoint current source frame {$sourceFrame->index} mismatch");
            }

            $rows[] = [
                'frame_index' => $sourceFrame->index,
                'page_number' => $sourceFrame->pageNumber,
                'commit_frame' => $sourceFrame->isCommitFrame(),
                'database_page_count_after_commit' => $sourceFrame->databasePageCountAfterCommit,
                'image_sha256' => hash('sha256', $sourceFrame->pageImage),
                'source_offset' => 32 + ($index * $frameSize),
                'source_length' => $frameSize,
                'matched_current_wal' => true,
            ];
        }

        return $rows;
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
