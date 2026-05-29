<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext179Plan
{
    /**
     * @param array<string,string> $canonicalPathMap
     * @param array<int,string> $currentPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_members?:list<string>,canonical_digest?:string,dirty?:bool,pinned?:bool,shared?:bool}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,canonical_digest?:string}> $reads
     * @param array<int,string> $writePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $masterJournalBytes,
        array $canonicalPathMap,
        int $pageSize,
        array $currentPages,
        array $readerCache,
        array $reads,
        array $writePages,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next179 requires non-empty paths and source id');
        }
        if (trim($masterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next179 requires master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next179 page size must be a power of two at least 512');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next179 epoch must be positive');
        }
        if ($currentPages === [] || $readerCache === [] || ($reads === [] && $writePages === [])) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next179 requires pages, cache, and next work');
        }

        $members = self::members($masterJournalBytes);
        $canonicalMembers = self::canonicalMembers($members, $canonicalPathMap);
        $canonicalDigest = self::digestMembers($canonicalMembers);
        $rawDigest = self::digestMembers($members);
        $databaseJournal = self::canonicalPath($databasePath . '-journal', $canonicalPathMap);
        if (!in_array($databaseJournal, $canonicalMembers, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next179 canonical master journal does not reference the database journal');
        }

        $currentPages = self::normalizePages($currentPages, $pageSize, 'current');
        $readerCache = self::normalizeCache($readerCache, $pageSize, $canonicalPathMap);
        $reads = self::normalizeReads($reads);
        $writePages = self::normalizePages($writePages, $pageSize, 'write', true);

        $operations = [[
            'op' => 'read_master_journal_and_canonicalize_members_for_reader_cache_next179',
            'path' => $masterJournalPath,
            'raw_digest' => $rawDigest,
            'canonical_digest' => $canonicalDigest,
            'members' => $members,
            'canonical_members' => $canonicalMembers,
        ]];

        $validCache = [];
        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $cacheRows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next179 cache page {$pageNumber} is outside current source");
            }

            $entryCanonicalDigest = self::digestMembers($entry['canonical_members']);
            $currentImage = $currentPages[$pageNumber];
            $imageMatches = hash_equals(self::digest($currentImage), self::digest($entry['image']));
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_canonical_master_source';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch_after_canonical_master_source';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_mismatch_after_canonical_master_source';
            } elseif ($entry['canonical_digest'] !== '' && !hash_equals($canonicalDigest, $entry['canonical_digest'])) {
                $reason = 'reader_cache_stored_canonical_digest_mismatch_next179';
            } elseif (!hash_equals($canonicalDigest, $entryCanonicalDigest)) {
                $reason = 'reader_cache_canonical_membership_mismatch_next179';
            } elseif ($entry['pinned'] && !$imageMatches) {
                $reason = 'pinned_reader_cache_image_mismatch_after_canonical_master_source';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = $reason;
                $operations[] = [
                    'op' => 'invalidate_reader_cache_after_canonical_master_source_next179',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $reason,
                ];
            } elseif (!$imageMatches) {
                $refreshed[$pageNumber] = $currentImage;
                $validCache[$pageNumber] = [
                    'image' => $currentImage,
                    'source' => 'reader-cache-refreshed-canonical-master-source-next179',
                ];
                $operations[] = [
                    'op' => 'refresh_reader_cache_after_canonical_master_source_next179',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            } else {
                $retained[$pageNumber] = $entry['image'];
                $validCache[$pageNumber] = [
                    'image' => $entry['image'],
                    'source' => 'reader-cache-retained-canonical-master-source-next179',
                ];
                $operations[] = [
                    'op' => 'retain_reader_cache_after_canonical_master_source_next179',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            }

            $cacheRows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'admitted' => $reason === null,
                'reason' => $reason ?? ($imageMatches ? 'reader_cache_matches_canonical_master_source' : 'reader_cache_refreshed_from_canonical_master_source'),
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'shared' => $entry['shared'],
                'raw_members' => $entry['raw_members'],
                'canonical_members' => $entry['canonical_members'],
                'canonical_digest_matches' => hash_equals($canonicalDigest, $entryCanonicalDigest),
                'stored_canonical_digest_matches' => $entry['canonical_digest'] === '' || hash_equals($canonicalDigest, $entry['canonical_digest']),
                'image_matches_current_source' => $imageMatches,
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
            ];
        }

        $readRows = [];
        $reopen = [];
        foreach ($reads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next179 read page {$pageNumber} is outside current source");
            }
            $ticketCurrent = $read['source_id'] === $currentSourceId
                && $read['epoch'] === $currentEpoch
                && ($read['canonical_digest'] === '' || hash_equals($canonicalDigest, $read['canonical_digest']));
            $cache = $ticketCurrent ? ($validCache[$pageNumber] ?? null) : null;
            if ($cache === null) {
                $reopen[$read['reader_id']] = $read['reader_id'];
            }
            $readRows[] = [
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
                'ticket_current' => $ticketCurrent,
                'cache_hit' => $cache !== null,
                'source' => $cache['source'] ?? 'canonical-master-source-reopen-next179',
                'source_id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'canonical_digest' => $canonicalDigest,
                'prefix' => self::prefix($cache['image'] ?? $currentPages[$pageNumber]),
                'digest' => self::digest($cache['image'] ?? $currentPages[$pageNumber]),
            ];
            $operations[] = [
                'op' => $cache !== null ? 'next179_reader_cache_hit' : 'next179_reader_reopen',
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
            ];
        }

        $writeRows = [];
        foreach ($writePages as $pageNumber => $image) {
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next179 write page {$pageNumber} is outside current source");
            }
            $before = $currentPages[$pageNumber];
            $currentPages[$pageNumber] = $image;
            $writeRows[] = [
                'page_number' => $pageNumber,
                'before_prefix' => self::prefix($before),
                'after_prefix' => self::prefix($image),
                'before_image_from_canonical_master_source' => true,
                'source_id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'canonical_digest' => $canonicalDigest,
            ];
            $operations[] = [
                'op' => 'capture_next_write_before_image_after_canonical_master_source_next179',
                'page_number' => $pageNumber,
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next179',
            'reason' => 'canonical master-journal member paths fence reader-cache reuse before the current source is trusted',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'raw_members' => $members,
            'canonical_members' => $canonicalMembers,
            'raw_master_digest' => $rawDigest,
            'canonical_master_digest' => $canonicalDigest,
            'source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'cache_rows' => $cacheRows,
            'retained_page_numbers' => array_keys($retained),
            'refreshed_page_numbers' => array_keys($refreshed),
            'invalidated_page_numbers' => array_keys($invalidated),
            'invalidated_reasons' => $invalidated,
            'reads' => $readRows,
            'read_cache_hits' => array_column($readRows, 'cache_hit', 'reader_id'),
            'read_prefixes' => array_column($readRows, 'prefix', 'reader_id'),
            'reopen_reader_ids' => array_values($reopen),
            'writes' => $writeRows,
            'requires_reader_reopen' => $reopen !== [] || $invalidated !== [],
            'operations' => $operations,
            'source_digest' => hash('sha256', $currentSourceId . '|' . $currentEpoch . '|' . $canonicalDigest . '|' . implode(',', array_keys($retained)) . '|' . implode(',', array_keys($refreshed))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next179',
                'sqlite-pager-master-journal-reader-cache-current-source-next174',
                'sqlite-master-journal-vfs-canonical-pathname',
            ],
            'non_overlap' => 'Extends accepted next174 canonical member ordering by adding VFS canonical pathname admission for aliased master-journal members before reader-cache reuse.',
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
                $members[] = $line;
            }
        }

        return $members;
    }

    /**
     * @param list<string> $members
     * @param array<string,string> $canonicalPathMap
     * @return list<string>
     */
    private static function canonicalMembers(array $members, array $canonicalPathMap): array
    {
        $canonical = [];
        foreach ($members as $member) {
            $canonical[self::canonicalPath($member, $canonicalPathMap)] = self::canonicalPath($member, $canonicalPathMap);
        }
        sort($canonical, SORT_STRING);

        return array_values($canonical);
    }

    /**
     * @param array<string,string> $canonicalPathMap
     */
    private static function canonicalPath(string $path, array $canonicalPathMap): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next179 member paths must be non-empty');
        }
        foreach ($canonicalPathMap as $from => $to) {
            if (!is_string($from) || !is_string($to) || trim($from) === '' || trim($to) === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next179 canonical path map must contain non-empty strings');
            }
        }

        return $canonicalPathMap[$path] ?? $path;
    }

    /**
     * @param list<string> $members
     */
    private static function digestMembers(array $members): string
    {
        return hash('sha256', implode("\n", $members));
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePages(array $pages, int $pageSize, string $label, bool $allowEmpty = false): array
    {
        if ($pages === [] && $allowEmpty) {
            return [];
        }
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next179 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next179 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $cache
     * @param array<string,string> $canonicalPathMap
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,raw_members:list<string>,canonical_members:list<string>,canonical_digest:string,dirty:bool,pinned:bool,shared:bool}>
     */
    private static function normalizeCache(array $cache, int $pageSize, array $canonicalPathMap): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next179 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next179 cache page {$pageNumber} must include page-size image");
            }
            $sourceId = isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '';
            $epoch = (int) ($entry['epoch'] ?? 0);
            if ($sourceId === '' || $epoch < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next179 cache page {$pageNumber} requires source id and epoch");
            }
            $readerId = isset($entry['reader_id']) && is_string($entry['reader_id']) && $entry['reader_id'] !== '' ? $entry['reader_id'] : "reader-{$pageNumber}";
            $members = $entry['master_members'] ?? [];
            if (!is_array($members) || $members === []) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next179 cache page {$pageNumber} requires master members");
            }
            $rawMembers = [];
            foreach ($members as $member) {
                if (!is_string($member) || trim($member) === '') {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next179 cache page {$pageNumber} master members must be non-empty strings");
                }
                $rawMembers[] = trim($member);
            }
            $storedDigest = $entry['canonical_digest'] ?? '';
            if (!is_string($storedDigest)) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next179 cache page {$pageNumber} canonical digest must be a string");
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'reader_id' => $readerId,
                'raw_members' => $rawMembers,
                'canonical_members' => self::canonicalMembers($rawMembers, $canonicalPathMap),
                'canonical_digest' => $storedDigest,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'shared' => (bool) ($entry['shared'] ?? false),
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,canonical_digest:string}>
     */
    private static function normalizeReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $readerId = $read['reader_id'] ?? '';
            $sourceId = $read['source_id'] ?? '';
            $digest = $read['canonical_digest'] ?? '';
            if (!is_string($readerId) || $readerId === '' || !is_string($sourceId) || $sourceId === '' || !is_string($digest)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next179 reads require reader id, source id, and canonical digest string');
            }
            $pageNumber = (int) ($read['page_number'] ?? 0);
            $epoch = (int) ($read['epoch'] ?? 0);
            if ($pageNumber < 1 || $epoch < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next179 reads require positive page number and epoch');
            }
            $normalized[] = [
                'reader_id' => $readerId,
                'page_number' => $pageNumber,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'canonical_digest' => $digest,
            ];
        }

        return $normalized;
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
