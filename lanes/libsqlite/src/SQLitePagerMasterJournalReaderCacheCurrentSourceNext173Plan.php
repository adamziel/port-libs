<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext173Plan
{
    /**
     * @param array<int,string> $currentPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_digest?:string,master_members?:list<string>,dirty?:bool,pinned?:bool,shared?:bool}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,master_digest?:string}> $reads
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        int $pageSize,
        array $currentPages,
        array $readerCache,
        array $reads,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 requires non-empty paths and source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 requires current master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 page size must be a power of two at least 512');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 epoch must be positive');
        }
        if ($currentPages === [] || $readerCache === [] || $reads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 requires pages, reader cache, and reads');
        }

        $members = self::members($currentMasterJournalBytes);
        if (!in_array($databasePath . '-journal', $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next173 current master journal does not reference the database journal');
        }

        $currentPages = self::normalizePages($currentPages, $pageSize, 'current');
        $readerCache = self::normalizeCache($readerCache, $pageSize);
        $reads = self::normalizeReads($reads);
        $masterDigest = self::digestMembers($members);
        $memberSignature = self::memberSignature($members);

        $operations = [[
            'op' => 'read_current_master_journal_for_reader_cache_membership_next173',
            'path' => $masterJournalPath,
            'digest' => $masterDigest,
            'member_count' => count($members),
            'members' => $members,
        ]];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next173 cache page {$pageNumber} is outside current source");
            }

            $currentImage = $currentPages[$pageNumber];
            $imageMatches = hash_equals(self::digest($currentImage), self::digest($entry['image']));
            $cachedSignature = self::memberSignature($entry['master_members']);
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_current_master_membership';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_mismatch';
            } elseif ($entry['master_digest'] !== '' && !hash_equals($masterDigest, $entry['master_digest'])) {
                $reason = 'reader_cache_master_journal_digest_mismatch';
            } elseif ($cachedSignature !== '' && $cachedSignature !== $memberSignature) {
                $reason = 'reader_cache_master_journal_membership_mismatch';
            } elseif ($entry['pinned'] && !$imageMatches) {
                $reason = 'pinned_reader_cache_image_mismatch_after_master_membership_read';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = [
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $reason,
                    'cached_master_digest' => $entry['master_digest'],
                    'current_master_digest' => $masterDigest,
                    'cached_members' => $entry['master_members'],
                    'current_members' => $members,
                    'dirty' => $entry['dirty'],
                    'pinned' => $entry['pinned'],
                ];
                $operations[] = [
                    'op' => 'invalidate_reader_cache_master_membership_ticket',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $reason,
                ];
            } elseif (!$imageMatches) {
                $refreshed[$pageNumber] = [
                    'image' => $currentImage,
                    'reader_id' => $entry['reader_id'],
                    'shared' => $entry['shared'],
                    'source' => 'reader-cache-refreshed-current-master-membership',
                ];
                $operations[] = [
                    'op' => 'refresh_reader_cache_from_current_master_membership',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            } else {
                $retained[$pageNumber] = [
                    'image' => $entry['image'],
                    'reader_id' => $entry['reader_id'],
                    'shared' => $entry['shared'],
                    'source' => 'reader-cache-retained-current-master-membership',
                ];
                $operations[] = [
                    'op' => 'retain_reader_cache_current_master_membership',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'admitted' => $reason === null,
                'reason' => $reason ?? ($imageMatches ? 'reader_cache_matches_current_master_membership' : 'reader_cache_refreshed_from_current_master_membership'),
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'cached_master_digest' => $entry['master_digest'],
                'current_master_digest' => $masterDigest,
                'cached_members' => $entry['master_members'],
                'current_members' => $members,
                'membership_matches' => $cachedSignature === '' || $cachedSignature === $memberSignature,
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'shared' => $entry['shared'],
                'image_matches_current_source' => $imageMatches,
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
            ];
        }

        ksort($retained, SORT_NUMERIC);
        ksort($refreshed, SORT_NUMERIC);
        ksort($invalidated, SORT_NUMERIC);

        $nextReads = [];
        $reopenReaders = [];
        foreach ($reads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next173 read page {$pageNumber} is outside current source");
            }

            $ticketCurrent = $read['source_id'] === $currentSourceId
                && $read['epoch'] === $currentEpoch
                && ($read['master_digest'] === '' || hash_equals($masterDigest, $read['master_digest']));
            $cache = $ticketCurrent ? ($retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null) : null;
            if (!$ticketCurrent || isset($invalidated[$pageNumber])) {
                $reopenReaders[$read['reader_id']] = $read['reader_id'];
            }
            $image = is_array($cache) ? $cache['image'] : $currentPages[$pageNumber];
            $nextReads[] = [
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
                'ticket_current' => $ticketCurrent,
                'cache_hit' => is_array($cache),
                'source' => is_array($cache) ? $cache['source'] : 'current-master-membership-source',
                'source_id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'master_digest' => $masterDigest,
                'prefix' => self::prefix($image),
                'digest' => self::digest($image),
            ];
            $operations[] = [
                'op' => is_array($cache) ? 'next_reader_cache_hit_current_master_membership' : 'next_reader_reopen_current_master_membership_page',
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next173',
            'reason' => 'fresh master-journal membership digest fences reader-cache reuse before current-source reads',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'master_journal_digest' => $masterDigest,
                'master_journal_members' => $members,
            ],
            'cache_rows' => $rows,
            'retained_page_numbers' => array_keys($retained),
            'refreshed_page_numbers' => array_keys($refreshed),
            'invalidated_page_numbers' => array_keys($invalidated),
            'invalidated_reasons' => array_column($invalidated, 'reason', 'page_number'),
            'invalidated_entries' => array_values($invalidated),
            'requires_reader_reopen' => $invalidated !== [] || $reopenReaders !== [],
            'next_reads' => $nextReads,
            'reopen_reader_ids' => array_values($reopenReaders),
            'read_cache_hits' => array_column($nextReads, 'cache_hit', 'reader_id'),
            'read_prefixes' => array_column($nextReads, 'prefix', 'reader_id'),
            'operations' => $operations,
            'source_digest' => hash('sha256', $currentSourceId . '|' . $currentEpoch . '|' . $masterDigest . '|' . implode(',', array_keys($retained)) . '|' . implode(',', array_keys($refreshed)) . '|' . implode(',', array_keys($invalidated))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next173',
                'sqlite-pager-master-journal-reader-cache-current-source-next167',
                'sqlite-master-journal-membership-digest-reader-ticket',
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
    private static function normalizePages(array $pages, int $pageSize, string $label): array
    {
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next173 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next173 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_digest?:string,master_members?:list<string>,dirty?:bool,pinned?:bool,shared?:bool}> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,master_digest:string,master_members:list<string>,dirty:bool,pinned:bool,shared:bool}>
     */
    private static function normalizeCache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next173 cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '';
            $epoch = $entry['epoch'] ?? 0;
            if ($sourceId === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 cache entries require source id');
            }
            if (!is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 cache entries require positive epoch');
            }
            $members = $entry['master_members'] ?? [];
            if (!is_array($members)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 cache master members must be a list');
            }
            $memberList = [];
            foreach ($members as $member) {
                if (!is_string($member) || trim($member) === '') {
                    throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 cache master members must be non-empty strings');
                }
                $memberList[trim($member)] = trim($member);
            }
            $masterDigest = $entry['master_digest'] ?? '';
            if (!is_string($masterDigest)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 cache master digest must be text');
            }

            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'reader_id' => isset($entry['reader_id']) && is_string($entry['reader_id']) && $entry['reader_id'] !== '' ? $entry['reader_id'] : 'reader-' . $pageNumber,
                'master_digest' => $masterDigest,
                'master_members' => array_values($memberList),
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'shared' => (bool) ($entry['shared'] ?? false),
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,master_digest?:string}> $reads
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,master_digest:string}>
     */
    private static function normalizeReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $index => $read) {
            $readerId = isset($read['reader_id']) && is_string($read['reader_id']) ? $read['reader_id'] : '';
            if ($readerId === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 reads require reader id');
            }
            $pageNumber = $read['page_number'] ?? 0;
            $epoch = $read['epoch'] ?? 0;
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next173 read {$index} page number must be one-based");
            }
            if (!isset($read['source_id']) || !is_string($read['source_id']) || $read['source_id'] === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 reads require source id');
            }
            if (!is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 reads require positive epoch');
            }
            $masterDigest = $read['master_digest'] ?? '';
            if (!is_string($masterDigest)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next173 read master digest must be text');
            }
            $normalized[] = [
                'reader_id' => $readerId,
                'page_number' => $pageNumber,
                'source_id' => $read['source_id'],
                'epoch' => $epoch,
                'master_digest' => $masterDigest,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<string> $members
     */
    private static function digestMembers(array $members): string
    {
        return hash('sha256', implode("\n", $members));
    }

    /**
     * @param list<string> $members
     */
    private static function memberSignature(array $members): string
    {
        $unique = [];
        foreach ($members as $member) {
            $unique[$member] = $member;
        }
        ksort($unique, SORT_STRING);

        return implode("\n", $unique);
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 64), ".\0");
    }

    private static function digest(string $bytes): string
    {
        return hash('sha256', $bytes);
    }
}
