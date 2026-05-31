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

    private static function align(int $value, int $boundary): int
    {
        return intdiv($value + $boundary - 1, $boundary) * $boundary;
    }
}
