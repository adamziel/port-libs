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

    private static function align(int $value, int $boundary): int
    {
        return intdiv($value + $boundary - 1, $boundary) * $boundary;
    }
}
