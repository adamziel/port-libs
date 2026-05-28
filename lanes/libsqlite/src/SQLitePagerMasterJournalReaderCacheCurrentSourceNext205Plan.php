<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext205Plan
{
    /**
     * @param array<int,string> $currentPages
     * @param array<int,array{image:string,label?:string,reader_id?:string,reader_transaction_id?:string,member_journal_path?:string,member_master_name_digest?:string,transaction_master_name_digest?:string,page_source_digest?:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<array{reader_id?:string,reader_transaction_id?:string,page_number:int,member_journal_path:string,member_master_name_digest?:string,transaction_master_name_digest?:string,page_source_digest?:string}> $nextReads
     * @param array<int,string> $pageMemberJournals
     * @param array<string,string> $memberMasterNames
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $currentPages,
        array $readerCache,
        array $nextReads,
        array $pageMemberJournals,
        array $memberMasterNames,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next205 requires paths and source id');
        }
        if (trim(str_replace("\0", '', $currentMasterJournalBytes)) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next205 requires master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next205 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next205 database bytes must be page-size aligned');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next205 epoch must be positive');
        }
        if ($currentPages === [] || $readerCache === [] || $nextReads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next205 requires pages, cache entries, and reads');
        }

        $members = self::members($currentMasterJournalBytes);
        $mainJournal = $databasePath . '-journal';
        if (!in_array($mainJournal, $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next205 current master journal does not reference the database journal');
        }

        $database = self::sourceMap($databaseBytes, $pageSize, $currentPages, $pageMemberJournals);
        $masterNameDigests = self::masterNameDigests($members, $memberMasterNames, $masterJournalPath);
        $cache = self::cache($readerCache, $pageSize);
        $reads = self::reads($nextReads);
        $groups = self::readGroups($reads, $database, $masterNameDigests);

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        $operations = [[
            'op' => 'derive_member_master_name_tokens_current_source_next205',
            'master_journal_path' => $masterJournalPath,
            'members' => $members,
            'member_master_name_digests' => $masterNameDigests,
        ]];

        foreach ($cache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 cache page {$pageNumber} is outside the database image");
            }
            $current = $database[$pageNumber];
            $group = $entry['reader_transaction_id'];
            $groupDigest = $groups[$group]['transaction_master_name_digest'] ?? null;
            if ($groupDigest === null) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 cache transaction {$group} is not represented by next reads");
            }

            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_member_master_name_fence';
            } elseif (!hash_equals($entry['member_master_name_digest'], $current['member_master_name_digest'])) {
                $reason = 'reader_cache_member_master_name_predates_current_source';
            } elseif (!hash_equals($entry['transaction_master_name_digest'], $groupDigest)) {
                $reason = 'reader_cache_transaction_master_name_predates_current_source';
            } elseif (!hash_equals($entry['page_source_digest'], $current['page_source_digest'])) {
                $reason = 'reader_cache_page_source_digest_predates_master_name';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_predates_current_master_name';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_predates_current_master_name';
            } elseif ($entry['pinned'] && !hash_equals($entry['image'], $current['image'])) {
                $reason = 'pinned_reader_cache_image_predates_current_master_name';
            }

            if ($reason !== null) {
                foreach (array_keys($groups[$group]['pages']) as $groupPage) {
                    $invalidated[$groupPage] = $reason;
                }
                $operations[] = [
                    'op' => 'invalidate_reader_cache_member_master_name_after_current_source_next205',
                    'page_number' => $pageNumber,
                    'reader_transaction_id' => $group,
                    'reason' => $reason,
                ];
            } elseif (!hash_equals($entry['image'], $current['image'])) {
                $refreshed[$pageNumber] = $current['image'];
                $operations[] = [
                    'op' => 'refresh_reader_cache_member_master_name_after_current_source_next205',
                    'page_number' => $pageNumber,
                ];
            } else {
                $retained[$pageNumber] = $entry['image'];
                $operations[] = [
                    'op' => 'retain_reader_cache_member_master_name_after_current_source_next205',
                    'page_number' => $pageNumber,
                ];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'label' => $entry['label'],
                'reader_id' => $entry['reader_id'],
                'reader_transaction_id' => $group,
                'admitted' => $reason === null,
                'reason' => $reason ?? (hash_equals($entry['image'], $current['image']) ? 'reader_cache_member_master_name_matches_current_source' : 'reader_cache_refreshed_after_member_master_name'),
                'member_journal_path' => $entry['member_journal_path'],
                'current_member_journal_path' => $current['member_journal_path'],
                'member_master_name_digest_matches' => hash_equals($entry['member_master_name_digest'], $current['member_master_name_digest']),
                'transaction_master_name_digest_matches' => hash_equals($entry['transaction_master_name_digest'], $groupDigest),
                'page_source_digest_matches' => hash_equals($entry['page_source_digest'], $current['page_source_digest']),
                'cache_member_master_name_digest' => $entry['member_master_name_digest'],
                'current_member_master_name_digest' => $current['member_master_name_digest'],
                'cache_transaction_master_name_digest' => $entry['transaction_master_name_digest'],
                'current_transaction_master_name_digest' => $groupDigest,
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($current['image']),
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
            ];
        }

        $next = [];
        $reopenReaders = [];
        foreach ($reads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 read page {$pageNumber} is outside the database image");
            }
            $group = $groups[$read['reader_transaction_id']];
            $current = $database[$pageNumber];
            $ticketCurrent = hash_equals($read['member_master_name_digest'], $current['member_master_name_digest'])
                && hash_equals($read['transaction_master_name_digest'], $group['transaction_master_name_digest'])
                && hash_equals($read['page_source_digest'], $current['page_source_digest']);
            $groupInvalidated = isset($invalidated[$pageNumber]);
            if (!$ticketCurrent || $groupInvalidated) {
                $reopenReaders[$read['reader_id']] = true;
            }
            $cacheImage = (!$ticketCurrent || $groupInvalidated) ? null : ($retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null);
            $next[] = [
                'reader_id' => $read['reader_id'],
                'reader_transaction_id' => $read['reader_transaction_id'],
                'page_number' => $pageNumber,
                'cache_hit' => $cacheImage !== null,
                'source' => $cacheImage !== null ? 'member-master-name-reader-cache-current-source-next205' : 'member-master-name-reader-reopen-current-source-next205',
                'reason' => $cacheImage !== null
                    ? 'next_read_uses_member_master_name_current_reader_cache'
                    : ($groupInvalidated ? 'next_read_reopens_after_member_master_name_cache_invalidation' : 'reader_ticket_member_master_name_predates_current_source'),
                'member_master_name_current' => hash_equals($read['member_master_name_digest'], $current['member_master_name_digest']),
                'transaction_master_name_current' => hash_equals($read['transaction_master_name_digest'], $group['transaction_master_name_digest']),
                'page_source_current' => hash_equals($read['page_source_digest'], $current['page_source_digest']),
                'transaction_master_name_digest' => $group['transaction_master_name_digest'],
                'prefix' => self::prefix($cacheImage ?? $current['image']),
            ];
        }

        $invalidatedPages = array_keys($invalidated);
        sort($invalidatedPages, SORT_NUMERIC);
        $retainedPages = array_values(array_diff(array_keys($retained), $invalidatedPages));
        $refreshedPages = array_values(array_diff(array_keys($refreshed), $invalidatedPages));
        sort($retainedPages, SORT_NUMERIC);
        sort($refreshedPages, SORT_NUMERIC);

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next205',
            'reason' => 'member_rollback_journal_master_name_fences_reader_cache_current_source_reuse',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'member_master_name_digests' => $masterNameDigests,
            'current_page_source_digests' => array_column($database, 'page_source_digest'),
            'reader_transaction_master_name_digests' => array_map(static fn (array $group): string => $group['transaction_master_name_digest'], $groups),
            'reader_rows' => $rows,
            'retained_cache_page_numbers' => $retainedPages,
            'refreshed_cache_page_numbers' => $refreshedPages,
            'invalidated_cache_page_numbers' => $invalidatedPages,
            'invalidated_reasons' => $invalidated,
            'requires_reader_reopen' => $invalidatedPages !== [],
            'next_reads' => $next,
            'read_cache_hits' => array_column($next, 'cache_hit', 'reader_id'),
            'reopen_reader_ids' => self::sortedStrings(array_keys($reopenReaders)),
            'operations' => $operations,
            'source_digest' => hash('sha256', $currentSourceId . '|' . $currentEpoch . '|' . hash('sha256', json_encode([$masterNameDigests, $groups, $invalidatedPages], JSON_THROW_ON_ERROR))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next205',
                'sqlite-pager-reader-cache-member-rollback-master-name-fence',
            ],
            'non_overlap' => 'next205 adds member rollback-journal master-name tickets after master-journal recovery; it does not repeat next203 member order, next200 member generations, next196 member header digests, next192 member tokens, next191 delete/sync proof, or accepted rollback/super-journal/VFS application paths.',
        ];
    }

    /** @return list<string> */
    private static function members(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/[\r\n\0]+/', $bytes) ?: [] as $member) {
            $member = trim($member);
            if ($member !== '') {
                $members[$member] = $member;
            }
        }
        if ($members === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next205 requires members');
        }

        return array_values($members);
    }

    /**
     * @param list<string> $members
     * @param array<string,string> $names
     * @return array<string,string>
     */
    private static function masterNameDigests(array $members, array $names, string $currentMasterPath): array
    {
        $digests = [];
        foreach ($members as $member) {
            $name = $names[$member] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 member {$member} requires rollback-journal master name");
            }
            if ($name !== $currentMasterPath) {
                throw new \RuntimeException("SQLite pager master-journal reader-cache next205 member {$member} names a different master journal");
            }
            $digests[$member] = hash('sha256', $member . '|' . $name);
        }

        return $digests;
    }

    /**
     * @param array<int,string> $databasePages
     * @param array<int,string> $currentPages
     * @param array<int,string> $pageMembers
     * @return array<int,array{image:string,member_journal_path:string,member_master_name_digest:string,page_source_digest:string}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize, array $currentPages, array $pageMembers): array
    {
        $pages = [];
        for ($offset = 0, $pageNumber = 1, $length = strlen($databaseBytes); $offset < $length; $offset += $pageSize, $pageNumber++) {
            $pages[$pageNumber] = substr($databaseBytes, $offset, $pageSize);
        }
        foreach ($currentPages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next205 current pages must be one-based page-size images');
            }
            if (!isset($pages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 current page {$pageNumber} is outside database image");
            }
            $pages[$pageNumber] = $image;
        }

        $source = [];
        foreach ($pages as $pageNumber => $image) {
            $member = $pageMembers[$pageNumber] ?? null;
            if (!is_string($member) || $member === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 page {$pageNumber} requires member journal path");
            }
            $source[$pageNumber] = [
                'image' => $image,
                'member_journal_path' => $member,
                'member_master_name_digest' => '',
                'page_source_digest' => hash('sha256', $pageNumber . '|' . $member . '|' . hash('sha256', $image)),
            ];
        }

        return $source;
    }

    /**
     * @param array<int,array<string,mixed>> $cache
     * @return array<int,array{image:string,label:string,reader_id:string,reader_transaction_id:string,member_journal_path:string,member_master_name_digest:string,transaction_master_name_digest:string,page_source_digest:string,source_id:string,epoch:int,dirty:bool,pinned:bool}>
     */
    private static function cache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            $image = $entry['image'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || !is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next205 cache entries require one-based page-size images');
            }
            $normalized[$pageNumber] = [
                'image' => $image,
                'label' => self::string($entry, 'label', 'page-' . $pageNumber),
                'reader_id' => self::string($entry, 'reader_id', 'reader-' . $pageNumber),
                'reader_transaction_id' => self::string($entry, 'reader_transaction_id', 'tx-' . $pageNumber),
                'member_journal_path' => self::string($entry, 'member_journal_path'),
                'member_master_name_digest' => self::string($entry, 'member_master_name_digest'),
                'transaction_master_name_digest' => self::string($entry, 'transaction_master_name_digest'),
                'page_source_digest' => self::string($entry, 'page_source_digest'),
                'source_id' => self::string($entry, 'source_id'),
                'epoch' => self::positiveInt($entry, 'epoch'),
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @return list<array{reader_id:string,reader_transaction_id:string,page_number:int,member_journal_path:string,member_master_name_digest:string,transaction_master_name_digest:string,page_source_digest:string}>
     */
    private static function reads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $page = $read['page_number'] ?? 0;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next205 reads require one-based page numbers');
            }
            $normalized[] = [
                'reader_id' => self::string($read, 'reader_id', 'read-' . $page),
                'reader_transaction_id' => self::string($read, 'reader_transaction_id'),
                'page_number' => $page,
                'member_journal_path' => self::string($read, 'member_journal_path'),
                'member_master_name_digest' => self::string($read, 'member_master_name_digest'),
                'transaction_master_name_digest' => self::string($read, 'transaction_master_name_digest'),
                'page_source_digest' => self::string($read, 'page_source_digest'),
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @param array<int,array<string,string>> $database
     * @param array<string,string> $masterNameDigests
     * @return array<string,array{transaction_master_name_digest:string,pages:array<int,true>}>
     */
    private static function readGroups(array $reads, array &$database, array $masterNameDigests): array
    {
        $groups = [];
        foreach ($database as $pageNumber => &$page) {
            $member = $page['member_journal_path'];
            $digest = $masterNameDigests[$member] ?? null;
            if (!is_string($digest) || $digest === '') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 page {$pageNumber} references a member outside current master journal");
            }
            $page['member_master_name_digest'] = $digest;
            $page['page_source_digest'] = hash('sha256', $pageNumber . '|' . $member . '|' . $digest . '|' . hash('sha256', $page['image']));
        }
        unset($page);

        foreach ($reads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 read page {$pageNumber} is outside the database image");
            }
            $member = $read['member_journal_path'];
            if ($member !== $database[$pageNumber]['member_journal_path']) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 read page {$pageNumber} member does not match current page source");
            }
            $groups[$read['reader_transaction_id']]['pages'][$pageNumber] = true;
        }
        foreach ($groups as $groupId => &$group) {
            ksort($group['pages'], SORT_NUMERIC);
            $parts = [];
            foreach (array_keys($group['pages']) as $pageNumber) {
                $parts[] = $pageNumber . ':' . $database[$pageNumber]['member_master_name_digest'];
            }
            $group['transaction_master_name_digest'] = hash('sha256', implode('|', $parts));
        }
        unset($group);

        return $groups;
    }

    /** @param array<string,mixed> $entry */
    private static function string(array $entry, string $key, ?string $default = null): string
    {
        $value = $entry[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 requires {$key}");
        }

        return $value;
    }

    /** @param array<string,mixed> $entry */
    private static function positiveInt(array $entry, string $key): int
    {
        $value = $entry[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next205 requires positive {$key}");
        }

        return $value;
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 72), ".\0 ");
    }

    /** @param list<string> $values @return list<string> */
    private static function sortedStrings(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values, SORT_NATURAL);

        return $values;
    }
}
