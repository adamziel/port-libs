<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan
{


    /**
     * @param array<int,array{image:string,source?:string,epoch?:int}> $currentCache
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $currentWrites
     * @param array<int,string> $nextWrites
     * @return array{status:string,page_size:int,savepoint:string,current_source_epoch:int,next_source_epoch:int,cache:array<string,mixed>,savepoint:array<string,mixed>,next:array<string,mixed>,operations:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function planRecoveredHotJournalSavepointRetry(
        int $pageSize,
        string $savepoint,
        array $currentCache,
        array $hotJournalPages,
        array $currentWrites,
        array $nextWrites,
        int $currentSourceEpoch = 1,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache page size must be positive');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache savepoint name must not be empty');
        }
        if ($hotJournalPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache requires recovered hot-journal pages');
        }
        if ($currentWrites === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache requires current savepoint writes');
        }
        if ($nextWrites === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache requires next retry writes');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache source epoch must be positive');
        }

        self::assertBasicCachePages($currentCache, $pageSize);
        self::assertBasicPageImages($hotJournalPages, $pageSize, 'hot-journal');
        self::assertBasicPageImages($currentWrites, $pageSize, 'current');
        self::assertBasicPageImages($nextWrites, $pageSize, 'next');

        $nextEpoch = $currentSourceEpoch + 1;
        $recoveredPageNumbers = array_keys($hotJournalPages);
        sort($recoveredPageNumbers, SORT_NUMERIC);

        $invalidated = [];
        $preserved = [];
        $currentSourcePages = $currentCache;
        foreach ($currentCache as $pageNumber => $entry) {
            $epoch = (int) ($entry['epoch'] ?? 0);
            $source = (string) ($entry['source'] ?? 'unknown');
            $recovered = array_key_exists($pageNumber, $hotJournalPages);
            if ($recovered || $epoch !== $currentSourceEpoch) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $source,
                    'epoch' => $epoch,
                    'reason' => $recovered ? 'hot_journal_recovered_page' : 'stale_current_source_epoch',
                ];
                unset($currentSourcePages[$pageNumber]);
                continue;
            }

            $preserved[] = [
                'page_number' => $pageNumber,
                'source' => $source,
                'epoch' => $epoch,
            ];
        }

        foreach ($hotJournalPages as $pageNumber => $image) {
            $currentSourcePages[$pageNumber] = [
                'image' => $image,
                'source' => 'hot-journal',
                'epoch' => $nextEpoch,
            ];
        }
        ksort($currentSourcePages, SORT_NUMERIC);

        $beforeImages = [];
        $operations = [];
        foreach ($currentWrites as $pageNumber => $image) {
            $before = $currentSourcePages[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            $source = $currentSourcePages[$pageNumber]['source'] ?? 'zero-fill';
            $epoch = (int) ($currentSourcePages[$pageNumber]['epoch'] ?? $nextEpoch);
            $beforeImages[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_savepoint_before_image',
                'page_number' => $pageNumber,
                'source' => $source,
                'epoch' => $epoch,
                'bytes' => strlen($before),
                'reason' => 'capture_after_hot_journal_cache_invalidation',
            ];
            $currentSourcePages[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-current-write',
                'epoch' => $nextEpoch,
            ];
            $operations[] = [
                'op' => 'write_current_savepoint_page',
                'page_number' => $pageNumber,
                'source' => 'savepoint-current-write',
                'epoch' => $nextEpoch,
                'bytes' => strlen($image),
            ];
        }

        foreach ($beforeImages as $pageNumber => $image) {
            $currentSourcePages[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-rollback-before-image',
                'epoch' => $nextEpoch,
            ];
            $operations[] = [
                'op' => 'restore_savepoint_before_image',
                'page_number' => $pageNumber,
                'source' => 'savepoint-rollback-before-image',
                'epoch' => $nextEpoch,
                'bytes' => strlen($image),
            ];
        }

        $nextCaptured = [];
        foreach ($nextWrites as $pageNumber => $image) {
            $before = $currentSourcePages[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            $source = $currentSourcePages[$pageNumber]['source'] ?? 'zero-fill';
            $nextCaptured[] = [
                'page_number' => $pageNumber,
                'source' => $source,
                'epoch' => (int) ($currentSourcePages[$pageNumber]['epoch'] ?? $nextEpoch),
                'matches_savepoint_before_image' => isset($beforeImages[$pageNumber]) && $beforeImages[$pageNumber] === $before,
                'zero_filled_short_read' => $source === 'zero-fill',
            ];
            $operations[] = [
                'op' => 'capture_next_retry_before_image',
                'page_number' => $pageNumber,
                'source' => $source,
                'epoch' => (int) ($currentSourcePages[$pageNumber]['epoch'] ?? $nextEpoch),
                'bytes' => strlen($before),
                'reason' => 'retry_uses_current_source_after_hot_journal_recovery',
            ];
            $currentSourcePages[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-next-write',
                'epoch' => $nextEpoch,
            ];
            $operations[] = [
                'op' => 'write_next_retry_page',
                'page_number' => $pageNumber,
                'source' => 'savepoint-next-write',
                'epoch' => $nextEpoch,
                'bytes' => strlen($image),
            ];
        }

        ksort($currentSourcePages, SORT_NUMERIC);

        return [
            'status' => 'hot_journal_savepoint_cache_current_source_next',
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'current_source_epoch' => $currentSourceEpoch,
            'next_source_epoch' => $nextEpoch,
            'cache' => [
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'preserved_page_numbers' => array_column($preserved, 'page_number'),
                'preserved_entries' => $preserved,
                'recovered_page_numbers' => $recoveredPageNumbers,
                'final_page_numbers' => array_keys($currentSourcePages),
                'final_sources' => self::cachePageSources($currentSourcePages),
            ],
            'savepoint' => [
                'name' => $savepoint,
                'captured_page_numbers' => array_keys($beforeImages),
                'captured_sources' => self::capturedOperationSources($operations, 'capture_savepoint_before_image'),
                'rollback_restored_page_numbers' => array_keys($beforeImages),
            ],
            'next' => [
                'written_page_numbers' => array_keys($nextWrites),
                'captured_pages' => $nextCaptured,
                'final_sources' => self::cachePageSources($currentSourcePages),
            ],
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-hot-journal-savepoint-cache-current-source-next83',
                'sqlite-hot-journal-recovery',
                'sqlite-savepoint-page-image-rollback',
                'sqlite-pager-cache-current-source',
            ],
        ];
    }

    /**
     * @param array<int,array{image:string,source?:string,epoch?:int}> $cache
     */
    private static function assertBasicCachePages(array $cache, int $pageSize): void
    {
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache page numbers are one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache page {$pageNumber} image must match the page size");
            }
        }
    }

    /**
     * @param array<int,string> $pages
     */
    private static function assertBasicPageImages(array $pages, int $pageSize, string $label): void
    {
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache {$label} page numbers are one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache {$label} page {$pageNumber} image must match the page size");
            }
        }
    }

    /**
     * @param array<int,array{image:string,source?:string,epoch?:int}> $pages
     * @return array<int,string>
     */
    private static function cachePageSources(array $pages): array
    {
        $sources = [];
        foreach ($pages as $pageNumber => $entry) {
            $sources[$pageNumber] = (string) ($entry['source'] ?? 'unknown');
        }

        return $sources;
    }

    /**
     * @param list<array<string,mixed>> $operations
     * @return array<int,string>
     */
    private static function capturedOperationSources(array $operations, string $op): array
    {
        $sources = [];
        foreach ($operations as $operation) {
            if (($operation['op'] ?? null) === $op && isset($operation['page_number'])) {
                $sources[(int) $operation['page_number']] = (string) ($operation['source'] ?? 'unknown');
            }
        }

        return $sources;
    }


    /**
     * @param array<int,array{image:string,source?:string,epoch?:int,source_id?:string,dirty?:bool}> $currentCache
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $currentWrites
     * @param list<int> $releaseReadPages
     * @return array<string,mixed>
     */
    public static function planRecoveredSourceReleaseReads(
        int $pageSize,
        string $savepoint,
        string $currentSourceId,
        string $nextSourceId,
        array $currentCache,
        array $hotJournalPages,
        array $currentWrites,
        array $releaseReadPages,
        int $currentSourceEpoch = 1,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next100 page size must be positive');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next100 savepoint must not be empty');
        }
        if ($currentSourceId === '' || $nextSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next100 source ids must not be empty');
        }
        if ($currentSourceId === $nextSourceId) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next100 source ids must change after recovery');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next100 source epoch must be positive');
        }
        if ($hotJournalPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next100 requires hot-journal pages');
        }
        if ($currentWrites === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next100 requires savepoint writes');
        }
        if ($releaseReadPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next100 requires release read pages');
        }

        self::assertTokenCachePages($currentCache, $pageSize);
        self::assertTokenPageImages($hotJournalPages, $pageSize, 'hot-journal');
        self::assertTokenPageImages($currentWrites, $pageSize, 'current-write');
        self::assertReleaseReadPageList($releaseReadPages);

        $nextEpoch = $currentSourceEpoch + 1;
        $databasePages = [];
        $invalidated = [];
        $preserved = [];
        $operations = [];

        foreach ($currentCache as $pageNumber => $entry) {
            $epoch = (int) ($entry['epoch'] ?? 0);
            $sourceId = (string) ($entry['source_id'] ?? '');
            $source = (string) ($entry['source'] ?? 'unknown');
            $dirty = (bool) ($entry['dirty'] ?? false);
            $recovered = array_key_exists($pageNumber, $hotJournalPages);
            $reason = null;
            if ($recovered) {
                $reason = 'hot_journal_recovered_page';
            } elseif ($dirty) {
                $reason = 'dirty_cache_from_aborted_savepoint';
            } elseif ($epoch !== $currentSourceEpoch) {
                $reason = 'stale_current_source_epoch';
            } elseif ($sourceId !== $currentSourceId) {
                $reason = 'stale_current_source_id';
            }

            if ($reason !== null) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'epoch' => $epoch,
                    'dirty' => $dirty,
                    'reason' => $reason,
                ];
                continue;
            }

            $databasePages[$pageNumber] = [
                'image' => $entry['image'],
                'source' => $source,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => false,
            ];
            $preserved[] = [
                'page_number' => $pageNumber,
                'source' => $source,
                'source_id' => $sourceId,
                'epoch' => $epoch,
            ];
        }

        foreach ($hotJournalPages as $pageNumber => $image) {
            $databasePages[$pageNumber] = [
                'image' => $image,
                'source' => 'hot-journal-recovery',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'install_hot_journal_page',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'bytes' => strlen($image),
            ];
        }

        ksort($databasePages, SORT_NUMERIC);
        $beforeImages = [];
        foreach ($currentWrites as $pageNumber => $image) {
            $beforeEntry = $databasePages[$pageNumber] ?? [
                'image' => str_repeat("\0", $pageSize),
                'source' => 'zero-fill',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => false,
            ];
            $beforeImages[$pageNumber] = $beforeEntry;
            $operations[] = [
                'op' => 'capture_savepoint_before_image',
                'page_number' => $pageNumber,
                'source' => $beforeEntry['source'],
                'source_id' => $beforeEntry['source_id'],
                'epoch' => $beforeEntry['epoch'],
                'bytes' => strlen($beforeEntry['image']),
            ];
            $databasePages[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-current-write',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => true,
            ];
            $operations[] = [
                'op' => 'write_savepoint_page',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'bytes' => strlen($image),
            ];
        }

        foreach ($beforeImages as $pageNumber => $entry) {
            $databasePages[$pageNumber] = [
                'image' => $entry['image'],
                'source' => 'savepoint-rollback-before-image',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'rollback_savepoint_before_image',
                'page_number' => $pageNumber,
                'source' => $entry['source'],
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'bytes' => strlen($entry['image']),
            ];
        }

        $releaseReads = [];
        foreach ($releaseReadPages as $pageNumber) {
            $entry = $databasePages[$pageNumber] ?? null;
            $cacheHit = $entry !== null && !$entry['dirty'] && $entry['source_id'] === $nextSourceId && $entry['epoch'] === $nextEpoch;
            $releaseReads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheHit,
                'source' => $cacheHit ? $entry['source'] : 'pager-read-miss',
                'source_id' => $cacheHit ? $entry['source_id'] : $nextSourceId,
                'epoch' => $nextEpoch,
                'zero_filled_short_read' => !$cacheHit,
                'matches_rollback_before_image' => isset($beforeImages[$pageNumber]) && $cacheHit && $beforeImages[$pageNumber]['image'] === $entry['image'],
            ];
            $operations[] = [
                'op' => $cacheHit ? 'release_read_cache_hit' : 'release_read_cache_miss',
                'page_number' => $pageNumber,
                'source' => $cacheHit ? $entry['source'] : 'zero-fill',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ];
        }

        ksort($databasePages, SORT_NUMERIC);

        return [
            'status' => 'hot_journal_savepoint_cache_current_source_next100',
            'page_size' => $pageSize,
            'savepoint' => [
                'name' => $savepoint,
                'captured_page_numbers' => array_keys($beforeImages),
                'rollback_restored_page_numbers' => array_keys($beforeImages),
                'released' => true,
            ],
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentSourceEpoch,
            ],
            'next_source' => [
                'id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ],
            'cache' => [
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'preserved_page_numbers' => array_column($preserved, 'page_number'),
                'preserved_entries' => $preserved,
                'recovered_page_numbers' => self::sortedPageKeys($hotJournalPages),
                'final_page_numbers' => array_keys($databasePages),
                'final_sources' => self::tokenPageSources($databasePages),
                'final_source_ids' => self::tokenPageSourceIds($databasePages),
                'dirty_page_numbers' => self::tokenDirtyPageNumbers($databasePages),
            ],
            'release_reads' => $releaseReads,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-hot-journal-savepoint-cache-current-source-next100',
                'sqlite-hot-journal-recovery',
                'sqlite-savepoint-page-image-rollback',
                'sqlite-pager-cache-current-source-token',
            ],
        ];
    }

    /**
     * @param array<int,array{image:string,source?:string,epoch?:int,source_id?:string,dirty?:bool}> $cache
     */
    private static function assertTokenCachePages(array $cache, int $pageSize): void
    {
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next100 page numbers are one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next100 page {$pageNumber} image must match page size");
            }
        }
    }

    /**
     * @param array<int,string> $pages
     */
    private static function assertTokenPageImages(array $pages, int $pageSize, string $label): void
    {
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next100 {$label} page numbers are one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next100 {$label} page {$pageNumber} image must match page size");
            }
        }
    }

    /**
     * @param list<int> $pages
     */
    private static function assertReleaseReadPageList(array $pages): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next100 release read pages are one-based integers');
            }
        }
    }

    /**
     * @param array<int,mixed> $array
     * @return list<int>
     */
    private static function sortedPageKeys(array $array): array
    {
        $keys = array_keys($array);
        sort($keys, SORT_NUMERIC);

        return $keys;
    }

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return array<int,string>
     */
    private static function tokenPageSources(array $pages): array
    {
        $sources = [];
        foreach ($pages as $pageNumber => $entry) {
            $sources[$pageNumber] = (string) ($entry['source'] ?? 'unknown');
        }

        return $sources;
    }

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return array<int,string>
     */
    private static function tokenPageSourceIds(array $pages): array
    {
        $sourceIds = [];
        foreach ($pages as $pageNumber => $entry) {
            $sourceIds[$pageNumber] = (string) ($entry['source_id'] ?? '');
        }

        return $sourceIds;
    }

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return list<int>
     */
    private static function tokenDirtyPageNumbers(array $pages): array
    {
        $dirty = [];
        foreach ($pages as $pageNumber => $entry) {
            if (($entry['dirty'] ?? false) === true) {
                $dirty[] = $pageNumber;
            }
        }

        return $dirty;
    }


    /**
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,savepoint?:string|null}> $cachePages
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,string> $nextStatementWrites
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function planRecoveredSourceNextStatement(
        int $pageSize,
        string $savepoint,
        string $nextStatement,
        string $currentSourceId,
        string $recoveredSourceId,
        array $cachePages,
        array $hotJournalPages,
        array $savepointBeforePages,
        array $nextStatementWrites,
        array $readPages,
        int $currentEpoch = 1,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next149 page size must be positive');
        }
        foreach ([$savepoint, $nextStatement, $currentSourceId, $recoveredSourceId] as $value) {
            if ($value === '') {
                throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next149 names and source ids must be non-empty');
            }
        }
        if ($currentSourceId === $recoveredSourceId) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next149 source id must advance after recovery');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next149 epoch must be positive');
        }
        if ($cachePages === [] || $hotJournalPages === [] || $savepointBeforePages === [] || $nextStatementWrites === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next149 requires cache, hot journal, savepoint, next write, and read pages');
        }

        self::assertStatementCachePages($cachePages, $pageSize);
        self::assertStatementPageImages($hotJournalPages, $pageSize, 'hot-journal');
        self::assertStatementPageImages($savepointBeforePages, $pageSize, 'savepoint-before');
        self::assertStatementPageImages($nextStatementWrites, $pageSize, 'next-statement');
        self::assertStatementReadPageList($readPages);

        $recoveredEpoch = $currentEpoch + 1;
        $pages = [];
        $invalidated = [];
        $retained = [];
        $operations = [];

        foreach ($cachePages as $pageNumber => $entry) {
            $source = (string) ($entry['source'] ?? 'cache');
            $sourceId = (string) ($entry['source_id'] ?? '');
            $epoch = (int) ($entry['epoch'] ?? 0);
            $dirty = (bool) ($entry['dirty'] ?? false);
            $pinned = (bool) ($entry['pinned'] ?? false);
            $entrySavepoint = isset($entry['savepoint']) ? (string) $entry['savepoint'] : null;
            $reason = null;

            if (array_key_exists($pageNumber, $hotJournalPages)) {
                $reason = 'hot_journal_recovered_page_replaces_cache';
            } elseif (array_key_exists($pageNumber, $savepointBeforePages)) {
                $reason = 'rollback_to_savepoint_restores_before_image';
            } elseif ($dirty) {
                $reason = 'dirty_cache_after_aborted_savepoint';
            } elseif ($pinned) {
                $reason = 'pinned_cache_page_rechecked_after_recovery';
            } elseif ($sourceId !== $currentSourceId) {
                $reason = 'stale_current_source_id';
            } elseif ($epoch !== $currentEpoch) {
                $reason = 'stale_current_source_epoch';
            }

            if ($reason !== null) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'epoch' => $epoch,
                    'dirty' => $dirty,
                    'pinned' => $pinned,
                    'savepoint' => $entrySavepoint,
                    'reason' => $reason,
                ];
                $operations[] = [
                    'op' => 'invalidate_cache_page',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                ];
                continue;
            }

            $pages[$pageNumber] = self::statementPageEntry($entry['image'], $source, $recoveredSourceId, $recoveredEpoch, false, $entrySavepoint);
            $retained[] = [
                'page_number' => $pageNumber,
                'source' => $source,
                'old_source_id' => $sourceId,
                'new_source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
            ];
            $operations[] = [
                'op' => 'retag_clean_cache_page_for_recovered_source',
                'page_number' => $pageNumber,
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
            ];
        }

        foreach ($hotJournalPages as $pageNumber => $image) {
            $pages[$pageNumber] = self::statementPageEntry($image, 'hot-journal-recovered-page', $recoveredSourceId, $recoveredEpoch, false, null);
            $operations[] = [
                'op' => 'install_hot_journal_recovered_page',
                'page_number' => $pageNumber,
            ];
        }

        foreach ($savepointBeforePages as $pageNumber => $image) {
            $pages[$pageNumber] = self::statementPageEntry($image, 'savepoint-before-image-after-hot-journal', $recoveredSourceId, $recoveredEpoch, false, $savepoint);
            $operations[] = [
                'op' => 'restore_savepoint_before_image',
                'savepoint' => $savepoint,
                'page_number' => $pageNumber,
            ];
        }

        $readRows = [];
        foreach ($readPages as $pageNumber) {
            $entry = $pages[$pageNumber] ?? self::statementZeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
            $hit = isset($pages[$pageNumber])
                && ($entry['source_id'] ?? '') === $recoveredSourceId
                && ($entry['epoch'] ?? 0) === $recoveredEpoch
                && ($entry['dirty'] ?? false) === false;
            $readRows[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $hit,
                'source' => $hit ? (string) $entry['source'] : 'zero-fill-recovered-current-source',
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
                'prefix' => self::statementPagePrefix((string) $entry['image']),
                'matches_hot_journal' => isset($hotJournalPages[$pageNumber]) && $entry['image'] === $hotJournalPages[$pageNumber],
                'matches_savepoint_before' => isset($savepointBeforePages[$pageNumber]) && $entry['image'] === $savepointBeforePages[$pageNumber],
            ];
            $operations[] = [
                'op' => $hit ? 'read_recovered_current_source_cache_page' : 'read_zero_fill_after_cache_invalidation',
                'page_number' => $pageNumber,
            ];
        }

        $nextBefore = [];
        foreach ($nextStatementWrites as $pageNumber => $image) {
            $before = $pages[$pageNumber] ?? self::statementZeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
            if (($before['source_id'] ?? '') !== $recoveredSourceId || ($before['epoch'] ?? 0) !== $recoveredEpoch || ($before['dirty'] ?? false) === true) {
                throw new \RuntimeException("SQLite pager hot-journal savepoint cache next149 page {$pageNumber} is not recovered-current clean");
            }
            $nextBefore[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_next_statement_before_image',
                'statement' => $nextStatement,
                'page_number' => $pageNumber,
                'source' => $before['source'],
            ];
            $pages[$pageNumber] = self::statementPageEntry($image, 'next-statement-write-after-recovered-savepoint', $recoveredSourceId, $recoveredEpoch, true, null);
            $operations[] = [
                'op' => 'write_next_statement_page',
                'statement' => $nextStatement,
                'page_number' => $pageNumber,
            ];
        }

        ksort($pages, SORT_NUMERIC);

        return [
            'status' => 'pager_hot_journal_savepoint_cache_current_source_next149',
            'reason' => 'hot_journal_recovery_and_savepoint_rollback_refresh_page_cache_for_next_statement',
            'page_size' => $pageSize,
            'savepoint' => ['name' => $savepoint, 'active_after_rollback' => true],
            'next_statement' => [
                'name' => $nextStatement,
                'before_page_numbers' => array_keys($nextBefore),
                'write_page_numbers' => self::sortedStatementPageKeys($nextStatementWrites),
            ],
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'recovered_source' => ['id' => $recoveredSourceId, 'epoch' => $recoveredEpoch],
            'cache' => [
                'retained_page_numbers' => array_column($retained, 'page_number'),
                'retained_entries' => $retained,
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'hot_journal_page_numbers' => self::sortedStatementPageKeys($hotJournalPages),
                'savepoint_before_page_numbers' => self::sortedStatementPageKeys($savepointBeforePages),
                'final_page_numbers' => array_keys($pages),
                'final_sources' => self::statementPageSources($pages),
                'final_source_ids' => self::statementPageSourceIds($pages),
                'dirty_page_numbers' => self::statementDirtyPageNumbers($pages),
            ],
            'read_pages' => $readRows,
            'next_before_prefixes' => self::statementPagePrefixes($nextBefore),
            'final_prefixes' => self::statementPagePrefixes($pages),
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-hot-journal-savepoint-cache-current-source-next149',
                'sqlite-hot-journal-cache-current-source-refresh',
                'sqlite-savepoint-page-image-rollback',
                'sqlite-next-statement-before-image-capture',
            ],
        ];
    }

    /**
     * @param array<int,array{image:string}> $cache
     */
    private static function assertStatementCachePages(array $cache, int $pageSize): void
    {
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next149 cache pages must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next149 cache page {$pageNumber} image must match page size");
            }
        }
    }

    /**
     * @param array<int,string> $pages
     */
    private static function assertStatementPageImages(array $pages, int $pageSize, string $label): void
    {
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next149 {$label} pages must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next149 {$label} page {$pageNumber} image must match page size");
            }
        }
    }

    /**
     * @param list<int> $pages
     */
    private static function assertStatementReadPageList(array $pages): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next149 read pages must be one-based integers');
            }
        }
    }

    /**
     * @return array{image:string,source:string,source_id:string,epoch:int,dirty:bool,savepoint:string|null}
     */
    private static function statementPageEntry(string $image, string $source, string $sourceId, int $epoch, bool $dirty, ?string $savepoint): array
    {
        return [
            'image' => $image,
            'source' => $source,
            'source_id' => $sourceId,
            'epoch' => $epoch,
            'dirty' => $dirty,
            'savepoint' => $savepoint,
        ];
    }

    /**
     * @return array{image:string,source:string,source_id:string,epoch:int,dirty:bool,savepoint:null}
     */
    private static function statementZeroPage(int $pageSize, string $sourceId, int $epoch): array
    {
        return self::statementPageEntry(str_repeat("\0", $pageSize), 'zero-fill-recovered-current-source', $sourceId, $epoch, false, null);
    }

    /**
     * @param array<int,mixed> $array
     * @return list<int>
     */
    private static function sortedStatementPageKeys(array $array): array
    {
        $keys = array_keys($array);
        sort($keys, SORT_NUMERIC);

        return $keys;
    }

    /**
     * @param array<int,array{source:string}> $pages
     * @return array<int,string>
     */
    private static function statementPageSources(array $pages): array
    {
        $sources = [];
        foreach ($pages as $pageNumber => $entry) {
            $sources[$pageNumber] = $entry['source'];
        }

        return $sources;
    }

    /**
     * @param array<int,array{source_id:string}> $pages
     * @return array<int,string>
     */
    private static function statementPageSourceIds(array $pages): array
    {
        $sourceIds = [];
        foreach ($pages as $pageNumber => $entry) {
            $sourceIds[$pageNumber] = $entry['source_id'];
        }

        return $sourceIds;
    }

    /**
     * @param array<int,array{dirty:bool}> $pages
     * @return list<int>
     */
    private static function statementDirtyPageNumbers(array $pages): array
    {
        $dirty = [];
        foreach ($pages as $pageNumber => $entry) {
            if ($entry['dirty']) {
                $dirty[] = $pageNumber;
            }
        }

        return $dirty;
    }

    /**
     * @param array<int,array{image:string}> $pages
     * @return array<int,string>
     */
    private static function statementPagePrefixes(array $pages): array
    {
        $prefixes = [];
        foreach ($pages as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::statementPagePrefix($entry['image']);
        }

        return $prefixes;
    }

    private static function statementPagePrefix(string $image): string
    {
        return rtrim(substr($image, 0, 48), ".\0");
    }


    /**
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool}> $cachePages
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $currentSourcePages
     * @param array<int,string> $savepointWrites
     * @param list<int> $rollbackPages
     * @param array<int,string> $retryWrites
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function planRecoveredSourceDigestFence(
        int $pageSize,
        string $savepoint,
        string $currentSourceId,
        string $recoveredSourceId,
        array $cachePages,
        array $hotJournalPages,
        array $currentSourcePages,
        array $savepointWrites,
        array $rollbackPages,
        array $retryWrites,
        array $readPages,
        int $currentEpoch = 1,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next157 page size must be positive');
        }
        foreach ([$savepoint, $currentSourceId, $recoveredSourceId] as $value) {
            if ($value === '') {
                throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next157 names and source ids must be non-empty');
            }
        }
        if ($currentSourceId === $recoveredSourceId) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next157 source id must advance after recovery');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next157 epoch must be positive');
        }
        if ($cachePages === [] || $hotJournalPages === [] || $currentSourcePages === [] || $savepointWrites === [] || $rollbackPages === [] || $retryWrites === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next157 requires cache, hot journal, current source, savepoint, retry, and read pages');
        }

        self::assertDigestFenceCachePages($cachePages, $pageSize);
        self::assertDigestFencePageImages($hotJournalPages, $pageSize, 'hot-journal');
        self::assertDigestFencePageImages($currentSourcePages, $pageSize, 'current-source');
        self::assertDigestFencePageImages($savepointWrites, $pageSize, 'savepoint-write');
        self::assertDigestFencePageList($rollbackPages, 'rollback');
        self::assertDigestFencePageImages($retryWrites, $pageSize, 'retry-write');
        self::assertDigestFencePageList($readPages, 'read');

        $recoveredEpoch = $currentEpoch + 1;
        $currentImages = $currentSourcePages;
        foreach ($hotJournalPages as $pageNumber => $image) {
            $currentImages[$pageNumber] = $image;
        }
        ksort($currentImages, SORT_NUMERIC);

        $pages = [];
        $retained = [];
        $invalidated = [];
        $operations = [];
        $currentDigests = self::digestFencePageDigests($currentImages);

        foreach ($cachePages as $pageNumber => $entry) {
            $source = (string) ($entry['source'] ?? 'pager-cache');
            $sourceId = (string) ($entry['source_id'] ?? '');
            $epoch = (int) ($entry['epoch'] ?? 0);
            $dirty = (bool) ($entry['dirty'] ?? false);
            $pinned = (bool) ($entry['pinned'] ?? false);
            $reason = null;

            if ($dirty) {
                $reason = 'dirty_cache_after_failed_savepoint';
            } elseif ($pinned) {
                $reason = 'pinned_cache_requires_reopen_after_hot_recovery';
            } elseif ($sourceId !== $recoveredSourceId || $epoch !== $recoveredEpoch) {
                $reason = 'stale_cache_source_token';
            } elseif (!isset($currentImages[$pageNumber])) {
                $reason = 'cache_page_absent_from_recovered_current_source';
            } elseif (!hash_equals(self::digestFencePageDigest($currentImages[$pageNumber]), self::digestFencePageDigest($entry['image']))) {
                $reason = 'cache_current_source_image_mismatch';
            }

            if ($reason !== null) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'epoch' => $epoch,
                    'dirty' => $dirty,
                    'pinned' => $pinned,
                    'reason' => $reason,
                ];
                $operations[] = [
                    'op' => 'invalidate_cache_page_before_savepoint_before_image',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                ];
                continue;
            }

            $pages[$pageNumber] = self::digestFencePageEntry($entry['image'], $source, $recoveredSourceId, $recoveredEpoch, false);
            $retained[] = [
                'page_number' => $pageNumber,
                'source' => $source,
                'digest' => self::digestFencePageDigest($entry['image']),
            ];
            $operations[] = [
                'op' => 'retain_cache_page_matching_recovered_current_source',
                'page_number' => $pageNumber,
                'digest' => self::digestFencePageDigest($entry['image']),
            ];
        }

        foreach ($currentImages as $pageNumber => $image) {
            if (!isset($pages[$pageNumber])) {
                $pages[$pageNumber] = self::digestFencePageEntry($image, isset($hotJournalPages[$pageNumber]) ? 'hot-journal-recovered-current-source' : 'database-current-source', $recoveredSourceId, $recoveredEpoch, false);
                $operations[] = [
                    'op' => isset($hotJournalPages[$pageNumber]) ? 'install_hot_journal_current_source_page' : 'install_database_current_source_page',
                    'page_number' => $pageNumber,
                ];
            }
        }

        $savepointBefore = [];
        foreach ($savepointWrites as $pageNumber => $image) {
            $before = $pages[$pageNumber] ?? self::digestFenceZeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
            if (($before['source_id'] ?? '') !== $recoveredSourceId || ($before['epoch'] ?? 0) !== $recoveredEpoch || ($before['dirty'] ?? false) === true) {
                throw new \RuntimeException("SQLite pager hot-journal savepoint cache next157 page {$pageNumber} is not a clean recovered current-source page");
            }
            $savepointBefore[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_savepoint_before_image_from_recovered_current_source',
                'savepoint' => $savepoint,
                'page_number' => $pageNumber,
                'source' => $before['source'],
                'digest' => self::digestFencePageDigest($before['image']),
            ];
            $pages[$pageNumber] = self::digestFencePageEntry($image, 'failed-savepoint-write', $recoveredSourceId, $recoveredEpoch, true);
            $operations[] = [
                'op' => 'write_failed_savepoint_page',
                'savepoint' => $savepoint,
                'page_number' => $pageNumber,
            ];
        }

        $rollbackRestored = [];
        foreach ($rollbackPages as $pageNumber) {
            $before = $savepointBefore[$pageNumber] ?? null;
            if ($before === null) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next157 rollback page {$pageNumber} has no savepoint before image");
            }
            $pages[$pageNumber] = self::digestFencePageEntry($before['image'], 'rollback-to-recovered-current-source-before-image', $recoveredSourceId, $recoveredEpoch, false);
            $rollbackRestored[$pageNumber] = $pages[$pageNumber];
            $operations[] = [
                'op' => 'rollback_to_restores_recovered_current_source_before_image',
                'savepoint' => $savepoint,
                'page_number' => $pageNumber,
            ];
        }

        $readRows = [];
        foreach ($readPages as $pageNumber) {
            $entry = $pages[$pageNumber] ?? self::digestFenceZeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
            $digest = self::digestFencePageDigest($entry['image']);
            $readRows[] = [
                'page_number' => $pageNumber,
                'cache_hit' => isset($pages[$pageNumber]) && ($entry['source_id'] ?? '') === $recoveredSourceId && ($entry['epoch'] ?? 0) === $recoveredEpoch && ($entry['dirty'] ?? false) === false,
                'source' => $entry['source'],
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'prefix' => self::digestFencePagePrefix($entry['image']),
                'digest' => $digest,
                'matches_current_source_digest' => isset($currentDigests[$pageNumber]) && hash_equals($currentDigests[$pageNumber], $digest),
            ];
            $operations[] = [
                'op' => 'read_after_rollback_to_current_source_fence',
                'page_number' => $pageNumber,
                'digest' => $digest,
            ];
        }

        $retryBefore = [];
        foreach ($retryWrites as $pageNumber => $image) {
            $before = $pages[$pageNumber] ?? self::digestFenceZeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
            if (($before['source_id'] ?? '') !== $recoveredSourceId || ($before['epoch'] ?? 0) !== $recoveredEpoch || ($before['dirty'] ?? false) === true) {
                throw new \RuntimeException("SQLite pager hot-journal savepoint cache next157 retry page {$pageNumber} is not a clean recovered current-source page");
            }
            $retryBefore[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_retry_before_image_after_source_fence',
                'page_number' => $pageNumber,
                'source' => $before['source'],
                'digest' => self::digestFencePageDigest($before['image']),
            ];
            $pages[$pageNumber] = self::digestFencePageEntry($image, 'retry-write-after-source-fenced-rollback', $recoveredSourceId, $recoveredEpoch, true);
            $operations[] = [
                'op' => 'write_retry_page_after_source_fence',
                'page_number' => $pageNumber,
            ];
        }

        ksort($pages, SORT_NUMERIC);

        return [
            'status' => 'pager_hot_journal_savepoint_cache_current_source_next157',
            'reason' => 'savepoint_before_images_are_fenced_by_recovered_current_source_digests_after_hot_journal_recovery',
            'page_size' => $pageSize,
            'savepoint' => [
                'name' => $savepoint,
                'active_after_rollback' => true,
                'rollback_page_numbers' => $rollbackPages,
            ],
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentEpoch,
            ],
            'recovered_source' => [
                'id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
                'page_numbers' => self::sortedDigestFencePageKeys($currentImages),
                'digests' => $currentDigests,
            ],
            'cache' => [
                'retained_page_numbers' => array_column($retained, 'page_number'),
                'retained_entries' => $retained,
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
            ],
            'savepoint_before_page_numbers' => self::sortedDigestFencePageKeys($savepointBefore),
            'savepoint_before_prefixes' => self::digestFencePagePrefixes($savepointBefore),
            'rollback_restored_prefixes' => self::digestFencePagePrefixes($rollbackRestored),
            'read_pages' => $readRows,
            'retry_before_page_numbers' => self::sortedDigestFencePageKeys($retryBefore),
            'retry_before_prefixes' => self::digestFencePagePrefixes($retryBefore),
            'final_page_numbers' => self::sortedDigestFencePageKeys($pages),
            'final_sources' => self::digestFencePageSources($pages),
            'final_prefixes' => self::digestFencePagePrefixes($pages),
            'dirty_page_numbers' => self::digestFenceDirtyPageNumbers($pages),
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-hot-journal-savepoint-cache-current-source-next157',
                'sqlite-hot-journal-recovered-source-image-fence',
                'sqlite-savepoint-before-image-current-source-validation',
                'sqlite-pager-cache-current-source-digest',
            ],
        ];
    }

    /**
     * @param array<int,array{image:string}> $cache
     */
    private static function assertDigestFenceCachePages(array $cache, int $pageSize): void
    {
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache next157 cache pages must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next157 cache page {$pageNumber} image must match page size");
            }
        }
    }

    /**
     * @param array<int,string> $pages
     */
    private static function assertDigestFencePageImages(array $pages, int $pageSize, string $label): void
    {
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next157 {$label} pages must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next157 {$label} page {$pageNumber} image must match page size");
            }
        }
    }

    /**
     * @param list<int> $pages
     */
    private static function assertDigestFencePageList(array $pages, string $label): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache next157 {$label} pages must be one-based integers");
            }
        }
    }

    /**
     * @return array{image:string,source:string,source_id:string,epoch:int,dirty:bool}
     */
    private static function digestFencePageEntry(string $image, string $source, string $sourceId, int $epoch, bool $dirty): array
    {
        return [
            'image' => $image,
            'source' => $source,
            'source_id' => $sourceId,
            'epoch' => $epoch,
            'dirty' => $dirty,
        ];
    }

    /**
     * @return array{image:string,source:string,source_id:string,epoch:int,dirty:false}
     */
    private static function digestFenceZeroPage(int $pageSize, string $sourceId, int $epoch): array
    {
        return self::digestFencePageEntry(str_repeat("\0", $pageSize), 'zero-fill-recovered-current-source', $sourceId, $epoch, false);
    }

    /**
     * @param array<int,mixed> $array
     * @return list<int>
     */
    private static function sortedDigestFencePageKeys(array $array): array
    {
        $keys = array_keys($array);
        sort($keys, SORT_NUMERIC);

        return $keys;
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function digestFencePageDigests(array $pages): array
    {
        $digests = [];
        foreach ($pages as $pageNumber => $image) {
            $digests[$pageNumber] = self::digestFencePageDigest($image);
        }

        return $digests;
    }

    private static function digestFencePageDigest(string $image): string
    {
        return substr(hash('sha256', $image), 0, 16);
    }

    /**
     * @param array<int,array{source:string}> $pages
     * @return array<int,string>
     */
    private static function digestFencePageSources(array $pages): array
    {
        $sources = [];
        foreach ($pages as $pageNumber => $entry) {
            $sources[$pageNumber] = $entry['source'];
        }

        return $sources;
    }

    /**
     * @param array<int,array{dirty:bool}> $pages
     * @return list<int>
     */
    private static function digestFenceDirtyPageNumbers(array $pages): array
    {
        $dirty = [];
        foreach ($pages as $pageNumber => $entry) {
            if ($entry['dirty']) {
                $dirty[] = $pageNumber;
            }
        }

        return $dirty;
    }

    /**
     * @param array<int,array{image:string}> $pages
     * @return array<int,string>
     */
    private static function digestFencePagePrefixes(array $pages): array
    {
        $prefixes = [];
        foreach ($pages as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::digestFencePagePrefix($entry['image']);
        }

        return $prefixes;
    }

    private static function digestFencePagePrefix(string $image): string
    {
        return rtrim(substr($image, 0, 48), ".\0");
    }
}
