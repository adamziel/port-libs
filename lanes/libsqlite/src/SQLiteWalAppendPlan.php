<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalAppendPlan
{
    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,savepoint:string,mode:string,database_path:string,wal_path:string,rollback:array<string,mixed>,checkpoint:array<string,mixed>,append:array<string,mixed>,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,retained_frame_count:int,discarded_frame_count:int,current_uses_rollback_wal_prefix:bool,next_uses_checkpoint_database:bool,next_uses_appended_wal:bool,images_match:bool,operations:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function savepointRestartCheckpointCurrentNext(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart checkpoint requires a savepoint name');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart checkpoint current/next requires at least one page number');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart checkpoint requires WAL bytes');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart checkpoint requires a database path');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart checkpoint requires restart or truncate mode');
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $currentWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $currentWal = SQLiteWal::parse($currentWalBytes, $wal->header->pageSize, true);
        $currentReaderEndFrame = $readerEndFrame ?? $currentWal->frameCount();
        if ($currentReaderEndFrame > $currentWal->frameCount()) {
            $currentReaderEndFrame = $currentWal->frameCount();
        }

        $checkpoint = $currentWal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        if ($checkpoint['busy']) {
            return [
                'status' => 'busy',
                'reason' => $checkpoint['reason'],
                'savepoint' => $savepoint,
                'mode' => $mode,
                'database_path' => $databasePath,
                'wal_path' => $databasePath . '-wal',
                'rollback' => $rollback,
                'checkpoint' => $checkpoint,
                'append' => [],
                'current_reader_end_frame' => $currentReaderEndFrame,
                'next_reader_end_frame' => 0,
                'current_reader' => [],
                'next_reader' => [],
                'current_reader_sources' => [],
                'next_reader_sources' => [],
                'current_reader_frame_indexes' => [],
                'next_reader_frame_indexes' => [],
                'current_reader_errors' => [],
                'next_reader_errors' => [],
                'retained_frame_count' => $rollback['retained_frame_count'],
                'discarded_frame_count' => $rollback['discarded_frame_count'],
                'current_uses_rollback_wal_prefix' => $rollback['discarded_frame_count'] > 0,
                'next_uses_checkpoint_database' => false,
                'next_uses_appended_wal' => false,
                'images_match' => false,
                'operations' => [],
                'dependencies' => array_values(array_unique(array_merge(
                    $checkpoint['dependencies'],
                    ['sqlite-wal-savepoint-restart-checkpoint-current-next']
                ))),
            ];
        }

        $checkpointWal = self::walAfterCheckpoint($currentWal, $checkpoint);
        $append = self::appendTransactions($checkpointWal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame = $append['last_commit_frame'] ?? $nextWal->frameCount();

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint restart checkpoint current/next pages must be integers');
            }

            $current[] = self::safeReaderVisibility($currentWal, $databaseBytes, $pageNumber, $currentReaderEndFrame);
            $next[] = self::safeReaderVisibility($nextWal, $checkpoint['database_bytes'], $pageNumber, $nextReaderEndFrame);
        }

        return [
            'status' => 'planned',
            'reason' => 'savepoint_rollback_restart_checkpoint_then_append_current_next_visibility',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'rollback' => $rollback,
            'checkpoint' => $checkpoint,
            'append' => $append,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'retained_frame_count' => $rollback['retained_frame_count'],
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'current_uses_rollback_wal_prefix' => $rollback['discarded_frame_count'] > 0,
            'next_uses_checkpoint_database' => $checkpoint['database_bytes'] !== $databaseBytes,
            'next_uses_appended_wal' => $nextWal->frameCount() > 0,
            'images_match' => self::visibilityImages($current) === self::visibilityImages($next),
            'operations' => $append['operations'],
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                $append['dependencies'],
                ['sqlite-wal-savepoint-restart-checkpoint-current-next']
            ))),
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,mode:string,database_path:string,wal_path:string,checkpoint:array<string,mixed>,append:array<string,mixed>,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_stable_after_checkpoint:bool,next_uses_checkpoint_database:bool,next_uses_appended_wal:bool,operations:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function checkpointAppendCurrentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint append current/next requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint append current/next requires restart or truncate mode');
        }

        $checkpoint = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        if ($checkpoint['busy']) {
            return [
                'status' => 'busy',
                'reason' => $checkpoint['reason'],
                'mode' => $mode,
                'database_path' => $databasePath,
                'wal_path' => $databasePath . '-wal',
                'checkpoint' => $checkpoint,
                'append' => [],
                'current_reader_end_frame' => $readerEndFrame ?? $wal->frameCount(),
                'next_reader_end_frame' => 0,
                'current_reader' => [],
                'next_reader' => [],
                'current_reader_sources' => [],
                'next_reader_sources' => [],
                'current_reader_frame_indexes' => [],
                'next_reader_frame_indexes' => [],
                'current_reader_errors' => [],
                'next_reader_errors' => [],
                'current_stable_after_checkpoint' => false,
                'next_uses_checkpoint_database' => false,
                'next_uses_appended_wal' => false,
                'operations' => [],
                'dependencies' => array_values(array_unique(array_merge($checkpoint['dependencies'], ['sqlite-wal-checkpoint-append-current-next']))),
            ];
        }

        $checkpointWal = self::walAfterCheckpoint($wal, $checkpoint);
        $append = self::appendTransactions($checkpointWal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $currentEndFrame = $readerEndFrame ?? $wal->frameCount();
        $nextEndFrame = $nextWal->frameCount();

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint append current/next pages must be integers');
            }
            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame);
            $next[] = self::safeReaderVisibility($nextWal, $checkpoint['database_bytes'], $pageNumber, $nextEndFrame);
        }

        return [
            'status' => 'planned',
            'reason' => 'checkpoint_then_append_current_next_visibility',
            'mode' => $mode,
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'checkpoint' => $checkpoint,
            'append' => $append,
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_stable_after_checkpoint' => true,
            'next_uses_checkpoint_database' => $checkpoint['database_bytes'] !== $databaseBytes,
            'next_uses_appended_wal' => $nextWal->frameCount() > 0,
            'operations' => array_values(array_merge($append['operations'])),
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                $append['dependencies'],
                ['sqlite-wal-checkpoint-append-current-next']
            ))),
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @return array{status:string,reason:string,database_path:string,wal_path:string,start_offset:int,append_bytes:string,append_bytes_length:int,wal_bytes:string,wal_bytes_length:int,start_frame:int,end_frame:int,appended_frame_count:int,committed_transaction_count:int,uncommitted_transaction_count:int,last_commit_frame:int|null,last_database_page_count:int|null,frames:list<array{frame_index:int,page_number:int,commit:int,checksum1:int,checksum2:int,transaction:int,committed:bool}>,operations:list<array{op:string,path:string,offset?:int,bytes?:int,durable?:bool,reason?:string}>,dependencies:list<string>}
     */
    public static function appendTransactions(
        SQLiteWal $wal,
        string $databasePath,
        array $transactions,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL append plan requires a database path');
        }
        if ($transactions === []) {
            throw new \InvalidArgumentException('SQLite WAL append plan requires at least one transaction');
        }

        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL append plan requires a concrete WAL page size');
        }

        $walBytes = $wal->toBytes();
        $appendBytes = '';
        $frames = [];
        $checksum = self::checksumSeed($wal);
        $frameIndex = $wal->frameCount();
        $committed = 0;
        $uncommitted = 0;
        $lastDatabasePageCount = $wal->lastCommitFrame()?->databasePageCountAfterCommit;

        foreach (array_values($transactions) as $transactionIndex => $transaction) {
            $pages = $transaction['pages'] ?? null;
            if (!is_array($pages) || $pages === []) {
                throw new \InvalidArgumentException('SQLite WAL append transaction requires at least one page image');
            }

            $commit = (bool) ($transaction['commit'] ?? true);
            $databasePageCount = $transaction['database_page_count'] ?? null;
            if ($commit) {
                if (!is_int($databasePageCount) || $databasePageCount < 1) {
                    throw new \InvalidArgumentException('SQLite WAL committed append transaction requires a database page count');
                }
                if ($databasePageCount < max(array_keys($pages))) {
                    throw new \InvalidArgumentException('SQLite WAL committed append database page count cannot shrink below written pages');
                }
                $committed++;
                $lastDatabasePageCount = $databasePageCount;
            } else {
                $databasePageCount = 0;
                $uncommitted++;
            }

            foreach ($pages as $pageNumber => $pageImage) {
                if (!is_int($pageNumber) || $pageNumber < 1) {
                    throw new \InvalidArgumentException('SQLite WAL append page numbers must be one-based integers');
                }
                if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                    throw new \InvalidArgumentException("SQLite WAL append page {$pageNumber} must match the WAL page size");
                }

                $frameIndex++;
                $isCommitFrame = $commit && $pageNumber === array_key_last($pages);
                $commitPageCount = $isCommitFrame ? $databasePageCount : 0;
                $framePrefix = pack('N*', $pageNumber, $commitPageCount, $wal->header->salt1, $wal->header->salt2);
                $checksum = SQLiteWal::checksumPair(
                    substr($framePrefix, 0, 8) . $pageImage,
                    $wal->header->usesLittleEndianChecksums(),
                    $checksum[0],
                    $checksum[1]
                );
                $frameBytes = $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $pageImage;
                $appendBytes .= $frameBytes;
                $walBytes .= $frameBytes;
                $frames[] = [
                    'frame_index' => $frameIndex,
                    'page_number' => $pageNumber,
                    'commit' => $commitPageCount,
                    'checksum1' => $checksum[0],
                    'checksum2' => $checksum[1],
                    'transaction' => $transactionIndex,
                    'committed' => $isCommitFrame,
                ];
            }
        }

        $walPath = $databasePath . '-wal';
        $operations = [[
            'op' => 'write',
            'path' => $walPath,
            'offset' => strlen($wal->toBytes()),
            'bytes' => strlen($appendBytes),
            'durable' => false,
            'reason' => 'append_wal_transaction_frames',
        ]];
        if ($syncWal) {
            $operations[] = [
                'op' => 'sync',
                'path' => $walPath,
                'durable' => true,
                'reason' => 'sync_appended_wal_frames',
            ];
        }
        if ($syncDirectory) {
            $operations[] = [
                'op' => 'sync_directory',
                'path' => dirname($databasePath),
                'durable' => true,
                'reason' => 'persist_appended_wal_sidecar',
            ];
        }

        return [
            'status' => 'planned',
            'reason' => $committed > 0 ? 'wal_append_contains_commit_frame' : 'wal_append_uncommitted_tail',
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'start_offset' => strlen($wal->toBytes()),
            'append_bytes' => $appendBytes,
            'append_bytes_length' => strlen($appendBytes),
            'wal_bytes' => $walBytes,
            'wal_bytes_length' => strlen($walBytes),
            'start_frame' => $wal->frameCount() + 1,
            'end_frame' => $frameIndex,
            'appended_frame_count' => count($frames),
            'committed_transaction_count' => $committed,
            'uncommitted_transaction_count' => $uncommitted,
            'last_commit_frame' => $committed > 0 ? self::lastCommittedFrameIndex($frames) : $wal->lastCommitFrame()?->index,
            'last_database_page_count' => $lastDatabasePageCount,
            'frames' => $frames,
            'operations' => $operations,
            'dependencies' => ['sqlite-wal-append-transaction', 'sqlite-wal-frame-checksum-chain', 'vfs-file-write-coordination'],
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @param list<int|null> $readMarks
     * @return array{status:string,reason:string,database_path:string,wal_path:string,current_reader_end_frame:int|null,next_reader_end_frame:int|null,next_reader_slot:int|null,current_read_marks:list<int|null>,next_read_marks:list<int|null>,release_read_marks:list<int|null>,append:array<string,mixed>,current_read_mark_plan:array<string,mixed>,next_read_mark_plan:array<string,mixed>,release_read_mark_plan:array<string,mixed>,checkpoint_before_release:array<string,mixed>,checkpoint_after_release:array<string,mixed>,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_snapshot_stable:bool,next_snapshot_advances:bool,current_pin_blocks_checkpoint:bool,release_allows_checkpoint_reset:bool,frames_hidden_from_current:list<int>,frames_visible_to_next:list<int>,dependencies:list<string>}
     */
    public static function readerPinAppendCurrentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        array $readMarks,
        string $checkpointMode = 'restart',
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader-pin append current/next requires at least one page number');
        }

        $checkpointMode = strtolower(trim($checkpointMode));
        if (!in_array($checkpointMode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL reader-pin append current/next requires restart or truncate checkpoint mode');
        }

        $currentReadMarkPlan = $wal->readMarkPlan($readMarks);
        $currentEndFrame = $currentReadMarkPlan['checkpoint_pinned_frame'] ?? $currentReadMarkPlan['recommended_reader_frame'];
        $append = self::appendTransactions($wal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $nextEndFrame = $append['last_commit_frame'] ?? $wal->frameCount();

        $nextReadMarks = array_values($readMarks);
        $nextReaderSlot = self::nextReaderSlot($nextReadMarks, $currentReadMarkPlan);
        if ($nextReaderSlot !== null) {
            $nextReadMarks[$nextReaderSlot] = $nextEndFrame;
        }
        $nextReadMarkPlan = $nextWal->readMarkPlan($nextReadMarks);

        $releaseReadMarks = $nextReadMarks;
        foreach ($currentReadMarkPlan['read_marks'] as $mark) {
            if ($mark['pins_checkpoint']) {
                $releaseReadMarks[$mark['slot']] = null;
            }
        }
        $releaseReadMarkPlan = $nextWal->readMarkPlan($releaseReadMarks);

        $checkpointBeforeRelease = $nextWal->durableCheckpointResult(
            $databaseBytes,
            $checkpointMode,
            $nextReadMarkPlan['checkpoint_pinned_frame'],
        );
        $checkpointAfterRelease = $nextWal->durableCheckpointResult(
            $databaseBytes,
            $checkpointMode,
            $releaseReadMarkPlan['checkpoint_pinned_frame'],
        );

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL reader-pin append current/next pages must be integers');
            }

            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame, true);
            $next[] = self::safeReaderVisibility($nextWal, $databaseBytes, $pageNumber, $nextEndFrame, true);
        }

        return [
            'status' => $currentReadMarkPlan['checkpoint_pinned_frame'] !== null
                ? 'current-reader-pinned-next-reader-advanced'
                : 'no-current-reader-pin',
            'reason' => $append['committed_transaction_count'] > 0
                ? 'wal_append_preserves_current_pin_and_assigns_next_reader'
                : 'wal_append_has_no_committed_next_reader_frame',
            'database_path' => $databasePath,
            'wal_path' => $append['wal_path'],
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'next_reader_slot' => $nextReaderSlot,
            'current_read_marks' => array_values($readMarks),
            'next_read_marks' => $nextReadMarks,
            'release_read_marks' => $releaseReadMarks,
            'append' => $append,
            'current_read_mark_plan' => $currentReadMarkPlan,
            'next_read_mark_plan' => $nextReadMarkPlan,
            'release_read_mark_plan' => $releaseReadMarkPlan,
            'checkpoint_before_release' => $checkpointBeforeRelease,
            'checkpoint_after_release' => $checkpointAfterRelease,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_snapshot_stable' => self::visibilityImages($current) === self::visibilityImages(array_map(
                static fn (array $entry): array => self::safeReaderVisibility($wal, $databaseBytes, (int) $entry['page_number'], $currentEndFrame, true),
                $current
            )),
            'next_snapshot_advances' => self::visibilityImages($current) !== self::visibilityImages($next),
            'current_pin_blocks_checkpoint' => $checkpointBeforeRelease['busy'] && $checkpointBeforeRelease['wal_action'] === 'preserve_wal',
            'release_allows_checkpoint_reset' => !$checkpointAfterRelease['busy'] && ($checkpointAfterRelease['can_reset'] || $checkpointAfterRelease['can_truncate']),
            'frames_hidden_from_current' => $currentEndFrame !== null && $currentEndFrame < $nextWal->frameCount()
                ? range($currentEndFrame + 1, $nextWal->frameCount())
                : [],
            'frames_visible_to_next' => $nextEndFrame > 0 ? range(1, $nextEndFrame) : [],
            'dependencies' => array_values(array_unique(array_merge(
                $append['dependencies'],
                $checkpointBeforeRelease['dependencies'],
                [
                    'sqlite-wal-reader-pin-append-current-next67',
                    'sqlite-wal-readmark-handoff',
                ],
            ))),
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @param list<int|null> $readMarks
     * @return array{status:string,reason:string,database_path:string,wal_path:string,current_reader_slot:int,current_reader_end_frame:int,next_reader_end_frame:int|null,next_reader_slot:int|null,current_read_marks:list<int|null>,next_read_marks:list<int|null>,release_read_marks:list<int|null>,append:array<string,mixed>,current_read_mark_plan:array<string,mixed>,next_read_mark_plan:array<string,mixed>,release_read_mark_plan:array<string,mixed>,checkpoint_with_current_pin:array<string,mixed>,checkpoint_after_release:array<string,mixed>,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_snapshot_stable:bool,next_snapshot_advances:bool,current_slot_blocks_checkpoint_reset:bool,release_allows_checkpoint_reset:bool,frames_hidden_from_current:list<int>,frames_visible_to_next:list<int>,dependencies:list<string>}
     */
    public static function readerSlotPinAppendCurrentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        array $readMarks,
        int $currentReaderSlot,
        string $checkpointMode = 'restart',
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader-slot pin current/next requires at least one page number');
        }
        if (!array_key_exists($currentReaderSlot, $readMarks)) {
            throw new \InvalidArgumentException('SQLite WAL reader-slot pin current/next requires an existing current reader slot');
        }
        if ($readMarks[$currentReaderSlot] === null) {
            throw new \InvalidArgumentException('SQLite WAL reader-slot pin current/next requires an active current reader slot');
        }
        if (!is_int($readMarks[$currentReaderSlot]) || $readMarks[$currentReaderSlot] < 0) {
            throw new \InvalidArgumentException('SQLite WAL reader-slot pin current/next requires a non-negative current reader frame');
        }
        if ($readMarks[$currentReaderSlot] > $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL reader-slot pin current/next current reader frame cannot exceed the WAL frame count');
        }

        $checkpointMode = strtolower(trim($checkpointMode));
        if (!in_array($checkpointMode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL reader-slot pin current/next requires restart or truncate checkpoint mode');
        }

        $currentEndFrame = $readMarks[$currentReaderSlot];
        $currentReadMarkPlan = $wal->readMarkPlan($readMarks);
        $append = self::appendTransactions($wal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $nextEndFrame = $append['last_commit_frame'] ?? null;

        $nextReadMarks = array_values($readMarks);
        $nextReaderSlot = null;
        foreach ($nextReadMarks as $slot => $mark) {
            if ($slot !== $currentReaderSlot && $mark === null) {
                $nextReaderSlot = $slot;
                break;
            }
        }
        if ($nextReaderSlot !== null && $nextEndFrame !== null) {
            $nextReadMarks[$nextReaderSlot] = $nextEndFrame;
        }
        $nextReadMarkPlan = $nextWal->readMarkPlan($nextReadMarks);

        $releaseReadMarks = $nextReadMarks;
        $releaseReadMarks[$currentReaderSlot] = null;
        $releaseReadMarkPlan = $nextWal->readMarkPlan($releaseReadMarks);

        $checkpointWithCurrentPin = $nextWal->durableCheckpointResult($databaseBytes, $checkpointMode, $currentEndFrame);
        $checkpointAfterRelease = $nextWal->durableCheckpointResult(
            $databaseBytes,
            $checkpointMode,
            $releaseReadMarkPlan['checkpoint_pinned_frame'],
        );

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL reader-slot pin current/next pages must be integers');
            }

            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame, true);
            $next[] = $nextEndFrame === null
                ? self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame, true)
                : self::safeReaderVisibility($nextWal, $databaseBytes, $pageNumber, $nextEndFrame, true);
        }

        return [
            'status' => $append['committed_transaction_count'] > 0
                ? 'current-reader-slot-pinned-next-reader-advanced'
                : 'current-reader-slot-pinned-no-committed-next',
            'reason' => $currentEndFrame === 0
                ? 'database_reader_pin_preserved_across_wal_append'
                : 'wal_reader_slot_pin_preserved_across_wal_append',
            'database_path' => $databasePath,
            'wal_path' => $append['wal_path'],
            'current_reader_slot' => $currentReaderSlot,
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'next_reader_slot' => $nextReaderSlot,
            'current_read_marks' => array_values($readMarks),
            'next_read_marks' => $nextReadMarks,
            'release_read_marks' => $releaseReadMarks,
            'append' => $append,
            'current_read_mark_plan' => $currentReadMarkPlan,
            'next_read_mark_plan' => $nextReadMarkPlan,
            'release_read_mark_plan' => $releaseReadMarkPlan,
            'checkpoint_with_current_pin' => $checkpointWithCurrentPin,
            'checkpoint_after_release' => $checkpointAfterRelease,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_snapshot_stable' => self::visibilityImages($current) === self::visibilityImages(array_map(
                static fn (array $entry): array => self::safeReaderVisibility($wal, $databaseBytes, (int) $entry['page_number'], $currentEndFrame, true),
                $current
            )),
            'next_snapshot_advances' => self::visibilityImages($current) !== self::visibilityImages($next),
            'current_slot_blocks_checkpoint_reset' => $checkpointWithCurrentPin['busy'] && $checkpointWithCurrentPin['wal_action'] === 'preserve_wal',
            'release_allows_checkpoint_reset' => !$checkpointAfterRelease['busy'] && ($checkpointAfterRelease['can_reset'] || $checkpointAfterRelease['can_truncate']),
            'frames_hidden_from_current' => $currentEndFrame < $nextWal->frameCount()
                ? range($currentEndFrame + 1, $nextWal->frameCount())
                : [],
            'frames_visible_to_next' => $nextEndFrame !== null && $nextEndFrame > 0 ? range(1, $nextEndFrame) : [],
            'dependencies' => array_values(array_unique(array_merge(
                $append['dependencies'],
                $checkpointWithCurrentPin['dependencies'],
                [
                    'sqlite-wal-reader-slot-pin-current-next69',
                    'sqlite-wal-readmark-handoff',
                ],
            ))),
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,mode:string,database_path:string,wal_path:string,first:array<string,mixed>,retry:array<string,mixed>,append:array<string,mixed>,current_reader_end_frame:int|null,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_reader_kept_snapshot:bool,retry_reset_ready:bool,next_uses_checkpoint_database:bool,next_uses_restarted_generation:bool,next_uses_appended_wal:bool,images_match:bool,frames_hidden_from_current:list<int>,frames_visible_to_next:list<int>,dependencies:list<string>}
     */
    public static function checkpointRestartAppendReaderCurrentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        SQLiteShmIndex $currentShm,
        SQLiteShmIndex $releasedShm,
        string $mode = 'restart',
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint restart append requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint restart append current/next requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint restart append requires restart or truncate mode');
        }

        $first = $wal->restartReadMarkTransition($databaseBytes, $currentShm, $mode);
        $retry = $wal->restartReadMarkTransition($databaseBytes, $releasedShm, $mode);
        if ($retry['checkpoint']['busy']) {
            throw new \RuntimeException('SQLite WAL checkpoint restart append requires released readers before appending');
        }

        $checkpointWal = self::walAfterCheckpoint($wal, $retry['checkpoint']);
        $append = self::appendTransactions($checkpointWal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame = $append['last_commit_frame'] ?? $nextWal->frameCount();

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint restart append current/next pages must be integers');
            }

            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $first['current_reader_end_frame'], true);
            $next[] = self::safeReaderVisibility($nextWal, $retry['checkpoint']['database_bytes'], $pageNumber, $nextReaderEndFrame, true);
        }

        return [
            'status' => $first['status'] === 'current-reader-pinned'
                ? 'reader-pin-restart-append-current-next'
                : 'restart-append-' . $first['status'],
            'reason' => $append['committed_transaction_count'] > 0
                ? 'released_reader_restart_checkpoint_then_append_advances_next_snapshot'
                : 'released_reader_restart_checkpoint_append_has_no_committed_next_snapshot',
            'mode' => $mode,
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'first' => $first,
            'retry' => $retry,
            'append' => $append,
            'current_reader_end_frame' => $first['current_reader_end_frame'],
            'next_reader_end_frame' => $nextReaderEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_reader_kept_snapshot' => $first['current_reader_kept_snapshot'],
            'retry_reset_ready' => !$retry['checkpoint']['busy'] && in_array($retry['checkpoint']['wal_action'], ['restart_wal', 'truncate_wal'], true),
            'next_uses_checkpoint_database' => true,
            'next_uses_restarted_generation' => $retry['checkpoint']['wal_bytes'] !== '' || $retry['checkpoint']['wal_action'] === 'truncate_wal',
            'next_uses_appended_wal' => $append['committed_transaction_count'] > 0,
            'images_match' => self::visibilityImages($current) === self::visibilityImages($next),
            'frames_hidden_from_current' => $first['current_reader_end_frame'] !== null && $first['current_reader_end_frame'] < $wal->frameCount()
                ? range($first['current_reader_end_frame'] + 1, $wal->frameCount())
                : [],
            'frames_visible_to_next' => $nextReaderEndFrame > 0 ? range(1, $nextReaderEndFrame) : [],
            'dependencies' => array_values(array_unique(array_merge(
                $first['dependencies'],
                $retry['dependencies'],
                $append['dependencies'],
                ['sqlite-wal-reader-pin-restart-snapshot-current-next73']
            ))),
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,database_path:string,wal_path:string,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_database_page_count:int,next_database_page_count:int,appended_frame_count:int,committed_transaction_count:int,uncommitted_transaction_count:int,uncommitted_tail_visible:bool,images_match:bool,append:array<string,mixed>,dependencies:list<string>}
     */
    public static function readerWriterSnapshotBoundary(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL append snapshot boundary requires at least one page number');
        }

        $append = self::appendTransactions($wal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $currentEndFrame = $wal->frameCount();
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $nextEndFrame = $append['last_commit_frame'] ?? $currentEndFrame;

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL append snapshot boundary pages must be integers');
            }

            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame, true);
            $next[] = self::safeReaderVisibility($nextWal, $databaseBytes, $pageNumber, $nextEndFrame, true);
        }

        $uncommittedTailVisible = false;
        foreach ($next as $entry) {
            $frameIndex = $entry['frame_index'] ?? null;
            if (is_int($frameIndex) && $frameIndex > $nextEndFrame) {
                $uncommittedTailVisible = true;
                break;
            }
        }

        return [
            'status' => 'planned',
            'reason' => $append['committed_transaction_count'] > 0
                ? 'next_reader_sees_committed_append_current_reader_pinned'
                : 'append_has_no_committed_next_snapshot',
            'database_path' => $databasePath,
            'wal_path' => $append['wal_path'],
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_database_page_count' => $wal->readerSnapshot($databaseBytes, $currentEndFrame)['database_page_count'],
            'next_database_page_count' => $nextWal->readerSnapshot($databaseBytes, $nextEndFrame)['database_page_count'],
            'appended_frame_count' => $append['appended_frame_count'],
            'committed_transaction_count' => $append['committed_transaction_count'],
            'uncommitted_transaction_count' => $append['uncommitted_transaction_count'],
            'uncommitted_tail_visible' => $uncommittedTailVisible,
            'images_match' => self::visibilityImages($current) === self::visibilityImages($next),
            'append' => $append,
            'dependencies' => array_values(array_unique(array_merge(
                $append['dependencies'],
                ['sqlite-wal-reader-writer-snapshot-boundary']
            ))),
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,database_path:string,wal_path:string,current_reader_end_frame:int,next_reader_end_frame:int,base_writer_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_database_page_count:int,next_database_page_count:int,current_commit_frame:int|null,next_commit_frame:int|null,appended_frame_count:int,committed_transaction_count:int,uncommitted_transaction_count:int,frames_hidden_from_current:list<int>,frames_visible_to_next:list<int>,uncommitted_tail_visible:bool,images_match:bool,append:array<string,mixed>,dependencies:list<string>}
     */
    public static function mvccReaderCurrentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        ?int $currentReaderEndFrame = null,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL MVCC reader current/next requires at least one page number');
        }

        $baseWriterEndFrame = $wal->frameCount();
        $currentEndFrame = $currentReaderEndFrame ?? $baseWriterEndFrame;
        if ($currentEndFrame < 0 || $currentEndFrame > $baseWriterEndFrame) {
            throw new \InvalidArgumentException('SQLite WAL MVCC reader end frame must be within the current WAL frame range');
        }

        $currentSnapshot = $wal->readerSnapshot($databaseBytes, $currentEndFrame);
        $append = self::appendTransactions($wal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $nextEndFrame = $append['last_commit_frame'] ?? $baseWriterEndFrame;
        $nextSnapshot = $nextWal->readerSnapshot($databaseBytes, $nextEndFrame);

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL MVCC reader current/next pages must be integers');
            }

            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame, true);
            $next[] = self::safeReaderVisibility($nextWal, $databaseBytes, $pageNumber, $nextEndFrame, true);
        }

        $uncommittedTailVisible = false;
        foreach ($next as $entry) {
            $frameIndex = $entry['frame_index'] ?? null;
            if (is_int($frameIndex) && $frameIndex > $nextEndFrame) {
                $uncommittedTailVisible = true;
                break;
            }
        }

        return [
            'status' => 'planned',
            'reason' => $append['committed_transaction_count'] > 0
                ? 'mvcc_current_reader_pinned_next_reader_advances'
                : 'mvcc_append_has_no_committed_next_snapshot',
            'database_path' => $databasePath,
            'wal_path' => $append['wal_path'],
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'base_writer_end_frame' => $baseWriterEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_database_page_count' => $currentSnapshot['database_page_count'],
            'next_database_page_count' => $nextSnapshot['database_page_count'],
            'current_commit_frame' => $currentSnapshot['commit_frame']?->index,
            'next_commit_frame' => $nextSnapshot['commit_frame']?->index,
            'appended_frame_count' => $append['appended_frame_count'],
            'committed_transaction_count' => $append['committed_transaction_count'],
            'uncommitted_transaction_count' => $append['uncommitted_transaction_count'],
            'frames_hidden_from_current' => $currentEndFrame < $nextWal->frameCount()
                ? range($currentEndFrame + 1, $nextWal->frameCount())
                : [],
            'frames_visible_to_next' => $nextEndFrame > 0 ? range(1, $nextEndFrame) : [],
            'uncommitted_tail_visible' => $uncommittedTailVisible,
            'images_match' => self::visibilityImages($current) === self::visibilityImages($next),
            'append' => $append,
            'dependencies' => array_values(array_unique(array_merge(
                $append['dependencies'],
                ['sqlite-pager-mvcc-reader-current-next']
            ))),
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,mode:string,database_path:string,wal_path:string,checkpoint:array<string,mixed>,append:array<string,mixed>,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_reader_images:list<string|null>,next_reader_images:list<string|null>,current_reader_stable:bool,next_reader_sees_committed_append:bool,next_reader_hides_uncommitted_tail:bool,checkpoint_backfilled_partial:bool,pin_preserved_wal:bool,appended_after_preserved_wal:bool,operations:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function pinnedCheckpointAppendCurrentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL pinned checkpoint append current/next requires at least one page number');
        }
        if ($readerEndFrame < 1) {
            throw new \InvalidArgumentException('SQLite WAL pinned checkpoint append requires a positive reader frame');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['passive', 'full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL pinned checkpoint append mode: {$mode}");
        }

        $checkpoint = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        $appendBaseWal = $checkpoint['wal_action'] === 'preserve_wal'
            ? $wal
            : self::walAfterCheckpoint($wal, $checkpoint);
        $append = self::appendTransactions($appendBaseWal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $nextReaderEndFrame = $append['last_commit_frame'] ?? $wal->lastCommitFrame()?->index ?? $readerEndFrame;

        $current = [];
        $currentAfterCheckpoint = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL pinned checkpoint append current/next pages must be integers');
            }

            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $readerEndFrame);
            $currentAfterCheckpoint[] = self::safeReaderVisibility($appendBaseWal, $checkpoint['database_bytes'], $pageNumber, min($readerEndFrame, $appendBaseWal->frameCount()));
            $next[] = self::safeReaderVisibility($nextWal, $checkpoint['database_bytes'], $pageNumber, min($nextReaderEndFrame, $nextWal->frameCount()));
        }

        $currentImages = self::visibilityImages($current);
        $nextImages = self::visibilityImages($next);
        $uncommittedTailVisible = false;
        foreach ($next as $entry) {
            $frameIndex = $entry['frame_index'] ?? null;
            if (is_int($frameIndex) && $frameIndex > $nextReaderEndFrame) {
                $uncommittedTailVisible = true;
                break;
            }
        }

        return [
            'status' => $checkpoint['busy'] ? 'pinned-append-planned' : 'append-after-reset-planned',
            'reason' => $checkpoint['busy']
                ? 'reader_pin_preserves_wal_then_writer_appends'
                : 'checkpoint_reset_then_writer_appends',
            'mode' => $mode,
            'database_path' => $databasePath,
            'wal_path' => $append['wal_path'],
            'checkpoint' => $checkpoint,
            'append' => $append,
            'current_reader_end_frame' => $readerEndFrame,
            'next_reader_end_frame' => min($nextReaderEndFrame, $nextWal->frameCount()),
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_reader_images' => $currentImages,
            'next_reader_images' => $nextImages,
            'current_reader_stable' => $currentImages === self::visibilityImages($currentAfterCheckpoint),
            'next_reader_sees_committed_append' => $append['committed_transaction_count'] > 0 && $nextImages !== $currentImages,
            'next_reader_hides_uncommitted_tail' => !$uncommittedTailVisible,
            'checkpoint_backfilled_partial' => $checkpoint['checkpointed_frame_count'] > 0 && $checkpoint['remaining_committed_frame_count'] > 0,
            'pin_preserved_wal' => $checkpoint['wal_action'] === 'preserve_wal',
            'appended_after_preserved_wal' => $append['start_frame'] === $wal->frameCount() + 1,
            'operations' => $append['operations'],
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                $append['dependencies'],
                ['sqlite-wal-reader-pin-append-current-next61']
            ))),
        ];
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $pageNumbers
     * @param list<int|null> $readMarks
     * @return array<string,mixed>
     */
    public static function readerPinCurrentNext(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $pageNumbers,
        array $readMarks,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader pin current/next requires at least one page number');
        }
        if ($readMarks === []) {
            throw new \InvalidArgumentException('SQLite WAL reader pin current/next requires read marks');
        }

        $baseWriterEndFrame = $wal->frameCount();
        $currentReadPlan = $wal->readMarkPlan($readMarks);
        $currentEndFrame = $currentReadPlan['checkpoint_pinned_frame']
            ?? $currentReadPlan['recommended_reader_frame']
            ?? $baseWriterEndFrame;
        if (!is_int($currentEndFrame) || $currentEndFrame < 0 || $currentEndFrame > $baseWriterEndFrame) {
            throw new \InvalidArgumentException('SQLite WAL reader pin current/next current reader frame is outside the current WAL');
        }

        $currentSnapshot = $wal->readerSnapshot($databaseBytes, $currentEndFrame);
        $append = self::appendTransactions($wal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $nextWal = SQLiteWal::parse($append['wal_bytes'], $wal->header->pageSize, true);
        $nextEndFrame = $append['last_commit_frame'] ?? $currentEndFrame;
        $nextSnapshot = $nextWal->readerSnapshot($databaseBytes, $nextEndFrame);

        $nextReadMarks = array_values($readMarks);
        $nextReaderSlot = $currentReadPlan['recommended_reader_slot'];
        if ($nextReaderSlot === null) {
            $nextReaderSlot = count($nextReadMarks);
            $nextReadMarks[] = null;
        }
        $nextReadMarks[$nextReaderSlot] = $nextEndFrame;
        $nextReadPlan = $nextWal->readMarkPlan($nextReadMarks);

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL reader pin current/next pages must be integers');
            }

            $current[] = self::safeReaderVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame, true);
            $next[] = self::safeReaderVisibility($nextWal, $databaseBytes, $pageNumber, $nextEndFrame, true);
        }

        $uncommittedTailVisible = false;
        foreach ($next as $entry) {
            $frameIndex = $entry['frame_index'] ?? null;
            if (is_int($frameIndex) && $frameIndex > $nextEndFrame) {
                $uncommittedTailVisible = true;
                break;
            }
        }

        return [
            'status' => 'planned',
            'reason' => $append['committed_transaction_count'] > 0
                ? 'reader_pin_current_snapshot_next_reader_advances'
                : 'reader_pin_append_has_no_committed_next_snapshot',
            'database_path' => $databasePath,
            'wal_path' => $append['wal_path'],
            'current_read_mark_plan' => $currentReadPlan,
            'next_read_mark_plan' => $nextReadPlan,
            'current_reader_slot' => $currentReadPlan['checkpoint_pinned_frame'] !== null
                ? self::firstReadMarkSlot($readMarks, $currentEndFrame)
                : $currentReadPlan['recommended_reader_slot'],
            'next_reader_slot' => $nextReaderSlot,
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'base_writer_end_frame' => $baseWriterEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_database_page_count' => $currentSnapshot['database_page_count'],
            'next_database_page_count' => $nextSnapshot['database_page_count'],
            'current_commit_frame' => $currentSnapshot['commit_frame']?->index,
            'next_commit_frame' => $nextSnapshot['commit_frame']?->index,
            'appended_frame_count' => $append['appended_frame_count'],
            'committed_transaction_count' => $append['committed_transaction_count'],
            'uncommitted_transaction_count' => $append['uncommitted_transaction_count'],
            'frames_hidden_from_current' => $currentEndFrame < $nextWal->frameCount()
                ? range($currentEndFrame + 1, $nextWal->frameCount())
                : [],
            'frames_visible_to_next' => $nextEndFrame > 0 ? range(1, $nextEndFrame) : [],
            'current_reader_pins_old_snapshot' => $currentEndFrame < $nextEndFrame,
            'next_reader_uses_reusable_slot' => $nextReaderSlot !== null,
            'uncommitted_tail_visible' => $uncommittedTailVisible,
            'images_match' => self::visibilityImages($current) === self::visibilityImages($next),
            'append' => $append,
            'next_read_marks' => $nextReadMarks,
            'dependencies' => array_values(array_unique(array_merge(
                $append['dependencies'],
                ['sqlite-wal-reader-pin-current-next65', 'wal-index-read-marks']
            ))),
        ];
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function checksumSeed(SQLiteWal $wal): array
    {
        if ($wal->frames === []) {
            return [$wal->header->checksum1, $wal->header->checksum2];
        }

        $last = $wal->frames[array_key_last($wal->frames)];

        return [$last->checksum1, $last->checksum2];
    }

    /**
     * @param list<array{frame_index:int,committed:bool}> $frames
     */
    private static function lastCommittedFrameIndex(array $frames): ?int
    {
        for ($index = count($frames) - 1; $index >= 0; $index--) {
            if ($frames[$index]['committed']) {
                return $frames[$index]['frame_index'];
            }
        }

        return null;
    }

    /**
     * @param list<int|null> $readMarks
     */
    private static function firstReadMarkSlot(array $readMarks, int $frame): ?int
    {
        foreach (array_values($readMarks) as $slot => $readMark) {
            if ($readMark === $frame) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $checkpoint
     */
    private static function walAfterCheckpoint(SQLiteWal $wal, array $checkpoint): SQLiteWal
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
     * @return array<string,mixed>
     */
    private static function safeReaderVisibility(SQLiteWal $wal, string $databaseBytes, int $pageNumber, ?int $snapshotEndFrame, bool $errorSource = false): array
    {
        $endFrame = $snapshotEndFrame ?? $wal->frameCount();
        try {
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $snapshotEndFrame);
        } catch (\Throwable $error) {
            $snapshot = $wal->readerSnapshot($databaseBytes, min($endFrame, $wal->frameCount()));

            return [
                'page_number' => $pageNumber,
                'source' => $errorSource ? 'error' : 'missing',
                'frame_index' => null,
                'database_offset' => null,
                'image' => $errorSource ? '' : null,
                'snapshot_end_frame' => $endFrame,
                'snapshot_commit_frame' => $snapshot['commit_frame']?->index,
                'database_page_count' => $snapshot['database_page_count'],
                'error' => $error->getMessage(),
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return list<mixed>
     */
    private static function visibilityColumn(array $entries, string $column): array
    {
        return array_map(static fn (array $entry): mixed => $entry[$column] ?? null, $entries);
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return list<string>
     */
    private static function visibilityErrors(array $entries): array
    {
        $errors = [];
        foreach ($entries as $entry) {
            if (isset($entry['error'])) {
                $errors[] = (string) $entry['error'];
            }
        }

        return $errors;
    }

    /**
     * @param list<array<string,mixed>> $entries
     * @return list<string|null>
     */
    private static function visibilityImages(array $entries): array
    {
        return array_map(static fn (array $entry): ?string => isset($entry['image']) && is_string($entry['image']) ? $entry['image'] : null, $entries);
    }

    /**
     * @param list<int|null> $readMarks
     * @param array<string,mixed> $readMarkPlan
     */
    private static function nextReaderSlot(array $readMarks, array $readMarkPlan): ?int
    {
        foreach ($readMarks as $slot => $frame) {
            if ($frame === null) {
                return $slot;
            }
        }

        foreach ($readMarkPlan['read_marks'] as $mark) {
            if (!$mark['pins_checkpoint'] && in_array($mark['slot'], $readMarkPlan['reusable_slots'], true)) {
                return $mark['slot'];
            }
        }

        return null;
    }
}
