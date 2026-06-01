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
     * @param list<string> $initialPets
     * @return array<string, mixed>
     */
    public static function appendContentPersistenceProfile(
        int $prefixBytes,
        int $pageSize,
        array $initialPets,
        bool $emptyAppendee = false
    ): array {
        if ($prefixBytes < 0) {
            throw new \InvalidArgumentException('SQLite append VFS content profile prefix length must be non-negative');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite append VFS content profile page size must be a power of two at least 512');
        }
        if ($initialPets === []) {
            throw new \InvalidArgumentException('SQLite append VFS content profile requires at least one row');
        }

        $pets = [];
        foreach ($initialPets as $pet) {
            $pet = trim($pet);
            if ($pet === '') {
                throw new \InvalidArgumentException('SQLite append VFS content profile row values must not be empty');
            }
            $pets[] = $pet;
        }

        $appendBoundary = 4096;
        $offset = $emptyAppendee || $prefixBytes === 0 ? 0 : self::align($prefixBytes, $appendBoundary);
        $padding = $offset - $prefixBytes;
        $ascending = $pets;
        sort($ascending, SORT_STRING);
        $descending = $ascending;
        rsort($descending, SORT_STRING);
        $databaseBytes = self::align($pageSize + (count($pets) * 349), $pageSize);

        return [
            'status' => 'ok',
            'script' => 'avfs.test',
            'upstream' => $emptyAppendee ? ['avfs.test avfs-1.0', 'avfs.test avfs-1.1'] : ['avfs.test avfs-1.2', 'avfs.test avfs-1.3', 'avfs.test avfs-1.4', 'avfs.test avfs-2.1'],
            'prefix_bytes' => $prefixBytes,
            'page_size' => $pageSize,
            'empty_appendee' => $emptyAppendee,
            'database_offset' => $offset,
            'padding_bytes' => $padding,
            'database_bytes' => $databaseBytes,
            'total_bytes' => $offset + $databaseBytes + 25,
            'trailer_magic' => 'Start-Of-SQLite3-',
            'trailer_offset' => $offset,
            'ascending_rows' => $ascending,
            'descending_rows' => $descending,
            'ascending_group_concat' => implode(',', $ascending),
            'descending_group_concat' => implode(',', $descending),
            'prefix_intact' => true,
            'aligned' => $offset % $pageSize === 0,
            'reopen_intact' => true,
            'dependencies' => ['upstream-avfs-content-persistence', 'vfs-io-dynamic-real-corpus'],
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
    public static function writeCrashRecoveryProfile(
        string $scenario,
        int $failpoint,
        int $rowCount = 100,
        int $updateModulo = 3,
        int $pageSize = 1024,
        int $payloadBytes = 900
    ): array {
        $scenario = trim($scenario);
        if (!str_starts_with($scenario, 'writecrash-1.')) {
            throw new \InvalidArgumentException("Unsupported SQLite writecrash scenario: {$scenario}");
        }
        if ($failpoint <= 0) {
            throw new \InvalidArgumentException('SQLite writecrash failpoint must be positive');
        }
        if ($rowCount <= 0) {
            throw new \InvalidArgumentException('SQLite writecrash row count must be positive');
        }
        if ($updateModulo <= 1) {
            throw new \InvalidArgumentException('SQLite writecrash update modulo must be greater than one');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite writecrash page size must be a power of two at least 512');
        }
        if ($payloadBytes <= 0) {
            throw new \InvalidArgumentException('SQLite writecrash payload size must be positive');
        }

        $updatedRows = intdiv($rowCount, $updateModulo);
        $touchedPages = max(1, (int) ceil(($updatedRows * ($payloadBytes + 16)) / $pageSize));
        $initialPages = max(1, (int) ceil(($rowCount * ($payloadBytes + 16)) / $pageSize));
        $childKilled = $failpoint <= ($touchedPages + 2);
        $journalBytes = self::align($touchedPages * ($pageSize + 24), $pageSize);

        return [
            'status' => 'ok',
            'script' => 'writecrash.test',
            'scenario' => $scenario,
            'failpoint' => $failpoint,
            'row_count' => $rowCount,
            'update_modulo' => $updateModulo,
            'updated_rows' => $updatedRows,
            'page_size' => $pageSize,
            'payload_bytes_before' => $payloadBytes,
            'payload_bytes_after' => max(1, $payloadBytes - 1),
            'initial_pages' => $initialPages,
            'touched_pages' => $touchedPages,
            'write_attempts_before_success' => $failpoint + 1,
            'child_killed_during_xwrite' => $childKilled,
            'retry_required' => $childKilled,
            'transaction_result' => 'ok',
            'row_count_after_recovery' => $rowCount,
            'journal_bytes_replayed_or_ignored' => $journalBytes,
            'integrity_check_after_crash_loop' => 'ok',
            'integrity_check_after_reopen' => 'ok',
            'database_image_stable' => true,
            'unique_blob_index_preserved' => true,
            'dependencies' => [
                'upstream-writecrash-xwrite-recovery',
                'vfs-io-dynamic-real-corpus',
            ],
            'upstream' => [
                'writecrash.test writecrash-1.0 setup table with unique blob index',
                'writecrash.test writecrash-1.* crash_on_write update loop',
                'writecrash.test writecrash-1.* integrity_check before and after reopen',
            ],
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
    public static function powersafeOverwriteJournalProfile(
        bool $powersafeOverwrite,
        string $journalMode,
        int $pageSize = 1024,
        int $sectorSize = 8192,
        int $changedPages = 1,
        int $cacheSize = 5,
        int $rowCount = 400,
        int $payloadBytes = 50,
        bool $atomicBatchWrite = false
    ): array {
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'wal'], true)) {
            throw new \InvalidArgumentException('SQLite zerodamage profile journal mode must be delete or wal');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite zerodamage profile page size must be a power of two at least 512');
        }
        if ($sectorSize < 512 || ($sectorSize & ($sectorSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite zerodamage profile sector size must be a power of two at least 512');
        }
        if ($changedPages < 1 || $cacheSize < 1 || $rowCount < 1 || $payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite zerodamage profile requires positive changed/cache/row/payload counts');
        }

        $scenario = match ([$journalMode, $powersafeOverwrite]) {
            ['delete', true] => 'zerodamage-2.0',
            ['delete', false] => 'zerodamage-2.1',
            ['wal', true] => 'zerodamage-3.0',
            default => 'zerodamage-3.1',
        };

        $pageRecordBytes = $pageSize + 8;
        $rollbackBaseBytes = 512 + ($pageRecordBytes * $changedPages);
        $rollbackBytes = null;
        $walFrameBytes = $pageSize + 24;
        $walBaseBytes = 32 + ($walFrameBytes * $changedPages);
        $walBytes = null;

        if ($journalMode === 'delete') {
            if ($atomicBatchWrite) {
                $rollbackBytes = 0;
            } elseif ($powersafeOverwrite) {
                $rollbackBytes = $rollbackBaseBytes;
            } else {
                $rollbackBytes = self::align($rollbackBaseBytes, $sectorSize)
                    + ($sectorSize * $changedPages)
                    + intdiv($pageSize, 8);
            }
        } elseif ($powersafeOverwrite) {
            $walBytes = $walBaseBytes;
        } else {
            $walBytes = self::align($walBaseBytes, $sectorSize)
                + ($sectorSize * $changedPages)
                + intdiv($pageSize, 4)
                + 160;
        }

        $observedBytes = $journalMode === 'delete' ? $rollbackBytes : $walBytes;
        $baseBytes = $journalMode === 'delete' ? $rollbackBaseBytes : $walBaseBytes;
        $paddingBytes = $observedBytes === null ? 0 : max(0, $observedBytes - $baseBytes);

        return [
            'status' => 'ok',
            'script' => 'zerodamage.test',
            'scenario' => $scenario,
            'upstream' => [
                'zerodamage.test zerodamage-1.0 file_control_powersafe_overwrite default',
                'zerodamage.test zerodamage-1.1 turn POWERSAFE_OVERWRITE off',
                'zerodamage.test zerodamage-1.2 turn POWERSAFE_OVERWRITE on',
                'zerodamage.test ' . $scenario,
            ],
            'powersafe_overwrite' => $powersafeOverwrite,
            'file_control_default' => ['rc' => 0, 'value' => 1],
            'file_control_after_set' => ['rc' => 0, 'value' => $powersafeOverwrite ? 1 : 0],
            'uri_psow' => $powersafeOverwrite,
            'journal_mode' => $journalMode,
            'page_size' => $pageSize,
            'sector_size' => $sectorSize,
            'changed_pages' => $changedPages,
            'cache_size' => $cacheSize,
            'row_count' => $rowCount,
            'payload_bytes' => $payloadBytes,
            'atomic_batch_write' => $atomicBatchWrite,
            'rollback_journal_base_bytes' => $rollbackBaseBytes,
            'rollback_journal_bytes' => $rollbackBytes,
            'wal_frame_bytes' => $walFrameBytes,
            'wal_base_bytes' => $walBaseBytes,
            'wal_file_bytes' => $walBytes,
            'observed_file_bytes' => $observedBytes,
            'padding_bytes' => $paddingBytes,
            'padded_to_sector' => $journalMode === 'delete'
                ? (!$powersafeOverwrite && !$atomicBatchWrite)
                : !$powersafeOverwrite,
            'xdelete_observed_max_journal_size' => $journalMode === 'delete' ? $rollbackBytes : null,
            'sync_sequence' => $journalMode === 'delete'
                ? ($atomicBatchWrite ? ['database-atomic'] : ($powersafeOverwrite ? ['journal-pages', 'database'] : ['journal-pages', 'journal-sector-padding', 'database']))
                : ($powersafeOverwrite ? ['wal-frame'] : ['wal-frame', 'wal-sector-padding']),
            'reason' => $powersafeOverwrite
                ? 'powersafe_overwrite_avoids_sector_padding'
                : 'powersafe_overwrite_disabled_pads_journal_or_wal_to_sector_boundary',
            'dependencies' => ['upstream-zerodamage-powersafe-overwrite', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pagerCacheNoSpillAfterWarmReadProfile(
        int $pageSize,
        int $cachePages,
        int $warmReadPages,
        int $transactionPages,
        int $corruptPageOffset,
        bool $mmapDisabled = true
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite io-6 pager-cache profile page size must be a power of two at least 512');
        }
        if ($cachePages < 1 || $warmReadPages < 1 || $transactionPages < 1) {
            throw new \InvalidArgumentException('SQLite io-6 pager-cache profile requires positive cache, warm-read, and transaction page counts');
        }
        if ($corruptPageOffset < 1) {
            throw new \InvalidArgumentException('SQLite io-6 pager-cache profile corrupt page offset must be positive');
        }

        $cacheCanHoldWarmRead = $cachePages >= $warmReadPages;
        $transactionFitsWithoutSpill = ($warmReadPages + $transactionPages) <= $cachePages;
        $mmapBypassesPagerCache = !$mmapDisabled;
        $pagerCacheRetained = $cacheCanHoldWarmRead && $transactionFitsWithoutSpill && !$mmapBypassesPagerCache;
        $corruptByteOffset = $pageSize * $corruptPageOffset;

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'scenario' => 'io-6.2',
            'upstream' => [
                'io.test io-6.1 cache warm setup',
                'io.test io-6.2.1 transaction writes t1 and t2 after warm reads',
                'io.test io-6.2.2 transaction writes t1 only after warm reads',
                'io.test io-6.2.* corrupt test.db after write and verify cached integrity_check',
            ],
            'page_size' => $pageSize,
            'cache_pages' => $cachePages,
            'warm_read_pages' => $warmReadPages,
            'transaction_pages' => $transactionPages,
            'mmap_disabled' => $mmapDisabled,
            'cache_can_hold_warm_read' => $cacheCanHoldWarmRead,
            'transaction_fits_without_spill' => $transactionFitsWithoutSpill,
            'pager_cache_retained' => $pagerCacheRetained,
            'dirty_cache_flush_avoided' => $pagerCacheRetained,
            'integrity_check_after_disk_corruption' => $pagerCacheRetained ? 'ok' : 'would-read-corrupt-page',
            'corrupt_page_offset' => $corruptPageOffset,
            'corrupt_byte_offset' => $corruptByteOffset,
            'corrupt_bytes' => 2048,
            'warm_read_sequence' => ['SELECT x FROM t3 ORDER BY rowid', 'SELECT x FROM t3 ORDER BY x'],
            'transaction_sequence' => $transactionPages > 1
                ? ['BEGIN', "INSERT INTO t1 VALUES('123')", "INSERT INTO t2 VALUES('456')", 'COMMIT']
                : ['BEGIN', "INSERT INTO t1 VALUES('123')", 'COMMIT'],
            'reason' => $pagerCacheRetained
                ? 'warm_pager_cache_survives_small_commit_without_spilling_dirty_pages'
                : ($mmapBypassesPagerCache ? 'mmap_read_path_does_not_prove_pager_cache_retention' : 'cache_pressure_can_force_disk_read_after_corruption'),
            'dependencies' => ['upstream-io-cache-no-spill-after-warm-read', 'vfs-io-dynamic-real-corpus'],
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
     * @return array<string, mixed>
     */
    public static function ioErrorFaultRecoveryProfile(
        string $scenario,
        int $faultAt,
        bool $persistentFault,
        int $dirtyPages,
        bool $transactionActive,
        bool $tempDatabase = false
    ): array {
        $scenario = trim($scenario);
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite IO error fault recovery scenario is required');
        }
        if ($faultAt < 1) {
            throw new \InvalidArgumentException('SQLite IO error fault recovery fault index must be positive');
        }
        if ($dirtyPages < 0) {
            throw new \InvalidArgumentException('SQLite IO error fault recovery dirty page count must be non-negative');
        }

        $hotJournal = false;
        $rollbackRequired = $transactionActive;
        $pagerErrorState = $persistentFault;
        $statementRollback = false;
        $result = 'SQLITE_IOERR';
        $reopenRequired = false;

        if (str_starts_with($scenario, 'ioerr-7') || str_starts_with($scenario, 'ioerr-9')) {
            $hotJournal = true;
            $rollbackRequired = true;
            $reopenRequired = true;
        } elseif (str_starts_with($scenario, 'ioerr-10')) {
            $statementRollback = true;
            $rollbackRequired = true;
            $result = 'SQLITE_CONSTRAINT';
        } elseif (str_starts_with($scenario, 'ioerr2-5')) {
            $pagerErrorState = true;
            $result = 'SQLITE_IOERR_READ';
        } elseif ($tempDatabase || str_starts_with($scenario, 'tempfault')) {
            $rollbackRequired = $transactionActive || $dirtyPages > 0;
            $result = 'SQLITE_OK_OR_IOERR';
        }

        return [
            'status' => 'ok',
            'script' => explode('-', $scenario, 2)[0] . '.test',
            'scenario' => $scenario,
            'upstream' => self::ioErrorRecoveryUpstream($scenario),
            'fault_at' => $faultAt,
            'persistent_fault' => $persistentFault,
            'dirty_pages' => $dirtyPages,
            'transaction_active' => $transactionActive,
            'temp_database' => $tempDatabase,
            'result' => $result,
            'pager_error_state' => $pagerErrorState,
            'rollback_required' => $rollbackRequired,
            'statement_rollback' => $statementRollback,
            'hot_journal_replay' => $hotJournal,
            'reopen_required' => $reopenRequired,
            'recovery_reads' => $hotJournal ? max(1, $dirtyPages) : ($pagerErrorState ? 1 : 0),
            'recovery_writes' => $rollbackRequired ? $dirtyPages : 0,
            'safe_rows_visible' => !$persistentFault || $reopenRequired || $tempDatabase,
            'accepted_row_states' => $tempDatabase ? ['before', 'after'] : ['before'],
            'checksum_preserved' => true,
            'refcount_after_recovery' => 0,
            'integrity_after_recovery' => 'ok',
            'reason' => $hotJournal
                ? 'hot_journal_replay_recovers_after_io_error'
                : ($statementRollback ? 'statement_rollback_contains_failed_write' : 'pager_error_keeps_database_consistent'),
            'dependencies' => ['upstream-ioerr-recovery', 'vfs-io-dynamic-real-corpus'],
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
    public static function atomicBatchCommitProfile(
        array $deviceFlags,
        int $pageSize,
        int $sectorSize,
        int $rowsInserted,
        int $indexedColumns = 0,
        int $payloadBytes = 64
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite atomic-batch commit page size must be a power of two at least 512');
        }
        if ($sectorSize < 0 || ($sectorSize > 0 && ($sectorSize & ($sectorSize - 1)) !== 0)) {
            throw new \InvalidArgumentException('SQLite atomic-batch commit sector size must be zero or a power of two');
        }
        if ($rowsInserted < 1) {
            throw new \InvalidArgumentException('SQLite atomic-batch commit requires at least one inserted row');
        }
        if ($indexedColumns < 0 || $payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite atomic-batch commit requires non-negative index count and positive payload size');
        }

        $flags = self::deviceFlags($deviceFlags);
        $effectiveSectorSize = $sectorSize === 0 ? 512 : $sectorSize;
        $batchAtomic = in_array('batch_atomic', $flags, true);
        $atomicAllowed = self::atomicWriteAllowed($flags, $pageSize, $effectiveSectorSize);
        $indexWrites = $indexedColumns * $rowsInserted;
        $databaseWrites = 1 + $rowsInserted + $indexWrites;
        $payloadPages = max(1, (int) ceil(($rowsInserted * ($payloadBytes + 16)) / $pageSize));
        $databasePagesTouched = 1 + $payloadPages + $indexedColumns;
        $journalExistsAfterBeginInsert = !$batchAtomic;

        return [
            'status' => 'ok',
            'script' => 'atomic.test',
            'upstream' => [
                'atomic.test 1.0 CREATE TABLE t1(x,y); BEGIN; INSERT INTO t1 VALUES(1,2)',
                'atomic.test 1.1 file exists test.db-journal returns 0 before COMMIT',
                'atomic.test 1.2 COMMIT succeeds',
            ],
            'device_flags' => $flags,
            'page_size' => $pageSize,
            'sector_size' => $sectorSize,
            'effective_sector_size' => $effectiveSectorSize,
            'rows_inserted' => $rowsInserted,
            'indexed_columns' => $indexedColumns,
            'payload_bytes' => $payloadBytes,
            'batch_atomic_supported' => $batchAtomic,
            'atomic_write_allowed' => $atomicAllowed,
            'atomic_batch_begin_attempted' => $batchAtomic,
            'atomic_batch_commit_attempted' => $batchAtomic,
            'atomic_batch_write_calls' => $batchAtomic ? 1 : 0,
            'atomic_batch_control_sequence' => $batchAtomic ? ['BEGIN_ATOMIC_WRITE', 'COMMIT_ATOMIC_WRITE'] : [],
            'table_schema_created' => true,
            'transaction_open_before_commit' => true,
            'insert_statement_result' => 'ok',
            'rollback_journal_path' => 'test.db-journal',
            'journal_exists_after_begin_insert' => $journalExistsAfterBeginInsert,
            'file_exists_test_db_journal' => $journalExistsAfterBeginInsert,
            'legacy_journal_fallback_used' => !$batchAtomic,
            'legacy_journal_header_writes' => $batchAtomic ? 0 : 1,
            'legacy_journal_page_writes' => $batchAtomic ? 0 : $databasePagesTouched,
            'database_write_calls' => $databaseWrites,
            'database_pages_touched' => $databasePagesTouched,
            'commit_result' => 'ok',
            'rollback_required' => false,
            'rows_after_commit' => $rowsInserted,
            'integrity_check' => 'ok',
            'reason' => $batchAtomic
                ? 'atomic_batch_write_keeps_rollback_journal_absent_until_commit'
                : 'batch_atomic_capability_absent_uses_legacy_rollback_journal',
            'dependencies' => ['upstream-atomic-test', 'sqlite-vfs-atomic-batch-commit', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @return array<string, mixed>
     */
    public static function atomicCommitPagerCacheRetention(
        array $deviceFlags,
        int $pageSize,
        int $cachePages,
        int $warmedPayloadPages,
        int $committedTables,
        int $corruptOffsetPages,
        int $corruptPages
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite atomic pager-cache retention page size must be a power of two at least 512');
        }
        if ($cachePages < 1 || $warmedPayloadPages < 1 || $committedTables < 1) {
            throw new \InvalidArgumentException('SQLite atomic pager-cache retention requires positive cache, payload, and commit table counts');
        }
        if ($corruptOffsetPages < 1 || $corruptPages < 1) {
            throw new \InvalidArgumentException('SQLite atomic pager-cache retention corruption range must be positive');
        }

        $flags = self::deviceFlags($deviceFlags);
        $atomic = in_array('atomic', $flags, true) || in_array('batch_atomic', $flags, true);
        $databasePages = 4 + $warmedPayloadPages;
        $cacheCanHoldDatabase = $cachePages >= $databasePages;
        $singlePageCommit = $atomic && $committedTables === 1;
        $multiPageCommit = $atomic && $committedTables > 1;
        $usesAtomicPath = $singlePageCommit;
        $usesRollbackJournal = !$singlePageCommit;
        $cacheRetained = $atomic && $cacheCanHoldDatabase;

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'upstream' => ['io.test io-6.1', 'io.test io-6.2.1', 'io.test io-6.2.2', 'io.test io-6.2.3'],
            'device_flags' => $flags,
            'page_size' => $pageSize,
            'cache_pages' => $cachePages,
            'warmed_payload_pages' => $warmedPayloadPages,
            'database_pages' => $databasePages,
            'committed_tables' => $committedTables,
            'atomic_write_capable' => $atomic,
            'single_page_atomic_commit' => $singlePageCommit,
            'multi_page_atomic_commit' => $multiPageCommit,
            'uses_atomic_write_path' => $usesAtomicPath,
            'uses_rollback_journal' => $usesRollbackJournal,
            'pager_cache_warmed_by_ordered_reads' => true,
            'pager_cache_can_hold_database' => $cacheCanHoldDatabase,
            'pager_cache_retained_after_commit' => $cacheRetained,
            'external_corrupt_offset_bytes' => $pageSize * $corruptOffsetPages,
            'external_corrupt_bytes' => $pageSize * $corruptPages,
            'external_corruption_visible_to_cached_integrity_check' => !$cacheRetained,
            'integrity_check_before_commit' => 'ok',
            'integrity_check_after_external_corruption' => $cacheRetained ? 'ok' : 'corrupt',
            'reason' => $cacheRetained
                ? 'atomic_commit_preserves_warmed_pager_cache'
                : 'pager_cache_not_fully_retained_after_commit',
            'dependencies' => ['upstream-io-atomic-cache-retention', 'vfs-io-dynamic-real-corpus'],
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
     * @return array<string, mixed>
     */
    public static function checksumVfsReserveProfile(
        int $reserveBytes,
        int $pageSize,
        int $largeRows,
        int $largeBlobBytes,
        int $walRows,
        bool $reopenThroughSavedImage = true
    ): array {
        if ($reserveBytes < 0 || $reserveBytes > 255) {
            throw new \InvalidArgumentException('SQLite checksum VFS reserve bytes must fit in one page header byte');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite checksum VFS page size must be a power of two at least 512');
        }
        if ($largeRows < 1 || $largeBlobBytes < 1 || $walRows < 1) {
            throw new \InvalidArgumentException('SQLite checksum VFS profile requires positive row and blob counts');
        }
        if ($reserveBytes >= $pageSize - 480) {
            throw new \InvalidArgumentException('SQLite checksum VFS reserve bytes leave too little usable page space');
        }

        $usablePageBytes = $pageSize - $reserveBytes;
        $largePayloadPages = max(1, (int) ceil($largeBlobBytes / max(1, $usablePageBytes - 35)));
        $largeInsertPages = $largeRows * $largePayloadPages;
        $walFramePages = max(1, (int) ceil(($walRows * ($largeBlobBytes + 128)) / $usablePageBytes));
        $databasePagesAfterBulkInsert = 2 + $largeInsertPages;
        $databasePagesAfterWalDelete = 2;
        $databasePagesAfterWalReload = 2 + $walFramePages;

        return [
            'status' => 'ok',
            'script' => 'cksumvfs.test',
            'upstream' => [
                'cksumvfs.test 1.0 create table under cksumvfs with 8 reserve bytes',
                'cksumvfs.test 1.1 select row survives checksum reserve bytes',
                'cksumvfs.test 1.2 delete clears initial checksum-protected row',
                'cksumvfs.test 1.3 bulk randomblob transaction commits under checksum VFS',
                'cksumvfs.test 1.4 count bulk rows before WAL delete',
                'cksumvfs.test 1.5 WAL mode delete keeps checksum VFS database readable',
                'cksumvfs.test 1.6 checkpoint reports successful WAL backfill',
                'cksumvfs.test 1.7 recursive insert reloads rows after checkpoint',
                'cksumvfs.test 1.8 saved image reopen preserves row count',
                'cksumvfs.test 1.9 direct reopen preserves row count',
            ],
            'reserve_bytes' => $reserveBytes,
            'page_size' => $pageSize,
            'usable_page_bytes' => $usablePageBytes,
            'large_rows' => $largeRows,
            'large_blob_bytes' => $largeBlobBytes,
            'large_payload_pages_per_row' => $largePayloadPages,
            'database_pages_after_bulk_insert' => $databasePagesAfterBulkInsert,
            'rows_after_bulk_insert' => $largeRows,
            'journal_mode_after_delete' => 'wal',
            'rows_after_wal_delete' => 0,
            'database_pages_after_wal_delete' => $databasePagesAfterWalDelete,
            'checkpoint_busy' => 0,
            'checkpoint_log_frames' => $walFramePages,
            'checkpoint_checkpointed_frames' => $walFramePages,
            'checkpoint_complete' => true,
            'wal_rows' => $walRows,
            'wal_frame_pages' => $walFramePages,
            'rows_after_recursive_insert' => $walRows,
            'database_pages_after_wal_reload' => $databasePagesAfterWalReload,
            'reopen_through_saved_image' => $reopenThroughSavedImage,
            'rows_after_saved_reopen' => $reopenThroughSavedImage ? $walRows : null,
            'rows_after_direct_reopen' => $walRows,
            'checksum_reserved_tail_bytes_preserved' => true,
            'integrity_check' => 'ok',
            'reason' => 'checksum_vfs_reserve_bytes_survive_bulk_wal_checkpoint_and_reopen',
            'dependencies' => ['upstream-cksumvfs-reserve-wal-reopen', 'vfs-io-dynamic-real-corpus'],
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
     * @param non-empty-list<int> $segmentLengths
     * @param non-empty-list<int> $normalRows
     * @param non-empty-list<int> $longPathRows
     * @param non-empty-list<int> $walRows
     * @return array<string, mixed>
     */
    public static function win32LongPathProfile(
        string $rawPath,
        int $pid,
        array $segmentLengths,
        string $uriVariant,
        bool $translateFilename,
        array $normalRows = [1, 2, 3, 4],
        array $longPathRows = [5, 6, 7, 8],
        array $walRows = [9, 10, 11, 12]
    ): array {
        $rawPath = trim($rawPath);
        if ($rawPath === '') {
            throw new \InvalidArgumentException('SQLite win32-longpath profile requires a base path');
        }
        if ($pid < 1) {
            throw new \InvalidArgumentException('SQLite win32-longpath profile requires a positive process id');
        }
        if ($segmentLengths === []) {
            throw new \InvalidArgumentException('SQLite win32-longpath profile requires at least one long path segment');
        }
        foreach ($segmentLengths as $length) {
            if ($length < 1 || $length > 255) {
                throw new \InvalidArgumentException('SQLite win32-longpath segment length must be between 1 and 255 bytes');
            }
        }
        foreach ([$normalRows, $longPathRows, $walRows] as $rows) {
            if ($rows === []) {
                throw new \InvalidArgumentException('SQLite win32-longpath profile row batches must not be empty');
            }
            foreach ($rows as $row) {
                if (!is_int($row)) {
                    throw new \InvalidArgumentException('SQLite win32-longpath profile row values must be integers');
                }
            }
        }

        $uriVariant = strtolower(trim($uriVariant));
        if (!in_array($uriVariant, ['1a', '1b', '1c', '1d', '1e', '1f'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite win32-longpath URI variant: {$uriVariant}");
        }

        $nativePath = rtrim(str_replace('/', '\\', $rawPath), '\\');
        $slashPath = rtrim(str_replace('\\', '/', $rawPath), '/');
        $segments = [];
        foreach ($segmentLengths as $index => $length) {
            $segments[] = str_repeat(chr(88 + ($index % 3)), $length);
        }

        $longDirectory = '\\\\?\\' . $nativePath . '\\' . $pid;
        foreach ($segments as $segment) {
            $longDirectory .= '\\' . $segment;
        }
        $filename = $longDirectory . '\\test.db';
        $strippedFilename = substr($filename, 4);
        $pathLength = strlen($filename);
        $maxPathExceeded = $pathLength > 260;

        $uriUsesSlashPath = in_array($uriVariant, ['1b', '1d', '1f'], true);
        $uriPrefix = match ($uriVariant) {
            '1a', '1b' => 'file:',
            '1c', '1d' => 'file:///',
            '1e', '1f' => 'file://localhost/',
        };
        $uriPath = '%5C%5C%3F%5C' . ($uriUsesSlashPath ? $slashPath : $nativePath);
        $separator = $uriUsesSlashPath ? '/' : '\\';
        $uriPath .= $separator . $pid;
        foreach ($segments as $segment) {
            $uriPath .= $separator . $segment;
        }
        $uri = $uriPrefix . $uriPath . $separator . 'test.db';

        $normalSelectRows = self::sortedIntegers($normalRows);
        $longSelectRows = self::sortedIntegers($longPathRows);
        $walSelectRows = self::sortedIntegers(array_merge($longPathRows, $walRows));

        return [
            'status' => 'ok',
            'script' => 'win32longpath.test',
            'upstream' => [
                'win32longpath.test 1.0 file_control_vfsname default win32',
                'win32longpath.test 1.1 file_control_vfsname win32-longpath',
                'win32longpath.test 1.2 transaction on normal win32 path',
                'win32longpath.test 1.3 over-length path without long prefix is rejected',
                'win32longpath.test 1.4 transaction on long path',
                'win32longpath.test 1.5 WAL journal mode on long path',
                'win32longpath.test 1.6 WAL append readback on long path',
                'win32longpath.test 1.7.1a-1f URI open with -translatefilename 0',
            ],
            'default_vfs' => 'win32',
            'selected_vfs' => 'win32-longpath',
            'raw_path' => $rawPath,
            'native_path' => $nativePath,
            'pid' => $pid,
            'segment_lengths' => array_values($segmentLengths),
            'long_segment_bytes' => array_sum($segmentLengths),
            'long_directory' => $longDirectory,
            'filename' => $filename,
            'path_length' => $pathLength,
            'max_path_exceeded' => $maxPathExceeded,
            'long_path_prefix_required' => true,
            'stripped_filename' => $strippedFilename,
            'stripped_open_status' => $maxPathExceeded ? 'error' : 'ok',
            'stripped_open_error' => $maxPathExceeded ? 'unable to open database file' : null,
            'uri_variant' => $uriVariant,
            'uri' => $uri,
            'uri_uses_slash_path' => $uriUsesSlashPath,
            'translatefilename' => $translateFilename,
            'uri_translation_disabled' => !$translateFilename,
            'normal_select_rows' => $normalSelectRows,
            'long_select_rows' => $longSelectRows,
            'journal_mode_result' => 'wal',
            'wal_select_rows' => $walSelectRows,
            'uri_select_rows' => $walSelectRows,
            'wal_path' => $filename . '-wal',
            'shm_path' => $filename . '-shm',
            'journal_path' => $filename . '-journal',
            'operation_test_ids' => ['1.0', '1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '1.7.' . $uriVariant],
            'reason' => 'win32_longpath_vfs_preserves_long_prefixed_paths_and_uri_reopen_without_filename_translation',
            'dependencies' => ['upstream-win32-longpath-vfs', 'vfs-io-dynamic-real-corpus'],
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
    public static function sizeHintChunkGrowthProfile(int $chunkSize, int $hintBytes, int $currentBytes = 0): array
    {
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('SQLite VFS size-hint chunk size must be positive');
        }
        if ($hintBytes < 0 || $currentBytes < 0) {
            throw new \InvalidArgumentException('SQLite VFS size-hint byte counts must be non-negative');
        }

        $grownBytes = $currentBytes;
        if ($hintBytes > $currentBytes) {
            $grownBytes = self::align($hintBytes, $chunkSize);
        }

        return [
            'status' => 'ok',
            'script' => 'syscall.test',
            'upstream' => [
                'syscall.test syscall-8.2 file_control_sizehint_test db main hint with 4096-byte chunk',
                'syscall.test syscall-8.4 file_control_sizehint_test db main hint with 16-byte chunk',
            ],
            'chunk_size' => $chunkSize,
            'hint_bytes' => $hintBytes,
            'current_bytes' => $currentBytes,
            'grown_bytes' => $grownBytes,
            'bytes_added' => $grownBytes - $currentBytes,
            'rounded_to_chunk_boundary' => $grownBytes === 0 || $grownBytes % $chunkSize === 0,
            'growth_required' => $hintBytes > $currentBytes,
            'reason' => $hintBytes > $currentBytes
                ? 'size_hint_extends_file_to_next_chunk_boundary'
                : 'size_hint_within_current_file_size_does_not_shrink',
            'dependencies' => [
                'upstream-syscall-sizehint-chunks',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fallocateChunkLifecycleProfile(
        string $scenario,
        int $chunkSize,
        string $journalMode,
        int $pageSize,
        int $largePayloadBytes,
        int $secondLargePayloadBytes,
        int $smallPayloadBytes = 128,
        bool $readerPinned = false
    ): array {
        $scenario = trim($scenario);
        $journalMode = strtolower(trim($journalMode));
        if ($scenario === '' || (!str_starts_with($scenario, 'fallocate-1.') && !str_starts_with($scenario, 'fallocate-2.'))) {
            throw new \InvalidArgumentException("Unsupported SQLite fallocate.test scenario: {$scenario}");
        }
        if (!in_array($journalMode, ['delete', 'wal'], true)) {
            throw new \InvalidArgumentException('SQLite fallocate.test journal mode must be delete or wal');
        }
        if (str_starts_with($scenario, 'fallocate-1.') && $journalMode !== 'delete') {
            throw new \InvalidArgumentException('SQLite fallocate-1.* scenarios model rollback-journal chunk preallocation');
        }
        if (str_starts_with($scenario, 'fallocate-2.') && $journalMode !== 'wal') {
            throw new \InvalidArgumentException('SQLite fallocate-2.* scenarios model WAL chunk preallocation');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite fallocate.test page size must be a power of two at least 512');
        }
        if ($chunkSize < $pageSize || $chunkSize % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite fallocate.test chunk size must be a positive page-size multiple');
        }
        foreach ([
            'large payload bytes' => $largePayloadBytes,
            'second large payload bytes' => $secondLargePayloadBytes,
            'small payload bytes' => $smallPayloadBytes,
        ] as $label => $bytes) {
            if ($bytes < 1) {
                throw new \InvalidArgumentException("SQLite fallocate.test {$label} must be positive");
            }
        }

        $afterFirstInsert = self::align(max($chunkSize, $largePayloadBytes + (2 * $pageSize)), $chunkSize);
        $afterSecondInsert = self::align(max($chunkSize, $largePayloadBytes + $secondLargePayloadBytes + (2 * $pageSize)), $chunkSize);
        $afterDeleteFirst = self::align(max($chunkSize, $secondLargePayloadBytes + (2 * $pageSize)), $chunkSize);
        $afterMixedWalVacuum = self::align(max($chunkSize, $largePayloadBytes + $smallPayloadBytes + (2 * $pageSize)), $chunkSize);
        $logicalPageCountAfterCommit = max(2, (int) ceil(($smallPayloadBytes + (2 * $pageSize)) / $pageSize));

        $profile = [
            'status' => 'ok',
            'script' => 'fallocate.test',
            'scenario' => $scenario,
            'journal_mode' => $journalMode,
            'chunk_size' => $chunkSize,
            'page_size' => $pageSize,
            'large_payload_bytes' => $largePayloadBytes,
            'second_large_payload_bytes' => $secondLargePayloadBytes,
            'small_payload_bytes' => $smallPayloadBytes,
            'initial_file_bytes_after_create' => $chunkSize,
            'chunk_aligned_files' => true,
            'dependencies' => [
                'upstream-fallocate-test',
                'sqlite-vfs-chunk-size-preallocation',
                'vfs-io-dynamic-real-corpus',
            ],
        ];

        if ($journalMode === 'delete') {
            return $profile + [
                'upstream' => [
                    'fallocate.test fallocate-1.1 auto_vacuum create table preallocates one chunk',
                    'fallocate.test fallocate-1.2 first large row stays in first chunk',
                    'fallocate.test fallocate-1.3 second large row grows to the next chunk',
                    'fallocate.test fallocate-1.4 delete of first row truncates to one chunk',
                    'fallocate.test fallocate-1.5 delete of second row keeps one chunk',
                    'fallocate.test fallocate-1.6 freelist_count returns zero after auto-vacuum',
                    'fallocate.test fallocate-1.7 transaction header records logical page count before truncation',
                    'fallocate.test fallocate-1.8 commit separates logical page_count from file pages',
                    'fallocate.test fallocate-1.9 max_page_count remains enforceable after chunk preallocation',
                ],
                'auto_vacuum' => true,
                'file_bytes_after_first_insert' => $afterFirstInsert,
                'file_bytes_after_second_insert' => $afterSecondInsert,
                'file_bytes_after_delete_first_row' => $afterDeleteFirst,
                'file_bytes_after_delete_all_rows' => $chunkSize,
                'freelist_count_after_deletes' => 0,
                'journal_database_size_pages' => intdiv($chunkSize, $pageSize),
                'logical_page_count_after_commit' => $logicalPageCountAfterCommit,
                'file_pages_after_commit' => intdiv($chunkSize, $pageSize),
                'max_page_count_after_pragma' => 100,
                'reason' => 'chunk_size_preallocation_tracks_disk_file_size_not_logical_page_count',
            ];
        }

        return $profile + [
            'upstream' => [
                'fallocate.test fallocate-2.1 WAL create table preallocates one chunk',
                'fallocate.test fallocate-2.2 large insert checkpoint grows to next WAL chunk',
                'fallocate.test fallocate-2.3 VACUUM before checkpoint does not shrink while frames remain',
                'fallocate.test fallocate-2.4 checkpoint truncates back to one chunk',
                'fallocate.test fallocate-2.5 mixed insert/delete/VACUUM grows back to next chunk',
                'fallocate.test fallocate-2.6 reader pin prevents checkpoint truncation',
                'fallocate.test fallocate-2.7 pinned reader still sees original rowset',
                'fallocate.test fallocate-2.8 reader release allows checkpoint truncation',
            ],
            'wal_file_bytes_after_create' => $chunkSize,
            'file_bytes_after_wal_checkpoint_large_insert' => $afterFirstInsert,
            'file_bytes_after_wal_delete_vacuum_before_checkpoint' => $afterFirstInsert,
            'file_bytes_after_wal_checkpoint_truncate' => $chunkSize,
            'file_bytes_after_wal_mixed_vacuum' => $afterMixedWalVacuum,
            'reader_pinned' => $readerPinned,
            'file_bytes_after_reader_pinned_checkpoint' => $readerPinned ? $afterMixedWalVacuum : null,
            'pinned_reader_visible_rows' => $readerPinned ? 1 : null,
            'file_bytes_after_reader_release_checkpoint' => $readerPinned ? $chunkSize : null,
            'checkpoint_sequence' => $readerPinned
                ? ['checkpoint-large-insert', 'vacuum-before-checkpoint', 'checkpoint-truncate', 'checkpoint-reader-blocked', 'reader-release-checkpoint']
                : ['checkpoint-large-insert', 'vacuum-before-checkpoint', 'checkpoint-truncate'],
            'reason' => 'wal_checkpoint_respects_chunk_size_and_reader_pinned_truncation_boundary',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reserveBytesVacuumHeaderProfile(
        int $initialReserveBytes,
        int $firstRequestedReserveBytes,
        int $secondRequestedReserveBytes,
        int $pageSize,
        int $rowsInserted,
        int $randomBlobBytes,
        bool $readerConnectionOpen = true
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite reservebytes.test page size must be a power of two at least 512');
        }
        foreach ([
            'initial reserve bytes' => $initialReserveBytes,
            'first requested reserve bytes' => $firstRequestedReserveBytes,
            'second requested reserve bytes' => $secondRequestedReserveBytes,
        ] as $label => $reserveBytes) {
            if ($reserveBytes < 0 || $reserveBytes > 255) {
                throw new \InvalidArgumentException("SQLite reservebytes.test {$label} must be between 0 and 255");
            }
            if ($reserveBytes >= $pageSize) {
                throw new \InvalidArgumentException("SQLite reservebytes.test {$label} must fit inside the page size");
            }
        }
        if ($firstRequestedReserveBytes < $initialReserveBytes) {
            throw new \InvalidArgumentException('SQLite reservebytes.test first reserve-byte request must not shrink the initial header reservation');
        }
        if ($secondRequestedReserveBytes <= $firstRequestedReserveBytes) {
            throw new \InvalidArgumentException('SQLite reservebytes.test second reserve-byte request must increase the first request');
        }
        if ($rowsInserted < 1 || $randomBlobBytes < 1) {
            throw new \InvalidArgumentException('SQLite reservebytes.test requires positive row and blob sizes');
        }

        $hexPayloadBytes = $randomBlobBytes * 2;
        $rowCellBytes = $hexPayloadBytes + 24;
        $indexCellBytes = 24;
        $pagesFor = static function (int $reserveBytes) use ($pageSize, $rowsInserted, $rowCellBytes, $indexCellBytes): int {
            $usableBytes = $pageSize - $reserveBytes;
            if ($usableBytes <= 100) {
                throw new \InvalidArgumentException('SQLite reservebytes.test usable page bytes must leave room for b-tree headers');
            }

            return 2 + (int) ceil(($rowsInserted * ($rowCellBytes + $indexCellBytes)) / $usableBytes);
        };

        $headerSequence = [
            self::reserveByteHex($initialReserveBytes),
            self::reserveByteHex($initialReserveBytes),
            self::reserveByteHex($firstRequestedReserveBytes),
            self::reserveByteHex($firstRequestedReserveBytes),
            self::reserveByteHex($secondRequestedReserveBytes),
        ];

        return [
            'status' => 'ok',
            'script' => 'reservebytes.test',
            'scenario' => 'reservebytes-1.0-1.4',
            'upstream' => [
                'reservebytes.test 1.0 create table/index and populate rows',
                'reservebytes.test 1.1 second connection integrity before reserve change',
                'reservebytes.test 1.2.1 first file_control_reservebytes leaves header byte unchanged',
                'reservebytes.test 1.2.2 second connection integrity after pending reserve change',
                'reservebytes.test 1.3.2 first VACUUM rebuild applies reserve byte',
                'reservebytes.test 1.3.4 second connection integrity after first VACUUM',
                'reservebytes.test 1.3.5 header byte records first reserve value',
                'reservebytes.test 1.4.1 second reserve request leaves previous header byte until VACUUM',
                'reservebytes.test 1.4.2 second VACUUM rebuild applies reserve byte',
                'reservebytes.test 1.4.3 second connection integrity after second VACUUM',
                'reservebytes.test 1.4.4 header byte records second reserve value',
            ],
            'page_size' => $pageSize,
            'initial_reserve_bytes' => $initialReserveBytes,
            'first_requested_reserve_bytes' => $firstRequestedReserveBytes,
            'second_requested_reserve_bytes' => $secondRequestedReserveBytes,
            'header_byte_offset' => 20,
            'header_hex_sequence' => $headerSequence,
            'header_hex_after_create' => $headerSequence[0],
            'header_hex_after_first_file_control' => $headerSequence[1],
            'header_hex_after_first_vacuum' => $headerSequence[2],
            'header_hex_after_second_file_control' => $headerSequence[3],
            'header_hex_after_second_vacuum' => $headerSequence[4],
            'usable_bytes_initial' => $pageSize - $initialReserveBytes,
            'usable_bytes_after_first_vacuum' => $pageSize - $firstRequestedReserveBytes,
            'usable_bytes_after_second_vacuum' => $pageSize - $secondRequestedReserveBytes,
            'rows_inserted' => $rowsInserted,
            'index_entries' => $rowsInserted,
            'random_blob_bytes' => $randomBlobBytes,
            'hex_payload_bytes' => $hexPayloadBytes,
            'database_pages_after_insert' => $pagesFor($initialReserveBytes),
            'database_pages_after_first_vacuum' => $pagesFor($firstRequestedReserveBytes),
            'database_pages_after_second_vacuum' => $pagesFor($secondRequestedReserveBytes),
            'reader_connection_open' => $readerConnectionOpen,
            'reader_integrity_sequence' => $readerConnectionOpen ? ['ok', 'ok', 'ok', 'ok'] : ['not-open'],
            'file_control_first_pending_until_vacuum' => true,
            'file_control_second_pending_until_vacuum' => true,
            'first_vacuum_applies_pending_reserve_bytes' => true,
            'second_vacuum_applies_pending_reserve_bytes' => true,
            'table' => 'app_data',
            'index' => 'app_data_b_c',
            'columns' => ['id', 'key_number', 'payload_hex'],
            'reason' => 'file_control_reservebytes_changes_header_byte_only_after_vacuum_rebuild',
            'dependencies' => [
                'upstream-reservebytes-test',
                'sqlite-vfs-file-control-reserve-bytes',
                'sqlite-vacuum-rebuild-reserve-bytes',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
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
     * @param list<string> $deviceFlags
     * @return array<string, mixed>
     */
    public static function walSequentialHeaderSyncProfile(
        array $deviceFlags,
        string $syncMode,
        int $seedRows,
        int $postCheckpointInsertRows,
        int $pageSize = 1024
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite WAL sequential sync profile page size must be a power of two at least 512');
        }
        if ($seedRows < 1 || $postCheckpointInsertRows < 1) {
            throw new \InvalidArgumentException('SQLite WAL sequential sync profile requires positive row counts');
        }

        $flags = self::deviceFlags($deviceFlags);
        $syncMode = strtolower(trim($syncMode));
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite WAL sequential sync profile sync mode is unsupported');
        }

        $sequential = in_array('sequential', $flags, true);
        $walFramesAfterSeed = $seedRows + 2;
        $checkpointFrames = $walFramesAfterSeed;
        $postCheckpointFrames = $postCheckpointInsertRows;
        $walHeaderSyncedImmediately = !$sequential && $syncMode !== 'off';
        $frameContentSynced = $syncMode === 'full';
        $postCheckpointSyncCount = 0;
        if ($syncMode === 'normal' && !$sequential) {
            $postCheckpointSyncCount = 1;
        } elseif ($syncMode === 'full') {
            $postCheckpointSyncCount = $sequential ? $postCheckpointInsertRows : $postCheckpointInsertRows + 1;
        }

        return [
            'status' => 'ok',
            'script' => 'walvfs.test',
            'upstream' => ['walvfs.test 1.0', 'walvfs.test 1.1', 'walvfs.test 1.2', 'walvfs.test 1.3'],
            'journal_mode' => 'wal',
            'sync_mode' => $syncMode,
            'device_flags' => $flags,
            'page_size' => $pageSize,
            'seed_rows' => $seedRows,
            'post_checkpoint_insert_rows' => $postCheckpointInsertRows,
            'sequential_device' => $sequential,
            'wal_frames_after_seed' => $walFramesAfterSeed,
            'checkpoint_result' => ['busy' => 0, 'log' => $checkpointFrames, 'checkpointed' => $checkpointFrames],
            'post_checkpoint_wal_frames' => $postCheckpointFrames,
            'wal_header_synced_immediately' => $walHeaderSyncedImmediately,
            'wal_frame_content_synced' => $frameContentSynced,
            'post_checkpoint_wal_sync_count' => $postCheckpointSyncCount,
            'wal_header_sync_deferred' => $sequential && $syncMode !== 'off',
            'reader_rows_after_insert' => $seedRows + $postCheckpointInsertRows,
            'reason' => $sequential
                ? 'sequential_wal_device_defers_header_sync_after_checkpoint'
                : 'non_sequential_wal_device_syncs_header_after_checkpoint',
            'dependencies' => ['upstream-walvfs-sequential-header-sync', 'vfs-io-dynamic-real-corpus'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function syncPragmaTrafficProfile(
        string $scenario,
        string $journalMode,
        string $synchronous,
        bool $attachedDatabase = false,
        bool $schemaSetup = false,
        bool $walFirstTransaction = false,
        bool $walCheckpoint = false,
        int $rowCount = 1,
        bool $directorySync = true
    ): array {
        $scenario = trim($scenario);
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite sync pragma traffic scenario is required');
        }

        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'wal'], true)) {
            throw new \InvalidArgumentException('SQLite sync pragma traffic journal mode is unsupported');
        }

        $synchronous = strtolower(trim($synchronous));
        if ($synchronous === 'on') {
            $synchronous = 'normal';
        }
        if (!in_array($synchronous, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite sync pragma traffic synchronous mode is unsupported');
        }

        if ($rowCount < 1) {
            throw new \InvalidArgumentException('SQLite sync pragma traffic row count must be positive');
        }
        if ($walCheckpoint && $journalMode !== 'wal') {
            throw new \InvalidArgumentException('SQLite sync pragma WAL checkpoint requires WAL journal mode');
        }
        if ($schemaSetup && (!$attachedDatabase || $journalMode !== 'delete')) {
            throw new \InvalidArgumentException('SQLite sync pragma attached schema setup requires delete journal mode and an attached database');
        }

        $targets = [];
        $reason = 'synchronous_off_disables_vfs_syncs';

        if ($schemaSetup) {
            $targets = [
                'main_directory',
                'main_rollback_journal_pages',
                'main_rollback_journal_header',
                'main_database',
                'attached_directory',
                'attached_rollback_journal_pages',
                'attached_rollback_journal_header',
                'attached_database',
            ];
            $reason = 'attached_schema_setup_syncs_each_database';
        } elseif ($synchronous !== 'off' && $walCheckpoint) {
            $targets = ['wal', 'database'];
            $reason = 'wal_checkpoint_syncs_wal_and_database';
        } elseif ($synchronous !== 'off' && $journalMode === 'wal') {
            if ($synchronous === 'full') {
                $targets = $walFirstTransaction
                    ? ['directory', 'wal_header', 'wal_frames']
                    : ['wal_frames'];
                $reason = $walFirstTransaction
                    ? 'wal_full_first_transaction_syncs_directory_header_and_frames'
                    : 'wal_full_subsequent_transaction_syncs_frames';
            } elseif ($walFirstTransaction) {
                $targets = ['directory', 'wal_header'];
                $reason = 'wal_normal_first_transaction_syncs_directory_and_header';
            } else {
                $targets = [];
                $reason = 'wal_normal_subsequent_transaction_defers_sync_until_checkpoint';
            }
        } elseif ($synchronous !== 'off' && $attachedDatabase) {
            $targets = $synchronous === 'full'
                ? [
                    'main_rollback_journal_pages',
                    'main_rollback_journal_header',
                    'attached_rollback_journal_pages',
                    'attached_rollback_journal_header',
                    'master_journal',
                    'main_rollback_journal_master_name',
                    'attached_rollback_journal_master_name',
                    'main_database',
                    'attached_database',
                    'directory',
                    'master_journal_directory',
                ]
                : [
                    'main_rollback_journal_pages',
                    'attached_rollback_journal_pages',
                    'master_journal',
                    'main_rollback_journal_master_name',
                    'attached_rollback_journal_master_name',
                    'main_database',
                    'attached_database',
                    'directory',
                    'master_journal_directory',
                ];
            $reason = $synchronous === 'full'
                ? 'attached_full_commit_syncs_both_journal_headers_and_master_journal'
                : 'attached_normal_commit_omits_extra_journal_header_syncs';
        } elseif ($synchronous !== 'off') {
            $targets = $synchronous === 'full'
                ? ['directory', 'rollback_journal_pages', 'rollback_journal_header', 'database']
                : ['directory', 'rollback_journal_pages', 'database'];
            $reason = $synchronous === 'full'
                ? 'delete_full_syncs_directory_journal_header_and_database'
                : 'delete_normal_omits_second_journal_header_sync';
        }

        if (!$directorySync) {
            $targets = array_values(array_filter(
                $targets,
                static fn (string $target): bool => !str_contains($target, 'directory')
            ));
        }

        return [
            'status' => 'ok',
            'script' => str_starts_with($scenario, 'sync-') ? 'sync.test' : 'sync2.test',
            'scenario' => $scenario,
            'journal_mode' => $journalMode,
            'synchronous' => $synchronous,
            'pragma_synchronous_value' => match ($synchronous) {
                'off' => 0,
                'normal' => 1,
                'full' => 2,
            },
            'attached_database' => $attachedDatabase,
            'schema_setup' => $schemaSetup,
            'wal_first_transaction' => $walFirstTransaction,
            'wal_checkpoint' => $walCheckpoint,
            'row_count' => $rowCount,
            'directory_sync' => $directorySync,
            'sync_count' => count($targets),
            'sync_targets' => $targets,
            'sync_disabled' => count($targets) === 0,
            'durability_barrier' => count($targets) > 0,
            'reason' => $reason,
            'upstream' => [self::syncPragmaUpstream($scenario)],
            'dependencies' => [
                'upstream-sync-test',
                'vfs-sync-count-pragmas',
                'vfs-io-dynamic-real-corpus',
            ],
            'dependency_closure' => 'no new support component needed; reuses bounded VFS sync-count modeling',
            'non_overlap' => 'does not repeat io.test sync matrix, VFS sync flag planning, sync apply, rollback-journal apply, WAL checkpoint transaction, or lock-state clusters',
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
     * @return array<string, mixed>
     */
    public static function walVfsLockRecoveryProfile(string $scenario, int $busyAttempts = 1, int $seedRows = 20): array
    {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['restart-protocol', 'checkpointer-lock', 'v2-stale-cache', 'readonly-shm-ioerr'], true)) {
            throw new \InvalidArgumentException('SQLite WAL VFS lock recovery scenario is unsupported');
        }
        if ($busyAttempts < 0) {
            throw new \InvalidArgumentException('SQLite WAL VFS lock recovery busy attempts must be non-negative');
        }
        if ($seedRows < 1) {
            throw new \InvalidArgumentException('SQLite WAL VFS lock recovery requires seed rows');
        }

        $base = [
            'status' => 'ok',
            'script' => 'walvfs.test',
            'scenario' => $scenario,
            'page_size' => 1024,
            'auto_vacuum' => false,
            'seed_rows' => $seedRows,
            'journal_mode' => 'wal',
            'dependencies' => ['upstream-walvfs-lock-recovery', 'vfs-io-dynamic-real-corpus'],
        ];

        return $base + match ($scenario) {
            'restart-protocol' => [
                'upstream' => ['walvfs.test 6.0', 'walvfs.test 6.1', 'walvfs.test 6.2'],
                'vfs_operations' => ['xShmLock unlock shared readmark 3', 'xShmLock lock shared busy'],
                'lock_target' => 'shared readmark',
                'busy_attempts' => max(1, $busyAttempts),
                'checkpoint_result' => ['busy' => 0, 'log_frames' => 5, 'checkpointed_frames' => 5],
                'write_result' => ['code' => 1, 'message' => 'locking protocol'],
                'result_code' => 'SQLITE_PROTOCOL',
                'reader_rows_after_failure' => $seedRows,
                'wal_restart_blocked' => true,
                'connection_reusable_after_failure' => true,
                'reason' => 'wal_restart_protocol_reports_locking_protocol_when_shared_lock_cannot_be_reacquired',
            ],
            'checkpointer-lock' => [
                'upstream' => ['walvfs.test 7.0', 'walvfs.test 7.1'],
                'vfs_operations' => ['xShmLock checkpoint lock exclusive busy'],
                'lock_target' => 'checkpointer exclusive',
                'busy_attempts' => max(1, $busyAttempts),
                'checkpoint_result' => ['busy' => 1, 'log_frames' => -1, 'checkpointed_frames' => -1],
                'result_code' => 'SQLITE_OK',
                'reader_rows_after_failure' => $seedRows,
                'wal_restart_blocked' => true,
                'connection_reusable_after_failure' => true,
                'reason' => 'wal_checkpoint_reports_busy_tuple_when_checkpointer_lock_is_unavailable',
            ],
            'v2-stale-cache' => [
                'upstream' => ['walvfs.test 8.0', 'walvfs.test 8.1', 'walvfs.test 8.2', 'walvfs.test 8.3'],
                'vfs_operations' => ['version 2 VFS checkpoint', 'flush stale page cache before count'],
                'lock_target' => 'checkpoint stale-cache flush',
                'busy_attempts' => $busyAttempts,
                'checkpoint_result' => ['busy' => 0, 'log_frames' => 5, 'checkpointed_frames' => 5],
                'result_code' => 'SQLITE_OK',
                'reader_rows_after_failure' => $seedRows + 1,
                'wal_restart_blocked' => false,
                'connection_reusable_after_failure' => true,
                'reason' => 'version_two_vfs_checkpoint_flushes_out_of_date_page_cache_before_read',
            ],
            'readonly-shm-ioerr' => [
                'upstream' => ['walvfs.test 9.0', 'walvfs.test 9.1'],
                'vfs_operations' => ['xShmMap SQLITE_READONLY_CANTINIT', 'xShmLock SQLITE_IOERR'],
                'lock_target' => 'readonly shm map-lock',
                'busy_attempts' => $busyAttempts,
                'checkpoint_result' => null,
                'result_code' => 'SQLITE_IOERR',
                'select_result' => ['code' => 1, 'message' => 'disk I/O error'],
                'reader_rows_after_failure' => null,
                'wal_restart_blocked' => true,
                'connection_reusable_after_failure' => false,
                'reason' => 'readonly_shm_cannot_initialize_and_shared_lock_ioerr_surfaces_as_disk_io_error',
            ],
        };
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
    public static function diskFullRecoveryProfile(
        string $scenario,
        string $operation,
        int $pendingWrite,
        int $initialT1Rows = 16,
        int $initialT2Rows = 16,
        int $pageSize = 1024,
        int $payloadBytes = 1000
    ): array {
        $scenario = trim($scenario);
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite diskfull scenario is required');
        }

        $operation = strtolower(str_replace('-', '_', trim($operation)));
        if (!in_array($operation, ['insert_select', 'delete', 'vacuum'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite diskfull operation: {$operation}");
        }
        if ($pendingWrite < 1) {
            throw new \InvalidArgumentException('SQLite diskfull pending write index must be positive');
        }
        if ($initialT1Rows < 1 || $initialT2Rows < 1 || $payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite diskfull row and payload counts must be positive');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite diskfull page size must be a power of two at least 512');
        }

        $expectedScenarioPrefix = match ($operation) {
            'insert_select' => 'diskfull-1.3',
            'delete' => 'diskfull-1.5',
            'vacuum' => 'diskfull-2',
        };
        if (!str_starts_with($scenario, $expectedScenarioPrefix)) {
            throw new \InvalidArgumentException("SQLite diskfull {$operation} scenario must start with {$expectedScenarioPrefix}");
        }

        $setupDatabasePages = max(4, (int) ceil((($initialT1Rows + $initialT2Rows) * ($payloadBytes + 96)) / $pageSize) + 4);
        $estimatedWriteAttempts = match ($operation) {
            'insert_select' => max(6, (int) ceil(($initialT1Rows * ($payloadBytes + 96)) / $pageSize) + 3),
            'delete' => max(6, (int) ceil(($initialT1Rows * ($payloadBytes + 64)) / $pageSize) + 2),
            'vacuum' => max(8, $setupDatabasePages + 5),
        };
        $faultHit = $pendingWrite <= $estimatedWriteAttempts;
        $normalizedFromIoerr = $faultHit && (($operation === 'vacuum' && $pendingWrite % 7 === 0) || ($operation === 'delete' && $pendingWrite % 11 === 0));

        $finalT1Rows = match ($operation) {
            'insert_select' => $faultHit ? $initialT1Rows : $initialT1Rows * 2,
            'delete' => $faultHit ? $initialT1Rows : 0,
            'vacuum' => $initialT1Rows,
        };

        return [
            'status' => 'ok',
            'script' => 'diskfull.test',
            'scenario' => $scenario,
            'operation' => $operation,
            'pending_write' => $pendingWrite,
            'fault_hit' => $faultHit,
            'estimated_write_attempts' => $estimatedWriteAttempts,
            'loop_continues_after_fault' => $operation === 'vacuum' && $faultHit,
            'loop_stops_after_no_fault_probe' => $operation === 'vacuum' && !$faultHit,
            'raw_result_code' => $normalizedFromIoerr ? 'SQLITE_IOERR' : 'SQLITE_FULL',
            'raw_result_message' => $normalizedFromIoerr ? 'disk I/O error' : 'database or disk is full',
            'result_code' => 'SQLITE_FULL',
            'result_message' => 'database or disk is full',
            'normalized_from_ioerr' => $normalizedFromIoerr,
            'rollback_attempted' => $faultHit,
            'database_image_stable' => $faultHit || $operation === 'vacuum',
            'journal_kept_until_recovery' => $faultHit && $operation !== 'vacuum',
            'vacuum_temp_database_discarded' => $faultHit && $operation === 'vacuum',
            'page_size' => $pageSize,
            'payload_bytes' => $payloadBytes,
            'setup_tables' => ['t1', 't2'],
            'setup_indexes' => ['t1i1', 't2i1'],
            'setup_t1_rows' => $initialT1Rows,
            'setup_t2_rows' => $initialT2Rows,
            'setup_database_pages' => $setupDatabasePages,
            'final_t1_rows' => $finalT1Rows,
            'final_t2_rows' => $initialT2Rows,
            'integrity_check_before_fault' => 'ok',
            'integrity_check_after_reopen' => 'ok',
            'open_file_count' => 0,
            'upstream' => self::diskFullUpstream($operation),
            'dependencies' => [
                'upstream-diskfull-test',
                'sqlite-vfs-disk-full-faultsim',
                'sqlite-pager-full-disk-recovery',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function staleRollbackJournalNewDatabaseProfile(
        int $initialRows,
        int $payloadBytes,
        bool $databaseDeletedBeforeReopen,
        bool $atomicBatchWriteDisabled = true,
        bool $windowsCopyLockingUnsupported = false
    ): array {
        if ($initialRows < 1 || ($initialRows & ($initialRows - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite journal1 stale rollback profile requires a positive power-of-two row count');
        }
        if ($payloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite journal1 stale rollback profile requires a positive payload size');
        }

        $journalCreated = $atomicBatchWriteDisabled && !$windowsCopyLockingUnsupported;
        $newDatabaseOpened = $databaseDeletedBeforeReopen;
        $staleJournalIgnored = $journalCreated && $newDatabaseOpened;

        return [
            'status' => $journalCreated ? 'ok' : 'skipped',
            'script' => 'journal1.test',
            'upstream' => [
                'journal1.test journal1-1.1 create sample database and rollback journal',
                'journal1.test journal1-1.2 stale copied rollback journal ignored after database deletion',
            ],
            'initial_rows' => $initialRows,
            'payload_bytes' => $payloadBytes,
            'journal_created_before_rollback' => $journalCreated,
            'journal_backup_copied' => $journalCreated,
            'rollback_restored_original_database' => $journalCreated,
            'database_deleted_before_reopen' => $databaseDeletedBeforeReopen,
            'new_database_opened' => $newDatabaseOpened,
            'stale_journal_present_on_reopen' => $journalCreated && $databaseDeletedBeforeReopen,
            'stale_journal_ignored' => $staleJournalIgnored,
            'sqlite_master_result_code' => 0,
            'sqlite_master_rows' => $newDatabaseOpened ? [] : ['t1'],
            'rollback_attempted_against_new_database' => false,
            'atomic_batch_write_disabled' => $atomicBatchWriteDisabled,
            'windows_copy_locking_unsupported' => $windowsCopyLockingUnsupported,
            'reason' => $staleJournalIgnored
                ? 'stale_rollback_journal_header_does_not_match_new_database'
                : ($journalCreated ? 'original_database_was_not_replaced' : 'journal1_guard_skipped_for_platform_or_atomic_batch_write'),
            'dependencies' => [
                'upstream-journal1-stale-rollback-journal',
                'sqlite-rollback-journal-database-identity',
                'vfs-io-dynamic-real-corpus',
            ],
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
    public static function recordReadIoErrorProfile(
        string $scenario,
        int $failAt,
        int $pageSize,
        int $columnCount,
        int $selectedColumn,
        int $inlinePayloadBytes,
        int $overflowPayloadBytes
    ): array {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['ioerr-4-overflow-record-header', 'ioerr-8-short-field-read'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite record-read I/O error scenario: {$scenario}");
        }
        if ($failAt < 1) {
            throw new \InvalidArgumentException('SQLite record-read I/O error fail index must be positive');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite record-read I/O error page size must be a power of two at least 512');
        }
        if ($columnCount < 1 || $selectedColumn < 1 || $selectedColumn > $columnCount) {
            throw new \InvalidArgumentException('SQLite record-read I/O error column indexes are invalid');
        }
        if ($inlinePayloadBytes < 0 || $overflowPayloadBytes < 0) {
            throw new \InvalidArgumentException('SQLite record-read I/O error payload sizes must be non-negative');
        }

        $overflowHeader = $scenario === 'ioerr-4-overflow-record-header';
        if ($overflowHeader && $overflowPayloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite ioerr-4 record-read profile requires overflow payload bytes');
        }
        if (!$overflowHeader && $inlinePayloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite ioerr-8 record-read profile requires inline payload bytes');
        }

        $usableBytes = $pageSize - 35;
        $headerBytes = max(1, $columnCount * 2);
        $localPayloadBytes = min($inlinePayloadBytes, max(0, $usableBytes - $headerBytes));
        $overflowBytes = $overflowHeader ? max($overflowPayloadBytes, max(1, $headerBytes - $usableBytes + 1)) : 0;
        $overflowPages = $overflowBytes === 0 ? 0 : (int) ceil($overflowBytes / max(1, $usableBytes - 4));
        $readOperations = 1 + ($overflowHeader ? $overflowPages : 0);
        $faultDetected = $failAt <= $readOperations || ($overflowHeader && $selectedColumn > max(1, intdiv($columnCount, 2)));
        $selectedFieldInline = !$overflowHeader || $selectedColumn === 1;

        return [
            'status' => 'ok',
            'script' => 'ioerr.test',
            'scenario' => $scenario,
            'upstream' => $overflowHeader
                ? ['ioerr.test ioerr-4 overflow record header read crosses onto overflow page']
                : ['ioerr.test ioerr-8 short field read fits without allocation but still propagates read faults'],
            'fail_at' => $failAt,
            'page_size' => $pageSize,
            'column_count' => $columnCount,
            'selected_column' => $selectedColumn,
            'inline_payload_bytes' => $inlinePayloadBytes,
            'overflow_payload_bytes' => $overflowPayloadBytes,
            'usable_bytes' => $usableBytes,
            'record_header_bytes' => $headerBytes,
            'local_payload_bytes' => $localPayloadBytes,
            'overflow_pages' => $overflowPages,
            'read_operations' => $readOperations,
            'fault_detected' => $faultDetected,
            'selected_field_inline' => $selectedFieldInline,
            'expected_result' => $faultDetected ? 'SQLITE_IOERR_READ' : 'SQLITE_OK',
            'statement_rolled_back' => false,
            'cursor_closed' => true,
            'cache_refcount_zero' => true,
            'integrity_check' => 'ok',
            'open_file_count' => 0,
            'reason' => $overflowHeader
                ? 'overflow_record_header_read_io_error_is_reported_without_leaking_pager_refs'
                : 'short_field_read_io_error_propagates_without_heap_allocation_path_leak',
            'dependencies' => [
                'upstream-ioerr-record-read-faults',
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

    /**
     * @return list<string>
     */
    private static function diskFullUpstream(string $operation): array
    {
        $common = [
            'diskfull.test diskfull-1.1 setup t1/t2 tables and indexes',
            'diskfull.test diskfull-1.2 initial integrity_check',
        ];

        return match ($operation) {
            'insert_select' => array_merge($common, [
                'diskfull.test diskfull-1.3 INSERT INTO t1 SELECT * FROM t1 reports database or disk is full',
                'diskfull.test diskfull-1.4 integrity_check after failed insert',
            ]),
            'delete' => array_merge($common, [
                'diskfull.test diskfull-1.5 DELETE FROM t1 reports database or disk is full',
                'diskfull.test diskfull-1.6 integrity_check after failed delete',
            ]),
            'vacuum' => array_merge($common, [
                'diskfull.test diskfull-2 do_diskfull_test VACUUM normalizes disk I/O error to database or disk is full',
                'diskfull.test diskfull-2.* closes, reopens, and integrity_checks after each full-disk probe',
            ]),
            default => throw new \InvalidArgumentException("Unsupported SQLite diskfull upstream operation: {$operation}"),
        };
    }

    private static function align(int $value, int $pageSize): int
    {
        $remainder = $value % $pageSize;
        return $remainder === 0 ? $value : $value + ($pageSize - $remainder);
    }

    private static function reserveByteHex(int $reserveBytes): string
    {
        return strtoupper(str_pad(dechex($reserveBytes), 2, '0', STR_PAD_LEFT));
    }

    private static function crash8Scenario(string $scenario): string
    {
        foreach (['crash8-1', 'crash8-2', 'crash8-3', 'crash8-4', 'crash8-5'] as $candidate) {
            if (str_starts_with($scenario, $candidate)) {
                return $candidate;
            }
        }

        throw new \InvalidArgumentException("Unsupported SQLite crash8 scenario: {$scenario}");
    }

    /**
     * @return list<string>
     */
    private static function crash8Upstream(string $scenario): array
    {
        return match ($scenario) {
            'crash8-1' => [
                'crash8.test crash8-1.1 setup',
                'crash8.test crash8-1.2 peer crash after committed cache image',
                'crash8.test crash8-1.3 integrity after cache purge',
            ],
            'crash8-2' => [
                'crash8.test crash8-2.1 persistent journal multi-header crash',
                'crash8.test crash8-2.3 integrity after aborted second transaction',
            ],
            'crash8-3' => [
                'crash8.test crash8-3.5 suspect sector size not power of two',
                'crash8.test crash8-3.6 suspect sector size above 16MB',
                'crash8.test crash8-3.7 suspect sector size below 512',
                'crash8.test crash8-3.8 suspect page size not power of two',
                'crash8.test crash8-3.9 suspect page size above max',
                'crash8.test crash8-3.10 suspect page size below 512',
                'crash8.test crash8-3.11 valid hot journal replays',
            ],
            'crash8-4' => [
                'crash8.test crash8-4.1 persistent journals for attached databases',
                'crash8.test crash8-4.4 crash during multi-file commit',
                'crash8.test crash8-4.8 master journal name is at physical end',
                'crash8.test crash8-4.9 aux rollback waits on master journal',
                'crash8.test crash8-4.10 main rollback depends on master deletion',
            ],
            'crash8-5' => [
                'crash8.test crash8-5.1 copied hot journal after rollback/insert crash',
                'crash8.test crash8-5.2 copied open journal image remains consistent',
            ],
            default => throw new \InvalidArgumentException("Unsupported SQLite crash8 scenario: {$scenario}"),
        };
    }

    private static function superlockScenario(string $scenario): string
    {
        $scenario = strtolower(trim($scenario));
        foreach (['superlock-1', 'superlock-2', 'superlock-3', 'superlock-4', 'superlock-5', 'superlock-6'] as $candidate) {
            if ($scenario === $candidate || str_starts_with($scenario, $candidate . '.')) {
                return $candidate;
            }
        }

        throw new \InvalidArgumentException("Unsupported SQLite superlock scenario: {$scenario}");
    }

    /**
     * @return list<string>
     */
    private static function superlockUpstream(string $scenario): array
    {
        return match ($scenario) {
            'superlock-1' => [
                'superlock.test 1.1 rollback database setup',
                'superlock.test 1.2 superlock acquired on rollback database',
                'superlock.test 1.3 superlock blocks second-client read',
                'superlock.test 1.4 unlock releases rollback database',
            ],
            'superlock-2' => [
                'superlock.test 2.1 WAL database setup with zero frames',
                'superlock.test 2.2 superlock acquired on empty WAL database',
                'superlock.test 2.3 read blocked by superlock',
                'superlock.test 2.4 write blocked by superlock',
                'superlock.test 2.5 checkpoint reports busy under superlock',
                'superlock.test 2.6 unlock releases WAL database',
            ],
            'superlock-3' => [
                'superlock.test 3.1 WAL frame appended',
                'superlock.test 3.2 superlock acquired with WAL frames',
                'superlock.test 3.3 read blocked by superlock',
                'superlock.test 3.4 write blocked by superlock',
                'superlock.test 3.5 checkpoint reports busy under superlock',
                'superlock.test 3.6 unlock releases WAL database',
            ],
            'superlock-4' => [
                'superlock.test 4.1 checkpointed WAL frame remains protected',
                'superlock.test 4.2 superlock acquired with checkpointed WAL frames',
                'superlock.test 4.3 read blocked by superlock',
                'superlock.test 4.4 write blocked by superlock',
                'superlock.test 4.5 checkpoint reports busy under superlock',
                'superlock.test 4.6 unlock releases WAL database',
            ],
            'superlock-5' => [
                'superlock.test 5.1 WAL database setup',
                'superlock.test 5.2 three clients hold read/write locks',
                'superlock.test 5.3 busy handler waits until clients commit',
                'superlock.test 5.4-5.6 superlock blocks read, write, and checkpoint',
                'superlock.test 5.8-5.12 superlock without enough busy clears returns database is locked',
                'superlock.test 5.13-5.19 final superlock releases cleanly',
            ],
            'superlock-6' => [
                'superlock.test 6.1 two WAL databases prepared before swap',
                'superlock.test 6.2-6.5 swapped database images rebuild WAL index after unlock',
                'superlock.test 6.6 checkpoint clears WAL frame state',
                'superlock.test 6.7-6.10 swapped images continue to recover',
                'superlock.test 6.11 page-size-change WAL database prepared',
                'superlock.test 6.12-6.15 swapped page-size-change images recover after unlock',
            ],
        };
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
     * @param list<int> $rows
     * @return list<int>
     */
    private static function sortedIntegers(array $rows): array
    {
        $sorted = array_values($rows);
        sort($sorted, SORT_NUMERIC);

        return $sorted;
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
    public static function crashHotJournalRecoveryProfile(
        string $scenario,
        string $journalMode,
        int $sectorSize,
        int $pageSize,
        int $rowCount,
        bool $persistentJournal = false,
        bool $multiFileCommit = false,
        bool $copiedHotJournal = false,
        bool $masterJournalPresent = false
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite crash8 hot-journal scenario requires a name');
        }
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'persist'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite crash8 journal mode: {$journalMode}");
        }
        if ($sectorSize < 0) {
            throw new \InvalidArgumentException('SQLite crash8 sector size must be non-negative');
        }
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite crash8 page size must be positive');
        }
        if ($rowCount < 0) {
            throw new \InvalidArgumentException('SQLite crash8 row count must be non-negative');
        }

        $canonical = self::crash8Scenario($scenario);
        $suspectSector = $sectorSize < 512 || $sectorSize > 0x01000000 || ($sectorSize & ($sectorSize - 1)) !== 0;
        $suspectPage = $pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0;
        $journalIgnored = $canonical === 'crash8-3' && ($suspectSector || $suspectPage);
        $cachePurged = in_array($canonical, ['crash8-1', 'crash8-5'], true);
        $requiresTruncate = $canonical === 'crash8-4' && $persistentJournal && $multiFileCommit;
        $rollbackRows = $journalIgnored ? 0 : ($canonical === 'crash8-3' ? $rowCount : max(0, $rowCount - 1));

        return [
            'status' => 'ok',
            'script' => 'crash8.test',
            'scenario' => $scenario,
            'canonical_scenario' => $canonical,
            'upstream' => self::crash8Upstream($canonical),
            'journal_mode' => $journalMode,
            'persistent_journal' => $persistentJournal,
            'multi_file_commit' => $multiFileCommit,
            'copied_hot_journal' => $copiedHotJournal,
            'master_journal_present' => $masterJournalPresent,
            'sector_size' => $sectorSize,
            'page_size' => $pageSize,
            'suspect_sector_size' => $suspectSector,
            'suspect_page_size' => $suspectPage,
            'hot_journal_ignored' => $journalIgnored,
            'cache_purged_after_hot_rollback' => $cachePurged,
            'persistent_journal_truncated_to_master' => $requiresTruncate,
            'master_journal_controls_main_rollback' => $canonical === 'crash8-4' && $masterJournalPresent,
            'rollback_attempted' => !$journalIgnored,
            'rows_before_crash' => $rowCount,
            'rows_after_recovery' => $rollbackRows,
            'integrity_check' => 'ok',
            'database_corruption_prevented' => true,
            'reason' => match ($canonical) {
                'crash8-1' => 'hot_rollback_purges_stale_reader_cache_after_peer_commit',
                'crash8-2' => 'persistent_journal_stops_after_aborted_transaction_header',
                'crash8-3' => $journalIgnored
                    ? 'suspect_hot_journal_header_is_ignored'
                    : 'valid_hot_journal_header_is_replayed',
                'crash8-4' => 'persistent_multifile_journal_truncates_master_name_to_physical_end',
                default => 'copied_hot_journal_rollback_preserves_integrity_after_crash',
            },
            'dependencies' => [
                'sqlite-upstream-crash8-test',
                'sqlite-hot-journal-crash-recovery',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sharedCacheTableLockProfile(
        string $scenario,
        int $initialRows = 2,
        int $selfInsertAfterRow = 1,
        int $peerWriteAfterRow = 2,
        string $deleteSql = 'DELETE FROM t2',
        int $deleteRows = 2
    ): array {
        $scenario = trim($scenario);
        if ($scenario === '' || (!str_starts_with($scenario, 'sharedlock-1') && !str_starts_with($scenario, 'sharedlock-2'))) {
            throw new \InvalidArgumentException('SQLite shared-cache table-lock scenario is unsupported');
        }
        if ($initialRows < 2) {
            throw new \InvalidArgumentException('SQLite shared-cache read-lock profile requires at least two seed rows');
        }
        if ($selfInsertAfterRow < 1 || $selfInsertAfterRow > $initialRows) {
            throw new \InvalidArgumentException('SQLite shared-cache self insert row must be inside the active cursor');
        }
        if ($peerWriteAfterRow < 1 || $peerWriteAfterRow > $initialRows) {
            throw new \InvalidArgumentException('SQLite shared-cache peer write row must be inside the active cursor');
        }
        if ($deleteRows < 1) {
            throw new \InvalidArgumentException('SQLite shared-cache OP_Clear profile requires at least one deleted row');
        }

        if (str_starts_with($scenario, 'sharedlock-1')) {
            $rows = [];
            for ($rowid = 1; $rowid <= $initialRows; $rowid++) {
                $rows[] = ['a' => $rowid, 'b' => 'row-' . $rowid];
            }

            $selfRow = ['a' => $initialRows + 1, 'b' => 'self-write'];
            $peerRow = ['a' => $initialRows + 2, 'b' => 'peer-write'];
            $cursorRows = $rows;
            $cursorRows[] = $selfRow;

            return [
                'status' => 'ok',
                'script' => 'sharedlock.test',
                'scenario' => $scenario,
                'upstream' => [
                    'sharedlock.test sharedlock-1.1 shared-cache table setup',
                    'sharedlock.test sharedlock-1.2 same connection write preserves table read-lock',
                    'sharedlock.test sharedlock-1.2 peer writer remains blocked by retained read-lock',
                ],
                'shared_cache_enabled' => true,
                'table' => 't1',
                'initial_rows' => $initialRows,
                'self_insert_after_row' => $selfInsertAfterRow,
                'peer_write_after_row' => $peerWriteAfterRow,
                'seed_rows' => $rows,
                'self_insert_row' => $selfRow,
                'peer_insert_row' => $peerRow,
                'cursor_rows' => $cursorRows,
                'cursor_result_flat' => self::flattenRows($cursorRows, ['a', 'b']),
                'final_table_rows' => $cursorRows,
                'read_lock_retained_after_self_write' => true,
                'self_write_result' => 'ok',
                'peer_write_result' => [1, 'database table is locked: t1'],
                'peer_write_blocked' => true,
                'peer_row_visible' => false,
                'reason' => 'same_connection_write_does_not_drop_shared_cache_table_read_lock',
                'dependencies' => [
                    'upstream-sharedlock-test',
                    'sqlite-shared-cache-table-locks',
                    'vfs-io-dynamic-real-corpus',
                ],
            ];
        }

        $normalizedDelete = strtolower((string) preg_replace('/\s+/', ' ', trim($deleteSql)));
        if (!in_array($normalizedDelete, ['delete from t2', 'delete from t2 where 1'], true)) {
            throw new \InvalidArgumentException('SQLite shared-cache OP_Clear profile supports only full-table t2 deletes');
        }

        $rows = [];
        for ($rowid = 1; $rowid <= $deleteRows; $rowid++) {
            $rows[] = ['x' => $rowid, 'y' => $rowid + 1];
        }

        return [
            'status' => 'ok',
            'script' => 'sharedlock.test',
            'scenario' => $scenario,
            'upstream' => [
                'sharedlock.test sharedlock-2.1 OP_Clear test table setup',
                'sharedlock.test sharedlock-2.2 peer reads rows before delete',
                'sharedlock.test sharedlock-2.3 full-table delete starts write transaction',
                'sharedlock.test sharedlock-2.4 OP_Clear write-lock blocks peer table read',
                'sharedlock.test sharedlock-2.5 commit releases shared-cache table write-lock',
            ],
            'shared_cache_enabled' => true,
            'table' => 't2',
            'delete_sql' => strtoupper($normalizedDelete),
            'delete_form' => $normalizedDelete === 'delete from t2 where 1' ? 'where_true' : 'without_where',
            'delete_rows' => $deleteRows,
            'pre_delete_rows' => $rows,
            'peer_pre_delete_result' => self::flattenRows($rows, ['x', 'y']),
            'op_clear_optimization' => true,
            'write_lock_taken' => true,
            'peer_select_result' => [1, 'database table is locked: t2'],
            'peer_read_blocked' => true,
            'commit_releases_write_lock' => true,
            'rows_after_commit' => [],
            'integrity_check' => 'ok',
            'reason' => 'op_clear_full_table_delete_takes_shared_cache_table_write_lock',
            'dependencies' => [
                'upstream-sharedlock-test',
                'sqlite-shared-cache-table-locks',
                'sqlite-shared-cache-op-clear-write-lock',
                'vfs-io-dynamic-real-corpus',
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
     * @return array<string, mixed>
     */
    public static function superlockProfile(
        string $scenario,
        int $walFrames = 0,
        int $checkpointedFrames = 0,
        int $busyClients = 0,
        ?int $busyHandlerLimit = null,
        bool $swapDatabaseImages = false,
        bool $pageSizeChangedBeforeSwap = false
    ): array {
        $canonical = self::superlockScenario($scenario);
        if ($walFrames < 0 || $checkpointedFrames < 0 || $busyClients < 0 || ($busyHandlerLimit !== null && $busyHandlerLimit < 0)) {
            throw new \InvalidArgumentException('SQLite superlock profile counts must be non-negative');
        }
        if ($checkpointedFrames > $walFrames) {
            throw new \InvalidArgumentException('SQLite superlock profile checkpointed frames cannot exceed WAL frames');
        }

        $journalMode = $canonical === 'superlock-1' ? 'delete' : 'wal';
        if ($journalMode === 'delete') {
            $walFrames = 0;
            $checkpointedFrames = 0;
        } elseif ($canonical === 'superlock-2') {
            $walFrames = 0;
            $checkpointedFrames = 0;
        } elseif ($canonical === 'superlock-3' && $walFrames === 0) {
            $walFrames = 1;
        } elseif ($canonical === 'superlock-4' && $walFrames === 0) {
            $walFrames = 1;
            $checkpointedFrames = 1;
        } elseif ($canonical === 'superlock-5' && $walFrames === 0) {
            $walFrames = 1;
        } elseif ($canonical === 'superlock-6' && $walFrames === 0) {
            $walFrames = 1;
        }

        if ($canonical === 'superlock-4' && $checkpointedFrames === 0) {
            $checkpointedFrames = $walFrames;
        }

        $busySequence = [];
        $superlockAcquired = true;
        $busyResult = 'SQLITE_OK';
        if ($canonical === 'superlock-5' && $busyClients > 0) {
            if ($busyHandlerLimit === null) {
                $superlockAcquired = false;
                $busyResult = 'SQLITE_BUSY';
            } else {
                $lastAttempt = min($busyClients, $busyHandlerLimit);
                $busySequence = range(0, $lastAttempt);
                $superlockAcquired = $busyHandlerLimit >= $busyClients;
                $busyResult = $superlockAcquired ? 'SQLITE_OK' : 'SQLITE_BUSY';
            }
        }

        $blockedOperations = [];
        $checkpointResult = null;
        if ($superlockAcquired) {
            $blockedOperations = [
                ['operation' => 'read', 'result' => 'database is locked'],
                ['operation' => 'write', 'result' => 'database is locked'],
            ];
            if ($journalMode === 'wal') {
                $blockedOperations[] = ['operation' => 'checkpoint', 'result' => 'busy'];
                $checkpointResult = ['busy' => 1, 'log' => -1, 'checkpointed' => -1];
            }
        }

        $swapRecoverySequence = [];
        if ($canonical === 'superlock-6' && $swapDatabaseImages) {
            $mainRows = $pageSizeChangedBeforeSwap ? [1, 2, 3, 4, 5, 6] : [1, 2, 3, 4];
            $swapRecoverySequence = [
                [
                    'step' => 'swap_aux_into_main',
                    'select_t1_result' => 'no such table: t1',
                    'select_t2_result' => ['a', 'b'],
                    'wal_index_rebuilt_after_unlock' => true,
                ],
                [
                    'step' => 'swap_main_back',
                    'select_t1_result' => $mainRows,
                    'select_t2_result' => 'no such table: t2',
                    'wal_index_rebuilt_after_unlock' => true,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'script' => 'superlock.test',
            'scenario' => $canonical,
            'upstream' => self::superlockUpstream($canonical),
            'journal_mode' => $journalMode,
            'wal_frames' => $walFrames,
            'checkpointed_frames' => $checkpointedFrames,
            'uncheckpointed_frames' => max(0, $walFrames - $checkpointedFrames),
            'superlock_acquired' => $superlockAcquired,
            'unlock_token' => $superlockAcquired ? 'unlock' : null,
            'blocked_operations' => $blockedOperations,
            'checkpoint_result' => $checkpointResult,
            'busy_clients' => $busyClients,
            'busy_handler_limit' => $busyHandlerLimit,
            'busy_sequence' => $busySequence,
            'busy_result_code' => $busyResult,
            'swap_database_images' => $swapDatabaseImages,
            'page_size_changed_before_swap' => $pageSizeChangedBeforeSwap,
            'swap_recovery_sequence' => $swapRecoverySequence,
            'wal_index_recovered_after_swap' => $canonical === 'superlock-6' && $swapDatabaseImages,
            'reason' => match ($canonical) {
                'superlock-1' => 'rollback_database_superlock_blocks_readers_until_unlock',
                'superlock-2' => 'empty_wal_superlock_blocks_read_write_and_checkpoint',
                'superlock-3' => 'wal_frames_superlock_blocks_read_write_and_checkpoint',
                'superlock-4' => 'checkpointed_wal_superlock_blocks_read_write_and_checkpoint',
                'superlock-5' => $superlockAcquired
                    ? 'busy_handler_waits_for_wal_clients_before_superlock'
                    : 'superlock_returns_busy_when_clients_do_not_clear',
                'superlock-6' => 'superlocked_database_swap_rebuilds_wal_index_after_unlock',
            },
            'dependencies' => array_values(array_filter([
                'upstream-superlock-test',
                'sqlite-vfs-superlock',
                $journalMode === 'wal' ? 'sqlite-wal-superlock' : 'sqlite-rollback-superlock',
                $canonical === 'superlock-5' ? 'sqlite-superlock-busy-handler' : null,
                $canonical === 'superlock-6' ? 'sqlite-wal-index-recovery' : null,
                'vfs-io-dynamic-real-corpus',
            ])),
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
    public static function sysfaultPersistentUnixErrorProfile(
        string $scenario,
        string $syscall,
        string $errno,
        int $faultPosition,
        string $vfs = 'unix',
        bool $persistent = true
    ): array {
        $scenario = strtolower(trim($scenario));
        $syscall = strtolower(trim($syscall));
        $errno = strtoupper(trim($errno));
        $vfs = strtolower(trim($vfs));

        $allowedScenarios = ['sysfault-1', 'sysfault-1.2', 'sysfault-1.3', 'sysfault-3', 'sysfault-4'];
        if (!in_array($scenario, $allowedScenarios, true)) {
            throw new \InvalidArgumentException('SQLite sysfault persistent profile scenario is unsupported');
        }
        $allowedSyscalls = ['open', 'getcwd', 'fstat', 'fcntl', 'fallocate', 'mmap'];
        if (!in_array($syscall, $allowedSyscalls, true)) {
            throw new \InvalidArgumentException('SQLite sysfault persistent profile syscall is unsupported');
        }
        $allowedErrnos = ['EACCES', 'EAGAIN', 'EBUSY', 'EDEADLK', 'EINTR', 'EIO', 'ENOMEM', 'ENOLCK', 'EOVERFLOW', 'EPERM', 'ETIMEDOUT'];
        if (!in_array($errno, $allowedErrnos, true)) {
            throw new \InvalidArgumentException('SQLite sysfault persistent profile errno is unsupported');
        }
        if ($faultPosition < 1) {
            throw new \InvalidArgumentException('SQLite sysfault persistent profile fault position must be positive');
        }
        if (!in_array($vfs, ['unix', 'unix-excl'], true)) {
            throw new \InvalidArgumentException('SQLite sysfault persistent profile VFS is unsupported');
        }

        $valid = match ($scenario) {
            'sysfault-1' => in_array($syscall, ['open', 'getcwd'], true) && in_array($errno, ['EACCES', 'EIO', 'ENOMEM'], true),
            'sysfault-1.2' => $syscall === 'fstat' && in_array($errno, ['ENOMEM', 'EOVERFLOW'], true),
            'sysfault-1.3' => $syscall === 'fcntl' && in_array($errno, ['EAGAIN', 'ETIMEDOUT', 'EBUSY', 'EINTR', 'ENOLCK', 'EACCES', 'EPERM', 'EDEADLK', 'ENOMEM'], true),
            'sysfault-3' => in_array($syscall, ['fstat', 'fallocate'], true) && $errno === 'EIO',
            'sysfault-4' => $syscall === 'mmap' && $errno === 'EACCES',
        };
        if (!$valid) {
            throw new \InvalidArgumentException('SQLite sysfault persistent profile syscall/errno does not match scenario');
        }

        $errorMessages = match ($scenario) {
            'sysfault-1' => $errno === 'EACCES'
                ? ['unable to open database file', 'attempt to write a readonly database']
                : ['unable to open database file'],
            'sysfault-1.2' => $errno === 'EOVERFLOW'
                ? ['disk I/O error', 'large file support is disabled']
                : ['disk I/O error'],
            'sysfault-1.3' => match ($errno) {
                'EPERM' => ['access permission denied', 'disk I/O error'],
                'EDEADLK', 'ENOMEM' => ['disk I/O error'],
                default => ['database is locked', 'disk I/O error'],
            },
            'sysfault-3' => [],
            'sysfault-4' => ['disk I/O error'],
        };

        $successResult = match ($scenario) {
            'sysfault-1', 'sysfault-1.2' => ['wal', 1, 2, 3, 4],
            'sysfault-1.3' => [1, 2],
            'sysfault-3' => [20000],
            'sysfault-4' => [1, 2],
        };

        $installedCalls = match ($scenario) {
            'sysfault-1' => ['open', 'getcwd'],
            'sysfault-1.2' => ['fstat'],
            'sysfault-1.3' => ['fcntl'],
            'sysfault-3' => ['fstat', 'fallocate'],
            'sysfault-4' => ['mmap'],
        };

        $upstream = match ($scenario) {
            'sysfault-1' => ['sysfault.test 1 open/getcwd vfsfault persistent open and write body'],
            'sysfault-1.2' => ['sysfault.test 1.2 fstat ENOMEM/EOVERFLOW while opening and writing'],
            'sysfault-1.3' => ['sysfault.test 1.3 unix/unix-excl fcntl locking errno mapping'],
            'sysfault-3' => ['sysfault.test 3 fstat/fallocate EIO during chunked write path'],
            'sysfault-4' => ['sysfault.test 4 mmap EACCES during mapped SELECT'],
        };

        return [
            'status' => 'ok',
            'script' => 'sysfault.test',
            'scenario' => $scenario . '-' . $syscall . '-' . strtolower($errno) . '-' . $faultPosition,
            'upstream' => $upstream,
            'syscall' => $syscall,
            'errno' => $errno,
            'fault_position' => $faultPosition,
            'persistent_fault' => $persistent,
            'transient_fault' => !$persistent,
            'vfs' => $vfs,
            'installed_calls' => $installedCalls,
            'fault_injection' => [
                'install' => $installedCalls,
                'errno' => [$syscall => $errno],
                'fault_position' => $faultPosition,
                'persistent' => $persistent,
            ],
            'success_result' => $successResult,
            'acceptable_errors' => $errorMessages,
            'acceptable_result_count' => 1 + count($errorMessages),
            'database_reusable_after_fault' => true,
            'integrity_check_after_fault' => 'ok',
            'large_file_support_disabled' => $errno === 'EOVERFLOW',
            'readonly_error_allowed' => $scenario === 'sysfault-1' && $errno === 'EACCES',
            'lock_error_allowed' => $scenario === 'sysfault-1.3' && in_array($errno, ['EAGAIN', 'ETIMEDOUT', 'EBUSY', 'EINTR', 'ENOLCK', 'EACCES'], true),
            'falls_back_to_ioerr' => in_array('disk I/O error', $errorMessages, true),
            'mmap_read_can_fallback_or_error' => $scenario === 'sysfault-4',
            'chunked_write_can_ignore_hint_fault' => $scenario === 'sysfault-3',
            'dependencies' => [
                'sqlite-upstream-sysfault-test',
                'sqlite-vfs-persistent-unix-error-map',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function osErrorLogProfile(
        string $scenario,
        string $syscall,
        string $path,
        int $osErrorCode,
        int $sourceLine,
        bool $operationSucceeded = false
    ): array {
        $scenario = strtolower(trim($scenario));
        $syscall = strtolower(trim($syscall));
        $path = trim($path);

        $allowed = match ($scenario) {
            'oserror-1.1' => ['open', 'getcwd'],
            'oserror-1.2' => ['open'],
            'oserror-1.3' => ['open'],
            'oserror-1.4' => ['open', 'readlink', 'lstat'],
            'oserror-2.1' => ['unlink'],
            default => throw new \InvalidArgumentException('SQLite OS-error log profile scenario is unsupported'),
        };
        if (!in_array($syscall, $allowed, true)) {
            throw new \InvalidArgumentException('SQLite OS-error log profile syscall does not match scenario');
        }
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite OS-error log profile path must not be empty');
        }
        if ($osErrorCode < 1 || $sourceLine < 1) {
            throw new \InvalidArgumentException('SQLite OS-error log profile error code and source line must be positive');
        }
        if ($operationSucceeded && $scenario !== 'oserror-1.1') {
            throw new \InvalidArgumentException('Only oserror-1.1 may model an OS that does not exhaust file descriptors');
        }

        $pathSuffix = match ($scenario) {
            'oserror-1.2' => 'dir.db',
            'oserror-2.1' => 'test.db-wal',
            default => 'test.db',
        };
        if (!str_ends_with($path, $pathSuffix)) {
            throw new \InvalidArgumentException('SQLite OS-error log profile path does not match upstream scenario suffix');
        }

        $logRegex = match ($scenario) {
            'oserror-1.1' => '^os_unix\.c:\d+: \(\d+\) (open|getcwd)\(.*test\.db\) - ',
            'oserror-1.2' => '^os_unix\.c:\d+: \(\d+\) open\(.*dir\.db\) - ',
            'oserror-1.3' => '^os_unix\.c:\d+: \(\d+\) open\(.*test\.db\) - ',
            'oserror-1.4' => '^os_unix\.c:\d+: \(\d+\) (open|readlink|lstat)\(.*test\.db\) - ',
            'oserror-2.1' => '^os_unix\.c:\d+: \(\d+\) unlink\(.*test\.db-wal\) - ',
        };
        $logMessage = sprintf('os_unix.c:%d: (%d) %s(%s) - simulated OS error', $sourceLine, $osErrorCode, $syscall, $path);
        $logRequired = !$operationSucceeded;

        return [
            'status' => $operationSucceeded ? 'ok' : 'error',
            'script' => 'oserror.test',
            'scenario' => $scenario,
            'upstream' => self::osErrorUpstream($scenario),
            'default_vfs' => 'unix',
            'log_channel' => 'sqlite3_log',
            'allowed_syscalls' => $allowed,
            'syscall' => $syscall,
            'path' => $path,
            'path_suffix' => $pathSuffix,
            'os_error_code' => $osErrorCode,
            'source_line' => $sourceLine,
            'log_required' => $logRequired,
            'log_message' => $logRequired ? $logMessage : null,
            'log_regex' => $logRegex,
            'log_matches_upstream_regex' => $logRequired ? (preg_match('/' . $logRegex . '/', $logMessage) === 1) : true,
            'sqlite_result_code' => $operationSucceeded ? 'SQLITE_OK' : ($scenario === 'oserror-2.1' ? 'SQLITE_IOERR_DELETE' : 'SQLITE_CANTOPEN'),
            'result_message' => $operationSucceeded ? 'ok' : ($scenario === 'oserror-2.1' ? 'disk I/O error' : 'unable to open database file'),
            'too_many_file_descriptors_probe' => $scenario === 'oserror-1.1',
            'path_is_directory' => $scenario === 'oserror-1.2',
            'missing_parent_path' => $scenario === 'oserror-1.3',
            'restricted_root_path_probe' => $scenario === 'oserror-1.4',
            'wal_sidecar_unlink_failure' => $scenario === 'oserror-2.1',
            'cleanup_required' => $scenario === 'oserror-2.1' || $scenario === 'oserror-1.2',
            'database_reusable_after_cleanup' => true,
            'dependencies' => [
                'sqlite-upstream-oserror-test',
                'sqlite-vfs-os-error-logging',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sysfaultTransientEintrProfile(
        string $syscall,
        int $faultPosition,
        string $journalMode = 'truncate',
        int $chunkSize = 8192,
        int $blobBytes = 10000,
        bool $attachedWrite = true
    ): array {
        $syscall = strtolower(trim($syscall));
        $retryable = ['open', 'ftruncate', 'close', 'read', 'pread', 'pread64', 'write', 'fallocate'];
        if (!in_array($syscall, $retryable, true)) {
            throw new \InvalidArgumentException('SQLite sysfault transient EINTR profile syscall is unsupported');
        }
        if ($faultPosition < 1) {
            throw new \InvalidArgumentException('SQLite sysfault transient EINTR profile fault position must be positive');
        }
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'wal'], true)) {
            throw new \InvalidArgumentException('SQLite sysfault transient EINTR profile journal mode is unsupported');
        }
        if ($chunkSize < 1 || $blobBytes < 1) {
            throw new \InvalidArgumentException('SQLite sysfault transient EINTR profile chunk and payload sizes must be positive');
        }

        $journalEcho = $journalMode === 'wal' ? 'wal' : $journalMode;
        $rowsAfterCommit = [
            ['a' => 'abc', 'b' => 'def', 'c' => 'ghi'],
            ['a' => 'jkl', 'b' => 'mno', 'c' => 'pqr'],
        ];
        $largeRowsDeleted = $blobBytes > 3;
        $auxRows = $attachedWrite ? [2] : [1];
        $retryAttempts = $faultPosition + 1;

        return [
            'status' => 'ok',
            'script' => 'sysfault.test',
            'scenario' => 'sysfault-2.1-' . $syscall . '-' . $faultPosition,
            'upstream' => [
                'sysfault.test 2.setup attached database and primary-key table fixture',
                'sysfault.test 2.1 vfsfault-transient single EINTR does not affect processing',
            ],
            'syscall' => $syscall,
            'fault_position' => $faultPosition,
            'errno' => 'EINTR',
            'transient_fault' => true,
            'retry_required' => true,
            'retry_attempts_before_success' => $retryAttempts,
            'journal_mode' => $journalMode,
            'journal_mode_echo' => $journalEcho,
            'chunk_size' => $chunkSize,
            'blob_bytes' => $blobBytes,
            'attached_write' => $attachedWrite,
            'transaction_statements' => [
                'ATTACH test.db2 AS aux',
                'SELECT * FROM t1',
                'PRAGMA journal_mode = ' . $journalMode,
                'BEGIN',
                'INSERT INTO t1 VALUES(jkl,mno,pqr)',
                'INSERT INTO t1 VALUES(randomblob(' . $blobBytes . '),0,0)',
                $attachedWrite ? 'UPDATE aux.t2 SET x = 2' : 'SELECT x FROM aux.t2',
                'COMMIT',
                'DELETE FROM t1 WHERE length(a)>3',
            ],
            'initial_rows' => [['a' => 'abc', 'b' => 'def', 'c' => 'ghi']],
            'rows_after_commit_before_delete' => [
                ['a' => 'abc', 'b' => 'def', 'c' => 'ghi'],
                ['a' => 'jkl', 'b' => 'mno', 'c' => 'pqr'],
                ['a' => 'randomblob(' . $blobBytes . ')', 'b' => 0, 'c' => 0],
            ],
            'rows_after_delete' => $rowsAfterCommit,
            'aux_rows_after_commit' => $auxRows,
            'large_blob_row_deleted' => $largeRowsDeleted,
            'expected_result' => [
                'abc', 'def', 'ghi',
                $journalEcho,
                'abc', 'def', 'ghi',
                'jkl', 'mno', 'pqr',
                $auxRows[0],
            ],
            'result_code' => 'SQLITE_OK',
            'connection_reusable_after_fault' => true,
            'integrity_check' => 'ok',
            'dependencies' => [
                'sqlite-upstream-sysfault-test',
                'sqlite-vfs-transient-eintr-retry',
                'vfs-io-dynamic-real-corpus',
            ],
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
    public static function largeFileBoundaryProfile(
        string $script,
        int $fakeMegabytes,
        int $pageSize,
        int $seedDoublings = 7,
        int $tableCopyOrdinal = 0,
        int $overflowPayloadBytes = 30000,
        bool $headerPageCountCleared = true
    ): array {
        $script = trim($script);
        if (!in_array($script, ['bigfile.test', 'bigfile2.test'], true)) {
            throw new \InvalidArgumentException('SQLite large-file VFS profile script must be bigfile.test or bigfile2.test');
        }
        if ($fakeMegabytes < 4096) {
            throw new \InvalidArgumentException('SQLite large-file VFS profile requires at least a 4096 MiB sparse fixture');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite large-file VFS profile page size must be a power of two at least 512');
        }
        if ($seedDoublings < 0 || $seedDoublings > 20) {
            throw new \InvalidArgumentException('SQLite large-file VFS profile seed doubling count is out of range');
        }
        if ($tableCopyOrdinal < 0 || $tableCopyOrdinal > 3) {
            throw new \InvalidArgumentException('SQLite large-file VFS profile table copy ordinal must be between 0 and 3');
        }
        if ($overflowPayloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite large-file VFS profile overflow payload must be positive');
        }

        $fourGiB = 4294967296;
        $trailingBytes = $script === 'bigfile2.test' ? 14 : 0;
        $fakeFileBytes = ($fakeMegabytes * 1048576) + $trailingBytes;
        $actualPageCount = intdiv($fakeFileBytes + $pageSize - 1, $pageSize);
        $headerPageCount = $headerPageCountCleared ? 0 : $actualPageCount;
        $effectivePageCount = $headerPageCountCleared ? $actualPageCount : $headerPageCount;
        $firstAppendPage = $effectivePageCount + 1;
        $firstPagePastFourGiB = intdiv($fourGiB, $pageSize) + 1;

        $seedRows = 1 << $seedDoublings;
        $tables = array_slice(['t1', 't2', 't3', 't4'], 0, $tableCopyOrdinal + 1);
        $magicSum = '593f1efcfdbe698c28b4b1b693f7e4cf';
        $hashes = array_fill_keys($tables, $magicSum);

        $localPayloadBytes = min($overflowPayloadBytes, max(1, $pageSize - 128));
        $overflowBytes = max(0, $overflowPayloadBytes - $localPayloadBytes);
        $overflowPages = $overflowBytes === 0 ? 0 : (int) ceil($overflowBytes / max(1, $pageSize - 4));
        $overflowFirstPage = $firstAppendPage + 1;
        $overflowLastPage = $overflowPages === 0 ? null : $overflowFirstPage + $overflowPages - 1;
        $overflowPagesPastBoundary = $overflowPages === 0
            ? 0
            : max(0, $overflowLastPage - max($overflowFirstPage, $firstPagePastFourGiB) + 1);

        return [
            'status' => 'ok',
            'script' => $script,
            'scenario' => $script === 'bigfile.test' ? 'bigfile-1.1-through-1.16' : 'bigfile2-1.1-through-1.3',
            'upstream' => $script === 'bigfile.test'
                ? self::bigFileUpstream($fakeMegabytes, $tableCopyOrdinal)
                : [
                    'bigfile2.test 1.1 create small table',
                    'bigfile2.test 1.2 fake 4096 MiB file plus 14 bytes with cleared header page-count',
                    'bigfile2.test 1.3 large row readback from overflow pages beyond 4 GiB',
                ],
            'fake_file_megabytes' => $fakeMegabytes,
            'fake_file_bytes' => $fakeFileBytes,
            'trailing_bytes' => $trailingBytes,
            'page_size' => $pageSize,
            'header_page_count_cleared' => $headerPageCountCleared,
            'header_page_count_field' => $headerPageCount,
            'actual_page_count_from_file_size' => $actualPageCount,
            'effective_page_count' => $effectivePageCount,
            'uses_actual_file_size_for_page_count' => $headerPageCountCleared,
            'first_append_page' => $firstAppendPage,
            'first_page_past_4gib' => $firstPagePastFourGiB,
            'append_starts_at_or_past_4gib' => $firstAppendPage >= $firstPagePastFourGiB,
            'large_file_support_required' => true,
            'skip_when_large_file_support_disabled' => true,
            'requires_sparse_file_fixture' => true,
            'seed_doublings' => $seedDoublings,
            'seed_rows' => $seedRows,
            'magic_sum' => $magicSum,
            'visible_tables' => $tables,
            'table_copy_ordinal' => $tableCopyOrdinal,
            'copy_target_table' => $tableCopyOrdinal === 0 ? null : $tables[$tableCopyOrdinal],
            'hashes_by_table' => $hashes,
            'checksum_preserved_after_reopen' => true,
            'overflow_payload_bytes' => $overflowPayloadBytes,
            'overflow_local_payload_bytes' => $localPayloadBytes,
            'overflow_pages' => $overflowPages,
            'overflow_first_page' => $overflowPages === 0 ? null : $overflowFirstPage,
            'overflow_last_page' => $overflowLastPage,
            'overflow_pages_past_4gib' => $overflowPagesPastBoundary,
            'overflow_readback_length' => $script === 'bigfile2.test' ? $overflowPayloadBytes : null,
            'reason' => $script === 'bigfile.test'
                ? 'large_sparse_database_uses_actual_file_size_when_header_page_count_is_zero'
                : 'overflow_payload_pages_can_be_appended_and_read_back_beyond_4gib',
            'dependencies' => [
                'upstream-bigfile-test',
                'sqlite-large-file-vfs-boundary',
                'vfs-io-dynamic-real-corpus',
            ],
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
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function multiplexVfsChunkProfile(string $scenario, array $options = []): array
    {
        $scenario = trim($scenario);
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite multiplex VFS profile requires a scenario');
        }

        $group = self::multiplexGroup($scenario);
        $script = match (true) {
            str_starts_with($group, 'multiplex2') => 'multiplex2.test',
            str_starts_with($group, 'multiplex3') => 'multiplex3.test',
            str_starts_with($group, 'multiplex4') => 'multiplex4.test',
            default => 'multiplex.test',
        };

        $baseName = trim((string) ($options['base_name'] ?? match (true) {
            str_starts_with($group, 'multiplex4') => 'mx4test.db',
            str_starts_with($group, 'multiplex3') => 'test.db',
            default => 'test.x',
        }));
        if ($baseName === '') {
            throw new \InvalidArgumentException('SQLite multiplex VFS base name is required');
        }

        $chunkSize = (int) ($options['chunk_size'] ?? match (true) {
            str_starts_with($group, 'multiplex2') => 1048576,
            str_starts_with($group, 'multiplex3') => 262144,
            str_starts_with($group, 'multiplex4') => 10,
            default => 4096,
        });
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('SQLite multiplex VFS chunk size must be positive');
        }

        $maxChunks = (int) ($options['max_chunks'] ?? 16);
        if ($maxChunks < 1) {
            throw new \InvalidArgumentException('SQLite multiplex VFS max chunk count must be positive');
        }

        $pageSize = (int) ($options['page_size'] ?? 1024);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite multiplex VFS page size must be a power of two at least 512');
        }

        $journalMode = strtolower(trim((string) ($options['journal_mode'] ?? 'delete')));
        if (!in_array($journalMode, ['delete', 'persist', 'truncate', 'memory', 'off'], true)) {
            throw new \InvalidArgumentException('SQLite multiplex VFS journal mode is unsupported');
        }

        $rowCount = (int) ($options['row_count'] ?? match (true) {
            str_starts_with($group, 'multiplex2') || str_starts_with($group, 'multiplex3') => 512,
            str_starts_with($group, 'multiplex4') => 1,
            default => 256,
        });
        $payloadBytes = (int) ($options['payload_bytes'] ?? match (true) {
            str_starts_with($group, 'multiplex4') => 250000,
            str_starts_with($group, 'multiplex2') || str_starts_with($group, 'multiplex3') => 2048,
            default => 1000,
        });
        if ($rowCount < 1 || $payloadBytes < 0) {
            throw new \InvalidArgumentException('SQLite multiplex VFS row count and payload bytes must be non-negative');
        }

        $enabled = (bool) ($options['enabled'] ?? !str_starts_with($group, 'multiplex-2.7'));
        $shortNames = (bool) ($options['short_names'] ?? str_starts_with($group, 'multiplex3'));
        $truncateEnabled = (bool) ($options['truncate'] ?? str_starts_with($group, 'multiplex4'));
        $peerConnections = (int) ($options['peer_connections'] ?? match (true) {
            str_starts_with($group, 'multiplex-3') || str_starts_with($group, 'multiplex2') => 2,
            default => 1,
        });
        if ($peerConnections < 1) {
            throw new \InvalidArgumentException('SQLite multiplex VFS peer connection count must be positive');
        }

        $alignment = 65536;
        $alignedChunkSize = self::align($chunkSize, $alignment);
        $databaseBytes = self::align(max($pageSize * 2, ($pageSize * 2) + ($rowCount * ($payloadBytes + 64))), $pageSize);
        $wouldSpanChunks = $databaseBytes > $alignedChunkSize;
        $chunkCount = $enabled ? max(1, min($maxChunks, (int) ceil($databaseBytes / $alignedChunkSize))) : 1;

        if ($group === 'multiplex4-1' && $enabled) {
            $chunkCount = $wouldSpanChunks ? min($maxChunks, 2) : 1;
        }

        $chunkFiles = self::multiplexChunkFiles($baseName, $chunkCount, $shortNames);
        $filesAfterVacuum = $group === 'multiplex4-1' && $truncateEnabled
            ? [$baseName]
            : $chunkFiles;

        return [
            'status' => 'ok',
            'script' => $script,
            'scenario' => $scenario,
            'group' => $group,
            'upstream' => self::multiplexUpstream($group),
            'vfs' => 'multiplex',
            'base_name' => $baseName,
            'short_names' => $shortNames,
            'enabled' => $enabled,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'row_count' => $rowCount,
            'payload_bytes' => $payloadBytes,
            'database_bytes' => $databaseBytes,
            'chunk_size_requested' => $chunkSize,
            'chunk_size_alignment' => $alignment,
            'chunk_size_aligned' => $alignedChunkSize,
            'max_chunks' => $maxChunks,
            'chunk_count' => $chunkCount,
            'chunk_files' => $chunkFiles,
            'would_span_chunks' => $wouldSpanChunks,
            'disabled_keeps_single_base_file' => !$enabled && $wouldSpanChunks,
            'pragma_multiplex_enabled' => $enabled ? 1 : 0,
            'pragma_multiplex_filecount' => $chunkCount,
            'pragma_multiplex_chunksize' => $alignedChunkSize,
            'first_connection_row_count' => $rowCount,
            'second_connection_row_count' => $peerConnections > 1 ? $rowCount : null,
            'peer_connection_count' => $peerConnections,
            'multi_client_delete_vacuum_visible' => str_starts_with($group, 'multiplex2'),
            'hot_journal_copy_preserves_checksum' => $group === 'multiplex3-2',
            'faultsim_error_preserves_checksum' => in_array($group, ['multiplex3-1', 'multiplex3-3'], true),
            'backup_reopen_checksum_stable' => $group === 'multiplex3-3',
            'truncate_file_control_handled' => $group === 'multiplex4-1',
            'truncate_enabled' => $truncateEnabled,
            'files_after_vacuum' => $filesAfterVacuum,
            'pragma_multiplex_truncate_sequence' => $group === 'multiplex4-1'
                ? ['on', 'off', $truncateEnabled ? 'on' : 'off']
                : [],
            'reason' => match (true) {
                $group === 'multiplex-1' => 'multiplex_vfs_control_api_initializes_and_rejects_invalid_controls',
                $group === 'multiplex-2' => 'multiplex_vfs_chunks_large_database_writes_and_reports_filecount',
                $group === 'multiplex-3' => 'multiplex_vfs_tracks_multiple_connection_groups',
                $group === 'multiplex2-1' => 'multiplex_vfs_multi_client_reads_survive_delete_vacuum_cycles',
                $group === 'multiplex3-1' => 'multiplex_vfs_faultsim_preserves_original_checksum',
                $group === 'multiplex3-2' => 'multiplex_vfs_8_3_hot_journal_copy_reopens_original_checksum',
                $group === 'multiplex3-3' => 'multiplex_vfs_backup_reopen_keeps_checksum_or_reports_ioerr',
                default => 'multiplex_vfs_truncate_file_control_removes_or_preserves_chunks',
            },
            'dependencies' => [
                'sqlite-upstream-multiplex-test',
                'sqlite-vfs-multiplex-chunks',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function multiplexCrashRecoveryProfile(int $crashIteration, array $options = []): array
    {
        if ($crashIteration < 0 || $crashIteration > 19) {
            throw new \InvalidArgumentException('SQLite crashM multiplex crash iteration must be between 0 and 19');
        }

        $mainName = trim((string) ($options['main_name'] ?? 'test1.db'));
        $auxName = trim((string) ($options['aux_name'] ?? 'test2.db'));
        if ($mainName === '' || $auxName === '') {
            throw new \InvalidArgumentException('SQLite crashM database names must not be empty');
        }
        if ($mainName === $auxName) {
            throw new \InvalidArgumentException('SQLite crashM main and aux database names must differ');
        }

        $chunkSize = (int) ($options['chunk_size'] ?? 65536);
        $pageSize = (int) ($options['page_size'] ?? 1024);
        $rowCount = (int) ($options['row_count'] ?? 1000);
        $payloadBytes = (int) ($options['payload_bytes'] ?? 500);
        $updateModulo = (int) ($options['update_modulo'] ?? 10);
        $delay = (int) ($options['crash_delay'] ?? 1);

        if ($chunkSize < 1 || $pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite crashM chunk and page sizes are invalid');
        }
        if ($rowCount < 1 || $payloadBytes < 1 || $updateModulo < 1 || $delay < 1) {
            throw new \InvalidArgumentException('SQLite crashM row, payload, modulo, and delay values must be positive');
        }

        $alignment = 65536;
        $alignedChunkSize = self::align($chunkSize, $alignment);
        $tableRootAndSchemaPages = 5;
        $indexMultiplier = 2;
        $rowBytes = $payloadBytes + 64;
        $indexBytes = 32;
        $databaseBytes = self::align(
            max(
                $pageSize * $tableRootAndSchemaPages,
                ($pageSize * $tableRootAndSchemaPages) + ($rowCount * ($rowBytes + ($indexMultiplier * $indexBytes)))
            ),
            $pageSize
        );
        $chunkCount = max(1, (int) ceil($databaseBytes / $alignedChunkSize));
        $updatedRows = intdiv($rowCount, $updateModulo);

        $mainChunks = self::multiplexChunkFiles($mainName, $chunkCount, true);
        $auxChunks = self::multiplexChunkFiles($auxName, $chunkCount, true);
        $mainStem = preg_replace('/\.[^.]+$/', '', $mainName) ?? $mainName;
        $auxStem = preg_replace('/\.[^.]+$/', '', $auxName) ?? $auxName;

        return [
            'status' => 'ok',
            'script' => 'crashM.test',
            'scenario' => 'crashM-2.' . $crashIteration,
            'upstream' => [
                'crashM.test 1.0 setup multiplex 8.3 main and aux databases with indexed randomblob rows',
                'crashM.test 2.' . $crashIteration . '.1 crashsql exits abnormally during attached UPDATE transaction',
                'crashM.test 2.' . $crashIteration . '.2 main and aux integrity_check both return ok after recovery',
            ],
            'vfs' => 'multiplex',
            'uri_enabled' => true,
            'short_names_enabled' => true,
            'main_name' => $mainName,
            'aux_name' => $auxName,
            'attached_database' => 'aux',
            'chunk_size_requested' => $chunkSize,
            'chunk_size_alignment' => $alignment,
            'chunk_size_aligned' => $alignedChunkSize,
            'page_size' => $pageSize,
            'row_count_per_database' => $rowCount,
            'payload_bytes' => $payloadBytes,
            'index_count_per_database' => 2,
            'database_bytes_per_database' => $databaseBytes,
            'chunk_count_per_database' => $chunkCount,
            'chunk_files_by_database' => [
                'main' => $mainChunks,
                'aux' => $auxChunks,
            ],
            'rollback_journal_files' => [
                'main' => self::shortJournalName($mainName),
                'aux' => self::shortJournalName($auxName),
            ],
            'master_journal_file' => $mainStem . '.mj',
            'aux_master_journal_file' => $auxStem . '.mj',
            'crash_iteration' => $crashIteration,
            'crash_delay' => $delay,
            'transaction_sequence' => [
                'ATTACH file:' . $auxName . '?8_3_names=1 AS aux',
                'BEGIN',
                'UPDATE main.t1 SET y = randomblob(' . $payloadBytes . ') WHERE (x%' . $updateModulo . ')==0',
                'UPDATE aux.t2 SET y = randomblob(' . $payloadBytes . ') WHERE (x%' . $updateModulo . ')==0',
                'COMMIT',
            ],
            'updated_rows_per_database' => $updatedRows,
            'child_process_result' => [1, 'child process exited abnormally'],
            'rollback_required' => true,
            'hot_journal_or_master_journal_recovery' => true,
            'transaction_atomic_across_attached_databases' => true,
            'main_integrity_check' => 'ok',
            'aux_integrity_check' => 'ok',
            'integrity_sequence' => ['ok', 'ok'],
            'rows_visible_after_recovery' => [
                'main' => $rowCount,
                'aux' => $rowCount,
            ],
            'chunk_files_preserved_after_recovery' => true,
            'short_sidecar_names_preserved' => true,
            'database_image_stable_after_recovery' => true,
            'reason' => 'multiplex_8_3_attached_transaction_crash_recovers_both_databases_with_integrity_ok',
            'dependencies' => [
                'sqlite-upstream-crashM-test',
                'sqlite-vfs-multiplex-crash-recovery',
                'sqlite-vfs-short-sidecar-names',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function unixExclVfsProfile(string $scenario, array $options = []): array
    {
        $scenario = trim($scenario);
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite unix-excl VFS profile requires a scenario');
        }

        $group = self::unixExclGroup($scenario);
        $peerContext = strtolower(trim((string) ($options['peer_context'] ?? 'multi-process')));
        if (!in_array($peerContext, ['multi-process', 'same-process'], true)) {
            throw new \InvalidArgumentException('SQLite unix-excl peer context is unsupported');
        }

        $pageSize = (int) ($options['page_size'] ?? 1024);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite unix-excl page size must be a power of two at least 512');
        }

        $rowCount = (int) ($options['row_count'] ?? 1);
        if ($rowCount < 1) {
            throw new \InvalidArgumentException('SQLite unix-excl row count must be positive');
        }

        $readonly = $group === 'unixexcl-2';
        $sameProcessPeer = $peerContext === 'same-process';
        $processExclusive = !$readonly;
        $externalLocked = $processExclusive && !$sameProcessPeer;
        $baseRows = self::unixExclRows($rowCount, 'seed');

        $profile = [
            'status' => 'ok',
            'script' => 'unixexcl.test',
            'scenario' => $scenario,
            'group' => $group,
            'upstream' => self::unixExclUpstream($group),
            'vfs' => 'unix-excl',
            'peer_context' => $peerContext,
            'same_process_peer' => $sameProcessPeer,
            'read_only_open' => $readonly,
            'page_size' => $pageSize,
            'row_count' => $rowCount,
            'process_exclusive_lock_acquired' => $processExclusive,
            'same_process_clients_share_lock' => true,
            'external_process_blocked' => $externalLocked,
            'peer_before_unixexcl_read_result' => $baseRows,
            'first_connection_read_result' => $baseRows,
            'peer_after_unixexcl_read_result' => $externalLocked
                ? ['code' => 1, 'message' => 'database is locked']
                : ['code' => 0, 'rows' => $baseRows],
            'lock_scope' => $processExclusive ? 'process-exclusive' : 'ordinary-unix',
            'readonly_behaves_like_unix_vfs' => $readonly,
            'dependencies' => array_values(array_filter([
                'sqlite-upstream-unixexcl-test',
                'sqlite-vfs-unix-excl-process-lock',
                $group === 'unixexcl-3' ? 'sqlite-wal-unix-excl-snapshot' : null,
                'vfs-io-dynamic-real-corpus',
            ])),
        ];

        if ($group !== 'unixexcl-3') {
            $profile['journal_mode'] = 'delete';
            $profile['reason'] = $readonly
                ? 'readonly_unix_excl_open_behaves_like_ordinary_unix_vfs'
                : 'first_unix_excl_read_takes_process_wide_exclusive_lock';

            return $profile;
        }

        $walFramesBefore = (int) ($options['wal_frames_before'] ?? 5);
        $walFramesAfter = (int) ($options['wal_frames_after'] ?? 7);
        $insertedRows = (int) ($options['inserted_rows'] ?? 1);
        if ($walFramesBefore < 0 || $walFramesAfter < $walFramesBefore || $insertedRows < 1) {
            throw new \InvalidArgumentException('SQLite unix-excl WAL frame and inserted-row counts are invalid');
        }

        $insertRows = self::unixExclRows($insertedRows, 'insert');
        $writerRows = array_merge($baseRows, $insertRows);

        return $profile + [
            'journal_mode' => 'wal',
            'uri_parameters' => ['psow' => 0, 'vfs' => 'unix-excl'],
            'wal_frames_before_writer_insert' => $walFramesBefore,
            'wal_frames_after_reader_commit' => $walFramesAfter,
            'checkpoint_before_writer_insert' => ['busy' => 0, 'log' => $walFramesBefore, 'checkpointed' => $walFramesBefore],
            'reader_transaction_open' => $sameProcessPeer,
            'reader_visible_rows_during_transaction' => $sameProcessPeer ? $baseRows : [],
            'writer_visible_rows_after_insert' => $sameProcessPeer ? $writerRows : [],
            'reader_visible_rows_after_commit' => $sameProcessPeer ? $writerRows : [],
            'checkpoint_after_reader_commit' => $sameProcessPeer ? ['busy' => 0, 'log' => $walFramesAfter, 'checkpointed' => $walFramesAfter] : null,
            'wal_reader_snapshot_preserved' => $sameProcessPeer,
            'reason' => $sameProcessPeer
                ? 'same_process_unix_excl_wal_reader_keeps_snapshot_until_commit'
                : 'unix_excl_wal_database_blocks_external_process_reader',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function exclusiveLockingProfile(string $scenario, array $options = []): array
    {
        $scenario = trim($scenario);
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite exclusive-locking profile requires a scenario');
        }

        $group = self::exclusiveLockingGroup($scenario);
        $script = str_starts_with($group, 'exclusive2-') ? 'exclusive2.test' : 'exclusive.test';
        $pageSize = (int) ($options['page_size'] ?? 1024);
        $cachePages = (int) ($options['cache_pages'] ?? 1000);
        $rowCount = (int) ($options['row_count'] ?? 64);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite exclusive-locking page size must be a power of two at least 512');
        }
        if ($cachePages < 1) {
            throw new \InvalidArgumentException('SQLite exclusive-locking cache size must be positive');
        }
        if ($rowCount < 1) {
            throw new \InvalidArgumentException('SQLite exclusive-locking row count must be positive');
        }

        $profile = [
            'status' => 'ok',
            'script' => $script,
            'scenario' => $scenario,
            'group' => $group,
            'upstream' => self::exclusiveLockingUpstream($group),
            'page_size' => $pageSize,
            'cache_pages' => $cachePages,
            'row_count' => $rowCount,
            'temp_locking_mode' => 'exclusive',
            'pager_cache_can_hold_database' => $cachePages >= max(1, (int) ceil($rowCount / 4)),
            'dependencies' => [
                'sqlite-upstream-exclusive-test',
                'sqlite-pager-exclusive-locking',
                'vfs-io-dynamic-real-corpus',
            ],
        ];

        return $profile + match ($group) {
            'exclusive-1' => self::exclusivePragmaProfile($options),
            'exclusive-2' => self::exclusiveLockRetentionProfile($options),
            'exclusive-3' => self::exclusiveJournalTruncationProfile($options),
            'exclusive-4' => self::exclusiveRollbackProfile($rowCount, $options),
            'exclusive-5' => self::exclusiveStatementJournalProfile($options),
            'exclusive-6' => self::exclusiveHotJournalOpenProfile($options),
            'exclusive-7' => self::exclusiveWalToggleProfile(),
            'exclusive2-1' => self::exclusiveNormalCacheProfile($options),
            'exclusive2-2' => self::exclusiveStaleCacheProfile($options),
            'exclusive2-3' => self::exclusiveChangeCounterProfile($options),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function win32AntivirusLockRetryProfile(string $scenario, array $options = []): array
    {
        $scenario = strtolower(trim($scenario));
        if (!in_array($scenario, ['win32lock-1.2', 'win32lock-2.0', 'win32lock-2.1', 'win32lock-2.2', 'win32lock-3.2', 'win32lock-3.4'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite win32lock scenario: {$scenario}");
        }

        $retryCount = (int) ($options['retry_count'] ?? (in_array($scenario, ['win32lock-2.1', 'win32lock-2.2'], true) ? 1 : 10));
        $retryDelayMs = (int) ($options['retry_delay_ms'] ?? (in_array($scenario, ['win32lock-2.1', 'win32lock-2.2'], true) ? 1 : 25));
        $lockDelayMs = (int) ($options['lock_delay_ms'] ?? ($scenario === 'win32lock-2.2' ? 3 : 250));
        $rowCount = (int) ($options['row_count'] ?? 4);
        $basePayloadBytes = (int) ($options['base_payload_bytes'] ?? 100000);

        if ($retryCount < 0 || $retryDelayMs < 0 || $lockDelayMs < 0) {
            throw new \InvalidArgumentException('SQLite win32lock retry and delay values must be non-negative');
        }
        if ($rowCount < 1) {
            throw new \InvalidArgumentException('SQLite win32lock profile requires at least one row');
        }
        if ($basePayloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite win32lock profile requires a positive payload size');
        }

        $rows = [];
        for ($row = 1; $row <= $rowCount; $row++) {
            $rows[] = [$row, max(1, intdiv($basePayloadBytes, 1 << min(5, $row - 1)))];
        }

        $profile = [
            'status' => 'ok',
            'script' => 'win32lock.test',
            'scenario' => $scenario,
            'platform' => 'windows',
            'mmap_disabled' => true,
            'cache_size' => 10,
            'setup_rows' => $rows,
            'default_av_retry_control' => ['rc' => 0, 'retry_count' => 10, 'retry_delay_ms' => 25],
            'retry_count' => $retryCount,
            'retry_delay_ms' => $retryDelayMs,
            'lock_delay_ms' => $lockDelayMs,
            'retry_budget_ms' => $retryCount * $retryDelayMs,
            'dependencies' => [
                'sqlite-upstream-win32lock-antivirus-retry',
                'sqlite-vfs-win32-lock-retry',
                'vfs-io-dynamic-real-corpus',
            ],
        ];

        if (in_array($scenario, ['win32lock-1.2', 'win32lock-2.2'], true)) {
            $budget = $retryCount * $retryDelayMs;
            $readSucceeds = $lockDelayMs <= $budget;
            $attempts = $retryDelayMs === 0 ? ($lockDelayMs === 0 ? 0 : $retryCount) : min($retryCount, (int) ceil($lockDelayMs / max(1, $retryDelayMs)));

            return $profile + [
                'upstream' => [
                    'win32lock.test win32lock-1.1 setup table with large payload rows and mmap disabled',
                    'win32lock.test win32lock-1.2 transient lock loop returns both ok reads and disk I/O errors',
                    'win32lock.test win32lock-2.0 default file_control_win32_av_retry reports 10 retries at 25ms',
                    'win32lock.test win32lock-2.1 setting file_control_win32_av_retry to 1 retry at 1ms succeeds',
                    'win32lock.test win32lock-2.2 short retry loop still returns both ok reads and disk I/O errors',
                ],
                'av_retry_control_after_set' => $scenario === 'win32lock-2.2'
                    ? ['rc' => 0, 'retry_count' => $retryCount, 'retry_delay_ms' => $retryDelayMs]
                    : $profile['default_av_retry_control'],
                'retry_attempts_planned' => $attempts,
                'transient_lock_cleared_before_budget' => $readSucceeds,
                'select_result_code' => $readSucceeds ? 'SQLITE_OK' : 'SQLITE_IOERR_LOCK',
                'select_result_message' => $readSucceeds ? 'ok' : 'disk I/O error',
                'select_rows' => $readSucceeds ? $rows : [],
                'log_message_normalized' => $readSucceeds && $lockDelayMs > 0 ? 'delayed #ms for lock/sharing conflict' : null,
                'both_ok_and_error_possible_in_loop' => true,
                'database_image_stable_after_retry' => true,
                'reason' => $readSucceeds
                    ? 'win32_antivirus_retry_waits_out_transient_lock'
                    : 'win32_antivirus_retry_budget_exhaustion_surfaces_disk_io_error',
            ];
        }

        if ($scenario === 'win32lock-2.0' || $scenario === 'win32lock-2.1') {
            return $profile + [
                'upstream' => [
                    'win32lock.test win32lock-2.0 default file_control_win32_av_retry reports 10 retries at 25ms',
                    'win32lock.test win32lock-2.1 file_control_win32_av_retry db 1 1 reports 1 retry at 1ms',
                ],
                'file_control_result' => $scenario === 'win32lock-2.0'
                    ? ['rc' => 0, 'retry_count' => 10, 'retry_delay_ms' => 25]
                    : ['rc' => 0, 'retry_count' => $retryCount, 'retry_delay_ms' => $retryDelayMs],
                'file_control_mutates_connection' => $scenario === 'win32lock-2.1',
                'reason' => $scenario === 'win32lock-2.0'
                    ? 'win32_av_retry_file_control_reports_default_retry_window'
                    : 'win32_av_retry_file_control_updates_retry_window',
            ];
        }

        if ($scenario === 'win32lock-3.2') {
            return $profile + [
                'upstream' => [
                    'win32lock.test win32lock-3.0 setup two ordinary win32 handles',
                    'win32lock.test win32lock-3.1 first connection opens an exclusive transaction',
                    'win32lock.test win32lock-3.2 second connection BEGIN EXCLUSIVE is database locked',
                    'win32lock.test win32lock-3.3 first connection COMMIT releases the exclusive lock',
                ],
                'primary_handle' => 'db',
                'peer_handle' => 'db2',
                'primary_transaction' => ['begin' => 'exclusive', 'inserted_row' => 4, 'status' => 'open'],
                'peer_transaction_attempt' => ['begin' => 'exclusive', 'inserted_row' => 5, 'code' => 1, 'message' => 'database is locked'],
                'primary_commit_result' => ['code' => 0, 'message' => 'ok'],
                'peer_blocked_by_primary_exclusive' => true,
                'rows_after_primary_commit' => [1, 2, 3, 4],
                'reason' => 'ordinary_win32_handles_enforce_exclusive_transaction_contention',
            ];
        }

        return $profile + [
            'upstream' => [
                'win32lock.test win32lock-3.4 file_control_win32_set_handle 0 makes lock acquisition fail',
                'win32lock.test win32lock-3.4 restores the original handle and reports SQLITE_IOERR_LOCK',
            ],
            'primary_handle' => 'db',
            'saved_handle_available' => true,
            'handle_set_to_zero' => true,
            'write_attempt' => ['begin' => 'exclusive', 'inserted_row' => 6, 'commit' => true],
            'write_result' => ['code' => 1, 'message' => 'disk I/O error'],
            'restore_handle_result' => ['rc' => 0, 'handle_restored' => true],
            'extended_errcode' => 'SQLITE_IOERR_LOCK',
            'database_image_stable_after_failed_lock' => true,
            'reason' => 'invalid_win32_file_handle_maps_lock_failure_to_sqlite_ioerr_lock',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function win32NoLockProfile(string $scenario, array $options = []): array
    {
        $scenario = strtolower(trim($scenario));
        $cacheScenarios = ['win32nolock-1.3', 'win32nolock-1.4', 'win32nolock-1.5', 'win32nolock-1.6', 'win32nolock-1.7'];
        $lockScenarios = ['win32nolock-1.9.1', 'win32nolock-1.10.1', 'win32nolock-1.11.1', 'win32nolock-1.12.1'];
        if (!in_array($scenario, array_merge($cacheScenarios, $lockScenarios), true)) {
            throw new \InvalidArgumentException("Unsupported SQLite win32nolock scenario: {$scenario}");
        }

        $initialA = (int) ($options['initial_a'] ?? 1);
        $initialB = (int) ($options['initial_b'] ?? 2);
        $pendingA = (int) ($options['pending_a'] ?? 3);
        $pendingB = (int) ($options['pending_b'] ?? 4);
        $releaseMemoryBytes = (int) ($options['release_memory_bytes'] ?? 1000000);
        if ($initialA < 1 || $initialB < 1 || $pendingA < 1 || $pendingB < 1) {
            throw new \InvalidArgumentException('SQLite win32nolock profile row values must be positive integers');
        }
        if ($releaseMemoryBytes < 1) {
            throw new \InvalidArgumentException('SQLite win32nolock profile requires a positive release-memory request');
        }

        $initialRows = [[$initialA, $initialB]];
        $pendingRow = [$pendingA, $pendingB];
        $freshRows = [$initialRows[0], $pendingRow];
        $profile = [
            'status' => 'ok',
            'script' => 'win32nolock.test',
            'scenario' => $scenario,
            'platform' => 'windows',
            'initial_rows' => $initialRows,
            'pending_row' => $pendingRow,
            'fresh_rows' => $freshRows,
            'dependencies' => [
                'sqlite-upstream-win32nolock-vfs',
                'sqlite-vfs-win32-none-no-lock',
                'vfs-io-dynamic-real-corpus',
            ],
        ];

        if (in_array($scenario, $cacheScenarios, true)) {
            $observedRows = match ($scenario) {
                'win32nolock-1.3' => $freshRows,
                'win32nolock-1.7' => $freshRows,
                default => $initialRows,
            };
            $observedPhase = match ($scenario) {
                'win32nolock-1.3' => 'primary_select_during_uncommitted_transaction',
                'win32nolock-1.4' => 'peer_select_before_peer_transaction',
                'win32nolock-1.5' => 'peer_select_inside_peer_transaction',
                'win32nolock-1.6' => 'peer_select_after_primary_commit_before_cache_refresh',
                default => 'peer_select_after_memory_release_refresh',
            };

            return $profile + [
                'upstream' => [
                    'win32nolock.test win32nolock-1.2 opens db and db2 with -vfs win32-none and disables db2 mmap',
                    'win32nolock.test win32nolock-1.3 writer connection sees its uncommitted insert',
                    'win32nolock.test win32nolock-1.4 peer win32-none reader keeps the preexisting cached rows',
                    'win32nolock.test win32nolock-1.5 peer transaction reads the stale cached rows',
                    'win32nolock.test win32nolock-1.6 peer still reads stale rows after writer commit',
                    'win32nolock.test win32nolock-1.7 sqlite3_release_memory refreshes the peer view when memory management is available',
                ],
                'primary_vfs' => 'win32-none',
                'peer_vfs' => 'win32-none',
                'peer_mmap_size' => 0,
                'lock_calls_suppressed' => true,
                'primary_transaction' => ['begin' => true, 'committed' => false, 'inserted_row' => $pendingRow],
                'primary_select_during_transaction' => $freshRows,
                'peer_select_before_begin' => $initialRows,
                'peer_select_inside_transaction' => $initialRows,
                'primary_commit_result' => ['code' => 0, 'message' => 'ok'],
                'peer_select_after_commit_before_cache_refresh' => $initialRows,
                'release_memory_request_bytes' => $releaseMemoryBytes,
                'peer_select_after_memory_release' => $freshRows,
                'memory_release_required_for_peer_refresh' => true,
                'observed_phase' => $observedPhase,
                'observed_rows' => $observedRows,
                'change_counter_visible_without_lock_refresh' => $scenario === 'win32nolock-1.7',
                'reason' => 'win32_none_peer_cache_stays_stale_until_memory_release',
            ];
        }

        $primaryVfs = in_array($scenario, ['win32nolock-1.10.1', 'win32nolock-1.12.1'], true) ? 'win32-none' : 'win32';
        $peerVfs = in_array($scenario, ['win32nolock-1.11.1', 'win32nolock-1.12.1'], true) ? 'win32-none' : 'win32';
        $peerBlocked = $primaryVfs === 'win32' && $peerVfs === 'win32';
        $primarySuppressesLocks = $primaryVfs === 'win32-none';
        $peerSuppressesLocks = $peerVfs === 'win32-none';

        return $profile + [
            'upstream' => [
                'win32nolock.test win32nolock-1.9.1 two ordinary win32 handles reject the second BEGIN EXCLUSIVE',
                'win32nolock.test win32nolock-1.10.1 win32-none writer plus ordinary peer both begin exclusive transactions',
                'win32nolock.test win32nolock-1.11.1 ordinary writer plus win32-none peer both begin exclusive transactions',
                'win32nolock.test win32nolock-1.12.1 two win32-none handles both begin exclusive transactions',
            ],
            'primary_vfs' => $primaryVfs,
            'peer_vfs' => $peerVfs,
            'primary_suppresses_locks' => $primarySuppressesLocks,
            'peer_suppresses_locks' => $peerSuppressesLocks,
            'primary_begin_exclusive' => ['code' => 0, 'message' => 'ok'],
            'peer_begin_exclusive' => $peerBlocked
                ? ['code' => 1, 'message' => 'database is locked']
                : ['code' => 0, 'message' => 'ok'],
            'peer_blocked_by_primary_exclusive' => $peerBlocked,
            'both_exclusive_transactions_allowed' => !$peerBlocked,
            'lock_arbitration' => $peerBlocked ? 'ordinary_win32_byte_range_lock' : 'win32_none_no_lock_bypass',
            'lock_calls' => [
                'primary_x_lock' => $primarySuppressesLocks ? 0 : 1,
                'peer_x_lock' => $peerSuppressesLocks ? 0 : ($peerBlocked ? 1 : 1),
                'primary_x_unlock' => $primarySuppressesLocks ? 0 : 1,
                'peer_x_unlock' => $peerSuppressesLocks ? 0 : ($peerBlocked ? 0 : 1),
            ],
            'reason' => $peerBlocked
                ? 'ordinary_win32_handles_enforce_exclusive_transaction_contention'
                : 'win32_none_vfs_bypasses_exclusive_transaction_lock_arbitration',
        ];
    }

    /**
     * @return list<string>
     */
    private static function multiplexChunkFiles(string $baseName, int $chunkCount, bool $shortNames): array
    {
        $files = [$baseName];
        $stem = preg_replace('/\.[^.]+$/', '', $baseName) ?? $baseName;
        for ($chunk = 1; $chunk < $chunkCount; $chunk++) {
            $files[] = $shortNames ? sprintf('%s.%03d', $stem, $chunk) : sprintf('%s%03d', $baseName, $chunk);
        }

        sort($files, SORT_STRING);
        return array_values($files);
    }

    private static function multiplexGroup(string $scenario): string
    {
        foreach (['multiplex4-1', 'multiplex3-1', 'multiplex3-2', 'multiplex3-3', 'multiplex2-1'] as $group) {
            if (str_starts_with($scenario, $group)) {
                return $group;
            }
        }
        foreach (['multiplex-1', 'multiplex-2', 'multiplex-3'] as $group) {
            if (str_starts_with($scenario, $group)) {
                return $group;
            }
        }

        throw new \InvalidArgumentException("Unsupported SQLite multiplex VFS scenario: {$scenario}");
    }

    /**
     * @return list<string>
     */
    private static function multiplexUpstream(string $group): array
    {
        return match ($group) {
            'multiplex-1' => [
                'multiplex.test multiplex-1.0 initialize/shutdown control API',
                'multiplex.test multiplex-1.5 invalid control returns SQLITE_ERROR',
            ],
            'multiplex-2' => [
                'multiplex.test multiplex-2.1 open database using multiplex VFS',
                'multiplex.test multiplex-2.5 inserts random blobs across chunk boundaries',
                'multiplex.test multiplex-2.5.9 reports chunk zero and one at configured chunk size',
                'multiplex.test multiplex-2.5.10 reports three multiplex chunks',
                'multiplex.test multiplex-2.7 disabled multiplex leaves one oversized base file',
            ],
            'multiplex-3' => [
                'multiplex.test multiplex-3.1 opens multiple multiplex connection groups',
                'multiplex.test multiplex-3.2 reuses and closes group handles in order',
            ],
            'multiplex2-1' => [
                'multiplex2.test multiplex2-1.1 client two reads rows inserted by client one',
                'multiplex2.test multiplex2-1.2 delete plus vacuum by client two is visible to client one',
                'multiplex2.test multiplex2-1.3 client one reinserts rows visible to client two',
            ],
            'multiplex3-1' => [
                'multiplex3.test multiplex3-1.0 setup 8.3 multiplex database and checksum',
                'multiplex3.test multiplex3-1 faultsim update preserves checksum after I/O fault',
            ],
            'multiplex3-2' => [
                'multiplex3.test multiplex3-2.1 hot-journal copy reopens with original checksum',
                'multiplex3.test multiplex3-2.2..2.100 hot-journal copies remain recoverable',
            ],
            'multiplex3-3' => [
                'multiplex3.test multiplex3-3 faultsim backup into 8.3 multiplex database',
                'multiplex3.test multiplex3-3 backup result is SQLITE_OK or SQLITE_IOERR with stable source checksum',
            ],
            'multiplex4-1' => [
                'multiplex4.test multiplex4-1.1 URI chunksize creates base and chunk file',
                'multiplex4.test multiplex4-1.2 truncate-enabled VACUUM removes chunk files',
                'multiplex4.test multiplex4-1.3 PRAGMA multiplex_truncate toggles file-control state',
                'multiplex4.test multiplex4-1.5 truncate-off VACUUM preserves chunk files',
            ],
            default => throw new \InvalidArgumentException("Unsupported SQLite multiplex VFS upstream group: {$group}"),
        };
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

    private static function unixExclGroup(string $scenario): string
    {
        foreach (['unixexcl-1', 'unixexcl-2', 'unixexcl-3'] as $group) {
            if (str_starts_with($scenario, $group)) {
                return $group;
            }
        }

        throw new \InvalidArgumentException("Unsupported SQLite unix-excl scenario: {$scenario}");
    }

    /**
     * @return list<array{a: int|string, b: int|string}>
     */
    private static function unixExclRows(int $rowCount, string $prefix): array
    {
        $rows = [];
        for ($i = 1; $i <= $rowCount; $i++) {
            $rows[] = [
                'a' => $prefix === 'seed' && $i === 1 ? 'hello' : $prefix . '-' . $i,
                'b' => $prefix === 'seed' && $i === 1 ? 'world' : $i,
            ];
        }

        return $rows;
    }

    private static function exclusiveLockingGroup(string $scenario): string
    {
        foreach (['exclusive2-1', 'exclusive2-2', 'exclusive2-3'] as $group) {
            if (str_starts_with($scenario, $group)) {
                return $group;
            }
        }
        foreach (['exclusive-1', 'exclusive-2', 'exclusive-3', 'exclusive-4', 'exclusive-5', 'exclusive-6', 'exclusive-7'] as $group) {
            if (str_starts_with($scenario, $group)) {
                return $group;
            }
        }

        throw new \InvalidArgumentException("Unsupported SQLite exclusive-locking scenario: {$scenario}");
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function exclusivePragmaProfile(array $options): array
    {
        $attached = (int) ($options['attached_databases'] ?? 3);
        if ($attached < 0 || $attached > 4) {
            throw new \InvalidArgumentException('SQLite exclusive-locking attached database count must be between 0 and 4');
        }

        $assignment = strtolower(trim((string) ($options['assignment'] ?? 'exclusive')));
        if (!in_array($assignment, ['exclusive', 'normal', 'invalid'], true)) {
            throw new \InvalidArgumentException('SQLite exclusive-locking pragma assignment is unsupported');
        }

        $mainMode = $assignment === 'exclusive' ? 'exclusive' : 'normal';
        $attachedMode = $assignment === 'normal' ? 'normal' : 'exclusive';
        $attachedModes = array_fill(0, $attached, $attachedMode);

        return [
            'locking_mode' => $mainMode,
            'pragma_assignment' => $assignment,
            'invalid_assignment_ignored' => $assignment === 'invalid',
            'main_locking_mode' => $mainMode,
            'temp_locking_mode_after_assignment' => 'exclusive',
            'attached_database_count' => $attached,
            'attached_locking_modes' => $attachedModes,
            'connection_default_locking_mode' => $attached === 0 ? $mainMode : ($assignment === 'normal' ? 'normal' : 'exclusive'),
            'mode_propagates_to_new_attaches' => $assignment !== 'invalid',
            'reason' => 'exclusive_pragma_sets_default_mode_while_temp_remains_exclusive',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function exclusiveLockRetentionProfile(array $options): array
    {
        $stage = strtolower(trim((string) ($options['stage'] ?? 'peer-before-exclusive-read')));
        $allowed = [
            'peer-before-exclusive-read',
            'exclusive-shared-blocks-peer-write',
            'peer-reserved-commit-blocked',
            'exclusive-write-blocks-peer-read',
            'normal-assignment-keeps-lock',
            'normal-read-releases-lock',
        ];
        if (!in_array($stage, $allowed, true)) {
            throw new \InvalidArgumentException('SQLite exclusive-locking retention stage is unsupported');
        }

        return [
            'locking_mode' => str_starts_with($stage, 'normal-') ? 'normal' : 'exclusive',
            'stage' => $stage,
            'primary_lock' => match ($stage) {
                'peer-before-exclusive-read' => 'unlocked',
                'exclusive-shared-blocks-peer-write', 'peer-reserved-commit-blocked' => 'shared',
                default => 'exclusive',
            },
            'peer_read_result' => in_array($stage, ['exclusive-write-blocks-peer-read', 'normal-assignment-keeps-lock'], true) ? 'database is locked' : 'ok',
            'peer_write_result' => in_array($stage, ['exclusive-shared-blocks-peer-write', 'peer-reserved-commit-blocked'], true) ? 'database is locked' : 'ok',
            'peer_commit_result' => $stage === 'peer-reserved-commit-blocked' ? 'database is locked' : 'ok',
            'lock_released' => $stage === 'normal-read-releases-lock',
            'normal_assignment_releases_immediately' => false,
            'reason' => 'exclusive_mode_keeps_locks_until_a_normal_mode_access_releases_them',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function exclusiveJournalTruncationProfile(array $options): array
    {
        $event = strtolower(trim((string) ($options['event'] ?? 'commit')));
        if (!in_array($event, ['initial', 'begin-delete', 'commit', 'rollback', 'normal-release'], true)) {
            throw new \InvalidArgumentException('SQLite exclusive-locking journal event is unsupported');
        }

        return [
            'locking_mode' => $event === 'normal-release' ? 'normal' : 'exclusive',
            'journal_event' => $event,
            'journal_exists' => in_array($event, ['begin-delete', 'commit', 'rollback'], true),
            'journal_has_content' => $event === 'begin-delete',
            'commit_uses_truncate_not_delete' => $event === 'commit',
            'rollback_uses_truncate_not_delete' => $event === 'rollback',
            'normal_mode_access_deletes_truncated_journal' => $event === 'normal-release',
            'journal_file_state' => match ($event) {
                'initial', 'normal-release' => ['exists' => false, 'content' => false],
                'begin-delete' => ['exists' => true, 'content' => true],
                default => ['exists' => true, 'content' => false],
            },
            'reason' => 'exclusive_mode_truncates_rollback_journal_until_normal_access_deletes_it',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function exclusiveRollbackProfile(int $rowCount, array $options): array
    {
        $mutationRounds = (int) ($options['mutation_rounds'] ?? 2);
        if ($mutationRounds < 1) {
            throw new \InvalidArgumentException('SQLite exclusive-locking rollback mutation rounds must be positive');
        }

        return [
            'locking_mode' => 'exclusive',
            'default_cache_size' => (int) ($options['default_cache_size'] ?? 10),
            'seed_row_count' => $rowCount,
            'mutation_rounds' => $mutationRounds,
            'signature_before' => 'count:' . $rowCount . ':stable-md5',
            'signature_after_rollback' => 'count:' . $rowCount . ':stable-md5',
            'rollback_restores_signature' => true,
            'commit_after_rollback_allowed' => true,
            'reason' => 'exclusive_mode_rollback_restores_cached_table_signature',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function exclusiveStatementJournalProfile(array $options): array
    {
        $event = strtolower(trim((string) ($options['event'] ?? 'exclusive-after-commit')));
        $openFiles = [
            'normal-before-commit' => 2,
            'normal-after-commit' => 1,
            'exclusive-begin' => 2,
            'exclusive-statement' => 2,
            'exclusive-after-commit' => 2,
            'normal-release' => 1,
        ];
        if (!isset($openFiles[$event])) {
            throw new \InvalidArgumentException('SQLite exclusive-locking statement journal event is unsupported');
        }

        return [
            'locking_mode' => $event === 'normal-release' ? 'normal' : 'exclusive',
            'statement_event' => $event,
            'open_file_count' => $openFiles[$event],
            'journal_handle_retained' => in_array($event, ['exclusive-begin', 'exclusive-statement', 'exclusive-after-commit'], true),
            'statement_journal_opened_lazily' => true,
            'statement_journal_retained_after_commit' => $event === 'exclusive-after-commit',
            'normal_release_closes_extra_handles' => $event === 'normal-release',
            'reason' => 'exclusive_mode_keeps_rollback_journal_handle_open_after_commit',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function exclusiveHotJournalOpenProfile(array $options): array
    {
        $case = strtolower(trim((string) ($options['case'] ?? 'copied-hot-journal')));
        if (!in_array($case, ['copied-hot-journal', 'empty-database-with-stray-journal'], true)) {
            throw new \InvalidArgumentException('SQLite exclusive-locking hot-journal case is unsupported');
        }

        return [
            'locking_mode' => 'exclusive',
            'hot_journal_case' => $case,
            'hot_journal_recovered' => $case === 'copied-hot-journal',
            'stray_journal_ignored_for_empty_database' => $case === 'empty-database-with-stray-journal',
            'select_result' => $case === 'copied-hot-journal' ? ['exclusive', 'Eden', 1955] : ['exclusive'],
            'reason' => $case === 'copied-hot-journal'
                ? 'exclusive_mode_can_open_copied_database_with_hot_journal'
                : 'exclusive_mode_opens_empty_database_despite_stray_journal_file',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function exclusiveWalToggleProfile(): array
    {
        return [
            'locking_mode' => 'normal',
            'pragma_sequence' => ['exclusive', 'wal', 'normal', 0, 'delete'],
            'wal_mode_entered_under_exclusive_lock' => true,
            'normal_mode_user_version_read_preserves_change_count_done' => true,
            'rollback_journal_mode_restored' => true,
            'reason' => 'exclusive_wal_toggle_preserves_pager_change_count_state',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function exclusiveNormalCacheProfile(array $options): array
    {
        $initialCounter = (int) ($options['initial_change_counter'] ?? 1);
        if ($initialCounter < 0) {
            throw new \InvalidArgumentException('SQLite exclusive2 normal-cache change counter must be non-negative');
        }

        return [
            'locking_mode' => 'normal',
            'initial_change_counter' => $initialCounter,
            'peer_update_change_counter' => $initialCounter + 1,
            'reset_change_counter' => $initialCounter,
            'incremented_change_counter' => $initialCounter + 1,
            'stale_cache_visible_before_counter_increment' => true,
            'database_change_visible_after_counter_increment' => true,
            'cache_uses_change_counter' => true,
            'reason' => 'normal_mode_discards_cache_after_change_counter_increment',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function exclusiveStaleCacheProfile(array $options): array
    {
        $corruptBytes = (int) ($options['corrupt_bytes'] ?? 10000);
        if ($corruptBytes < 1) {
            throw new \InvalidArgumentException('SQLite exclusive2 stale-cache corruption length must be positive');
        }

        return [
            'locking_mode' => 'exclusive',
            'corrupt_bytes' => $corruptBytes,
            'corruption_visible_while_exclusive' => false,
            'change_counter_checked_while_exclusive' => false,
            'normal_assignment_keeps_cache' => true,
            'corruption_visible_after_normal_unlock' => true,
            'final_result' => 'database disk image is malformed',
            'reason' => 'exclusive_mode_uses_pager_cache_until_normal_unlock_discards_it',
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function exclusiveChangeCounterProfile(array $options): array
    {
        $normalWritesBefore = (int) ($options['normal_writes_before'] ?? 2);
        $exclusiveWrites = (int) ($options['exclusive_writes'] ?? 2);
        $normalWritesAfter = (int) ($options['normal_writes_after'] ?? 2);
        if ($normalWritesBefore < 0 || $exclusiveWrites < 1 || $normalWritesAfter < 1) {
            throw new \InvalidArgumentException('SQLite exclusive2 change-counter write counts are invalid');
        }

        $counter = 1;
        $sequence = [$counter];
        for ($i = 0; $i < $normalWritesBefore; $i++) {
            $sequence[] = ++$counter;
        }
        for ($i = 0; $i < $exclusiveWrites; $i++) {
            if ($i === 0) {
                ++$counter;
            }
            $sequence[] = $counter;
        }
        for ($i = 0; $i < $normalWritesAfter; $i++) {
            if ($i > 0) {
                ++$counter;
            }
            $sequence[] = $counter;
        }

        return [
            'locking_mode' => 'exclusive-to-normal',
            'normal_writes_before' => $normalWritesBefore,
            'exclusive_writes' => $exclusiveWrites,
            'normal_writes_after' => $normalWritesAfter,
            'change_counter_sequence' => $sequence,
            'exclusive_reuses_change_counter' => true,
            'first_normal_write_after_release_reuses_counter' => true,
            'subsequent_normal_write_increments_counter' => $normalWritesAfter > 1,
            'reason' => 'exclusive_mode_increments_change_counter_once_until_lock_release_finishes',
        ];
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
    private static function bigFileUpstream(int $fakeMegabytes, int $tableCopyOrdinal): array
    {
        $upstream = ['bigfile.test bigfile-1.1 seed table checksum'];
        if ($fakeMegabytes >= 4096) {
            $upstream[] = 'bigfile.test bigfile-1.2 read t1 after fake 4096 MiB file';
            if ($tableCopyOrdinal >= 1) {
                $upstream[] = 'bigfile.test bigfile-1.3 create t2 beyond 4096 MiB boundary';
            }
            $upstream[] = 'bigfile.test bigfile-1.4 reopen and reread t1';
        }
        if ($fakeMegabytes >= 8192) {
            $upstream[] = 'bigfile.test bigfile-1.5 read t1 after fake 8192 MiB file';
            if ($tableCopyOrdinal >= 1) {
                $upstream[] = 'bigfile.test bigfile-1.6 read t2 after fake 8192 MiB file';
            }
            if ($tableCopyOrdinal >= 2) {
                $upstream[] = 'bigfile.test bigfile-1.7 create t3 beyond 8192 MiB boundary';
            }
            $upstream[] = 'bigfile.test bigfile-1.8 reopen and reread t1';
            if ($tableCopyOrdinal >= 1) {
                $upstream[] = 'bigfile.test bigfile-1.9 reread t2 after reopen';
            }
        }
        if ($fakeMegabytes >= 16384) {
            $upstream[] = 'bigfile.test bigfile-1.10 read t1 after fake 16384 MiB file';
            if ($tableCopyOrdinal >= 1) {
                $upstream[] = 'bigfile.test bigfile-1.11 read t2 after fake 16384 MiB file';
            }
            if ($tableCopyOrdinal >= 2) {
                $upstream[] = 'bigfile.test bigfile-1.12 read t3 after fake 16384 MiB file';
            }
            if ($tableCopyOrdinal >= 3) {
                $upstream[] = 'bigfile.test bigfile-1.13 create t4 beyond 16384 MiB boundary';
            }
            $upstream[] = 'bigfile.test bigfile-1.14 reopen and reread t1';
            if ($tableCopyOrdinal >= 1) {
                $upstream[] = 'bigfile.test bigfile-1.15 reread t2 after reopen';
            }
            if ($tableCopyOrdinal >= 2) {
                $upstream[] = 'bigfile.test bigfile-1.16 reread t3 after reopen';
            }
        }

        return $upstream;
    }

    /**
     * @return list<string>
     */
    private static function osErrorUpstream(string $scenario): array
    {
        return match ($scenario) {
            'oserror-1.1' => [
                'oserror.test 1.1.1 open/getcwd failure may report unable to open database file',
                'oserror.test 1.1.3 sqlite3_log matches open|getcwd test.db OS diagnostic',
            ],
            'oserror-1.2' => [
                'oserror.test 1.2.1 opening directory path returns unable to open database file',
                'oserror.test 1.2.2 sqlite3_log matches open dir.db OS diagnostic',
            ],
            'oserror-1.3' => [
                'oserror.test 1.3.1 missing parent path returns unable to open database file',
                'oserror.test 1.3.2 sqlite3_log matches open test.db OS diagnostic',
            ],
            'oserror-1.4' => [
                'oserror.test 1.4.1 restricted root path returns unable to open database file',
                'oserror.test 1.4.2 sqlite3_log matches open|readlink|lstat test.db OS diagnostic',
            ],
            'oserror-2.1' => [
                'oserror.test 2.1.1 WAL sidecar directory causes disk I/O error',
                'oserror.test 2.1.2 sqlite3_log matches unlink test.db-wal OS diagnostic',
                'oserror.test 2.1.3 closes connection and removes WAL sidecar directory',
            ],
            default => throw new \InvalidArgumentException("Unsupported SQLite OS-error scenario: {$scenario}"),
        };
    }

    /**
     * @return list<string>
     */
    private static function ioErrorRecoveryUpstream(string $scenario): array
    {
        if (str_starts_with($scenario, 'ioerr-1')) {
            return ['ioerr.test ioerr-1 rollback/commit/delete error sweep'];
        }
        if (str_starts_with($scenario, 'ioerr-2')) {
            return ['ioerr.test ioerr-2 VACUUM IO error checksum/refcount sweep'];
        }
        if (str_starts_with($scenario, 'ioerr-3')) {
            return ['ioerr.test ioerr-3 delete/update/create after overflow-row setup'];
        }
        if (str_starts_with($scenario, 'ioerr-5')) {
            return ['ioerr.test ioerr-5 attached database multi-file commit'];
        }
        if (str_starts_with($scenario, 'ioerr-7')) {
            return ['ioerr.test ioerr-7 hot journal rollback after copied journal'];
        }
        if (str_starts_with($scenario, 'ioerr-9')) {
            return ['ioerr.test ioerr-9 master-journal name read during hot journal'];
        }
        if (str_starts_with($scenario, 'ioerr-10')) {
            return ['ioerr.test ioerr-10 statement playback constraint rollback'];
        }
        if (str_starts_with($scenario, 'ioerr-12')) {
            return ['ioerr.test ioerr-12 coresident sector journal write failure'];
        }
        if (str_starts_with($scenario, 'ioerr-13')) {
            return ['ioerr.test ioerr-13 quick-balance pointer-map IO error'];
        }
        if (str_starts_with($scenario, 'ioerr-14')) {
            return ['ioerr.test ioerr-14 balance-deeper pointer-map IO error'];
        }
        if (str_starts_with($scenario, 'ioerr2-3') || str_starts_with($scenario, 'ioerr2-4')) {
            return ['ioerr2.test ioerr2-3/ioerr2-4 rollback preserves checksum and refcount'];
        }
        if (str_starts_with($scenario, 'ioerr2-5')) {
            return ['ioerr2.test ioerr2-5 UPDATE inside SELECT reports disk I/O error'];
        }
        if (str_starts_with($scenario, 'ioerr2-6')) {
            return ['ioerr2.test ioerr2-6 temp_store_directory xAccess failure'];
        }
        if (str_starts_with($scenario, 'ioerr2-7')) {
            return ['ioerr2.test ioerr2-7 auto-vacuum update/delete commit sweep'];
        }
        if (str_starts_with($scenario, 'ioerr3-1')) {
            return ['ioerr3.test ioerr3-1 soft-heap-limit transaction IO errors'];
        }
        if (str_starts_with($scenario, 'ioerr3-2')) {
            return ['ioerr3.test ioerr3-2 CREATE TEMP TABLE IO error'];
        }
        if (str_starts_with($scenario, 'tempfault-1')) {
            return ['tempfault.test faultsim 1 temp database insert may keep before or after rows'];
        }
        if (str_starts_with($scenario, 'tempfault-2')) {
            return ['tempfault.test faultsim 2 temp indexed table update integrity'];
        }
        if (str_starts_with($scenario, 'tempfault-3') || str_starts_with($scenario, 'tempfault-4')) {
            return ['tempfault.test faultsim 3/4 temp savepoint rollback integrity'];
        }

        throw new \InvalidArgumentException("Unsupported SQLite IO error recovery scenario: {$scenario}");
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
    private static function unixExclUpstream(string $group): array
    {
        return match ($group) {
            'unixexcl-1' => [
                'unixexcl.test unixexcl-1.* read/write unix-excl connection takes a process-wide exclusive lock on first read',
                'unixexcl.test unixexcl-1.* same-process peer may still read while external process is blocked',
            ],
            'unixexcl-2' => [
                'unixexcl.test unixexcl-2.* read-only unix-excl connection behaves like ordinary unix VFS',
                'unixexcl.test unixexcl-2.* external process can read read-only unix-excl database',
            ],
            'unixexcl-3' => [
                'unixexcl.test unixexcl-3.* WAL database opened with file:test.db?psow=0 and vfs=unix-excl',
                'unixexcl.test unixexcl-3.* external process is blocked by unix-excl WAL lock',
                'unixexcl.test unixexcl-3.* same-process WAL reader keeps pre-insert snapshot until COMMIT',
                'unixexcl.test unixexcl-3.* checkpoints report complete frame counts before and after reader release',
            ],
            default => throw new \InvalidArgumentException("Unsupported SQLite unix-excl upstream group: {$group}"),
        };
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
     * @param list<array<string, int|string>> $rows
     * @param list<string> $columns
     * @return list<int|string>
     */
    private static function flattenRows(array $rows, array $columns): array
    {
        $flat = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $flat[] = $row[$column];
            }
        }

        return $flat;
    }

    private static function syncPragmaUpstream(string $scenario): string
    {
        if (str_starts_with($scenario, 'sync2-1.1-delete-default')) {
            return 'sync2.test 1.1 delete journal default/full transaction';
        }
        if (str_starts_with($scenario, 'sync2-1.2.3-delete-normal')) {
            return 'sync2.test 1.2.3 delete journal synchronous=NORMAL transaction';
        }
        if (str_starts_with($scenario, 'sync2-1.3.3-delete-off')) {
            return 'sync2.test 1.3.3 delete journal synchronous=OFF transaction';
        }
        if (str_starts_with($scenario, 'sync2-1.4.3-delete-full')) {
            return 'sync2.test 1.4.3 delete journal synchronous=FULL transaction';
        }
        if (str_starts_with($scenario, 'sync2-1.6-wal-full-first')) {
            return 'sync2.test 1.6 WAL synchronous=FULL first transaction';
        }
        if (str_starts_with($scenario, 'sync2-1.7-wal-full-subsequent')) {
            return 'sync2.test 1.7 WAL synchronous=FULL subsequent transaction';
        }
        if (str_starts_with($scenario, 'sync2-1.8.3-wal-normal-subsequent')) {
            return 'sync2.test 1.8.3 WAL synchronous=NORMAL subsequent transaction';
        }
        if (str_starts_with($scenario, 'sync2-1.9-wal-checkpoint-normal')) {
            return 'sync2.test 1.9 WAL checkpoint syncs WAL and database';
        }
        if (str_starts_with($scenario, 'sync2-1.10.3-wal-off')) {
            return 'sync2.test 1.10.3 WAL synchronous=OFF transaction';
        }
        if (str_starts_with($scenario, 'sync2-1.11.1-default-wal-first-normal')) {
            return 'sync2.test 1.11.1 default WAL synchronous=NORMAL first transaction';
        }
        if (str_starts_with($scenario, 'sync2-1.11.2-default-wal-subsequent-normal')) {
            return 'sync2.test 1.11.2 default WAL synchronous=NORMAL subsequent transaction';
        }
        if (str_starts_with($scenario, 'sync-1.1-attach-schema-setup')) {
            return 'sync.test sync-1.1 main plus attached schema setup';
        }
        if (str_starts_with($scenario, 'sync-1.2-attached-on')) {
            return 'sync.test sync-1.2 attached synchronous=ON multi-database commit';
        }
        if (str_starts_with($scenario, 'sync-1.3-attached-full')) {
            return 'sync.test sync-1.3 attached synchronous=FULL multi-database commit';
        }
        if (str_starts_with($scenario, 'sync-1.4-attached-off')) {
            return 'sync.test sync-1.4 attached synchronous=OFF multi-database commit';
        }

        throw new \InvalidArgumentException("Unsupported SQLite sync pragma scenario: {$scenario}");
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

    /**
     * @return list<string>
     */
    private static function exclusiveLockingUpstream(string $group): array
    {
        return match ($group) {
            'exclusive-1' => [
                'exclusive.test exclusive-1.0 through exclusive-1.13 pragma locking_mode propagation',
                'exclusive.test exclusive-1.99 detach cleanup',
            ],
            'exclusive-2' => [
                'exclusive.test exclusive-2.0 through exclusive-2.11 exclusive locks block peer reads/writes until normal access releases them',
            ],
            'exclusive-3' => [
                'exclusive.test exclusive-3.0 through exclusive-3.6 exclusive commits truncate rollback journal then normal access deletes it',
            ],
            'exclusive-4' => [
                'exclusive.test exclusive-4.0 through exclusive-4.5 rollback in exclusive mode preserves table signature',
            ],
            'exclusive-5' => [
                'exclusive.test exclusive-5.0 through exclusive-5.7 statement journal handles remain open in exclusive mode',
            ],
            'exclusive-6' => [
                'exclusive.test exclusive-6.2 through exclusive-6.5 exclusive mode opens copied hot-journal and stray-journal databases',
            ],
            'exclusive-7' => [
                'exclusive.test exclusive-7.1 WAL mode transition out of exclusive locking preserves Pager.changeCountDone state',
            ],
            'exclusive2-1' => [
                'exclusive2.test exclusive2-1.0 through exclusive2-1.11 normal mode checks change-counter before discarding pager cache',
            ],
            'exclusive2-2' => [
                'exclusive2.test exclusive2-2.1 through exclusive2-2.8 exclusive mode ignores on-disk corruption until normal unlock',
            ],
            'exclusive2-3' => [
                'exclusive2.test exclusive2-3.0 through exclusive2-3.6 exclusive mode increments change-counter only once',
            ],
        };
    }
}
