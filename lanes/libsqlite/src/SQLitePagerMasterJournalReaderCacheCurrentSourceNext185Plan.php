<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext185Plan
{
    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_digest?:string,journal_digest?:string,initial_database_page_count?:int,journal_page_numbers?:list<int>,dirty?:bool,pinned?:bool}> $readerCache
     * @param list<int> $readPages
     * @param array<int,string> $writePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
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
            throw new \InvalidArgumentException('SQLite pager reader-cache next185 requires non-empty paths and source id');
        }
        if (trim($currentMasterJournalBytes) === '' || $rollbackJournalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager reader-cache next185 requires master-journal and rollback-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next185 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next185 database bytes must be page-size aligned');
        }
        if ($readerCache === [] || ($readPages === [] && $writePages === [])) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next185 requires cache entries and next work');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next185 epoch must be positive');
        }

        $members = self::members($currentMasterJournalBytes);
        $journalPath = $databasePath . '-journal';
        if (!in_array($journalPath, $members, true)) {
            throw new \RuntimeException('SQLite pager reader-cache next185 current master journal does not reference the database journal');
        }

        $journal = SQLiteRollbackJournal::parse($rollbackJournalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next185 rollback-journal page size must match pager page size');
        }
        if ($journal->header->pageCount === SQLiteRollbackJournalHeader::UNKNOWN_PAGE_COUNT) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next185 requires a finite rollback-journal page count');
        }

        $database = self::pages($databaseBytes, $pageSize);
        $originalPageCount = $journal->header->initialDatabasePageCount;
        if ($originalPageCount < 1 || $originalPageCount > count($database)) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next185 initial database page count must truncate within the current image');
        }

        $readerCache = self::cache($readerCache, $pageSize);
        self::pageList($readPages, 'read');
        $writePages = self::images($writePages, $pageSize, 'write');

        $masterDigest = self::digestMembers($members);
        $journalPageNumbers = [];
        $ignoredJournalPageNumbers = [];
        foreach ($journal->pages as $page) {
            $journalPageNumbers[] = $page->pageNumber;
            if ($page->pageNumber > $originalPageCount) {
                $ignoredJournalPageNumbers[] = $page->pageNumber;
                continue;
            }
            if (!isset($database[$page->pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next185 journal page {$page->pageNumber} is outside the database image");
            }
            $database[$page->pageNumber] = [
                'image' => $page->pageImage,
                'source' => 'finite-master-journal-recovered-current-source-next185',
            ];
        }

        $truncatedPageNumbers = [];
        foreach (array_keys($database) as $pageNumber) {
            if ($pageNumber > $originalPageCount) {
                $truncatedPageNumbers[] = $pageNumber;
                unset($database[$pageNumber]);
            }
        }

        $journalDigest = self::journalDigest($journalPath, $rollbackJournalBytes, $journalPageNumbers, $journal->header);
        $nextSource = [
            'id' => 'master-journal-finite-truncate-source:' . substr(hash('sha256', $masterDigest . '|' . $journalDigest . '|' . $originalPageCount), 0, 32),
            'epoch' => $currentEpoch + 1,
        ];
        $operations = [[
            'op' => 'read_current_master_journal_for_finite_truncate_reader_cache_next185',
            'path' => $masterJournalPath,
            'members' => $members,
        ], [
            'op' => 'apply_finite_rollback_journal_before_reader_cache_next185',
            'path' => $journalPath,
            'initial_database_page_count' => $originalPageCount,
            'declared_record_count' => $journal->header->pageCount,
        ]];
        foreach ($truncatedPageNumbers as $pageNumber) {
            $operations[] = ['op' => 'truncate_tail_page_before_reader_cache_next185', 'page_number' => $pageNumber];
        }
        foreach ($ignoredJournalPageNumbers as $pageNumber) {
            $operations[] = ['op' => 'ignore_journal_page_beyond_finite_database_size_next185', 'page_number' => $pageNumber];
        }

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $cacheRows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            $reason = null;
            $currentImage = $database[$pageNumber]['image'] ?? null;
            if ($pageNumber > $originalPageCount) {
                $reason = 'reader_cache_page_truncated_by_finite_master_journal_recovery';
            } elseif ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_finite_master_journal_recovery';
            } elseif (!hash_equals($entry['master_digest'], $masterDigest)) {
                $reason = 'reader_cache_master_digest_mismatch_after_finite_master_read';
            } elseif (!hash_equals($entry['journal_digest'], $journalDigest)) {
                $reason = 'reader_cache_journal_digest_mismatch_after_finite_master_read';
            } elseif ($entry['initial_database_page_count'] !== $originalPageCount) {
                $reason = 'reader_cache_initial_page_count_mismatch_after_finite_recovery';
            } elseif ($entry['journal_page_numbers'] !== $journalPageNumbers) {
                $reason = 'reader_cache_journal_page_set_mismatch_after_finite_recovery';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch_after_finite_recovery';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_mismatch_after_finite_recovery';
            } elseif ($entry['pinned'] && $currentImage !== null && !hash_equals($entry['image'], $currentImage)) {
                $reason = 'pinned_reader_cache_image_predates_finite_recovery';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = $reason;
                $operations[] = ['op' => 'invalidate_reader_cache_finite_truncate_next185', 'page_number' => $pageNumber, 'reason' => $reason];
            } elseif ($currentImage !== null && !hash_equals($entry['image'], $currentImage)) {
                $refreshed[$pageNumber] = $currentImage;
                $operations[] = ['op' => 'refresh_reader_cache_finite_truncate_next185', 'page_number' => $pageNumber];
            } else {
                $retained[$pageNumber] = $entry['image'];
                $operations[] = ['op' => 'retain_reader_cache_finite_truncate_next185', 'page_number' => $pageNumber];
            }

            $cacheRows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'admitted' => $reason === null,
                'reason' => $reason ?? (hash_equals($entry['image'], (string) $currentImage) ? 'reader_cache_matches_finite_recovered_current_source' : 'reader_cache_refreshed_from_finite_recovered_current_source'),
                'truncated' => $pageNumber > $originalPageCount,
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'initial_database_page_count_before' => $entry['initial_database_page_count'],
                'initial_database_page_count_current' => $originalPageCount,
                'journal_page_numbers_before' => $entry['journal_page_numbers'],
                'journal_page_numbers_current' => $journalPageNumbers,
                'master_digest_matches' => hash_equals($entry['master_digest'], $masterDigest),
                'journal_digest_matches' => hash_equals($entry['journal_digest'], $journalDigest),
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => $currentImage === null ? null : self::prefix($currentImage),
            ];
        }

        $reads = [];
        $blockedReads = [];
        foreach ($readPages as $pageNumber) {
            if ($pageNumber > $originalPageCount || !isset($database[$pageNumber])) {
                $blockedReads[] = $pageNumber;
                $reads[] = [
                    'page_number' => $pageNumber,
                    'cache_hit' => false,
                    'blocked' => true,
                    'reason' => 'next_read_page_truncated_by_finite_master_journal_recovery',
                    'source_id' => $nextSource['id'],
                    'epoch' => $nextSource['epoch'],
                    'prefix' => null,
                ];
                $operations[] = ['op' => 'block_next_read_of_truncated_page_next185', 'page_number' => $pageNumber];
                continue;
            }
            $cacheImage = $retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null;
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheImage !== null,
                'blocked' => false,
                'reason' => $cacheImage !== null ? 'next_read_uses_finite_recovery_reader_cache' : 'next_read_reopens_finite_recovered_current_source',
                'source' => $cacheImage !== null ? 'reader-cache-finite-current-source-next185' : $database[$pageNumber]['source'],
                'source_id' => $nextSource['id'],
                'epoch' => $nextSource['epoch'],
                'prefix' => self::prefix($cacheImage ?? $database[$pageNumber]['image']),
            ];
            $operations[] = ['op' => $cacheImage !== null ? 'next_read_uses_finite_reader_cache_next185' : 'next_read_reopens_finite_current_source_next185', 'page_number' => $pageNumber];
        }

        $writes = [];
        $blockedWrites = [];
        foreach ($writePages as $pageNumber => $image) {
            if ($pageNumber > $originalPageCount || !isset($database[$pageNumber])) {
                $blockedWrites[] = $pageNumber;
                $writes[] = [
                    'page_number' => $pageNumber,
                    'blocked' => true,
                    'reason' => 'next_write_page_truncated_by_finite_master_journal_recovery',
                    'before_prefix' => null,
                    'after_prefix' => self::prefix($image),
                ];
                $operations[] = ['op' => 'block_next_write_of_truncated_page_next185', 'page_number' => $pageNumber];
                continue;
            }
            $before = $database[$pageNumber]['image'];
            $database[$pageNumber] = ['image' => $image, 'source' => 'next-write-after-finite-master-journal-reader-cache-next185'];
            $writes[] = [
                'page_number' => $pageNumber,
                'blocked' => false,
                'reason' => 'next_write_journals_from_finite_recovered_current_source',
                'before_prefix' => self::prefix($before),
                'after_prefix' => self::prefix($image),
            ];
            $operations[] = ['op' => 'capture_next_write_after_finite_reader_cache_next185', 'page_number' => $pageNumber];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next185',
            'reason' => 'finite rollback-journal original database size truncates reader-cache current source before next reads',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'journal_path' => $journalPath,
            'page_size' => $pageSize,
            'master_members' => $members,
            'master_digest' => $masterDigest,
            'journal_digest' => $journalDigest,
            'journal_record_count' => $journal->header->pageCount,
            'initial_database_page_count' => $originalPageCount,
            'journal_page_numbers' => $journalPageNumbers,
            'ignored_journal_page_numbers' => $ignoredJournalPageNumbers,
            'truncated_page_numbers' => $truncatedPageNumbers,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'next_source' => $nextSource,
            'cache_rows' => $cacheRows,
            'retained_page_numbers' => array_keys($retained),
            'refreshed_page_numbers' => array_keys($refreshed),
            'invalidated_page_numbers' => array_keys($invalidated),
            'invalidated_reasons' => $invalidated,
            'blocked_read_page_numbers' => $blockedReads,
            'blocked_write_page_numbers' => $blockedWrites,
            'next_reads' => $reads,
            'next_writes' => $writes,
            'operations' => $operations,
            'final_sources' => array_map(static fn (array $page): string => $page['source'], $database),
            'final_prefixes' => array_map(static fn (array $page): string => self::prefix($page['image']), $database),
            'final_database_bytes' => self::bytes($database, $pageSize),
            'source_digest' => hash('sha256', $nextSource['id'] . '|' . implode(',', $truncatedPageNumbers) . '|' . implode(',', $invalidated)),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next185',
                'sqlite-rollback-journal-finite-original-page-count-truncation',
                'sqlite-pager-master-journal-reader-cache-current-source-next182',
            ],
            'non_overlap' => 'Adds finite rollback-journal original database-size truncation before reader-cache reuse; avoids next182 unknown-page-count checksum EOF scanning, next180 page-one format-ticket fences, and accepted rollback commit/apply paths.',
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

    /** @param list<string> $members */
    private static function digestMembers(array $members): string
    {
        return hash('sha256', implode("\n", $members));
    }

    /**
     * @param list<int> $pageNumbers
     */
    private static function journalDigest(string $journalPath, string $journalBytes, array $pageNumbers, SQLiteRollbackJournalHeader $header): string
    {
        return hash('sha256', implode('|', [
            $journalPath,
            strlen($journalBytes),
            $header->pageCount,
            $header->checksumNonce,
            $header->initialDatabasePageCount,
            $header->sectorSize,
            $header->pageSize,
            implode(',', $pageNumbers),
            hash('sha256', $journalBytes),
        ]));
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function pages(string $bytes, int $pageSize): array
    {
        $pages = [];
        foreach (str_split($bytes, $pageSize) as $index => $image) {
            $pages[$index + 1] = ['image' => $image, 'source' => 'database-before-finite-reader-cache-next185'];
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
                throw new \InvalidArgumentException("SQLite pager reader-cache next185 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next185 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_digest?:string,journal_digest?:string,initial_database_page_count?:int,journal_page_numbers?:list<int>,dirty?:bool,pinned?:bool}> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,master_digest:string,journal_digest:string,initial_database_page_count:int,journal_page_numbers:list<int>,dirty:bool,pinned:bool}>
     */
    private static function cache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager reader-cache next185 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next185 cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = (string) ($entry['source_id'] ?? '');
            $masterDigest = (string) ($entry['master_digest'] ?? '');
            $journalDigest = (string) ($entry['journal_digest'] ?? '');
            $readerId = (string) ($entry['reader_id'] ?? ('reader-' . $pageNumber));
            if ($sourceId === '' || $masterDigest === '' || $journalDigest === '' || $readerId === '') {
                throw new \InvalidArgumentException('SQLite pager reader-cache next185 cache entries require source, reader, and digest fields');
            }
            $epoch = $entry['epoch'] ?? 0;
            $initialPageCount = $entry['initial_database_page_count'] ?? 0;
            if (!is_int($epoch) || $epoch < 1 || !is_int($initialPageCount) || $initialPageCount < 1) {
                throw new \InvalidArgumentException('SQLite pager reader-cache next185 cache entries require positive epoch and initial page count');
            }
            $pageNumbers = $entry['journal_page_numbers'] ?? null;
            if (!is_array($pageNumbers)) {
                throw new \InvalidArgumentException('SQLite pager reader-cache next185 cache journal page numbers must be a list');
            }
            $pageNumbers = array_values($pageNumbers);
            foreach ($pageNumbers as $number) {
                if (!is_int($number) || $number < 1) {
                    throw new \InvalidArgumentException('SQLite pager reader-cache next185 cache journal page numbers must be one-based integers');
                }
            }

            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'reader_id' => $readerId,
                'master_digest' => $masterDigest,
                'journal_digest' => $journalDigest,
                'initial_database_page_count' => $initialPageCount,
                'journal_page_numbers' => $pageNumbers,
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
                throw new \InvalidArgumentException("SQLite pager reader-cache next185 {$label} page numbers must be one-based integers");
            }
        }
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }

    /**
     * @param array<int,array{image:string,source:string}> $pages
     */
    private static function bytes(array $pages, int $pageSize): string
    {
        ksort($pages, SORT_NUMERIC);
        $bytes = '';
        foreach ($pages as $page) {
            if (strlen($page['image']) !== $pageSize) {
                throw new \RuntimeException('SQLite pager reader-cache next185 final image is not page-size bytes');
            }
            $bytes .= $page['image'];
        }

        return $bytes;
    }
}
