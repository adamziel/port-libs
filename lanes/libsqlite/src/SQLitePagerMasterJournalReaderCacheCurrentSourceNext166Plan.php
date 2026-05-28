<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext166Plan
{
    /**
     * @param array<int,string> $currentPages
     * @param array<int,string> $nextPages
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,generation?:int,schema_cookie?:int,page_count?:int,dirty?:bool,pinned?:bool,master_digest?:string}> $readerCache
     * @param list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,generation?:int,schema_cookie?:int,page_count?:int}> $nextReads
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        int $pageSize,
        array $currentPages,
        array $nextPages,
        array $readerCache,
        array $nextReads,
        string $currentSourceId,
        string $nextSourceId,
        int $currentEpoch,
        int $nextEpoch,
        int $currentGeneration,
        int $nextGeneration,
        int $currentSchemaCookie,
        int $nextSchemaCookie,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '' || $nextSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next166 requires paths and source ids');
        }
        if ($currentSourceId === $nextSourceId) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next166 requires a distinct next source');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next166 requires current master-journal bytes');
        }
        if ($pageSize < 1 || $currentEpoch < 1 || $nextEpoch <= $currentEpoch || $currentGeneration < 1 || $nextGeneration <= $currentGeneration) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next166 requires positive increasing epochs and generations');
        }
        if ($currentSchemaCookie < 0 || $nextSchemaCookie < 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next166 schema cookies must be non-negative');
        }
        if ($currentPages === [] || $nextPages === [] || $readerCache === [] || $nextReads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next166 requires current pages, next pages, reader cache, and reads');
        }

        $members = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next166 current master journal does not reference the database journal');
        }
        $currentPages = self::assertPages($currentPages, $pageSize, 'current');
        $nextPages = self::assertPages($nextPages, $pageSize, 'next');
        $readerCache = self::assertCache($readerCache, $pageSize);
        $nextReads = self::assertReads($nextReads);

        $currentPageCount = max(array_keys($currentPages));
        $nextPageCount = max(array_keys($nextPages));
        $masterDigest = self::digest($currentMasterJournalBytes);
        $schemaChanged = $currentSchemaCookie !== $nextSchemaCookie;
        $truncated = $nextPageCount < $currentPageCount;

        $operations = [[
            'op' => 'read_current_master_journal_before_reader_cache_next166',
            'path' => $masterJournalPath,
            'digest' => $masterDigest,
            'members' => $members,
        ]];

        $cacheRows = [];
        $reusable = [];
        $invalidated = [];
        foreach ($readerCache as $pageNumber => $entry) {
            $currentImage = $currentPages[$pageNumber] ?? null;
            $nextImage = $nextPages[$pageNumber] ?? null;
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_page_cannot_seed_next_source';
            } elseif ($entry['pinned'] && ($nextImage === null || $currentImage === null || !hash_equals(self::digest($currentImage), self::digest($nextImage)))) {
                $reason = 'pinned_reader_cache_changed_or_truncated_next_source';
            } elseif ($entry['source_id'] !== $currentSourceId || $entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_source_token_is_not_current';
            } elseif ($entry['generation'] !== $currentGeneration) {
                $reason = 'reader_cache_generation_is_not_current';
            } elseif ($entry['schema_cookie'] !== $currentSchemaCookie) {
                $reason = 'reader_cache_schema_cookie_is_not_current';
            } elseif ($entry['page_count'] !== $currentPageCount) {
                $reason = 'reader_cache_page_count_is_not_current';
            } elseif ($entry['master_digest'] !== '' && !hash_equals($masterDigest, $entry['master_digest'])) {
                $reason = 'reader_cache_master_digest_is_not_current';
            } elseif ($currentImage === null) {
                $reason = 'reader_cache_page_absent_from_current_source';
            } elseif (!hash_equals(self::digest($currentImage), self::digest($entry['image']))) {
                $reason = 'reader_cache_image_is_not_current_source';
            } elseif ($nextImage === null) {
                $reason = 'reader_cache_page_truncated_from_next_source';
            } elseif (!hash_equals(self::digest($currentImage), self::digest($nextImage))) {
                $reason = 'reader_cache_page_changed_in_next_source';
            }

            $row = [
                'page_number' => $pageNumber,
                'source' => $entry['source'],
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'generation' => $entry['generation'],
                'schema_cookie' => $entry['schema_cookie'],
                'page_count' => $entry['page_count'],
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'current_digest' => $currentImage === null ? null : self::digest($currentImage),
                'next_digest' => $nextImage === null ? null : self::digest($nextImage),
                'reason' => $reason ?? 'reader_cache_page_reusable_for_next_source',
            ];
            $cacheRows[$pageNumber] = $row;

            if ($reason === null) {
                $reusable[$pageNumber] = $entry;
                $operations[] = [
                    'op' => 'retain_reader_cache_page_for_next166',
                    'page_number' => $pageNumber,
                    'generation' => $entry['generation'],
                ];
                continue;
            }

            $invalidated[$pageNumber] = $row;
            $operations[] = [
                'op' => 'invalidate_reader_cache_page_for_next166',
                'page_number' => $pageNumber,
                'reason' => $reason,
            ];
        }

        $reads = [];
        $reopenReaders = [];
        foreach ($nextReads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($nextPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next166 read page {$pageNumber} is outside next source");
            }
            $ticketCurrent = ($read['source_id'] ?? $nextSourceId) === $nextSourceId
                && ($read['epoch'] ?? $nextEpoch) === $nextEpoch
                && ($read['generation'] ?? $nextGeneration) === $nextGeneration
                && ($read['schema_cookie'] ?? $nextSchemaCookie) === $nextSchemaCookie
                && ($read['page_count'] ?? $nextPageCount) === $nextPageCount;
            $cacheHit = $ticketCurrent && isset($reusable[$pageNumber]);
            if (!$ticketCurrent || isset($invalidated[$pageNumber])) {
                $reopenReaders[$read['reader_id']] = $read['reader_id'];
            }
            $image = $cacheHit ? $reusable[$pageNumber]['image'] : $nextPages[$pageNumber];
            $reads[] = [
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
                'ticket_current' => $ticketCurrent,
                'cache_hit' => $cacheHit,
                'source' => $cacheHit ? 'reader-cache-current-source-next166' : 'next-master-journal-current-source',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'generation' => $nextGeneration,
                'schema_cookie' => $nextSchemaCookie,
                'page_count' => $nextPageCount,
                'prefix' => self::prefix($image),
                'digest' => self::digest($image),
            ];
            $operations[] = [
                'op' => $cacheHit ? 'reader_cache_next166_hit' : 'reader_cache_next166_reopen',
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
            ];
        }

        ksort($cacheRows, SORT_NUMERIC);
        ksort($reusable, SORT_NUMERIC);
        ksort($invalidated, SORT_NUMERIC);

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next166',
            'reason' => 'next reader cache is reused only after master-journal recovery when generation schema and page-count fences still match',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'master_members' => $members,
            'master_digest' => $masterDigest,
            'page_size' => $pageSize,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'generation' => $currentGeneration,
                'schema_cookie' => $currentSchemaCookie,
                'page_count' => $currentPageCount,
            ],
            'next_source' => [
                'id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'generation' => $nextGeneration,
                'schema_cookie' => $nextSchemaCookie,
                'page_count' => $nextPageCount,
            ],
            'schema_changed' => $schemaChanged,
            'page_count_truncated' => $truncated,
            'cache_rows' => array_values($cacheRows),
            'reusable_page_numbers' => array_keys($reusable),
            'invalidated_page_numbers' => array_keys($invalidated),
            'invalidated_reasons' => array_column($invalidated, 'reason', 'page_number'),
            'reads' => $reads,
            'read_cache_hits' => array_column($reads, 'cache_hit', 'reader_id'),
            'read_prefixes' => array_column($reads, 'prefix', 'reader_id'),
            'reopen_reader_ids' => array_values($reopenReaders),
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next166',
                'sqlite-master-journal-reader-cache-generation-fence',
                'sqlite-master-journal-reader-cache-schema-pagecount-fence',
                'sqlite-pager-master-journal-reader-cache-current-source-next163',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function members(string $bytes): array
    {
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
    private static function assertPages(array $pages, int $pageSize, string $label): array
    {
        ksort($pages, SORT_NUMERIC);
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next166 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next166 {$label} page {$pageNumber} image must match page size");
            }
        }

        return $pages;
    }

    /**
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,generation?:int,schema_cookie?:int,page_count?:int,dirty?:bool,pinned?:bool,master_digest?:string}> $cache
     * @return array<int,array{image:string,source:string,source_id:string,epoch:int,generation:int,schema_cookie:int,page_count:int,dirty:bool,pinned:bool,master_digest:string}>
     */
    private static function assertCache(array $cache, int $pageSize): array
    {
        ksort($cache, SORT_NUMERIC);
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next166 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next166 cache page {$pageNumber} image must match page size");
            }
            foreach (['epoch', 'generation', 'schema_cookie', 'page_count'] as $field) {
                $value = $entry[$field] ?? 0;
                if (!is_int($value) || $value < 0) {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next166 cache {$field} must be non-negative");
                }
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source' => isset($entry['source']) && is_string($entry['source']) && $entry['source'] !== '' ? $entry['source'] : 'master-journal-reader-cache-next166',
                'source_id' => isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '',
                'epoch' => $entry['epoch'] ?? 0,
                'generation' => $entry['generation'] ?? 0,
                'schema_cookie' => $entry['schema_cookie'] ?? 0,
                'page_count' => $entry['page_count'] ?? 0,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'master_digest' => isset($entry['master_digest']) && is_string($entry['master_digest']) ? $entry['master_digest'] : '',
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,generation?:int,schema_cookie?:int,page_count?:int}> $reads
     * @return list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,generation?:int,schema_cookie?:int,page_count?:int}>
     */
    private static function assertReads(array $reads): array
    {
        foreach ($reads as $read) {
            if (!isset($read['reader_id']) || !is_string($read['reader_id']) || $read['reader_id'] === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next166 reads require reader ids');
            }
            if (!isset($read['page_number']) || !is_int($read['page_number']) || $read['page_number'] < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next166 read page numbers must be one-based integers');
            }
            foreach (['epoch', 'generation', 'schema_cookie', 'page_count'] as $field) {
                if (isset($read[$field]) && (!is_int($read[$field]) || $read[$field] < 0)) {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next166 read {$field} must be non-negative");
                }
            }
        }

        return $reads;
    }

    private static function digest(string $value): string
    {
        return hash('sha256', $value);
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 88), ".\0 ");
    }
}
