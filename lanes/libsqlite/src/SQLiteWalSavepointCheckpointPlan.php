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
    public static function releaseAfterRollbackCheckpointCurrentNext(
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
                ['sqlite-wal-savepoint-release-reader-current-next', 'application-import-release-reader-current-next']
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
                ['sqlite-wal-savepoint-checkpoint-yield-current-next', 'application-import-yield-savepoint-current-next']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,original_reader_end_frame:int,current_reader_end_frame:int,next_reader_end_frame:int,retained_frame_count:int,discarded_frame_count:int,current_source_rows:list<array{page_number:int,before_source:string,current_source:string,next_source:string,before_frame:int|null,current_frame:int|null,next_frame:int|null,rollback_changed_current:bool,checkpoint_changed_next:bool,source_transition:string,current_label:string,next_label:string}>,current_sources:list<string>,next_sources:list<string>,source_transitions:list<string>,current_source_counts:array<string,int>,next_source_counts:array<string,int>,rolled_back_page_numbers:list<int>,rolled_back_frame_indexes:list<int>,current_uses_rollback_prefix:bool,next_uses_checkpoint_database:bool,next_uses_preserved_wal:bool,images_match:bool,yield_count:int,dependencies:list<string>}
     */
    public static function checkpointReaderSavepointCurrentSourceNext(
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
                ['sqlite-wal-savepoint-release-checkpoint-current-next', 'application-import-release-savepoint-current-next']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,before_reader_end_frame:int,after_release_reader_end_frame:int,next_reader_end_frame:int,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,release:array<string,mixed>,before_reader:list<array<string,mixed>>,after_release_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,before_reader_sources:list<string>,after_release_reader_sources:list<string>,next_reader_sources:list<string>,before_reader_frame_indexes:list<int|null>,after_release_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,before_to_release_images_match:bool,release_to_next_images_match:bool,merged_page_numbers:list<int>,released_frame_names:list<string>,yield_count:int,current_wal_bytes_length:int,current_wal_frame_count:int,current_wal_checkpoint_sequence:int,current_wal_salt1:int,current_wal_salt2:int,current_source_verified:bool,dependencies:list<string>}
     */
    public static function releaseReaderCheckpointCurrentSourceNext(
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
    public static function readerBoundaryCurrentSourceNext(
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
    public static function checkpointReaderSavepointPinnedCurrentSourceNext(
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

        $plan = self::checkpointReaderSavepointCurrentSourceNext(
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
    public static function checkpointReaderSavepointReleaseCurrentSourceNext(
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

        $pinned = self::checkpointReaderSavepointPinnedCurrentSourceNext(
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
    public static function checkpointReaderSavepointRecoveryCurrentSourceNext(
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

        $pinned = self::checkpointReaderSavepointPinnedCurrentSourceNext(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $pinnedReaderEndFrame
        );
        $released = self::checkpointReaderSavepointReleaseCurrentSourceNext(
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
            'released_next_images' => $released['released_next_images'],
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
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function savepointRestartAppendReaderCurrentSourceNext(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $pinnedReaderEndFrame = null,
        bool $syncWal = true,
        bool $syncDirectory = true
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart append current-source next103 requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart append current-source next103 requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart append current-source next103 requires restart or truncate mode');
        }

        self::assertCurrentWalSource($wal, $walBytes);

        $released = self::checkpointReaderSavepointRecoveryCurrentSourceNext(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            $mode,
            $pinnedReaderEndFrame
        );
        if (!$released['reader_release_unblocked_checkpoint']) {
            throw new \RuntimeException('SQLite WAL savepoint restart append current-source next103 requires release to unblock checkpoint reset');
        }

        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $releasedDurable = $retainedWal->durableCheckpointResult($databaseBytes, $mode);
        $checkpointWal = self::walAfterDurableCheckpoint($retainedWal, $releasedDurable);
        $append = SQLiteWalAppendPlan::appendTransactions($checkpointWal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame = $append['last_commit_frame'] ?? $nextWal->frameCount();

        $current = [];
        $next = [];
        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint restart append current-source next103 pages must be integers');
            }

            $currentRow = $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $released['current_reader_end_frame']);
            $nextRow = $nextWal->readerSnapshotPageImage($releasedDurable['database_bytes'], $pageNumber, $nextReaderEndFrame);
            $current[] = $currentRow;
            $next[] = $nextRow;

            $releasedRow = $released['current_source_rows'][$index];
            $rows[] = [
                'page_number' => $pageNumber,
                'before_source' => (string) $releasedRow['before_source'],
                'current_source' => (string) $releasedRow['current_source'],
                'released_source' => (string) $releasedRow['released_next_source'],
                'next_source' => (string) $nextRow['source'],
                'before_frame' => $releasedRow['before_frame'],
                'current_frame' => $releasedRow['current_frame'],
                'released_frame' => $releasedRow['released_next_frame'],
                'next_frame' => $nextRow['frame_index'] ?? null,
                'current_label' => (string) $releasedRow['current_label'],
                'released_label' => (string) $releasedRow['released_next_label'],
                'next_label' => rtrim(substr((string) $nextRow['image'], 0, 80), ".\0"),
                'current_to_next_changed' => (string) $currentRow['image'] !== (string) $nextRow['image'],
                'released_to_next_changed' => (string) $releasedRow['released_next_label'] !== rtrim(substr((string) $nextRow['image'], 0, 80), ".\0"),
                'source_transition' => $releasedRow['before_source'] . '>' . $releasedRow['current_source'] . '>' . $releasedRow['released_next_source'] . '>' . $nextRow['source'],
            ];
        }

        $currentSources = self::visibilityColumn($current, 'source');
        $nextSources = self::visibilityColumn($next, 'source');

        return [
            'status' => 'savepoint-restart-append-current-source-next103',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'pinned' => $released,
            'released_checkpoint' => $releasedDurable,
            'append' => $append,
            'original_reader_end_frame' => $released['original_reader_end_frame'],
            'current_reader_end_frame' => $released['current_reader_end_frame'],
            'released_next_reader_end_frame' => $released['released_next_reader_end_frame'],
            'next_reader_end_frame' => $nextReaderEndFrame,
            'retained_frame_count' => $released['retained_frame_count'],
            'discarded_frame_count' => $released['discarded_frame_count'],
            'current_source_verified' => true,
            'current_source' => $released['current_source'],
            'retained_source' => $released['retained_source'],
            'released_source' => $released['released_source'],
            'next_source' => [
                'checkpoint_sequence' => $nextWal->header->checkpointSequence,
                'salt1' => $nextWal->header->salt1,
                'salt2' => $nextWal->header->salt2,
                'page_size' => $nextWal->header->pageSize,
                'frame_count' => $nextWal->frameCount(),
                'wal_bytes_length' => strlen((string) $append['wal_bytes']),
            ],
            'frame_source_rows' => $released['frame_source_rows'],
            'commit_frame_indexes' => $released['commit_frame_indexes'],
            'current_reader' => $current,
            'next_reader' => $next,
            'current_source_rows' => $rows,
            'current_sources' => $currentSources,
            'released_next_sources' => $released['released_next_sources'],
            'next_sources' => $nextSources,
            'source_transitions' => array_column($rows, 'source_transition'),
            'current_source_counts' => array_count_values($currentSources),
            'next_source_counts' => array_count_values($nextSources),
            'rolled_back_page_numbers' => $released['rolled_back_page_numbers'],
            'rolled_back_frame_indexes' => $released['rolled_back_frame_indexes'],
            'reader_release_unblocked_checkpoint' => true,
            'next_uses_restarted_generation' => in_array($releasedDurable['wal_action'], ['restart_wal', 'truncate_wal'], true),
            'next_uses_appended_wal' => $append['committed_transaction_count'] > 0,
            'current_reader_preserved' => (bool) $released['released_images_match'],
            'images_match' => self::visibilityColumn($current, 'image') === self::visibilityColumn($next, 'image'),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'yield_count' => $released['yield_count'] + count($transactions) + count($pageNumbers),
            'dependencies' => array_values(array_unique(array_merge(
                $released['dependencies'],
                $releasedDurable['dependencies'],
                $append['dependencies'],
                ['sqlite-wal-savepoint-restart-reader-current-source-next103']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function checkpointRestartTruncateSavepointReaderCurrentSourceNext(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        SQLiteShmIndex $currentShm,
        SQLiteShmIndex $nextReaderShm,
        SQLiteShmIndex $allReleasedShm,
        array $pageNumbers
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart/truncate reader current-source next105 requires at least one page number');
        }

        self::assertCurrentWalSource($wal, $walBytes);
        $currentPlan = $currentShm->checkpointPlan();
        $nextPlan = $nextReaderShm->checkpointPlan();
        $allReleasedPlan = $allReleasedShm->checkpointPlan();
        $currentSalt = $currentShm->header['salt'] ?? [];
        if (($currentSalt[0] ?? null) !== $wal->header->salt1 || ($currentSalt[1] ?? null) !== $wal->header->salt2) {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart/truncate reader current-source next105 SHM salt does not match current WAL');
        }
        if ((int) ($currentShm->header['mx_frame'] ?? -1) !== $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart/truncate reader current-source next105 SHM mxFrame does not match current WAL');
        }

        $pinnedFrame = $currentPlan['checkpoint_pinned_frame'];
        if (!is_int($pinnedFrame) || $pinnedFrame < 1) {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart/truncate reader current-source next105 requires a current reader pin');
        }

        $restart = self::checkpointReaderSavepointRecoveryCurrentSourceNext(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            'restart',
            $pinnedFrame
        );
        $truncate = self::checkpointReaderSavepointRecoveryCurrentSourceNext(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $pageNumbers,
            'truncate',
            $pinnedFrame
        );

        $restartRows = $restart['current_source_rows'];
        $truncateRows = $truncate['current_source_rows'];

        return [
            'status' => $restart['reader_release_unblocked_checkpoint']
                && $truncate['reader_release_unblocked_checkpoint']
                && $currentPlan['reset_blocked']
                && $nextPlan['reset_blocked']
                && !$allReleasedPlan['reset_blocked']
                    ? 'savepoint-reader-current-source-next105'
                    : 'savepoint-reader-current-source-next105-incomplete',
            'savepoint' => $savepoint,
            'current_source_verified' => true,
            'shm_source_verified' => true,
            'current_source' => $restart['current_source'],
            'retained_source' => $restart['retained_source'],
            'current_reader_end_frame' => $restart['current_reader_end_frame'],
            'next_reader_end_frame' => $restart['pinned_next_reader_end_frame'],
            'released_restart_reader_end_frame' => $restart['released_next_reader_end_frame'],
            'released_truncate_reader_end_frame' => $truncate['released_next_reader_end_frame'],
            'retained_frame_count' => $restart['retained_frame_count'],
            'discarded_frame_count' => $restart['discarded_frame_count'],
            'current_shm_source' => [
                'mx_frame' => $currentShm->header['mx_frame'],
                'backfilled_frame_count' => $currentShm->backfilledFrameCount,
                'backfill_attempted_frame_count' => $currentShm->backfillAttemptedFrameCount,
                'salt1' => $currentSalt[0],
                'salt2' => $currentSalt[1],
                'checkpoint_pinned_frame' => $currentPlan['checkpoint_pinned_frame'],
                'reset_blocked' => $currentPlan['reset_blocked'],
                'read_locks' => $currentPlan['read_locks'],
            ],
            'next_shm_source' => [
                'checkpoint_pinned_frame' => $nextPlan['checkpoint_pinned_frame'],
                'reset_blocked' => $nextPlan['reset_blocked'],
                'read_locks' => $nextPlan['read_locks'],
            ],
            'all_released_shm_source' => [
                'checkpoint_pinned_frame' => $allReleasedPlan['checkpoint_pinned_frame'],
                'reset_blocked' => $allReleasedPlan['reset_blocked'],
                'read_locks' => $allReleasedPlan['read_locks'],
            ],
            'restart' => $restart,
            'truncate' => $truncate,
            'restart_released_source' => $restart['released_source'],
            'truncate_released_source' => $truncate['released_source'],
            'current_sources' => $restart['current_sources'],
            'pinned_next_sources' => $restart['pinned_next_sources'],
            'restart_released_sources' => $restart['released_next_sources'],
            'truncate_released_sources' => $truncate['released_next_sources'],
            'restart_source_transitions' => array_column($restartRows, 'source_transition'),
            'truncate_source_transitions' => array_column($truncateRows, 'source_transition'),
            'restart_final_wal_generation' => [
                'action' => $restart['released_wal_action'],
                'wal_bytes_length' => $restart['released_source']['wal_bytes_length'],
                'checkpoint_sequence' => $restart['released_source']['checkpoint_sequence'],
                'salt1' => $restart['released_source']['salt1'],
            ],
            'truncate_final_wal_generation' => [
                'action' => $truncate['released_wal_action'],
                'wal_bytes_length' => $truncate['released_source']['wal_bytes_length'],
                'checkpoint_sequence' => $truncate['released_source']['checkpoint_sequence'],
                'salt1' => $truncate['released_source']['salt1'],
            ],
            'restart_truncate_released_database_match' => $restart['released_next_images'] === $truncate['released_next_images'],
            'current_reader_preserves_sidecar_source' => in_array('wal', $restart['current_sources'], true),
            'pinned_reader_blocks_restart_reset' => $restart['pinned_checkpoint_busy'] && $restart['pinned_wal_action'] === 'preserve_wal',
            'pinned_reader_blocks_truncate_reset' => $truncate['pinned_checkpoint_busy'] && $truncate['pinned_wal_action'] === 'preserve_wal',
            'reader_release_unblocks_restart' => $restart['reader_release_unblocked_checkpoint'],
            'reader_release_unblocks_truncate' => $truncate['reader_release_unblocked_checkpoint'],
            'restart_released_uses_checkpoint_database' => $restart['released_reader_uses_checkpoint_database'],
            'truncate_released_uses_checkpoint_database' => $truncate['released_reader_uses_checkpoint_database'],
            'rolled_back_page_numbers' => $restart['rolled_back_page_numbers'],
            'rolled_back_frame_indexes' => $restart['rolled_back_frame_indexes'],
            'commit_frame_indexes' => $restart['commit_frame_indexes'],
            'frame_source_rows' => $restart['frame_source_rows'],
            'source_digest' => hash('sha256', implode('|', array_merge(
                array_column($restartRows, 'source_transition'),
                array_column($truncateRows, 'source_transition')
            ))),
            'yield_count' => $restart['yield_count'] + $truncate['yield_count'] + count($pageNumbers),
            'dependencies' => array_values(array_unique(array_merge(
                $restart['dependencies'],
                $truncate['dependencies'],
                $currentPlan['dependencies'],
                $nextPlan['dependencies'],
                $allReleasedPlan['dependencies'],
                ['sqlite-wal-restart-truncate-savepoint-reader-current-source-next105']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function readerCheckpointSavepointCurrentSourceNext(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        SQLiteShmIndex $activeReaderShm,
        SQLiteShmIndex $releasedReaderShm,
        array $pageNumbers,
        string $mode = 'restart'
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint current-source next139 requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint current-source next139 requires restart or truncate mode');
        }

        self::assertCurrentWalSource($wal, $walBytes);
        self::assertShmMatchesWal($activeReaderShm, $wal, 'active reader');
        self::assertShmMatchesWal($releasedReaderShm, $wal, 'released reader');

        $activePlan = $activeReaderShm->checkpointPlan();
        $releasedPlan = $releasedReaderShm->checkpointPlan();
        $activeReaderEndFrame = $activePlan['checkpoint_pinned_frame'];
        if (!is_int($activeReaderEndFrame) || $activeReaderEndFrame < 1) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint current-source next139 requires an active reader pin');
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $writerReaderEndFrame = min($activeReaderEndFrame, $retainedWal->frameCount());
        $activeCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, $mode, $writerReaderEndFrame);
        $releasedCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, $mode);
        $activeNextWal = $activeCheckpoint['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($activeCheckpoint['wal_bytes'], $wal->header->pageSize, true);
        $releasedNextWal = $releasedCheckpoint['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($releasedCheckpoint['wal_bytes'], $wal->header->pageSize, true);
        $activeNextEndFrame = $activeNextWal?->frameCount() ?? 0;
        $releasedNextEndFrame = $releasedNextWal?->frameCount() ?? 0;

        $rows = [];
        $activeReader = [];
        $writerCurrent = [];
        $activeNext = [];
        $releasedNext = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL reader checkpoint savepoint current-source next139 pages must be integers');
            }

            $active = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $activeReaderEndFrame);
            $current = $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $writerReaderEndFrame);
            $blockedNext = $activeNextWal === null
                ? self::databasePageVisibility($activeCheckpoint['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $activeNextWal->readerSnapshotPageImage($activeCheckpoint['database_bytes'], $pageNumber, $activeNextEndFrame);
            $next = $releasedNextWal === null
                ? self::databasePageVisibility($releasedCheckpoint['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $releasedNextWal->readerSnapshotPageImage($releasedCheckpoint['database_bytes'], $pageNumber, $releasedNextEndFrame);

            $activeReader[] = $active;
            $writerCurrent[] = $current;
            $activeNext[] = $blockedNext;
            $releasedNext[] = $next;
            $rows[] = [
                'page_number' => $pageNumber,
                'active_reader_source' => (string) $active['source'],
                'writer_current_source' => (string) $current['source'],
                'active_next_source' => (string) $blockedNext['source'],
                'released_next_source' => (string) $next['source'],
                'active_reader_frame' => $active['frame_index'] ?? null,
                'writer_current_frame' => $current['frame_index'] ?? null,
                'active_next_frame' => $blockedNext['frame_index'] ?? null,
                'released_next_frame' => $next['frame_index'] ?? null,
                'active_reader_label' => rtrim(substr((string) $active['image'], 0, 72), ".\0"),
                'writer_current_label' => rtrim(substr((string) $current['image'], 0, 72), ".\0"),
                'released_next_label' => rtrim(substr((string) $next['image'], 0, 72), ".\0"),
                'reader_held_rolled_back_frame' => ($active['frame_index'] ?? 0) > $retainedWal->frameCount(),
                'writer_rolled_back_reader_image' => $active['image'] !== $current['image'],
                'released_next_matches_writer_current' => $next['image'] === $current['image'],
                'source_transition' => $active['source'] . '>' . $current['source'] . '>' . $blockedNext['source'] . '>' . $next['source'],
            ];
        }

        $activeSources = self::visibilityColumn($activeReader, 'source');
        $writerSources = self::visibilityColumn($writerCurrent, 'source');
        $activeNextSources = self::visibilityColumn($activeNext, 'source');
        $releasedNextSources = self::visibilityColumn($releasedNext, 'source');
        $writerImages = self::visibilityColumn($writerCurrent, 'image');
        $releasedImages = self::visibilityColumn($releasedNext, 'image');

        return [
            'status' => $activeCheckpoint['busy']
                && !$releasedCheckpoint['busy']
                && $activePlan['reset_blocked']
                && !$releasedPlan['reset_blocked']
                    ? 'reader-checkpoint-savepoint-current-source-next139'
                    : 'reader-checkpoint-savepoint-current-source-next139-incomplete',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'current_source_verified' => true,
            'shm_source_verified' => true,
            'active_reader_end_frame' => $activeReaderEndFrame,
            'writer_current_reader_end_frame' => $writerReaderEndFrame,
            'active_next_reader_end_frame' => $activeNextEndFrame,
            'released_next_reader_end_frame' => $releasedNextEndFrame,
            'retained_frame_count' => $retainedWal->frameCount(),
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'rolled_back_frame_indexes' => array_map(static fn (array $frame): int => $frame['frame_index'], $rollback['discarded_wal_frames']),
            'rolled_back_page_numbers' => self::discardedPageNumbers($rollback['discarded_wal_frames']),
            'current_source' => self::walSourceSummary($wal, strlen($walBytes)),
            'retained_source' => self::walSourceSummary($retainedWal, strlen($retainedWalBytes)),
            'active_shm_source' => self::shmSourceSummary($activeReaderShm, $activePlan),
            'released_shm_source' => self::shmSourceSummary($releasedReaderShm, $releasedPlan),
            'active_checkpoint' => $activeCheckpoint,
            'released_checkpoint' => $releasedCheckpoint,
            'active_wal_action' => $activeCheckpoint['wal_action'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'active_checkpoint_busy' => $activeCheckpoint['busy'],
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'active_checkpoint_reason' => $activeCheckpoint['reason'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'active_reader' => $activeReader,
            'writer_current_reader' => $writerCurrent,
            'active_next_reader' => $activeNext,
            'released_next_reader' => $releasedNext,
            'current_source_rows' => $rows,
            'active_reader_sources' => $activeSources,
            'writer_current_sources' => $writerSources,
            'active_next_sources' => $activeNextSources,
            'released_next_sources' => $releasedNextSources,
            'active_reader_frame_indexes' => self::visibilityColumn($activeReader, 'frame_index'),
            'writer_current_frame_indexes' => self::visibilityColumn($writerCurrent, 'frame_index'),
            'active_next_frame_indexes' => self::visibilityColumn($activeNext, 'frame_index'),
            'released_next_frame_indexes' => self::visibilityColumn($releasedNext, 'frame_index'),
            'source_transitions' => array_column($rows, 'source_transition'),
            'active_reader_keeps_original_wal' => in_array(true, array_column($rows, 'reader_held_rolled_back_frame'), true),
            'writer_current_uses_retained_prefix' => in_array('wal', $writerSources, true) && $writerReaderEndFrame === $retainedWal->frameCount(),
            'active_reader_blocks_checkpoint_reset' => $activeCheckpoint['busy'] && $activeCheckpoint['wal_action'] === 'preserve_wal',
            'reader_release_unblocks_checkpoint' => !$releasedCheckpoint['busy'],
            'released_next_uses_checkpoint_database' => !in_array('wal', $releasedNextSources, true),
            'released_next_matches_writer_current' => $releasedImages === $writerImages,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'yield_count' => (4 * count($pageNumbers)) + count($rollback['discarded_wal_frames']),
            'dependencies' => array_values(array_unique(array_merge(
                $activeCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                $activePlan['dependencies'],
                $releasedPlan['dependencies'],
                ['sqlite-wal-reader-checkpoint-savepoint-current-source-next139']
            ))),
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $nextTransactions
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function readerCheckpointTruncateSavepointCurrentSourceNext(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        SQLiteShmIndex $activeReaderShm,
        SQLiteShmIndex $releasedReaderShm,
        string $databasePath,
        array $nextTransactions,
        array $pageNumbers
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint truncate savepoint current-source next142 requires a savepoint name');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint truncate savepoint current-source next142 requires database bytes');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint truncate savepoint current-source next142 requires a database path');
        }
        if ($nextTransactions === []) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint truncate savepoint current-source next142 requires next transactions');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint truncate savepoint current-source next142 requires page numbers');
        }

        self::assertCurrentWalSource($wal, $walBytes);
        self::assertShmMatchesWal($activeReaderShm, $wal, 'active reader');
        self::assertShmMatchesWal($releasedReaderShm, $wal, 'released reader');

        $activePlan = $activeReaderShm->checkpointPlan();
        $releasedPlan = $releasedReaderShm->checkpointPlan();
        $activeReaderEndFrame = $activePlan['checkpoint_pinned_frame'];
        if (!is_int($activeReaderEndFrame) || $activeReaderEndFrame < 1) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint truncate savepoint current-source next142 requires an active reader pin');
        }
        if ($releasedPlan['reset_blocked']) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint truncate savepoint current-source next142 requires a released-reader SHM image');
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $writerCurrentEndFrame = min($activeReaderEndFrame, $retainedWal->frameCount());
        $activeCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, 'truncate', $writerCurrentEndFrame);
        $releasedCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, 'truncate');
        if (($releasedCheckpoint['wal_action'] ?? null) !== 'truncate_wal' || ($releasedCheckpoint['wal_bytes'] ?? null) !== '') {
            throw new \RuntimeException('SQLite WAL reader checkpoint truncate savepoint current-source next142 requires a released truncate checkpoint');
        }

        $freshWal = self::freshWalAfterReleasedCheckpoint($retainedWal, $releasedCheckpoint);
        $append = SQLiteWalAppendPlan::appendTransactions($freshWal, $databasePath, $nextTransactions);
        $nextWal = SQLiteWal::parse((string) $append['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame = $append['last_commit_frame'] ?? $nextWal->frameCount();

        $rows = [];
        $activeReader = [];
        $writerCurrent = [];
        $releasedNext = [];
        $appendedNext = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL reader checkpoint truncate savepoint current-source next142 pages must be one-based integers');
            }

            $active = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $activeReaderEndFrame);
            $current = $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $writerCurrentEndFrame);
            $released = self::databasePageVisibility($releasedCheckpoint['database_bytes'], $wal->header->pageSize, $pageNumber);
            $next = $nextWal->readerSnapshotPageImage($releasedCheckpoint['database_bytes'], $pageNumber, $nextReaderEndFrame);

            $activeReader[] = $active;
            $writerCurrent[] = $current;
            $releasedNext[] = $released;
            $appendedNext[] = $next;
            $rows[] = [
                'page_number' => $pageNumber,
                'active_reader_source' => (string) $active['source'],
                'writer_current_source' => (string) $current['source'],
                'released_truncate_source' => (string) $released['source'],
                'appended_next_source' => (string) $next['source'],
                'active_reader_frame' => $active['frame_index'] ?? null,
                'writer_current_frame' => $current['frame_index'] ?? null,
                'released_truncate_frame' => $released['frame_index'] ?? null,
                'appended_next_frame' => $next['frame_index'] ?? null,
                'active_reader_label' => rtrim(substr((string) $active['image'], 0, 72), ".\0"),
                'writer_current_label' => rtrim(substr((string) $current['image'], 0, 72), ".\0"),
                'released_truncate_label' => rtrim(substr((string) $released['image'], 0, 72), ".\0"),
                'appended_next_label' => rtrim(substr((string) $next['image'], 0, 72), ".\0"),
                'active_reader_held_rolled_back_frame' => ($active['frame_index'] ?? 0) > $retainedWal->frameCount(),
                'writer_rolled_back_reader_image' => $active['image'] !== $current['image'],
                'released_matches_writer_current' => $released['image'] === $current['image'],
                'appended_next_matches_writer_current' => $next['image'] === $current['image'],
                'source_transition' => $active['source'] . '>' . $current['source'] . '>' . $released['source'] . '>' . $next['source'],
            ];
        }

        $activeSources = self::visibilityColumn($activeReader, 'source');
        $writerSources = self::visibilityColumn($writerCurrent, 'source');
        $releasedSources = self::visibilityColumn($releasedNext, 'source');
        $nextSources = self::visibilityColumn($appendedNext, 'source');
        $writerImages = self::visibilityColumn($writerCurrent, 'image');
        $releasedImages = self::visibilityColumn($releasedNext, 'image');

        return [
            'status' => $activeCheckpoint['busy']
                && !$releasedCheckpoint['busy']
                && $activeCheckpoint['wal_action'] === 'preserve_wal'
                && $releasedCheckpoint['wal_action'] === 'truncate_wal'
                    ? 'reader-checkpoint-truncate-savepoint-current-source-next142'
                    : 'reader-checkpoint-truncate-savepoint-current-source-next142-incomplete',
            'reason' => 'active_current_source_reader_pins_savepoint_truncate_until_release_then_next_writer_uses_fresh_wal_generation',
            'savepoint' => $savepoint,
            'mode' => 'truncate',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'current_source_verified' => true,
            'shm_source_verified' => true,
            'active_reader_end_frame' => $activeReaderEndFrame,
            'writer_current_reader_end_frame' => $writerCurrentEndFrame,
            'released_next_reader_end_frame' => 0,
            'appended_next_reader_end_frame' => $nextReaderEndFrame,
            'retained_frame_count' => $retainedWal->frameCount(),
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'rolled_back_frame_indexes' => array_map(static fn (array $frame): int => $frame['frame_index'], $rollback['discarded_wal_frames']),
            'rolled_back_page_numbers' => self::discardedPageNumbers($rollback['discarded_wal_frames']),
            'active_checkpoint_busy' => $activeCheckpoint['busy'],
            'active_checkpoint_reason' => $activeCheckpoint['reason'],
            'active_wal_action' => $activeCheckpoint['wal_action'],
            'active_wal_bytes_length' => strlen((string) $activeCheckpoint['wal_bytes']),
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'released_database_sha256' => hash('sha256', (string) $releasedCheckpoint['database_bytes']),
            'fresh_wal_checkpoint_sequence' => $freshWal->header->checkpointSequence,
            'fresh_wal_salt' => [$freshWal->header->salt1, $freshWal->header->salt2],
            'append_start_frame' => $append['start_frame'],
            'append_end_frame' => $append['end_frame'],
            'append_frame_count' => $append['appended_frame_count'],
            'append_last_commit_frame' => $append['last_commit_frame'],
            'append_wal_bytes_length' => $append['wal_bytes_length'],
            'active_reader_sources' => $activeSources,
            'writer_current_sources' => $writerSources,
            'released_truncate_sources' => $releasedSources,
            'appended_next_sources' => $nextSources,
            'active_reader_frame_indexes' => self::visibilityColumn($activeReader, 'frame_index'),
            'writer_current_frame_indexes' => self::visibilityColumn($writerCurrent, 'frame_index'),
            'released_truncate_frame_indexes' => self::visibilityColumn($releasedNext, 'frame_index'),
            'appended_next_frame_indexes' => self::visibilityColumn($appendedNext, 'frame_index'),
            'current_source_rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'active_reader_keeps_original_wal' => in_array(true, array_column($rows, 'active_reader_held_rolled_back_frame'), true),
            'writer_current_uses_retained_prefix' => in_array('wal', $writerSources, true) && $writerCurrentEndFrame === $retainedWal->frameCount(),
            'active_reader_blocks_truncate_reset' => $activeCheckpoint['busy'] && $activeCheckpoint['wal_action'] === 'preserve_wal',
            'reader_release_unblocks_truncate' => !$releasedCheckpoint['busy'],
            'released_truncate_removes_wal' => $releasedCheckpoint['wal_action'] === 'truncate_wal' && $releasedCheckpoint['wal_bytes'] === '',
            'released_next_uses_checkpoint_database' => !in_array('wal', $releasedSources, true),
            'released_next_matches_writer_current' => $releasedImages === $writerImages,
            'appended_next_uses_fresh_generation' => $freshWal->frameCount() === 0 && in_array('wal', $nextSources, true),
            'appended_next_separated_from_released_truncate' => in_array('wal', $nextSources, true) && $append['start_frame'] === 1,
            'append_operations' => $append['operations'],
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'yield_count' => (4 * count($pageNumbers)) + count($rollback['discarded_wal_frames']) + (int) $append['appended_frame_count'],
            'dependencies' => array_values(array_unique(array_merge(
                $activeCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                $activePlan['dependencies'],
                $releasedPlan['dependencies'],
                $append['dependencies'],
                [
                    'sqlite-wal-reader-checkpoint-truncate-savepoint-current-source-next142',
                    'sqlite-wal-savepoint-current-prefix',
                    'sqlite-wal-truncate-fresh-generation',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native WAL parser/checkpoint/savepoint, SHM read-mark, and WAL append planning helpers',
            'non_overlap' => 'avoids accepted next134 truncate-reader fresh generation and next139 savepoint-reader restart/truncate by requiring an active current-source reader over rolled-back savepoint frames, a released truncate checkpoint, and a new writer append on the fresh truncated WAL generation',
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $nextTransactions
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function readerCheckpointRestartSavepointCurrentSourceNext(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        SQLiteShmIndex $activeReaderShm,
        SQLiteShmIndex $releasedReaderShm,
        string $databasePath,
        array $nextTransactions,
        array $pageNumbers
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next145 requires a savepoint name');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next145 requires database bytes');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next145 requires a database path');
        }
        if ($nextTransactions === []) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next145 requires next transactions');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next145 requires page numbers');
        }

        self::assertCurrentWalSource($wal, $walBytes);
        self::assertShmMatchesWal($activeReaderShm, $wal, 'active reader');
        self::assertShmMatchesWal($releasedReaderShm, $wal, 'released reader');

        $activePlan = $activeReaderShm->checkpointPlan();
        $releasedPlan = $releasedReaderShm->checkpointPlan();
        $activeReaderEndFrame = $activePlan['checkpoint_pinned_frame'];
        if (!is_int($activeReaderEndFrame) || $activeReaderEndFrame < 1) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next145 requires an active reader pin');
        }
        if ($releasedPlan['reset_blocked']) {
            throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next145 requires a released-reader SHM image');
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $writerCurrentEndFrame = min($activeReaderEndFrame, $retainedWal->frameCount());
        $activeCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, 'restart', $writerCurrentEndFrame);
        $releasedCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, 'restart');
        if (($releasedCheckpoint['wal_action'] ?? null) !== 'restart_wal' || ($releasedCheckpoint['wal_bytes'] ?? '') === '') {
            throw new \RuntimeException('SQLite WAL reader checkpoint restart savepoint current-source next145 requires a released restart checkpoint');
        }

        $freshWal = self::freshWalAfterReleasedCheckpoint($retainedWal, $releasedCheckpoint);
        $append = SQLiteWalAppendPlan::appendTransactions($freshWal, $databasePath, $nextTransactions);
        $nextWal = SQLiteWal::parse((string) $append['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame = $append['last_commit_frame'] ?? $nextWal->frameCount();

        $rows = [];
        $activeReader = [];
        $writerCurrent = [];
        $releasedRestart = [];
        $appendedNext = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL reader checkpoint restart savepoint current-source next145 pages must be one-based integers');
            }

            $active = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $activeReaderEndFrame);
            $current = $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $writerCurrentEndFrame);
            $released = $freshWal->readerSnapshotPageImage((string) $releasedCheckpoint['database_bytes'], $pageNumber);
            $next = $nextWal->readerSnapshotPageImage((string) $releasedCheckpoint['database_bytes'], $pageNumber, $nextReaderEndFrame);

            $activeReader[] = $active;
            $writerCurrent[] = $current;
            $releasedRestart[] = $released;
            $appendedNext[] = $next;
            $rows[] = [
                'page_number' => $pageNumber,
                'active_reader_source' => (string) $active['source'],
                'writer_current_source' => (string) $current['source'],
                'released_restart_source' => (string) $released['source'],
                'appended_next_source' => (string) $next['source'],
                'active_reader_frame' => $active['frame_index'] ?? null,
                'writer_current_frame' => $current['frame_index'] ?? null,
                'released_restart_frame' => $released['frame_index'] ?? null,
                'appended_next_frame' => $next['frame_index'] ?? null,
                'active_reader_label' => rtrim(substr((string) $active['image'], 0, 72), ".\0"),
                'writer_current_label' => rtrim(substr((string) $current['image'], 0, 72), ".\0"),
                'released_restart_label' => rtrim(substr((string) $released['image'], 0, 72), ".\0"),
                'appended_next_label' => rtrim(substr((string) $next['image'], 0, 72), ".\0"),
                'active_reader_held_rolled_back_frame' => ($active['frame_index'] ?? 0) > $retainedWal->frameCount(),
                'writer_rolled_back_reader_image' => $active['image'] !== $current['image'],
                'released_matches_writer_current' => $released['image'] === $current['image'],
                'appended_next_matches_writer_current' => $next['image'] === $current['image'],
                'source_transition' => $active['source'] . '>' . $current['source'] . '>' . $released['source'] . '>' . $next['source'],
            ];
        }

        $activeSources = self::visibilityColumn($activeReader, 'source');
        $writerSources = self::visibilityColumn($writerCurrent, 'source');
        $releasedSources = self::visibilityColumn($releasedRestart, 'source');
        $nextSources = self::visibilityColumn($appendedNext, 'source');
        $writerImages = self::visibilityColumn($writerCurrent, 'image');
        $releasedImages = self::visibilityColumn($releasedRestart, 'image');

        return [
            'status' => $activeCheckpoint['busy']
                && !$releasedCheckpoint['busy']
                && $activeCheckpoint['wal_action'] === 'preserve_wal'
                && $releasedCheckpoint['wal_action'] === 'restart_wal'
                    ? 'reader-checkpoint-restart-savepoint-current-source-next145'
                    : 'reader-checkpoint-restart-savepoint-current-source-next145-incomplete',
            'reason' => 'active_current_source_reader_pins_savepoint_restart_until_release_then_next_writer_appends_to_restarted_generation',
            'savepoint' => $savepoint,
            'mode' => 'restart',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'current_source_verified' => true,
            'shm_source_verified' => true,
            'active_reader_end_frame' => $activeReaderEndFrame,
            'writer_current_reader_end_frame' => $writerCurrentEndFrame,
            'released_restart_reader_end_frame' => 0,
            'appended_next_reader_end_frame' => $nextReaderEndFrame,
            'retained_frame_count' => $retainedWal->frameCount(),
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'rolled_back_frame_indexes' => array_map(static fn (array $frame): int => $frame['frame_index'], $rollback['discarded_wal_frames']),
            'rolled_back_page_numbers' => self::discardedPageNumbers($rollback['discarded_wal_frames']),
            'active_checkpoint_busy' => $activeCheckpoint['busy'],
            'active_checkpoint_reason' => $activeCheckpoint['reason'],
            'active_wal_action' => $activeCheckpoint['wal_action'],
            'active_wal_bytes_length' => strlen((string) $activeCheckpoint['wal_bytes']),
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'released_database_sha256' => hash('sha256', (string) $releasedCheckpoint['database_bytes']),
            'fresh_wal_checkpoint_sequence' => $freshWal->header->checkpointSequence,
            'fresh_wal_salt' => [$freshWal->header->salt1, $freshWal->header->salt2],
            'append_start_frame' => $append['start_frame'],
            'append_end_frame' => $append['end_frame'],
            'append_frame_count' => $append['appended_frame_count'],
            'append_last_commit_frame' => $append['last_commit_frame'],
            'append_wal_bytes_length' => $append['wal_bytes_length'],
            'active_reader_sources' => $activeSources,
            'writer_current_sources' => $writerSources,
            'released_restart_sources' => $releasedSources,
            'appended_next_sources' => $nextSources,
            'active_reader_frame_indexes' => self::visibilityColumn($activeReader, 'frame_index'),
            'writer_current_frame_indexes' => self::visibilityColumn($writerCurrent, 'frame_index'),
            'released_restart_frame_indexes' => self::visibilityColumn($releasedRestart, 'frame_index'),
            'appended_next_frame_indexes' => self::visibilityColumn($appendedNext, 'frame_index'),
            'current_source_rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'active_reader_keeps_original_wal' => in_array(true, array_column($rows, 'active_reader_held_rolled_back_frame'), true),
            'writer_current_uses_retained_prefix' => in_array('wal', $writerSources, true) && $writerCurrentEndFrame === $retainedWal->frameCount(),
            'active_reader_blocks_restart_reset' => $activeCheckpoint['busy'] && $activeCheckpoint['wal_action'] === 'preserve_wal',
            'reader_release_unblocks_restart' => !$releasedCheckpoint['busy'],
            'released_restart_keeps_header' => $releasedCheckpoint['wal_action'] === 'restart_wal' && strlen((string) $releasedCheckpoint['wal_bytes']) === 32,
            'released_next_uses_checkpoint_database' => !in_array('wal', $releasedSources, true),
            'released_next_matches_writer_current' => $releasedImages === $writerImages,
            'appended_next_uses_restarted_generation' => $freshWal->frameCount() === 0 && in_array('wal', $nextSources, true),
            'appended_next_separated_from_released_restart' => in_array('wal', $nextSources, true) && $append['start_frame'] === 1,
            'append_operations' => $append['operations'],
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'yield_count' => (4 * count($pageNumbers)) + count($rollback['discarded_wal_frames']) + (int) $append['appended_frame_count'],
            'dependencies' => array_values(array_unique(array_merge(
                $activeCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                $activePlan['dependencies'],
                $releasedPlan['dependencies'],
                $append['dependencies'],
                [
                    'sqlite-wal-reader-checkpoint-restart-savepoint-current-source-next145',
                    'sqlite-wal-savepoint-current-prefix',
                    'sqlite-wal-restart-fresh-generation',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native WAL parser/checkpoint/savepoint, SHM read-mark, and WAL append planning helpers',
            'non_overlap' => 'avoids accepted next127 reader restart/savepoint without SHM release validation and next142 truncate reset by requiring active and released current-source SHM images around a RESTART checkpoint before appending the retry generation',
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function readerCheckpointTruncateReaderRestartCurrentSourceNext(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $readerWalBytes,
        string $databaseBytes,
        SQLiteShmIndex $activeReaderShm,
        SQLiteShmIndex $releasedReaderShm,
        string $databasePath,
        array $pageNumbers
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader restart current-source next146 requires a savepoint name');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader restart current-source next146 requires database bytes');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader restart current-source next146 requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader restart current-source next146 requires page numbers');
        }

        self::assertCurrentWalSource($wal, $walBytes);
        if ($readerWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader restart current-source next146 requires reader WAL bytes');
        }

        $readerSourceMatchesCurrent = hash_equals(hash('sha256', $walBytes), hash('sha256', $readerWalBytes));
        if (!$readerSourceMatchesCurrent) {
            return [
                'status' => 'wal-checkpoint-truncate-reader-restart-current-source-blocked-next146',
                'reason' => 'reader_wal_source_mismatch_requires_reopen_before_restart_checkpoint',
                'savepoint' => $savepoint,
                'mode' => 'truncate',
                'database_path' => $databasePath,
                'wal_path' => $databasePath . '-wal',
                'current_source_verified' => true,
                'reader_source_matches_current' => false,
                'current_wal_sha256' => hash('sha256', $walBytes),
                'reader_wal_sha256' => hash('sha256', $readerWalBytes),
                'reader_restart_required' => true,
                'restart_reader_uses_fresh_current_source' => false,
                'dependencies' => [
                    'sqlite-wal-checkpoint-truncate-reader-restart-current-source-next146',
                    'sqlite-wal-current-source-reader-restart',
                ],
                'dependency_closure' => 'no new support component needed; stale reader source rejection uses existing WAL byte source validation',
                'non_overlap' => 'distinct from next142 append-after-truncate by checking the reopened reader current-source boundary before any new writer append',
            ];
        }

        self::assertShmMatchesWal($activeReaderShm, $wal, 'active reader');
        self::assertShmMatchesWal($releasedReaderShm, $wal, 'released reader');

        $activePlan = $activeReaderShm->checkpointPlan();
        $releasedPlan = $releasedReaderShm->checkpointPlan();
        $activeReaderEndFrame = $activePlan['checkpoint_pinned_frame'];
        if (!is_int($activeReaderEndFrame) || $activeReaderEndFrame < 1) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader restart current-source next146 requires an active reader pin');
        }
        if ($releasedPlan['reset_blocked']) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader restart current-source next146 requires a released-reader SHM image');
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $writerCurrentEndFrame = min($activeReaderEndFrame, $retainedWal->frameCount());
        $activeCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, 'truncate', $writerCurrentEndFrame);
        $releasedCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, 'truncate');
        if (($releasedCheckpoint['wal_action'] ?? null) !== 'truncate_wal' || ($releasedCheckpoint['wal_bytes'] ?? null) !== '') {
            throw new \RuntimeException('SQLite WAL checkpoint truncate reader restart current-source next146 requires a released truncate checkpoint');
        }

        $freshWal = self::freshWalAfterReleasedCheckpoint($retainedWal, $releasedCheckpoint);
        $restartReaderEndFrame = 0;
        $rows = [];
        $activeReader = [];
        $writerCurrent = [];
        $releasedReader = [];
        $restartReader = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint truncate reader restart current-source next146 pages must be one-based integers');
            }

            $active = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $activeReaderEndFrame);
            $current = $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $writerCurrentEndFrame);
            $released = self::databasePageVisibility($releasedCheckpoint['database_bytes'], $wal->header->pageSize, $pageNumber);
            $restart = self::databasePageVisibility($releasedCheckpoint['database_bytes'], $wal->header->pageSize, $pageNumber);

            $activeReader[] = $active;
            $writerCurrent[] = $current;
            $releasedReader[] = $released;
            $restartReader[] = $restart;
            $rows[] = [
                'page_number' => $pageNumber,
                'active_reader_source' => (string) $active['source'],
                'writer_current_source' => (string) $current['source'],
                'released_truncate_source' => (string) $released['source'],
                'restart_reader_source' => (string) $restart['source'],
                'active_reader_frame' => $active['frame_index'] ?? null,
                'writer_current_frame' => $current['frame_index'] ?? null,
                'released_truncate_frame' => $released['frame_index'] ?? null,
                'restart_reader_frame' => $restart['frame_index'] ?? null,
                'active_reader_label' => rtrim(substr((string) $active['image'], 0, 72), ".\0"),
                'writer_current_label' => rtrim(substr((string) $current['image'], 0, 72), ".\0"),
                'restart_reader_label' => rtrim(substr((string) $restart['image'], 0, 72), ".\0"),
                'active_reader_held_rolled_back_frame' => ($active['frame_index'] ?? 0) > $retainedWal->frameCount(),
                'writer_rolled_back_reader_image' => $active['image'] !== $current['image'],
                'restart_matches_released_truncate' => $restart['image'] === $released['image'],
                'restart_matches_writer_current' => $restart['image'] === $current['image'],
                'source_transition' => $active['source'] . '>' . $current['source'] . '>' . $released['source'] . '>' . $restart['source'],
            ];
        }

        $activeSources = self::visibilityColumn($activeReader, 'source');
        $writerSources = self::visibilityColumn($writerCurrent, 'source');
        $releasedSources = self::visibilityColumn($releasedReader, 'source');
        $restartSources = self::visibilityColumn($restartReader, 'source');
        $writerImages = self::visibilityColumn($writerCurrent, 'image');
        $restartImages = self::visibilityColumn($restartReader, 'image');

        return [
            'status' => $activeCheckpoint['busy']
                && !$releasedCheckpoint['busy']
                && $activeCheckpoint['wal_action'] === 'preserve_wal'
                && $releasedCheckpoint['wal_action'] === 'truncate_wal'
                && $freshWal->frameCount() === 0
                    ? 'wal-checkpoint-truncate-reader-restart-current-source-next146'
                    : 'wal-checkpoint-truncate-reader-restart-current-source-next146-incomplete',
            'reason' => 'released_truncate_checkpoint_restarts_reader_on_fresh_current_source_before_next_writer_append',
            'savepoint' => $savepoint,
            'mode' => 'truncate',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'current_source_verified' => true,
            'shm_source_verified' => true,
            'reader_source_matches_current' => true,
            'current_wal_sha256' => hash('sha256', $walBytes),
            'reader_wal_sha256' => hash('sha256', $readerWalBytes),
            'active_reader_end_frame' => $activeReaderEndFrame,
            'writer_current_reader_end_frame' => $writerCurrentEndFrame,
            'restart_reader_end_frame' => $restartReaderEndFrame,
            'retained_frame_count' => $retainedWal->frameCount(),
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'rolled_back_frame_indexes' => array_map(static fn (array $frame): int => $frame['frame_index'], $rollback['discarded_wal_frames']),
            'rolled_back_page_numbers' => self::discardedPageNumbers($rollback['discarded_wal_frames']),
            'active_checkpoint_busy' => $activeCheckpoint['busy'],
            'active_checkpoint_reason' => $activeCheckpoint['reason'],
            'active_wal_action' => $activeCheckpoint['wal_action'],
            'active_wal_bytes_length' => strlen((string) $activeCheckpoint['wal_bytes']),
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'released_database_sha256' => hash('sha256', (string) $releasedCheckpoint['database_bytes']),
            'fresh_wal_frame_count' => $freshWal->frameCount(),
            'fresh_wal_bytes_length' => 32,
            'fresh_wal_checkpoint_sequence' => $freshWal->header->checkpointSequence,
            'fresh_wal_salt' => [$freshWal->header->salt1, $freshWal->header->salt2],
            'active_reader_sources' => $activeSources,
            'writer_current_sources' => $writerSources,
            'released_truncate_sources' => $releasedSources,
            'restart_reader_sources' => $restartSources,
            'active_reader_frame_indexes' => self::visibilityColumn($activeReader, 'frame_index'),
            'writer_current_frame_indexes' => self::visibilityColumn($writerCurrent, 'frame_index'),
            'released_truncate_frame_indexes' => self::visibilityColumn($releasedReader, 'frame_index'),
            'restart_reader_frame_indexes' => self::visibilityColumn($restartReader, 'frame_index'),
            'current_source_rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'active_reader_keeps_original_wal' => in_array(true, array_column($rows, 'active_reader_held_rolled_back_frame'), true),
            'writer_current_uses_retained_prefix' => in_array('wal', $writerSources, true) && $writerCurrentEndFrame === $retainedWal->frameCount(),
            'active_reader_blocks_truncate_reset' => $activeCheckpoint['busy'] && $activeCheckpoint['wal_action'] === 'preserve_wal',
            'reader_release_unblocks_truncate' => !$releasedCheckpoint['busy'],
            'released_truncate_removes_wal' => $releasedCheckpoint['wal_action'] === 'truncate_wal' && $releasedCheckpoint['wal_bytes'] === '',
            'restart_reader_uses_fresh_current_source' => $freshWal->frameCount() === 0 && !in_array('wal', $restartSources, true),
            'restart_reader_separated_from_stale_source' => $freshWal->header->checkpointSequence !== $wal->header->checkpointSequence
                && $freshWal->header->salt1 !== $wal->header->salt1,
            'restart_matches_writer_current' => $restartImages === $writerImages,
            'reader_restart_required' => true,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'yield_count' => (4 * count($pageNumbers)) + count($rollback['discarded_wal_frames']) + 1,
            'dependencies' => array_values(array_unique(array_merge(
                $activeCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                $activePlan['dependencies'],
                $releasedPlan['dependencies'],
                [
                    'sqlite-wal-checkpoint-truncate-reader-restart-current-source-next146',
                    'sqlite-wal-current-source-reader-restart',
                    'sqlite-wal-truncate-fresh-generation',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native WAL parser/checkpoint/savepoint, SHM read-mark, and fresh WAL header helpers',
            'non_overlap' => 'avoids accepted next142 append-after-truncate and batch141 reader checkpoint truncate behavior by proving the reopened reader binds to the fresh current source at frame 0 before any new WAL append',
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function readerCheckpointSavepointReaderRestartCurrentSourceNext(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $readerWalBytes,
        string $databaseBytes,
        SQLiteShmIndex $activeReaderShm,
        SQLiteShmIndex $releasedReaderShm,
        string $databasePath,
        array $pageNumbers
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint reader restart current-source next151 requires a savepoint name');
        }
        if ($readerWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint reader restart current-source next151 requires reader WAL bytes');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint reader restart current-source next151 requires database bytes');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint reader restart current-source next151 requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint reader restart current-source next151 requires page numbers');
        }

        self::assertCurrentWalSource($wal, $walBytes);
        $readerSourceMatchesCurrent = hash_equals(hash('sha256', $walBytes), hash('sha256', $readerWalBytes));
        if (!$readerSourceMatchesCurrent) {
            return [
                'status' => 'wal-checkpoint-savepoint-reader-restart-current-source-blocked-next151',
                'reason' => 'reader_wal_source_mismatch_requires_reopen_before_savepoint_restart_checkpoint',
                'savepoint' => $savepoint,
                'mode' => 'restart',
                'database_path' => $databasePath,
                'wal_path' => $databasePath . '-wal',
                'current_source_verified' => true,
                'reader_source_matches_current' => false,
                'current_wal_sha256' => hash('sha256', $walBytes),
                'reader_wal_sha256' => hash('sha256', $readerWalBytes),
                'reader_restart_required' => true,
                'restart_reader_uses_fresh_current_source' => false,
                'dependencies' => [
                    'sqlite-wal-checkpoint-savepoint-reader-restart-current-source-next151',
                    'sqlite-wal-current-source-reader-restart',
                ],
                'dependency_closure' => 'no new support component needed; stale reader source rejection uses existing WAL byte source validation',
                'non_overlap' => 'avoids accepted next145 append-after-restart and next146 truncate-reader restart by checking the reopened reader current-source boundary after RESTART checkpoint before any new writer append',
            ];
        }

        self::assertShmMatchesWal($activeReaderShm, $wal, 'active reader');
        self::assertShmMatchesWal($releasedReaderShm, $wal, 'released reader');

        $activePlan = $activeReaderShm->checkpointPlan();
        $releasedPlan = $releasedReaderShm->checkpointPlan();
        $activeReaderEndFrame = $activePlan['checkpoint_pinned_frame'];
        if (!is_int($activeReaderEndFrame) || $activeReaderEndFrame < 1) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint reader restart current-source next151 requires an active reader pin');
        }
        if ($releasedPlan['reset_blocked']) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint reader restart current-source next151 requires a released-reader SHM image');
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $wal->header->pageSize, true);
        $writerCurrentEndFrame = min($activeReaderEndFrame, $retainedWal->frameCount());
        $activeCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, 'restart', $writerCurrentEndFrame);
        $releasedCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, 'restart');
        if (($releasedCheckpoint['wal_action'] ?? null) !== 'restart_wal' || ($releasedCheckpoint['wal_bytes'] ?? '') === '') {
            throw new \RuntimeException('SQLite WAL checkpoint savepoint reader restart current-source next151 requires a released restart checkpoint');
        }

        $freshWal = self::freshWalAfterReleasedCheckpoint($retainedWal, $releasedCheckpoint);
        $restartReaderEndFrame = 0;
        $rows = [];
        $activeReader = [];
        $writerCurrent = [];
        $releasedRestart = [];
        $restartReader = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint reader restart current-source next151 pages must be one-based integers');
            }

            $active = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $activeReaderEndFrame);
            $current = $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $writerCurrentEndFrame);
            $released = $freshWal->readerSnapshotPageImage((string) $releasedCheckpoint['database_bytes'], $pageNumber);
            $restart = $freshWal->readerSnapshotPageImage((string) $releasedCheckpoint['database_bytes'], $pageNumber, $restartReaderEndFrame);

            $activeReader[] = $active;
            $writerCurrent[] = $current;
            $releasedRestart[] = $released;
            $restartReader[] = $restart;
            $rows[] = [
                'page_number' => $pageNumber,
                'active_reader_source' => (string) $active['source'],
                'writer_current_source' => (string) $current['source'],
                'released_restart_source' => (string) $released['source'],
                'restart_reader_source' => (string) $restart['source'],
                'active_reader_frame' => $active['frame_index'] ?? null,
                'writer_current_frame' => $current['frame_index'] ?? null,
                'released_restart_frame' => $released['frame_index'] ?? null,
                'restart_reader_frame' => $restart['frame_index'] ?? null,
                'active_reader_label' => rtrim(substr((string) $active['image'], 0, 72), ".\0"),
                'writer_current_label' => rtrim(substr((string) $current['image'], 0, 72), ".\0"),
                'restart_reader_label' => rtrim(substr((string) $restart['image'], 0, 72), ".\0"),
                'active_reader_held_rolled_back_frame' => ($active['frame_index'] ?? 0) > $retainedWal->frameCount(),
                'writer_rolled_back_reader_image' => $active['image'] !== $current['image'],
                'restart_matches_released_restart' => $restart['image'] === $released['image'],
                'restart_matches_writer_current' => $restart['image'] === $current['image'],
                'source_transition' => $active['source'] . '>' . $current['source'] . '>' . $released['source'] . '>' . $restart['source'],
            ];
        }

        $activeSources = self::visibilityColumn($activeReader, 'source');
        $writerSources = self::visibilityColumn($writerCurrent, 'source');
        $releasedSources = self::visibilityColumn($releasedRestart, 'source');
        $restartSources = self::visibilityColumn($restartReader, 'source');
        $writerImages = self::visibilityColumn($writerCurrent, 'image');
        $restartImages = self::visibilityColumn($restartReader, 'image');

        return [
            'status' => $activeCheckpoint['busy']
                && !$releasedCheckpoint['busy']
                && $activeCheckpoint['wal_action'] === 'preserve_wal'
                && $releasedCheckpoint['wal_action'] === 'restart_wal'
                && $freshWal->frameCount() === 0
                    ? 'wal-checkpoint-savepoint-reader-restart-current-source-next151'
                    : 'wal-checkpoint-savepoint-reader-restart-current-source-next151-incomplete',
            'reason' => 'released_restart_checkpoint_restarts_reader_on_fresh_current_source_before_next_writer_append',
            'savepoint' => $savepoint,
            'mode' => 'restart',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'current_source_verified' => true,
            'shm_source_verified' => true,
            'reader_source_matches_current' => true,
            'current_wal_sha256' => hash('sha256', $walBytes),
            'reader_wal_sha256' => hash('sha256', $readerWalBytes),
            'active_reader_end_frame' => $activeReaderEndFrame,
            'writer_current_reader_end_frame' => $writerCurrentEndFrame,
            'restart_reader_end_frame' => $restartReaderEndFrame,
            'retained_frame_count' => $retainedWal->frameCount(),
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'rolled_back_frame_indexes' => array_map(static fn (array $frame): int => $frame['frame_index'], $rollback['discarded_wal_frames']),
            'rolled_back_page_numbers' => self::discardedPageNumbers($rollback['discarded_wal_frames']),
            'active_checkpoint_busy' => $activeCheckpoint['busy'],
            'active_checkpoint_reason' => $activeCheckpoint['reason'],
            'active_wal_action' => $activeCheckpoint['wal_action'],
            'active_wal_bytes_length' => strlen((string) $activeCheckpoint['wal_bytes']),
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'released_database_sha256' => hash('sha256', (string) $releasedCheckpoint['database_bytes']),
            'fresh_wal_frame_count' => $freshWal->frameCount(),
            'fresh_wal_bytes_length' => 32,
            'fresh_wal_checkpoint_sequence' => $freshWal->header->checkpointSequence,
            'fresh_wal_salt' => [$freshWal->header->salt1, $freshWal->header->salt2],
            'active_reader_sources' => $activeSources,
            'writer_current_sources' => $writerSources,
            'released_restart_sources' => $releasedSources,
            'restart_reader_sources' => $restartSources,
            'active_reader_frame_indexes' => self::visibilityColumn($activeReader, 'frame_index'),
            'writer_current_frame_indexes' => self::visibilityColumn($writerCurrent, 'frame_index'),
            'released_restart_frame_indexes' => self::visibilityColumn($releasedRestart, 'frame_index'),
            'restart_reader_frame_indexes' => self::visibilityColumn($restartReader, 'frame_index'),
            'current_source_rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'active_reader_keeps_original_wal' => in_array(true, array_column($rows, 'active_reader_held_rolled_back_frame'), true),
            'writer_current_uses_retained_prefix' => in_array('wal', $writerSources, true) && $writerCurrentEndFrame === $retainedWal->frameCount(),
            'active_reader_blocks_restart_reset' => $activeCheckpoint['busy'] && $activeCheckpoint['wal_action'] === 'preserve_wal',
            'reader_release_unblocks_restart' => !$releasedCheckpoint['busy'],
            'released_restart_keeps_header' => $releasedCheckpoint['wal_action'] === 'restart_wal' && strlen((string) $releasedCheckpoint['wal_bytes']) === 32,
            'restart_reader_uses_fresh_current_source' => $freshWal->frameCount() === 0 && !in_array('wal', $restartSources, true),
            'restart_reader_separated_from_stale_source' => $freshWal->header->checkpointSequence !== $wal->header->checkpointSequence
                && $freshWal->header->salt1 !== $wal->header->salt1,
            'restart_matches_writer_current' => $restartImages === $writerImages,
            'reader_restart_required' => true,
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'yield_count' => (4 * count($pageNumbers)) + count($rollback['discarded_wal_frames']) + 1,
            'dependencies' => array_values(array_unique(array_merge(
                $activeCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                $activePlan['dependencies'],
                $releasedPlan['dependencies'],
                [
                    'sqlite-wal-checkpoint-savepoint-reader-restart-current-source-next151',
                    'sqlite-wal-current-source-reader-restart',
                    'sqlite-wal-restart-fresh-generation',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native WAL parser/checkpoint/savepoint, SHM read-mark, and fresh WAL header helpers',
            'non_overlap' => 'avoids accepted next145 append-after-restart and next146 truncate-reader restart by proving the reopened reader binds to the fresh RESTART current source before any new writer append',
        ];
    }

    /**
     * @param array<string,mixed> $checkpoint
     */
    private static function freshWalAfterReleasedCheckpoint(SQLiteWal $wal, array $checkpoint): SQLiteWal
    {
        $salt = $checkpoint['next_wal_header_salt'] ?? null;
        if (!is_array($salt) || count($salt) !== 2) {
            throw new \RuntimeException('SQLite WAL checkpoint released result did not include next WAL header salt');
        }

        $headerBytes = pack(
            'N*',
            $wal->header->magic,
            $wal->header->formatVersion,
            $wal->header->pageSize,
            ($wal->header->checkpointSequence + 1) & 0xffffffff,
            (int) $salt[0],
            (int) $salt[1]
        );
        $checksum = SQLiteWal::checksumPair($headerBytes, $wal->header->usesLittleEndianChecksums());

        return SQLiteWal::parse($headerBytes . pack('N*', $checksum[0], $checksum[1]), $wal->header->pageSize, true);
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
    public static function releaseThenRollbackCheckpointCurrentSourceNext(
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
                ['sqlite-wal-savepoint-commit-current-next72', 'application-import-savepoint-commit-current-next72']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,savepoint:string,mode:string,original_reader_end_frame:int,retained_reader_end_frame:int,next_reader_end_frame:int,rollback_to_frame:int,retained_frame_count:int,discarded_frame_count:int,discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool,frame_name:string}>,wal_action:string,checkpoint_busy:bool,checkpoint_reason:string,original_reader:list<array<string,mixed>>,retained_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,original_reader_sources:list<string>,retained_reader_sources:list<string>,next_reader_sources:list<string>,original_reader_frame_indexes:list<int|null>,retained_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_source_keeps_original_wal:bool,retained_source_excludes_savepoint_tail:bool,next_reader_uses_checkpoint_database:bool,rolled_back_pages:list<int>,checkpointed_pages:list<int>,source_transitions:list<string>,images_match_retained_to_next:bool,images_match_original_to_retained:bool,current_wal_bytes_length:int,next_wal_bytes_length:int,database_bytes_length:int,dependencies:list<string>}
     */
    public static function checkpointReaderSavepointReplayCurrentSourceNext(
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
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next149 requires a savepoint name');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next149 requires at least one page number');
        }

        self::assertCurrentWalSource($wal, $walBytes);
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['passive', 'full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite checkpoint mode for current-source next149: {$mode}");
        }

        $rollback = $savepoints->walRollbackToPlan($savepoint);
        $checkpoint = self::afterRollbackTo($savepoints, $savepoint, $wal, $walBytes, $databaseBytes, $mode);
        $retainedWal = SQLiteWal::parse($checkpoint['current_wal_bytes'], $wal->header->pageSize, true);
        $originalReaderEndFrame ??= $wal->frameCount();
        $retainedReaderEndFrame = $retainedWal->frameCount();
        if ($originalReaderEndFrame < 0 || $originalReaderEndFrame > $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next149 original reader frame is outside the WAL frame range');
        }

        $durable = $checkpoint['current_durable'];
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($durable['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame ??= $nextWal?->frameCount() ?? 0;
        if ($nextReaderEndFrame < 0 || ($nextWal !== null && $nextReaderEndFrame > $nextWal->frameCount())) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next149 next reader frame is outside the WAL frame range');
        }

        $original = [];
        $retained = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader savepoint current-source next149 pages must be integers');
            }

            $original[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $originalReaderEndFrame);
            $retained[] = $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $retainedReaderEndFrame);
            $next[] = $nextWal === null
                ? self::databasePageVisibility($durable['database_bytes'], $wal->header->pageSize, $pageNumber)
                : $nextWal->readerSnapshotPageImage($durable['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        $originalSources = self::visibilityColumn($original, 'source');
        $retainedSources = self::visibilityColumn($retained, 'source');
        $nextSources = self::visibilityColumn($next, 'source');
        $originalFrames = self::visibilityColumn($original, 'frame_index');
        $retainedFrames = self::visibilityColumn($retained, 'frame_index');
        $nextFrames = self::visibilityColumn($next, 'frame_index');
        $originalImages = self::visibilityColumn($original, 'image');
        $retainedImages = self::visibilityColumn($retained, 'image');
        $nextImages = self::visibilityColumn($next, 'image');

        $rolledBackPages = self::discardedPageNumbers($rollback['discarded_wal_frames']);
        $checkpointedPages = [];
        foreach ($retainedWal->checkpointPlan($databaseBytes)['frames'] as $frame) {
            if ($frame['applied']) {
                $checkpointedPages[$frame['page_number']] = true;
            }
        }
        $checkpointedPages = array_keys($checkpointedPages);
        sort($checkpointedPages, SORT_NUMERIC);

        $sourceTransitions = [];
        foreach ($pageNumbers as $index => $_pageNumber) {
            $sourceTransitions[] = $originalSources[$index] . '>' . $retainedSources[$index] . '>' . $nextSources[$index];
        }

        return [
            'status' => 'wal-checkpoint-reader-savepoint-current-source-next149',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'original_reader_end_frame' => $originalReaderEndFrame,
            'retained_reader_end_frame' => $retainedReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'rollback_to_frame' => $rollback['rollback_to_frame'],
            'retained_frame_count' => $checkpoint['retained_frame_count'],
            'discarded_frame_count' => $checkpoint['discarded_frame_count'],
            'discarded_wal_frames' => $rollback['discarded_wal_frames'],
            'wal_action' => $durable['wal_action'],
            'checkpoint_busy' => $checkpoint['busy'],
            'checkpoint_reason' => $checkpoint['reason'],
            'original_reader' => $original,
            'retained_reader' => $retained,
            'next_reader' => $next,
            'original_reader_sources' => $originalSources,
            'retained_reader_sources' => $retainedSources,
            'next_reader_sources' => $nextSources,
            'original_reader_frame_indexes' => $originalFrames,
            'retained_reader_frame_indexes' => $retainedFrames,
            'next_reader_frame_indexes' => $nextFrames,
            'current_source_keeps_original_wal' => $originalImages !== $retainedImages,
            'retained_source_excludes_savepoint_tail' => $retainedReaderEndFrame === $rollback['rollback_to_frame'],
            'next_reader_uses_checkpoint_database' => !in_array('wal', $nextSources, true),
            'rolled_back_pages' => $rolledBackPages,
            'checkpointed_pages' => $checkpointedPages,
            'source_transitions' => $sourceTransitions,
            'images_match_retained_to_next' => $retainedImages === $nextImages,
            'images_match_original_to_retained' => $originalImages === $retainedImages,
            'current_wal_bytes_length' => strlen($checkpoint['current_wal_bytes']),
            'next_wal_bytes_length' => strlen((string) $durable['wal_bytes']),
            'database_bytes_length' => strlen((string) $durable['database_bytes']),
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                ['sqlite-wal-checkpoint-reader-savepoint-current-source-next149', 'application-import-wal-current-reader-savepoint-boundary']
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

    private static function assertShmMatchesWal(SQLiteShmIndex $shm, SQLiteWal $wal, string $label): void
    {
        $salt = $shm->header['salt'] ?? [];
        if (($salt[0] ?? null) !== $wal->header->salt1 || ($salt[1] ?? null) !== $wal->header->salt2) {
            throw new \InvalidArgumentException("SQLite WAL reader checkpoint savepoint current-source next139 {$label} SHM salt does not match current WAL");
        }
        if ((int) ($shm->header['mx_frame'] ?? -1) !== $wal->frameCount()) {
            throw new \InvalidArgumentException("SQLite WAL reader checkpoint savepoint current-source next139 {$label} SHM mxFrame does not match current WAL");
        }
    }

    /**
     * @param array<string,mixed> $checkpointPlan
     * @return array<string,mixed>
     */
    private static function shmSourceSummary(SQLiteShmIndex $shm, array $checkpointPlan): array
    {
        $salt = $shm->header['salt'] ?? [];

        return [
            'mx_frame' => $shm->header['mx_frame'],
            'backfilled_frame_count' => $shm->backfilledFrameCount,
            'backfill_attempted_frame_count' => $shm->backfillAttemptedFrameCount,
            'salt1' => $salt[0] ?? null,
            'salt2' => $salt[1] ?? null,
            'checkpoint_pinned_frame' => $checkpointPlan['checkpoint_pinned_frame'],
            'reset_blocked' => $checkpointPlan['reset_blocked'],
            'read_locks' => $checkpointPlan['read_locks'],
        ];
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
     * @param array<string,mixed> $checkpoint
     */
    private static function walAfterDurableCheckpoint(SQLiteWal $wal, array $checkpoint): SQLiteWal
    {
        $walBytes = (string) $checkpoint['wal_bytes'];
        if ($walBytes !== '') {
            return SQLiteWal::parse($walBytes, $wal->header->pageSize, true);
        }

        /** @var array{0:int,1:int} $salt */
        $salt = $checkpoint['next_wal_header_salt'];
        $headerBytes = pack(
            'N*',
            $wal->header->magic,
            $wal->header->formatVersion,
            $wal->header->pageSize,
            ($wal->header->checkpointSequence + 1) & 0xffffffff,
            $salt[0],
            $salt[1],
        );
        $checksum = SQLiteWal::checksumPair($headerBytes, $wal->header->usesLittleEndianChecksums());

        return SQLiteWal::parse($headerBytes . pack('N*', $checksum[0], $checksum[1]), $wal->header->pageSize, true);
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
