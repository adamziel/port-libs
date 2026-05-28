<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext200Plan
{
    /**
     * @param array<int,array{image:string,label?:string,member_journal_path?:string,member_generation_token?:string,reader_id?:string,reader_transaction_id?:string,reader_member_generation_token?:string,page_source_digest?:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<array{reader_id?:string,reader_transaction_id:string,page_number:int,member_journal_path:string,member_generation_token?:string,reader_member_generation_token?:string,page_source_digest?:string}> $nextReads
     * @param array<int,array{image:string,member_journal_path:string}> $currentPages
     * @param array<string,int> $memberSourceGenerations
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $readerCache,
        array $nextReads,
        array $currentPages,
        array $memberSourceGenerations,
        string $currentSourceId,
        int $currentEpoch,
        int $checkpointGeneration,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 requires non-empty database path, master path, and source id');
        }
        if (trim(str_replace("\0", '', $currentMasterJournalBytes)) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 requires master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 database bytes must be page-size aligned');
        }
        if ($readerCache === [] || $nextReads === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 requires reader cache and next reads');
        }
        if ($currentEpoch < 1 || $checkpointGeneration < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 requires positive epoch and checkpoint generation');
        }

        $members = self::members($currentMasterJournalBytes);
        $mainJournal = $databasePath . '-journal';
        if (!in_array($mainJournal, $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next200 current master journal does not reference the database journal');
        }
        $memberTokens = self::memberTokens($members, $memberSourceGenerations, $masterJournalPath, $currentMasterJournalBytes, $checkpointGeneration);
        $sourceMap = self::sourceMap($databaseBytes, $pageSize, $currentPages, $memberTokens);
        $cache = self::cache($readerCache, $pageSize);
        $reads = self::reads($nextReads);
        $groups = self::readGroups($reads, $sourceMap, $memberTokens);

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        $operations = [[
            'op' => 'derive_master_journal_member_generation_tokens_next200',
            'master_journal_path' => $masterJournalPath,
            'members' => $members,
            'member_generation_tokens' => $memberTokens,
            'checkpoint_generation' => $checkpointGeneration,
        ]];

        foreach ($cache as $pageNumber => $entry) {
            if (!isset($sourceMap[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next200 cache page {$pageNumber} is outside the database image");
            }
            $current = $sourceMap[$pageNumber];
            $groupId = $entry['reader_transaction_id'];
            $currentGroupToken = $groups[$groupId]['token'] ?? null;
            if ($currentGroupToken === null) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next200 cache transaction {$groupId} is not represented by next reads");
            }

            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_member_generation_fence';
            } elseif (!hash_equals($entry['member_generation_token'], $current['member_generation_token'])) {
                $reason = 'reader_cache_member_generation_token_predates_current_source';
            } elseif (!hash_equals($entry['page_source_digest'], $current['page_source_digest'])) {
                $reason = 'reader_cache_page_source_digest_predates_member_generation';
            } elseif (!hash_equals($entry['reader_member_generation_token'], $currentGroupToken)) {
                $reason = 'reader_cache_transaction_member_generation_predates_current_source';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_predates_current_source';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_predates_current_source';
            } elseif ($entry['pinned'] && !hash_equals($entry['image'], $current['image'])) {
                $reason = 'pinned_reader_cache_image_predates_member_generation';
            }

            if ($reason !== null) {
                foreach (array_keys($groups[$groupId]['pages']) as $groupPage) {
                    $invalidated[$groupPage] = $reason;
                }
                $operations[] = [
                    'op' => 'invalidate_reader_cache_member_generation_after_master_current_source_next200',
                    'page_number' => $pageNumber,
                    'reader_transaction_id' => $groupId,
                    'reason' => $reason,
                ];
            } elseif (!hash_equals($entry['image'], $current['image'])) {
                $refreshed[$pageNumber] = $current['image'];
                $operations[] = ['op' => 'refresh_reader_cache_member_generation_after_master_current_source_next200', 'page_number' => $pageNumber];
            } else {
                $retained[$pageNumber] = $entry['image'];
                $operations[] = ['op' => 'retain_reader_cache_member_generation_after_master_current_source_next200', 'page_number' => $pageNumber];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'label' => $entry['label'],
                'reader_id' => $entry['reader_id'],
                'reader_transaction_id' => $groupId,
                'admitted' => $reason === null,
                'reason' => $reason ?? (hash_equals($entry['image'], $current['image']) ? 'reader_cache_member_generation_matches_current_source' : 'reader_cache_refreshed_after_member_generation'),
                'member_journal_path' => $entry['member_journal_path'],
                'current_member_journal_path' => $current['member_journal_path'],
                'member_generation_token_matches' => hash_equals($entry['member_generation_token'], $current['member_generation_token']),
                'page_source_digest_matches' => hash_equals($entry['page_source_digest'], $current['page_source_digest']),
                'reader_member_generation_token_matches' => hash_equals($entry['reader_member_generation_token'], $currentGroupToken),
                'cache_member_generation_token' => $entry['member_generation_token'],
                'current_member_generation_token' => $current['member_generation_token'],
                'cache_reader_member_generation_token' => $entry['reader_member_generation_token'],
                'current_reader_member_generation_token' => $currentGroupToken,
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
            if (!isset($sourceMap[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next200 read page {$pageNumber} is outside the database image");
            }
            $group = $groups[$read['reader_transaction_id']];
            $current = $sourceMap[$pageNumber];
            $ticketCurrent = hash_equals($read['member_generation_token'], $current['member_generation_token'])
                && hash_equals($read['page_source_digest'], $current['page_source_digest'])
                && hash_equals($read['reader_member_generation_token'], $group['token']);
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
                'source' => $cacheImage !== null ? 'member-generation-reader-cache-current-source-next200' : 'member-generation-reader-reopen-current-source-next200',
                'reason' => $cacheImage !== null
                    ? 'next_read_uses_member_generation_current_reader_cache'
                    : ($groupInvalidated ? 'next_read_reopens_after_member_generation_cache_invalidation' : 'reader_ticket_member_generation_predates_current_source'),
                'member_generation_current' => hash_equals($read['member_generation_token'], $current['member_generation_token']),
                'page_source_current' => hash_equals($read['page_source_digest'], $current['page_source_digest']),
                'reader_member_generation_current' => hash_equals($read['reader_member_generation_token'], $group['token']),
                'reader_member_generation_token' => $group['token'],
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
            'status' => 'pager-master-journal-reader-cache-current-source-next200',
            'reason' => 'master_journal_member_generation_fences_reader_cache_current_source_reuse',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'member_generation_tokens' => $memberTokens,
            'current_page_source_digests' => array_column($sourceMap, 'page_source_digest'),
            'reader_member_generation_tokens' => array_map(static fn (array $group): string => $group['token'], $groups),
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
            'source_digest' => hash('sha256', $currentSourceId . '|' . $currentEpoch . '|' . $checkpointGeneration . '|' . hash('sha256', json_encode([$memberTokens, $groups, $invalidatedPages], JSON_THROW_ON_ERROR))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next200',
                'sqlite-pager-master-journal-member-generation-reader-cache-fence',
            ],
            'non_overlap' => 'next200 adds member-journal generation and transaction member-generation ticket fencing after master-journal recovery; it does not repeat next194 transaction snapshot digests, next193 stable master reads, next191 delete-directory-sync fencing, VFS rollback/commit/sync application, or WAL checkpoint/savepoint byte truncation.',
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
        ksort($members, SORT_STRING);

        return array_values($members);
    }

    /** @param list<string> $members @param array<string,int> $generations @return array<string,string> */
    private static function memberTokens(array $members, array $generations, string $masterPath, string $masterBytes, int $checkpointGeneration): array
    {
        $tokens = [];
        foreach ($members as $member) {
            $generation = $generations[$member] ?? null;
            if (!is_int($generation) || $generation < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next200 member {$member} requires a positive generation");
            }
            $tokens[$member] = 'member-source-generation:' . substr(hash('sha256', $masterPath . '|' . hash('sha256', $masterBytes) . '|' . $checkpointGeneration . '|' . $member . '|' . $generation), 0, 40);
        }

        return $tokens;
    }

    /**
     * @param array<int,array{image:string,member_journal_path:string}> $currentPages
     * @param array<string,string> $memberTokens
     * @return array<int,array{image:string,member_journal_path:string,member_generation_token:string,page_source_digest:string}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize, array $currentPages, array $memberTokens): array
    {
        $map = [];
        foreach (str_split($databaseBytes, $pageSize) as $index => $image) {
            $page = $index + 1;
            $map[$page] = [
                'image' => $image,
                'member_journal_path' => 'database-image-before-master-journal-recovery-next200',
                'member_generation_token' => 'database-before-master-member-generation-next200',
                'page_source_digest' => self::pageDigest($page, $image, 'database-image-before-master-journal-recovery-next200', 'database-before-master-member-generation-next200'),
            ];
        }
        foreach ($currentPages as $pageNumber => $entry) {
            $image = $entry['image'] ?? null;
            $member = $entry['member_journal_path'] ?? '';
            if (!is_int($pageNumber) || $pageNumber < 1 || !isset($map[$pageNumber]) || !is_string($image) || strlen($image) !== $pageSize || !is_string($member) || !isset($memberTokens[$member])) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 current pages require valid page images and member journal paths');
            }
            $map[$pageNumber] = [
                'image' => $image,
                'member_journal_path' => $member,
                'member_generation_token' => $memberTokens[$member],
                'page_source_digest' => self::pageDigest($pageNumber, $image, $member, $memberTokens[$member]),
            ];
        }

        return $map;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function cache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1 || !isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 cache entries require one-based page numbers and page-size images');
            }
            foreach (['member_journal_path', 'member_generation_token', 'reader_transaction_id', 'reader_member_generation_token', 'page_source_digest', 'source_id'] as $key) {
                if (!isset($entry[$key]) || !is_string($entry[$key]) || $entry[$key] === '') {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next200 cache entries require {$key}");
                }
            }
            $epoch = $entry['epoch'] ?? 0;
            if (!is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 cache entries require positive epoch');
            }
            $entry['label'] = (string) ($entry['label'] ?? ('page-' . $pageNumber));
            $entry['reader_id'] = (string) ($entry['reader_id'] ?? ('reader-' . $pageNumber));
            $entry['dirty'] = (bool) ($entry['dirty'] ?? false);
            $entry['pinned'] = (bool) ($entry['pinned'] ?? false);
            $normalized[$pageNumber] = $entry;
        }

        return $normalized;
    }

    /** @param list<array<string,mixed>> $reads @return list<array<string,mixed>> */
    private static function reads(array $reads): array
    {
        $normalized = [];
        foreach ($reads as $read) {
            $page = $read['page_number'] ?? 0;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 reads require one-based page numbers');
            }
            foreach (['reader_transaction_id', 'member_journal_path', 'member_generation_token', 'reader_member_generation_token', 'page_source_digest'] as $key) {
                if (!isset($read[$key]) || !is_string($read[$key]) || $read[$key] === '') {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next200 reads require {$key}");
                }
            }
            $read['reader_id'] = (string) ($read['reader_id'] ?? ('read-' . $page));
            if ($read['reader_id'] === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next200 reads require reader ids');
            }
            $normalized[] = $read;
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $reads
     * @param array<int,array{image:string,member_journal_path:string,member_generation_token:string,page_source_digest:string}> $sourceMap
     * @param array<string,string> $memberTokens
     * @return array<string,array{token:string,pages:array<int,true>}>
     */
    private static function readGroups(array $reads, array $sourceMap, array $memberTokens): array
    {
        $parts = [];
        $pages = [];
        foreach ($reads as $read) {
            $page = $read['page_number'];
            $group = $read['reader_transaction_id'];
            $member = $read['member_journal_path'];
            if (!isset($sourceMap[$page])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next200 read page {$page} is outside current source map");
            }
            if (!isset($memberTokens[$member]) && $member !== 'database-image-before-master-journal-recovery-next200') {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next200 read member {$member} is not in the current master journal");
            }
            $parts[$group][] = $page . ':' . $sourceMap[$page]['member_generation_token'] . ':' . $sourceMap[$page]['page_source_digest'];
            $pages[$group][$page] = true;
        }
        $groups = [];
        foreach ($parts as $group => $groupParts) {
            sort($groupParts, SORT_NATURAL);
            $groups[$group] = [
                'token' => 'reader-member-generation:' . substr(hash('sha256', $group . '|' . implode('|', $groupParts)), 0, 40),
                'pages' => $pages[$group],
            ];
        }

        return $groups;
    }

    private static function pageDigest(int $pageNumber, string $image, string $member, string $token): string
    {
        return hash('sha256', $pageNumber . '|' . $member . '|' . $token . '|' . hash('sha256', $image));
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 64), ".\0 ");
    }

    /** @param list<string> $values @return list<string> */
    private static function sortedStrings(array $values): array
    {
        sort($values, SORT_NATURAL);

        return $values;
    }
}
