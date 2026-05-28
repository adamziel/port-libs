<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext163Plan
{
    /**
     * @param array<int,string> $currentPages
     * @param array<int,string> $nextPages
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
        array $nextPages,
        array $readerCache,
        array $readPages,
        string $currentSourceId,
        string $nextSourceId,
        int $currentEpoch,
        int $nextEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '' || $nextSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next163 requires non-empty paths and source ids');
        }
        if ($currentSourceId === $nextSourceId) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next163 requires distinct current and next source ids');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next163 requires current master-journal bytes');
        }
        if ($pageSize < 1 || $currentEpoch < 1 || $nextEpoch <= $currentEpoch) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next163 requires positive page size and increasing epochs');
        }
        if ($currentPages === [] || $nextPages === [] || $readerCache === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next163 requires current pages, next pages, reader cache, and reads');
        }

        $currentMembers = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $currentMembers, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next163 current master journal does not reference the database journal');
        }
        $cachedMembers = self::members($cachedMasterJournalBytes);
        $currentPages = self::assertPages($currentPages, $pageSize, 'current');
        $nextPages = self::assertPages($nextPages, $pageSize, 'next');
        $readerCache = self::assertCache($readerCache, $pageSize);
        self::assertPageList($readPages);

        $operations = [[
            'op' => 'read_current_master_journal_before_next_reader_source',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
        ]];
        if ($cachedMembers !== [] && $cachedMembers !== $currentMembers) {
            $operations[] = [
                'op' => 'discard_cached_master_journal_for_next_reader_source',
                'reason' => 'cached_master_journal_members_do_not_match_current_source',
            ];
        }

        $decisions = [];
        $blockers = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($nextPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next163 read page {$pageNumber} is outside next source");
            }
            $currentImage = $currentPages[$pageNumber] ?? null;
            $nextImage = $nextPages[$pageNumber];
            $entry = $readerCache[$pageNumber] ?? null;
            $currentDigest = $currentImage === null ? null : self::digest($currentImage);
            $nextDigest = self::digest($nextImage);
            $source = 'next-master-journal-reader-source';
            $cacheHit = false;
            $reason = 'reader_cache_missing_next_source_page';

            if ($entry !== null) {
                if ($entry['dirty']) {
                    $reason = 'dirty_reader_cache_blocks_next_source_read';
                    $blockers[] = ['page_number' => $pageNumber, 'reason' => $reason];
                } elseif ($entry['pinned'] && $currentDigest !== $nextDigest) {
                    $reason = 'pinned_reader_cache_blocks_changed_next_source';
                    $blockers[] = ['page_number' => $pageNumber, 'reason' => $reason];
                } elseif ($entry['master_members'] !== [] && $entry['master_members'] !== $currentMembers) {
                    $reason = 'reader_cache_master_members_mismatch';
                } elseif ($entry['source_id'] !== $currentSourceId || $entry['epoch'] !== $currentEpoch) {
                    $reason = 'reader_cache_current_source_token_mismatch';
                } elseif ($currentImage === null) {
                    $reason = 'reader_cache_page_absent_from_current_source';
                } elseif (!hash_equals($currentDigest, self::digest($entry['image']))) {
                    $reason = 'reader_cache_does_not_match_recovered_current_source';
                } elseif (hash_equals($currentDigest, $nextDigest)) {
                    $source = $entry['source'];
                    $cacheHit = true;
                    $reason = 'reader_cache_reused_for_unchanged_next_source_page';
                } else {
                    $reason = 'next_source_page_changed_after_master_journal_recovery';
                }
            }

            $decisions[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheHit,
                'source' => $source,
                'reason' => $reason,
                'current_digest' => $currentDigest,
                'next_digest' => $nextDigest,
                'next_prefix' => self::prefix($nextImage),
                'current_matches_next' => $currentDigest !== null && hash_equals($currentDigest, $nextDigest),
                'blocked' => str_contains($reason, 'blocks'),
            ];
            $operations[] = [
                'op' => $cacheHit ? 'read_next_reader_source_from_current_cache' : 'read_next_reader_source_from_next_pages',
                'page_number' => $pageNumber,
                'reason' => $reason,
            ];
        }

        return [
            'status' => 'pager_master_journal_reader_cache_current_source_next163',
            'reason' => 'reader_cache_pages_are_reused_only_when_the_recovered_current_source_still_matches_the_next_source',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'cached_members' => $cachedMembers,
            'current_members' => $currentMembers,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch, 'digests' => self::digests($currentPages)],
            'next_source' => ['id' => $nextSourceId, 'epoch' => $nextEpoch, 'digests' => self::digests($nextPages)],
            'decisions' => $decisions,
            'read_page_numbers' => array_column($decisions, 'page_number'),
            'read_cache_hits' => array_column($decisions, 'cache_hit', 'page_number'),
            'read_sources' => array_column($decisions, 'source', 'page_number'),
            'read_reasons' => array_column($decisions, 'reason', 'page_number'),
            'blocked_page_numbers' => array_column($blockers, 'page_number'),
            'blockers' => $blockers,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next163',
                'sqlite-master-journal-current-to-next-reader-source',
                'sqlite-pager-cache-next-source-digest',
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
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next163 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next163 {$label} page {$pageNumber} image must match page size");
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
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next163 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next163 cache page {$pageNumber} image must match page size");
            }
            $epoch = $entry['epoch'] ?? 0;
            if (!is_int($epoch) || $epoch < 0) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next163 cache page {$pageNumber} epoch must be non-negative");
            }
            $members = $entry['master_members'] ?? [];
            if (!is_array($members)) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next163 cache page {$pageNumber} members must be a list");
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
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next163 read pages must be one-based integers');
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
