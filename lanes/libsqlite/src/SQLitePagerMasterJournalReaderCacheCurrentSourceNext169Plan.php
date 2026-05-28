<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext169Plan
{
    /**
     * @param array<string,array{generation:int,recovered?:bool,hot?:bool,deleted?:bool}> $memberStates
     * @param array<int,string> $currentPages
     * @param array<int,array{image:string,reader_id?:string,source_id?:string,epoch?:int,member_journal?:string,member_generation?:int,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<array{reader_id:string,page_number:int,member_journal?:string,source_id?:string,epoch?:int}> $nextReads
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        int $pageSize,
        array $memberStates,
        array $currentPages,
        array $readerCache,
        array $nextReads,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 requires database path, master-journal path, and current source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 requires current master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 page size must be a power of two at least 512');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 current epoch must be positive');
        }
        if ($memberStates === [] || $currentPages === [] || $readerCache === [] || $nextReads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 requires member states, current pages, reader cache, and next reads');
        }

        $members = self::members($currentMasterJournalBytes);
        $databaseJournal = $databasePath . '-journal';
        if (!in_array($databaseJournal, $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next169 current master journal does not reference the database journal');
        }

        $memberStates = self::normalizeMemberStates($memberStates, $members);
        $currentPages = self::normalizePages($currentPages, $pageSize);
        $readerCache = self::normalizeReaderCache($readerCache, $pageSize);
        $nextReads = self::normalizeNextReads($nextReads);
        $unrecoveredMembers = self::unrecoveredMembers($memberStates);
        $memberDigest = hash('sha256', implode("\n", $members) . '|' . self::memberStateDigest($memberStates));

        $operations = [[
            'op' => 'read_current_master_journal_member_states_before_reader_cache',
            'path' => $masterJournalPath,
            'members' => $members,
            'unrecovered_members' => $unrecoveredMembers,
        ]];

        $retained = [];
        $invalidated = [];
        $rows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            $member = $entry['member_journal'];
            $state = $memberStates[$member] ?? null;
            $imageMatches = isset($currentPages[$pageNumber])
                && hash_equals(self::digest($currentPages[$pageNumber]), self::digest($entry['image']));
            $reason = null;

            if ($state === null) {
                $reason = 'reader_cache_member_not_in_current_master_journal';
            } elseif ($unrecoveredMembers !== []) {
                $reason = 'master_journal_members_not_fully_recovered';
            } elseif (!$state['recovered']) {
                $reason = 'reader_cache_member_journal_not_recovered';
            } elseif ($state['hot']) {
                $reason = 'reader_cache_member_journal_still_hot';
            } elseif (!$state['deleted']) {
                $reason = 'reader_cache_member_journal_not_deleted_after_recovery';
            } elseif ($entry['dirty']) {
                $reason = 'dirty_reader_cache_after_attached_master_recovery';
            } elseif ($entry['pinned'] && !$imageMatches) {
                $reason = 'pinned_reader_cache_cross_member_image_mismatch';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_not_current_member_source';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_not_current_member_source';
            } elseif ($entry['member_generation'] !== $state['generation']) {
                $reason = 'reader_cache_member_generation_not_current';
            } elseif (!$imageMatches) {
                $reason = 'reader_cache_image_digest_not_current_member_source';
            }

            $row = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'member_journal' => $member,
                'member_generation' => $entry['member_generation'],
                'current_member_generation' => $state['generation'] ?? null,
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'image_matches_current' => $imageMatches,
                'admitted' => $reason === null,
                'reason' => $reason ?? 'reader_cache_member_recovered_and_current',
                'prefix' => self::prefix($entry['image']),
            ];
            $rows[$pageNumber] = $row;

            if ($reason === null) {
                $retained[$pageNumber] = $entry;
                $operations[] = [
                    'op' => 'retain_reader_cache_for_recovered_master_member',
                    'page_number' => $pageNumber,
                    'reader_id' => $entry['reader_id'],
                    'member_journal' => $member,
                ];
                continue;
            }

            $invalidated[] = $row;
            $operations[] = [
                'op' => 'invalidate_reader_cache_for_attached_master_member',
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'member_journal' => $member,
                'reason' => $reason,
            ];
        }

        $reads = [];
        $reopenReaders = [];
        foreach ($nextReads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($currentPages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next169 read page {$pageNumber} is outside current source");
            }
            $member = $read['member_journal'];
            if (!isset($memberStates[$member])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next169 read member {$member} is not in current master journal");
            }
            $ticketCurrent = ($read['source_id'] ?? $currentSourceId) === $currentSourceId
                && ($read['epoch'] ?? $currentEpoch) === $currentEpoch
                && $unrecoveredMembers === [];
            $cacheHit = $ticketCurrent
                && isset($retained[$pageNumber])
                && $retained[$pageNumber]['member_journal'] === $member;
            if (!$cacheHit) {
                $reopenReaders[$read['reader_id']] = $read['reader_id'];
            }
            $image = $cacheHit ? $retained[$pageNumber]['image'] : $currentPages[$pageNumber];
            $reads[] = [
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
                'member_journal' => $member,
                'ticket_current' => $ticketCurrent,
                'cache_hit' => $cacheHit,
                'source' => $cacheHit ? 'reader-cache-recovered-master-member' : 'current-master-member-source',
                'source_id' => $currentSourceId,
                'epoch' => $currentEpoch,
                'prefix' => self::prefix($image),
                'digest' => self::digest($image),
            ];
            $operations[] = [
                'op' => $cacheHit ? 'next_reader_cache_hit_recovered_master_member' : 'next_reader_reopen_recovered_master_member_source',
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
                'member_journal' => $member,
            ];
        }

        ksort($rows, SORT_NUMERIC);
        ksort($retained, SORT_NUMERIC);

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next169',
            'reason' => 'attached_master_journal_member_recovery_state_fences_reader_cache_reuse',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'member_states' => $memberStates,
            'unrecovered_members' => $unrecoveredMembers,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'member_state_digest' => $memberDigest,
            'cache_rows' => array_values($rows),
            'retained_cache_page_numbers' => array_keys($retained),
            'invalidated_cache_page_numbers' => array_column($invalidated, 'page_number'),
            'invalidated_reasons' => array_column($invalidated, 'reason', 'page_number'),
            'requires_reader_reopen' => $invalidated !== [] || $unrecoveredMembers !== [],
            'reads' => $reads,
            'read_cache_hits' => array_column($reads, 'cache_hit', 'reader_id'),
            'read_prefixes' => array_column($reads, 'prefix', 'reader_id'),
            'reopen_reader_ids' => array_values($reopenReaders),
            'operations' => $operations,
            'source_digest' => hash('sha256', $currentSourceId . '|' . $currentEpoch . '|' . $memberDigest),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next169',
                'sqlite-master-journal-attached-member-recovery-fence',
                'sqlite-pager-reader-cache-current-member-generation',
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
     * @param array<string,array{generation:int,recovered?:bool,hot?:bool,deleted?:bool}> $states
     * @param list<string> $members
     * @return array<string,array{generation:int,recovered:bool,hot:bool,deleted:bool}>
     */
    private static function normalizeMemberStates(array $states, array $members): array
    {
        $memberSet = array_fill_keys($members, true);
        $normalized = [];
        foreach ($states as $member => $state) {
            if (!is_string($member) || $member === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 member state keys must be journal paths');
            }
            if (!isset($memberSet[$member])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next169 member {$member} is outside current master journal");
            }
            $generation = $state['generation'] ?? null;
            if (!is_int($generation) || $generation < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next169 member {$member} generation must be positive");
            }
            $normalized[$member] = [
                'generation' => $generation,
                'recovered' => (bool) ($state['recovered'] ?? false),
                'hot' => (bool) ($state['hot'] ?? false),
                'deleted' => (bool) ($state['deleted'] ?? false),
            ];
        }
        foreach ($members as $member) {
            if (!isset($normalized[$member])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next169 member {$member} is missing recovery state");
            }
        }

        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePages(array $pages, int $pageSize): array
    {
        ksort($pages, SORT_NUMERIC);
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 page numbers must be one-based integers');
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next169 page {$pageNumber} image must match page size");
            }
        }

        return $pages;
    }

    /**
     * @param array<int,array{image:string,reader_id?:string,source_id?:string,epoch?:int,member_journal?:string,member_generation?:int,dirty?:bool,pinned?:bool}> $cache
     * @return array<int,array{image:string,reader_id:string,source_id:string,epoch:int,member_journal:string,member_generation:int,dirty:bool,pinned:bool}>
     */
    private static function normalizeReaderCache(array $cache, int $pageSize): array
    {
        ksort($cache, SORT_NUMERIC);
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next169 cache page {$pageNumber} image must match page size");
            }
            $member = $entry['member_journal'] ?? '';
            $generation = $entry['member_generation'] ?? 0;
            if (!is_string($member) || $member === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next169 cache page {$pageNumber} requires member journal");
            }
            if (!is_int($generation) || $generation < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next169 cache page {$pageNumber} requires positive member generation");
            }
            $epoch = $entry['epoch'] ?? 0;
            if (!is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next169 cache page {$pageNumber} requires positive epoch");
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'reader_id' => isset($entry['reader_id']) && is_string($entry['reader_id']) && $entry['reader_id'] !== '' ? $entry['reader_id'] : 'reader-' . $pageNumber,
                'source_id' => isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '',
                'epoch' => $epoch,
                'member_journal' => $member,
                'member_generation' => $generation,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{reader_id:string,page_number:int,member_journal?:string,source_id?:string,epoch?:int}> $reads
     * @return list<array{reader_id:string,page_number:int,member_journal:string,source_id?:string,epoch?:int}>
     */
    private static function normalizeNextReads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $readerId = $read['reader_id'] ?? '';
            $pageNumber = $read['page_number'] ?? 0;
            $member = $read['member_journal'] ?? '';
            if (!is_string($readerId) || $readerId === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 reads require reader ids');
            }
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 reads require one-based page numbers');
            }
            if (!is_string($member) || $member === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next169 reads require member journals');
            }
            $normalized[] = $read + ['member_journal' => $member];
        }

        return $normalized;
    }

    /**
     * @param array<string,array{generation:int,recovered:bool,hot:bool,deleted:bool}> $states
     * @return list<string>
     */
    private static function unrecoveredMembers(array $states): array
    {
        $members = [];
        foreach ($states as $member => $state) {
            if (!$state['recovered'] || $state['hot'] || !$state['deleted']) {
                $members[] = $member;
            }
        }

        return $members;
    }

    /**
     * @param array<string,array{generation:int,recovered:bool,hot:bool,deleted:bool}> $states
     */
    private static function memberStateDigest(array $states): string
    {
        $parts = [];
        foreach ($states as $member => $state) {
            $parts[] = $member . ':' . $state['generation'] . ':' . (int) $state['recovered'] . ':' . (int) $state['hot'] . ':' . (int) $state['deleted'];
        }

        return hash('sha256', implode('|', $parts));
    }

    private static function digest(string $image): string
    {
        return hash('sha256', $image);
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 72), ".\0 ");
    }
}
