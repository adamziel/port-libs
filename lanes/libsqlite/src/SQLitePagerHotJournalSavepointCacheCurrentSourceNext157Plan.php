<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalSavepointCacheCurrentSourceNext157Plan
{
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
    public static function currentSourceNext(
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

        self::assertCache($cachePages, $pageSize);
        self::assertPages($hotJournalPages, $pageSize, 'hot-journal');
        self::assertPages($currentSourcePages, $pageSize, 'current-source');
        self::assertPages($savepointWrites, $pageSize, 'savepoint-write');
        self::assertPageList($rollbackPages, 'rollback');
        self::assertPages($retryWrites, $pageSize, 'retry-write');
        self::assertPageList($readPages, 'read');

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
        $currentDigests = self::digests($currentImages);

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
            } elseif (!hash_equals(self::digest($currentImages[$pageNumber]), self::digest($entry['image']))) {
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

            $pages[$pageNumber] = self::pageEntry($entry['image'], $source, $recoveredSourceId, $recoveredEpoch, false);
            $retained[] = [
                'page_number' => $pageNumber,
                'source' => $source,
                'digest' => self::digest($entry['image']),
            ];
            $operations[] = [
                'op' => 'retain_cache_page_matching_recovered_current_source',
                'page_number' => $pageNumber,
                'digest' => self::digest($entry['image']),
            ];
        }

        foreach ($currentImages as $pageNumber => $image) {
            if (!isset($pages[$pageNumber])) {
                $pages[$pageNumber] = self::pageEntry($image, isset($hotJournalPages[$pageNumber]) ? 'hot-journal-recovered-current-source' : 'database-current-source', $recoveredSourceId, $recoveredEpoch, false);
                $operations[] = [
                    'op' => isset($hotJournalPages[$pageNumber]) ? 'install_hot_journal_current_source_page' : 'install_database_current_source_page',
                    'page_number' => $pageNumber,
                ];
            }
        }

        $savepointBefore = [];
        foreach ($savepointWrites as $pageNumber => $image) {
            $before = $pages[$pageNumber] ?? self::zeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
            if (($before['source_id'] ?? '') !== $recoveredSourceId || ($before['epoch'] ?? 0) !== $recoveredEpoch || ($before['dirty'] ?? false) === true) {
                throw new \RuntimeException("SQLite pager hot-journal savepoint cache next157 page {$pageNumber} is not a clean recovered current-source page");
            }
            $savepointBefore[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_savepoint_before_image_from_recovered_current_source',
                'savepoint' => $savepoint,
                'page_number' => $pageNumber,
                'source' => $before['source'],
                'digest' => self::digest($before['image']),
            ];
            $pages[$pageNumber] = self::pageEntry($image, 'failed-savepoint-write', $recoveredSourceId, $recoveredEpoch, true);
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
            $pages[$pageNumber] = self::pageEntry($before['image'], 'rollback-to-recovered-current-source-before-image', $recoveredSourceId, $recoveredEpoch, false);
            $rollbackRestored[$pageNumber] = $pages[$pageNumber];
            $operations[] = [
                'op' => 'rollback_to_restores_recovered_current_source_before_image',
                'savepoint' => $savepoint,
                'page_number' => $pageNumber,
            ];
        }

        $readRows = [];
        foreach ($readPages as $pageNumber) {
            $entry = $pages[$pageNumber] ?? self::zeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
            $digest = self::digest($entry['image']);
            $readRows[] = [
                'page_number' => $pageNumber,
                'cache_hit' => isset($pages[$pageNumber]) && ($entry['source_id'] ?? '') === $recoveredSourceId && ($entry['epoch'] ?? 0) === $recoveredEpoch && ($entry['dirty'] ?? false) === false,
                'source' => $entry['source'],
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'prefix' => self::prefix($entry['image']),
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
            $before = $pages[$pageNumber] ?? self::zeroPage($pageSize, $recoveredSourceId, $recoveredEpoch);
            if (($before['source_id'] ?? '') !== $recoveredSourceId || ($before['epoch'] ?? 0) !== $recoveredEpoch || ($before['dirty'] ?? false) === true) {
                throw new \RuntimeException("SQLite pager hot-journal savepoint cache next157 retry page {$pageNumber} is not a clean recovered current-source page");
            }
            $retryBefore[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_retry_before_image_after_source_fence',
                'page_number' => $pageNumber,
                'source' => $before['source'],
                'digest' => self::digest($before['image']),
            ];
            $pages[$pageNumber] = self::pageEntry($image, 'retry-write-after-source-fenced-rollback', $recoveredSourceId, $recoveredEpoch, true);
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
                'page_numbers' => self::sortedKeys($currentImages),
                'digests' => $currentDigests,
            ],
            'cache' => [
                'retained_page_numbers' => array_column($retained, 'page_number'),
                'retained_entries' => $retained,
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
            ],
            'savepoint_before_page_numbers' => self::sortedKeys($savepointBefore),
            'savepoint_before_prefixes' => self::prefixes($savepointBefore),
            'rollback_restored_prefixes' => self::prefixes($rollbackRestored),
            'read_pages' => $readRows,
            'retry_before_page_numbers' => self::sortedKeys($retryBefore),
            'retry_before_prefixes' => self::prefixes($retryBefore),
            'final_page_numbers' => self::sortedKeys($pages),
            'final_sources' => self::sources($pages),
            'final_prefixes' => self::prefixes($pages),
            'dirty_page_numbers' => self::dirtyPageNumbers($pages),
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
    private static function assertCache(array $cache, int $pageSize): void
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
    private static function assertPages(array $pages, int $pageSize, string $label): void
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
    private static function assertPageList(array $pages, string $label): void
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
    private static function pageEntry(string $image, string $source, string $sourceId, int $epoch, bool $dirty): array
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
    private static function zeroPage(int $pageSize, string $sourceId, int $epoch): array
    {
        return self::pageEntry(str_repeat("\0", $pageSize), 'zero-fill-recovered-current-source', $sourceId, $epoch, false);
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
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function digests(array $pages): array
    {
        $digests = [];
        foreach ($pages as $pageNumber => $image) {
            $digests[$pageNumber] = self::digest($image);
        }

        return $digests;
    }

    private static function digest(string $image): string
    {
        return substr(hash('sha256', $image), 0, 16);
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
