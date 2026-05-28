<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext172Plan
{
    /**
     * @param array<string,array<int,string>> $currentPages
     * @param array<string,array<int,array{image:string,database_path?:string,source_id?:string,epoch?:int,master_digest?:string,reader_id?:string,dirty?:bool,pinned?:bool}>> $readerCache
     * @param list<array{reader_id:string,database_path:string,page_number:int,source_id?:string,epoch?:int,master_digest?:string}> $nextReads
     * @return array<string,mixed>
     */
    public static function plan(
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        int $pageSize,
        array $currentPages,
        array $readerCache,
        array $nextReads,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 requires a master journal path and source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 requires current master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 page size must be a power of two at least 512');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 source epoch must be positive');
        }
        if ($currentPages === [] || $readerCache === [] || $nextReads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 requires current pages, reader cache, and next reads');
        }

        $members = self::members($currentMasterJournalBytes);
        $memberDatabases = self::memberDatabases($members);
        $masterDigest = self::digest($currentMasterJournalBytes);
        $currentPages = self::assertCurrentPages($currentPages, $pageSize, $memberDatabases);
        $readerCache = self::assertReaderCache($readerCache, $pageSize);
        $nextReads = self::assertNextReads($nextReads);

        $operations = [[
            'op' => 'read_current_master_journal_members_for_attached_reader_cache_next172',
            'path' => $masterJournalPath,
            'digest' => $masterDigest,
            'member_count' => count($members),
        ]];

        $cacheRows = [];
        $retained = [];
        $invalidated = [];
        foreach ($readerCache as $slotDatabase => $pages) {
            foreach ($pages as $pageNumber => $entry) {
                $entryDatabase = $entry['database_path'];
                $currentImage = $currentPages[$entryDatabase][$pageNumber] ?? null;
                $reason = null;
                if ($entryDatabase !== $slotDatabase) {
                    $reason = 'reader_cache_database_path_mismatches_cache_slot';
                } elseif (!isset($memberDatabases[$entryDatabase])) {
                    $reason = 'reader_cache_database_not_in_current_master_journal';
                } elseif ($entry['dirty']) {
                    $reason = 'dirty_reader_cache_page_after_master_journal_recovery';
                } elseif ($entry['pinned']) {
                    $reason = 'pinned_reader_cache_page_requires_reopen_after_master_journal_recovery';
                } elseif ($entry['source_id'] !== $currentSourceId) {
                    $reason = 'reader_cache_source_id_not_current';
                } elseif ($entry['epoch'] !== $currentEpoch) {
                    $reason = 'reader_cache_epoch_not_current';
                } elseif ($entry['master_digest'] !== $masterDigest) {
                    $reason = 'reader_cache_master_digest_not_current';
                } elseif ($currentImage === null) {
                    $reason = 'reader_cache_page_absent_from_current_database';
                } elseif (!hash_equals(self::digest($currentImage), self::digest($entry['image']))) {
                    $reason = 'reader_cache_image_not_current_database_source';
                }

                $row = [
                    'database_path' => $entryDatabase,
                    'cache_slot_database_path' => $slotDatabase,
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'source_id' => $entry['source_id'],
                    'epoch' => $entry['epoch'],
                    'dirty' => $entry['dirty'],
                    'pinned' => $entry['pinned'],
                    'master_digest_matches' => $entry['master_digest'] === $masterDigest,
                    'current_digest' => $currentImage === null ? null : self::digest($currentImage),
                    'cache_digest' => self::digest($entry['image']),
                    'reason' => $reason ?? 'reader_cache_admitted_for_current_database_master_journal_member',
                ];
                $cacheRows[] = $row;

                if ($reason === null) {
                    $retained[$entryDatabase][$pageNumber] = $entry;
                    $operations[] = [
                        'op' => 'retain_attached_database_reader_cache_after_master_journal_next172',
                        'database_path' => $entryDatabase,
                        'page_number' => $pageNumber,
                        'reader_id' => $entry['reader_id'],
                    ];
                    continue;
                }

                $invalidated[] = $row;
                $operations[] = [
                    'op' => 'invalidate_attached_database_reader_cache_after_master_journal_next172',
                    'database_path' => $entryDatabase,
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $reason,
                ];
            }
        }

        $reads = [];
        $reopen = [];
        foreach ($nextReads as $read) {
            $databasePath = $read['database_path'];
            $pageNumber = $read['page_number'];
            if (!isset($memberDatabases[$databasePath])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next172 read database {$databasePath} is outside current master journal");
            }
            if (!isset($currentPages[$databasePath][$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next172 read page {$pageNumber} is outside {$databasePath}");
            }
            $ticketCurrent = ($read['source_id'] ?? $currentSourceId) === $currentSourceId
                && ($read['epoch'] ?? $currentEpoch) === $currentEpoch
                && ($read['master_digest'] ?? $masterDigest) === $masterDigest;
            $cacheHit = $ticketCurrent && isset($retained[$databasePath][$pageNumber]);
            if (!$cacheHit) {
                $reopen[$read['reader_id']] = $read['reader_id'];
            }
            $image = $cacheHit ? $retained[$databasePath][$pageNumber]['image'] : $currentPages[$databasePath][$pageNumber];
            $reads[] = [
                'reader_id' => $read['reader_id'],
                'database_path' => $databasePath,
                'page_number' => $pageNumber,
                'ticket_current' => $ticketCurrent,
                'cache_hit' => $cacheHit,
                'source' => $cacheHit ? 'attached-reader-cache-current-master-member-next172' : 'current-attached-database-master-member-next172',
                'source_id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'master_digest' => $masterDigest,
                'prefix' => self::prefix($image),
                'digest' => self::digest($image),
            ];
            $operations[] = [
                'op' => $cacheHit ? 'next172_attached_reader_cache_hit' : 'next172_attached_reader_reopen_current_source',
                'database_path' => $databasePath,
                'page_number' => $pageNumber,
                'reader_id' => $read['reader_id'],
            ];
        }

        ksort($retained);

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next172',
            'reason' => 'master-journal reader cache entries are scoped by attached database path before current-source reuse',
            'master_journal_path' => $masterJournalPath,
            'master_members' => $members,
            'member_databases' => array_keys($memberDatabases),
            'master_digest' => $masterDigest,
            'page_size' => $pageSize,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'cache_rows' => $cacheRows,
            'retained' => self::retainedSummary($retained),
            'invalidated' => $invalidated,
            'invalidated_reasons' => self::reasonMap($invalidated),
            'reads' => $reads,
            'read_cache_hits' => array_column($reads, 'cache_hit', 'reader_id'),
            'read_prefixes' => array_column($reads, 'prefix', 'reader_id'),
            'reopen_reader_ids' => array_values($reopen),
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next172',
                'sqlite-master-journal-attached-database-reader-cache-scope',
                'sqlite-pager-master-journal-reader-cache-current-source-next166',
            ],
            'non_overlap' => 'Scopes reader-cache reuse by attached database path and current master-journal membership; does not repeat next166 generation/schema/page-count fencing.',
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
     * @return array<string,string>
     */
    private static function memberDatabases(array $members): array
    {
        $databases = [];
        foreach ($members as $member) {
            if (!str_ends_with($member, '-journal')) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 members must be rollback journals');
            }
            $database = substr($member, 0, -8);
            if ($database === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 member database path cannot be empty');
            }
            $databases[$database] = $database;
        }

        if ($databases === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 requires at least one member database');
        }

        return $databases;
    }

    /**
     * @param array<string,array<int,string>> $pages
     * @param array<string,string> $memberDatabases
     * @return array<string,array<int,string>>
     */
    private static function assertCurrentPages(array $pages, int $pageSize, array $memberDatabases): array
    {
        ksort($pages);
        foreach ($pages as $databasePath => $databasePages) {
            if (!is_string($databasePath) || $databasePath === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 current page database paths must be non-empty');
            }
            if (!isset($memberDatabases[$databasePath])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next172 current pages for {$databasePath} are outside the master journal");
            }
            if (!is_array($databasePages) || $databasePages === []) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next172 current pages for {$databasePath} cannot be empty");
            }
            ksort($databasePages, SORT_NUMERIC);
            foreach ($databasePages as $pageNumber => $image) {
                if (!is_int($pageNumber) || $pageNumber < 1) {
                    throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 current page numbers must be one-based integers');
                }
                if (!is_string($image) || strlen($image) !== $pageSize) {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next172 current page {$pageNumber} must be page-size bytes");
                }
            }
            $pages[$databasePath] = $databasePages;
        }

        return $pages;
    }

    /**
     * @param array<string,array<int,array{image:string,database_path?:string,source_id?:string,epoch?:int,master_digest?:string,reader_id?:string,dirty?:bool,pinned?:bool}>> $cache
     * @return array<string,array<int,array{image:string,database_path:string,source_id:string,epoch:int,master_digest:string,reader_id:string,dirty:bool,pinned:bool}>>
     */
    private static function assertReaderCache(array $cache, int $pageSize): array
    {
        ksort($cache);
        $normalized = [];
        foreach ($cache as $slotDatabase => $pages) {
            if (!is_string($slotDatabase) || $slotDatabase === '' || !is_array($pages) || $pages === []) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 cache slots require database paths and pages');
            }
            ksort($pages, SORT_NUMERIC);
            foreach ($pages as $pageNumber => $entry) {
                if (!is_int($pageNumber) || $pageNumber < 1) {
                    throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 cache page numbers must be one-based integers');
                }
                if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next172 cache page {$pageNumber} image must match page size");
                }
                $epoch = $entry['epoch'] ?? 0;
                if (!is_int($epoch) || $epoch < 0) {
                    throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 cache epochs must be non-negative');
                }
                $databasePath = (string) ($entry['database_path'] ?? $slotDatabase);
                $readerId = (string) ($entry['reader_id'] ?? ($databasePath . '#' . $pageNumber));
                if ($databasePath === '' || $readerId === '') {
                    throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 cache entries require database paths and reader ids');
                }
                $normalized[$slotDatabase][$pageNumber] = [
                    'image' => $entry['image'],
                    'database_path' => $databasePath,
                    'source_id' => (string) ($entry['source_id'] ?? ''),
                    'epoch' => $epoch,
                    'master_digest' => (string) ($entry['master_digest'] ?? ''),
                    'reader_id' => $readerId,
                    'dirty' => (bool) ($entry['dirty'] ?? false),
                    'pinned' => (bool) ($entry['pinned'] ?? false),
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param list<array{reader_id:string,database_path:string,page_number:int,source_id?:string,epoch?:int,master_digest?:string}> $reads
     * @return list<array{reader_id:string,database_path:string,page_number:int,source_id?:string,epoch?:int,master_digest?:string}>
     */
    private static function assertNextReads(array $reads): array
    {
        foreach ($reads as $read) {
            if (($read['reader_id'] ?? '') === '' || ($read['database_path'] ?? '') === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 reads require reader ids and database paths');
            }
            if (!isset($read['page_number']) || !is_int($read['page_number']) || $read['page_number'] < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 read page numbers must be one-based integers');
            }
            $epoch = $read['epoch'] ?? 0;
            if (!is_int($epoch) || $epoch < 0) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next172 read epochs must be non-negative');
            }
        }

        return $reads;
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $retained
     * @return array<string,list<int>>
     */
    private static function retainedSummary(array $retained): array
    {
        $summary = [];
        foreach ($retained as $databasePath => $pages) {
            ksort($pages, SORT_NUMERIC);
            $summary[$databasePath] = array_keys($pages);
        }

        return $summary;
    }

    /**
     * @param list<array<string,mixed>> $invalidated
     * @return array<string,string>
     */
    private static function reasonMap(array $invalidated): array
    {
        $reasons = [];
        foreach ($invalidated as $row) {
            $key = $row['cache_slot_database_path'] . '|' . $row['database_path'] . '#' . $row['page_number'];
            $reasons[$key] = (string) $row['reason'];
        }

        return $reasons;
    }

    private static function digest(string $bytes): string
    {
        return hash('sha256', $bytes);
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 48), ".\0 ");
    }
}
