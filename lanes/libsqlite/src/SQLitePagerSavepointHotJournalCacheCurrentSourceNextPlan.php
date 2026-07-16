<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan
{
    /**
     * @param array<int,array{image:string,source?:string,epoch?:int,source_id?:string,dirty?:bool}> $currentCache
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointWrites
     * @param array<int,string> $nextStatementWrites
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
        int $pageSize,
        string $savepointName,
        string $statementName,
        string $currentSourceId,
        string $nextSourceId,
        array $currentCache,
        array $hotJournalPages,
        array $savepointWrites,
        array $nextStatementWrites,
        array $readPages,
        int $currentSourceEpoch = 1,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal cache next128 page size must be positive');
        }
        if ($savepointName === '' || $statementName === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal cache next128 names must not be empty');
        }
        if ($currentSourceId === '' || $nextSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal cache next128 source ids must not be empty');
        }
        if ($currentSourceId === $nextSourceId) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal cache next128 source ids must change after recovery');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal cache next128 source epoch must be positive');
        }
        if ($hotJournalPages === [] || $savepointWrites === [] || $nextStatementWrites === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal cache next128 requires hot pages, savepoint writes, next writes, and read pages');
        }

        self::assertCache($currentCache, $pageSize);
        self::assertPages($hotJournalPages, $pageSize, 'hot-journal');
        self::assertPages($savepointWrites, $pageSize, 'savepoint-write');
        self::assertPages($nextStatementWrites, $pageSize, 'next-statement-write');
        self::assertPageList($readPages);

        $nextEpoch = $currentSourceEpoch + 1;
        $pages = [];
        $invalidated = [];
        $preserved = [];
        $operations = [];

        foreach ($currentCache as $pageNumber => $entry) {
            $epoch = (int) ($entry['epoch'] ?? 0);
            $sourceId = (string) ($entry['source_id'] ?? '');
            $source = (string) ($entry['source'] ?? 'unknown');
            $dirty = (bool) ($entry['dirty'] ?? false);
            $reason = null;
            if (array_key_exists($pageNumber, $hotJournalPages)) {
                $reason = 'hot_journal_current_source_replaces_cached_page';
            } elseif ($dirty) {
                $reason = 'dirty_savepoint_cache_discarded_before_retry_statement';
            } elseif ($epoch !== $currentSourceEpoch) {
                $reason = 'stale_epoch_cache_discarded_before_retry_statement';
            } elseif ($sourceId !== $currentSourceId) {
                $reason = 'stale_source_cache_discarded_before_retry_statement';
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

            $pages[$pageNumber] = [
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
            $pages[$pageNumber] = [
                'image' => $image,
                'source' => 'hot-journal-current-source',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'install_hot_journal_current_source_page',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ];
        }

        $savepointBefore = [];
        foreach ($savepointWrites as $pageNumber => $image) {
            $before = $pages[$pageNumber] ?? self::zeroPage($pageSize, $nextSourceId, $nextEpoch);
            $savepointBefore[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_savepoint_before_image',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'source' => $before['source'],
                'reason' => 'capture_from_hot_journal_recovered_current_source',
            ];
            $pages[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-write-before-rollback-to',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => true,
            ];
            $operations[] = [
                'op' => 'write_savepoint_page',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ];
        }

        foreach ($savepointBefore as $pageNumber => $before) {
            $pages[$pageNumber] = [
                'image' => $before['image'],
                'source' => 'rollback-to-savepoint-before-image',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'rollback_to_savepoint_before_image',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'source' => $before['source'],
                'reason' => 'rollback_to_keeps_savepoint_open_and_current_source_valid',
            ];
        }

        $nextBefore = [];
        foreach ($nextStatementWrites as $pageNumber => $image) {
            $before = $pages[$pageNumber] ?? self::zeroPage($pageSize, $nextSourceId, $nextEpoch);
            if (($before['dirty'] ?? false) === true || ($before['source_id'] ?? '') !== $nextSourceId || ($before['epoch'] ?? 0) !== $nextEpoch) {
                throw new \RuntimeException("SQLite pager savepoint hot-journal cache next128 page {$pageNumber} is not on the recovered current source");
            }
            $nextBefore[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_next_statement_before_image',
                'statement' => $statementName,
                'page_number' => $pageNumber,
                'source' => $before['source'],
                'reason' => 'retry_statement_captures_after_hot_journal_rollback_to_current_source',
            ];
            $pages[$pageNumber] = [
                'image' => $image,
                'source' => 'next-statement-write-after-rollback-to',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => true,
            ];
            $operations[] = [
                'op' => 'write_next_statement_page',
                'statement' => $statementName,
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            $entry = $pages[$pageNumber] ?? null;
            $cacheHit = $entry !== null && ($entry['source_id'] ?? '') === $nextSourceId && ($entry['epoch'] ?? 0) === $nextEpoch;
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheHit,
                'source' => $cacheHit ? $entry['source'] : 'pager-read-miss',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => $cacheHit ? (bool) ($entry['dirty'] ?? false) : false,
                'matches_savepoint_before_image' => isset($savepointBefore[$pageNumber]) && $cacheHit && $entry['image'] === $savepointBefore[$pageNumber]['image'],
                'matches_next_statement_before_image' => isset($nextBefore[$pageNumber]) && $cacheHit && $entry['image'] === $nextBefore[$pageNumber]['image'],
            ];
            $operations[] = [
                'op' => $cacheHit ? 'read_current_source_cache_page' : 'read_current_source_cache_miss',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ];
        }

        ksort($pages, SORT_NUMERIC);

        return [
            'status' => 'pager_savepoint_hot_journal_cache_current_source_next128',
            'reason' => 'retry_statement_uses_hot_journal_recovered_cache_after_rollback_to_savepoint',
            'page_size' => $pageSize,
            'savepoint' => [
                'name' => $savepointName,
                'still_active_after_rollback_to' => true,
                'before_page_numbers' => array_keys($savepointBefore),
                'rollback_restored_page_numbers' => array_keys($savepointBefore),
            ],
            'statement' => [
                'name' => $statementName,
                'before_page_numbers' => array_keys($nextBefore),
                'write_page_numbers' => array_keys($nextStatementWrites),
            ],
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentSourceEpoch],
            'next_source' => ['id' => $nextSourceId, 'epoch' => $nextEpoch],
            'cache' => [
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'preserved_page_numbers' => array_column($preserved, 'page_number'),
                'preserved_entries' => $preserved,
                'hot_journal_page_numbers' => self::sortedKeys($hotJournalPages),
                'final_page_numbers' => array_keys($pages),
                'final_sources' => self::sources($pages),
                'final_source_ids' => self::sourceIds($pages),
                'dirty_page_numbers' => self::dirtyPageNumbers($pages),
            ],
            'savepoint_before_prefixes' => self::prefixes($savepointBefore),
            'next_statement_before_prefixes' => self::prefixes($nextBefore),
            'final_prefixes' => self::prefixes($pages),
            'reads' => $reads,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-savepoint-hot-journal-cache-current-source-next128',
                'sqlite-hot-journal-recovery-current-source-cache',
                'sqlite-rollback-to-savepoint-keeps-current-source-token',
                'sqlite-next-statement-subjournal-captures-recovered-source',
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
                throw new \InvalidArgumentException('SQLite pager savepoint hot-journal cache next128 page numbers are one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint hot-journal cache next128 page {$pageNumber} image must match page size");
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
                throw new \InvalidArgumentException("SQLite pager savepoint hot-journal cache next128 {$label} page numbers are one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint hot-journal cache next128 {$label} page {$pageNumber} image must match page size");
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
                throw new \InvalidArgumentException('SQLite pager savepoint hot-journal cache next128 read pages are one-based integers');
            }
        }
    }

    /**
     * @return array{image:string,source:string,source_id:string,epoch:int,dirty:bool}
     */
    private static function zeroPage(int $pageSize, string $sourceId, int $epoch): array
    {
        return [
            'image' => str_repeat("\0", $pageSize),
            'source' => 'zero-fill-current-source',
            'source_id' => $sourceId,
            'epoch' => $epoch,
            'dirty' => false,
        ];
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

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return array<int,string>
     */
    private static function prefixes(array $pages): array
    {
        $prefixes = [];
        foreach ($pages as $pageNumber => $entry) {
            $prefixes[$pageNumber] = rtrim(substr((string) $entry['image'], 0, 36), ".\0");
        }

        return $prefixes;
    }
}
