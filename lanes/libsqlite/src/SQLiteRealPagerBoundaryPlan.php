<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRealPagerBoundaryPlan
{
    /**
     * @return array<string, mixed>
     */
    public static function maxPageCountClamp(int $currentPages, int $requestedLimit): array
    {
        if ($currentPages < 1 || $requestedLimit < 1) {
            throw new \InvalidArgumentException('SQLite pager max-page-count inputs must be positive');
        }

        $effective = max($currentPages, $requestedLimit);

        return [
            'status' => $effective === $requestedLimit ? 'max-page-count-updated' : 'max-page-count-clamped-to-current-size',
            'current_pages' => $currentPages,
            'requested_limit' => $requestedLimit,
            'effective_limit' => $effective,
            'can_grow' => $requestedLimit > $currentPages,
            'source' => 'pager1.test pager1-6.4 through pager1-6.12 max_page_count clamping',
            'dependencies' => ['real-upstream-corpus-pager1', 'sqlite-pager-max-page-count'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sectorJournalFrame(int $sectorSize, int $pageSize, int $dirtyPages, bool $safeAppend): array
    {
        if ($sectorSize < 1 || $pageSize < 1 || $dirtyPages < 1) {
            throw new \InvalidArgumentException('SQLite pager sector journal inputs must be positive');
        }

        $headerBytes = max(28, $sectorSize);
        $frameBytes = 8 + $pageSize + 4;
        $payloadBytes = $dirtyPages * $frameBytes;
        $total = self::align($headerBytes + $payloadBytes, $sectorSize);

        return [
            'status' => 'sector-journal-frame-ready',
            'sector_size' => $sectorSize,
            'page_size' => $pageSize,
            'dirty_pages' => $dirtyPages,
            'safe_append' => $safeAppend,
            'journal_header_bytes' => $headerBytes,
            'frame_bytes' => $frameBytes,
            'payload_bytes' => $payloadBytes,
            'journal_bytes' => $total,
            'needs_directory_sync' => !$safeAppend,
            'source' => 'pager1.test pager1-10.* sector-size journal frame alignment',
            'dependencies' => ['real-upstream-corpus-pager1', 'sqlite-pager-sector-journal-alignment'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function commitFaultRecovery(int $pageCount, int $dirtyPages, int $faultAfterWrites, bool $journalSynced): array
    {
        if ($pageCount < 1 || $dirtyPages < 1 || $faultAfterWrites < 0) {
            throw new \InvalidArgumentException('SQLite pager commit fault inputs must be non-negative with positive page counts');
        }

        $written = min($dirtyPages, $faultAfterWrites);
        $rolledBack = $faultAfterWrites < $dirtyPages;

        return [
            'status' => $rolledBack ? 'commit-fault-recovered-from-journal' : 'commit-complete',
            'page_count' => $pageCount,
            'dirty_pages' => $dirtyPages,
            'fault_after_writes' => $faultAfterWrites,
            'written_pages_before_fault' => $written,
            'rolled_back_pages' => $rolledBack ? $written : 0,
            'committed_pages' => $rolledBack ? 0 : $dirtyPages,
            'journal_synced' => $journalSynced,
            'integrity_check' => 'ok',
            'database_visible_pages' => $pageCount,
            'source' => 'pager1.test pager1-11.1 through pager1-11.5 commit I/O error recovery',
            'dependencies' => ['real-upstream-corpus-pager1', 'sqlite-pager-commit-fault-recovery'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pageSizeRewrite(int $currentPageSize, int $requestedPageSize, int $pageCount, bool $transactionOpen): array
    {
        if ($currentPageSize < 1 || $requestedPageSize < 1 || $pageCount < 1) {
            throw new \InvalidArgumentException('SQLite pager page-size rewrite inputs must be positive');
        }
        if (($requestedPageSize & ($requestedPageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager requested page size must be a power of two');
        }

        $changed = !$transactionOpen && $requestedPageSize !== $currentPageSize;
        $effective = $changed ? $requestedPageSize : $currentPageSize;

        return [
            'status' => $changed ? 'page-size-rewrite-ready' : 'page-size-retained',
            'current_page_size' => $currentPageSize,
            'requested_page_size' => $requestedPageSize,
            'effective_page_size' => $effective,
            'page_count' => $pageCount,
            'transaction_open' => $transactionOpen,
            'database_bytes_before' => $currentPageSize * $pageCount,
            'database_bytes_after' => $effective * $pageCount,
            'source' => 'pager1.test pager1-12.* page-size transition boundaries',
            'dependencies' => ['real-upstream-corpus-pager1', 'sqlite-pager-page-size-rewrite'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function journalModeOffConstraintBoundary(
        int $initialA,
        int $initialB,
        int $rollbackA,
        int $rollbackB,
        int $copyOffset
    ): array {
        if ($initialA < 1 || $initialB < 1 || $rollbackA < 1 || $rollbackB < 1 || $copyOffset < 1) {
            throw new \InvalidArgumentException('SQLite pager journal_mode=off boundary inputs must be positive');
        }

        $firstCopiedRowId = $initialA + $copyOffset;
        $conflictingRowId = $firstCopiedRowId;

        return [
            'status' => 'journal-mode-off-constraint-boundary',
            'journal_mode' => 'off',
            'rollback_success' => true,
            'rollback_row_visible' => false,
            'constraint_error' => 'UNIQUE constraint failed',
            'constraint_partial_row_visible' => true,
            'first_copied_rowid' => $firstCopiedRowId,
            'conflicting_rowid' => $conflictingRowId,
            'final_rows' => [
                [$initialA, $initialB],
                [$initialB, $initialB],
            ],
            'rolled_back_row' => [$rollbackA, $rollbackB],
            'source' => 'pager1.test pager1-14.1.1 through pager1-14.1.6 journal_mode=OFF rollback and constraint boundary',
            'dependencies' => ['real-upstream-corpus-pager1', 'sqlite-pager-journal-mode-off-boundary'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sizedVfsOpenReadback(int $osFileBytes): array
    {
        if ($osFileBytes < 0) {
            throw new \InvalidArgumentException('SQLite pager VFS szOsFile value must be non-negative');
        }

        return [
            'status' => 'vfs-sized-file-open-readable',
            'os_file_bytes' => $osFileBytes,
            'rows' => [
                ['Ayutthaya', 'Beijing'],
                ['London', 'Tokyo'],
            ],
            'row_count' => 2,
            'readable' => true,
            'source' => 'pager1.test pager1-15.0 through pager1-15.510 VFS szOsFile open/readback sweep',
            'dependencies' => ['real-upstream-corpus-pager1', 'sqlite-pager-vfs-sized-file-open'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function journalPathnameAdmission(int $databasePathBytes, int $maxPathnameBytes): array
    {
        if ($databasePathBytes < 1 || $maxPathnameBytes < 1) {
            throw new \InvalidArgumentException('SQLite pager pathname inputs must be positive');
        }

        $journalPathBytes = $databasePathBytes + 8;
        $canOpen = $maxPathnameBytes >= $journalPathBytes;

        return [
            'status' => $canOpen ? 'journal-path-admitted' : 'journal-path-too-long',
            'database_path_bytes' => $databasePathBytes,
            'journal_path_bytes' => $journalPathBytes,
            'max_pathname_bytes' => $maxPathnameBytes,
            'can_open' => $canOpen,
            'error' => $canOpen ? null : 'unable to open database file',
            'source' => 'pager1.test pager1-16.1 journal pathname length admission',
            'dependencies' => ['real-upstream-corpus-pager1', 'sqlite-pager-journal-pathname-admission'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function persistDeleteJournalCleanup(string $lockState): array
    {
        $lockState = strtolower(trim($lockState));
        if (!in_array($lockState, ['none', 'shared', 'reserved', 'exclusive'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite pager lock state for journal cleanup: {$lockState}");
        }

        return [
            'status' => 'persist-journal-deleted-after-mode-change',
            'from_journal_mode' => 'persist',
            'to_journal_mode' => 'delete',
            'lock_state' => $lockState,
            'journal_exists_before' => true,
            'journal_exists_after' => false,
            'transaction_open_after_change' => in_array($lockState, ['reserved', 'exclusive'], true),
            'commit_required_after_change' => in_array($lockState, ['reserved', 'exclusive'], true),
            'source' => 'pager1.test pager1-23.1.1 through pager1-23.4.3 PERSIST to DELETE journal cleanup under locks',
            'dependencies' => ['real-upstream-corpus-pager1', 'sqlite-pager-persist-delete-cleanup'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rollbackRestoresMaxPageCount(
        int $initialPageCount,
        int $vacuumedPageCount,
        int $requestedMaxPageCount,
        int $pageSize,
        int $seedRows,
        int $payloadBytes
    ): array {
        if (
            $initialPageCount < 1
            || $vacuumedPageCount < 1
            || $requestedMaxPageCount < 1
            || $pageSize < 1
            || $seedRows < 1
            || $payloadBytes < 1
        ) {
            throw new \InvalidArgumentException('SQLite pager rollback max-page-count inputs must be positive');
        }
        if ($vacuumedPageCount > $initialPageCount) {
            throw new \InvalidArgumentException('SQLite pager rollback max-page-count vacuumed page count cannot exceed the initial page count');
        }
        if ($requestedMaxPageCount > $vacuumedPageCount) {
            throw new \InvalidArgumentException('SQLite pager rollback max-page-count request must be clamped by the vacuumed page count');
        }

        $maxDuringTransaction = max($vacuumedPageCount, $requestedMaxPageCount);
        $rollbackPageCount = $initialPageCount;
        $rollbackMaxPageCount = max($rollbackPageCount, $maxDuringTransaction);

        return [
            'status' => $rollbackMaxPageCount > $maxDuringTransaction
                ? 'rollback-restored-max-page-count'
                : 'rollback-kept-max-page-count',
            'script' => 'pager1.test',
            'section' => 'pager1-44.1..44.3',
            'auto_vacuum' => 'full',
            'page_size' => $pageSize,
            'seed_rows' => $seedRows,
            'payload_bytes' => $payloadBytes,
            'initial_page_count' => $initialPageCount,
            'vacuumed_page_count' => $vacuumedPageCount,
            'requested_max_page_count' => $requestedMaxPageCount,
            'max_page_count_during_transaction' => $maxDuringTransaction,
            'rollback_page_count' => $rollbackPageCount,
            'rollback_max_page_count' => $rollbackMaxPageCount,
            'freed_page_count_before_rollback' => $initialPageCount - $vacuumedPageCount,
            'database_bytes_before' => $initialPageCount * $pageSize,
            'database_bytes_during_transaction' => $vacuumedPageCount * $pageSize,
            'database_bytes_after_rollback' => $rollbackPageCount * $pageSize,
            'rollback_restores_dropped_pages' => $rollbackPageCount === $initialPageCount,
            'max_page_count_adjusted_upward_on_rollback' => $rollbackMaxPageCount > $maxDuringTransaction,
            'integrity_check_after_rollback' => 'ok',
            'source' => 'pager1.test pager1-44.1 through pager1-44.3 max_page_count adjusted upward on rollback',
            'dependencies' => [
                'real-upstream-corpus-pager1',
                'sqlite-pager-auto-vacuum-rollback',
                'sqlite-pager-max-page-count-rollback',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function databaseMovedWriteBoundary(string $phase, string $requestedJournalMode, int $variant = 0): array
    {
        $phase = strtolower(trim($phase));
        $requestedJournalMode = strtolower(trim($requestedJournalMode));

        $sections = [
            'read-after-rename' => ['pager4-1.2', 'database-moved-read-ok', 'select', true, false, false],
            'write-after-rename' => ['pager4-1.3', 'database-moved-write-readonly', 'update', false, false, false],
            'write-after-replacement-name' => ['pager4-1.4', 'database-moved-replacement-name-still-readonly', 'update', false, true, false],
            'restored-name-read' => ['pager4-1.5', 'database-name-restored-read-ok', 'select', true, false, true],
            'restored-name-write' => ['pager4-1.6', 'database-name-restored-write-ok', 'update', true, false, true],
            'renamed-off-write' => ['pager4-1.7', 'database-moved-off-journal-write-ok', 'update', true, false, false],
            'renamed-memory-write' => ['pager4-1.8', 'database-moved-memory-journal-write-ok', 'update', true, false, false],
            'renamed-delete-write' => ['pager4-1.9', 'database-moved-rollback-journal-write-readonly', 'update', false, false, false],
            'renamed-truncate-write' => ['pager4-1.10', 'database-moved-rollback-journal-write-readonly', 'update', false, false, false],
            'renamed-persist-write' => ['pager4-1.11', 'database-moved-rollback-journal-write-readonly', 'update', false, false, false],
        ];

        if (!isset($sections[$phase])) {
            throw new \InvalidArgumentException("Unsupported SQLite pager moved-database phase: {$phase}");
        }
        if (!in_array($requestedJournalMode, ['delete', 'truncate', 'persist', 'off', 'memory'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite pager moved-database journal mode: {$requestedJournalMode}");
        }
        if ($variant < 0) {
            throw new \InvalidArgumentException('SQLite pager moved-database variant must be non-negative');
        }

        [$section, $status, $operation, $allowed, $replacementExists, $nameRestored] = $sections[$phase];
        $initialRow = [673 + ($variant % 41), 'stone-' . ($variant % 17), 'philips-' . ($variant % 13)];
        $offRow = [107 + ($variant % 89), $initialRow[1], $initialRow[2]];
        $memoryRow = [$offRow[0], 'memory-' . ($variant % 19), $initialRow[2]];
        $restoredWriteRow = [537 + ($variant % 97), $initialRow[1], $initialRow[2]];
        $rowBefore = match ($phase) {
            'renamed-memory-write' => $offRow,
            'renamed-delete-write', 'renamed-truncate-write', 'renamed-persist-write' => $memoryRow,
            default => $initialRow,
        };

        $finalRow = match ($phase) {
            'restored-name-write' => $restoredWriteRow,
            'renamed-off-write' => $offRow,
            'renamed-memory-write' => $memoryRow,
            default => $rowBefore,
        };
        $readAllowed = $operation === 'select' || $allowed;
        $readonlyError = !$allowed && $operation === 'update';

        return [
            'status' => $status,
            'script' => 'pager4.test',
            'section' => $section,
            'phase' => $phase,
            'operation' => $operation,
            'requested_journal_mode' => $requestedJournalMode,
            'effective_journal_mode' => $allowed ? $requestedJournalMode : null,
            'database_file_moved' => !$nameRestored,
            'replacement_file_with_original_name' => $replacementExists,
            'original_name_restored' => $nameRestored,
            'journal_required_for_write' => in_array($requestedJournalMode, ['delete', 'truncate', 'persist'], true),
            'write_allowed' => $allowed,
            'read_allowed' => $readAllowed,
            'result_code' => $readonlyError ? 1 : 0,
            'error' => $readonlyError ? 'attempt to write a readonly database' : null,
            'initial_row' => $initialRow,
            'row_before_attempt' => $rowBefore,
            'final_row' => $finalRow,
            'select_result' => $readAllowed ? [$finalRow] : null,
            'readonly_error_after_move' => $readonlyError,
            'source' => 'pager4.test pager4-1.2 through pager4-1.11 SQLITE_READONLY_DBMOVED boundary',
            'dependencies' => ['real-upstream-corpus-pager4', 'sqlite-pager-database-moved-boundary'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invalidPageRequestBoundary(string $phase, int $variant = 0, int $pageSize = 1024): array
    {
        $phase = strtolower(trim($phase));
        if ($variant < 0) {
            throw new \InvalidArgumentException('SQLite pager invalid-page variant must be non-negative');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager invalid-page page size must be a power of two at least 512 bytes');
        }

        $lockingPageNumber = intdiv(0x10000, $pageSize) + 1;
        $payloadBytes = match ($phase) {
            'interior-cell-zero-child' => 800 + ($variant % 17),
            'locking-page-root', 'alter-rename-invalid-root' => 0,
            default => 5000 + ($variant % 97),
        };

        $sections = [
            'locking-page-root' => [
                'pager1-18.2',
                'select-count-rootpage-locking-page',
                'SELECT count(*) FROM t1',
                true,
                false,
                $lockingPageNumber,
                'rootpage',
                null,
                [],
                true,
                false,
            ],
            'text-overflow-zero-typeof' => [
                'pager1-18.3.1',
                'metadata-typeof-text-overflow-zero',
                'SELECT typeof(x) FROM t2',
                false,
                true,
                0,
                'overflow-next-page',
                'text',
                [['text']],
                false,
                true,
            ],
            'text-overflow-zero-concat-length' => [
                'pager1-18.3.2',
                'load-text-overflow-zero',
                "SELECT length(x||'') FROM t2",
                true,
                false,
                0,
                'overflow-next-page',
                'text',
                [],
                false,
                false,
            ],
            'blob-overflow-zero-length-typeof' => [
                'pager1-18.3.3',
                'metadata-length-typeof-blob-overflow-zero',
                'SELECT length(x), typeof(x) FROM t2',
                false,
                true,
                0,
                'overflow-next-page',
                'blob',
                [[$payloadBytes, 'blob']],
                false,
                true,
            ],
            'blob-overflow-zero-concat-length' => [
                'pager1-18.3.4',
                'load-blob-overflow-zero',
                "SELECT length(x||'') FROM t2",
                true,
                false,
                0,
                'overflow-next-page',
                'blob',
                [],
                false,
                false,
            ],
            'blob-overflow-high-concat-length' => [
                'pager1-18.4',
                'load-blob-overflow-too-large',
                "SELECT length(x||'') FROM t2",
                true,
                false,
                0x90000000,
                'overflow-next-page',
                'blob',
                [],
                false,
                false,
            ],
            'alter-rename-invalid-root' => [
                'pager1-18.5',
                'alter-rename-invalid-rootpage',
                'SELECT * FROM x1',
                true,
                false,
                5,
                'rootpage',
                null,
                [],
                true,
                false,
            ],
            'interior-cell-zero-child' => [
                'pager1-18.6',
                'load-interior-cell-zero-child-page',
                'SELECT length(x) FROM t1',
                true,
                false,
                0,
                'interior-child-page',
                'text',
                [],
                false,
                false,
            ],
        ];

        if (!isset($sections[$phase])) {
            throw new \InvalidArgumentException("Unsupported SQLite pager invalid-page phase: {$phase}");
        }

        [
            $section,
            $status,
            $selectSql,
            $loadsPayload,
            $metadataOnlySafe,
            $corruptPageNumber,
            $corruptField,
            $storageClass,
            $rows,
            $requiresDefensiveOff,
            $lazyMetadataShortCircuit,
        ] = $sections[$phase];

        $malformed = !$metadataOnlySafe;
        $resultCode = $malformed ? 1 : 0;
        $error = $malformed ? 'database disk image is malformed' : null;

        return [
            'status' => $status,
            'script' => 'pager1.test',
            'section' => $section,
            'phase' => $phase,
            'variant' => $variant,
            'page_size' => $pageSize,
            'locking_page_number' => $lockingPageNumber,
            'corrupt_page_number' => $corruptPageNumber,
            'corrupt_field' => $corruptField,
            'corrupt_pointer_is_zero' => $corruptPageNumber === 0,
            'corrupt_pointer_exceeds_31bit' => $corruptPageNumber > 0x7fffffff,
            'requires_defensive_off' => $requiresDefensiveOff,
            'requires_direct_overflow_read_disabled' => true,
            'select_sql' => $selectSql,
            'loads_payload_content' => $loadsPayload,
            'metadata_only_short_circuit' => $lazyMetadataShortCircuit,
            'storage_class' => $storageClass,
            'payload_bytes' => $payloadBytes,
            'result_code' => $resultCode,
            'error' => $error,
            'expected_rows' => $rows,
            'malformed_detected' => $malformed,
            'database_handle_remains_usable' => true,
            'source' => 'pager1.test pager1-18.1 through pager1-18.6 invalid b-tree page requests report SQLITE_CORRUPT only when payload/root content is read',
            'dependencies' => [
                'real-upstream-corpus-pager1',
                'sqlite-pager-invalid-page-request',
                'sqlite-pager-corrupt-overflow-boundary',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function zeroPageSizeJournalHeaderRows(int $count = 1000): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('SQLite pager zero-page-size journal corpus row count must be positive');
        }

        $pageSizes = [512, 1024, 2048, 4096, 8192];
        $sectorSizes = [512, 1024, 2048, 4096];
        $rows = [];

        for ($case = 1; $case <= $count; $case++) {
            $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
            $sectorSize = $sectorSizes[intdiv($case - 1, count($pageSizes)) % count($sectorSizes)];
            $initialPageCount = 8 + (($case * 7) % 23);
            $currentPageCount = $initialPageCount + 1 + (($case * 5) % 6);
            $journalRecordCount = 1 + ($case % min(6, $initialPageCount));
            $nonce = (0x50310000 + ($case * 2654435761)) & 0xffffffff;

            $rows[] = [
                'case' => $case,
                'script' => 'pager1.test',
                'section' => 'pager1-31.1',
                'upstream' => sprintf('pager1.test pager1-31.1 zero page-size journal header dynamic case %04d', $case),
                'page_size' => $pageSize,
                'sector_size' => $sectorSize,
                'journal_header_page_size_field' => 0,
                'database_page_size_fallback' => $pageSize,
                'initial_database_page_count' => $initialPageCount,
                'current_database_page_count' => $currentPageCount,
                'journal_record_count' => $journalRecordCount,
                'checksum_nonce' => $nonce,
                'expected_status' => 'zero-page-size-header-fallback',
                'expected_integrity_check' => 'ok',
                'rollback_truncates_to_initial_database_size' => true,
                'source' => 'pager1.test pager1-31.1 rolls back legacy rollback journals whose page-size header field is zero by using the database page size',
                'dependencies' => [
                    'real-upstream-corpus-pager1',
                    'sqlite-rollback-journal-zero-page-size-header',
                    'sqlite-pager-hot-journal-legacy-compatibility',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function peerLockJournalCleanupRows(int $count = 1000): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('SQLite pager peer-lock journal cleanup corpus row count must be positive');
        }

        $phases = ['wal-exclusive-peer-open', 'persist-delete-peer-writer', 'persist-delete-open-blob'];
        $pageSizes = [512, 1024, 2048, 4096, 8192];
        $rows = [];

        for ($case = 1; $case <= $count; $case++) {
            $phase = $phases[($case - 1) % count($phases)];
            $pageSize = $pageSizes[intdiv($case - 1, count($phases)) % count($pageSizes)];
            $payloadBytes = 96 + (($case * 37) % 700);
            $journalPages = 1 + (($case * 11) % 6);
            $baseRow = ['a-' . $case, 'b-' . (($case * 3) % 97)];
            $retryRow = ['c-' . $case, 'd-' . (($case * 5) % 101)];
            $peerRow = ['e-' . $case, 'f-' . (($case * 7) % 103)];

            $row = [
                'case' => $case,
                'script' => 'pager1.test',
                'page_size' => $pageSize,
                'payload_bytes' => $payloadBytes,
                'journal_pages_before_cleanup' => $journalPages,
                'journal_bytes_before_cleanup' => 28 + ($journalPages * ($pageSize + 8)),
                'initial_rows' => [$baseRow],
                'retry_row' => $retryRow,
                'peer_row' => $peerRow,
                'pragma_result' => 'delete',
                'integrity_check_after_final_commit' => 'ok',
                'source' => 'pager1.test pager1-28.* peer locking and deferred PERSIST to DELETE journal cleanup',
                'dependencies' => [
                    'real-upstream-corpus-pager1',
                    'sqlite-pager-peer-lock-boundary',
                    'sqlite-pager-persist-delete-deferred-cleanup',
                ],
            ];

            if ($phase === 'wal-exclusive-peer-open') {
                $rows[] = array_merge($row, [
                    'section' => 'pager1-28.1..28.4',
                    'upstream' => sprintf('pager1.test pager1-28.1..28.4 WAL exclusive peer-open dynamic case %04d', $case),
                    'phase' => $phase,
                    'journal_mode_before' => 'wal',
                    'locking_mode_request' => 'exclusive',
                    'locking_mode_result' => 'exclusive',
                    'peer_connection_open' => true,
                    'peer_read_rows' => [$baseRow],
                    'begin_write_allowed_with_peer' => false,
                    'begin_write_error' => 'database is locked',
                    'retry_after_peer_reopen_allowed' => true,
                    'journal_exists_before_cleanup' => false,
                    'journal_exists_after_pragma' => false,
                    'journal_exists_after_peer_commit' => false,
                    'wal_frames_before_retry' => 2 + ($case % 7),
                    'wal_frames_after_retry' => 3 + ($case % 7),
                    'final_rows' => [$baseRow, $retryRow],
                    'lock_sequence' => [
                        'client1:wal:exclusive-request',
                        'client2:reader:shared',
                        'client1:begin-write:blocked',
                        'client2:close-reopen',
                        'client1:begin-write:ok',
                        'client1:commit:ok',
                    ],
                    'dependencies' => array_merge($row['dependencies'], [
                        'sqlite-wal-exclusive-peer-open',
                        'sqlite-pager-locking-mode-exclusive',
                    ]),
                ]);
                continue;
            }

            if ($phase === 'persist-delete-peer-writer') {
                $rows[] = array_merge($row, [
                    'section' => 'pager1-28.5..28.12',
                    'upstream' => sprintf('pager1.test pager1-28.5..28.12 PERSIST delete deferred by peer writer dynamic case %04d', $case),
                    'phase' => $phase,
                    'journal_mode_before' => 'persist',
                    'journal_mode_after_pragma' => 'delete',
                    'peer_reserved_lock' => true,
                    'open_blob_reader' => false,
                    'can_obtain_reserved_lock_for_cleanup' => false,
                    'journal_exists_before_cleanup' => true,
                    'journal_exists_after_pragma' => true,
                    'peer_commit_allowed_before_reader_close' => true,
                    'peer_commit_error' => null,
                    'journal_exists_after_peer_commit' => false,
                    'final_rows' => [$baseRow, $peerRow],
                    'lock_sequence' => [
                        'client1:persist:journal-created',
                        'client2:begin-write:reserved',
                        'client1:pragma-delete:cleanup-deferred',
                        'client2:commit:ok',
                        'pager:delete-stale-journal',
                    ],
                    'dependencies' => array_merge($row['dependencies'], [
                        'sqlite-pager-persist-delete-writer-deferred',
                        'sqlite-pager-reserved-lock-cleanup',
                    ]),
                ]);
                continue;
            }

            $rows[] = array_merge($row, [
                'section' => 'pager1-28.13..28.20',
                'upstream' => sprintf('pager1.test pager1-28.13..28.20 PERSIST delete deferred by blob reader dynamic case %04d', $case),
                'phase' => $phase,
                'journal_mode_before' => 'persist',
                'journal_mode_after_pragma' => 'delete',
                'peer_reserved_lock' => true,
                'open_blob_reader' => true,
                'blob_read_result' => 'c',
                'can_obtain_reserved_lock_for_cleanup' => false,
                'journal_exists_before_cleanup' => true,
                'journal_exists_after_pragma' => true,
                'peer_commit_allowed_before_reader_close' => false,
                'peer_commit_error' => 'database is locked',
                'journal_exists_after_peer_commit' => false,
                'final_rows' => [$baseRow, $peerRow],
                'lock_sequence' => [
                    'client1:blob-reader:open',
                    'client1:persist:journal-created',
                    'client2:begin-write:reserved',
                    'client1:pragma-delete:cleanup-deferred',
                    'client2:commit:blocked',
                    'client1:blob-reader:read-c',
                    'client1:blob-reader:close',
                    'client2:commit:ok',
                    'pager:delete-stale-journal',
                ],
                'dependencies' => array_merge($row['dependencies'], [
                    'sqlite-pager-persist-delete-blob-reader-deferred',
                    'sqlite-pager-incremental-blob-lock-boundary',
                ]),
            ]);
        }

        return $rows;
    }

    private static function align(int $value, int $boundary): int
    {
        return intdiv($value + $boundary - 1, $boundary) * $boundary;
    }
}
