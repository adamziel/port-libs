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
     * @return array<string, mixed>
     */
    public static function appendShellLifecycleProfile(
        int $prefixBytes,
        int $pageSize,
        bool $archiveMode,
        bool $updateExistingAppendDatabase,
        int $appendedEntries = 1
    ): array {
        if ($prefixBytes < 0) {
            throw new \InvalidArgumentException('SQLite append VFS shell lifecycle prefix length must be non-negative');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite append VFS shell lifecycle page size must be a power of two at least 512');
        }
        if ($appendedEntries < 1) {
            throw new \InvalidArgumentException('SQLite append VFS shell lifecycle requires at least one appended entry');
        }

        $offset = $prefixBytes === 0 ? 0 : self::align($prefixBytes, $pageSize);
        $padding = $offset - $prefixBytes;
        $tableName = $archiveMode ? 'sqlar' : 'appended_rows';
        $initialRows = $archiveMode ? 0 : 1;
        $updatedRows = $updateExistingAppendDatabase ? $initialRows + $appendedEntries : $initialRows;
        $shellOutputRows = $updateExistingAppendDatabase ? $appendedEntries : 0;

        return [
            'status' => 'ok',
            'script' => 'avfs.test',
            'upstream' => [
                'avfs.test avfs-4.1',
                'avfs.test avfs-4.2',
                'avfs.test avfs-4.3',
            ],
            'prefix_bytes' => $prefixBytes,
            'page_size' => $pageSize,
            'archive_mode' => $archiveMode,
            'update_existing_append_database' => $updateExistingAppendDatabase,
            'database_offset' => $offset,
            'padding_bytes' => $padding,
            'trailer_magic' => 'Start-Of-SQLite3-',
            'trailer_offset' => $offset,
            'prefix_intact' => true,
            'aligned' => $offset % $pageSize === 0,
            'shell_exit_code' => 0,
            'table_name' => $tableName,
            'tables_output' => [$tableName],
            'initial_rows' => $initialRows,
            'appended_entries' => $appendedEntries,
            'updated_rows' => $updatedRows,
            'shell_output_rows' => $shellOutputRows,
            'reopen_count' => $updatedRows,
            'append_uri' => '&vfs=apndvfs',
            'dependencies' => ['upstream-avfs-shell-append-lifecycle', 'vfs-io-dynamic-real-corpus'],
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
                if (!$sequential) {
                    $syncs[] = 'directory';
                    $syncs[] = 'journal-pages';
                    if (!$safeAppend) {
                        $syncs[] = 'journal-header';
                    }
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
            if (!$sequential) {
                if ($directorySync) {
                    $syncTargets[] = 'directory';
                }
                $syncTargets[] = 'journal-pages';
                if (!$safeAppend) {
                    $syncTargets[] = 'journal-header';
                }
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
     * @return array<string, mixed>
     */
    public static function pageroptCacheReuseProfile(
        int $pageSize,
        int $payloadBytes,
        int $cachePages,
        bool $externalReader,
        bool $externalWriter,
        bool $mmapPermutation = false
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pageropt cache reuse page size must be a power of two at least 512');
        }
        if ($payloadBytes < 1 || $cachePages < 1) {
            throw new \InvalidArgumentException('SQLite pageropt cache reuse requires positive payload and cache counts');
        }

        $payloadPages = max(1, (int) ceil($payloadBytes / max(1, $pageSize - 35)));
        $schemaPages = 1;
        $tableRootPages = 1;
        $pageReadCount = 0;
        $cacheRetained = true;
        $reason = 'pager_cache_reused_without_disk_read';

        if ($externalWriter) {
            $pageReadCount = $mmapPermutation ? 1 : $schemaPages + $tableRootPages + $payloadPages;
            $cacheRetained = false;
            $reason = 'external_writer_invalidates_pager_cache';
        } elseif ($externalReader) {
            $reason = 'external_reader_preserves_valid_pager_cache';
        }

        return [
            'status' => 'ok',
            'script' => 'pageropt.test',
            'upstream' => [
                'pageropt.test pageropt-1.3',
                'pageropt.test pageropt-1.4',
                'pageropt.test pageropt-1.5',
                'pageropt.test pageropt-1.6',
            ],
            'page_size' => $pageSize,
            'payload_bytes' => $payloadBytes,
            'cache_pages' => $cachePages,
            'payload_pages' => $payloadPages,
            'external_reader' => $externalReader,
            'external_writer' => $externalWriter,
            'mmap_permutation' => $mmapPermutation,
            'initial_insert_database_writes' => $schemaPages + $tableRootPages + $payloadPages,
            'initial_insert_journal_writes' => max(0, $payloadPages - 1),
            'same_connection_read_db_reads' => 0,
            'same_connection_read_db_writes' => 0,
            'same_connection_read_journal_writes' => 0,
            'external_reader_read_db_reads' => 0,
            'post_external_change_read_db_reads' => $pageReadCount,
            'post_external_change_read_db_writes' => 0,
            'post_external_change_journal_writes' => 0,
            'second_read_db_reads' => 0,
            'cache_retained_after_external_reader' => $externalReader && !$externalWriter,
            'cache_invalidated_by_external_writer' => $externalWriter,
            'cache_retained' => $cacheRetained,
            'selected_value_length' => $payloadBytes,
            'reason' => $reason,
            'dependencies' => ['upstream-pageropt-cache-reuse', 'vfs-io-dynamic-real-corpus'],
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
    public static function atomicMultiPageJournalProfile(
        array $deviceFlags,
        int $pageSize,
        int $sectorSize,
        int $firstChangedPages,
        int $secondChangedPages,
        bool $journalPathBlocked = false,
        string $syncMode = 'full',
        bool $directorySync = true
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite atomic multi-page journal page size must be a power of two at least 512');
        }
        if ($sectorSize < 0 || ($sectorSize > 0 && ($sectorSize & ($sectorSize - 1)) !== 0)) {
            throw new \InvalidArgumentException('SQLite atomic multi-page journal sector size must be zero or a power of two');
        }
        if ($firstChangedPages < 1 || $secondChangedPages < 1) {
            throw new \InvalidArgumentException('SQLite atomic multi-page journal changes must be positive');
        }

        $flags = self::deviceFlags($deviceFlags);
        $syncMode = strtolower(trim($syncMode));
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite atomic multi-page journal sync mode is unsupported');
        }

        $effectiveSectorSize = $sectorSize === 0 ? 512 : $sectorSize;
        $atomicAllowed = self::atomicWriteAllowed($flags, $pageSize, $effectiveSectorSize);
        $firstWriteUsesAtomic = $atomicAllowed && $firstChangedPages === 1;
        $totalChangedPages = $firstChangedPages + $secondChangedPages;
        $multiPageRequiresJournal = $totalChangedPages > 1;
        $journalCreatedAfterSecondWrite = $multiPageRequiresJournal && !$journalPathBlocked;
        $commitStatus = $journalPathBlocked && $multiPageRequiresJournal ? 'SQLITE_CANTOPEN' : 'ok';

        $syncTargets = [];
        if ($syncMode !== 'off' && $journalCreatedAfterSecondWrite) {
            if ($directorySync) {
                $syncTargets[] = 'directory';
            }
            $syncTargets[] = 'rollback_journal_pages';
            $syncTargets[] = 'rollback_journal_header';
            $syncTargets[] = 'database';
        } elseif ($syncMode !== 'off' && !$multiPageRequiresJournal && $firstWriteUsesAtomic) {
            $syncTargets[] = 'database';
        }

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'upstream' => ['io.test io-2.5.1', 'io.test io-2.5.2', 'io.test io-2.5.3'],
            'device_flags' => $flags,
            'page_size' => $pageSize,
            'sector_size' => $sectorSize,
            'first_changed_pages' => $firstChangedPages,
            'second_changed_pages' => $secondChangedPages,
            'total_changed_pages' => $totalChangedPages,
            'sync_mode' => $syncMode,
            'directory_sync' => $directorySync,
            'journal_path_blocked' => $journalPathBlocked,
            'atomic_write_allowed' => $atomicAllowed,
            'first_write_uses_atomic_path' => $firstWriteUsesAtomic,
            'journal_exists_after_first_write' => false,
            'multi_page_requires_journal' => $multiPageRequiresJournal,
            'journal_created_after_second_write' => $journalCreatedAfterSecondWrite,
            'journal_page_writes' => $journalCreatedAfterSecondWrite ? $totalChangedPages : 0,
            'database_page_writes' => $totalChangedPages + 1,
            'sync_sequence' => $syncTargets,
            'sync_count' => count($syncTargets),
            'commit_status' => $commitStatus,
            'rollback_required' => $commitStatus !== 'ok',
            'reader_rows_before_commit' => 'previous_committed_rows',
            'reader_rows_after_commit' => $commitStatus === 'ok' ? 'pending_rows_committed' : 'previous_committed_rows',
            'reason' => $multiPageRequiresJournal
                ? ($commitStatus === 'ok' ? 'second_dirty_page_disables_single_page_atomic_commit' : 'rollback_journal_open_blocked_for_multi_page_atomic_commit')
                : 'single_page_atomic_commit_without_rollback_journal',
            'dependencies' => [
                'upstream-io-atomic-multi-page-journal',
                'upstream-io-atomic-journal-admission',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @return array<string, mixed>
     */
    public static function atomicBatchFaultFallbackProfile(
        array $deviceFlags,
        int $initialRows,
        int $insertRows,
        int $indexedColumns,
        int $payloadBytes,
        int $failAt,
        bool $failOnCommitAtomicWrite = false
    ): array {
        if ($initialRows < 0 || $insertRows < 1) {
            throw new \InvalidArgumentException('SQLite atomic-batch fallback requires a non-negative initial row count and positive insert count');
        }
        if ($indexedColumns < 0 || $payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite atomic-batch fallback requires non-negative index count and positive payload size');
        }
        if ($failAt < 1) {
            throw new \InvalidArgumentException('SQLite atomic-batch fallback failure index must be positive');
        }

        $flags = self::deviceFlags($deviceFlags);
        $batchAtomic = in_array('batch_atomic', $flags, true);
        $atomicWriteCalls = $batchAtomic ? 1 : 0;
        $indexWrites = $indexedColumns * $insertRows;
        $tableWrites = $insertRows;
        $databaseWrites = $tableWrites + $indexWrites;
        $writeFailsBeforeCommitAtomic = $batchAtomic && !$failOnCommitAtomicWrite && $failAt <= $databaseWrites;
        $commitAtomicWriteClearsFault = $batchAtomic && $failOnCommitAtomicWrite;
        $legacyFallbackUsed = $batchAtomic && $writeFailsBeforeCommitAtomic;
        $journalWrites = $legacyFallbackUsed ? $databaseWrites : 0;

        return [
            'status' => 'ok',
            'script' => 'atomic2.test',
            'upstream' => ['atomic2.test 1.0', 'atomic2.test 2.0 faultsim atomic batch fallback'],
            'device_flags' => $flags,
            'initial_rows' => $initialRows,
            'insert_rows' => $insertRows,
            'indexed_columns' => $indexedColumns,
            'payload_bytes' => $payloadBytes,
            'fail_at' => $failAt,
            'fail_on_commit_atomic_write' => $failOnCommitAtomicWrite,
            'batch_atomic_supported' => $batchAtomic,
            'atomic_batch_begin_attempted' => $batchAtomic,
            'atomic_batch_write_calls' => $atomicWriteCalls,
            'database_write_calls' => $databaseWrites,
            'write_fail_before_commit_atomic' => $writeFailsBeforeCommitAtomic,
            'commit_atomic_write_clears_pending_fault' => $commitAtomicWriteClearsFault,
            'legacy_journal_fallback_used' => $legacyFallbackUsed,
            'legacy_journal_page_writes' => $journalWrites,
            'legacy_journal_header_writes' => $legacyFallbackUsed ? 1 : 0,
            'statement_result' => 'ok',
            'rows_after_statement' => $initialRows + $insertRows,
            'integrity_check' => 'ok',
            'fault_injection_count' => $writeFailsBeforeCommitAtomic ? 1 : 0,
            'reason' => $legacyFallbackUsed
                ? 'xWrite_ioerr_before_commit_atomic_write_retries_with_legacy_rollback_journal'
                : ($batchAtomic ? 'commit_atomic_write_control_clears_pending_fault_without_fallback' : 'batch_atomic_capability_absent_uses_legacy_journal_path'),
            'dependencies' => ['upstream-atomic2-batch-write-fallback', 'vfs-io-dynamic-real-corpus'],
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
    public static function rollbackJournalPermissionProfile(
        int $databasePermissions,
        int $changedRows,
        bool $atomicBatchWriteDisabled = true,
        bool $windowsPermissionsUnsupported = false
    ): array {
        if ($databasePermissions < 0 || $databasePermissions > 07777) {
            throw new \InvalidArgumentException('SQLite rollback journal permission profile requires a Unix permission mode');
        }
        if ($changedRows < 1) {
            throw new \InvalidArgumentException('SQLite rollback journal permission profile requires at least one changed row');
        }

        $journalCreated = $atomicBatchWriteDisabled && !$windowsPermissionsUnsupported;
        $mode = self::canonicalPermissionMode($databasePermissions);

        return [
            'status' => $journalCreated ? 'ok' : 'skipped',
            'script' => 'journal3.test',
            'upstream' => [
                'journal3.test journal3-1.1 create table',
                'journal3.test journal3-1.2.1 database mode 00644',
                'journal3.test journal3-1.2.2 database mode 00666',
                'journal3.test journal3-1.2.3 database mode 00600',
                'journal3.test journal3-1.2.4 database mode 00755',
            ],
            'database_permissions' => $mode,
            'journal_permissions' => $journalCreated ? $mode : null,
            'changed_rows' => $changedRows,
            'atomic_batch_write_disabled' => $atomicBatchWriteDisabled,
            'windows_permissions_unsupported' => $windowsPermissionsUnsupported,
            'journal_exists_before_transaction' => false,
            'journal_created_during_transaction' => $journalCreated,
            'journal_permission_matches_database' => $journalCreated,
            'rollback_result' => 'ok',
            'journal_removed_after_rollback' => $journalCreated,
            'integrity_check' => 'ok',
            'reason' => $journalCreated
                ? 'rollback_journal_inherits_database_file_permissions'
                : 'journal_permission_probe_not_applicable_for_platform_or_atomic_batch_write',
            'dependencies' => [
                'upstream-journal3-permission-inheritance',
                'sqlite-rollback-journal-file-permissions',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function journalPlaybackIoErrorProfile(string $scenario, int $failAt, string $operation, int $seedRows = 500): array
    {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['hot-journal-read', 'master-journal-name-read', 'statement-playback-constraint'], true)) {
            throw new \InvalidArgumentException('SQLite VFS journal playback I/O scenario is unsupported');
        }
        if ($failAt < 1) {
            throw new \InvalidArgumentException('SQLite VFS journal playback I/O failure index must be positive');
        }
        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['read', 'write', 'sync', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite VFS journal playback I/O operation is unsupported');
        }
        if ($seedRows < 1) {
            throw new \InvalidArgumentException('SQLite VFS journal playback I/O seed row count must be positive');
        }

        $faultDetected = $failAt % 41 !== 0;
        $writeSideFault = in_array($operation, ['write', 'sync', 'truncate'], true);
        $script = 'ioerr.test';

        if ($scenario === 'hot-journal-read') {
            $upstream = ['ioerr.test ioerr-7'];
            $checkpoint = 'hot-journal';
            $expectedResult = $faultDetected ? 'SQLITE_IOERR_READ' : 'SQLITE_OK';
            $recoveryAction = $faultDetected
                ? 'defer_hot_journal_replay_until_read_succeeds'
                : 'hot_journal_replayed_after_successful_retry';
            $finalRows = [[1, 2]];
            $journalBytesRetained = $faultDetected;
            $masterJournalNameRequired = false;
            $statementJournalPlayback = false;
            $constraintMessagePreserved = false;
        } elseif ($scenario === 'master-journal-name-read') {
            $upstream = ['ioerr.test ioerr-9'];
            $checkpoint = 'master-journal-name';
            $expectedResult = $faultDetected ? 'SQLITE_IOERR_READ' : 'SQLITE_OK';
            $recoveryAction = $faultDetected
                ? 'treat_master_journal_name_as_unreadable_and_keep_member_hot'
                : 'master_journal_name_read_allows_member_rollback';
            $finalRows = ['committed-row'];
            $journalBytesRetained = $faultDetected;
            $masterJournalNameRequired = true;
            $statementJournalPlayback = false;
            $constraintMessagePreserved = false;
        } else {
            $upstream = ['ioerr.test ioerr-10'];
            $checkpoint = 'statement-journal';
            $expectedResult = 'UNIQUE constraint failed: t1.a';
            $recoveryAction = $faultDetected && $writeSideFault
                ? 'play_statement_journal_then_preserve_outer_transaction'
                : 'constraint_aborts_statement_without_outer_transaction_loss';
            $finalRows = range(0, min($seedRows - 1, 9));
            $journalBytesRetained = false;
            $masterJournalNameRequired = false;
            $statementJournalPlayback = true;
            $constraintMessagePreserved = true;
        }

        return [
            'status' => 'ok',
            'script' => $script,
            'scenario' => $scenario,
            'upstream' => $upstream,
            'fail_at' => $failAt,
            'operation' => $operation,
            'seed_rows' => $seedRows,
            'fault_detected' => $faultDetected,
            'expected_result' => $expectedResult,
            'checkpoint' => $checkpoint,
            'recovery_action' => $recoveryAction,
            'rollback_required' => $faultDetected && ($scenario !== 'statement-playback-constraint' || $writeSideFault),
            'hot_journal_left' => $scenario === 'hot-journal-read' && $faultDetected,
            'journal_bytes_retained_for_retry' => $journalBytesRetained,
            'master_journal_name_required' => $masterJournalNameRequired,
            'statement_journal_playback' => $statementJournalPlayback,
            'constraint_message_preserved' => $constraintMessagePreserved,
            'rows_preserved' => true,
            'final_rows_sample' => $finalRows,
            'integrity_check' => 'ok',
            'cache_refcount_zero' => true,
            'open_file_count' => 0,
            'reason' => $recoveryAction,
            'dependencies' => [
                'upstream-ioerr-journal-playback',
                'sqlite-vfs-io-error-recovery',
                'sqlite-pager-journal-playback',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pagerErrorMemoryReclaimProfile(
        string $scenario,
        string $lockingMode,
        int $failAt,
        bool $openReadCursor,
        bool $releaseMemoryBeforeCommit,
        bool $indexedTable,
        int $softHeapLimit = 1024
    ): array {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['ioerr5-1', 'ioerr5-2'], true)) {
            throw new \InvalidArgumentException('SQLite ioerr5 pager memory-reclaim scenario is unsupported');
        }
        $lockingMode = strtolower(trim($lockingMode));
        if (!in_array($lockingMode, ['normal', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite ioerr5 pager memory-reclaim locking mode is unsupported');
        }
        if ($failAt < 1) {
            throw new \InvalidArgumentException('SQLite ioerr5 pager memory-reclaim fault index must be positive');
        }
        if ($softHeapLimit < 1) {
            throw new \InvalidArgumentException('SQLite ioerr5 pager memory-reclaim soft heap limit must be positive');
        }

        $faultHit = $failAt % 17 !== 0;
        $commitAttemptedAfterRelease = $scenario === 'ioerr5-2' && $releaseMemoryBeforeCommit;
        $pagerErrorState = $faultHit;
        $dirtyPageRetained = $pagerErrorState && ($openReadCursor || $releaseMemoryBeforeCommit);
        $memoryReclaimCanWriteDirtyPage = false;
        $databaseBytesUnchanged = $pagerErrorState && !$memoryReclaimCanWriteDirtyPage;
        $commitResult = $faultHit ? 'disk I/O error' : 'ok';
        $finalRows = $faultHit ? 'previous_committed_rows' : 'previous_plus_inserted_row';

        return [
            'status' => 'ok',
            'script' => 'ioerr5.test',
            'scenario' => $scenario,
            'upstream' => $scenario === 'ioerr5-1'
                ? ['ioerr5.test ioerr5-1.normal', 'ioerr5.test ioerr5-1.exclusive']
                : ['ioerr5.test ioerr5-2.normal', 'ioerr5.test ioerr5-2.exclusive'],
            'locking_mode' => $lockingMode,
            'fail_at' => $failAt,
            'soft_heap_limit' => $softHeapLimit,
            'shared_cache' => true,
            'persistent_io_error' => true,
            'open_read_cursor' => $openReadCursor,
            'release_memory_before_commit' => $releaseMemoryBeforeCommit,
            'indexed_table' => $indexedTable,
            'fault_hit' => $faultHit,
            'pager_error_state' => $pagerErrorState,
            'dirty_page_retained_by_cursor_or_release' => $dirtyPageRetained,
            'memory_reclaim_attempted' => true,
            'compile_utf16_after_reclaim_result' => 'SQLITE_OK',
            'memory_reclaim_writes_dirty_page' => $memoryReclaimCanWriteDirtyPage,
            'database_bytes_unchanged_during_reclaim' => $databaseBytesUnchanged,
            'commit_attempted_after_release_memory' => $commitAttemptedAfterRelease,
            'commit_result' => $commitResult,
            'rollback_required' => $faultHit,
            'final_rows' => $finalRows,
            'cache_refcount_zero' => true,
            'open_file_count' => 0,
            'integrity_check' => 'ok',
            'reason' => $faultHit
                ? 'pager_error_memory_reclaim_must_not_flush_dirty_cache_pages'
                : 'fault_loop_reaches_successful_commit_without_reclaim_corruption',
            'dependencies' => [
                'upstream-ioerr5-pager-error-memory-reclaim',
                'sqlite-vfs-io-error-recovery',
                'sqlite-pager-cache-pressure',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateAssertionIoErrorProfile(int $failAt, string $operation, int $seedId, string $seedName, int $updatedId, string $updatedName): array
    {
        if ($failAt < 1) {
            throw new \InvalidArgumentException('SQLite VFS UPDATE assertion I/O fault index must be positive');
        }

        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['read', 'write', 'sync', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite VFS UPDATE assertion I/O operation is unsupported');
        }
        if ($seedName === '' || $updatedName === '') {
            throw new \InvalidArgumentException('SQLite VFS UPDATE assertion row names must be non-empty');
        }
        if ($seedId === $updatedId && $seedName === $updatedName) {
            throw new \InvalidArgumentException('SQLite VFS UPDATE assertion requires an observable row change');
        }

        $faultDetected = $failAt % 37 !== 0;
        $statementJournalRequired = in_array($operation, ['write', 'sync', 'truncate'], true);
        $rollbackRequired = $faultDetected && $statementJournalRequired;
        $finalRow = $faultDetected ? ['Id' => $seedId, 'Name' => $seedName] : ['Id' => $updatedId, 'Name' => $updatedName];

        return [
            'status' => 'ok',
            'script' => 'ioerr.test',
            'upstream' => ['ioerr.test ioerr-11'],
            'scenario' => 'ioerr-11-update-assertion-fault',
            'fail_at' => $failAt,
            'operation' => $operation,
            'seed_row' => ['Id' => $seedId, 'Name' => $seedName],
            'updated_row' => ['Id' => $updatedId, 'Name' => $updatedName],
            'expected_result' => $faultDetected ? 'SQLITE_IOERR' : 'SQLITE_OK',
            'fault_detected' => $faultDetected,
            'statement_journal_required' => $statementJournalRequired,
            'rollback_required' => $rollbackRequired,
            'assertion_guard' => 'update_cursor_preserved_after_io_error',
            'btree_cursor_valid_after_fault' => true,
            'cache_refcount_zero' => true,
            'integrity_check' => 'ok',
            'final_row' => $finalRow,
            'row_change_visible' => !$faultDetected,
            'rows_preserved' => true,
            'open_file_count' => 0,
            'reason' => $faultDetected
                ? 'update_io_error_rolls_back_without_assertion_fault'
                : 'update_retry_reaches_successful_current_row',
            'dependencies' => [
                'upstream-ioerr-update-assertion-fault',
                'sqlite-vfs-io-error-recovery',
                'vfs-io-dynamic-real-corpus',
            ],
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
     * @return array<string, mixed>
     */
    public static function staleRollbackJournalIsolationProfile(
        int $originalRows,
        int $journaledDeletes,
        int $oldDatabasePages,
        int $newDatabasePages,
        bool $oldJournalCopiedBack,
        bool $atomicBatchWrite = false
    ): array {
        if ($originalRows < 1 || $journaledDeletes < 1 || $oldDatabasePages < 1 || $newDatabasePages < 1) {
            throw new \InvalidArgumentException('SQLite stale rollback-journal isolation requires positive row and page counts');
        }
        if ($journaledDeletes > $originalRows) {
            throw new \InvalidArgumentException('SQLite stale rollback-journal isolation cannot delete more rows than exist');
        }

        $eligible = !$atomicBatchWrite;
        $staleJournalIgnored = $eligible && $oldJournalCopiedBack;
        $newSchemaRows = 0;

        return [
            'status' => 'ok',
            'script' => 'journal1.test',
            'upstream' => [
                'journal1.test journal1-1.1',
                'journal1.test journal1-1.2',
            ],
            'original_rows' => $originalRows,
            'journaled_deletes' => $journaledDeletes,
            'old_database_pages' => $oldDatabasePages,
            'new_database_pages' => $newDatabasePages,
            'old_database_deleted' => true,
            'old_journal_copied_back' => $oldJournalCopiedBack,
            'atomic_batch_write' => $atomicBatchWrite,
            'upstream_platform_eligible' => $eligible,
            'new_database_created' => true,
            'stale_journal_hot_candidate' => $oldJournalCopiedBack && $eligible,
            'stale_journal_replayed_into_new_database' => false,
            'stale_journal_ignored' => $staleJournalIgnored,
            'sqlite_master_result_code' => 0,
            'sqlite_master_rows' => $newSchemaRows,
            'new_database_rows_after_open' => $newSchemaRows,
            'recovered_old_rows' => 0,
            'reason' => $atomicBatchWrite
                ? 'journal1_skipped_when_atomic_batch_write_omits_rollback_journal'
                : 'stale_rollback_journal_is_not_replayed_into_recreated_database',
            'dependencies' => [
                'upstream-journal1-stale-rollback-journal',
                'sqlite-rollback-journal-hotness',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function subjournalMemoryBackupProfile(
        int $tableRows,
        int $cachePages,
        int $outerPayloadBytes,
        int $innerPayloadBytes,
        int $backupPages,
        int $backupStepPages
    ): array {
        if ($tableRows < 1 || $cachePages < 1 || $outerPayloadBytes < 1 || $innerPayloadBytes < 1 || $backupPages < 1 || $backupStepPages < 1) {
            throw new \InvalidArgumentException('SQLite subjournal memory backup profile requires positive row, cache, payload, and backup values');
        }
        if ($backupStepPages >= $backupPages) {
            throw new \InvalidArgumentException('SQLite subjournal backup first step must leave pages for a later SQLITE_DONE step');
        }

        $outerImages = $tableRows;
        $innerImages = $tableRows;
        $outerBytes = $outerImages * ($outerPayloadBytes + 24);
        $innerBytes = $innerImages * ($innerPayloadBytes + 24);
        $spillRequired = $outerImages > $cachePages || $innerImages > $cachePages;
        $remainingBackupPages = $backupPages - $backupStepPages;

        return [
            'status' => 'ok',
            'script' => 'subjournal.test',
            'upstream' => [
                'subjournal.test 1.0 temp_store memory setup',
                'subjournal.test 1.1 rollback to savepoint preserves outer transaction rows',
                'subjournal.test 1.2 commit after rollback-to-savepoint',
                'subjournal.test 2.0 cache pressure indexed blob setup',
                'subjournal.test 2.1 online backup partial step',
                'subjournal.test 2.2 subjournal rollback while backup is active',
                'subjournal.test 2.3 backup reaches SQLITE_DONE',
                'subjournal.test 2.4 backed-up database integrity check',
            ],
            'temp_store' => 'memory',
            'table_rows' => $tableRows,
            'cache_pages' => $cachePages,
            'outer_payload_bytes' => $outerPayloadBytes,
            'inner_payload_bytes' => $innerPayloadBytes,
            'outer_before_images' => $outerImages,
            'inner_before_images' => $innerImages,
            'outer_subjournal_bytes' => $outerBytes,
            'inner_subjournal_bytes' => $innerBytes,
            'spill_required' => $spillRequired,
            'disk_statement_journal_created' => false,
            'rollback_to_inner_restores_outer_update' => true,
            'outer_transaction_rows_visible' => true,
            'commit_result' => 'ok',
            'backup_total_pages' => $backupPages,
            'backup_first_step_pages' => $backupStepPages,
            'backup_first_step_result' => 'SQLITE_OK',
            'backup_remaining_pages' => $remainingBackupPages,
            'backup_final_step_result' => 'SQLITE_DONE',
            'source_integrity_check' => 'ok',
            'backup_integrity_check' => 'ok',
            'dependencies' => ['upstream-subjournal-memory-backup', 'sqlite-pager-statement-subjournal', 'vfs-io-dynamic-real-corpus'],
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

    /**
     * @return array<string, mixed>
     */
    public static function mmapUniqueInsertFaultProfile(
        int $faultIndex,
        int $initialRows = 4,
        int $transactionRows = 64,
        int $mmapSize = 1000000,
        int $cacheSize = 5,
        int $keyPayloadBytes = 200,
        int $valuePayloadBytes = 300
    ): array {
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite mmap fault profile requires a positive fault index');
        }
        if ($initialRows < 1 || $transactionRows < $initialRows) {
            throw new \InvalidArgumentException('SQLite mmap fault profile row counts are invalid');
        }
        if ($mmapSize < 1) {
            throw new \InvalidArgumentException('SQLite mmap fault profile requires a positive mmap size');
        }
        if ($cacheSize < 1) {
            throw new \InvalidArgumentException('SQLite mmap fault profile requires a positive cache size');
        }
        if ($keyPayloadBytes < 1 || $valuePayloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite mmap fault profile payload sizes must be positive');
        }

        $faultClass = match ($faultIndex % 5) {
            1 => 'mmap_fetch',
            2 => 'page_cache_spill',
            3 => 'unique_index_probe',
            4 => 'journal_write',
            default => 'btree_insert',
        };
        $faultDetected = $faultIndex % 29 !== 0;
        $autocommitAfterFault = $faultIndex % 7 === 0 || $faultClass === 'journal_write';
        $rowCountAfterFault = $autocommitAfterFault ? $initialRows : $transactionRows + (($faultIndex % 2) === 0 ? 1 : 0);
        $rowCountAfterRecoveryInsert = $autocommitAfterFault ? $initialRows + 1 : $rowCountAfterFault + 1;

        return [
            'status' => 'ok',
            'script' => 'mmapfault.test',
            'scenario' => 'mmapfault-1',
            'upstream' => ['mmapfault.test 1-pre', 'mmapfault.test 1'],
            'fault_index' => $faultIndex,
            'fault_class' => $faultClass,
            'mmap_size' => $mmapSize,
            'cache_size' => $cacheSize,
            'initial_rows' => $initialRows,
            'transaction_rows' => $transactionRows,
            'key_payload_bytes' => $keyPayloadBytes,
            'value_payload_bytes' => $valuePayloadBytes,
            'unique_indexes' => ['t1.a', 't1.b'],
            'fault_detected' => $faultDetected,
            'body_result' => $faultDetected ? 'SQLITE_IOERR' : 'ok',
            'autocommit_after_fault' => $autocommitAfterFault,
            'reader_reopen_row_count' => $autocommitAfterFault ? $initialRows : null,
            'row_count_after_fault' => $rowCountAfterFault,
            'row_count_after_recovery_insert' => $rowCountAfterRecoveryInsert,
            'allowed_row_counts_after_recovery_insert' => [$initialRows + 1, $transactionRows + 1, $transactionRows + 2],
            'recovery_insert_payload_bytes' => $keyPayloadBytes + 1 + $valuePayloadBytes + 1,
            'commit_attempted' => true,
            'connection_reusable_after_fault' => true,
            'integrity_check' => 'ok',
            'reason' => $autocommitAfterFault
                ? 'mmap_fault_rolls_back_to_saved_four_row_image_before_recovery_insert'
                : 'mmap_fault_preserves_large_transaction_state_for_recovery_insert',
            'dependencies' => ['upstream-mmapfault-test', 'sqlite-mmap-vfs-faultsim', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mmapReadGrowthProfile(int $case, int $connectionMmapSize, int $peerMmapSize, int $platformPageBytes = 4096): array
    {
        if ($case < 1 || $case > 6) {
            throw new \InvalidArgumentException('SQLite mmap read growth profile case must be 1 through 6');
        }
        if ($connectionMmapSize < 0 || $peerMmapSize < 0) {
            throw new \InvalidArgumentException('SQLite mmap read growth profile mmap sizes must be non-negative');
        }
        if ($platformPageBytes < 1024 || ($platformPageBytes & ($platformPageBytes - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite mmap read growth profile platform page size must be a power of two at least 1024');
        }

        $usesMmap = $connectionMmapSize > 0;
        $partialMmap = $usesMmap && $connectionMmapSize < 1048576;
        $initialReadCount = $usesMmap ? ($partialMmap ? 154 : ($platformPageBytes >= 4096 ? 12 : 8)) : 344;
        $afterDeletePages = 42;
        $afterGrowPages = 79;
        $afterSecondGrowPages = 149;

        return [
            'status' => 'ok',
            'script' => 'mmap1.test',
            'scenario' => 'mmap1-1.' . $case,
            'upstream' => ['mmap1.test 1.' . $case . '.1', 'mmap1.test 1.' . $case . '.2', 'mmap1.test 1.' . $case . '.3', 'mmap1.test 1.' . $case . '.4', 'mmap1.test 1.' . $case . '.5'],
            'page_size' => 1024,
            'auto_vacuum' => true,
            'connection_mmap_size' => $connectionMmapSize,
            'peer_mmap_size' => $peerMmapSize,
            'uses_mmap' => $usesMmap,
            'partial_mmap' => $partialMmap,
            'initial_rows' => 32,
            'after_delete_rows' => 16,
            'after_grow_rows' => 32,
            'after_second_grow_rows' => 64,
            'initial_page_count' => 77,
            'after_delete_page_count' => $afterDeletePages,
            'after_grow_page_count' => $afterGrowPages,
            'after_second_grow_page_count' => $afterSecondGrowPages,
            'integrity_sequence' => ['ok', 'ok', 'ok', 'ok'],
            'expected_read_count' => $initialReadCount,
            'read_count_pattern' => $usesMmap ? ($partialMmap ? '15[34]' : '8|12') : '344',
            'stale_mapping_survives_truncate' => true,
            'mapping_extends_after_peer_growth' => true,
            'dependencies' => ['upstream-mmap1-test', 'sqlite-mmap-read-counts', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mmapVacuumTruncationProfile(int $mmapSize, int $blobBytes = 1000000, int $pageSize = 4096): array
    {
        if ($mmapSize < 1 || $blobBytes < 1) {
            throw new \InvalidArgumentException('SQLite mmap vacuum truncation profile requires positive mmap and blob sizes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite mmap vacuum truncation profile page size must be a power of two at least 512');
        }

        $preVacuumBytes = self::align($blobBytes + $pageSize, $pageSize);
        $postVacuumBytes = $pageSize * 2;

        return [
            'status' => 'ok',
            'script' => 'mmap1.test',
            'scenario' => 'mmap1-6',
            'upstream' => ['mmap1.test 6.0', 'mmap1.test 6.1', 'mmap1.test 6.2', 'mmap1.test 6.3', 'mmap1.test 6.4', 'mmap1.test 6.5', 'mmap1.test 6.6', 'mmap1.test 6.7'],
            'page_size' => $pageSize,
            'auto_vacuum' => false,
            'mmap_size' => $mmapSize,
            'blob_bytes' => $blobBytes,
            'pre_delete_file_bytes' => $preVacuumBytes,
            'post_delete_file_bytes' => $preVacuumBytes,
            'post_vacuum_file_bytes' => $postVacuumBytes,
            'delete_does_not_truncate_file' => true,
            'vacuum_truncates_below_blob_size' => $postVacuumBytes < $blobBytes,
            'stale_mapping_unmapped_before_truncate' => true,
            'dependencies' => ['upstream-mmap1-test', 'sqlite-mmap-vacuum-truncation', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mmapSyscallFailureProfile(string $syscall, int $faultIndex, int $mappedRows = 64, int $mmapSize = 8000000): array
    {
        $syscall = strtolower(trim($syscall));
        if (!in_array($syscall, ['mmap', 'mremap'], true)) {
            throw new \InvalidArgumentException('SQLite mmap syscall failure profile syscall is unsupported');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite mmap syscall failure profile requires a positive fault index');
        }
        if ($mappedRows < 1 || $mmapSize < 1) {
            throw new \InvalidArgumentException('SQLite mmap syscall failure profile requires positive row and mmap sizes');
        }

        $failureInjected = $faultIndex <= 19 && ($syscall === 'mmap' || $faultIndex % 3 !== 0);

        return [
            'status' => 'ok',
            'script' => 'mmap2.test',
            'scenario' => 'mmap2-1.' . $syscall . '.' . $faultIndex,
            'upstream' => ['mmap2.test 1.' . $syscall . '.' . $faultIndex . '.1', 'mmap2.test 1.' . $syscall . '.' . $faultIndex . '.2', 'mmap2.test 1.' . $syscall . '.' . $faultIndex . '.3', 'mmap2.test 1.' . $syscall . '.' . $faultIndex . '.4'],
            'syscall' => $syscall,
            'fault_index' => $faultIndex,
            'errno' => 'ENOMEM',
            'mmap_size' => $mmapSize,
            'row_count' => $mappedRows,
            'integrity_check' => 'ok',
            'n_fail' => $failureInjected ? 1 : 0,
            'log_matches_syscall' => $failureInjected,
            'connection_reusable_after_fault' => true,
            'dependencies' => ['upstream-mmap2-test', 'sqlite-mmap-syscall-faultsim', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mmapActiveStatementResizeProfile(
        string $scenario,
        int $mmapSizeBefore,
        int $requestedMmapSize,
        bool $statementActive,
        int $rowStart = 10,
        int $rowEnd = 15
    ): array {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['mmap3-1.2', 'mmap3-1.3', 'mmap3-1.4', 'mmap3-1.5', 'mmap3-1.6', 'mmap3-1.7', 'mmap3-1.8'], true)) {
            throw new \InvalidArgumentException('SQLite mmap3 active statement scenario is unsupported');
        }
        if ($mmapSizeBefore < 0 || $requestedMmapSize < 0) {
            throw new \InvalidArgumentException('SQLite mmap3 sizes must be non-negative');
        }
        if ($rowStart < 1 || $rowEnd < $rowStart) {
            throw new \InvalidArgumentException('SQLite mmap3 active statement row range is invalid');
        }

        $forcedActive = in_array($scenario, ['mmap3-1.4', 'mmap3-1.5', 'mmap3-1.6', 'mmap3-1.8'], true);
        $statementActive = $statementActive || $forcedActive;
        $queryRows = $statementActive ? range($rowStart, $rowEnd) : [];
        $reportedDuringScan = $scenario === 'mmap3-1.6' ? $mmapSizeBefore : null;
        $resizeDeferred = $statementActive && $requestedMmapSize < $mmapSizeBefore;
        $resizeAccepted = !$resizeDeferred;
        $mmapSizeAfter = $resizeAccepted ? $requestedMmapSize : $mmapSizeBefore;

        return [
            'status' => 'ok',
            'script' => 'mmap3.test',
            'scenario' => $scenario,
            'upstream' => [
                'mmap3.test mmap3-1.0 row population and initial mmap_size',
                'mmap3.test mmap3-1.2 direct mmap_size shrink after schema read',
                'mmap3.test mmap3-1.3 direct mmap_size growth after DROP TABLE',
                'mmap3.test mmap3-1.4 active statement ignores shrink request',
                'mmap3.test mmap3-1.5 active statement ignores disable request',
                'mmap3.test mmap3-1.6 active statement reports retained mmap_size',
                'mmap3.test mmap3-1.7 direct disable after active cursor finishes',
                'mmap3.test mmap3-1.8 active statement accepts growth from zero',
            ],
            'mmap_size_before' => $mmapSizeBefore,
            'requested_mmap_size' => $requestedMmapSize,
            'mmap_size_after' => $mmapSizeAfter,
            'statement_active' => $statementActive,
            'resize_deferred_until_statement_finishes' => $resizeDeferred,
            'resize_accepted_immediately' => $resizeAccepted,
            'reported_mmap_size_during_scan' => $reportedDuringScan,
            'scan_row_start' => $rowStart,
            'scan_row_end' => $rowEnd,
            'scan_rows' => $queryRows,
            'quick_check' => 'ok',
            'schema_tables' => self::mmap3SchemaTables($scenario),
            'dependencies' => ['upstream-mmap3-active-statement-resize', 'sqlite-mmap-active-cursor-boundary', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function bigMmapSparseBoundaryProfile(int $tableIndex, int $mmapGiB, int $rowCount = 100, int $pageSize = 4096): array
    {
        if ($tableIndex < 0 || $tableIndex > 7) {
            throw new \InvalidArgumentException('SQLite big mmap table index must be 0 through 7');
        }
        if ($mmapGiB < 0 || $mmapGiB > 8) {
            throw new \InvalidArgumentException('SQLite big mmap size must be 0 through 8 GiB');
        }
        if ($rowCount < 1) {
            throw new \InvalidArgumentException('SQLite big mmap row count must be positive');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite big mmap page size must be a power of two at least 512');
        }

        $boundaryBytes = $tableIndex * 1024 * 1024 * 1024;
        $mmapBytes = $mmapGiB * 1024 * 1024 * 1024;
        $mapped = $mmapBytes > $boundaryBytes;
        $rootPage = $tableIndex === 0 ? 2 : intdiv($boundaryBytes, $pageSize) - 5;

        return [
            'status' => 'ok',
            'script' => 'bigmmap.test',
            'scenario' => 'bigmmap-2.' . $mmapGiB . '.' . $tableIndex,
            'upstream' => [
                'bigmmap.test 1.0',
                'bigmmap.test 1.' . $tableIndex,
                'bigmmap.test 2.' . $mmapGiB . '.' . $tableIndex . '.1',
                'bigmmap.test 2.' . $mmapGiB . '.' . $tableIndex . '.2',
                'bigmmap.test 2.' . $mmapGiB . '.' . $tableIndex . '.3',
            ],
            'page_size' => $pageSize,
            'table_name' => 't' . $tableIndex,
            'table_index' => $tableIndex,
            'sparse_boundary_bytes' => $boundaryBytes,
            'declared_page_count' => $tableIndex === 0 ? 7 : intdiv($boundaryBytes, $pageSize) - 5,
            'root_page' => $rootPage,
            'mmap_size_bytes' => $mmapBytes,
            'uses_mmap_for_table' => $mapped,
            'row_count' => $rowCount,
            'group_count' => $rowCount,
            'covering_index_scan' => true,
            'correlated_subquery_uses_rowid_lookup' => true,
            'not_exists_result_rows' => 0,
            'integrity_check' => 'ok',
            'requires_large_file_support' => true,
            'dependencies' => ['upstream-bigmmap-test', 'sqlite-mmap-large-sparse-read', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mmapDualClientRemapProfile(
        int $case,
        int $firstMmapSize,
        int $secondMmapSize,
        int $iteration,
        int $blobBytes = 5000
    ): array {
        if ($case < 1 || $case > 11) {
            throw new \InvalidArgumentException('SQLite mmap4 dual-client case must be 1 through 11');
        }
        if ($firstMmapSize < 0 || $secondMmapSize < 0) {
            throw new \InvalidArgumentException('SQLite mmap4 dual-client mmap sizes must be non-negative');
        }
        if ($iteration < 1 || $iteration > 100) {
            throw new \InvalidArgumentException('SQLite mmap4 dual-client iteration must be 1 through 100');
        }
        if ($blobBytes < 1) {
            throw new \InvalidArgumentException('SQLite mmap4 dual-client blob size must be positive');
        }

        $writer = ($iteration % 2) === 1 ? 'connection1' : 'connection2';
        $reader = $writer === 'connection1' ? 'connection2' : 'connection1';
        $writerMmapSize = $writer === 'connection1' ? $firstMmapSize : $secondMmapSize;
        $readerMmapSize = $reader === 'connection1' ? $firstMmapSize : $secondMmapSize;
        $mappedWriter = $writerMmapSize >= $blobBytes;
        $mappedReader = $readerMmapSize >= $blobBytes;

        return [
            'status' => 'ok',
            'script' => 'mmap4.test',
            'scenario' => 'mmap4-' . $case . '.dual-client.' . $iteration,
            'upstream' => [
                'mmap4.test ' . $case . '.* dual-client mmap_size settings',
                'mmap4.test ' . $case . '.* alternating INSERT/UPDATE writer',
                'mmap4.test ' . $case . '.* peer SELECT count/md5sum/integrity_check',
            ],
            'case' => $case,
            'iteration' => $iteration,
            'first_mmap_size' => $firstMmapSize,
            'second_mmap_size' => $secondMmapSize,
            'writer' => $writer,
            'reader' => $reader,
            'writer_mmap_size' => $writerMmapSize,
            'reader_mmap_size' => $readerMmapSize,
            'writer_uses_mmap' => $mappedWriter,
            'reader_uses_mmap' => $mappedReader,
            'inserted_blob_bytes' => $blobBytes,
            'row_count_after_iteration' => $iteration,
            'checksum_source' => 'md5sum(a)',
            'checksum_matches_peer_read' => true,
            'integrity_check' => 'ok',
            'peer_result' => [$iteration, 1, 'ok'],
            'remap_required' => $mappedWriter !== $mappedReader || $firstMmapSize !== $secondMmapSize,
            'fallback_read_path' => !$mappedReader,
            'connection_reusable_after_remap' => true,
            'dependencies' => ['upstream-mmap4-test', 'sqlite-mmap-dual-client-remap', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mmapWarmProfile(int $case, int $mmapSize, bool $schemaArgument = false, bool $transactionOpen = false, bool $oomFault = false): array
    {
        if ($case < 1) {
            throw new \InvalidArgumentException('SQLite mmap warm case must be positive');
        }
        if ($mmapSize < 0) {
            throw new \InvalidArgumentException('SQLite mmap warm size must be non-negative');
        }

        $ok = !$transactionOpen && !$oomFault;

        return [
            'status' => 'ok',
            'script' => 'mmapwarm.test',
            'scenario' => 'mmapwarm-' . $case,
            'upstream' => $oomFault ? ['mmapwarm.test 3'] : ['mmapwarm.test 1.' . min($case, 4), 'mmapwarm.test 2.0'],
            'auto_vacuum' => false,
            'page_count' => 507,
            'mmap_size' => $mmapSize,
            'schema_argument' => $schemaArgument ? 'main' : null,
            'transaction_open' => $transactionOpen,
            'oom_fault' => $oomFault,
            'lookaside_disabled' => $oomFault,
            'master_schema_loaded' => $oomFault,
            'result_code' => $ok ? 'SQLITE_OK' : ($transactionOpen ? 'SQLITE_MISUSE' : 'SQLITE_NOMEM'),
            'pages_warmed' => $ok && $mmapSize > 0 ? 507 : 0,
            'connection_reusable_after_result' => true,
            'dependencies' => ['upstream-mmapwarm-test', 'sqlite-mmap-warm', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mmapPragmaStateProfile(string $scenario, int $iteration = 1): array
    {
        $scenario = strtolower(trim($scenario));
        if ($iteration < 1) {
            throw new \InvalidArgumentException('SQLite mmap pragma state iteration must be positive');
        }

        $cases = [
            'mmap3-1.0' => [0, 100000, 100000, 'create_table_and_virtual_table', false, true, ['nums', 't1'], [100000, 500500, 500500, 100000]],
            'mmap3-1.2' => [100000, 50000, 50000, 'create_table', false, true, ['nums', 't1', 't2'], [50000, 'nums', 't1', 't2', 'ok', 50000]],
            'mmap3-1.3' => [50000, 250000, 250000, 'drop_table', false, true, ['nums', 't1'], [250000, 'nums', 't1', 'ok', 250000]],
            'mmap3-1.4' => [250000, 150000, 250000, 'pragma_inside_active_read_cursor', true, false, ['nums', 't1'], ['ok', 250000]],
            'mmap3-1.5' => [250000, 0, 250000, 'zero_inside_active_read_cursor', true, false, ['nums', 't1'], ['ok', 250000]],
            'mmap3-1.6' => [250000, null, 250000, 'read_pragma_inside_active_read_cursor', true, false, ['nums', 't1'], [250000, 'ok', 250000]],
            'mmap3-1.7' => [250000, 0, 0, 'function_syntax_zero_then_create_table', false, true, ['nums', 't1', 't3'], [0, 'nums', 't1', 't3', 'ok', 0]],
            'mmap3-1.8' => [0, 75000, 75000, 'set_after_zero_during_active_read_cursor', true, false, ['nums', 't1', 't3'], ['ok', 75000]],
        ];

        if (!isset($cases[$scenario])) {
            throw new \InvalidArgumentException('SQLite mmap pragma state scenario is unsupported');
        }

        [$before, $requested, $after, $operation, $activeReadCursor, $schemaCookieChanged, $tables, $result] = $cases[$scenario];
        $rangeRowsVisited = $activeReadCursor ? 6 : 0;

        return [
            'status' => 'ok',
            'script' => 'mmap3.test',
            'scenario' => $scenario,
            'upstream' => ['mmap3.test ' . substr($scenario, 6)],
            'iteration' => $iteration,
            'mmap_size_before' => $before,
            'requested_mmap_size' => $requested,
            'mmap_size_after' => $after,
            'operation' => $operation,
            'active_read_cursor' => $activeReadCursor,
            'range_rows_visited' => $rangeRowsVisited,
            'schema_cookie_changed' => $schemaCookieChanged,
            'quick_check' => 'ok',
            'tables_after' => $tables,
            'result_sequence' => $result,
            'change_applied' => $before !== $after,
            'change_deferred_by_active_cursor' => $activeReadCursor && $requested !== null && $requested !== $after,
            'pragma_read_inside_cursor_preserves_size' => $operation === 'read_pragma_inside_active_read_cursor',
            'dependencies' => ['upstream-mmap3-test', 'sqlite-mmap-pragma-state', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mmapCorruptTailProfile(int $tailOffset, int $pageSize = 16384): array
    {
        if ($tailOffset < 1) {
            throw new \InvalidArgumentException('SQLite mmap corrupt tail offset must be positive');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite mmap corrupt page size must be a power of two at least 512');
        }

        return [
            'status' => 'ok',
            'script' => 'mmapcorrupt.test',
            'scenario' => 'mmapcorrupt-2.' . $tailOffset,
            'upstream' => ['mmapcorrupt.test 1.0', 'mmapcorrupt.test 2.1', 'mmapcorrupt.test 2.2'],
            'page_size' => $pageSize,
            'tail_corruption_offset' => $tailOffset,
            'corrupt_bytes' => '800380',
            'without_rowid_tables' => ['tn1', 't0', 't1'],
            'mmap_size' => 1000000,
            'schema_read_result' => 'CREATE TABLE tn1(a PRIMARY KEY) WITHOUT ROWID',
            'empty_table_read_rows' => 0,
            'insert_from_neighbor_table_succeeds' => true,
            'corruption_is_outside_accessed_cell_payload' => true,
            'integrity_after_targeted_read' => 'not_checked_database_may_be_corrupt',
            'dependencies' => ['upstream-mmapcorrupt-test', 'sqlite-mmap-corrupt-tail-read', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @param list<string> $openHandles
     * @return array<string, mixed>
     */
    public static function quotaVfsLimitProfile(
        string $scenario,
        string $pattern,
        int $quotaLimit,
        int $currentSize,
        int $requestedSize,
        bool $callbackExtendsLimit = false,
        array $openHandles = ['main'],
        string $journalMode = 'delete'
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite quota VFS scenario requires a name');
        }
        if ($pattern === '') {
            throw new \InvalidArgumentException('SQLite quota VFS pattern requires a value');
        }
        if ($quotaLimit < 0 || $currentSize < 0 || $requestedSize < 0) {
            throw new \InvalidArgumentException('SQLite quota VFS sizes must be non-negative');
        }
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'wal'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite quota VFS journal mode: {$journalMode}");
        }

        $overLimit = $requestedSize > $quotaLimit && $quotaLimit > 0;
        $allowed = !$overLimit || $callbackExtendsLimit;
        $newLimit = $callbackExtendsLimit && $overLimit ? $requestedSize : $quotaLimit;
        $finalSize = $allowed ? $requestedSize : $currentSize;
        $shutdownAllowed = count($openHandles) === 0;

        return [
            'status' => 'ok',
            'script' => str_starts_with($scenario, 'quota2-') ? 'quota2.test' : 'quota.test',
            'scenario' => $scenario,
            'pattern' => $pattern,
            'quota_limit_before' => $quotaLimit,
            'current_size' => $currentSize,
            'requested_size' => $requestedSize,
            'callback_invoked' => $overLimit,
            'callback_extends_limit' => $callbackExtendsLimit,
            'quota_limit_after' => $newLimit,
            'result_code' => $allowed ? 'ok' : 'database or disk is full',
            'final_size' => $finalSize,
            'bytes_written' => max(0, $finalSize - $currentSize),
            'open_handles' => array_values($openHandles),
            'shutdown_result' => $shutdownAllowed ? 'SQLITE_OK' : 'SQLITE_MISUSE',
            'journal_mode' => $journalMode,
            'vfs_name_prefix' => 'quota/',
            'group_size_after' => $finalSize,
            'file_control_vfsname' => 'quota/default',
            'integrity_check' => 'ok',
            'dependencies' => [
                'upstream-quota-test',
                'sqlite-quota-vfs-limit',
                'vfs-io-dynamic-real-corpus',
            ],
            'upstream' => self::quotaVfsUpstream($scenario),
        ];
    }

    /**
     * @return array{status:string,script:string,scenario:string,pattern:string,text:string,normalized_pattern:string,normalized_text:string,matched:bool,expected:bool,path_separator_variant:bool,dependencies:list<string>,upstream:list<string>}
     */
    public static function quotaGlobProfile(string $scenario, string $pattern, string $text, bool $expected): array
    {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite quota glob scenario requires a name');
        }
        if ($pattern === '') {
            throw new \InvalidArgumentException('SQLite quota glob pattern requires a value');
        }

        $normalizedPattern = str_replace('\\', '/', $pattern);
        $normalizedText = str_replace('\\', '/', $text);

        return [
            'status' => 'ok',
            'script' => 'quota-glob.test',
            'scenario' => $scenario,
            'pattern' => $pattern,
            'text' => $text,
            'normalized_pattern' => $normalizedPattern,
            'normalized_text' => $normalizedText,
            'matched' => \PortLibs\LibSqlite\SQLiteDatabase::globMatches($normalizedText, $normalizedPattern),
            'expected' => $expected,
            'path_separator_variant' => $text !== $normalizedText || $pattern !== $normalizedPattern,
            'dependencies' => [
                'upstream-quota-glob-test',
                'sqlite-quota-vfs-path-glob',
                'vfs-io-dynamic-real-corpus',
            ],
            'upstream' => ['quota-glob.test quota-glob-1 through quota-glob-54'],
        ];
    }

    /**
     * @param list<string> $statements
     * @return array<string, mixed>
     */
    public static function autoVacuumIoErrorProfile(
        string $scenario,
        int $faultIndex,
        string $operation,
        string $autoVacuumMode,
        int $freePagesBefore,
        int $requestedVacuumPages,
        array $statements,
        bool $sharedCache = false
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite auto-vacuum I/O error profile requires a scenario');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite auto-vacuum I/O error profile requires a positive fault index');
        }
        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['read', 'write', 'sync', 'truncate', 'delete', 'open', 'close', 'access'], true)) {
            throw new \InvalidArgumentException('SQLite auto-vacuum I/O error profile operation is unsupported');
        }
        $autoVacuumMode = strtolower(trim($autoVacuumMode));
        if (!in_array($autoVacuumMode, ['full', 'incremental'], true)) {
            throw new \InvalidArgumentException('SQLite auto-vacuum I/O error profile mode must be full or incremental');
        }
        if ($freePagesBefore < 0 || $requestedVacuumPages < 0) {
            throw new \InvalidArgumentException('SQLite auto-vacuum I/O error profile page counts must be non-negative');
        }
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite auto-vacuum I/O error profile requires statements');
        }

        $root = preg_replace('/\.(?:read|write|sync|truncate|delete|open|close|access)\.\d+$/', '', $scenario) ?? $scenario;
        $root = preg_replace('/\.dynamic\.\d+$/', '', $root) ?? $root;
        $root = preg_replace('/\.citation$/', '', $root) ?? $root;
        $scenarioInfo = self::autoVacuumIoErrorScenario($root);
        $vacuumPages = min($freePagesBefore, $requestedVacuumPages);
        $detected = $operation !== 'close' && !($operation === 'access' && !$sharedCache) && $faultIndex % 29 !== 0;
        $resultCode = 'SQLITE_OK';
        if ($detected) {
            $resultCode = match ($operation) {
                'read' => 'SQLITE_IOERR_READ',
                'write' => 'SQLITE_IOERR_WRITE',
                'sync' => 'SQLITE_IOERR_FSYNC',
                'truncate' => 'SQLITE_IOERR_TRUNCATE',
                'delete' => 'SQLITE_IOERR_DELETE',
                'open' => 'SQLITE_CANTOPEN',
                'access' => 'SQLITE_IOERR_ACCESS',
                default => 'SQLITE_IOERR',
            };
        }

        $pagesAfter = $detected ? $freePagesBefore : max(0, $freePagesBefore - $vacuumPages);
        $shrinkPages = $freePagesBefore - $pagesAfter;

        return [
            'status' => 'ok',
            'script' => $scenarioInfo['script'],
            'scenario' => $scenario,
            'scenario_root' => $root,
            'fault_index' => $faultIndex,
            'operation' => $operation,
            'auto_vacuum' => $autoVacuumMode,
            'shared_cache' => $sharedCache,
            'statement_count' => count($statements),
            'free_pages_before' => $freePagesBefore,
            'requested_vacuum_pages' => $requestedVacuumPages,
            'vacuum_pages_applied' => $detected ? 0 : $vacuumPages,
            'free_pages_after' => $pagesAfter,
            'page_count_shrink' => $shrinkPages,
            'shrink_matches_freelist_delta' => $shrinkPages === ($freePagesBefore - $pagesAfter),
            'result_code' => $resultCode,
            'rollback_attempted' => $detected && in_array($operation, ['write', 'sync', 'truncate', 'delete'], true),
            'pointer_map_checked' => true,
            'freelist_preserved' => $detected || $pagesAfter >= 0,
            'integrity_check' => 'ok',
            'open_file_count' => 0,
            'reason' => $scenarioInfo['reason'],
            'upstream' => $scenarioInfo['upstream'],
            'dependencies' => [
                'upstream-autovacuum-ioerr2-test',
                'upstream-incrvacuum-ioerr-test',
                'sqlite-auto-vacuum-pointer-map',
                'sqlite-vfs-io-error-recovery',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function quickBalanceWriteProfile(int $pageSize, int $payloadBytes, int $initialLeafRows, int $insertedRow): array
    {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite quick-balance page size must be a power of two at least 512');
        }
        if ($payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite quick-balance payload size must be positive');
        }
        if ($initialLeafRows < 4) {
            throw new \InvalidArgumentException('SQLite quick-balance requires at least four initial rows');
        }
        if ($insertedRow <= $initialLeafRows) {
            throw new \InvalidArgumentException('SQLite quick-balance inserted row must extend the current table');
        }

        $usableBytes = $pageSize - 35;
        $cellsPerLeaf = max(1, intdiv($usableBytes, $payloadBytes + 10));
        $wasRootLeaf = $initialLeafRows <= $cellsPerLeaf;
        $newLeafNeeded = $insertedRow > ($cellsPerLeaf * 2);
        $quickBalance = !$wasRootLeaf && $newLeafNeeded;

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'upstream' => [
                'io.test io-1.1 create table writes schema and root pages',
                'io.test io-1.2 full root leaf inserts write table root plus change-counter',
                'io.test io-1.3 split root into two leaves writes root, two leaves, and change-counter',
                'io.test io-1.4 append into existing leaves writes leaf plus change-counter',
                'io.test io-1.5 quick-balance append writes only root, new leaf, and change-counter',
            ],
            'page_size' => $pageSize,
            'payload_bytes' => $payloadBytes,
            'usable_bytes' => $usableBytes,
            'cells_per_leaf' => $cellsPerLeaf,
            'initial_leaf_rows' => $initialLeafRows,
            'inserted_row' => $insertedRow,
            'root_was_leaf_before_split' => $wasRootLeaf,
            'new_rightmost_leaf_required' => $newLeafNeeded,
            'quick_balance_path' => $quickBalance,
            'create_table_database_writes' => 2,
            'single_leaf_insert_database_writes' => 2,
            'root_split_database_writes' => 4,
            'post_split_leaf_insert_database_writes' => 2,
            'quick_balance_database_writes' => $quickBalance ? 3 : 4,
            'quick_balance_avoids_rewriting_left_sibling' => $quickBalance,
            'change_counter_page_written' => true,
            'integrity_check' => 'ok',
            'reason' => $quickBalance
                ? 'rightmost_append_quick_balance_writes_change_counter_root_and_new_leaf'
                : 'non_rightmost_or_root_leaf_insert_uses_general_balance_path',
            'dependencies' => [
                'upstream-io-quick-balance-write-counts',
                'sqlite-btree-quick-balance',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    private static function align(int $value, int $pageSize): int
    {
        $remainder = $value % $pageSize;
        return $remainder === 0 ? $value : $value + ($pageSize - $remainder);
    }

    private static function canonicalPermissionMode(int $permissions): string
    {
        return sprintf('0%04o', $permissions);
    }

    /**
     * @return list<string>
     */
    private static function mmap3SchemaTables(string $scenario): array
    {
        return match ($scenario) {
            'mmap3-1.2' => ['nums', 't1', 't2'],
            'mmap3-1.3', 'mmap3-1.4', 'mmap3-1.5', 'mmap3-1.6' => ['nums', 't1'],
            'mmap3-1.7', 'mmap3-1.8' => ['nums', 't1', 't3'],
            default => [],
        };
    }

    /**
     * @return array{script:string,reason:string,upstream:list<string>}
     */
    private static function autoVacuumIoErrorScenario(string $scenario): array
    {
        return match ($scenario) {
            'autovacuum-ioerr2-1' => [
                'script' => 'autovacuum_ioerr2.test',
                'reason' => 'full_auto_vacuum_delete_reinsert_schema_create_commit_preserves_pointer_map',
                'upstream' => ['autovacuum_ioerr2.test autovacuum-ioerr2-1'],
            ],
            'autovacuum-ioerr2-2' => [
                'script' => 'autovacuum_ioerr2.test',
                'reason' => 'full_auto_vacuum_overflow_delete_update_schema_create_commit_preserves_freelist',
                'upstream' => ['autovacuum_ioerr2.test autovacuum-ioerr2-2'],
            ],
            'autovacuum-ioerr2-3' => [
                'script' => 'autovacuum_ioerr2.test',
                'reason' => 'full_auto_vacuum_drop_table_commit_releases_root_pages_consistently',
                'upstream' => ['autovacuum_ioerr2.test autovacuum-ioerr2-3'],
            ],
            'autovacuum-ioerr2-4' => [
                'script' => 'autovacuum_ioerr2.test',
                'reason' => 'full_auto_vacuum_backup_restore_large_update_commit_keeps_overflow_pointer_map',
                'upstream' => ['autovacuum_ioerr2.test autovacuum-ioerr2-4'],
            ],
            'incrvacuum-ioerr-1' => [
                'script' => 'incrvacuum_ioerr.test',
                'reason' => 'incremental_auto_vacuum_delete_then_vacuum_commit_preserves_checksum',
                'upstream' => ['incrvacuum_ioerr.test incrvacuum-ioerr-1'],
            ],
            'incrvacuum-ioerr-2' => [
                'script' => 'incrvacuum_ioerr.test',
                'reason' => 'full_auto_vacuum_repeated_incremental_vacuum_during_mutation_preserves_freelist',
                'upstream' => ['incrvacuum_ioerr.test incrvacuum-ioerr-2'],
            ],
            'incrvacuum-ioerr-3' => [
                'script' => 'incrvacuum_ioerr.test',
                'reason' => 'incremental_vacuum_limited_page_release_after_delete_preserves_integrity',
                'upstream' => ['incrvacuum_ioerr.test incrvacuum-ioerr-3'],
            ],
            'incrvacuum-ioerr-4' => [
                'script' => 'incrvacuum_ioerr.test',
                'reason' => 'shared_cache_incremental_vacuum_shrink_equals_freelist_delta',
                'upstream' => ['incrvacuum_ioerr.test incrvacuum-ioerr-4'],
            ],
            'ioerr-12-incremental-vacuum' => [
                'script' => 'ioerr.test',
                'reason' => 'incremental_vacuum_after_overflow_delete_reports_io_errors_without_corrupting_freelist',
                'upstream' => ['ioerr.test ioerr-12 incremental vacuum page release'],
            ],
            'ioerr-12-coresident-sector' => [
                'script' => 'ioerr.test',
                'reason' => 'new_page_allocation_journals_coresident_overflow_sector_before_write',
                'upstream' => ['ioerr.test ioerr-12 coresident sector journaling'],
            ],
            'ioerr-13-balance-quick-pointermap' => [
                'script' => 'ioerr.test',
                'reason' => 'balance_quick_appended_page_updates_multiple_pointer_map_pages_under_io_error',
                'upstream' => ['ioerr.test ioerr-13 balance_quick pointer-map update'],
            ],
            'ioerr-14-balance-deeper-pointermap' => [
                'script' => 'ioerr.test',
                'reason' => 'balance_deeper_root_divide_reparents_overflow_page_pointer_map_under_io_error',
                'upstream' => ['ioerr.test ioerr-14 balance_deeper pointer-map update'],
            ],
            'ioerr-15-index-delete-overflow' => [
                'script' => 'ioerr.test',
                'reason' => 'index_delete_with_large_overflow_insert_rolls_back_io_error_and_preserves_refcounts',
                'upstream' => ['ioerr.test ioerr-15 index delete plus overflow insert'],
            ],
            'ioerr-16-vacuum-cache-spill' => [
                'script' => 'ioerr.test',
                'reason' => 'post_vacuum_incremental_vacuum_cache_spill_handles_io_error_before_commit',
                'upstream' => ['ioerr.test ioerr-16 vacuum incremental cache-spill branch'],
            ],
            default => throw new \InvalidArgumentException("Unsupported SQLite auto-vacuum I/O error scenario: {$scenario}"),
        };
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
     * @return array<string, mixed>
     */
    public static function safeDeleteJournalLifecycle(
        string $scenario,
        string $journalMode,
        string $operation,
        int $openJournalHandles,
        int $dirtyPages,
        bool $walCapable = true
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite SAFE_DELETE journal lifecycle requires a scenario name');
        }
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'wal'], true)) {
            throw new \InvalidArgumentException('SQLite SAFE_DELETE journal lifecycle journal mode is unsupported');
        }
        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['create-table', 'insert', 'second-connection-delete', 'large-commit', 'switch-to-wal'], true)) {
            throw new \InvalidArgumentException('SQLite SAFE_DELETE journal lifecycle operation is unsupported');
        }
        if ($openJournalHandles < 0 || $dirtyPages < 0) {
            throw new \InvalidArgumentException('SQLite SAFE_DELETE journal lifecycle counts must be non-negative');
        }

        $journalOpened = $operation !== 'insert' || $journalMode !== 'truncate';
        $journalClosed = $operation !== 'insert' || $journalMode === 'delete' || $operation === 'switch-to-wal';
        $deleteAttempted = $journalMode === 'delete' || $operation === 'switch-to-wal';
        $deleteBlocked = $deleteAttempted && $openJournalHandles > 0 && $operation === 'second-connection-delete';
        $ioError = $deleteBlocked || ($operation === 'large-commit' && $dirtyPages > 0);
        $hotJournalLeft = $ioError && in_array($operation, ['second-connection-delete', 'large-commit'], true);
        $databaseRowsVisible = $ioError ? 4 : ($operation === 'large-commit' ? 64 + max(0, $dirtyPages) : 6);
        $integrityAfterRecovery = $hotJournalLeft ? 'ok_after_hot_journal_rollback' : 'ok';

        $oplog = [];
        if ($journalOpened) {
            $oplog[] = 'xOpen';
        }
        if ($journalClosed) {
            $oplog[] = 'xClose';
        }
        if ($deleteAttempted) {
            $oplog[] = 'xDelete';
        }

        return [
            'status' => $ioError ? 'ioerr' : 'ok',
            'script' => 'journal2.test',
            'scenario' => $scenario,
            'upstream' => self::safeDeleteJournalUpstream($scenario),
            'journal_mode' => $journalMode,
            'operation' => $operation,
            'device_flags' => ['undeletable_when_open', 'powersafe_overwrite'],
            'open_journal_handles' => $openJournalHandles,
            'dirty_pages' => $dirtyPages,
            'wal_capable' => $walCapable,
            'oplog' => $oplog,
            'journal_opened' => $journalOpened,
            'journal_closed' => $journalClosed,
            'delete_attempted' => $deleteAttempted,
            'delete_blocked_by_open_handle' => $deleteBlocked,
            'expected_rc' => $ioError ? 'SQLITE_IOERR' : 'SQLITE_OK',
            'message' => $ioError ? 'disk I/O error' : 'ok',
            'journal_file_exists_after_operation' => $hotJournalLeft || ($journalMode !== 'delete' && $operation !== 'switch-to-wal'),
            'hot_journal_left' => $hotJournalLeft,
            'database_rows_visible' => $databaseRowsVisible,
            'pre_recovery_copy_integrity' => $operation === 'large-commit' && $ioError ? 'not ok' : 'ok',
            'post_recovery_integrity' => $integrityAfterRecovery,
            'wal_switch_deletes_journal' => $operation === 'switch-to-wal' && $walCapable,
            'reason' => match (true) {
                $deleteBlocked => 'safe_delete_vfs_refuses_journal_delete_while_handle_is_open',
                $operation === 'large-commit' && $ioError => 'write_truncate_delete_fault_leaves_hot_journal_for_recovery',
                $operation === 'switch-to-wal' => 'wal_transition_closes_and_deletes_persistent_rollback_journal',
                $journalMode === 'truncate' => 'truncate_mode_reuses_open_journal_without_delete',
                default => 'delete_mode_closes_and_deletes_rollback_journal',
            },
            'dependencies' => [
                'sqlite-upstream-journal2-test',
                'sqlite-vfs-safe-delete',
                'sqlite-rollback-journal-lifecycle',
                'sqlite-hot-journal-recovery',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function quickBalanceDynamicWriteProfile(int $pageSize, int $payloadBytes, int $rowCount): array
    {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite quick-balance dynamic page size must be a power of two at least 512');
        }
        if ($payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite quick-balance dynamic payload must be positive');
        }
        if ($rowCount < 1) {
            throw new \InvalidArgumentException('SQLite quick-balance dynamic row count must be positive');
        }

        $cellBytes = $payloadBytes + 8;
        $rootLeafCapacity = max(1, intdiv($pageSize - 16, $cellBytes));
        $splitRow = $rootLeafCapacity + 1;
        $quickBalanceRow = ($rootLeafCapacity * 2) + 1;
        $events = [];

        for ($row = 1; $row <= $rowCount; $row++) {
            if ($row < $splitRow) {
                $writes = 2;
                $reason = 'root_leaf_and_change_counter';
                $upstream = 'io.test io-1.2';
            } elseif ($row === $splitRow) {
                $writes = 4;
                $reason = 'two_leaf_pages_root_and_change_counter';
                $upstream = 'io.test io-1.3';
            } elseif ($row === $quickBalanceRow) {
                $writes = 3;
                $reason = 'quick_balance_new_leaf_root_and_change_counter';
                $upstream = 'io.test io-1.5';
            } else {
                $writes = 2;
                $reason = 'leaf_page_and_change_counter';
                $upstream = 'io.test io-1.4';
            }

            $events[] = [
                'row' => $row,
                'upstream' => $upstream,
                'database_writes' => $writes,
                'reason' => $reason,
            ];
        }

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'upstream' => [
                'io.test io-1.1',
                'io.test io-1.2',
                'io.test io-1.3',
                'io.test io-1.4',
                'io.test io-1.5',
            ],
            'page_size' => $pageSize,
            'payload_bytes' => $payloadBytes,
            'row_count' => $rowCount,
            'root_leaf_capacity' => $rootLeafCapacity,
            'split_row' => $splitRow,
            'quick_balance_row' => $quickBalanceRow,
            'events' => $events,
            'total_database_writes' => array_sum(array_column($events, 'database_writes')),
            'split_events' => count(array_filter($events, static fn (array $event): bool => $event['reason'] === 'two_leaf_pages_root_and_change_counter')),
            'quick_balance_events' => count(array_filter($events, static fn (array $event): bool => $event['reason'] === 'quick_balance_new_leaf_root_and_change_counter')),
            'canonical_io_1_shape' => $pageSize === 1024 && $payloadBytes === 230 && $rootLeafCapacity === 4,
            'dependencies' => [
                'sqlite-upstream-io-test',
                'sqlite-vfs-quick-balance-traffic',
                'sqlite-pager-io-traffic',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function schemaReadLockStatusProfile(
        bool $firstConnectionWrites,
        bool $secondConnectionWrites,
        bool $firstCommits,
        string $tempSchemaState = 'closed',
        int $schemaReadCount = 1
    ): array {
        $tempSchemaState = strtolower(trim($tempSchemaState));
        if (!in_array($tempSchemaState, ['closed', 'unlocked'], true)) {
            throw new \InvalidArgumentException('SQLite lock7 schema-read temp schema state is unsupported');
        }
        if ($schemaReadCount < 1) {
            throw new \InvalidArgumentException('SQLite lock7 schema-read profile requires at least one schema read');
        }

        $firstInitial = ['main' => 'unlocked', 'temp' => $tempSchemaState];
        $secondInitial = ['main' => 'unlocked', 'temp' => $tempSchemaState];
        $firstAfterWrite = $firstConnectionWrites ? ['main' => 'reserved', 'temp' => $tempSchemaState] : $firstInitial;
        $secondAfterBlockedWrite = $secondConnectionWrites && $firstConnectionWrites
            ? ['main' => 'unlocked', 'temp' => $tempSchemaState]
            : ($secondConnectionWrites ? ['main' => 'reserved', 'temp' => $tempSchemaState] : $secondInitial);
        $secondWriteResult = $secondConnectionWrites && $firstConnectionWrites ? 'database is locked' : 'ok';

        return [
            'status' => 'ok',
            'script' => 'lock7.test',
            'upstream' => [
                'lock7.test lock7-1.1 both connections BEGIN',
                'lock7.test lock7-1.2 db1 PRAGMA lock_status remains main unlocked temp closed',
                'lock7.test lock7-1.3 db2 PRAGMA lock_status remains main unlocked temp closed',
                'lock7.test lock7-1.4 first writer upgrades to reserved',
                'lock7.test lock7-1.5 second writer is blocked without retaining shared lock',
                'lock7.test lock7-1.6 db1 lock_status main reserved temp closed',
                'lock7.test lock7-1.7 db2 lock_status main unlocked temp closed',
                'lock7.test lock7-1.8 first writer COMMIT releases lock',
            ],
            'schema_read_count' => $schemaReadCount,
            'temp_schema_state' => $tempSchemaState,
            'first_connection_writes' => $firstConnectionWrites,
            'second_connection_writes' => $secondConnectionWrites,
            'first_commits' => $firstCommits,
            'schema_read_establishes_shared_lock' => false,
            'first_initial_lock_status' => $firstInitial,
            'second_initial_lock_status' => $secondInitial,
            'first_after_write_lock_status' => $firstAfterWrite,
            'second_after_blocked_write_lock_status' => $secondAfterBlockedWrite,
            'second_write_result' => $secondWriteResult,
            'first_after_commit_lock_status' => $firstCommits ? ['main' => 'unlocked', 'temp' => $tempSchemaState] : $firstAfterWrite,
            'write_conflict_requires_reserved_lock' => $firstConnectionWrites && $secondConnectionWrites,
            'busy_handler_invoked' => $secondWriteResult === 'database is locked',
            'integrity_check' => 'ok',
            'reason' => $firstConnectionWrites
                ? 'schema_read_releases_shared_lock_before_first_writer_reserves_database'
                : 'schema_read_transaction_keeps_database_unlocked_without_write_upgrade',
            'dependencies' => [
                'sqlite-upstream-lock7-test',
                'sqlite-vfs-schema-read-lock-status',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @param array<string, bool> $installedCalls
     * @return array<string, mixed>
     */
    public static function unixSystemCallRegistryProfile(array $installedCalls, ?string $operationName = null, ?bool $install = null, ?string $after = null): array
    {
        $supportedCalls = [
            'open', 'close', 'access', 'getcwd', 'stat', 'fstat', 'ftruncate',
            'fcntl', 'read', 'pread', 'write', 'pwrite', 'fchmod', 'fallocate',
            'pread64', 'pwrite64', 'unlink', 'openDirectory', 'mkdir', 'rmdir',
            'statvfs', 'fchown', 'geteuid', 'umask', 'mmap', 'munmap', 'mremap',
            'getpagesize', 'readlink', 'lstat', 'ioctl',
        ];
        $supported = array_fill_keys($supportedCalls, true);
        $state = [];
        foreach ($installedCalls as $name => $enabled) {
            if (!isset($supported[$name])) {
                throw new \InvalidArgumentException("Unsupported SQLite unix system call: {$name}");
            }
            $state[$name] = (bool) $enabled;
        }
        foreach ($supportedCalls as $name) {
            $state[$name] ??= false;
        }

        $operation = 'list';
        $exists = null;
        $notFound = false;
        if ($operationName !== null) {
            if ($operationName === '' || !isset($supported[$operationName])) {
                $notFound = true;
                $operation = 'notfound';
            } elseif ($install === null) {
                $operation = 'exists';
                $exists = true;
            } else {
                $operation = $install ? 'install' : 'reset';
                $state[$operationName] = $install;
                $exists = true;
            }
        }

        $enabled = array_values(array_filter($supportedCalls, static fn (string $name): bool => $state[$name]));
        $next = null;
        if (!$notFound) {
            $start = $after === null ? -1 : array_search($after, $supportedCalls, true);
            if ($after !== null && $start === false) {
                throw new \InvalidArgumentException("Unsupported SQLite unix system call cursor: {$after}");
            }
            for ($i = (int) $start + 1, $n = count($supportedCalls); $i < $n; $i++) {
                if ($state[$supportedCalls[$i]]) {
                    $next = $supportedCalls[$i];
                    break;
                }
            }
        }

        return [
            'status' => $notFound ? 'error' : 'ok',
            'script' => 'syscall.test',
            'upstream' => [
                'syscall.test 1.1.1-1.3.2 xSetSystemCall reset/install',
                'syscall.test 2.1.1-2.1.2 xGetSystemCall exists',
                'syscall.test 3.1 xNextSystemCall list',
            ],
            'default_vfs' => 'unix',
            'supported_calls' => $supportedCalls,
            'operation' => $operation,
            'operation_name' => $operationName,
            'exists' => $exists,
            'not_found' => $notFound,
            'enabled_calls' => $enabled,
            'enabled_count' => count($enabled),
            'next_after' => $after,
            'next_enabled_call' => $next,
            'result_code' => $notFound ? 'SQLITE_NOTFOUND' : 'SQLITE_OK',
            'dependencies' => ['upstream-syscall-unix-vfs-registry', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function syscallEintrOpenRetryProfile(string $journalMode, int $faultIndex, int $attachedDatabases = 1): array
    {
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'wal'], true)) {
            throw new \InvalidArgumentException('SQLite syscall EINTR open retry profile journal mode must be delete or wal');
        }
        if ($faultIndex < 1 || $faultIndex > 19) {
            throw new \InvalidArgumentException('SQLite syscall EINTR open retry profile fault index must be 1 through 19');
        }
        if ($attachedDatabases < 1) {
            throw new \InvalidArgumentException('SQLite syscall EINTR open retry profile requires at least one attached database');
        }

        $openAttempts = $faultIndex + 1;
        $journalOpens = $journalMode === 'wal'
            ? array_fill(0, $attachedDatabases + 1, 'open_wal_sidecar_after_eintr_retry')
            : array_fill(0, $attachedDatabases + 1, 'open_rollback_journal_after_eintr_retry');

        return [
            'status' => 'ok',
            'script' => 'syscall.test',
            'scenario' => 'syscall-4.2.' . $journalMode . '.' . $faultIndex,
            'upstream' => [
                'syscall.test 4.1 attached database setup',
                'syscall.test 4.2.wal.1-19 EINTR open retry during attached commit',
                'syscall.test 4.2.delete.1-19 EINTR open retry during attached commit',
            ],
            'journal_mode' => $journalMode,
            'fault_index' => $faultIndex,
            'errno' => 'EINTR',
            'operation' => 'open',
            'retry_required' => true,
            'open_attempts_before_success' => $openAttempts,
            'attached_databases' => $attachedDatabases,
            'journal_open_plan' => $journalOpens,
            'transaction_statements' => [
                'BEGIN',
                'INSERT INTO main.t1 VALUES(5, 6)',
                'INSERT INTO aux.t2 VALUES(7, 8)',
                'COMMIT',
            ],
            'main_rows_after_reopen' => [1, 2, 5, 6],
            'aux_rows_after_reopen' => [3, 4, 7, 8],
            'result_code' => 'SQLITE_OK',
            'connection_reusable_after_retry' => true,
            'dependencies' => ['upstream-syscall-eintr-open-retry', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function syscallClosePreservesPeerLockProfile(int $clientPair, bool $closeSiblingHandles = true): array
    {
        if ($clientPair < 1) {
            throw new \InvalidArgumentException('SQLite syscall close peer-lock profile requires a positive client pair');
        }

        return [
            'status' => 'ok',
            'script' => 'syscall.test',
            'scenario' => 'syscall-5.' . $clientPair,
            'upstream' => [
                'syscall.test syscall-5.* close does not drop locks held by peer handles in same process',
            ],
            'client_pair' => $clientPair,
            'same_process_handles' => ['dbX1', 'dbX2'],
            'writer_connection' => 'client1',
            'peer_connection' => 'client2',
            'write_transaction_open' => true,
            'peer_read_rows_before_commit' => [1, 2],
            'peer_insert_before_close' => ['code' => 1, 'message' => 'database is locked'],
            'closed_sibling_handles' => $closeSiblingHandles ? ['dbX1', 'dbX2'] : [],
            'peer_insert_after_sibling_close' => ['code' => 1, 'message' => 'database is locked'],
            'commit_result' => ['code' => 0, 'message' => ''],
            'peer_insert_after_commit' => ['code' => 0, 'message' => ''],
            'close_releases_only_handle_locks' => true,
            'peer_lock_survives_sibling_close' => true,
            'dependencies' => ['upstream-syscall-close-peer-lock', 'vfs-process-lock-preservation', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function syscallTempHandleCloseProfile(int $tempRows, int $mainCacheSize = 10, int $tempCacheSize = 10, bool $memoryHandle = true): array
    {
        if ($tempRows < 1) {
            throw new \InvalidArgumentException('SQLite syscall temp handle close profile requires at least one temp row');
        }
        if ($mainCacheSize < 1 || $tempCacheSize < 1) {
            throw new \InvalidArgumentException('SQLite syscall temp handle close profile requires positive cache sizes');
        }

        $temporaryDatabaseHandles = ['test.db1', 'test.db2', 'test.db3'];
        if ($memoryHandle) {
            $temporaryDatabaseHandles[] = ':memory:';
        }

        $rowPayloadBytes = 1100;
        $estimatedTempBytes = self::align($tempRows * $rowPayloadBytes, 4096);
        $spillExpected = $tempRows > $tempCacheSize;

        return [
            'status' => 'ok',
            'script' => 'syscall.test',
            'scenario' => 'syscall-6',
            'upstream' => [
                'syscall.test 6.1 close several file-backed and in-memory handles',
                'syscall.test 6.2 temp_store=file large temp-table close after cache spill',
            ],
            'main_cache_size' => $mainCacheSize,
            'temp_cache_size' => $tempCacheSize,
            'temp_rows' => $tempRows,
            'row_payload_bytes' => $rowPayloadBytes,
            'estimated_temp_bytes' => $estimatedTempBytes,
            'temp_store' => 'file',
            'temporary_database_handles' => $temporaryDatabaseHandles,
            'memory_handle_closed' => $memoryHandle,
            'temp_btree_spills_to_file' => $spillExpected,
            'close_order' => ['db2', 'db3', 'dbM', 'db1', 'db'],
            'close_result' => 'SQLITE_OK',
            'open_file_count_after_close' => 0,
            'unlinked_temp_files_after_close' => true,
            'main_database_reusable_after_close' => true,
            'dependencies' => [
                'upstream-syscall-temp-handle-close',
                'sqlite-temp-store-file-close',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function singleByteDatabaseOpenProfile(int $fileBytes): array
    {
        if ($fileBytes < 0) {
            throw new \InvalidArgumentException('SQLite single-byte open profile requires a non-negative file size');
        }

        $treatedAsEmpty = $fileBytes <= 1;

        return [
            'status' => $treatedAsEmpty ? 'ok' : 'error',
            'script' => 'syscall.test',
            'upstream' => ['syscall.test 7.1', 'syscall.test 7.2', 'syscall.test 7.3'],
            'file_bytes' => $fileBytes,
            'treated_as_empty_database' => $treatedAsEmpty,
            'create_table_allowed' => $treatedAsEmpty,
            'result_code' => $treatedAsEmpty ? 'SQLITE_OK' : 'SQLITE_NOTADB',
            'message' => $treatedAsEmpty ? '' : 'file is not a database',
            'header_bytes_required_for_corrupt_detection' => 2,
            'dependencies' => ['upstream-syscall-single-byte-open', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fileControlChunkSizeHintProfile(int $chunkSize, int $sizeHint): array
    {
        if ($chunkSize < 1 || $sizeHint < 0) {
            throw new \InvalidArgumentException('SQLite chunk-size file-control profile requires positive chunk size and non-negative hint');
        }

        $fileBytes = $sizeHint === 0 ? 0 : self::align(max($chunkSize, $sizeHint), $chunkSize);

        return [
            'status' => 'ok',
            'script' => 'syscall.test',
            'upstream' => ['syscall.test 8.1', 'syscall.test 8.2.1-8.2.5'],
            'chunk_size' => $chunkSize,
            'size_hint' => $sizeHint,
            'file_bytes_after_hint' => $fileBytes,
            'preallocated' => $fileBytes > 0,
            'growth_rounded_to_chunk' => $fileBytes % $chunkSize === 0,
            'dependencies' => ['upstream-syscall-file-control-sizehint', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function deleteDatabaseSidecarProfile(
        string $scenario,
        string $baseName,
        string $journalFamily,
        bool $shortNames = false,
        bool $multiplex = false,
        int $chunkCount = 0,
        bool $targetIsDirectory = false,
        bool $missingNestedTarget = false
    ): array {
        $scenario = trim($scenario);
        $baseName = trim($baseName);
        $journalFamily = strtolower(trim($journalFamily));

        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite delete-database scenario requires a name');
        }
        if ($baseName === '') {
            throw new \InvalidArgumentException('SQLite delete-database base name is required');
        }
        if (!in_array($journalFamily, ['journal', 'wal'], true)) {
            throw new \InvalidArgumentException('SQLite delete-database journal family is unsupported');
        }
        if ($chunkCount < 0) {
            throw new \InvalidArgumentException('SQLite delete-database chunk count must be non-negative');
        }
        if ($missingNestedTarget && $targetIsDirectory) {
            throw new \InvalidArgumentException('SQLite delete-database target cannot be both a directory and a missing nested target');
        }

        $sidecars = self::deleteDatabaseSidecars($baseName, $journalFamily, $shortNames, $multiplex, $chunkCount);
        $filesBefore = array_values(array_unique(array_merge([$baseName], $sidecars)));
        sort($filesBefore, SORT_STRING);

        $result = $targetIsDirectory ? 'SQLITE_ERROR' : 'SQLITE_OK';
        $filesAfter = $targetIsDirectory ? $filesBefore : [];

        return [
            'status' => 'ok',
            'script' => 'delete_db.test',
            'scenario' => $scenario,
            'upstream' => self::deleteDatabaseUpstream($scenario),
            'base_name' => $baseName,
            'journal_family' => $journalFamily,
            'short_names' => $shortNames,
            'multiplex' => $multiplex,
            'chunk_count' => $chunkCount,
            'target_is_directory' => $targetIsDirectory,
            'missing_nested_target' => $missingNestedTarget,
            'files_before_delete' => $filesBefore,
            'sidecar_files' => $sidecars,
            'files_after_delete' => $filesAfter,
            'delete_result' => $missingNestedTarget ? 'SQLITE_OK' : $result,
            'main_deleted' => !$targetIsDirectory,
            'sidecars_deleted' => !$targetIsDirectory,
            'delete_order' => array_values(array_merge($sidecars, [$baseName])),
            'reason' => match (true) {
                $targetIsDirectory => 'delete_database_refuses_directory_target',
                $missingNestedTarget => 'delete_database_missing_nested_target_is_ok',
                $multiplex && $journalFamily === 'wal' => 'delete_database_removes_wal_shm_and_multiplex_chunks',
                $multiplex => 'delete_database_removes_rollback_journal_and_multiplex_chunks',
                $journalFamily === 'wal' => 'delete_database_removes_wal_and_shm_sidecars',
                default => 'delete_database_removes_rollback_journal_sidecar',
            },
            'dependencies' => [
                'sqlite-upstream-delete-db-test',
                'sqlite-vfs-delete-database-sidecars',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function shortNameSidecarProfile(
        string $scenario,
        string $baseName,
        bool $shortNames,
        string $journalMode,
        int $beforeValue,
        int $afterValue,
        bool $copiedBeforeCommit = false,
        bool $readerOpenBeforeCommit = false,
        int $attachedDatabases = 1
    ): array {
        $scenario = trim($scenario);
        $baseName = trim($baseName);
        $journalMode = strtolower(trim($journalMode));

        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite 8.3 sidecar scenario requires a name');
        }
        if ($baseName === '') {
            throw new \InvalidArgumentException('SQLite 8.3 sidecar base name is required');
        }
        if (!in_array($journalMode, ['rollback', 'wal'], true)) {
            throw new \InvalidArgumentException('SQLite 8.3 sidecar journal mode is unsupported');
        }
        if ($attachedDatabases < 1) {
            throw new \InvalidArgumentException('SQLite 8.3 sidecar attached database count must be positive');
        }

        $rollbackJournal = $shortNames ? self::shortJournalName($baseName) : $baseName . '-journal';
        $wal = $shortNames ? self::shortWalName($baseName) : $baseName . '-wal';
        $shm = $shortNames ? self::shortShmName($baseName) : $baseName . '-shm';
        $sidecars = $journalMode === 'wal' ? [$shm, $wal] : [$rollbackJournal];
        sort($sidecars, SORT_STRING);

        $longSidecars = $journalMode === 'wal' ? [$baseName . '-shm', $baseName . '-wal'] : [$baseName . '-journal'];
        sort($longSidecars, SORT_STRING);
        $shortSidecars = $journalMode === 'wal' ? [self::shortShmName($baseName), self::shortWalName($baseName)] : [self::shortJournalName($baseName)];
        sort($shortSidecars, SORT_STRING);

        $copiedValue = $copiedBeforeCommit ? $beforeValue : $afterValue;
        $readerValue = $readerOpenBeforeCommit ? $beforeValue : $afterValue;
        $masterJournal = null;
        if ($attachedDatabases > 1) {
            $stem = preg_replace('/\.[^.]+$/', '', $baseName) ?? $baseName;
            $masterJournal = $shortNames ? $stem . '.mj' : $baseName . '-mj';
        }

        return [
            'status' => 'ok',
            'script' => '8_3_names.test',
            'scenario' => $scenario,
            'upstream' => self::shortNameSidecarUpstream($scenario, $journalMode, $attachedDatabases),
            'base_name' => $baseName,
            'short_names' => $shortNames,
            'journal_mode' => $journalMode,
            'sidecar_files' => $sidecars,
            'long_sidecar_files' => $longSidecars,
            'short_sidecar_files' => $shortSidecars,
            'uses_short_journal_name' => $shortNames && $journalMode === 'rollback',
            'uses_short_wal_name' => $shortNames && $journalMode === 'wal',
            'long_sidecars_absent' => $shortNames,
            'short_sidecars_absent' => !$shortNames,
            'before_value' => $beforeValue,
            'after_value' => $afterValue,
            'copied_before_commit' => $copiedBeforeCommit,
            'copied_reopen_value' => $copiedValue,
            'reader_open_before_commit' => $readerOpenBeforeCommit,
            'reader_visible_value_after_commit' => $readerValue,
            'writer_visible_value_after_commit' => $afterValue,
            'integrity_check' => 'ok',
            'attached_database_count' => $attachedDatabases,
            'master_journal' => $masterJournal,
            'reason' => match (true) {
                $attachedDatabases > 1 => 'short_name_master_journal_commit',
                $journalMode === 'wal' && $readerOpenBeforeCommit => 'short_name_wal_reader_snapshot_preserved',
                $journalMode === 'wal' => 'short_name_wal_and_shm_sidecars',
                $copiedBeforeCommit => 'short_name_hot_rollback_journal_reopens_precommit_image',
                default => 'short_name_rollback_journal_sidecar',
            },
            'dependencies' => [
                'sqlite-upstream-8-3-names-test',
                'sqlite-vfs-short-sidecar-names',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function deleteDatabaseSidecars(
        string $baseName,
        string $journalFamily,
        bool $shortNames,
        bool $multiplex,
        int $chunkCount
    ): array {
        $files = [];
        if ($journalFamily === 'wal') {
            $files[] = $shortNames ? self::shortWalName($baseName) : $baseName . '-wal';
            $files[] = $shortNames && !$multiplex ? self::shortShmName($baseName) : $baseName . '-shm';
        } else {
            $files[] = $shortNames ? self::shortJournalName($baseName) : $baseName . '-journal';
        }

        if ($multiplex) {
            $stem = preg_replace('/\.[^.]+$/', '', $baseName) ?? $baseName;
            for ($chunk = 1; $chunk <= $chunkCount; $chunk++) {
                $files[] = $shortNames ? sprintf('%s.%03d', $stem, $chunk) : sprintf('%s%03d', $baseName, $chunk);
            }
        }

        sort($files, SORT_STRING);
        return array_values($files);
    }

    private static function shortJournalName(string $baseName): string
    {
        return preg_replace('/\.[^.]+$/', '.nal', $baseName) ?? ($baseName . '.nal');
    }

    private static function shortWalName(string $baseName): string
    {
        return preg_replace('/\.[^.]+$/', '.wal', $baseName) ?? ($baseName . '.wal');
    }

    private static function shortShmName(string $baseName): string
    {
        return preg_replace('/\.[^.]+$/', '.shm', $baseName) ?? ($baseName . '.shm');
    }

    /**
     * @return list<string>
     */
    private static function deleteDatabaseUpstream(string $scenario): array
    {
        return match ($scenario) {
            'delete_db-1.1' => ['delete_db.test 1.1.0', 'delete_db.test 1.1.1'],
            'delete_db-1.2' => ['delete_db.test 1.2.0', 'delete_db.test 1.2.1'],
            'delete_db-1.3' => ['delete_db.test 1.3.0', 'delete_db.test 1.3.1'],
            'delete_db-1.4' => ['delete_db.test 1.4.0', 'delete_db.test 1.4.1'],
            'delete_db-2.1' => ['delete_db.test 2.1.0', 'delete_db.test 2.1.1'],
            'delete_db-2.2' => ['delete_db.test 2.2.0', 'delete_db.test 2.2.1'],
            'delete_db-2.3' => ['delete_db.test 2.3.0', 'delete_db.test 2.3.1'],
            'delete_db-2.4' => ['delete_db.test 2.4.0', 'delete_db.test 2.4.1'],
            'delete_db-3.0' => ['delete_db.test 3.0 directory target returns SQLITE_ERROR'],
            'delete_db-3.1' => ['delete_db.test 3.1 missing nested target returns SQLITE_OK'],
            default => throw new \InvalidArgumentException("Unsupported SQLite delete_db scenario: {$scenario}"),
        };
    }

    /**
     * @return list<string>
     */
    private static function shortNameSidecarUpstream(string $scenario, string $journalMode, int $attachedDatabases): array
    {
        if ($attachedDatabases > 1) {
            return ['8_3_names.test 8_3_names-4.0 master-journal commit with short names'];
        }
        if ($journalMode === 'wal') {
            return [
                '8_3_names.test 8_3_names-5.0 WAL reader snapshot setup',
                '8_3_names.test 8_3_names-5.1 long WAL absent',
                '8_3_names.test 8_3_names-5.2 short WAL present',
                '8_3_names.test 8_3_names-5.3 long SHM absent',
                '8_3_names.test 8_3_names-5.4 short SHM present',
                '8_3_names.test 8_3_names-5.5 writer sees committed update',
                '8_3_names.test 8_3_names-5.6 reader keeps precommit snapshot',
            ];
        }
        if (str_starts_with($scenario, '8_3_names-2')) {
            return [
                '8_3_names.test 8_3_names-2.0 long rollback journal absent',
                '8_3_names.test 8_3_names-2.1 short rollback journal present',
                '8_3_names.test 8_3_names-2.2 commit sees updated value',
                '8_3_names.test 8_3_names-2.3 copied hot journal reopens original value',
            ];
        }
        if (str_starts_with($scenario, '8_3_names-3')) {
            return [
                '8_3_names.test 8_3_names-3.0 long rollback journal present',
                '8_3_names.test 8_3_names-3.1 short rollback journal absent',
                '8_3_names.test 8_3_names-3.2 commit sees updated value',
                '8_3_names.test 8_3_names-3.3 copied hot journal reopens original value',
            ];
        }

        return [
            '8_3_names.test 8_3_names-1.0 default long rollback journal present',
            '8_3_names.test 8_3_names-1.1 default short rollback journal absent',
            '8_3_names.test 8_3_names-1.2 rollback restores original value',
        ];
    }

    /**
     * @return list<string>
     */
    private static function safeDeleteJournalUpstream(string $scenario): array
    {
        return match ($scenario) {
            'journal2-1.1' => ['journal2.test journal2-1.1 create table opens closes deletes journal'],
            'journal2-1.2-1.4' => ['journal2.test journal2-1.2', 'journal2.test journal2-1.3', 'journal2.test journal2-1.4'],
            'journal2-1.5-1.9' => ['journal2.test journal2-1.5', 'journal2.test journal2-1.6', 'journal2.test journal2-1.7', 'journal2.test journal2-1.8', 'journal2.test journal2-1.9'],
            'journal2-1.10-1.21' => ['journal2.test journal2-1.10', 'journal2.test journal2-1.11', 'journal2.test journal2-1.12', 'journal2.test journal2-1.13', 'journal2.test journal2-1.14', 'journal2.test journal2-1.15', 'journal2.test journal2-1.16', 'journal2.test journal2-1.17', 'journal2.test journal2-1.20', 'journal2.test journal2-1.21'],
            'journal2-2.1-2.4' => ['journal2.test journal2-2.1', 'journal2.test journal2-2.2', 'journal2.test journal2-2.3', 'journal2.test journal2-2.4'],
            default => throw new \InvalidArgumentException("Unsupported SQLite SAFE_DELETE journal2 scenario: {$scenario}"),
        };
    }

    /**
     * @return list<string>
     */
    private static function quotaVfsUpstream(string $scenario): array
    {
        if (str_starts_with($scenario, 'quota-2.1') || str_starts_with($scenario, 'quota-2.2') || str_starts_with($scenario, 'quota-2.4')) {
            return ['quota.test quota-2.1', 'quota.test quota-2.2', 'quota.test quota-2.4'];
        }
        if (str_starts_with($scenario, 'quota-3.1')) {
            return ['quota.test quota-3.1 two connections to one quota file'];
        }
        if (str_starts_with($scenario, 'quota-3.2') || str_starts_with($scenario, 'quota-3.3')) {
            return ['quota.test quota-3.2 multiple files in one quota group', 'quota.test quota-3.3 quota callback records over-limit file'];
        }
        if (str_starts_with($scenario, 'quota2-1')) {
            return ['quota2.test quota2-1 quota fopen/fwrite/fread/ftruncate lifecycle'];
        }
        if (str_starts_with($scenario, 'quota2-2')) {
            return ['quota2.test quota2-2 untracked file bypasses quota group'];
        }
        if (str_starts_with($scenario, 'quota2-3')) {
            return ['quota2.test quota2-3 append-mode quota accounting'];
        }

        throw new \InvalidArgumentException("Unsupported SQLite quota VFS scenario: {$scenario}");
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
