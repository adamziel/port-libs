<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalWalCacheCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $masterRecoveredPages
     * @param array<int,array{image:string,source?:string,dirty?:bool,frame?:int}> $walCachePages
     * @param array<int,string> $walAppendPages
     * @param list<int> $checkpointPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $masterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $masterRecoveredPages,
        array $walCachePages,
        array $walAppendPages,
        array $checkpointPages,
        bool $refreshStaleCache = true,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal WAL-cache next129 requires database and master-journal paths');
        }
        if ($masterJournalBytes === null || $masterJournalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal WAL-cache next129 requires master-journal bytes');
        }
        if (!str_contains($masterJournalBytes, $databasePath . '-journal')) {
            throw new \RuntimeException('SQLite pager master-journal WAL-cache next129 master journal does not reference the database journal');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal WAL-cache next129 requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal WAL-cache next129 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal WAL-cache next129 database bytes must be page-size aligned');
        }
        if ($masterRecoveredPages === [] || $walCachePages === [] || $walAppendPages === [] || $checkpointPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal WAL-cache next129 requires recovered, cache, append, and checkpoint pages');
        }

        $database = self::sourceMap($databaseBytes, $pageSize, 'database-before-master-journal-recovery');
        $masterRecoveredPages = self::normalizeImages($masterRecoveredPages, $pageSize, 'master-recovered');
        $walAppendPages = self::normalizeImages($walAppendPages, $pageSize, 'wal-append');
        $walCachePages = self::normalizeCache($walCachePages, $pageSize);
        $checkpointPages = self::normalizePageList($checkpointPages, 'checkpoint');

        $operations = [[
            'op' => 'read_master_journal',
            'path' => $masterJournalPath,
            'bytes' => strlen($masterJournalBytes),
            'reason' => 'master_journal_names_database_rollback_journal_before_wal_cache_reuse',
        ]];

        foreach ($masterRecoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal WAL-cache next129 recovered page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-recovered-current-source',
            ];
            $operations[] = [
                'op' => 'restore_master_journal_page',
                'page_number' => $pageNumber,
                'reason' => 'recover_database_current_source_before_reusing_wal_cache',
            ];
        }

        $cacheRows = [];
        $stalePages = [];
        $refreshedPages = [];
        $retainedPages = [];
        foreach ($walCachePages as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal WAL-cache next129 cache page {$pageNumber} is outside the recovered database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $stale = $entry['image'] !== $currentImage;
            $sourceBefore = $entry['source'];
            $sourceAfter = $sourceBefore;
            $imageAfter = $entry['image'];
            if ($stale) {
                $stalePages[] = $pageNumber;
                if ($refreshStaleCache) {
                    $sourceAfter = 'master-journal-refreshed-wal-cache';
                    $imageAfter = $currentImage;
                    $refreshedPages[] = $pageNumber;
                    $operations[] = [
                        'op' => 'refresh_wal_cache_page',
                        'page_number' => $pageNumber,
                        'reason' => 'cached_page_predates_master_journal_recovery',
                    ];
                } else {
                    $sourceAfter = 'stale-wal-cache-blocked';
                    $operations[] = [
                        'op' => 'block_stale_wal_cache_page',
                        'page_number' => $pageNumber,
                        'reason' => 'cached_page_predates_master_journal_recovery',
                    ];
                }
            } else {
                $retainedPages[] = $pageNumber;
                $operations[] = [
                    'op' => 'retain_wal_cache_page',
                    'page_number' => $pageNumber,
                    'reason' => 'cached_page_matches_master_recovered_current_source',
                ];
            }

            $cacheRows[$pageNumber] = [
                'page_number' => $pageNumber,
                'frame' => $entry['frame'],
                'dirty' => $entry['dirty'],
                'source_before' => $sourceBefore,
                'source_after' => $sourceAfter,
                'stale_before_refresh' => $stale,
                'refreshed' => $stale && $refreshStaleCache,
                'before_prefix' => self::label($entry['image']),
                'current_prefix' => self::label($currentImage),
                'after_prefix' => self::label($imageAfter),
            ];
        }

        if ($stalePages !== [] && !$refreshStaleCache) {
            return [
                'status' => 'pager-master-journal-wal-cache-blocked-current-source-next129',
                'reason' => 'stale_wal_cache_pages_predate_master_journal_recovery',
                'database_path' => $databasePath,
                'master_journal_path' => $masterJournalPath,
                'page_size' => $pageSize,
                'current_source_verified' => false,
                'stale_cache_page_numbers' => $stalePages,
                'refreshed_cache_page_numbers' => [],
                'retained_cache_page_numbers' => $retainedPages,
                'cache_rows' => array_values($cacheRows),
                'operations' => $operations,
                'dependencies' => self::dependencies(),
            ];
        }

        foreach ($walAppendPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal WAL-cache next129 append page {$pageNumber} is outside the recovered database image");
            }
            if (!isset($walCachePages[$pageNumber])) {
                throw new \RuntimeException("SQLite pager master-journal WAL-cache next129 append page {$pageNumber} needs a refreshed cache source");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'wal-append-after-master-cache-refresh',
            ];
            $operations[] = [
                'op' => 'append_wal_frame_from_refreshed_cache',
                'page_number' => $pageNumber,
                'reason' => 'wal_append_uses_master_recovered_current_source',
            ];
        }

        foreach ($checkpointPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal WAL-cache next129 checkpoint page {$pageNumber} is outside the recovered database image");
            }
            $operations[] = [
                'op' => 'checkpoint_page_from_current_source',
                'page_number' => $pageNumber,
                'source' => $database[$pageNumber]['source'],
                'reason' => 'checkpoint_uses_refreshed_wal_cache_or_recovered_database_source',
            ];
        }

        return [
            'status' => 'pager-master-journal-wal-cache-current-source-next129',
            'reason' => 'master_journal_recovery_invalidates_stale_wal_cache_before_next_append_checkpoint',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_source_verified' => $stalePages === $refreshedPages,
            'master_recovered_page_numbers' => array_keys($masterRecoveredPages),
            'stale_cache_page_numbers' => $stalePages,
            'refreshed_cache_page_numbers' => $refreshedPages,
            'retained_cache_page_numbers' => $retainedPages,
            'wal_append_page_numbers' => array_keys($walAppendPages),
            'checkpoint_page_numbers' => $checkpointPages,
            'cache_rows' => array_values($cacheRows),
            'final_prefixes' => self::prefixes($database),
            'final_sources' => self::sources($database),
            'final_database_bytes' => self::sourceBytes($database, $pageSize),
            'operations' => $operations,
            'source_digest' => hash('sha256', implode('|', self::sources($database))),
            'dependencies' => self::dependencies(),
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizeImages(array $pages, int $pageSize, string $label): array
    {
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal WAL-cache next129 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal WAL-cache next129 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source?:string,dirty?:bool,frame?:int}> $pages
     * @return array<int,array{image:string,source:string,dirty:bool,frame:int|null}>
     */
    private static function normalizeCache(array $pages, int $pageSize): array
    {
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal WAL-cache next129 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal WAL-cache next129 cache page {$pageNumber} image must match page size");
            }
            $frame = $entry['frame'] ?? null;
            if ($frame !== null && (!is_int($frame) || $frame < 1)) {
                throw new \InvalidArgumentException("SQLite pager master-journal WAL-cache next129 cache page {$pageNumber} frame must be a positive integer");
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source' => isset($entry['source']) && is_string($entry['source']) && $entry['source'] !== '' ? $entry['source'] : 'wal-cache-before-master-recovery',
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'frame' => $frame,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<int> $pages
     * @return list<int>
     */
    private static function normalizePageList(array $pages, string $label): array
    {
        $normalized = [];
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal WAL-cache next129 {$label} pages must be one-based integers");
            }
            $normalized[] = $pageNumber;
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize, string $source): array
    {
        $map = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $map[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'source' => $source,
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

    /**
     * @return list<string>
     */
    private static function dependencies(): array
    {
        return [
            'sqlite-pager-master-journal-wal-cache-current-source-next129',
            'sqlite-master-journal-recovery-before-wal-cache-reuse',
            'sqlite-wal-cache-stale-page-invalidation',
            'sqlite-checkpoint-current-source-after-master-journal',
        ];
    }
}
