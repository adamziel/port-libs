<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext176Plan
{
    /**
     * @param array<int,string> $currentPages
     * @param array<int,string> $nextPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_digest?:string,master_members?:list<string>,dirty?:bool,pinned?:bool,shared?:bool}> $readerCache
     * @param list<array{reader_id?:string,page_number:int,source_id?:string,epoch?:int,master_digest?:string,phase?:string}> $reads
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $nextMasterJournalBytes,
        int $pageSize,
        array $currentPages,
        array $nextPages,
        array $readerCache,
        array $reads,
        string $currentSourceId,
        int $currentEpoch,
        string $nextSourceId,
        int $nextEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '' || $nextSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next176 requires non-empty paths and source ids');
        }
        if ($currentSourceId === $nextSourceId || $nextEpoch <= $currentEpoch) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next176 requires an advancing next source');
        }
        if (trim($currentMasterJournalBytes) === '' || trim($nextMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next176 requires current and next master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next176 page size must be a power of two at least 512');
        }
        if ($currentPages === [] || $nextPages === [] || $readerCache === [] || $reads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next176 requires pages, reader cache, and reads');
        }

        $currentMembers = self::members($currentMasterJournalBytes);
        $nextMembers = self::members($nextMasterJournalBytes);
        $databaseJournal = $databasePath . '-journal';
        if (!in_array($databaseJournal, $currentMembers, true) || !in_array($databaseJournal, $nextMembers, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next176 master journals must reference the database journal');
        }

        $currentPages = self::normalizePages($currentPages, $pageSize, 'current');
        $nextPages = self::normalizePages($nextPages, $pageSize, 'next');
        $readerCache = self::normalizeCache($readerCache, $pageSize);
        $reads = self::normalizeReads($reads);

        $currentDigest = self::digestMembers($currentMembers);
        $nextDigest = self::digestMembers($nextMembers);
        $currentSignature = self::memberSignature($currentMembers);
        $nextSignature = self::memberSignature($nextMembers);

        $operations = [[
            'op' => 'read_current_and_next_master_journals_for_reader_cache_next176',
            'path' => $masterJournalPath,
            'current_digest' => $currentDigest,
            'next_digest' => $nextDigest,
            'source_rollover' => true,
        ]];

        $currentRetained = [];
        $currentInvalidated = [];
        $nextReusable = [];
        $nextInvalidated = [];
        $cacheRows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($currentPages[$pageNumber]) || !isset($nextPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next176 cache page {$pageNumber} is outside current or next source");
            }

            $cacheSignature = self::memberSignature($entry['master_members']);
            $currentImageMatches = hash_equals(self::digest($currentPages[$pageNumber]), self::digest($entry['image']));
            $nextImageMatches = hash_equals(self::digest($nextPages[$pageNumber]), self::digest($entry['image']));

            $currentReason = null;
            if ($entry['dirty']) {
                $currentReason = 'dirty_reader_cache_cannot_survive_current_source';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $currentReason = 'reader_cache_current_source_id_mismatch';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $currentReason = 'reader_cache_current_epoch_mismatch';
            } elseif ($entry['master_digest'] !== '' && !hash_equals($currentDigest, $entry['master_digest'])) {
                $currentReason = 'reader_cache_current_master_digest_mismatch';
            } elseif ($cacheSignature !== '' && $cacheSignature !== $currentSignature) {
                $currentReason = 'reader_cache_current_master_membership_mismatch';
            } elseif ($entry['pinned'] && !$currentImageMatches) {
                $currentReason = 'pinned_reader_cache_current_image_mismatch';
            }

            if ($currentReason === null) {
                $currentRetained[$pageNumber] = $entry;
                $operations[] = [
                    'op' => 'retain_reader_cache_for_current_master_source_next176',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            } else {
                $currentInvalidated[$pageNumber] = $currentReason;
                $operations[] = [
                    'op' => 'invalidate_reader_cache_for_current_master_source_next176',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $currentReason,
                ];
            }

            $nextReason = null;
            if ($entry['dirty']) {
                $nextReason = 'dirty_reader_cache_cannot_survive_next_source';
            } elseif ($entry['source_id'] !== $nextSourceId) {
                $nextReason = 'reader_cache_source_rollover_requires_reopen';
            } elseif ($entry['epoch'] !== $nextEpoch) {
                $nextReason = 'reader_cache_epoch_rollover_requires_reopen';
            } elseif ($entry['master_digest'] !== '' && !hash_equals($nextDigest, $entry['master_digest'])) {
                $nextReason = 'reader_cache_next_master_digest_mismatch';
            } elseif ($cacheSignature !== '' && $cacheSignature !== $nextSignature) {
                $nextReason = 'reader_cache_next_master_membership_mismatch';
            } elseif ($entry['pinned'] && !$nextImageMatches) {
                $nextReason = 'pinned_reader_cache_next_image_mismatch';
            }

            if ($nextReason === null) {
                $nextReusable[$pageNumber] = $entry;
                $operations[] = [
                    'op' => 'retain_reader_cache_for_next_master_source_next176',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                ];
            } else {
                $nextInvalidated[$pageNumber] = $nextReason;
                $operations[] = [
                    'op' => 'invalidate_reader_cache_before_next_master_source_next176',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'reason' => $nextReason,
                ];
            }

            $cacheRows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'current_admitted' => $currentReason === null,
                'current_reason' => $currentReason ?? 'reader_cache_admitted_to_current_master_source',
                'next_admitted' => $nextReason === null,
                'next_reason' => $nextReason ?? 'reader_cache_admitted_to_next_master_source',
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'shared' => $entry['shared'],
                'current_image_matches' => $currentImageMatches,
                'next_image_matches' => $nextImageMatches,
                'current_master_digest' => $currentDigest,
                'next_master_digest' => $nextDigest,
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentPages[$pageNumber]),
                'next_prefix' => self::prefix($nextPages[$pageNumber]),
            ];
        }

        $readRows = [];
        $reopen = [];
        foreach ($reads as $read) {
            $pageNumber = $read['page_number'];
            $phase = $read['phase'];
            $pages = $phase === 'next' ? $nextPages : $currentPages;
            if (!isset($pages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next176 read page {$pageNumber} is outside {$phase} source");
            }

            $expectedSource = $phase === 'next' ? $nextSourceId : $currentSourceId;
            $expectedEpoch = $phase === 'next' ? $nextEpoch : $currentEpoch;
            $expectedDigest = $phase === 'next' ? $nextDigest : $currentDigest;
            $pool = $phase === 'next' ? $nextReusable : $currentRetained;
            $ticketCurrent = $read['source_id'] === $expectedSource
                && $read['epoch'] === $expectedEpoch
                && ($read['master_digest'] === '' || hash_equals($expectedDigest, $read['master_digest']));
            $cacheHit = $ticketCurrent && isset($pool[$pageNumber]);
            if (!$cacheHit) {
                $reopen[$read['reader_id']] = $read['reader_id'];
            }
            $image = $cacheHit ? $pool[$pageNumber]['image'] : $pages[$pageNumber];
            $readRows[] = [
                'reader_id' => $read['reader_id'],
                'phase' => $phase,
                'page_number' => $pageNumber,
                'ticket_current' => $ticketCurrent,
                'cache_hit' => $cacheHit,
                'source' => $cacheHit ? "reader-cache-{$phase}-master-source-next176" : "{$phase}-master-source-reopen-next176",
                'source_id' => $expectedSource,
                'epoch' => $expectedEpoch,
                'master_digest' => $expectedDigest,
                'prefix' => self::prefix($image),
                'digest' => self::digest($image),
            ];
            $operations[] = [
                'op' => $cacheHit ? "next176_{$phase}_reader_cache_hit" : "next176_{$phase}_reader_reopen",
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next176',
            'reason' => 'next master-journal source rollover fences reader-cache reuse even when page images still match',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch, 'master_digest' => $currentDigest, 'members' => $currentMembers],
            'next_source' => ['id' => $nextSourceId, 'epoch' => $nextEpoch, 'master_digest' => $nextDigest, 'members' => $nextMembers],
            'cache_rows' => $cacheRows,
            'current_retained_page_numbers' => array_keys($currentRetained),
            'current_invalidated_reasons' => $currentInvalidated,
            'next_reusable_page_numbers' => array_keys($nextReusable),
            'next_invalidated_reasons' => $nextInvalidated,
            'reads' => $readRows,
            'read_cache_hits' => array_column($readRows, 'cache_hit', 'reader_id'),
            'read_prefixes' => array_column($readRows, 'prefix', 'reader_id'),
            'reopen_reader_ids' => array_values($reopen),
            'requires_reader_reopen' => $reopen !== [] || $nextInvalidated !== [],
            'operations' => $operations,
            'source_digest' => hash('sha256', $currentSourceId . '|' . $nextSourceId . '|' . $currentDigest . '|' . $nextDigest . '|' . implode(',', array_keys($currentRetained)) . '|' . implode(',', array_keys($nextReusable))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next176',
                'sqlite-pager-master-journal-reader-cache-current-source-next173',
                'sqlite-master-journal-next-source-reader-cache-rollover',
            ],
            'non_overlap' => 'Extends accepted next173 current membership fencing by proving a subsequent master-journal source rollover cannot reuse a current-source reader cache, even for identical page images.',
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
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next176 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next176 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,master_digest:string,master_members:list<string>,dirty:bool,pinned:bool,shared:bool}>
     */
    private static function normalizeCache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next176 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next176 cache page {$pageNumber} must include page-size image");
            }
            $sourceId = isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '';
            $readerId = isset($entry['reader_id']) && is_string($entry['reader_id']) && $entry['reader_id'] !== '' ? $entry['reader_id'] : "reader-{$pageNumber}";
            $epoch = (int) ($entry['epoch'] ?? 0);
            if ($sourceId === '' || $epoch < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next176 cache page {$pageNumber} requires source id and epoch");
            }
            $digest = $entry['master_digest'] ?? '';
            if (!is_string($digest)) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next176 cache page {$pageNumber} master digest must be a string");
            }
            $members = $entry['master_members'] ?? [];
            if (!is_array($members)) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next176 cache page {$pageNumber} master members must be a list");
            }
            $memberList = [];
            foreach ($members as $member) {
                if (!is_string($member) || trim($member) === '') {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next176 cache page {$pageNumber} master members must be non-empty strings");
                }
                $memberList[] = trim($member);
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'reader_id' => $readerId,
                'master_digest' => $digest,
                'master_members' => $memberList,
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
     * @return list<array{reader_id:string,page_number:int,source_id:string,epoch:int,master_digest:string,phase:string}>
     */
    private static function normalizeReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $readerId = $read['reader_id'] ?? '';
            $sourceId = $read['source_id'] ?? '';
            $digest = $read['master_digest'] ?? '';
            $phase = $read['phase'] ?? 'next';
            if (!is_string($readerId) || $readerId === '' || !is_string($sourceId) || $sourceId === '' || !is_string($digest) || !is_string($phase) || !in_array($phase, ['current', 'next'], true)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next176 reads require reader id, source id, digest string, and current/next phase');
            }
            $pageNumber = (int) ($read['page_number'] ?? 0);
            $epoch = (int) ($read['epoch'] ?? 0);
            if ($pageNumber < 1 || $epoch < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next176 reads require positive page number and epoch');
            }
            $normalized[] = [
                'reader_id' => $readerId,
                'page_number' => $pageNumber,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'master_digest' => $digest,
                'phase' => $phase,
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
        if ($members === []) {
            return '';
        }
        sort($members, SORT_STRING);

        return hash('sha256', implode("\n", $members));
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
