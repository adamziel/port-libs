<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext175Plan
{
    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_digest?:string,journal_digest?:string,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<int> $readPages
     * @param array<int,string> $writePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $masterJournalBytes,
        string $rollbackJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $readerCache,
        array $readPages,
        array $writePages,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager reader-cache next175 requires non-empty paths and source id');
        }
        if (trim($masterJournalBytes) === '' || $rollbackJournalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager reader-cache next175 requires master and rollback journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next175 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next175 database bytes must be page-size aligned');
        }
        if ($readerCache === [] || ($readPages === [] && $writePages === [])) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next175 requires cache entries and next work');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next175 epoch must be positive');
        }

        $members = self::members($masterJournalBytes);
        $journalPath = $databasePath . '-journal';
        if (!in_array($journalPath, $members, true)) {
            throw new \RuntimeException('SQLite pager reader-cache next175 master journal does not reference the database journal');
        }

        $journal = SQLiteRollbackJournal::parse($rollbackJournalBytes, false);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next175 rollback journal page size does not match');
        }

        $database = self::pages($databaseBytes, $pageSize);
        $readerCache = self::cache($readerCache, $pageSize);
        self::pageList($readPages, 'read');
        $writePages = self::images($writePages, $pageSize, 'write');

        $masterDigest = hash('sha256', implode("\n", $members));
        $journalDigest = hash('sha256', $journalPath . '|' . strlen($rollbackJournalBytes) . '|' . hash('sha256', $rollbackJournalBytes));
        $journalRows = [];
        $validJournalPages = [];
        $corruptPages = [];
        foreach ($journal->pages as $page) {
            if (!isset($database[$page->pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next175 journal page {$page->pageNumber} is outside the database image");
            }
            $expected = SQLiteRollbackJournal::pageChecksum($page->pageImage, $journal->header->checksumNonce);
            $valid = $expected === $page->checksum;
            $journalRows[] = [
                'index' => $page->index,
                'page_number' => $page->pageNumber,
                'stored_checksum' => $page->checksum,
                'expected_checksum' => $expected,
                'checksum_valid' => $valid,
                'prefix' => self::prefix($page->pageImage),
            ];
            if ($valid) {
                $validJournalPages[$page->pageNumber] = $page->pageImage;
                $database[$page->pageNumber] = [
                    'image' => $page->pageImage,
                    'source' => 'rollback-journal-checksum-current-source-next175',
                ];
            } else {
                $corruptPages[$page->pageNumber] = $page->pageNumber;
            }
        }

        $operations = [[
            'op' => 'read_master_journal_for_reader_cache_checksum_next175',
            'path' => $masterJournalPath,
            'members' => $members,
        ], [
            'op' => 'verify_current_rollback_journal_page_checksums_next175',
            'journal_path' => $journalPath,
            'valid_pages' => array_keys($validJournalPages),
            'corrupt_pages' => array_values($corruptPages),
        ]];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $cacheRows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next175 cache page {$pageNumber} is outside the database image");
            }
            $reason = null;
            $currentImage = $database[$pageNumber]['image'];
            if (isset($corruptPages[$pageNumber])) {
                $reason = 'reader_cache_page_quarantined_by_rollback_journal_checksum';
            } elseif ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_checksum_verified_journal';
            } elseif ($entry['pinned'] && !hash_equals($entry['image'], $currentImage)) {
                $reason = 'pinned_reader_cache_image_mismatch_after_checksum_recovery';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch_after_checksum_recovery';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_mismatch_after_checksum_recovery';
            } elseif ($entry['master_digest'] !== $masterDigest) {
                $reason = 'reader_cache_master_digest_mismatch_after_checksum_recovery';
            } elseif ($entry['journal_digest'] !== $journalDigest) {
                $reason = 'reader_cache_journal_digest_mismatch_after_checksum_recovery';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = $reason;
                $operations[] = ['op' => 'invalidate_reader_cache_checksum_next175', 'page_number' => $pageNumber, 'reason' => $reason];
            } elseif (!hash_equals($entry['image'], $currentImage)) {
                $refreshed[$pageNumber] = $currentImage;
                $operations[] = ['op' => 'refresh_reader_cache_checksum_current_source_next175', 'page_number' => $pageNumber];
            } else {
                $retained[$pageNumber] = $entry['image'];
                $operations[] = ['op' => 'retain_reader_cache_checksum_current_source_next175', 'page_number' => $pageNumber];
            }

            $cacheRows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'admitted' => $reason === null,
                'reason' => $reason ?? (hash_equals($entry['image'], $currentImage) ? 'reader_cache_matches_checksum_verified_current_source' : 'reader_cache_refreshed_from_checksum_verified_current_source'),
                'checksum_valid' => !isset($corruptPages[$pageNumber]),
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'master_digest_matches' => $entry['master_digest'] === $masterDigest,
                'journal_digest_matches' => $entry['journal_digest'] === $journalDigest,
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next175 read page {$pageNumber} is outside the database image");
            }
            $cacheImage = $retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null;
            $quarantined = isset($corruptPages[$pageNumber]);
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheImage !== null && !$quarantined,
                'quarantined' => $quarantined,
                'source' => $quarantined ? 'rollback-journal-checksum-quarantine-next175' : ($cacheImage !== null ? 'reader-cache-checksum-current-source-next175' : $database[$pageNumber]['source']),
                'prefix' => self::prefix($cacheImage ?? $database[$pageNumber]['image']),
            ];
            $operations[] = ['op' => $quarantined ? 'next_read_blocks_on_rollback_journal_checksum_next175' : ($cacheImage !== null ? 'next_read_uses_checksum_verified_reader_cache_next175' : 'next_read_uses_checksum_verified_current_source_next175'), 'page_number' => $pageNumber];
        }

        $writes = [];
        foreach ($writePages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next175 write page {$pageNumber} is outside the database image");
            }
            $allowed = !isset($corruptPages[$pageNumber]);
            $writes[] = [
                'page_number' => $pageNumber,
                'allowed' => $allowed,
                'reason' => $allowed ? 'before_image_from_checksum_verified_current_source' : 'write_blocked_until_checksum_verified_before_image',
                'before_prefix' => self::prefix($database[$pageNumber]['image']),
                'after_prefix' => self::prefix($image),
            ];
            if ($allowed) {
                $database[$pageNumber] = ['image' => $image, 'source' => 'next-write-after-checksum-reader-cache-next175'];
            }
            $operations[] = ['op' => $allowed ? 'capture_next_write_after_checksum_reader_cache_next175' : 'block_next_write_after_checksum_reader_cache_next175', 'page_number' => $pageNumber];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next175',
            'reason' => 'rollback journal page checksums fence reader-cache reuse after master-journal recovery',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'journal_path' => $journalPath,
            'page_size' => $pageSize,
            'master_members' => $members,
            'master_digest' => $masterDigest,
            'journal_digest' => $journalDigest,
            'journal_header' => $journal->header->toArray(),
            'journal_rows' => $journalRows,
            'valid_journal_page_numbers' => array_keys($validJournalPages),
            'corrupt_journal_page_numbers' => array_values($corruptPages),
            'cache_rows' => $cacheRows,
            'retained_page_numbers' => array_keys($retained),
            'refreshed_page_numbers' => array_keys($refreshed),
            'invalidated_page_numbers' => array_keys($invalidated),
            'invalidated_reasons' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [] || $corruptPages !== [],
            'next_reads' => $reads,
            'next_writes' => $writes,
            'operations' => $operations,
            'final_sources' => array_map(static fn (array $page): string => $page['source'], $database),
            'final_prefixes' => array_map(static fn (array $page): string => self::prefix($page['image']), $database),
            'source_digest' => hash('sha256', $masterDigest . '|' . $journalDigest . '|' . implode(',', array_keys($validJournalPages)) . '|' . implode(',', $corruptPages)),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next175',
                'sqlite-rollback-journal-page-checksum-current-source-fence',
                'sqlite-pager-master-journal-reader-cache-current-source-next170',
            ],
            'non_overlap' => 'Adds checksum admission for rollback-journal pages before reader-cache reuse; avoids next170 source digest and next173 membership fences.',
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
            $pages[$page] = ['image' => substr($bytes, $offset, $pageSize), 'source' => 'database-before-checksum-recovery-next175'];
        }
        return $pages;
    }

    /** @param array<int,array<string,mixed>> $cache @return array<int,array<string,mixed>> */
    private static function cache(array $cache, int $pageSize): array
    {
        ksort($cache, SORT_NUMERIC);
        foreach ($cache as $page => $entry) {
            if (!is_int($page) || $page < 1 || !isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager reader-cache next175 cache entries must use one-based page numbers and full page images');
            }
            $cache[$page] = [
                'image' => $entry['image'],
                'source_id' => (string)($entry['source_id'] ?? ''),
                'epoch' => (int)($entry['epoch'] ?? 0),
                'reader_id' => (string)($entry['reader_id'] ?? ('reader-' . $page)),
                'master_digest' => (string)($entry['master_digest'] ?? ''),
                'journal_digest' => (string)($entry['journal_digest'] ?? ''),
                'dirty' => (bool)($entry['dirty'] ?? false),
                'pinned' => (bool)($entry['pinned'] ?? false),
            ];
            if ($cache[$page]['source_id'] === '' || $cache[$page]['epoch'] < 1 || $cache[$page]['master_digest'] === '' || $cache[$page]['journal_digest'] === '') {
                throw new \InvalidArgumentException('SQLite pager reader-cache next175 cache entries require source, epoch, master digest, and journal digest');
            }
        }
        return $cache;
    }

    /** @param list<int> $pages */
    private static function pageList(array $pages, string $label): void
    {
        foreach ($pages as $page) {
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next175 {$label} pages must be one-based");
            }
        }
    }

    /** @param array<int,string> $images @return array<int,string> */
    private static function images(array $images, int $pageSize, string $label): array
    {
        ksort($images, SORT_NUMERIC);
        foreach ($images as $page => $image) {
            if (!is_int($page) || $page < 1 || !is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next175 {$label} images must use one-based page numbers and full page images");
            }
        }
        return $images;
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }
}
