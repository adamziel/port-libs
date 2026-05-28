<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext182Plan
{
    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_digest?:string,journal_digest?:string,checksum_nonce?:int,journal_record_count?:int,journal_page_numbers?:list<int>,dirty?:bool,pinned?:bool}> $readerCache
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
            throw new \InvalidArgumentException('SQLite pager reader-cache next182 requires non-empty paths and source id');
        }
        if (trim($currentMasterJournalBytes) === '' || $rollbackJournalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager reader-cache next182 requires master-journal and rollback-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next182 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next182 database bytes must be page-size aligned');
        }
        if ($readerCache === [] || ($readPages === [] && $writePages === [])) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next182 requires cache entries and next work');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next182 epoch must be positive');
        }

        $members = self::members($currentMasterJournalBytes);
        $journalPath = $databasePath . '-journal';
        if (!in_array($journalPath, $members, true)) {
            throw new \RuntimeException('SQLite pager reader-cache next182 current master journal does not reference the database journal');
        }

        $journal = SQLiteRollbackJournal::parse($rollbackJournalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite pager reader-cache next182 rollback-journal page size must match pager page size');
        }

        $database = self::pages($databaseBytes, $pageSize);
        $readerCache = self::cache($readerCache, $pageSize);
        self::pageList($readPages, 'read');
        $writePages = self::images($writePages, $pageSize, 'write');

        $masterDigest = self::digestMembers($members);
        $journalPageNumbers = [];
        foreach ($journal->pages as $page) {
            if (!isset($database[$page->pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next182 journal page {$page->pageNumber} is outside the database image");
            }
            $journalPageNumbers[] = $page->pageNumber;
            $database[$page->pageNumber] = [
                'image' => $page->pageImage,
                'source' => 'checksum-validated-rollback-journal-current-source-next182',
            ];
        }
        $journalDigest = self::journalDigest($journalPath, $rollbackJournalBytes, $journalPageNumbers, $journal->header);
        $recordCount = count($journal->pages);
        $unknownPageCount = $journal->header->pageCount === SQLiteRollbackJournalHeader::UNKNOWN_PAGE_COUNT;
        $nextSource = [
            'id' => 'master-journal-checksum-source:' . hash('sha256', $masterDigest . '|' . $journalDigest),
            'epoch' => $currentEpoch + 1,
        ];

        $operations = [[
            'op' => 'read_current_master_journal_for_checksum_reader_cache_next182',
            'path' => $masterJournalPath,
            'members' => $members,
        ], [
            'op' => 'parse_checksum_validated_rollback_journal_for_reader_cache_next182',
            'path' => $journalPath,
            'unknown_page_count' => $unknownPageCount,
            'record_count' => $recordCount,
            'checksum_nonce' => $journal->header->checksumNonce,
        ]];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $cacheRows = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next182 cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_cannot_cross_checksum_source';
            } elseif (!hash_equals($entry['master_digest'], $masterDigest)) {
                $reason = 'reader_cache_master_digest_mismatch_after_checksum_read';
            } elseif (!hash_equals($entry['journal_digest'], $journalDigest)) {
                $reason = 'reader_cache_journal_digest_mismatch_after_checksum_read';
            } elseif ($entry['checksum_nonce'] !== $journal->header->checksumNonce) {
                $reason = 'reader_cache_checksum_nonce_mismatch';
            } elseif ($entry['journal_record_count'] !== $recordCount) {
                $reason = 'reader_cache_journal_record_count_mismatch';
            } elseif ($entry['journal_page_numbers'] !== $journalPageNumbers) {
                $reason = 'reader_cache_journal_page_set_mismatch_after_checksum_read';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_mismatch_after_checksum_read';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_mismatch_after_checksum_read';
            } elseif ($entry['pinned'] && !hash_equals($entry['image'], $currentImage)) {
                $reason = 'pinned_reader_cache_image_predates_checksum_recovery';
            }

            if ($reason !== null) {
                $invalidated[$pageNumber] = $reason;
                $operations[] = ['op' => 'invalidate_reader_cache_checksum_source_next182', 'page_number' => $pageNumber, 'reason' => $reason];
            } elseif (!hash_equals($entry['image'], $currentImage)) {
                $refreshed[$pageNumber] = $currentImage;
                $operations[] = ['op' => 'refresh_reader_cache_checksum_source_next182', 'page_number' => $pageNumber];
            } else {
                $retained[$pageNumber] = $entry['image'];
                $operations[] = ['op' => 'retain_reader_cache_checksum_source_next182', 'page_number' => $pageNumber];
            }

            $cacheRows[] = [
                'page_number' => $pageNumber,
                'reader_id' => $entry['reader_id'],
                'admitted' => $reason === null,
                'reason' => $reason ?? (hash_equals($entry['image'], $currentImage) ? 'reader_cache_matches_checksum_validated_current_source' : 'reader_cache_refreshed_from_checksum_validated_current_source'),
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'checksum_nonce_before' => $entry['checksum_nonce'],
                'checksum_nonce_current' => $journal->header->checksumNonce,
                'journal_record_count_before' => $entry['journal_record_count'],
                'journal_record_count_current' => $recordCount,
                'journal_page_numbers_before' => $entry['journal_page_numbers'],
                'journal_page_numbers_current' => $journalPageNumbers,
                'master_digest_matches' => hash_equals($entry['master_digest'], $masterDigest),
                'journal_digest_matches' => hash_equals($entry['journal_digest'], $journalDigest),
                'image_matches_current_source' => hash_equals($entry['image'], $currentImage),
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next182 read page {$pageNumber} is outside the database image");
            }
            $cacheImage = $retained[$pageNumber] ?? $refreshed[$pageNumber] ?? null;
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheImage !== null,
                'source' => $cacheImage !== null ? 'reader-cache-checksum-current-source-next182' : $database[$pageNumber]['source'],
                'source_id' => $nextSource['id'],
                'epoch' => $nextSource['epoch'],
                'prefix' => self::prefix($cacheImage ?? $database[$pageNumber]['image']),
            ];
            $operations[] = ['op' => $cacheImage !== null ? 'next_read_uses_checksum_reader_cache_next182' : 'next_read_reopens_checksum_current_source_next182', 'page_number' => $pageNumber];
        }

        $writes = [];
        foreach ($writePages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next182 write page {$pageNumber} is outside the database image");
            }
            $before = $database[$pageNumber]['image'];
            $database[$pageNumber] = ['image' => $image, 'source' => 'next-write-after-checksum-reader-cache-next182'];
            $writes[] = [
                'page_number' => $pageNumber,
                'before_prefix' => self::prefix($before),
                'after_prefix' => self::prefix($image),
                'journal_before_from_checksum_validated_source' => true,
            ];
            $operations[] = ['op' => 'capture_next_write_after_checksum_reader_cache_next182', 'page_number' => $pageNumber];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next182',
            'reason' => 'checksum-validated unknown-count rollback journal fences master-journal reader cache',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'journal_path' => $journalPath,
            'page_size' => $pageSize,
            'master_members' => $members,
            'master_digest' => $masterDigest,
            'unknown_page_count' => $unknownPageCount,
            'journal_record_count' => $recordCount,
            'journal_page_numbers' => $journalPageNumbers,
            'journal_digest' => $journalDigest,
            'checksum_nonce' => $journal->header->checksumNonce,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'next_source' => $nextSource,
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
            'source_digest' => hash('sha256', $nextSource['id'] . '|' . implode(',', array_keys($retained)) . '|' . implode(',', array_keys($refreshed)) . '|' . implode(',', $invalidated)),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next182',
                'sqlite-rollback-journal-unknown-page-count-eof-scan',
                'sqlite-rollback-journal-checksum-validation',
                'sqlite-pager-master-journal-reader-cache-current-source-next174',
            ],
            'non_overlap' => 'Adds checksum nonce, record-count, and unknown-page-count EOF-scan fencing for master-journal reader-cache admission; avoids accepted membership, generation, page-count, and canonical member-set slices.',
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
            $pages[$index + 1] = ['image' => $image, 'source' => 'database-before-checksum-reader-cache-next182'];
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
                throw new \InvalidArgumentException("SQLite pager reader-cache next182 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next182 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_id?:string,master_digest?:string,journal_digest?:string,checksum_nonce?:int,journal_record_count?:int,journal_page_numbers?:list<int>,dirty?:bool,pinned?:bool}> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_id:string,master_digest:string,journal_digest:string,checksum_nonce:int,journal_record_count:int,journal_page_numbers:list<int>,dirty:bool,pinned:bool}>
     */
    private static function cache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager reader-cache next182 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager reader-cache next182 cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = (string) ($entry['source_id'] ?? '');
            $masterDigest = (string) ($entry['master_digest'] ?? '');
            $journalDigest = (string) ($entry['journal_digest'] ?? '');
            $readerId = (string) ($entry['reader_id'] ?? ('reader-' . $pageNumber));
            if ($sourceId === '' || $masterDigest === '' || $journalDigest === '' || $readerId === '') {
                throw new \InvalidArgumentException('SQLite pager reader-cache next182 cache entries require source, reader, and digest fields');
            }
            $epoch = $entry['epoch'] ?? 0;
            $nonce = $entry['checksum_nonce'] ?? null;
            $recordCount = $entry['journal_record_count'] ?? null;
            foreach (['epoch' => $epoch, 'checksum_nonce' => $nonce, 'journal_record_count' => $recordCount] as $name => $value) {
                if (!is_int($value) || $value < 0 || ($name === 'epoch' && $value < 1)) {
                    throw new \InvalidArgumentException("SQLite pager reader-cache next182 cache {$name} must be a valid integer");
                }
            }
            $pageNumbers = $entry['journal_page_numbers'] ?? null;
            if (!is_array($pageNumbers)) {
                throw new \InvalidArgumentException('SQLite pager reader-cache next182 cache journal page numbers must be a list');
            }
            $pageNumbers = array_values($pageNumbers);
            foreach ($pageNumbers as $number) {
                if (!is_int($number) || $number < 1) {
                    throw new \InvalidArgumentException('SQLite pager reader-cache next182 cache journal page numbers must be one-based integers');
                }
            }

            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'reader_id' => $readerId,
                'master_digest' => $masterDigest,
                'journal_digest' => $journalDigest,
                'checksum_nonce' => $nonce,
                'journal_record_count' => $recordCount,
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
                throw new \InvalidArgumentException("SQLite pager reader-cache next182 {$label} page numbers must be one-based integers");
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
                throw new \RuntimeException('SQLite pager reader-cache next182 final image is not page-size bytes');
            }
            $bytes .= $page['image'];
        }

        return $bytes;
    }
}
