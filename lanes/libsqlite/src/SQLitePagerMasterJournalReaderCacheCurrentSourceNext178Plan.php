<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext178Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,member_journal?:string,member_generation?:int,master_digest?:string,dirty?:bool,pinned?:bool}> $readerCache
     * @param array<string,array{generation:int,deleted?:bool,recovered?:bool}> $memberStates
     * @param list<int> $readPages
     * @param array<int,string> $writePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $recoveredPages,
        array $readerCache,
        array $memberStates,
        array $readPages,
        array $writePages,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager reader-cache next178 requires non-empty paths and source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager reader-cache next178 requires current master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next178 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next178 database bytes must be page-size aligned');
        }
        if ($recoveredPages === [] || $readerCache === [] || $memberStates === [] || ($readPages === [] && $writePages === [])) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next178 requires recovered pages, cache entries, member states, and next work');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next178 epoch must be positive');
        }

        $members = self::members($currentMasterJournalBytes);
        $journalPath = $databasePath . '-journal';
        if (!in_array($journalPath, $members, true)) {
            throw new \RuntimeException('SQLite pager reader-cache next178 current master journal does not reference the database journal');
        }

        $database = self::pages($databaseBytes, $pageSize);
        $recoveredPages = self::images($recoveredPages, $pageSize, 'recovered');
        $readerCache = self::cache($readerCache, $pageSize);
        $memberStates = self::memberStates($memberStates, $members);
        self::pageList($readPages, 'read');
        $writePages = self::images($writePages, $pageSize, 'write');

        foreach ($recoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next178 recovered page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = ['image' => $image, 'source' => 'master-journal-member-generation-current-source-next178'];
        }

        $masterDigest = hash('sha256', implode("\n", $members));
        $memberRows = [];
        foreach ($members as $member) {
            $state = $memberStates[$member];
            $memberRows[] = [
                'journal' => $member,
                'generation' => $state['generation'],
                'deleted' => $state['deleted'],
                'recovered' => $state['recovered'],
                'admitted' => $state['deleted'] && $state['recovered'],
                'reason' => $state['deleted'] && $state['recovered'] ? 'member_journal_recovered_and_deleted' : ($state['recovered'] ? 'member_journal_not_deleted_after_recovery' : 'member_journal_not_recovered'),
            ];
        }

        $operations = [[
            'op' => 'read_current_master_journal_member_generation_next178',
            'path' => $masterJournalPath,
            'members' => $members,
        ], [
            'op' => 'verify_member_journal_recovery_and_delete_state_next178',
            'member_states' => $memberRows,
        ]];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $cacheRows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next178 cache page {$pageNumber} is outside the database image");
            }
            $member = $entry['member_journal'];
            $state = $memberStates[$member];
            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if (!$state['recovered']) {
                $reason = 'reader_cache_member_journal_not_recovered';
            } elseif (!$state['deleted']) {
                $reason = 'reader_cache_member_journal_not_deleted';
            } elseif ($entry['member_generation'] !== $state['generation']) {
                $reason = 'reader_cache_member_generation_mismatch';
            } elseif ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_master_member_boundary';
            } elseif ($entry['pinned'] && !hash_equals($entry['image'], $currentImage)) {
                $reason = 'pinned_reader_cache_image_predates_member_recovery';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch_after_member_recovery';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_mismatch_after_member_recovery';
            } elseif ($entry['master_digest'] !== $masterDigest) {
                $reason = 'reader_cache_master_digest_mismatch_after_member_recovery';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = $reason;
                $operations[] = ['op' => 'invalidate_reader_cache_member_generation_next178', 'page_number' => $pageNumber, 'reason' => $reason];
            } elseif (!hash_equals($entry['image'], $currentImage)) {
                $refreshed[$pageNumber] = $currentImage;
                $operations[] = ['op' => 'refresh_reader_cache_member_generation_next178', 'page_number' => $pageNumber];
            } else {
                $retained[$pageNumber] = $entry['image'];
                $operations[] = ['op' => 'retain_reader_cache_member_generation_next178', 'page_number' => $pageNumber];
            }

            $cacheRows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'member_journal' => $member,
                'member_generation_before' => $entry['member_generation'],
                'member_generation_current' => $state['generation'],
                'member_deleted' => $state['deleted'],
                'member_recovered' => $state['recovered'],
                'admitted' => $reason === null,
                'reason' => $reason ?? (hash_equals($entry['image'], $currentImage) ? 'reader_cache_matches_recovered_member_current_source' : 'reader_cache_refreshed_from_recovered_member_current_source'),
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next178 read page {$pageNumber} is outside the database image");
            }
            $cacheImage = $retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null;
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheImage !== null,
                'source' => $cacheImage !== null ? 'reader-cache-member-generation-current-source-next178' : $database[$pageNumber]['source'],
                'prefix' => self::prefix($cacheImage ?? $database[$pageNumber]['image']),
            ];
            $operations[] = ['op' => $cacheImage !== null ? 'next_read_uses_member_generation_reader_cache_next178' : 'next_read_uses_member_generation_current_source_next178', 'page_number' => $pageNumber];
        }

        $writes = [];
        foreach ($writePages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next178 write page {$pageNumber} is outside the database image");
            }
            $before = $database[$pageNumber]['image'];
            $database[$pageNumber] = ['image' => $image, 'source' => 'next-write-after-member-generation-reader-cache-next178'];
            $writes[] = [
                'page_number' => $pageNumber,
                'before_prefix' => self::prefix($before),
                'after_prefix' => self::prefix($image),
                'journal_before_from_recovered_member_source' => true,
            ];
            $operations[] = ['op' => 'capture_next_write_after_member_generation_reader_cache_next178', 'page_number' => $pageNumber];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next178',
            'reason' => 'current master-journal member generations and delete state fence reader-cache reuse',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'journal_path' => $journalPath,
            'page_size' => $pageSize,
            'master_members' => $members,
            'master_digest' => $masterDigest,
            'member_rows' => $memberRows,
            'recovered_page_numbers' => array_keys($recoveredPages),
            'cache_rows' => $cacheRows,
            'retained_page_numbers' => array_keys($retained),
            'refreshed_page_numbers' => array_keys($refreshed),
            'invalidated_page_numbers' => array_keys($invalidated),
            'invalidated_reasons' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [],
            'next_reads' => $reads,
            'next_writes' => $writes,
            'operations' => $operations,
            'final_sources' => array_map(static fn (array $page): string => $page['source'], $database),
            'final_prefixes' => array_map(static fn (array $page): string => self::prefix($page['image']), $database),
            'final_database_bytes' => self::bytes($database, $pageSize),
            'source_digest' => hash('sha256', $masterDigest . '|' . implode(',', array_keys($retained)) . '|' . implode(',', array_keys($refreshed)) . '|' . implode(',', $invalidated)),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next178',
                'sqlite-pager-master-journal-reader-cache-current-source-next169',
                'sqlite-pager-master-journal-reader-cache-current-source-next175',
            ],
            'non_overlap' => 'Adds member journal generation and delete-state admission for reader-cache reuse; avoids next175 checksum fencing and accepted next169 membership-only cache reuse.',
        ];
    }

    /** @return list<string> */
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

    /** @return array<int,array{image:string,source:string}> */
    private static function pages(string $bytes, int $pageSize): array
    {
        $pages = [];
        for ($offset = 0, $page = 1; $offset < strlen($bytes); $offset += $pageSize, $page++) {
            $pages[$page] = ['image' => substr($bytes, $offset, $pageSize), 'source' => 'database-before-member-generation-recovery-next178'];
        }
        return $pages;
    }

    /** @param array<int,string> $images @return array<int,string> */
    private static function images(array $images, int $pageSize, string $label): array
    {
        ksort($images, SORT_NUMERIC);
        foreach ($images as $page => $image) {
            if (!is_int($page) || $page < 1 || !is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next178 {$label} images must use one-based page numbers and full page images");
            }
        }
        return $images;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function cache(array $cache, int $pageSize): array
    {
        ksort($cache, SORT_NUMERIC);
        foreach ($cache as $page => $entry) {
            if (!is_int($page) || $page < 1 || !isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager reader-cache next178 cache entries must use one-based page numbers and full page images');
            }
            $cache[$page] = [
                'image' => $entry['image'],
                'source_id' => (string)($entry['source_id'] ?? ''),
                'epoch' => (int)($entry['epoch'] ?? 0),
                'reader_id' => (string)($entry['reader_id'] ?? ('reader-' . $page)),
                'member_journal' => (string)($entry['member_journal'] ?? ''),
                'member_generation' => (int)($entry['member_generation'] ?? 0),
                'master_digest' => (string)($entry['master_digest'] ?? ''),
                'dirty' => (bool)($entry['dirty'] ?? false),
                'pinned' => (bool)($entry['pinned'] ?? false),
            ];
            if ($cache[$page]['source_id'] === '' || $cache[$page]['epoch'] < 1 || $cache[$page]['member_journal'] === '' || $cache[$page]['member_generation'] < 1 || $cache[$page]['master_digest'] === '') {
                throw new \InvalidArgumentException('SQLite pager reader-cache next178 cache entries require source, epoch, member journal/generation, and master digest');
            }
        }
        return $cache;
    }

    /**
     * @param array<string,array<string,mixed>> $states
     * @param list<string> $members
     * @return array<string,array{generation:int,deleted:bool,recovered:bool}>
     */
    private static function memberStates(array $states, array $members): array
    {
        $normalized = [];
        foreach ($members as $member) {
            if (!isset($states[$member]) || !is_array($states[$member])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next178 missing member state for {$member}");
            }
            $generation = $states[$member]['generation'] ?? 0;
            if (!is_int($generation) || $generation < 1) {
                throw new \InvalidArgumentException('SQLite pager reader-cache next178 member generations must be positive integers');
            }
            $normalized[$member] = [
                'generation' => $generation,
                'deleted' => (bool)($states[$member]['deleted'] ?? false),
                'recovered' => (bool)($states[$member]['recovered'] ?? false),
            ];
        }
        return $normalized;
    }

    /** @param list<int> $pages */
    private static function pageList(array $pages, string $label): void
    {
        foreach ($pages as $page) {
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next178 {$label} pages must be one-based");
            }
        }
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }

    /** @param array<int,array{image:string,source:string}> $pages */
    private static function bytes(array $pages, int $pageSize): string
    {
        ksort($pages, SORT_NUMERIC);
        $bytes = '';
        foreach ($pages as $page) {
            if (strlen($page['image']) !== $pageSize) {
                throw new \RuntimeException('SQLite pager reader-cache next178 final image is not page-size bytes');
            }
            $bytes .= $page['image'];
        }
        return $bytes;
    }
}
