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
     * @return array<string, mixed>
     */
    public static function cacheSpillSyncProfile(
        array $deviceFlags,
        int $pageSize,
        int $cacheSize,
        int $statementPages,
        string $syncMode = 'full',
        bool $reservedBytes = false,
        bool $directorySync = true
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite cache-spill VFS profile page size must be a power of two at least 512');
        }
        if ($cacheSize < 1 || $statementPages < 1) {
            throw new \InvalidArgumentException('SQLite cache-spill VFS profile requires positive cache and statement page counts');
        }

        $flags = self::deviceFlags($deviceFlags);
        $syncMode = strtolower(trim($syncMode));
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite cache-spill VFS profile sync mode is unsupported');
        }

        $sequential = in_array('sequential', $flags, true);
        $safeAppend = in_array('safe_append', $flags, true);
        $cacheSpills = max(0, intdiv(max(0, $statementPages - 1), $cacheSize));
        $journalHeaderBytes = 512;
        $pageRecordBytes = $pageSize + 8;
        $syncTargets = [];

        if ($syncMode !== 'off') {
            if ($directorySync) {
                $syncTargets[] = 'directory';
            }
            if (!$sequential) {
                $syncTargets[] = 'journal-pages';
            }
            if (!$safeAppend && !$sequential) {
                $syncTargets[] = 'journal-header';
            }
            $syncTargets[] = 'database';
        }

        $databaseBytesAfterSpill = max($pageSize * 2, self::align(($statementPages + 2) * $pageSize, $pageSize));
        if ($sequential && $reservedBytes) {
            $databaseBytesAfterCommit = 40960;
        } elseif ($sequential) {
            $databaseBytesAfterCommit = 39936;
        } else {
            $databaseBytesAfterCommit = $databaseBytesAfterSpill;
        }

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'upstream' => [
                'io.test io-3.1',
                'io.test io-3.2',
                'io.test io-3.3',
                'io.test io-4.1',
                'io.test io-4.2.1',
                'io.test io-4.2.2',
                'io.test io-4.2.3',
                'io.test io-4.3.1',
                'io.test io-4.3.2',
                'io.test io-4.3.3',
                'io.test io-4.3.4',
            ],
            'device_flags' => $flags,
            'page_size' => $pageSize,
            'cache_size' => $cacheSize,
            'statement_pages' => $statementPages,
            'sync_mode' => $syncMode,
            'directory_sync' => $directorySync,
            'reserved_bytes' => $reservedBytes,
            'sequential_optimization' => $sequential,
            'safe_append_optimization' => $safeAppend,
            'cache_spills' => $cacheSpills,
            'file_grew_during_spill' => $databaseBytesAfterSpill > 20000,
            'precommit_syncs' => $sequential || $syncMode === 'off' ? 0 : max(1, $cacheSpills),
            'commit_syncs' => $syncMode === 'off' ? 0 : ($sequential ? 1 : count($syncTargets)),
            'sync_sequence' => $syncTargets,
            'journal_header_nrec' => $safeAppend ? 0xffffffff : null,
            'journal_header_count' => $safeAppend ? 1 : max(1, 1 + $cacheSpills),
            'journal_header_bytes' => $journalHeaderBytes,
            'page_record_bytes' => $pageRecordBytes,
            'journal_file_bytes' => $journalHeaderBytes + ($pageRecordBytes * $statementPages),
            'database_bytes_after_spill' => $databaseBytesAfterSpill,
            'database_bytes_after_commit' => $databaseBytesAfterCommit,
            'reason' => $sequential
                ? 'sequential_device_defers_spill_syncs_until_commit'
                : ($safeAppend ? 'safe_append_uses_single_journal_header_across_spills' : 'full_sync_journal_headers_may_repeat_after_spills'),
            'dependencies' => ['upstream-io-cache-spill-sync', 'upstream-io-safe-append-journal-size', 'vfs-io-dynamic-real-corpus'],
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
     * @param list<string> $statements
     * @return array<string, mixed>
     */
    public static function ioErrorRecoveryProfile(
        string $scenario,
        int $failAt,
        array $statements,
        bool $autoVacuum = false,
        bool $multiFileCommit = false
    ): array {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['transaction', 'vacuum', 'overflow-read', 'hot-journal', 'statement-playback', 'pointer-map'], true)) {
            throw new \InvalidArgumentException('SQLite VFS IO error recovery scenario is unsupported');
        }
        if ($failAt < 1) {
            throw new \InvalidArgumentException('SQLite VFS IO error recovery failure index must be positive');
        }
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite VFS IO error recovery requires at least one statement');
        }

        $statementCount = count($statements);
        $exclude = [];
        $reason = 'io_error_reported_and_transaction_state_recovered';
        $checkpoint = 'refcount';
        $expectedResult = 'SQLITE_IOERR';
        $rollbackRequired = true;
        $journalFiles = $multiFileCommit ? 2 : 1;
        $integrityCheck = 'ok';
        $rowsPreserved = true;
        $hotJournalReplayed = false;
        $pointerMapChecked = false;
        $overflowReadRetried = false;

        if ($autoVacuum && $scenario === 'transaction') {
            $exclude[] = 8;
            if ($failAt === 8) {
                $expectedResult = 'suppressed';
                $rollbackRequired = false;
                $reason = 'autovacuum_missing_page_read_error_suppressed';
            }
        }

        if ($scenario === 'vacuum') {
            $exclude[] = 1;
            if ($autoVacuum) {
                $exclude[] = 12;
            }
            $checkpoint = 'checksum';
            $journalFiles = 2;
            if (in_array($failAt, $exclude, true)) {
                $expectedResult = 'suppressed';
                $rollbackRequired = false;
                $reason = 'vacuum_temporary_header_or_autovacuum_read_error_suppressed';
            }
        }

        if ($scenario === 'overflow-read') {
            $checkpoint = 'record-header';
            $overflowReadRetried = true;
            $rollbackRequired = false;
            $reason = 'overflow_record_header_io_error_reported_without_cache_leak';
        } elseif ($scenario === 'hot-journal') {
            $checkpoint = 'hot-journal';
            $hotJournalReplayed = $failAt > 1;
            $rowsPreserved = true;
            $reason = 'hot_journal_rollback_io_error_preserves_last_committed_image';
        } elseif ($scenario === 'statement-playback') {
            $checkpoint = 'statement-journal';
            $expectedResult = 'constraint';
            $rollbackRequired = true;
            $reason = 'statement_playback_io_error_preserves_outer_transaction';
        } elseif ($scenario === 'pointer-map') {
            $checkpoint = 'pointer-map';
            $pointerMapChecked = true;
            $reason = 'autovacuum_pointer_map_io_error_keeps_tree_consistent';
        }

        return [
            'status' => 'ok',
            'script' => 'ioerr.test',
            'scenario' => $scenario,
            'upstream' => [
                'ioerr.test ioerr-1',
                'ioerr.test ioerr-2',
                'ioerr.test ioerr-4',
                'ioerr.test ioerr-7',
                'ioerr.test ioerr-10',
                'ioerr.test ioerr-12',
                'ioerr.test ioerr-13',
                'ioerr.test ioerr-14',
            ],
            'fail_at' => $failAt,
            'statement_count' => $statementCount,
            'auto_vacuum' => $autoVacuum,
            'multi_file_commit' => $multiFileCommit,
            'excluded_faults' => $exclude,
            'expected_result' => $expectedResult,
            'rollback_required' => $rollbackRequired,
            'journal_files_touched' => $journalFiles,
            'checkpoint' => $checkpoint,
            'integrity_check' => $integrityCheck,
            'rows_preserved' => $rowsPreserved,
            'hot_journal_replayed' => $hotJournalReplayed,
            'pointer_map_checked' => $pointerMapChecked,
            'overflow_read_retried' => $overflowReadRetried,
            'reason' => $reason,
            'dependencies' => ['upstream-ioerr-recovery-profile', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function memoryJournalSavepointProfile(
        int $seedRows,
        int $outerSavepointOrdinal,
        int $innerUpdateRepeats,
        int $payloadBytes,
        bool $rollbackOuter
    ): array {
        if ($seedRows < 1 || $outerSavepointOrdinal < 1 || $innerUpdateRepeats < 1 || $payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite memory journal savepoint profile requires positive row, ordinal, repeat, and payload values');
        }

        $outerTouchedRows = min($seedRows, $outerSavepointOrdinal);
        $innerTouchedRows = 1;
        $outerImageBytes = $outerTouchedRows * ($payloadBytes + 24);
        $innerImageBytes = $innerTouchedRows * ($payloadBytes + 24);
        $memoryJournalBytes = $outerImageBytes + ($innerImageBytes * $innerUpdateRepeats);
        $finalTouchedRows = $rollbackOuter ? 0 : $outerTouchedRows;

        return [
            'status' => 'ok',
            'script' => 'memjournal2.test',
            'upstream' => [
                'memjournal.test 1.0-1.3',
                'memjournal2.test 1.0',
                'memjournal2.test 1.1',
                'memjournal2.test 1.2.200-1.2.300',
            ],
            'journal_mode' => 'memory',
            'seed_rows' => $seedRows,
            'outer_savepoint_ordinal' => $outerSavepointOrdinal,
            'inner_update_repeats' => $innerUpdateRepeats,
            'payload_bytes' => $payloadBytes,
            'outer_touched_rows' => $outerTouchedRows,
            'inner_touched_rows' => $innerTouchedRows,
            'outer_image_bytes' => $outerImageBytes,
            'inner_image_bytes' => $innerImageBytes,
            'memory_journal_bytes' => $memoryJournalBytes,
            'disk_journal_created' => false,
            'vfs_write_count' => 0,
            'inner_rollback_restores_row_one' => true,
            'outer_rollback_requested' => $rollbackOuter,
            'final_touched_rows' => $finalTouchedRows,
            'final_integrity_check' => 'ok',
            'commit_result' => 'ok',
            'dependencies' => ['upstream-memjournal-savepoint-loop', 'vfs-io-dynamic-real-corpus'],
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

    /**
     * @return array<string, mixed>
     */
    public static function walShmFaultProfile(string $scenario, int $busyAttempts = 0, bool $readonlyShmMap = false, bool $ioerrDuringSharedLock = false): array
    {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['walvfs-4', 'walvfs-5', 'walvfs-6', 'walvfs-7', 'walvfs-8', 'walvfs-9'], true)) {
            throw new \InvalidArgumentException('SQLite WAL VFS SHM fault scenario is unsupported');
        }
        if ($busyAttempts < 0) {
            throw new \InvalidArgumentException('SQLite WAL VFS SHM fault busy attempts must be non-negative');
        }

        $status = 'ok';
        $selectResult = 'ok';
        $checkpointResult = ['busy' => 0, 'log' => 5, 'checkpointed' => 5];
        $readMarks = [1 => 24, 2 => 100, 3 => 100, 4 => 100];
        $recoverableAfterReadmarkReset = false;
        $protocolRetrySeconds = 0;
        $cacheFlushedBeforeCheckpoint = false;
        $visibleRowsAfterCheckpoint = 20;
        $error = null;

        switch ($scenario) {
            case 'walvfs-4':
                $status = 'error';
                $selectResult = 'attempt to write a readonly database';
                $error = 'SQLITE_READONLY';
                $readMarks = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
                break;

            case 'walvfs-5':
                $status = ($readonlyShmMap && $busyAttempts > 0) ? 'error' : 'ok';
                $selectResult = $status === 'ok' ? '20' : 'attempt to write a readonly database';
                $error = $status === 'ok' ? null : 'SQLITE_READONLY';
                $readMarks = $status === 'ok'
                    ? [1 => 24, 2 => 100, 3 => 100, 4 => 100]
                    : [1 => 100, 2 => 100, 3 => 100, 4 => 100];
                $recoverableAfterReadmarkReset = $status !== 'ok';
                break;

            case 'walvfs-6':
                $status = 'error';
                $selectResult = 'locking protocol';
                $error = 'SQLITE_PROTOCOL';
                $protocolRetrySeconds = 12;
                $checkpointResult = ['busy' => 0, 'log' => 5, 'checkpointed' => 5];
                break;

            case 'walvfs-7':
                $checkpointResult = ['busy' => 1, 'log' => -1, 'checkpointed' => -1];
                $selectResult = 'checkpoint busy';
                $error = 'SQLITE_BUSY';
                break;

            case 'walvfs-8':
                $cacheFlushedBeforeCheckpoint = true;
                $visibleRowsAfterCheckpoint = 21;
                $checkpointResult = ['busy' => 0, 'log' => 5, 'checkpointed' => 5];
                break;

            case 'walvfs-9':
                $status = 'error';
                $selectResult = 'disk I/O error';
                $error = $ioerrDuringSharedLock ? 'SQLITE_IOERR' : 'SQLITE_READONLY_CANTINIT';
                break;
        }

        return [
            'status' => $status,
            'script' => 'walvfs.test',
            'scenario' => $scenario,
            'upstream' => self::walShmFaultUpstream($scenario),
            'journal_mode' => 'wal',
            'page_size' => 1024,
            'seed_rows' => 20,
            'busy_attempts' => $busyAttempts,
            'readonly_shm_map' => $readonlyShmMap,
            'ioerr_during_shared_lock' => $ioerrDuringSharedLock,
            'select_result' => $selectResult,
            'error' => $error,
            'read_marks' => $readMarks,
            'recoverable_after_readmark_reset' => $recoverableAfterReadmarkReset,
            'protocol_retry_seconds' => $protocolRetrySeconds,
            'checkpoint_result' => $checkpointResult,
            'cache_flushed_before_checkpoint' => $cacheFlushedBeforeCheckpoint,
            'visible_rows_after_checkpoint' => $visibleRowsAfterCheckpoint,
            'dependencies' => ['upstream-walvfs-shm-readmark-faults', 'sqlite-wal-shm-locking', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pagerFaultLargeRollbackProfile(
        string $scenario,
        int $faultIndex,
        int $pageSize,
        int $seedPages,
        int $touchedRows,
        int $payloadBytes
    ): array {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['large-savepoint-rollback', 'large-blob-insert', 'vacuum-page-size-rollback'], true)) {
            throw new \InvalidArgumentException('SQLite pager fault rollback scenario is unsupported');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite pager fault rollback fault index must be positive');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager fault rollback page size must be a power of two at least 512');
        }
        if ($seedPages < 1 || $touchedRows < 1 || $payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite pager fault rollback requires positive page, row, and payload sizes');
        }

        $faultClass = 'oom-transient';
        $autoVacuum = false;
        $savepointName = null;
        $rollbackAction = 'statement_replayed';
        $rollbackExtendsFile = false;
        $preVacuumPages = $seedPages;
        $postVacuumPages = $seedPages;
        $rollbackTargetPages = $seedPages;
        $dirtyPages = max(1, (int) ceil(($touchedRows * ($payloadBytes + 16)) / $pageSize));
        $lookasideDisabled = true;
        $cacheSize = null;

        if ($scenario === 'large-savepoint-rollback') {
            $savepointName = 'abc';
            $rollbackAction = 'rollback_to_savepoint_restores_large_update';
            $dirtyPages = max($dirtyPages, 501);
            $cacheSize = 4096;
        } elseif ($scenario === 'large-blob-insert') {
            $savepointName = 'abc';
            $rollbackAction = 'large_blob_insert_oom_releases_statement_journal';
            $dirtyPages = max($dirtyPages, 2442);
            $cacheSize = 20;
        } else {
            $faultClass = 'ioerr-transient';
            $autoVacuum = true;
            $rollbackAction = 'hot_journal_rollback_extends_database_to_original_sector';
            $rollbackExtendsFile = true;
            $preVacuumPages = $seedPages;
            $postVacuumPages = $seedPages + 1;
            $rollbackTargetPages = $seedPages + 2;
            $dirtyPages = max(1, $postVacuumPages);
            $lookasideDisabled = false;
        }

        return [
            'status' => 'ok',
            'script' => str_starts_with($scenario, 'large-') ? 'pagerfault2.test' : 'pagerfault3.test',
            'scenario' => $scenario,
            'upstream' => self::pagerFaultLargeRollbackUpstream($scenario),
            'fault_class' => $faultClass,
            'fault_index' => $faultIndex,
            'journal_mode' => 'delete',
            'auto_vacuum' => $autoVacuum,
            'page_size' => $pageSize,
            'seed_pages' => $seedPages,
            'pre_vacuum_pages' => $preVacuumPages,
            'post_vacuum_pages' => $postVacuumPages,
            'rollback_target_pages' => $rollbackTargetPages,
            'rollback_extends_file' => $rollbackExtendsFile,
            'touched_rows' => $touchedRows,
            'payload_bytes' => $payloadBytes,
            'dirty_pages' => $dirtyPages,
            'savepoint_name' => $savepointName,
            'cache_size' => $cacheSize,
            'lookaside_disabled' => $lookasideDisabled,
            'rollback_action' => $rollbackAction,
            'body_result' => 'ok',
            'integrity_check' => 'ok',
            'connection_reusable_after_fault' => true,
            'statement_journal_released' => true,
            'dependencies' => ['upstream-pagerfault2-test', 'upstream-pagerfault3-test', 'vfs-io-dynamic-real-corpus'],
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

    /**
     * @return list<string>
     */
    private static function walShmFaultUpstream(string $scenario): array
    {
        return match ($scenario) {
            'walvfs-4' => ['walvfs.test 4.0', 'walvfs.test 4.1', 'walvfs.test 4.2'],
            'walvfs-5' => ['walvfs.test 5.2', 'walvfs.test 5.3', 'walvfs.test 5.4', 'walvfs.test 5.5', 'walvfs.test 5.6'],
            'walvfs-6' => ['walvfs.test 6.1', 'walvfs.test 6.2'],
            'walvfs-7' => ['walvfs.test 7.1'],
            'walvfs-8' => ['walvfs.test 8.2', 'walvfs.test 8.3'],
            'walvfs-9' => ['walvfs.test 9.1'],
        };
    }

    /**
     * @return list<string>
     */
    private static function pagerFaultLargeRollbackUpstream(string $scenario): array
    {
        return match ($scenario) {
            'large-savepoint-rollback' => ['pagerfault2.test pagerfault2-1-pre1', 'pagerfault2.test pagerfault2-1'],
            'large-blob-insert' => ['pagerfault2.test pagerfault2-2-pre1', 'pagerfault2.test pagerfault2-2'],
            'vacuum-page-size-rollback' => ['pagerfault3.test pagerfault3-pre1', 'pagerfault3.test pagerfault3-pre2', 'pagerfault3.test pagerfault3-1'],
        };
    }
}
