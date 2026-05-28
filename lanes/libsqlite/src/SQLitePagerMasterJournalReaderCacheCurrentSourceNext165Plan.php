<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext165Plan
{
    /**
     * @param array<int,string> $currentPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_journal_digest?:string,change_counter?:int,schema_cookie?:int,end_frame?:int,pinned?:bool,dirty?:bool}> $readerCache
     * @param list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,change_counter?:int,schema_cookie?:int,end_frame?:int}> $nextReads
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        int $pageSize,
        array $currentPages,
        array $readerCache,
        array $nextReads,
        string $currentSourceId,
        int $currentEpoch,
        int $currentChangeCounter,
        int $currentSchemaCookie,
        int $currentEndFrame,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next165 requires paths and current source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next165 requires current master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next165 page size must be a power of two at least 512');
        }
        if ($currentEpoch < 1 || $currentChangeCounter < 0 || $currentSchemaCookie < 0 || $currentEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next165 source generation fields must be valid');
        }
        if ($currentPages === [] || $readerCache === [] || $nextReads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next165 requires pages, cache, and reads');
        }

        $currentMembers = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $currentMembers, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next165 current master journal does not reference the database journal');
        }

        $currentPages = self::assertPages($currentPages, $pageSize, 'current');
        if (!isset($currentPages[1])) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next165 requires current page 1 for generation fencing');
        }

        $readerCache = self::assertReaderCache($readerCache, $pageSize);
        $nextReads = self::assertNextReads($nextReads);
        $currentDigest = self::memberDigest($currentMembers);

        $operations = [[
            'op' => 'read_current_master_journal_and_header_generation_before_reader_cache',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
            'change_counter' => $currentChangeCounter,
            'schema_cookie' => $currentSchemaCookie,
            'end_frame' => $currentEndFrame,
        ]];

        $retained = [];
        $invalidated = [];
        $rows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            $imageMatches = isset($currentPages[$pageNumber])
                && hash_equals(self::digest($currentPages[$pageNumber]), self::digest($entry['image']));
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_page_after_master_journal_recovery';
            } elseif ($entry['pinned']) {
                $reason = 'pinned_reader_cache_page_needs_reopen_after_master_journal_recovery';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_not_current';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_not_current';
            } elseif ($entry['master_journal_digest'] !== $currentDigest) {
                $reason = 'reader_cache_master_journal_digest_not_current';
            } elseif ($entry['change_counter'] !== $currentChangeCounter) {
                $reason = 'reader_cache_change_counter_predates_current_header';
            } elseif ($entry['schema_cookie'] !== $currentSchemaCookie) {
                $reason = 'reader_cache_schema_cookie_predates_current_header';
            } elseif ($entry['end_frame'] !== $currentEndFrame) {
                $reason = 'reader_cache_end_frame_predates_current_wal_source';
            } elseif (!$imageMatches) {
                $reason = 'reader_cache_image_digest_not_current';
            }

            $row = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'master_journal_digest_matches' => $entry['master_journal_digest'] === $currentDigest,
                'change_counter' => $entry['change_counter'],
                'schema_cookie' => $entry['schema_cookie'],
                'end_frame' => $entry['end_frame'],
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'image_matches_current' => $imageMatches,
                'reason' => $reason ?? 'reader_cache_admitted_by_master_journal_header_generation',
            ];
            $rows[$pageNumber] = $row;

            if ($reason !== null) {
                $invalidated[] = $row;
                $operations[] = [
                    'op' => 'invalidate_reader_cache_on_master_journal_header_generation_fence',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $reason,
                ];
                continue;
            }

            $retained[$pageNumber] = $entry;
            $operations[] = [
                'op' => 'retain_reader_cache_after_master_journal_header_generation_fence',
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'digest' => self::digest($entry['image']),
            ];
        }

        $reads = [];
        $reopen = [];
        foreach ($nextReads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next165 read page {$pageNumber} is outside current source");
            }
            $ticketCurrent = ($read['source_id'] ?? $currentSourceId) === $currentSourceId
                && ($read['epoch'] ?? $currentEpoch) === $currentEpoch
                && ($read['change_counter'] ?? $currentChangeCounter) === $currentChangeCounter
                && ($read['schema_cookie'] ?? $currentSchemaCookie) === $currentSchemaCookie
                && ($read['end_frame'] ?? $currentEndFrame) === $currentEndFrame;
            $cacheHit = $ticketCurrent && isset($retained[$pageNumber]);
            if (!$cacheHit) {
                $reopen[$read['reader_id']] = $read['reader_id'];
            }
            $image = $cacheHit ? $retained[$pageNumber]['image'] : $currentPages[$pageNumber];
            $reads[] = [
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
                'ticket_current' => $ticketCurrent,
                'cache_hit' => $cacheHit,
                'source' => $cacheHit ? 'reader-cache-current-master-header-generation' : 'current-master-journal-header-generation-source',
                'source_id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'change_counter' => $currentChangeCounter,
                'schema_cookie' => $currentSchemaCookie,
                'end_frame' => $currentEndFrame,
                'prefix' => self::prefix($image),
                'digest' => self::digest($image),
            ];
            $operations[] = [
                'op' => $cacheHit ? 'next_reader_cache_hit_header_generation_current' : 'next_reader_reopen_header_generation_current_source',
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
            ];
        }

        ksort($retained, SORT_NUMERIC);
        ksort($rows, SORT_NUMERIC);

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next165',
            'reason' => 'reader_cache_reuse_is_fenced_by_master_journal_membership_and_header_generation',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $currentMembers,
            'current_master_journal_digest' => $currentDigest,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'change_counter' => $currentChangeCounter,
                'schema_cookie' => $currentSchemaCookie,
                'end_frame' => $currentEndFrame,
                'page_numbers' => array_keys($currentPages),
                'page1_digest' => self::digest($currentPages[1]),
            ],
            'cache' => [
                'retained_page_numbers' => array_keys($retained),
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'rows' => array_values($rows),
            ],
            'reads' => $reads,
            'reopen_reader_ids' => array_values($reopen),
            'read_cache_hits' => array_column($reads, 'cache_hit', 'reader_id'),
            'read_prefixes' => array_column($reads, 'prefix', 'reader_id'),
            'operations' => $operations,
            'source_digest' => hash('sha256', $currentSourceId . '|' . $currentEpoch . '|' . $currentChangeCounter . '|' . $currentSchemaCookie . '|' . $currentEndFrame . '|' . $currentDigest),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next165',
                'sqlite-pager-master-journal-reader-cache-current-source-next162',
                'sqlite-master-journal-header-generation-reader-cache-fence',
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
     * @param list<string> $members
     */
    private static function memberDigest(array $members): string
    {
        return hash('sha256', implode("\n", $members));
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
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next165 {$label} page numbers must be positive integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next165 {$label} page {$pageNumber} image must match page size");
            }
        }

        return $pages;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_journal_digest?:string,change_counter?:int,schema_cookie?:int,end_frame?:int,pinned?:bool,dirty?:bool}> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,master_journal_digest:string,change_counter:int,schema_cookie:int,end_frame:int,pinned:bool,dirty:bool}>
     */
    private static function assertReaderCache(array $cache, int $pageSize): array
    {
        ksort($cache, SORT_NUMERIC);
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next165 cache page numbers must be positive integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next165 cache page {$pageNumber} image must match page size");
            }
            foreach (['epoch', 'change_counter', 'schema_cookie', 'end_frame'] as $field) {
                if (isset($entry[$field]) && (!is_int($entry[$field]) || $entry[$field] < 0)) {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next165 cache {$field} must be non-negative");
                }
            }
            $readerId = isset($entry['reader_id']) && is_string($entry['reader_id']) && $entry['reader_id'] !== '' ? $entry['reader_id'] : 'reader-' . $pageNumber;
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '',
                'epoch' => $entry['epoch'] ?? 0,
                'reader_id' => $readerId,
                'master_journal_digest' => isset($entry['master_journal_digest']) && is_string($entry['master_journal_digest']) ? $entry['master_journal_digest'] : '',
                'change_counter' => $entry['change_counter'] ?? 0,
                'schema_cookie' => $entry['schema_cookie'] ?? 0,
                'end_frame' => $entry['end_frame'] ?? 0,
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'dirty' => (bool) ($entry['dirty'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,change_counter?:int,schema_cookie?:int,end_frame?:int}> $reads
     * @return list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,change_counter?:int,schema_cookie?:int,end_frame?:int}>
     */
    private static function assertNextReads(array $reads): array
    {
        foreach ($reads as $read) {
            if (!isset($read['reader_id']) || !is_string($read['reader_id']) || $read['reader_id'] === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next165 read entries require reader ids');
            }
            if (!isset($read['page_number']) || !is_int($read['page_number']) || $read['page_number'] < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next165 read page numbers must be positive integers');
            }
            foreach (['epoch', 'change_counter', 'schema_cookie', 'end_frame'] as $field) {
                if (isset($read[$field]) && (!is_int($read[$field]) || $read[$field] < 0)) {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next165 read {$field} must be non-negative");
                }
            }
        }

        return $reads;
    }

    private static function digest(string $image): string
    {
        return hash('sha256', $image);
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 64), ".\0 ");
    }
}
