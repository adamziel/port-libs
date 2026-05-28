<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext162Plan
{
    /**
     * @param array<int,string> $currentPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,pinned?:bool,dirty?:bool,next_source?:bool}> $readerCache
     * @param list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,end_frame?:int}> $nextReads
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
        array $nextReads,
        string $currentSourceId,
        int $currentEpoch,
        int $currentEndFrame,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next162 requires paths and current source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next162 requires current master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next162 page size must be a power of two at least 512');
        }
        if ($currentEpoch < 1 || $currentEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next162 epoch and end frame must be valid');
        }
        if ($currentPages === [] || $readerCache === [] || $nextReads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next162 requires pages, cache, and reads');
        }

        $currentMembers = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $currentMembers, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next162 current master journal does not reference the database journal');
        }
        $cachedMembers = self::members($cachedMasterJournalBytes);
        $membershipChanged = $cachedMembers !== $currentMembers;
        $currentPages = self::assertPages($currentPages, $pageSize, 'current');
        $readerCache = self::assertReaderCache($readerCache, $pageSize);
        $nextReads = self::assertNextReads($nextReads);

        $operations = [[
            'op' => 'read_current_master_journal_before_next_reader_cache_source',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
            'reason' => 'next_reader_must_not_reuse_pre_recovery_cache_source',
        ]];
        if ($membershipChanged) {
            $operations[] = [
                'op' => 'retire_cached_master_journal_members_for_next_reader',
                'cached_members' => $cachedMembers,
                'current_members' => $currentMembers,
            ];
        }

        $retained = [];
        $invalidated = [];
        $cacheRows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_page_after_master_recovery';
            } elseif ($entry['pinned']) {
                $reason = 'pinned_reader_cache_cannot_seed_next_reader';
            } elseif ($entry['next_source']) {
                $reason = 'speculative_next_source_cache_must_be_reopened';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_is_not_current';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_is_not_current';
            } elseif (!isset($currentPages[$pageNumber])) {
                $reason = 'reader_cache_page_not_in_current_source';
            } elseif (!hash_equals(self::digest($currentPages[$pageNumber]), self::digest($entry['image']))) {
                $reason = 'reader_cache_image_not_current';
            }

            $row = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'next_source' => $entry['next_source'],
                'image_matches_current_source' => isset($currentPages[$pageNumber]) && hash_equals(self::digest($currentPages[$pageNumber]), self::digest($entry['image'])),
                'reason' => $reason ?? 'cache_page_admitted_for_current_source_next_read',
            ];
            $cacheRows[$pageNumber] = $row;

            if ($reason !== null) {
                $invalidated[] = $row;
                $operations[] = [
                    'op' => 'invalidate_reader_cache_before_next_source_read',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $reason,
                ];
                continue;
            }

            $retained[$pageNumber] = $entry;
            $operations[] = [
                'op' => 'retain_reader_cache_for_current_source_next_read',
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'digest' => self::digest($entry['image']),
            ];
        }

        $reads = [];
        $reopenReaders = [];
        foreach ($nextReads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next162 read page {$pageNumber} is outside current source");
            }
            $ticketCurrent = ($read['source_id'] ?? $currentSourceId) === $currentSourceId
                && ($read['epoch'] ?? $currentEpoch) === $currentEpoch
                && ($read['end_frame'] ?? $currentEndFrame) === $currentEndFrame;
            $cacheHit = $ticketCurrent && isset($retained[$pageNumber]);
            if (!$ticketCurrent) {
                $reopenReaders[$read['reader_id']] = $read['reader_id'];
            }
            $image = $cacheHit ? $retained[$pageNumber]['image'] : $currentPages[$pageNumber];
            $reads[] = [
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
                'ticket_current' => $ticketCurrent,
                'cache_hit' => $cacheHit,
                'source' => $cacheHit ? 'reader-cache-current-master-source' : 'current-master-journal-source',
                'source_id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'end_frame' => $currentEndFrame,
                'prefix' => self::prefix($image),
                'digest' => self::digest($image),
            ];
            $operations[] = [
                'op' => $cacheHit ? 'next_reader_cache_hit_current_source' : 'next_reader_reopen_current_source_page',
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
            ];
        }

        ksort($retained, SORT_NUMERIC);
        ksort($cacheRows, SORT_NUMERIC);

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next162',
            'reason' => 'next_reader_sources_are_reopened_when_master_journal_recovery_changes_cache_membership',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'cached_members' => $cachedMembers,
            'current_members' => $currentMembers,
            'membership_changed' => $membershipChanged,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'end_frame' => $currentEndFrame,
                'page_numbers' => array_keys($currentPages),
            ],
            'cache' => [
                'retained_page_numbers' => array_keys($retained),
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'rows' => array_values($cacheRows),
            ],
            'reads' => $reads,
            'reopen_reader_ids' => array_values($reopenReaders),
            'read_cache_hits' => array_column($reads, 'cache_hit', 'reader_id'),
            'read_prefixes' => array_column($reads, 'prefix', 'reader_id'),
            'operations' => $operations,
            'source_digest' => hash('sha256', $currentSourceId . '|' . $currentEpoch . '|' . implode(',', array_keys($retained)) . '|' . implode(',', array_column($invalidated, 'page_number'))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next162',
                'sqlite-pager-master-journal-reader-cache-current-source-next160',
                'sqlite-master-journal-next-reader-current-source-reopen',
            ],
        ];
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
    private static function assertPages(array $pages, int $pageSize, string $label): array
    {
        ksort($pages, SORT_NUMERIC);
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next162 {$label} page numbers must be positive integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next162 {$label} page {$pageNumber} image must match page size");
            }
        }

        return $pages;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,pinned?:bool,dirty?:bool,next_source?:bool}> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,pinned:bool,dirty:bool,next_source:bool}>
     */
    private static function assertReaderCache(array $cache, int $pageSize): array
    {
        ksort($cache, SORT_NUMERIC);
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next162 cache page numbers must be positive integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next162 cache page {$pageNumber} image must match page size");
            }
            $epoch = $entry['epoch'] ?? 0;
            if (!is_int($epoch) || $epoch < 0) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next162 cache page {$pageNumber} epoch must be non-negative");
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '',
                'epoch' => $epoch,
                'reader_id' => isset($entry['reader_id']) && is_string($entry['reader_id']) && $entry['reader_id'] !== '' ? $entry['reader_id'] : 'reader-' . $pageNumber,
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'next_source' => (bool) ($entry['next_source'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,end_frame?:int}> $reads
     * @return list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,end_frame?:int}>
     */
    private static function assertNextReads(array $reads): array
    {
        foreach ($reads as $read) {
            if (!isset($read['reader_id']) || !is_string($read['reader_id']) || $read['reader_id'] === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next162 read entries require reader ids');
            }
            if (!isset($read['page_number']) || !is_int($read['page_number']) || $read['page_number'] < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next162 read page numbers must be positive integers');
            }
            foreach (['epoch', 'end_frame'] as $field) {
                if (isset($read[$field]) && (!is_int($read[$field]) || $read[$field] < 0)) {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next162 read {$field} must be non-negative");
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
