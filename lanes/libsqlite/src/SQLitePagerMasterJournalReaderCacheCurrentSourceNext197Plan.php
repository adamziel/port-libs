<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext197Plan
{
    /**
     * @param array<int,array{image:string,reader_id?:string,source_id?:string,epoch?:int,master_member_digest?:string,current_source_nonce?:string,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,master_member_digest?:string,current_source_nonce?:string}> $readRequests
     * @param array<int,string> $currentPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $masterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $readerCache,
        array $readRequests,
        array $currentPages,
        string $currentSourceId,
        int $currentEpoch,
        string $currentSourceNonce,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '' || $currentSourceNonce === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 requires non-empty paths, source id, and source nonce');
        }
        if (trim(str_replace("\0", '', $masterJournalBytes)) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 requires master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 database bytes must be page-size aligned');
        }
        if ($readerCache === [] || $readRequests === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 requires reader cache and read requests');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 requires positive epoch');
        }

        $members = self::members($masterJournalBytes);
        $mainJournal = $databasePath . '-journal';
        if (!in_array($mainJournal, $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next197 current master journal does not reference the database journal');
        }

        $database = self::pages($databaseBytes, $pageSize);
        foreach (self::images($currentPages, $pageSize, 'current') as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next197 current page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-member-current-source-next197',
            ];
        }

        $memberDigest = hash('sha256', implode("\n", $members));
        $cache = self::readerCache($readerCache, $pageSize);
        $reads = self::readRequests($readRequests);
        $nextSource = [
            'id' => 'master-journal-member-source:' . substr(hash('sha256', $memberDigest . '|' . $currentSourceNonce . '|' . $currentSourceId), 0, 32),
            'epoch' => $currentEpoch + 1,
            'member_digest' => $memberDigest,
            'nonce' => $currentSourceNonce,
        ];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        $operations = [[
            'op' => 'verify_master_journal_members_current_source_next197',
            'path' => $masterJournalPath,
            'members' => $members,
            'member_digest' => $memberDigest,
            'source_nonce' => $currentSourceNonce,
        ]];

        foreach ($cache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next197 cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_master_journal_member_source';
            } elseif ($entry['master_member_digest'] !== $memberDigest) {
                $reason = 'reader_cache_master_member_digest_mismatch';
            } elseif ($entry['current_source_nonce'] !== $currentSourceNonce) {
                $reason = 'reader_cache_current_source_nonce_mismatch';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_predates_master_member_source';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_predates_master_member_source';
            } elseif ($entry['pinned'] && !hash_equals($entry['image'], $currentImage)) {
                $reason = 'pinned_reader_cache_image_predates_master_member_source';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = $reason;
                $operations[] = ['op' => 'invalidate_reader_cache_after_master_member_source_next197', 'page_number' => $pageNumber, 'reason' => $reason];
            } elseif (!hash_equals($entry['image'], $currentImage)) {
                $refreshed[$pageNumber] = $currentImage;
                $operations[] = ['op' => 'refresh_reader_cache_after_master_member_source_next197', 'page_number' => $pageNumber];
            } else {
                $retained[$pageNumber] = $entry['image'];
                $operations[] = ['op' => 'retain_reader_cache_after_master_member_source_next197', 'page_number' => $pageNumber];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'admitted' => $reason === null,
                'reason' => $reason ?? (hash_equals($entry['image'], $currentImage) ? 'reader_cache_matches_master_journal_member_source' : 'reader_cache_refreshed_after_master_journal_member_source'),
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'member_digest_before' => $entry['master_member_digest'],
                'member_digest_current' => $memberDigest,
                'member_digest_matches' => $entry['master_member_digest'] === $memberDigest,
                'source_nonce_before' => $entry['current_source_nonce'],
                'source_nonce_current' => $currentSourceNonce,
                'source_nonce_matches' => $entry['current_source_nonce'] === $currentSourceNonce,
                'source_id_before' => $entry['source_id'],
                'epoch_before' => $entry['epoch'],
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
            ];
        }

        $nextReads = [];
        $readCacheHits = [];
        foreach ($reads as $read) {
            $pageNumber = $read['page_number'];
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next197 read page {$pageNumber} is outside the database image");
            }
            $requestCurrent = ($read['source_id'] ?? $currentSourceId) === $currentSourceId
                && ($read['epoch'] ?? $currentEpoch) === $currentEpoch
                && ($read['master_member_digest'] ?? $memberDigest) === $memberDigest
                && ($read['current_source_nonce'] ?? $currentSourceNonce) === $currentSourceNonce;
            $cacheImage = $requestCurrent ? ($retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null) : null;
            $readCacheHits[$read['reader_id']] = $cacheImage !== null;
            $nextReads[] = [
                'reader_id' => $read['reader_id'],
                'page_number' => $pageNumber,
                'cache_hit' => $cacheImage !== null,
                'request_current' => $requestCurrent,
                'reason' => $cacheImage !== null ? 'next_read_uses_master_member_current_source_cache' : 'next_read_reopens_after_master_member_source_change',
                'source_id' => $nextSource['id'],
                'epoch' => $nextSource['epoch'],
                'prefix' => self::prefix($cacheImage ?? $database[$pageNumber]['image']),
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next197',
            'reason' => 'master_journal_member_digest_and_source_nonce_fence_reader_cache_admission',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'current_member_digest' => $memberDigest,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch, 'nonce' => $currentSourceNonce],
            'next_source' => $nextSource,
            'reader_rows' => $rows,
            'retained_page_numbers' => array_keys($retained),
            'refreshed_page_numbers' => array_keys($refreshed),
            'invalidated_page_numbers' => array_keys($invalidated),
            'invalidated_reasons' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [],
            'next_reads' => $nextReads,
            'read_cache_hits' => $readCacheHits,
            'operations' => $operations,
            'final_prefixes' => array_map(static fn (array $page): string => self::prefix($page['image']), $database),
            'final_sources' => array_map(static fn (array $page): string => $page['source'], $database),
            'source_digest' => hash('sha256', $memberDigest . '|' . $currentSourceNonce . '|' . implode(',', array_keys($retained)) . '|' . implode(',', array_keys($invalidated))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next197',
                'sqlite-master-journal-member-digest-current-source-fence',
            ],
            'non_overlap' => 'Adds active master-journal member-digest plus current-source-nonce reader-cache fencing; avoids next191 delete/directory-sync fencing, next183 publication fences, rollback-journal commit/apply, super-journal commits, and WAL checkpoint/savepoint byte truncation.',
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

    /** @return array<int,array{image:string,source:string}> */
    private static function pages(string $bytes, int $pageSize): array
    {
        $pages = [];
        foreach (str_split($bytes, $pageSize) as $index => $image) {
            $pages[$index + 1] = ['image' => $image, 'source' => 'database-before-master-member-reader-cache-next197'];
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
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next197 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next197 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,reader_id?:string,source_id?:string,epoch?:int,master_member_digest?:string,current_source_nonce?:string,dirty?:bool,pinned?:bool}> $cache
     * @return array<int,array{image:string,reader_id:string,source_id:string,epoch:int,master_member_digest:string,current_source_nonce:string,dirty:bool,pinned:bool}>
     */
    private static function readerCache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next197 cache page {$pageNumber} must be page-size bytes");
            }
            $readerId = (string) ($entry['reader_id'] ?? ('reader-' . $pageNumber));
            $sourceId = (string) ($entry['source_id'] ?? '');
            $epoch = $entry['epoch'] ?? 0;
            $memberDigest = (string) ($entry['master_member_digest'] ?? '');
            $sourceNonce = (string) ($entry['current_source_nonce'] ?? '');
            if ($readerId === '' || $sourceId === '' || $memberDigest === '' || $sourceNonce === '' || !is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 cache entries require reader, source, epoch, member digest, and source nonce');
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'reader_id' => $readerId,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'master_member_digest' => $memberDigest,
                'current_source_nonce' => $sourceNonce,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,master_member_digest?:string,current_source_nonce?:string}> $reads
     * @return list<array{reader_id:string,page_number:int,source_id?:string,epoch?:int,master_member_digest?:string,current_source_nonce?:string}>
     */
    private static function readRequests(array $reads): array
    {
        foreach ($reads as $read) {
            if (($read['reader_id'] ?? '') === '' || !isset($read['page_number']) || !is_int($read['page_number']) || $read['page_number'] < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 read requests require reader id and one-based page number');
            }
            if (isset($read['source_id']) && $read['source_id'] === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 read request source id cannot be empty');
            }
            if (isset($read['epoch']) && (!is_int($read['epoch']) || $read['epoch'] < 1)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 read request epoch must be positive');
            }
            if (isset($read['master_member_digest']) && $read['master_member_digest'] === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 read request member digest cannot be empty');
            }
            if (isset($read['current_source_nonce']) && $read['current_source_nonce'] === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next197 read request source nonce cannot be empty');
            }
        }

        return $reads;
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }
}
