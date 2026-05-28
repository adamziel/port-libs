<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext188Plan
{
    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,member_token?:string,member_digest?:string,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<int> $readPages
     * @param array<int,string> $refreshedPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $readerCache,
        array $readPages,
        array $refreshedPages,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next188 requires non-empty paths and source id');
        }
        if ($currentMasterJournalBytes === '' || trim(str_replace("\0", '', $currentMasterJournalBytes)) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next188 requires master-journal member bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next188 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next188 database bytes must be page-size aligned');
        }
        if ($readerCache === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next188 requires reader cache and read pages');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next188 current epoch must be positive');
        }

        $members = self::members($currentMasterJournalBytes);
        $journalPath = $databasePath . '-journal';
        if (!in_array($journalPath, $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next188 current master journal does not reference the database journal');
        }

        $database = self::pages($databaseBytes, $pageSize);
        $readerCache = self::readerCache($readerCache, $pageSize);
        self::pageList($readPages, 'read');
        $refreshedPages = self::images($refreshedPages, $pageSize, 'refreshed');
        foreach ($refreshedPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next188 refreshed page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'nul-sector-master-journal-current-source-next188',
            ];
        }

        $memberToken = self::memberToken($members);
        $memberDigest = hash('sha256', implode("\n", $members));
        $nextSource = [
            'id' => 'master-journal-nul-member-source:' . substr(hash('sha256', $masterJournalPath . '|' . $memberToken), 0, 32),
            'epoch' => $currentEpoch + 1,
        ];
        $operations = [[
            'op' => 'read_sector_padded_nul_master_journal_members_next188',
            'path' => $masterJournalPath,
            'members' => $members,
            'member_token' => $memberToken,
        ]];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next188 cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_nul_master_journal_read';
            } elseif ($entry['member_token'] !== $memberToken) {
                $reason = 'reader_cache_master_member_token_mismatch_after_nul_parse';
            } elseif (!hash_equals($entry['member_digest'], $memberDigest)) {
                $reason = 'reader_cache_master_member_digest_mismatch_after_nul_parse';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch_after_nul_master_read';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_mismatch_after_nul_master_read';
            } elseif ($entry['pinned'] && !hash_equals($entry['image'], $currentImage)) {
                $reason = 'pinned_reader_cache_image_predates_nul_master_read';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = $reason;
                $operations[] = ['op' => 'invalidate_reader_cache_after_nul_master_journal_parse_next188', 'page_number' => $pageNumber, 'reason' => $reason];
            } elseif (!hash_equals($entry['image'], $currentImage)) {
                $refreshed[$pageNumber] = $currentImage;
                $operations[] = ['op' => 'refresh_reader_cache_after_nul_master_journal_parse_next188', 'page_number' => $pageNumber];
            } else {
                $retained[$pageNumber] = $entry['image'];
                $operations[] = ['op' => 'retain_reader_cache_after_nul_master_journal_parse_next188', 'page_number' => $pageNumber];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'admitted' => $reason === null,
                'reason' => $reason ?? (hash_equals($entry['image'], $currentImage) ? 'reader_cache_matches_nul_master_journal_source' : 'reader_cache_refreshed_from_nul_master_journal_source'),
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'member_token_before' => $entry['member_token'],
                'member_token_current' => $memberToken,
                'member_token_matches' => $entry['member_token'] === $memberToken,
                'member_digest_before' => $entry['member_digest'],
                'member_digest_current' => $memberDigest,
                'member_digest_matches' => hash_equals($entry['member_digest'], $memberDigest),
                'source_id_before' => $entry['source_id'],
                'epoch_before' => $entry['epoch'],
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next188 read page {$pageNumber} is outside the database image");
            }
            $cacheImage = $retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null;
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheImage !== null,
                'reason' => $cacheImage !== null ? 'next_read_uses_nul_master_journal_reader_cache' : 'next_read_reopens_after_nul_master_journal_parse',
                'source_id' => $nextSource['id'],
                'epoch' => $nextSource['epoch'],
                'prefix' => self::prefix($cacheImage ?? $database[$pageNumber]['image']),
            ];
            $operations[] = ['op' => $cacheImage !== null ? 'next_read_uses_nul_master_reader_cache_next188' : 'next_read_reopens_after_nul_master_parse_next188', 'page_number' => $pageNumber];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next188',
            'reason' => 'sector-padded NUL master-journal member bytes fence reader-cache current-source admission',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'current_member_token' => $memberToken,
            'current_member_digest' => $memberDigest,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'next_source' => $nextSource,
            'reader_rows' => $rows,
            'retained_page_numbers' => array_keys($retained),
            'refreshed_page_numbers' => array_keys($refreshed),
            'invalidated_page_numbers' => array_keys($invalidated),
            'invalidated_reasons' => $invalidated,
            'next_reads' => $reads,
            'operations' => $operations,
            'final_prefixes' => array_map(static fn (array $page): string => self::prefix($page['image']), $database),
            'final_sources' => array_map(static fn (array $page): string => $page['source'], $database),
            'source_digest' => hash('sha256', $memberToken . '|' . implode(',', array_keys($retained)) . '|' . implode(',', array_keys($invalidated))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next188',
                'sqlite-master-journal-nul-sector-member-parser',
                'sqlite-pager-master-journal-reader-cache-current-source-next185',
            ],
            'non_overlap' => 'Adds sector-padded NUL-separated master-journal member parsing and token fencing; avoids next185 finite rollback truncation, next184 file generation tokens, next181 pending membership, and rollback-journal apply/commit paths.',
        ];
    }

    /** @return list<string> */
    private static function members(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/[\r\n\0]+/', $bytes) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '') {
                $members[$part] = $part;
            }
        }
        ksort($members, SORT_STRING);

        return array_values($members);
    }

    /** @param list<string> $members */
    private static function memberToken(array $members): string
    {
        return 'nul-sector-members:' . substr(hash('sha256', implode("\n", $members)), 0, 40);
    }

    /** @return array<int,array{image:string,source:string}> */
    private static function pages(string $bytes, int $pageSize): array
    {
        $pages = [];
        foreach (str_split($bytes, $pageSize) as $index => $image) {
            $pages[$index + 1] = ['image' => $image, 'source' => 'database-before-nul-master-reader-cache-next188'];
        }

        return $pages;
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private static function images(array $images, int $pageSize, string $label): array
    {
        $normalized = [];
        foreach ($images as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next188 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next188 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,member_token?:string,member_digest?:string,dirty?:bool,pinned?:bool}> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,member_token:string,member_digest:string,dirty:bool,pinned:bool}>
     */
    private static function readerCache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next188 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next188 cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = (string) ($entry['source_id'] ?? '');
            $readerId = (string) ($entry['reader_id'] ?? ('reader-' . $pageNumber));
            $memberToken = (string) ($entry['member_token'] ?? '');
            $memberDigest = (string) ($entry['member_digest'] ?? '');
            $epoch = $entry['epoch'] ?? 0;
            if ($sourceId === '' || $readerId === '' || $memberToken === '' || $memberDigest === '' || !is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next188 cache entries require source, reader, member token, digest, and positive epoch');
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'reader_id' => $readerId,
                'member_token' => $memberToken,
                'member_digest' => $memberDigest,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /** @param list<int> $pages */
    private static function pageList(array $pages, string $label): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next188 {$label} page numbers must be one-based integers");
            }
        }
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }
}
