<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerCacheSpillWalRecoveryCurrentSourceNextPlan
{
    /**
     * @param list<array{page:int,image:string,bytes?:int,dirty?:bool,pinned?:bool,current_image?:string}> $cachePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        string $walBytes,
        array $cachePages,
        int $cacheSize,
        int $spillThreshold,
        ?int $currentReaderEndFrame = null,
        ?int $maxSpillPages = null,
        bool $cacheSpillEnabled = true,
        ?int $databasePageSize = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL recovery current-source requires a database path');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL recovery current-source requires database bytes');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL recovery current-source requires WAL bytes');
        }
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL recovery current-source requires cache pages');
        }
        if ($currentReaderEndFrame !== null && $currentReaderEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL recovery current-source reader frame must not be negative');
        }

        $boundary = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $databasePageSize);
        $wal = $boundary['wal'];
        $committedWal = $boundary['committed_wal'];
        $pageSize = $wal->header->pageSize !== 0
            ? $wal->header->pageSize
            : ($databasePageSize ?? SQLiteHeader::parse($databaseBytes)->pageSize);
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL recovery current-source database bytes must be page-size aligned');
        }

        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        $committedFramesByPage = self::latestFramesByPage($committedWal);
        $validFramesByPage = self::latestFramesByPage($wal);
        $currentDatabaseBytes = $boundary['checkpoint_database_bytes'] ?? $databaseBytes;
        $cachePages = self::normalizeCachePages($cachePages, $pageCount, $pageSize);

        $cacheSources = [];
        $currentSourceMismatchPages = [];
        $discardedTailSourcePages = [];
        $spillInputs = [];
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'];
            $committedFrame = $committedFramesByPage[$page] ?? null;
            $validFrame = $validFramesByPage[$page] ?? null;
            $expectedImage = $committedFrame['image'] ?? self::pageImage($currentDatabaseBytes, $page, $pageSize);
            $latestValidFrame = $validFrame['frame_index'] ?? null;
            $latestCommittedFrame = $committedFrame['frame_index'] ?? null;
            $hasDiscardedTailSource = $latestValidFrame !== null
                && ($latestCommittedFrame === null || $latestValidFrame > $latestCommittedFrame);

            if (($cachePage['current_image'] ?? $expectedImage) !== $expectedImage) {
                $currentSourceMismatchPages[] = $page;
            }
            if ($hasDiscardedTailSource) {
                $discardedTailSourcePages[] = $page;
            }

            $cacheSources[$page] = [
                'page' => $page,
                'database_prefix' => self::prefix(self::pageImage($databaseBytes, $page, $pageSize)),
                'current_source_prefix' => self::prefix($expectedImage),
                'cache_prefix' => self::prefix($cachePage['image']),
                'committed_frame' => $latestCommittedFrame,
                'latest_valid_frame' => $latestValidFrame,
                'uses_recovered_wal_frame' => $latestCommittedFrame !== null,
                'discarded_tail_frame_source' => $hasDiscardedTailSource,
                'cache_matches_current_source' => $cachePage['image'] === $expectedImage,
                'current_image_verified' => !in_array($page, $currentSourceMismatchPages, true),
            ];

            $spillInputs[] = [
                'page' => $page,
                'bytes' => $cachePage['bytes'] ?? $pageSize,
                'dirty' => $cachePage['dirty'] ?? true,
                'pinned' => $cachePage['pinned'] ?? false,
                'journaled' => !$hasDiscardedTailSource,
            ];
        }
        sort($currentSourceMismatchPages, SORT_NUMERIC);
        sort($discardedTailSourcePages, SORT_NUMERIC);
        ksort($cacheSources, SORT_NUMERIC);

        $readerEndFrame = $currentReaderEndFrame ?? $boundary['committed_frame_count'];
        if ($readerEndFrame > $boundary['valid_frame_count']) {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL recovery current-source reader frame is past the valid WAL prefix');
        }
        $readerPinsDiscardedTail = $readerEndFrame > $boundary['committed_frame_count'];

        $spill = SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext107(
            $pageCount,
            $cacheSize,
            $spillThreshold,
            $spillInputs,
            'wal',
            true,
            'exclusive',
            $cacheSpillEnabled && $currentSourceMismatchPages === [] && $discardedTailSourcePages === [],
            $maxSpillPages
        );
        if ($currentSourceMismatchPages !== [] && !in_array('wal_recovery_current_source_mismatch', $spill['blocked_reasons'], true)) {
            $spill['blocked_reasons'][] = 'wal_recovery_current_source_mismatch';
        }
        if ($discardedTailSourcePages !== [] && !in_array('wal_uncommitted_tail_discarded_before_cache_spill', $spill['blocked_reasons'], true)) {
            $spill['blocked_reasons'][] = 'wal_uncommitted_tail_discarded_before_cache_spill';
        }

        $spilledPages = $spill['next']['spilled_pages'] ?? [];
        $walResetBlockedReasons = [];
        if ($readerPinsDiscardedTail) {
            $walResetBlockedReasons[] = 'reader_pins_valid_uncommitted_tail';
        }
        if (($boundary['discarded_corrupt_tail_frame_count'] ?? 0) > 0) {
            $walResetBlockedReasons[] = 'corrupt_tail_requires_recovery_prefix_preservation_until_sync';
        }

        $recovered = $currentSourceMismatchPages === []
            && $discardedTailSourcePages === []
            && ($spill['status'] ?? null) === 'spilled'
            && $spilledPages !== [];

        return [
            'status' => $recovered
                ? 'pager_cache_spill_wal_recovery_current_source_next135'
                : 'pager_cache_spill_wal_recovery_current_source_blocked_next135',
            'reason' => $recovered
                ? 'wal_committed_prefix_recovered_before_cache_spill'
                : 'cache_spill_blocked_until_wal_recovery_current_source_is_verified',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'page_count' => $pageCount,
            'recovery' => [
                'status' => $boundary['status'],
                'reason' => $boundary['reason'],
                'valid_frame_count' => $boundary['valid_frame_count'],
                'committed_frame_count' => $boundary['committed_frame_count'],
                'discarded_valid_tail_frame_count' => $boundary['discarded_valid_tail_frame_count'],
                'discarded_corrupt_tail_frame_count' => $boundary['discarded_corrupt_tail_frame_count'],
                'committed_end_offset' => $boundary['committed_end_offset'],
                'recovery_end_offset' => $boundary['recovery_end_offset'],
            ],
            'current_reader_end_frame' => $readerEndFrame,
            'reader_pins_discarded_tail' => $readerPinsDiscardedTail,
            'wal_reset_blocked' => $walResetBlockedReasons !== [],
            'wal_reset_blocked_reasons' => $walResetBlockedReasons,
            'current_source_verified' => $currentSourceMismatchPages === [],
            'current_source_mismatch_pages' => $currentSourceMismatchPages,
            'discarded_tail_source_pages' => $discardedTailSourcePages,
            'cache_page_sources' => array_values($cacheSources),
            'spill' => $spill,
            'spilled_page_numbers' => $spilledPages,
            'committed_wal_bytes' => $boundary['committed_wal_bytes'],
            'valid_wal_bytes' => $boundary['valid_wal_bytes'],
            'checkpoint_database_bytes' => $currentDatabaseBytes,
            'operations' => self::operations($databasePath, $boundary, $spill, $walResetBlockedReasons),
            'dependencies' => array_values(array_unique(array_merge(
                $boundary['dependencies'],
                $spill['dependencies'] ?? [],
                [
                    'sqlite-pager-cache-spill-wal-recovery-current-source-next135',
                    'sqlite-wal-transaction-recovery-boundary',
                    'sqlite-pager-cache-spill-wal-frame-routing',
                ]
            ))),
        ];
    }

    /**
     * @return array<int,array{frame_index:int,image:string}>
     */
    private static function latestFramesByPage(SQLiteWal $wal): array
    {
        $frames = [];
        foreach ($wal->frames as $frame) {
            $frames[$frame->pageNumber] = [
                'frame_index' => $frame->index,
                'image' => $frame->pageImage,
            ];
        }

        ksort($frames, SORT_NUMERIC);

        return $frames;
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,image:string,bytes?:int,dirty?:bool,pinned?:bool,current_image?:string}>
     */
    private static function normalizeCachePages(array $cachePages, int $pageCount, int $pageSize): array
    {
        $normalized = [];
        $seen = [];
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'] ?? null;
            if (!is_int($page) || $page < 1 || $page > $pageCount) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL recovery current-source cache pages must be one-based pages inside the database image');
            }
            if (isset($seen[$page])) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL recovery current-source cache pages must be unique');
            }
            $seen[$page] = true;
            $image = $cachePage['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager cache-spill WAL recovery current-source cache image for page {$page} must match page size");
            }
            if (isset($cachePage['current_image']) && (!is_string($cachePage['current_image']) || strlen($cachePage['current_image']) !== $pageSize)) {
                throw new \InvalidArgumentException("SQLite pager cache-spill WAL recovery current-source current image for page {$page} must match page size");
            }
            $bytes = $cachePage['bytes'] ?? $pageSize;
            if (!is_int($bytes) || $bytes < 0) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL recovery current-source cache bytes must be non-negative');
            }
            $cachePage['image'] = $image;
            $cachePage['bytes'] = $bytes;
            $normalized[] = $cachePage;
        }

        return $normalized;
    }

    private static function pageImage(string $databaseBytes, int $page, int $pageSize): string
    {
        return substr($databaseBytes, ($page - 1) * $pageSize, $pageSize);
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 64), ".\0 ");
    }

    /**
     * @param array<string,mixed> $boundary
     * @param array<string,mixed> $spill
     * @param list<string> $walResetBlockedReasons
     * @return list<array<string,mixed>>
     */
    private static function operations(string $databasePath, array $boundary, array $spill, array $walResetBlockedReasons): array
    {
        $operations = [[
            'op' => 'recover_wal_committed_prefix',
            'path' => $databasePath . '-wal',
            'committed_frame_count' => $boundary['committed_frame_count'],
            'valid_frame_count' => $boundary['valid_frame_count'],
            'reason' => 'select_committed_wal_frames_before_cache_spill_source_selection',
        ]];
        $operations[] = [
            'op' => $walResetBlockedReasons === [] ? 'allow_wal_reset_after_recovery_sync' : 'defer_wal_reset',
            'path' => $databasePath . '-wal',
            'reasons' => $walResetBlockedReasons,
            'reason' => $walResetBlockedReasons === [] ? 'no_reader_pins_recovered_tail' : 'reader_or_corrupt_tail_requires_wal_prefix_preservation',
        ];

        return array_values(array_merge($operations, $spill['operations'] ?? []));
    }
}
