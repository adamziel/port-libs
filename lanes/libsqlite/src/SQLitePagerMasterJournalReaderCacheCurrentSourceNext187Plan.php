<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext187Plan
{
    /**
     * @param array<int,array{image:string,label?:string,source_id?:string,epoch?:int,master_member_ordinals?:array<string,int>,master_complete_read_digest?:string,master_byte_length?:int,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<int> $readPages
     * @param array<int,string> $currentPages
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
        array $currentPages,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 requires non-empty paths and source id');
        }
        if (trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 requires master-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 database bytes must be page-size aligned');
        }
        if ($readerCache === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 requires reader cache and read pages');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 current epoch must be positive');
        }

        $members = self::members($currentMasterJournalBytes);
        $mainJournal = $databasePath . '-journal';
        if (!in_array($mainJournal, $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next187 current master journal does not reference the database journal');
        }
        $ordinals = self::ordinals($members);
        $completeReadDigest = self::completeReadDigest($masterJournalPath, $currentMasterJournalBytes, $members);
        $database = self::sourceMap($databaseBytes, $pageSize);
        $currentPages = self::normalizeImages($currentPages, $pageSize, true);
        foreach ($currentPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next187 current page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-complete-read-current-source-next187',
            ];
        }

        $cache = self::normalizeReaderCache($readerCache, $pageSize);
        self::assertPageList($readPages, 'read');

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        $operations = [[
            'op' => 'read_complete_master_journal_membership_next187',
            'path' => $masterJournalPath,
            'byte_length' => strlen($currentMasterJournalBytes),
            'members' => $members,
            'member_ordinals' => $ordinals,
            'complete_read_digest' => $completeReadDigest,
        ]];

        foreach ($cache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next187 cache page {$pageNumber} is outside the database image");
            }

            $reason = null;
            $missingMembers = array_values(array_diff($members, array_keys($entry['master_member_ordinals'])));
            $ordinalMismatch = self::ordinalMismatch($entry['master_member_ordinals'], $ordinals);
            $currentImage = $database[$pageNumber]['image'];
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_before_complete_master_membership';
            } elseif ($entry['master_complete_read_digest'] !== $completeReadDigest) {
                $reason = 'reader_cache_master_complete_read_digest_changed';
            } elseif ($entry['master_byte_length'] !== strlen($currentMasterJournalBytes)) {
                $reason = 'reader_cache_master_byte_length_changed';
            } elseif ($missingMembers !== []) {
                $reason = 'reader_cache_prefix_read_missing_master_members';
            } elseif ($ordinalMismatch !== []) {
                $reason = 'reader_cache_master_member_ordinal_changed';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_predates_complete_master_read';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_predates_complete_master_read';
            } elseif ($entry['pinned'] && $entry['image'] !== $currentImage) {
                $reason = 'pinned_reader_cache_image_predates_complete_master_read';
            }

            if ($reason !== null) {
                $invalidated[] = $pageNumber;
                $operations[] = [
                    'op' => 'invalidate_reader_cache_after_complete_master_read_next187',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                    'missing_members' => $missingMembers,
                    'ordinal_mismatch' => $ordinalMismatch,
                ];
            } elseif ($entry['image'] !== $currentImage) {
                $refreshed[] = $pageNumber;
                $operations[] = [
                    'op' => 'refresh_reader_cache_after_complete_master_read_next187',
                    'page_number' => $pageNumber,
                ];
            } else {
                $retained[] = $pageNumber;
                $operations[] = [
                    'op' => 'retain_reader_cache_after_complete_master_read_next187',
                    'page_number' => $pageNumber,
                ];
            }

            $rows[] = [
                'page_number' => $pageNumber,
                'label' => $entry['label'],
                'admitted' => $reason === null,
                'reason' => $reason ?? ($entry['image'] === $currentImage ? 'reader_cache_matches_complete_master_read' : 'reader_cache_refreshed_from_complete_master_read'),
                'missing_members' => $missingMembers,
                'ordinal_mismatch' => $ordinalMismatch,
                'master_member_ordinals_before' => $entry['master_member_ordinals'],
                'master_member_ordinals_current' => $ordinals,
                'complete_read_digest_matches' => $entry['master_complete_read_digest'] === $completeReadDigest,
                'master_byte_length_before' => $entry['master_byte_length'],
                'master_byte_length_current' => strlen($currentMasterJournalBytes),
                'image_matches_current_source' => $entry['image'] === $currentImage,
                'cache_prefix' => self::label($entry['image']),
                'current_prefix' => self::label($currentImage),
            ];
        }

        $nextReads = [];
        $nextSourceId = 'master-reader-cache-complete-read:' . substr(hash('sha256', $completeReadDigest), 0, 28);
        foreach ($readPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next187 read page {$pageNumber} is outside the database image");
            }
            $cacheHit = in_array($pageNumber, $retained, true) || in_array($pageNumber, $refreshed, true);
            $nextReads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheHit,
                'source_id' => $nextSourceId,
                'epoch' => $currentEpoch + 1,
                'complete_read_digest' => $completeReadDigest,
                'prefix' => self::label($database[$pageNumber]['image']),
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next187',
            'reason' => 'complete_master_journal_read_membership_fences_prefix_reader_cache',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'current_member_ordinals' => $ordinals,
            'current_master_byte_length' => strlen($currentMasterJournalBytes),
            'current_complete_read_digest' => $completeReadDigest,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'next_source' => ['id' => $nextSourceId, 'epoch' => $currentEpoch + 1],
            'reader_rows' => $rows,
            'retained_cache_page_numbers' => $retained,
            'refreshed_cache_page_numbers' => $refreshed,
            'invalidated_cache_page_numbers' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [],
            'next_reads' => $nextReads,
            'operations' => $operations,
            'final_prefixes' => self::prefixes($database),
            'source_digest' => hash('sha256', $completeReadDigest . '|' . implode(',', $retained) . '|' . implode(',', $refreshed) . '|' . implode(',', $invalidated)),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next187',
                'sqlite-pager-master-journal-complete-read-membership-fence',
            ],
            'non_overlap' => 'Adds complete master-journal byte-span membership ordinal fencing for prefix-read reader cache entries; avoids next184 file read-token/stat fencing and accepted next170 rollback-journal source identity fences.',
        ];
    }

    /** @return list<string> */
    private static function members(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/\r?\n/', trim($bytes)) ?: [] as $member) {
            $member = trim($member);
            if ($member !== '' && !in_array($member, $members, true)) {
                $members[] = $member;
            }
        }

        return $members;
    }

    /** @param list<string> $members @return array<string,int> */
    private static function ordinals(array $members): array
    {
        $ordinals = [];
        foreach ($members as $index => $member) {
            $ordinals[$member] = $index + 1;
        }

        return $ordinals;
    }

    /** @param list<string> $members */
    private static function completeReadDigest(string $path, string $bytes, array $members): string
    {
        return hash('sha256', $path . '|' . strlen($bytes) . '|' . implode("\n", $members) . '|' . hash('sha256', $bytes));
    }

    /** @return array<int,array{image:string,source:string}> */
    private static function sourceMap(string $bytes, int $pageSize): array
    {
        $map = [];
        foreach (str_split($bytes, $pageSize) as $index => $image) {
            $map[$index + 1] = ['image' => $image, 'source' => 'database-before-complete-master-read-next187'];
        }

        return $map;
    }

    /** @param array<int,string> $images @return array<int,string> */
    private static function normalizeImages(array $images, int $pageSize, bool $allowEmpty): array
    {
        if ($images === [] && !$allowEmpty) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 requires page images');
        }
        $normalized = [];
        foreach ($images as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 page numbers must be one-based integers');
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next187 page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $cache
     * @return array<int,array{image:string,label:string,source_id:string,epoch:int,master_member_ordinals:array<string,int>,master_complete_read_digest:string,master_byte_length:int,dirty:bool,pinned:bool}>
     */
    private static function normalizeReaderCache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next187 cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = (string) ($entry['source_id'] ?? '');
            $digest = (string) ($entry['master_complete_read_digest'] ?? '');
            $epoch = $entry['epoch'] ?? 0;
            $byteLength = $entry['master_byte_length'] ?? null;
            if ($sourceId === '' || $digest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 cache entries require source id and complete read digest');
            }
            if (!is_int($epoch) || $epoch < 1 || !is_int($byteLength) || $byteLength < 0) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 cache epoch and master byte length must be valid integers');
            }
            $ordinals = $entry['master_member_ordinals'] ?? null;
            if (!is_array($ordinals) || $ordinals === []) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 cache member ordinals must be a non-empty map');
            }
            $normalizedOrdinals = [];
            foreach ($ordinals as $member => $ordinal) {
                if (!is_string($member) || trim($member) === '' || !is_int($ordinal) || $ordinal < 1) {
                    throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next187 cache member ordinals require non-empty members and positive ordinals');
                }
                $normalizedOrdinals[trim($member)] = $ordinal;
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'label' => (string) ($entry['label'] ?? ('reader-cache-page-' . $pageNumber)),
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'master_member_ordinals' => $normalizedOrdinals,
                'master_complete_read_digest' => $digest,
                'master_byte_length' => $byteLength,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /** @param array<string,int> $cache @param array<string,int> $current @return array<string,array{before:int,current:int}> */
    private static function ordinalMismatch(array $cache, array $current): array
    {
        $mismatch = [];
        foreach ($current as $member => $ordinal) {
            if (isset($cache[$member]) && $cache[$member] !== $ordinal) {
                $mismatch[$member] = ['before' => $cache[$member], 'current' => $ordinal];
            }
        }

        return $mismatch;
    }

    /** @param list<int> $pages */
    private static function assertPageList(array $pages, string $label): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next187 {$label} page numbers must be one-based integers");
            }
        }
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }

    /** @param array<int,array{image:string,source:string}> $source @return array<int,string> */
    private static function prefixes(array $source): array
    {
        $prefixes = [];
        foreach ($source as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::label($entry['image']);
        }

        return $prefixes;
    }
}
