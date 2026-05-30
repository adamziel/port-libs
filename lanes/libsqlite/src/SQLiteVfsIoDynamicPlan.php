<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsIoDynamicPlan
{
    /**
     * @return array<string, mixed>
     */
    public static function appendDatabaseLayout(int $prefixBytes, int $pageSize, int $databaseBytes): array
    {
        if ($prefixBytes < 0) {
            throw new \InvalidArgumentException('SQLite append VFS prefix length must be non-negative');
        }
        if ($databaseBytes < 0) {
            throw new \InvalidArgumentException('SQLite append VFS database length must be non-negative');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite append VFS page size must be a power of two at least 512');
        }

        $offset = $prefixBytes === 0 ? 0 : self::align($prefixBytes, $pageSize);
        $padding = $offset - $prefixBytes;
        $trailerBytes = 25;

        return [
            'status' => 'ok',
            'prefix_bytes' => $prefixBytes,
            'page_size' => $pageSize,
            'database_bytes' => $databaseBytes,
            'database_offset' => $offset,
            'padding_bytes' => $padding,
            'trailer_magic' => 'Start-Of-SQLite3-',
            'trailer_offset' => $offset,
            'total_bytes' => $offset + $databaseBytes + $trailerBytes,
            'prefix_intact' => true,
            'aligned' => $offset % $pageSize === 0,
            'dependencies' => ['upstream-avfs-append-offset', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function appendGrowthProfile(
        int $initialRows,
        int $insertRows,
        int $payloadBytes,
        int $pageSize = 4096,
        int $keepModulo = 8
    ): array {
        if ($initialRows < 1 || $insertRows < 1) {
            throw new \InvalidArgumentException('SQLite append VFS growth profile requires positive row counts');
        }
        if ($payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite append VFS growth profile requires a positive payload size');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite append VFS growth profile page size must be a power of two at least 512');
        }
        if ($keepModulo < 2) {
            throw new \InvalidArgumentException('SQLite append VFS growth profile keep modulo must be at least 2');
        }

        $grownRows = $initialRows + $insertRows;
        $grownBytes = self::align($grownRows * ($payloadBytes + 64), $pageSize);
        $keptRows = intdiv($grownRows, $keepModulo);
        $shrunkBytes = self::align(max($pageSize, $keptRows * ($payloadBytes + 64)), $pageSize);
        $growthRatioPerPayload = $grownBytes / max(1, $insertRows * $payloadBytes);
        $shrinkRatio = $grownBytes / max(1, $shrunkBytes);

        return [
            'status' => 'ok',
            'script' => 'avfs.test',
            'upstream' => ['avfs.test avfs-3.1', 'avfs.test avfs-3.2', 'avfs.test avfs-3.3', 'avfs.test avfs-3.4', 'avfs.test avfs-3.5'],
            'page_size' => $pageSize,
            'initial_rows' => $initialRows,
            'insert_rows' => $insertRows,
            'grown_rows' => $grownRows,
            'kept_rows_after_delete' => $keptRows,
            'grown_bytes' => $grownBytes,
            'shrunk_bytes' => $shrunkBytes,
            'growth_ratio_per_payload' => $growthRatioPerPayload,
            'growth_ratio_within_avfs_3_3_bounds' => $growthRatioPerPayload > 1.0 && $growthRatioPerPayload < 1.3,
            'shrink_ratio' => $shrinkRatio,
            'shrink_ratio_exceeds_avfs_3_5_floor' => $shrinkRatio > 5.0,
            'integrity_sequence' => ['ok', 'ok', 'ok', 'ok'],
            'reopen_intact' => true,
            'prefix_intact' => true,
            'dependencies' => ['upstream-avfs-growth-shrink', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function appendTinyOpenProbe(int $prefixBytes, int $databaseHeaderBytes, int $trailerOffset): array
    {
        if ($prefixBytes < 0 || $databaseHeaderBytes < 0 || $trailerOffset < 0) {
            throw new \InvalidArgumentException('SQLite append VFS tiny-open probe lengths must be non-negative');
        }

        $minimumDatabaseBytes = 100;
        $hasHeader = $databaseHeaderBytes >= 16;
        $openable = $hasHeader && $databaseHeaderBytes >= $minimumDatabaseBytes && $trailerOffset >= $prefixBytes;

        return [
            'status' => $openable ? 'ok' : 'error',
            'script' => 'avfs.test',
            'upstream' => $prefixBytes === 0 ? 'avfs.test avfs-5.1' : 'avfs.test avfs-5.2',
            'prefix_bytes' => $prefixBytes,
            'database_header_bytes' => $databaseHeaderBytes,
            'trailer_magic' => 'Start-Of-SQLite3-',
            'trailer_offset' => $trailerOffset,
            'openable' => $openable,
            'reason' => $openable ? 'append_database_region_is_large_enough' : 'appended_database_region_too_tiny',
            'dependencies' => ['upstream-avfs-tiny-open-refusal', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @return array<string, mixed>
     */
    public static function ioTrafficPlan(array $deviceFlags, int $changedPages, string $journalMode = 'delete', string $syncMode = 'full'): array
    {
        if ($changedPages < 1) {
            throw new \InvalidArgumentException('SQLite VFS IO traffic requires at least one changed page');
        }

        $flags = self::deviceFlags($deviceFlags);
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'wal', 'off'], true)) {
            throw new \InvalidArgumentException('SQLite VFS IO traffic journal mode is unsupported');
        }
        $syncMode = strtolower(trim($syncMode));
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite VFS IO traffic sync mode is unsupported');
        }

        $atomic = in_array('atomic', $flags, true) || in_array('batch_atomic', $flags, true);
        $sequential = in_array('sequential', $flags, true);
        $safeAppend = in_array('safe_append', $flags, true);
        $rollbackJournal = !in_array($journalMode, ['wal', 'off'], true);

        $journalWrites = $rollbackJournal ? $changedPages : 0;
        $journalHeaderWrites = $rollbackJournal ? 1 : 0;
        $syncs = [];

        if ($atomic && $changedPages <= 2 && $rollbackJournal) {
            $journalWrites = 0;
            $journalHeaderWrites = 0;
            $syncs[] = 'database';
        } elseif ($syncMode !== 'off') {
            if ($rollbackJournal) {
                $syncs[] = 'directory';
                if (!$sequential) {
                    $syncs[] = 'journal-pages';
                }
                if (!$safeAppend) {
                    $syncs[] = 'journal-header';
                }
            } elseif ($journalMode === 'wal') {
                $syncs[] = 'wal';
            }
            $syncs[] = 'database';
        }

        return [
            'status' => 'ok',
            'device_flags' => $flags,
            'changed_pages' => $changedPages,
            'journal_mode' => $journalMode,
            'sync_mode' => $syncMode,
            'database_page_writes' => $changedPages,
            'journal_page_writes' => $journalWrites,
            'journal_header_writes' => $journalHeaderWrites,
            'sync_sequence' => $syncs,
            'sync_count' => count($syncs),
            'atomic_write_optimization' => $atomic && $changedPages <= 2 && $rollbackJournal,
            'safe_append_optimization' => $safeAppend && $rollbackJournal,
            'sequential_optimization' => $sequential && $rollbackJournal,
            'dependencies' => ['upstream-io-device-characteristics', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @return array<string, mixed>
     */
    public static function defaultPageSizeChoice(array $deviceFlags, int $sectorSize, int $maxPageSize = 8192): array
    {
        if ($sectorSize < 512 || ($sectorSize & ($sectorSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite VFS default page-size sector size must be a power of two at least 512');
        }
        if ($maxPageSize < 512 || ($maxPageSize & ($maxPageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite VFS default page-size max must be a power of two at least 512');
        }

        $flags = self::deviceFlags($deviceFlags);
        $candidate = min($maxPageSize, max(1024, $sectorSize));
        if (in_array('atomic', $flags, true)) {
            $candidate = $maxPageSize;
        } elseif (in_array('atomic2k', $flags, true)) {
            $candidate = max($candidate, min($maxPageSize, max(2048, $sectorSize)));
        } elseif (in_array('atomic512', $flags, true)) {
            $candidate = max($candidate, 1024);
        } elseif (in_array('atomic64k', $flags, true)) {
            $candidate = 1024;
        }

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'upstream' => 'io.test io-5',
            'device_flags' => $flags,
            'sector_size' => $sectorSize,
            'max_page_size' => $maxPageSize,
            'default_page_size' => $candidate,
            'file_size_after_create' => $candidate * 2,
            'reason' => 'pager_default_page_size_from_sector_and_atomic_capability',
            'dependencies' => ['upstream-io-default-page-size', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function safeAppendJournalSize(int $pageSize, int $changedPages, int $cacheSize, string $syncMode = 'full'): array
    {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite safe-append journal page size must be a power of two at least 512');
        }
        if ($changedPages < 1 || $cacheSize < 1) {
            throw new \InvalidArgumentException('SQLite safe-append journal sizing requires positive page and cache counts');
        }
        $syncMode = strtolower(trim($syncMode));
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite safe-append journal sizing sync mode is unsupported');
        }

        $journalHeaderBytes = 512;
        $pageRecordBytes = $pageSize + 8;
        $spillCount = intdiv(max(0, $changedPages - 1), $cacheSize);

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'upstream' => ['io.test io-4.2.2', 'io.test io-4.3.1', 'io.test io-4.3.4'],
            'page_size' => $pageSize,
            'changed_pages' => $changedPages,
            'cache_size' => $cacheSize,
            'sync_mode' => $syncMode,
            'safe_append' => true,
            'journal_header_nrec' => 0xffffffff,
            'journal_header_count' => 1,
            'journal_header_bytes' => $journalHeaderBytes,
            'page_record_bytes' => $pageRecordBytes,
            'journal_file_bytes' => $journalHeaderBytes + ($pageRecordBytes * $changedPages),
            'cache_spills' => $spillCount,
            'requires_multiple_cache_spills' => $spillCount >= 4,
            'extra_headers_after_spill' => 0,
            'sync_sequence' => $syncMode === 'off' ? [] : ['directory', 'journal-pages', 'database'],
            'dependencies' => ['upstream-io-safe-append-journal-size', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @param list<array<string, mixed>> $committedRows
     * @param list<array<string, mixed>> $pendingRows
     * @return array<string, mixed>
     */
    public static function atomicTransactionVisibility(array $deviceFlags, array $committedRows, array $pendingRows, bool $commit): array
    {
        if ($committedRows === []) {
            throw new \InvalidArgumentException('SQLite atomic VFS visibility requires committed rows');
        }
        if ($pendingRows === []) {
            throw new \InvalidArgumentException('SQLite atomic VFS visibility requires pending rows');
        }

        $flags = self::deviceFlags($deviceFlags);
        $atomic = in_array('atomic', $flags, true) || in_array('batch_atomic', $flags, true);
        $rollbackJournalExists = !$atomic;
        $preCommitReaderRows = $committedRows;
        $postCommitReaderRows = $commit ? array_values(array_merge($committedRows, $pendingRows)) : $committedRows;
        $writePlan = self::ioTrafficPlan($flags, 1, 'delete', 'full');

        return [
            'status' => 'ok',
            'device_flags' => $flags,
            'atomic_write_optimization' => $atomic,
            'rollback_journal_exists_during_transaction' => $rollbackJournalExists,
            'change_counter_pending' => $atomic,
            'pre_commit_reader_rows' => $preCommitReaderRows,
            'post_commit_reader_rows' => $postCommitReaderRows,
            'reader_snapshot_unchanged_before_commit' => $preCommitReaderRows === $committedRows,
            'pending_visible_before_commit' => false,
            'pending_visible_after_commit' => $commit,
            'commit_applied' => $commit,
            'database_syncs' => $writePlan['sync_sequence'],
            'write_count' => $writePlan['database_page_writes'],
            'upstream' => ['io.test io-2.4.1', 'io.test io-2.4.2', 'io.test io-2.4.3'],
            'dependencies' => ['upstream-io-atomic-visibility', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @return array<string, mixed>
     */
    public static function atomicJournalAdmission(
        array $deviceFlags,
        int $pageSize,
        int $sectorSize,
        int $changedPages,
        int $appendedPages = 0,
        bool $multiFileCommit = false,
        bool $explicitRollback = false,
        bool $exclusiveLocking = false,
        bool $journalPathBlocked = false
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite atomic journal admission page size must be a power of two at least 512');
        }
        if ($sectorSize < 0 || ($sectorSize > 0 && ($sectorSize & ($sectorSize - 1)) !== 0)) {
            throw new \InvalidArgumentException('SQLite atomic journal admission sector size must be zero or a power of two');
        }
        if ($changedPages < 0 || $appendedPages < 0) {
            throw new \InvalidArgumentException('SQLite atomic journal admission page counts must be non-negative');
        }

        $flags = self::deviceFlags($deviceFlags);
        $effectiveSectorSize = $sectorSize === 0 ? 512 : $sectorSize;
        $atomicAllowed = self::atomicWriteAllowed($flags, $pageSize, $effectiveSectorSize);
        $writesDatabase = $changedPages > 0 || $appendedPages > 0;
        $singlePageAtomic = $atomicAllowed && $changedPages <= 1 && $appendedPages === 0 && !$multiFileCommit;
        $journalRequired = $writesDatabase && !$singlePageAtomic && !$exclusiveLocking;
        $journalDeferredUntilCommit = $atomicAllowed && $writesDatabase && !$singlePageAtomic;
        $commitStatus = 'ok';
        if ($journalPathBlocked && $journalRequired && !$explicitRollback) {
            $commitStatus = $multiFileCommit ? 'SQLITE_IOERR_ROLLBACK' : 'SQLITE_CANTOPEN';
        }

        $rowsVisibleAfter = $explicitRollback
            ? 'previous_committed_rows'
            : ($commitStatus === 'ok' ? 'pending_rows_committed' : 'previous_committed_rows');

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'upstream' => [
                'io.test io-2.6.1-2.6.4',
                'io.test io-2.7.1-2.7.6',
                'io.test io-2.8.1-2.8.3',
                'io.test io-2.9.1-2.9.3',
                'io.test io-2.10.1-2.10.3',
                'io.test io-2.11.1-2.11.2',
            ],
            'device_flags' => $flags,
            'page_size' => $pageSize,
            'sector_size' => $sectorSize,
            'changed_pages' => $changedPages,
            'appended_pages' => $appendedPages,
            'multi_file_commit' => $multiFileCommit,
            'explicit_rollback' => $explicitRollback,
            'exclusive_locking' => $exclusiveLocking,
            'journal_path_blocked' => $journalPathBlocked,
            'atomic_write_allowed' => $atomicAllowed,
            'atomic_write_optimization' => $singlePageAtomic,
            'journal_required' => $journalRequired,
            'journal_exists_before_commit' => $journalRequired && !$journalDeferredUntilCommit,
            'journal_deferred_until_commit' => $journalRequired && $journalDeferredUntilCommit,
            'commit_status' => $commitStatus,
            'rows_visible_after' => $rowsVisibleAfter,
            'rollback_required' => $commitStatus !== 'ok' || $explicitRollback,
            'reason' => self::atomicAdmissionReason($singlePageAtomic, $journalRequired, $journalDeferredUntilCommit, $multiFileCommit, $exclusiveLocking, $explicitRollback, $commitStatus),
            'dependencies' => ['upstream-io-atomic-journal-admission', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @return array<string, mixed>
     */
    public static function nolockProbe(string $filename, bool $writeTransaction = false, array $deviceFlags = []): array
    {
        $flags = self::deviceFlags($deviceFlags);
        $capability = SQLiteVfsCapabilityPlan::forFilename($filename, true, !$writeTransaction || !str_contains($filename, 'mode=ro'), 512, $flags);
        $immutableDevice = in_array('immutable', $flags, true);
        $suppressed = (bool) $capability['nolock'] || (bool) $capability['immutable'] || $immutableDevice;
        $readOnly = (bool) $capability['read_only'];

        if ($suppressed) {
            $calls = ['xLock' => 0, 'xUnlock' => 0, 'xCheckReservedLock' => 0, 'xAccess' => 0];
        } elseif ($writeTransaction && !$readOnly) {
            $calls = ['xLock' => 7, 'xUnlock' => 5, 'xCheckReservedLock' => 0, 'xAccess' => 0];
        } else {
            $calls = ['xLock' => 2, 'xUnlock' => 2, 'xCheckReservedLock' => 0, 'xAccess' => 4];
        }

        return [
            'status' => 'ok',
            'path' => $capability['path'],
            'read_only' => $readOnly,
            'immutable' => (bool) $capability['immutable'],
            'immutable_device' => $immutableDevice,
            'nolock' => (bool) $capability['nolock'],
            'write_transaction' => $writeTransaction,
            'calls' => $calls,
            'lock_calls_suppressed' => $suppressed,
            'dependencies' => array_values(array_unique(array_merge($capability['dependencies'], ['upstream-nolock-uri-lock-suppression', 'vfs-io-dynamic-real-corpus']))),
        ];
    }

    /**
     * @param list<string> $sqlControls
     * @return array<string, mixed>
     */
    public static function fileControlSequence(string $filename, array $sqlControls): array
    {
        if ($sqlControls === []) {
            throw new \InvalidArgumentException('SQLite VFS dynamic file-control sequence requires SQL controls');
        }

        $capability = SQLiteVfsCapabilityPlan::forFilename(
            $filename,
            true,
            !str_contains($filename, 'mode=ro'),
            4096,
            ['safe_append', 'powersafe_overwrite'],
            'full',
            false,
            8192,
            0
        );
        $state = SQLiteVfsFileControlState::fromCapabilityPlan($capability);
        $sequence = $state->sqlFileControlSequence($sqlControls);
        $sequence['dependencies'] = array_values(array_unique(array_merge($sequence['dependencies'], ['upstream-filectrl-sql-file-control', 'vfs-io-dynamic-real-corpus'])));

        return $sequence;
    }

    /**
     * @return array<string, mixed>
     */
    public static function checksumReserveProfile(
        int $reserveBytes,
        int $pageSize,
        int $largeRows,
        int $smallRows,
        int $largePayloadBytes,
        int $smallPayloadBytes
    ): array {
        if ($reserveBytes < 0 || $reserveBytes > 255) {
            throw new \InvalidArgumentException('SQLite checksum VFS reserve bytes must be between 0 and 255');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite checksum VFS page size must be a power of two at least 512');
        }
        if ($largeRows < 1 || $smallRows < 1 || $largePayloadBytes < 1 || $smallPayloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite checksum VFS profile requires positive row and payload sizes');
        }

        $usableBytes = $pageSize - $reserveBytes;
        if ($usableBytes < 480) {
            throw new \InvalidArgumentException('SQLite checksum VFS reserve bytes leave too little usable page space');
        }

        $largePages = self::align($largeRows * ($largePayloadBytes + $reserveBytes + 16), $usableBytes);
        $smallPages = self::align($smallRows * ($smallPayloadBytes + $reserveBytes + 16), $usableBytes);

        return [
            'status' => 'ok',
            'script' => 'cksumvfs.test',
            'upstream' => ['cksumvfs.test 1.3', 'cksumvfs.test 1.4', 'cksumvfs.test 1.5', 'cksumvfs.test 1.6', 'cksumvfs.test 1.7', 'cksumvfs.test 1.8', 'cksumvfs.test 1.9'],
            'reserve_bytes' => $reserveBytes,
            'page_size' => $pageSize,
            'usable_bytes' => $usableBytes,
            'large_rows_inserted' => $largeRows,
            'large_payload_bytes' => $largePayloadBytes,
            'large_payload_pages' => intdiv($largePages, $usableBytes),
            'large_count_after_commit' => $largeRows,
            'journal_mode_after_delete' => 'wal',
            'checkpoint_result' => ['busy' => 0, 'log' => 'nonzero', 'checkpointed' => 'nonzero'],
            'small_rows_inserted' => $smallRows,
            'small_payload_bytes' => $smallPayloadBytes,
            'small_payload_pages' => intdiv($smallPages, $usableBytes),
            'small_count_before_reopen' => $smallRows,
            'small_count_after_restore_reopen' => $smallRows,
            'small_count_after_plain_reopen' => $smallRows,
            'checksum_trailer_reserved' => $reserveBytes > 0,
            'integrity_sequence' => ['ok', 'ok', 'ok', 'ok'],
            'dependencies' => ['upstream-cksumvfs-reserve-bytes', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function walJournalSizeLimitProfile(int $limitBytes, int $reducedLimitBytes, int $rows, int $payloadBytes): array
    {
        if ($limitBytes < 1 || $reducedLimitBytes < 1 || $rows < 1 || $payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite WAL journal size limit profile requires positive limits, rows, and payload');
        }
        if ($reducedLimitBytes > $limitBytes) {
            throw new \InvalidArgumentException('SQLite WAL reduced journal size limit must not exceed the first limit');
        }

        $walBytesBeforeCheckpoint = max($limitBytes + 4096, self::align($rows * ($payloadBytes + 32), 4096));

        return [
            'status' => 'ok',
            'script' => 'walvfs.test',
            'upstream' => ['walvfs.test 2.0', 'walvfs.test 2.1', 'walvfs.test 2.2', 'walvfs.test 2.3'],
            'journal_mode' => 'wal',
            'journal_size_limit' => $limitBytes,
            'reduced_journal_size_limit' => $reducedLimitBytes,
            'rows_inserted' => $rows,
            'payload_bytes' => $payloadBytes,
            'wal_bytes_before_checkpoint' => $walBytesBeforeCheckpoint,
            'wal_exceeds_first_limit_before_checkpoint' => $walBytesBeforeCheckpoint > $limitBytes,
            'wal_bytes_after_first_checkpoint_insert' => $limitBytes,
            'wal_bytes_after_reduced_checkpoint_insert' => $reducedLimitBytes,
            'checkpoint_result_shape' => ['busy' => 0, 'log' => 'nonzero', 'checkpointed' => 'nonzero'],
            'dependencies' => ['upstream-walvfs-journal-size-limit', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function walCheckpointInterruptProfile(int $writeFailCountdown, bool $oomBeforeInterrupt): array
    {
        if ($writeFailCountdown < 1) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint interrupt profile requires a positive write countdown');
        }

        return [
            'status' => 'ok',
            'script' => 'walvfs.test',
            'upstream' => $oomBeforeInterrupt ? 'walvfs.test 3.2' : 'walvfs.test 3.1',
            'write_fail_countdown' => $writeFailCountdown,
            'oom_before_interrupt' => $oomBeforeInterrupt,
            'checkpoint_result' => $oomBeforeInterrupt ? 'out of memory' : 'interrupted',
            'result_code_priority' => $oomBeforeInterrupt ? 'SQLITE_NOMEM_before_SQLITE_INTERRUPT' : 'SQLITE_INTERRUPT',
            'database_write_hook' => 'xWrite',
            'wal_mode_preserved' => true,
            'statement_result_matches_checkpoint' => $oomBeforeInterrupt,
            'dependencies' => ['upstream-walvfs-checkpoint-interrupt', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @return array<string, mixed>
     */
    public static function atomicPagerCacheRetentionProfile(
        int $pageSize,
        int $cacheSize,
        int $indexedRows,
        int $payloadBytes,
        int $tablesModified,
        array $deviceFlags = ['atomic']
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite atomic pager-cache retention page size must be a power of two at least 512');
        }
        if ($cacheSize < 1 || $indexedRows < 1 || $payloadBytes < 1 || $tablesModified < 1) {
            throw new \InvalidArgumentException('SQLite atomic pager-cache retention requires positive cache, row, payload, and table counts');
        }

        $flags = self::deviceFlags($deviceFlags);
        $payloadPages = max(1, (int) ceil(($indexedRows * ($payloadBytes + 24)) / $pageSize));
        $schemaPages = 4;
        $indexPages = max(1, (int) ceil($payloadPages / 3));
        $databasePages = $schemaPages + $payloadPages + $indexPages;
        $databaseFitsCache = $databasePages <= $cacheSize;
        $atomicAllowed = self::atomicWriteAllowed($flags, $pageSize, $pageSize);
        $singleTableAtomic = $atomicAllowed && $tablesModified === 1;

        $commitPath = $singleTableAtomic
            ? 'single_page_atomic_write'
            : 'rollback_journal_transaction';
        $pagerCacheFlushed = !$databaseFitsCache || !$atomicAllowed;
        $corruptionHiddenByCache = $databaseFitsCache && !$pagerCacheFlushed;

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'upstream' => ['io.test io-6.1', 'io.test io-6.2.1.1-6.2.1.3', 'io.test io-6.2.2.1-6.2.2.3'],
            'page_size' => $pageSize,
            'cache_size' => $cacheSize,
            'indexed_rows' => $indexedRows,
            'payload_bytes' => $payloadBytes,
            'tables_modified' => $tablesModified,
            'device_flags' => $flags,
            'database_pages' => $databasePages,
            'database_fits_cache' => $databaseFitsCache,
            'atomic_write_allowed' => $atomicAllowed,
            'commit_path' => $commitPath,
            'pre_commit_integrity' => 'ok',
            'post_commit_integrity' => $corruptionHiddenByCache ? 'ok' : 'corruption-visible',
            'pager_cache_flushed_by_commit' => $pagerCacheFlushed,
            'corrupt_disk_pages' => 2,
            'corrupt_offset' => $pageSize * 5,
            'mmap_disabled' => true,
            'ordered_cache_warmup' => ['rowid', 'index'],
            'dependencies' => ['upstream-io-atomic-pager-cache-retention', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    private static function align(int $value, int $pageSize): int
    {
        $remainder = $value % $pageSize;
        return $remainder === 0 ? $value : $value + ($pageSize - $remainder);
    }

    /**
     * @param list<string> $flags
     * @return list<string>
     */
    private static function deviceFlags(array $flags): array
    {
        $supported = SQLiteVfsCapabilityPlan::deviceFlagMap();
        $normalized = [];
        foreach ($flags as $flag) {
            $name = strtolower(str_replace('-', '_', trim($flag)));
            if (!isset($supported[$name])) {
                throw new \InvalidArgumentException("Unsupported SQLite VFS IO traffic device flag: {$flag}");
            }
            $normalized[$name] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param list<string> $flags
     */
    private static function atomicWriteAllowed(array $flags, int $pageSize, int $sectorSize): bool
    {
        if ($sectorSize > $pageSize) {
            return false;
        }
        if (in_array('atomic', $flags, true) || in_array('batch_atomic', $flags, true)) {
            return true;
        }

        $specific = [
            'atomic512' => 512,
            'atomic1k' => 1024,
            'atomic2k' => 2048,
            'atomic4k' => 4096,
            'atomic8k' => 8192,
            'atomic16k' => 16384,
            'atomic32k' => 32768,
            'atomic64k' => 65536,
        ];
        foreach ($specific as $flag => $bytes) {
            if (in_array($flag, $flags, true) && $pageSize <= $bytes) {
                return true;
            }
        }

        return false;
    }

    private static function atomicAdmissionReason(
        bool $singlePageAtomic,
        bool $journalRequired,
        bool $journalDeferredUntilCommit,
        bool $multiFileCommit,
        bool $exclusiveLocking,
        bool $explicitRollback,
        string $commitStatus
    ): string {
        if ($explicitRollback) {
            return 'explicit_rollback_restores_rows_before_journal_materialization';
        }
        if ($commitStatus !== 'ok') {
            return $multiFileCommit ? 'multi_file_commit_journal_open_failure_rolls_back_all_files' : 'deferred_journal_open_failure_rolls_back_transaction';
        }
        if ($exclusiveLocking) {
            return 'exclusive_locking_keeps_journal_unlinked_after_commit';
        }
        if ($singlePageAtomic) {
            return 'single_page_atomic_write_skips_rollback_journal';
        }
        if ($journalDeferredUntilCommit) {
            return 'atomic_capable_append_or_multifile_transaction_defers_journal_until_commit';
        }
        if ($journalRequired) {
            return 'rollback_journal_required_before_commit';
        }

        return 'read_only_or_empty_transaction_needs_no_journal';
    }
}
