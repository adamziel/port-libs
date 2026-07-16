<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalCacheSpillCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $masterRecoveredPages
     * @param array<int,array{image:string,before?:string,journaled?:bool,dirty?:bool,pinned?:bool,source?:string,bytes?:int}> $cachePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $masterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $masterRecoveredPages,
        array $cachePages,
        int $cacheSize,
        int $spillThreshold,
        string $journalMode = 'delete',
        string $lockState = 'reserved',
        bool $refreshStaleCache = true,
        ?int $maxSpillPages = null,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 requires database and master-journal paths');
        }
        if ($masterJournalBytes === null || $masterJournalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 requires master-journal bytes');
        }
        if (!str_contains($masterJournalBytes, $databasePath . '-journal')) {
            throw new \RuntimeException('SQLite pager master-journal cache-spill next132 master journal does not reference the database journal');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 database bytes must be page-size aligned');
        }
        if ($cacheSize < 0 || $spillThreshold < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 cache size and threshold are invalid');
        }
        if ($maxSpillPages !== null && $maxSpillPages < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 max spill pages must be positive');
        }

        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'wal', 'memory'], true)) {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 journal mode is invalid');
        }
        $lockState = strtolower(trim($lockState));
        if (!in_array($lockState, ['none', 'shared', 'reserved', 'pending', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 lock state is invalid');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $masterRecoveredPages = self::normalizeImages($masterRecoveredPages, $pageSize, 'master recovered');
        $cachePages = self::normalizeCache($cachePages, $pageSize);

        $operations = [[
            'op' => 'read_master_journal',
            'path' => $masterJournalPath,
            'bytes' => strlen($masterJournalBytes),
            'reason' => 'master_journal_controls_cache_spill_current_source',
        ]];

        foreach ($masterRecoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal cache-spill next132 recovered page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = ['image' => $image, 'source' => 'master-journal-recovered-current-source'];
            $operations[] = [
                'op' => 'restore_master_journal_page',
                'page_number' => $pageNumber,
                'reason' => 'recover_current_source_before_cache_spill',
            ];
        }

        $rows = [];
        $spilledPages = [];
        $deferredPages = [];
        $refreshedPages = [];
        $retainedPages = [];
        $blockedReasons = [];
        $spillCandidates = [];
        $cacheBelowThreshold = $cacheSize < $spillThreshold;
        $canWrite = in_array($lockState, ['reserved', 'pending', 'exclusive'], true);

        foreach ($cachePages as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal cache-spill next132 cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $before = $entry['before'] ?? $currentImage;
            if ($before !== $currentImage) {
                $deferredPages[] = $pageNumber;
                $blockedReasons[] = 'cache_before_image_predates_master_journal_recovery';
                $operations[] = [
                    'op' => 'defer_cache_spill',
                    'page_number' => $pageNumber,
                    'reason' => 'cache_before_image_predates_master_journal_recovery',
                ];
            } elseif ($entry['image'] !== $currentImage && $refreshStaleCache) {
                $refreshedPages[] = $pageNumber;
                $entry['image'] = $currentImage;
                $entry['source'] = 'master-journal-refreshed-cache-before-spill';
                $operations[] = [
                    'op' => 'refresh_cache_page',
                    'page_number' => $pageNumber,
                    'reason' => 'cache_image_predates_master_journal_recovery',
                ];
            } elseif ($entry['image'] !== $currentImage) {
                $deferredPages[] = $pageNumber;
                $blockedReasons[] = 'stale_cache_image_not_refreshed';
                $operations[] = [
                    'op' => 'defer_cache_spill',
                    'page_number' => $pageNumber,
                    'reason' => 'stale_cache_image_not_refreshed',
                ];
            } else {
                $retainedPages[] = $pageNumber;
                $operations[] = [
                    'op' => 'retain_cache_page',
                    'page_number' => $pageNumber,
                    'reason' => 'cache_page_matches_master_recovered_current_source',
                ];
            }

            $eligible = $entry['dirty'] && $entry['journaled'] && !$entry['pinned'] && !in_array($pageNumber, $deferredPages, true);
            if ($eligible) {
                $spillCandidates[] = [
                    'page_number' => $pageNumber,
                    'bytes' => $entry['bytes'],
                    'image' => $entry['image'],
                ];
            }

            $rows[$pageNumber] = [
                'page_number' => $pageNumber,
                'dirty' => $entry['dirty'],
                'journaled' => $entry['journaled'],
                'pinned' => $entry['pinned'],
                'eligible_for_spill' => $eligible,
                'before_matches_current_source' => $before === $currentImage,
                'image_matches_current_source' => $entry['image'] === $currentImage,
                'source_after' => $entry['source'],
                'current_prefix' => self::label($currentImage),
                'cache_prefix' => self::label($entry['image']),
            ];
        }

        if ($cacheBelowThreshold) {
            $blockedReasons[] = 'cache_below_spill_threshold';
        }
        if (!$canWrite) {
            $blockedReasons[] = 'exclusive_lock_unavailable';
        }
        if ($spillCandidates === []) {
            $blockedReasons[] = 'no_master_journal_protected_dirty_pages';
        }

        $blockedReasons = array_values(array_unique($blockedReasons));
        if (!$cacheBelowThreshold && $canWrite && $spillCandidates !== []) {
            usort($spillCandidates, static fn (array $left, array $right): int => $left['page_number'] <=> $right['page_number']);
            $spillCandidates = array_slice($spillCandidates, 0, $maxSpillPages ?? count($spillCandidates));
            if ($lockState !== 'exclusive') {
                $operations[] = [
                    'op' => 'promote_lock',
                    'from' => $lockState,
                    'to' => 'exclusive',
                    'reason' => 'cache_spill_after_master_journal_requires_exclusive_lock',
                ];
            }
            foreach ($spillCandidates as $candidate) {
                $spilledPages[] = $candidate['page_number'];
                $op = $journalMode === 'wal' ? 'append_wal_frame_after_master_journal' : 'write_database_page_after_master_journal';
                $target = $journalMode === 'wal' ? 'wal' : 'database';
                $operations[] = [
                    'op' => $op,
                    'page_number' => $candidate['page_number'],
                    'bytes' => $candidate['bytes'],
                    'target' => $target,
                    'reason' => 'spill_master_journal_protected_dirty_page_from_current_source',
                ];
                if ($journalMode !== 'wal') {
                    $database[$candidate['page_number']] = [
                        'image' => $candidate['image'],
                        'source' => 'cache-spill-after-master-journal',
                    ];
                }
                $operations[] = [
                    'op' => 'mark_page_clean_in_cache',
                    'page_number' => $candidate['page_number'],
                    'reason' => 'master_journal_cache_spill_completed',
                ];
            }
        }

        $status = $spilledPages === [] ? 'pager-master-journal-cache-spill-deferred-current-source-next132' : 'pager-master-journal-cache-spill-current-source-next132';

        return [
            'status' => $status,
            'reason' => $spilledPages === []
                ? 'master_journal_recovery_left_no_safe_cache_spill_pages'
                : 'master_journal_recovery_refreshes_cache_before_safe_spill',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'cache_size_before' => $cacheSize,
            'cache_size_after' => max(0, $cacheSize - count($spilledPages)),
            'spill_threshold' => $spillThreshold,
            'lock_before' => $lockState,
            'lock_after' => $spilledPages === [] ? $lockState : 'exclusive',
            'master_recovered_page_numbers' => array_keys($masterRecoveredPages),
            'refreshed_cache_page_numbers' => $refreshedPages,
            'retained_cache_page_numbers' => $retainedPages,
            'deferred_cache_page_numbers' => array_values(array_unique($deferredPages)),
            'spilled_page_numbers' => $spilledPages,
            'blocked_reasons' => $spilledPages === [] ? $blockedReasons : array_values(array_diff($blockedReasons, ['no_master_journal_protected_dirty_pages'])),
            'cache_rows' => array_values($rows),
            'operations' => $operations,
            'final_prefixes' => self::prefixes($database),
            'final_sources' => self::sources($database),
            'final_database_bytes' => self::sourceBytes($database, $pageSize),
            'source_digest' => hash('sha256', implode('|', self::sources($database)) . '|' . implode(',', $spilledPages)),
            'dependencies' => [
                'sqlite-pager-master-journal-cache-spill-current-source-next132',
                'sqlite-master-journal-recovery-before-cache-spill',
                'sqlite-pager-cache-spill-journalmode-current-source-next107',
                'sqlite-pager-master-journal-wal-cache-current-source-next129',
            ],
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizeImages(array $pages, int $pageSize, string $label): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException("SQLite pager master-journal cache-spill next132 {$label} pages are required");
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal cache-spill next132 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal cache-spill next132 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,before?:string,journaled?:bool,dirty?:bool,pinned?:bool,source?:string,bytes?:int}> $pages
     * @return array<int,array{image:string,before?:string,journaled:bool,dirty:bool,pinned:bool,source:string,bytes:int}>
     */
    private static function normalizeCache(array $pages, int $pageSize): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 cache pages are required');
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal cache-spill next132 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal cache-spill next132 cache page {$pageNumber} image must match page size");
            }
            $before = $entry['before'] ?? null;
            if ($before !== null && (!is_string($before) || strlen($before) !== $pageSize)) {
                throw new \InvalidArgumentException("SQLite pager master-journal cache-spill next132 cache page {$pageNumber} before image must match page size");
            }
            $bytes = $entry['bytes'] ?? $pageSize;
            if (!is_int($bytes) || $bytes < 0) {
                throw new \InvalidArgumentException("SQLite pager master-journal cache-spill next132 cache page {$pageNumber} bytes must be non-negative");
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'before' => $before,
                'journaled' => (bool) ($entry['journaled'] ?? false),
                'dirty' => (bool) ($entry['dirty'] ?? true),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'source' => isset($entry['source']) && is_string($entry['source']) && $entry['source'] !== '' ? $entry['source'] : 'dirty-cache-before-master-journal-recovery',
                'bytes' => $bytes,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize): array
    {
        $map = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $map[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'source' => 'database-before-master-journal-recovery',
            ];
        }

        return $map;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     */
    private static function sourceBytes(array $source, int $pageSize): string
    {
        ksort($source, SORT_NUMERIC);
        $bytes = '';
        $maxPage = max(array_keys($source));
        for ($pageNumber = 1; $pageNumber <= $maxPage; $pageNumber++) {
            $bytes .= $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
        }

        return $bytes;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @return array<int,string>
     */
    private static function prefixes(array $source): array
    {
        ksort($source, SORT_NUMERIC);
        $prefixes = [];
        foreach ($source as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::label($entry['image']);
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @return array<int,string>
     */
    private static function sources(array $source): array
    {
        ksort($source, SORT_NUMERIC);
        $sources = [];
        foreach ($source as $pageNumber => $entry) {
            $sources[$pageNumber] = $entry['source'];
        }

        return $sources;
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
