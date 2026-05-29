<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan
{
    /**
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,dirty?:bool,pin?:string|null}> $currentCache
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $outerSavepointBefore
     * @param array<int,string> $innerSavepointBefore
     * @param array<int,string> $nextStatementWrites
     * @param list<int> $cursorPages
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
        int $pageSize,
        string $outerSavepoint,
        string $innerSavepoint,
        string $nextStatement,
        string $currentSourceId,
        string $recoveredSourceId,
        array $currentCache,
        array $hotJournalPages,
        array $outerSavepointBefore,
        array $innerSavepointBefore,
        array $nextStatementWrites,
        array $cursorPages,
        int $currentEpoch = 1,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache savepoint next131 page size must be positive');
        }
        foreach ([$outerSavepoint, $innerSavepoint, $nextStatement, $currentSourceId, $recoveredSourceId] as $value) {
            if ($value === '') {
                throw new \InvalidArgumentException('SQLite pager hot-journal cache savepoint next131 names and source ids must be non-empty');
            }
        }
        if ($outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache savepoint next131 savepoint names must differ');
        }
        if ($currentSourceId === $recoveredSourceId) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache savepoint next131 source id must change after hot-journal recovery');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache savepoint next131 epoch must be positive');
        }
        if ($hotJournalPages === [] || $outerSavepointBefore === [] || $innerSavepointBefore === [] || $nextStatementWrites === [] || $cursorPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache savepoint next131 requires hot pages, savepoint images, next writes, and cursor pages');
        }

        self::assertCache($currentCache, $pageSize);
        self::assertPages($hotJournalPages, $pageSize, 'hot-journal');
        self::assertPages($outerSavepointBefore, $pageSize, 'outer-savepoint');
        self::assertPages($innerSavepointBefore, $pageSize, 'inner-savepoint');
        self::assertPages($nextStatementWrites, $pageSize, 'next-statement');
        self::assertPageList($cursorPages);

        $recoveredEpoch = $currentEpoch + 1;
        $pages = [];
        $invalidated = [];
        $retagged = [];
        $operations = [];

        foreach ($currentCache as $pageNumber => $entry) {
            $source = (string) ($entry['source'] ?? 'unknown');
            $sourceId = (string) ($entry['source_id'] ?? '');
            $epoch = (int) ($entry['epoch'] ?? 0);
            $dirty = (bool) ($entry['dirty'] ?? false);
            $pin = isset($entry['pin']) ? (string) $entry['pin'] : null;
            $reason = null;

            if (array_key_exists($pageNumber, $hotJournalPages)) {
                $reason = 'hot_journal_replaces_cached_page';
            } elseif ($dirty) {
                $reason = 'dirty_inner_savepoint_cache_discarded';
            } elseif ($sourceId !== $currentSourceId) {
                $reason = 'stale_source_token_discarded';
            } elseif ($epoch !== $currentEpoch) {
                $reason = 'stale_source_epoch_discarded';
            }

            if ($reason !== null) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'epoch' => $epoch,
                    'dirty' => $dirty,
                    'pin' => $pin,
                    'reason' => $reason,
                ];
                continue;
            }

            $pages[$pageNumber] = [
                'image' => $entry['image'],
                'source' => $source,
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
                'dirty' => false,
                'pin' => $pin,
            ];
            $retagged[] = [
                'page_number' => $pageNumber,
                'source' => $source,
                'old_source_id' => $sourceId,
                'new_source_id' => $recoveredSourceId,
                'pin' => $pin,
            ];
            $operations[] = [
                'op' => 'retag_clean_cache_page',
                'page_number' => $pageNumber,
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
            ];
        }

        foreach ($hotJournalPages as $pageNumber => $image) {
            $pages[$pageNumber] = [
                'image' => $image,
                'source' => 'hot-journal-recovered-page',
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
                'dirty' => false,
                'pin' => null,
            ];
            $operations[] = [
                'op' => 'install_hot_journal_page',
                'page_number' => $pageNumber,
                'source_id' => $recoveredSourceId,
            ];
        }

        $savepointBefore = [];
        foreach ($outerSavepointBefore as $pageNumber => $image) {
            $savepointBefore[$outerSavepoint][$pageNumber] = self::pageEntry($image, 'outer-savepoint-before-image', $recoveredSourceId, $recoveredEpoch, false, $outerSavepoint);
            $operations[] = [
                'op' => 'restore_outer_savepoint_before_image',
                'savepoint' => $outerSavepoint,
                'page_number' => $pageNumber,
            ];
            $pages[$pageNumber] = $savepointBefore[$outerSavepoint][$pageNumber];
        }

        foreach ($innerSavepointBefore as $pageNumber => $image) {
            $savepointBefore[$innerSavepoint][$pageNumber] = self::pageEntry($image, 'inner-savepoint-before-image', $recoveredSourceId, $recoveredEpoch, false, $innerSavepoint);
            $operations[] = [
                'op' => 'rollback_to_inner_savepoint_before_image',
                'savepoint' => $innerSavepoint,
                'page_number' => $pageNumber,
                'reason' => 'inner_savepoint_rollback_keeps_recovered_source_token',
            ];
            $pages[$pageNumber] = $savepointBefore[$innerSavepoint][$pageNumber];
        }

        $cursorReads = [];
        foreach ($cursorPages as $pageNumber) {
            $entry = $pages[$pageNumber] ?? self::zeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
            $sourceOk = ($entry['source_id'] ?? '') === $recoveredSourceId && ($entry['epoch'] ?? 0) === $recoveredEpoch;
            $cursorReads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => isset($pages[$pageNumber]) && $sourceOk,
                'source' => $sourceOk ? (string) $entry['source'] : 'pager-cache-miss',
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
                'pin' => $entry['pin'] ?? null,
                'matches_outer_before_image' => isset($savepointBefore[$outerSavepoint][$pageNumber]) && $entry['image'] === $savepointBefore[$outerSavepoint][$pageNumber]['image'],
                'matches_inner_before_image' => isset($savepointBefore[$innerSavepoint][$pageNumber]) && $entry['image'] === $savepointBefore[$innerSavepoint][$pageNumber]['image'],
            ];
            $operations[] = [
                'op' => isset($pages[$pageNumber]) && $sourceOk ? 'cursor_read_recovered_current_source' : 'cursor_read_zero_fill_current_source',
                'page_number' => $pageNumber,
                'source_id' => $recoveredSourceId,
            ];
        }

        $nextBefore = [];
        foreach ($nextStatementWrites as $pageNumber => $image) {
            $before = $pages[$pageNumber] ?? self::zeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
            if (($before['source_id'] ?? '') !== $recoveredSourceId || ($before['epoch'] ?? 0) !== $recoveredEpoch || ($before['dirty'] ?? false) === true) {
                throw new \RuntimeException("SQLite pager hot-journal cache savepoint next131 page {$pageNumber} is not recovered-current clean");
            }
            $nextBefore[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_next_statement_before_image',
                'statement' => $nextStatement,
                'page_number' => $pageNumber,
                'source' => $before['source'],
            ];
            $pages[$pageNumber] = self::pageEntry($image, 'next-statement-write-after-savepoint-rollback', $recoveredSourceId, $recoveredEpoch, true, null);
            $operations[] = [
                'op' => 'write_next_statement_page',
                'statement' => $nextStatement,
                'page_number' => $pageNumber,
            ];
        }

        ksort($pages, SORT_NUMERIC);

        return [
            'status' => 'pager_hot_journal_cache_savepoint_current_source_next131',
            'reason' => 'hot_journal_recovery_retags_page_cache_before_savepoint_cursor_and_next_statement',
            'page_size' => $pageSize,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'recovered_source' => ['id' => $recoveredSourceId, 'epoch' => $recoveredEpoch],
            'savepoints' => [
                'outer' => ['name' => $outerSavepoint, 'active_after_rollback_to_inner' => true, 'page_numbers' => self::sortedKeys($outerSavepointBefore)],
                'inner' => ['name' => $innerSavepoint, 'active_after_rollback_to_inner' => true, 'page_numbers' => self::sortedKeys($innerSavepointBefore)],
            ],
            'cache' => [
                'hot_journal_page_numbers' => self::sortedKeys($hotJournalPages),
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'retagged_page_numbers' => array_column($retagged, 'page_number'),
                'retagged_entries' => $retagged,
                'final_page_numbers' => array_keys($pages),
                'final_sources' => self::sources($pages),
                'final_source_ids' => self::sourceIds($pages),
                'dirty_page_numbers' => self::dirtyPageNumbers($pages),
            ],
            'cursor_reads' => $cursorReads,
            'next_statement' => [
                'name' => $nextStatement,
                'before_page_numbers' => array_keys($nextBefore),
                'write_page_numbers' => self::sortedKeys($nextStatementWrites),
            ],
            'outer_before_prefixes' => self::prefixes($savepointBefore[$outerSavepoint] ?? []),
            'inner_before_prefixes' => self::prefixes($savepointBefore[$innerSavepoint] ?? []),
            'next_before_prefixes' => self::prefixes($nextBefore),
            'final_prefixes' => self::prefixes($pages),
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-hot-journal-cache-savepoint-current-source-next131',
                'sqlite-hot-journal-cache-source-retag',
                'sqlite-savepoint-cursor-current-source-refresh',
                'sqlite-next-statement-captures-after-savepoint-source-refresh',
            ],
        ];
    }

    /**
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,dirty?:bool,pin?:string|null}> $cache
     */
    private static function assertCache(array $cache, int $pageSize): void
    {
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal cache savepoint next131 cache pages must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache savepoint next131 cache page {$pageNumber} image must match page size");
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
                throw new \InvalidArgumentException("SQLite pager hot-journal cache savepoint next131 {$label} pages must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache savepoint next131 {$label} page {$pageNumber} image must match page size");
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
                throw new \InvalidArgumentException('SQLite pager hot-journal cache savepoint next131 cursor pages must be one-based integers');
            }
        }
    }

    /**
     * @return array{image:string,source:string,source_id:string,epoch:int,dirty:bool,pin:string|null}
     */
    private static function pageEntry(string $image, string $source, string $sourceId, int $epoch, bool $dirty, ?string $pin): array
    {
        return [
            'image' => $image,
            'source' => $source,
            'source_id' => $sourceId,
            'epoch' => $epoch,
            'dirty' => $dirty,
            'pin' => $pin,
        ];
    }

    /**
     * @return array{image:string,source:string,source_id:string,epoch:int,dirty:bool,pin:null}
     */
    private static function zeroPage(int $pageSize, string $sourceId, int $epoch): array
    {
        return self::pageEntry(str_repeat("\0", $pageSize), 'zero-fill-current-source', $sourceId, $epoch, false, null);
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
            $prefixes[$pageNumber] = rtrim(substr((string) $entry['image'], 0, 40), ".\0");
        }

        return $prefixes;
    }
}
