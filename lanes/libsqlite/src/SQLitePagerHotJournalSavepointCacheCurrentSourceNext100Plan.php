<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalSavepointCacheCurrentSourceNext100Plan
{
    /**
     * @param array<int,array{image:string,source?:string,epoch?:int,source_id?:string,dirty?:bool}> $currentCache
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $currentWrites
     * @param list<int> $releaseReadPages
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
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

        self::assertCache($currentCache, $pageSize);
        self::assertPages($hotJournalPages, $pageSize, 'hot-journal');
        self::assertPages($currentWrites, $pageSize, 'current-write');
        self::assertPageList($releaseReadPages);

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
                'recovered_page_numbers' => self::sortedKeys($hotJournalPages),
                'final_page_numbers' => array_keys($databasePages),
                'final_sources' => self::sources($databasePages),
                'final_source_ids' => self::sourceIds($databasePages),
                'dirty_page_numbers' => self::dirtyPageNumbers($databasePages),
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
    private static function assertCache(array $cache, int $pageSize): void
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
    private static function assertPages(array $pages, int $pageSize, string $label): void
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
    private static function assertPageList(array $pages): void
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
    private static function sortedKeys(array $array): array
    {
        $keys = array_keys($array);
        sort($keys, SORT_NUMERIC);

        return $keys;
    }

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return array<int,string>
     */
    private static function sources(array $pages): array
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
    private static function sourceIds(array $pages): array
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
    private static function dirtyPageNumbers(array $pages): array
    {
        $dirty = [];
        foreach ($pages as $pageNumber => $entry) {
            if (($entry['dirty'] ?? false) === true) {
                $dirty[] = $pageNumber;
            }
        }

        return $dirty;
    }
}
