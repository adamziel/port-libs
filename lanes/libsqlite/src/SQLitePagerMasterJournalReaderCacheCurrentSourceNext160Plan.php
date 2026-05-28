<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext160Plan
{
    /**
     * @param array<int,string> $currentPages
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,master_members?:list<string>}> $readerCache
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        string $currentMasterJournalBytes,
        int $pageSize,
        array $currentPages,
        array $readerCache,
        array $readPages,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next160 requires non-empty paths and current source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next160 requires current master-journal bytes');
        }
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next160 page size must be positive');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next160 epoch must be positive');
        }
        if ($currentPages === [] || $readerCache === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next160 requires current pages, reader cache, and read pages');
        }

        $currentMembers = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $currentMembers, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next160 current master journal does not reference the database journal');
        }
        $cachedMembers = self::members($cachedMasterJournalBytes);
        $cacheStale = $cachedMembers !== $currentMembers;
        $currentPages = self::assertPages($currentPages, $pageSize, 'current');
        $readerCache = self::assertCache($readerCache, $pageSize);
        self::assertPageList($readPages);

        $operations = [[
            'op' => 'read_current_master_journal_for_reader_cache',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
            'reason' => 'reader_cache_must_be_fenced_by_current_master_journal_source',
        ]];
        if ($cacheStale) {
            $operations[] = [
                'op' => 'discard_cached_master_journal_reader_members',
                'path' => $masterJournalPath,
                'reason' => 'cached_master_journal_members_do_not_match_current_source',
            ];
        }

        $retained = [];
        $invalidated = [];
        $cacheRows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            $reason = null;
            $sourceId = $entry['source_id'];
            $epoch = $entry['epoch'];
            $members = $entry['master_members'];
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_from_failed_writer';
            } elseif ($entry['pinned'] && $cacheStale) {
                $reason = 'pinned_reader_cache_uses_stale_master_members';
            } elseif ($members !== [] && $members !== $currentMembers) {
                $reason = 'reader_cache_master_members_mismatch';
            } elseif ($sourceId !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch';
            } elseif ($epoch !== $currentEpoch) {
                $reason = 'reader_cache_source_epoch_mismatch';
            } elseif (!isset($currentPages[$pageNumber])) {
                $reason = 'reader_cache_page_absent_from_current_source';
            } elseif (!hash_equals(self::digest($currentPages[$pageNumber]), self::digest($entry['image']))) {
                $reason = 'reader_cache_image_mismatch';
            }

            $cacheRows[$pageNumber] = [
                'page_number' => $pageNumber,
                'source' => $entry['source'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'master_members_match' => $members === [] || $members === $currentMembers,
                'image_matches_current_source' => isset($currentPages[$pageNumber]) && hash_equals(self::digest($currentPages[$pageNumber]), self::digest($entry['image'])),
                'reason' => $reason,
            ];

            if ($reason !== null) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $entry['source'],
                    'source_id' => $sourceId,
                    'epoch' => $epoch,
                    'dirty' => $entry['dirty'],
                    'pinned' => $entry['pinned'],
                    'reason' => $reason,
                ];
                $operations[] = [
                    'op' => 'invalidate_master_journal_reader_cache_page',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                ];
                continue;
            }

            $retained[$pageNumber] = $entry;
            $operations[] = [
                'op' => 'retain_master_journal_reader_cache_page',
                'page_number' => $pageNumber,
                'digest' => self::digest($entry['image']),
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next160 read page {$pageNumber} is outside current source");
            }
            $cacheHit = isset($retained[$pageNumber]);
            $image = $cacheHit ? $retained[$pageNumber]['image'] : $currentPages[$pageNumber];
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheHit,
                'source' => $cacheHit ? $retained[$pageNumber]['source'] : 'current-master-journal-reader-source',
                'source_id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'prefix' => self::prefix($image),
                'digest' => self::digest($image),
                'matches_current_source_digest' => hash_equals(self::digest($currentPages[$pageNumber]), self::digest($image)),
            ];
            $operations[] = [
                'op' => $cacheHit ? 'read_master_journal_reader_cache_hit' : 'read_master_journal_reader_cache_miss_current_source',
                'page_number' => $pageNumber,
            ];
        }

        ksort($retained, SORT_NUMERIC);
        ksort($cacheRows, SORT_NUMERIC);

        return [
            'status' => 'pager_master_journal_reader_cache_current_source_next160',
            'reason' => 'reader_cache_pages_are_fenced_by_current_master_journal_membership_and_page_digests',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'cached_members' => $cachedMembers,
            'current_members' => $currentMembers,
            'cache_stale_rejected' => $cacheStale,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'page_numbers' => array_keys($currentPages),
                'digests' => self::digests($currentPages),
            ],
            'cache' => [
                'retained_page_numbers' => array_keys($retained),
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'rows' => array_values($cacheRows),
            ],
            'reads' => $reads,
            'read_page_numbers' => array_column($reads, 'page_number'),
            'read_cache_hits' => array_column($reads, 'cache_hit', 'page_number'),
            'read_prefixes' => array_column($reads, 'prefix', 'page_number'),
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next160',
                'sqlite-master-journal-current-source-reader-fence',
                'sqlite-pager-cache-current-source-digest',
            ],
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function assertPages(array $pages, int $pageSize, string $label): array
    {
        ksort($pages, SORT_NUMERIC);
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next160 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next160 {$label} page {$pageNumber} image must match page size");
            }
        }

        return $pages;
    }

    /**
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,master_members?:list<string>}> $cache
     * @return array<int,array{image:string,source:string,source_id:string,epoch:int,dirty:bool,pinned:bool,master_members:list<string>}>
     */
    private static function assertCache(array $cache, int $pageSize): array
    {
        ksort($cache, SORT_NUMERIC);
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next160 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next160 cache page {$pageNumber} image must match page size");
            }
            $epoch = $entry['epoch'] ?? 0;
            if (!is_int($epoch) || $epoch < 0) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next160 cache page {$pageNumber} epoch must be non-negative");
            }
            $members = $entry['master_members'] ?? [];
            if (!is_array($members)) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next160 cache page {$pageNumber} members must be a list");
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source' => isset($entry['source']) && is_string($entry['source']) && $entry['source'] !== '' ? $entry['source'] : 'master-journal-reader-cache',
                'source_id' => isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '',
                'epoch' => $epoch,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'master_members' => array_values(array_map('strval', $members)),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next160 read pages must be one-based integers');
            }
        }
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
        return hash('sha256', $image);
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
