<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $masterRecoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,source?:string}> $cachePages
     * @param list<int> $nextReadPages
     * @param array<int,string> $nextWritePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $masterRecoveredPages,
        array $cachePages,
        array $nextReadPages,
        array $nextWritePages,
        string $currentSourceId,
        int $currentSourceEpoch = 1,
        bool $refreshCleanStaleCache = true,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal hot-cache next136 requires database and master-journal paths');
        }
        if ($currentMasterJournalBytes === null || trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal hot-cache next136 requires current master-journal bytes');
        }
        if (!str_contains($currentMasterJournalBytes, $databasePath . '-journal')) {
            throw new \RuntimeException('SQLite pager master-journal hot-cache next136 current master journal does not reference the database journal');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal hot-cache next136 requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal hot-cache next136 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal hot-cache next136 database bytes must be page-size aligned');
        }
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal hot-cache next136 requires a current source id');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal hot-cache next136 source epoch must be positive');
        }
        if ($nextReadPages === [] && $nextWritePages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal hot-cache next136 requires next read or write pages');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $masterRecoveredPages = self::normalizeImages($masterRecoveredPages, $pageSize, 'master recovered');
        $cachePages = self::normalizeCache($cachePages, $pageSize);
        self::assertPageList($nextReadPages, 'next read');
        $nextWritePages = self::normalizeOptionalImages($nextWritePages, $pageSize, 'next write');

        $cachedMembers = self::members($cachedMasterJournalBytes);
        $currentMembers = self::members($currentMasterJournalBytes);
        $nextSourceId = self::sourceId($masterJournalPath, $currentMembers);
        $nextEpoch = $currentSourceEpoch + 1;
        $cacheStale = $cachedMembers !== $currentMembers;

        $operations = [[
            'op' => 'read_current_master_journal_for_hot_cache',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
            'reason' => 'hot_cache_must_follow_current_master_journal_source',
        ]];
        if ($cacheStale) {
            $operations[] = [
                'op' => 'discard_cached_master_journal_members_for_hot_cache',
                'path' => $masterJournalPath,
                'reason' => 'cached_master_journal_members_do_not_match_current_source',
            ];
        }

        foreach ($masterRecoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal hot-cache next136 recovered page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-hot-recovered-current-source',
            ];
            $operations[] = [
                'op' => 'restore_master_journal_hot_page',
                'page_number' => $pageNumber,
                'reason' => 'recover_current_source_before_hot_cache_reuse',
            ];
        }

        $validCache = [];
        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];

        foreach ($cachePages as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal hot-cache next136 cache page {$pageNumber} is outside the database image");
            }
            $currentImage = $database[$pageNumber]['image'];
            $sourceId = $entry['source_id'];
            $epoch = $entry['epoch'];
            $dirty = $entry['dirty'];
            $pinned = $entry['pinned'];
            $reason = null;
            if ($dirty) {
                $reason = 'dirty_cache_from_crashed_transaction';
            } elseif ($pinned && $entry['image'] !== $currentImage) {
                $reason = 'pinned_reader_cache_predates_hot_recovery';
            } elseif ($sourceId !== $currentSourceId) {
                $reason = 'stale_master_journal_source_id';
            } elseif ($epoch !== $currentSourceEpoch) {
                $reason = 'stale_master_journal_source_epoch';
            } elseif ($entry['image'] !== $currentImage && !$refreshCleanStaleCache) {
                $reason = 'stale_hot_cache_refresh_disabled';
            }

            if ($reason !== null) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $entry['source'],
                    'source_id' => $sourceId,
                    'epoch' => $epoch,
                    'dirty' => $dirty,
                    'pinned' => $pinned,
                    'reason' => $reason,
                ];
                $operations[] = [
                    'op' => 'invalidate_master_journal_hot_cache_page',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                ];
            } elseif ($entry['image'] !== $currentImage) {
                $refreshed[] = $pageNumber;
                $validCache[$pageNumber] = [
                    'image' => $currentImage,
                    'source' => 'master-journal-hot-cache-refreshed-current-source',
                    'source_id' => $nextSourceId,
                    'epoch' => $nextEpoch,
                    'dirty' => false,
                ];
                $operations[] = [
                    'op' => 'refresh_master_journal_hot_cache_page',
                    'page_number' => $pageNumber,
                    'source_id' => $nextSourceId,
                    'epoch' => $nextEpoch,
                    'reason' => 'clean_hot_cache_image_predates_current_source',
                ];
            } else {
                $retained[] = $pageNumber;
                $validCache[$pageNumber] = [
                    'image' => $entry['image'],
                    'source' => $entry['source'],
                    'source_id' => $nextSourceId,
                    'epoch' => $nextEpoch,
                    'dirty' => false,
                ];
                $operations[] = [
                    'op' => 'retain_master_journal_hot_cache_page',
                    'page_number' => $pageNumber,
                    'source_id' => $nextSourceId,
                    'epoch' => $nextEpoch,
                    'reason' => 'hot_cache_page_matches_current_source',
                ];
            }

            $rows[$pageNumber] = [
                'page_number' => $pageNumber,
                'dirty' => $dirty,
                'pinned' => $pinned,
                'source_id_before' => $sourceId,
                'epoch_before' => $epoch,
                'image_matches_current_source' => $entry['image'] === $currentImage,
                'current_prefix' => self::label($currentImage),
                'cache_prefix' => self::label($entry['image']),
            ];
        }
        ksort($validCache, SORT_NUMERIC);

        $readResults = [];
        foreach ($nextReadPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal hot-cache next136 read page {$pageNumber} is outside the database image");
            }
            $entry = $validCache[$pageNumber] ?? null;
            $hit = is_array($entry);
            $image = $hit ? $entry['image'] : $database[$pageNumber]['image'];
            $readResults[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $hit,
                'source' => $hit ? $entry['source'] : $database[$pageNumber]['source'],
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'prefix' => self::label($image),
            ];
            $operations[] = [
                'op' => $hit ? 'next_read_master_journal_hot_cache_hit' : 'next_read_master_journal_hot_cache_miss',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reason' => 'next_read_uses_hot_cache_current_source',
            ];
        }

        $writeResults = [];
        foreach ($nextWritePages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal hot-cache next136 write page {$pageNumber} is outside the database image");
            }
            $beforeImage = $database[$pageNumber]['image'];
            $validCache[$pageNumber] = [
                'image' => $image,
                'source' => 'next-write-after-master-journal-hot-cache',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => true,
            ];
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'next-write-after-master-journal-hot-cache',
            ];
            $writeResults[] = [
                'page_number' => $pageNumber,
                'before_prefix' => self::label($beforeImage),
                'after_prefix' => self::label($image),
                'journal_before_from_current_source' => true,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ];
            $operations[] = [
                'op' => 'capture_next_write_before_image_from_hot_current_source',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reason' => 'next_write_journal_uses_master_journal_recovered_page',
            ];
        }

        return [
            'status' => 'pager-master-journal-hot-cache-current-source-next136',
            'reason' => 'master_journal_hot_recovery_rebases_pager_cache_before_next_read_write',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'cached_members' => $cachedMembers,
            'current_members' => $currentMembers,
            'cache_stale_rejected' => $cacheStale,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentSourceEpoch,
            ],
            'next_source' => [
                'id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ],
            'master_recovered_page_numbers' => array_keys($masterRecoveredPages),
            'retained_cache_page_numbers' => $retained,
            'refreshed_cache_page_numbers' => $refreshed,
            'invalidated_cache_page_numbers' => array_column($invalidated, 'page_number'),
            'invalidated_cache_entries' => $invalidated,
            'cache_rows' => array_values($rows),
            'next_reads' => $readResults,
            'next_writes' => $writeResults,
            'final_cache_page_numbers' => array_keys($validCache),
            'final_cache_sources' => self::cacheSources($validCache),
            'final_cache_dirty_page_numbers' => self::dirtyPageNumbers($validCache),
            'final_prefixes' => self::prefixes($database),
            'final_sources' => self::sources($database),
            'final_database_bytes' => self::sourceBytes($database, $pageSize),
            'operations' => $operations,
            'source_digest' => hash('sha256', implode('|', self::sources($database)) . '|' . implode(',', array_keys($validCache))),
            'dependencies' => [
                'sqlite-pager-master-journal-hot-cache-current-source-next136',
                'sqlite-pager-master-journal-cache-recovery-current-source-next122',
                'sqlite-pager-master-journal-savepoint-cache-current-source-next125',
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
            throw new \InvalidArgumentException("SQLite pager master-journal hot-cache next136 {$label} pages are required");
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal hot-cache next136 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal hot-cache next136 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizeOptionalImages(array $pages, int $pageSize, string $label): array
    {
        if ($pages === []) {
            return [];
        }

        return self::normalizeImages($pages, $pageSize, $label);
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,source?:string}> $pages
     * @return array<int,array{image:string,source_id:string,epoch:int,dirty:bool,pinned:bool,source:string}>
     */
    private static function normalizeCache(array $pages, int $pageSize): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal hot-cache next136 cache pages are required');
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal hot-cache next136 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal hot-cache next136 cache page {$pageNumber} image must match page size");
            }
            $sourceId = isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '';
            $epoch = $entry['epoch'] ?? 0;
            if (!is_int($epoch) || $epoch < 0) {
                throw new \InvalidArgumentException("SQLite pager master-journal hot-cache next136 cache page {$pageNumber} epoch must be non-negative");
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'source' => isset($entry['source']) && is_string($entry['source']) && $entry['source'] !== '' ? $entry['source'] : 'pager-cache-before-master-journal-hot-recovery',
            ];
        }

        return $normalized;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages, string $label): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal hot-cache next136 {$label} pages must be one-based integers");
            }
        }
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
                'source' => 'database-before-master-journal-hot-recovery',
            ];
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private static function members(?string $bytes): array
    {
        if ($bytes === null || trim($bytes) === '') {
            return [];
        }
        $members = [];
        foreach (preg_split('/\r?\n/', $bytes) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $members[$line] = $line;
            }
        }

        return array_values($members);
    }

    /**
     * @param list<string> $members
     */
    private static function sourceId(string $masterJournalPath, array $members): string
    {
        return 'master-hot-cache:' . substr(hash('sha256', $masterJournalPath . '|' . implode('|', $members)), 0, 16);
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

    /**
     * @param array<int,array<string,mixed>> $cache
     * @return array<int,string>
     */
    private static function cacheSources(array $cache): array
    {
        ksort($cache, SORT_NUMERIC);
        $sources = [];
        foreach ($cache as $pageNumber => $entry) {
            $sources[$pageNumber] = (string) $entry['source'];
        }

        return $sources;
    }

    /**
     * @param array<int,array<string,mixed>> $cache
     * @return list<int>
     */
    private static function dirtyPageNumbers(array $cache): array
    {
        $pages = [];
        foreach ($cache as $pageNumber => $entry) {
            if (($entry['dirty'] ?? false) === true) {
                $pages[] = $pageNumber;
            }
        }

        return $pages;
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
