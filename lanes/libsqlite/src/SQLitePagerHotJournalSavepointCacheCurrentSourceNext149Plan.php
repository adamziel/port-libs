<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalSavepointCacheCurrentSourceNext149Plan
{
    /**
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,savepoint?:string|null}> $cachePages
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,string> $nextStatementWrites
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
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

        self::assertCache($cachePages, $pageSize);
        self::assertPages($hotJournalPages, $pageSize, 'hot-journal');
        self::assertPages($savepointBeforePages, $pageSize, 'savepoint-before');
        self::assertPages($nextStatementWrites, $pageSize, 'next-statement');
        self::assertPageList($readPages);

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

            $pages[$pageNumber] = self::pageEntry($entry['image'], $source, $recoveredSourceId, $recoveredEpoch, false, $entrySavepoint);
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
            $pages[$pageNumber] = self::pageEntry($image, 'hot-journal-recovered-page', $recoveredSourceId, $recoveredEpoch, false, null);
            $operations[] = [
                'op' => 'install_hot_journal_recovered_page',
                'page_number' => $pageNumber,
            ];
        }

        foreach ($savepointBeforePages as $pageNumber => $image) {
            $pages[$pageNumber] = self::pageEntry($image, 'savepoint-before-image-after-hot-journal', $recoveredSourceId, $recoveredEpoch, false, $savepoint);
            $operations[] = [
                'op' => 'restore_savepoint_before_image',
                'savepoint' => $savepoint,
                'page_number' => $pageNumber,
            ];
        }

        $readRows = [];
        foreach ($readPages as $pageNumber) {
            $entry = $pages[$pageNumber] ?? self::zeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
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
                'prefix' => self::prefix((string) $entry['image']),
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
            $before = $pages[$pageNumber] ?? self::zeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
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
            $pages[$pageNumber] = self::pageEntry($image, 'next-statement-write-after-recovered-savepoint', $recoveredSourceId, $recoveredEpoch, true, null);
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
                'write_page_numbers' => self::sortedKeys($nextStatementWrites),
            ],
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'recovered_source' => ['id' => $recoveredSourceId, 'epoch' => $recoveredEpoch],
            'cache' => [
                'retained_page_numbers' => array_column($retained, 'page_number'),
                'retained_entries' => $retained,
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'hot_journal_page_numbers' => self::sortedKeys($hotJournalPages),
                'savepoint_before_page_numbers' => self::sortedKeys($savepointBeforePages),
                'final_page_numbers' => array_keys($pages),
                'final_sources' => self::sources($pages),
                'final_source_ids' => self::sourceIds($pages),
                'dirty_page_numbers' => self::dirtyPageNumbers($pages),
            ],
            'read_pages' => $readRows,
            'next_before_prefixes' => self::prefixes($nextBefore),
            'final_prefixes' => self::prefixes($pages),
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
    private static function assertCache(array $cache, int $pageSize): void
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
    private static function assertPages(array $pages, int $pageSize, string $label): void
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
    private static function assertPageList(array $pages): void
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
    private static function pageEntry(string $image, string $source, string $sourceId, int $epoch, bool $dirty, ?string $savepoint): array
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
    private static function zeroPage(int $pageSize, string $sourceId, int $epoch): array
    {
        return self::pageEntry(str_repeat("\0", $pageSize), 'zero-fill-recovered-current-source', $sourceId, $epoch, false, null);
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
     * @param array<int,array{source:string}> $pages
     * @return array<int,string>
     */
    private static function sources(array $pages): array
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
    private static function sourceIds(array $pages): array
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
    private static function dirtyPageNumbers(array $pages): array
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
    private static function prefixes(array $pages): array
    {
        $prefixes = [];
        foreach ($pages as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::prefix($entry['image']);
        }

        return $prefixes;
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 48), ".\0");
    }
}
