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
