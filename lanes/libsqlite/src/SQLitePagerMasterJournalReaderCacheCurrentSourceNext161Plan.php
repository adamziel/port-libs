<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext161Plan
{
    /**
     * @param array<int,string> $masterRecoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string,master_journal_digest?:string}> $readerCachePages
     * @param list<int> $nextReadPages
     * @param array<int,string> $nextWritePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $masterRecoveredPages,
        array $readerCachePages,
        array $nextReadPages,
        array $nextWritePages,
        string $currentSourceId,
        int $currentSourceEpoch = 1,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 requires database and master-journal paths');
        }
        if ($currentMasterJournalBytes === null || trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 requires current master-journal bytes');
        }
        if (!str_contains($currentMasterJournalBytes, $databasePath . '-journal')) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next161 current master journal does not reference the database journal');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 database bytes must be page-size aligned');
        }
        if ($readerCachePages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 requires reader cache pages');
        }
        if ($nextReadPages === [] && $nextWritePages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 requires next read or write pages');
        }
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 requires current source id');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 source epoch must be positive');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $masterRecoveredPages = self::normalizeImages($masterRecoveredPages, $pageSize, 'master recovered');
        $readerCachePages = self::normalizeReaderCache($readerCachePages, $pageSize);
        self::assertPageList($nextReadPages, 'next read');
        $nextWritePages = self::normalizeImages($nextWritePages, $pageSize, 'next write', true);

        $cachedMembers = self::members($cachedMasterJournalBytes);
        $currentMembers = self::members($currentMasterJournalBytes);
        $cachedDigest = self::memberDigest($cachedMembers);
        $currentDigest = self::memberDigest($currentMembers);
        $recoveredSourceId = 'master-reader-cache:' . hash('sha256', $masterJournalPath . '|' . implode('|', $currentMembers));
        $recoveredEpoch = $currentSourceEpoch + 1;
        $cachedMembershipStale = $cachedMembers !== $currentMembers;

        $operations = [[
            'op' => 'read_current_master_journal_for_reader_cache',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
            'reason' => 'reader_cache_must_use_current_master_journal_membership',
        ]];
        if ($cachedMembershipStale) {
            $operations[] = [
                'op' => 'discard_cached_master_journal_members_for_reader_cache',
                'cached_members' => $cachedMembers,
                'current_members' => $currentMembers,
            ];
        }

        foreach ($masterRecoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next161 recovered page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-reader-cache-current-source',
            ];
            $operations[] = [
                'op' => 'restore_master_journal_page_before_reader_cache_check',
                'page_number' => $pageNumber,
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
            ];
        }

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $readerRows = [];
        $validCache = [];
        foreach ($readerCachePages as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next161 reader cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_from_failed_master_transaction';
            } elseif ($entry['master_journal_digest'] !== $currentDigest) {
                $reason = 'reader_cache_master_journal_digest_predates_current_membership';
            } elseif ($entry['pinned'] && $entry['image'] !== $currentImage) {
                $reason = 'pinned_reader_cache_image_predates_current_master_source';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_predates_current_master_source';
            } elseif ($entry['epoch'] !== $currentSourceEpoch) {
                $reason = 'reader_cache_epoch_predates_current_master_source';
            }

            if ($reason !== null) {
                $invalidated[] = $pageNumber;
                $operations[] = [
                    'op' => 'invalidate_reader_cache_after_master_journal_recovery',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                    'requires_reopen' => true,
                ];
            } elseif ($entry['image'] !== $currentImage) {
                $refreshed[] = $pageNumber;
                $validCache[$pageNumber] = [
                    'image' => $currentImage,
                    'source' => 'reader-cache-refreshed-master-current-source',
                ];
                $operations[] = [
                    'op' => 'refresh_reader_cache_from_master_journal_current_source',
                    'page_number' => $pageNumber,
                    'source_id' => $recoveredSourceId,
                    'epoch' => $recoveredEpoch,
                ];
            } else {
                $retained[] = $pageNumber;
                $validCache[$pageNumber] = [
                    'image' => $entry['image'],
                    'source' => 'reader-cache-retained-master-current-source',
                ];
                $operations[] = [
                    'op' => 'retain_reader_cache_after_master_journal_source_check',
                    'page_number' => $pageNumber,
                    'source_id' => $recoveredSourceId,
                    'epoch' => $recoveredEpoch,
                ];
            }

            $readerRows[] = [
                'label' => $entry['label'],
                'page_number' => $pageNumber,
                'pinned' => $entry['pinned'],
                'dirty' => $entry['dirty'],
                'source_id_before' => $entry['source_id'],
                'epoch_before' => $entry['epoch'],
                'master_journal_digest_before' => $entry['master_journal_digest'],
                'master_journal_digest_current' => $currentDigest,
                'master_journal_digest_matches_current' => $entry['master_journal_digest'] === $currentDigest,
                'cache_prefix' => self::label($entry['image']),
                'current_prefix' => self::label($currentImage),
                'image_matches_current_source' => $entry['image'] === $currentImage,
                'admitted' => $reason === null,
                'reason' => $reason ?? ($entry['image'] === $currentImage ? 'reader_cache_matches_current_master_source' : 'reader_cache_refreshed_from_current_master_source'),
            ];
        }

        $reads = [];
        foreach ($nextReadPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next161 read page {$pageNumber} is outside the database image");
            }
            $cache = $validCache[$pageNumber] ?? null;
            $image = is_array($cache) ? $cache['image'] : $database[$pageNumber]['image'];
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => is_array($cache),
                'source' => is_array($cache) ? $cache['source'] : $database[$pageNumber]['source'],
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
                'prefix' => self::label($image),
            ];
            $operations[] = [
                'op' => is_array($cache) ? 'next_read_uses_rebased_reader_cache' : 'next_read_uses_recovered_master_current_source',
                'page_number' => $pageNumber,
            ];
        }

        $writes = [];
        foreach ($nextWritePages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next161 write page {$pageNumber} is outside the database image");
            }
            $beforeImage = $database[$pageNumber]['image'];
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'next-write-after-master-journal-reader-cache',
            ];
            $writes[] = [
                'page_number' => $pageNumber,
                'before_prefix' => self::label($beforeImage),
                'after_prefix' => self::label($image),
                'journal_before_from_current_master_source' => true,
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
            ];
            $operations[] = [
                'op' => 'capture_next_write_before_image_after_reader_cache_rebase',
                'page_number' => $pageNumber,
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
            ];
        }

        ksort($database, SORT_NUMERIC);

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next161',
            'reason' => 'current_master_journal_membership_rebases_reader_cache_before_next_source',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'cached_members' => $cachedMembers,
            'current_members' => $currentMembers,
            'cached_membership_stale' => $cachedMembershipStale,
            'cached_master_journal_digest' => $cachedDigest,
            'current_master_journal_digest' => $currentDigest,
            'input_source' => ['id' => $currentSourceId, 'epoch' => $currentSourceEpoch],
            'recovered_source' => ['id' => $recoveredSourceId, 'epoch' => $recoveredEpoch],
            'master_recovered_page_numbers' => array_keys($masterRecoveredPages),
            'reader_rows' => $readerRows,
            'retained_cache_page_numbers' => $retained,
            'refreshed_cache_page_numbers' => $refreshed,
            'invalidated_cache_page_numbers' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [],
            'next_reads' => $reads,
            'next_writes' => $writes,
            'operations' => $operations,
            'final_prefixes' => self::prefixesFromSource($database),
            'final_sources' => self::sources($database),
            'final_database_bytes' => self::sourceBytes($database, $pageSize),
            'source_digest' => hash('sha256', $recoveredSourceId . '|' . implode(',', $retained) . '|' . implode(',', $refreshed) . '|' . implode(',', $invalidated)),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next161',
                'sqlite-pager-reader-cache-master-journal-digest-fence-next161',
                'sqlite-pager-master-journal-cache-recovery-current-source-next122',
                'sqlite-pager-master-journal-hot-cache-current-source-next136',
            ],
        ];
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $bytes, int $pageSize): array
    {
        $pages = str_split($bytes, $pageSize);
        $map = [];
        foreach ($pages as $index => $image) {
            if (strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 database bytes must split into complete pages');
            }
            $map[$index + 1] = [
                'image' => $image,
                'source' => 'database-before-master-journal-reader-cache',
            ];
        }

        return $map;
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private static function normalizeImages(array $images, int $pageSize, string $label, bool $allowEmpty = false): array
    {
        if ($images === [] && !$allowEmpty) {
            throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next161 requires {$label} pages");
        }
        $normalized = [];
        foreach ($images as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next161 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next161 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string,master_journal_digest?:string}> $cachePages
     * @return array<int,array{image:string,source_id:string,epoch:int,pinned:bool,dirty:bool,label:string,master_journal_digest:string}>
     */
    private static function normalizeReaderCache(array $cachePages, int $pageSize): array
    {
        $normalized = [];
        foreach ($cachePages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 reader cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next161 reader cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = (string) ($entry['source_id'] ?? '');
            if ($sourceId === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 reader cache source ids must be non-empty strings');
            }
            $epoch = $entry['epoch'] ?? 0;
            if (!is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 reader cache epochs must be positive integers');
            }
            $digest = (string) ($entry['master_journal_digest'] ?? '');
            if ($digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next161 reader cache master-journal digests must be non-empty strings');
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'label' => (string) ($entry['label'] ?? ('reader-cache-page-' . $pageNumber)),
                'master_journal_digest' => $digest,
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages, string $label): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next161 {$label} page numbers must be one-based integers");
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function members(?string $bytes): array
    {
        if ($bytes === null || trim($bytes) === '') {
            return [];
        }
        $members = [];
        foreach (preg_split('/\r?\n/', trim($bytes)) ?: [] as $member) {
            $member = trim($member);
            if ($member !== '' && !in_array($member, $members, true)) {
                $members[] = $member;
            }
        }

        return $members;
    }

    /**
     * @param list<string> $members
     */
    private static function memberDigest(array $members): string
    {
        return hash('sha256', implode("\n", $members));
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @return array<int,string>
     */
    private static function prefixesFromSource(array $source): array
    {
        $prefixes = [];
        foreach ($source as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::label($entry['image']);
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @return array<int,string>
     */
    private static function sources(array $source): array
    {
        $sources = [];
        foreach ($source as $pageNumber => $entry) {
            $sources[$pageNumber] = $entry['source'];
        }

        return $sources;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     */
    private static function sourceBytes(array $source, int $pageSize): string
    {
        ksort($source, SORT_NUMERIC);
        $bytes = '';
        foreach ($source as $entry) {
            if (strlen($entry['image']) !== $pageSize) {
                throw new \RuntimeException('SQLite pager master-journal reader-cache next161 final page image is not page-size bytes');
            }
            $bytes .= $entry['image'];
        }

        return $bytes;
    }
}
