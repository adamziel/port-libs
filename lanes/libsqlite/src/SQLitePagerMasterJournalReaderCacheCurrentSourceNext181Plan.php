<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext181Plan
{
    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,label?:string,master_journal_digest?:string,journal_source_digest?:string,journal_page_count?:int,journal_initial_page_count?:int,journal_page_numbers?:list<int>}> $readerCache
     * @param list<int> $readPages
     * @param array<int,string> $writePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $currentMasterJournalBytes,
        string $pendingMasterJournalBytes,
        string $currentRollbackJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $readerCache,
        array $readPages,
        array $writePages,
        string $currentSourceId,
        int $currentEpoch,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 requires non-empty paths and source id');
        }
        if (trim($currentMasterJournalBytes) === '' || trim($pendingMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 requires current and pending master-journal bytes');
        }
        if ($currentRollbackJournalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 requires rollback-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 database bytes must be page-size aligned');
        }
        if ($readerCache === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 requires reader cache pages');
        }
        if ($readPages === [] && $writePages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 requires read or write pages');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 current epoch must be positive');
        }

        $members = self::members($currentMasterJournalBytes);
        $pendingMembers = self::members($pendingMasterJournalBytes);
        $journalPath = $databasePath . '-journal';
        if (!in_array($journalPath, $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next181 current master journal does not reference the database journal');
        }
        if ($pendingMembers === $members) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 pending master journal must differ from current membership');
        }

        $journal = SQLiteRollbackJournal::parse($currentRollbackJournalBytes, false);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 rollback-journal page size must match pager page size');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $readerCache = self::normalizeReaderCache($readerCache, $pageSize);
        self::assertPageList($readPages, 'read');
        $writePages = self::normalizeImages($writePages, $pageSize, 'write', true);

        $masterDigest = hash('sha256', implode("\n", $members));
        $pendingMasterDigest = hash('sha256', implode("\n", $pendingMembers));
        $addedPendingMembers = array_values(array_diff($pendingMembers, $members));
        $removedPendingMembers = array_values(array_diff($members, $pendingMembers));
        $journalPageNumbers = array_map(static fn (SQLiteRollbackJournalPage $page): int => $page->pageNumber, $journal->pages);
        $journalSourceDigest = self::journalSourceDigest($journalPath, $currentRollbackJournalBytes, $journalPageNumbers, $journal->header);
        $recoveredSourceId = 'master-reader-journal-source:' . hash('sha256', $masterJournalPath . '|' . $masterDigest . '|' . $journalSourceDigest);
        $recoveredEpoch = $currentEpoch + 1;

        foreach ($journal->pages as $page) {
            if (!isset($database[$page->pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next181 journal page {$page->pageNumber} is outside the database image");
            }
            $database[$page->pageNumber] = [
                'image' => $page->pageImage,
                'source' => 'rollback-journal-current-source-before-reader-cache',
            ];
        }

        $operations = [[
            'op' => 'read_current_master_journal_for_rollback_reader_cache',
            'path' => $masterJournalPath,
            'members' => $members,
        ], [
            'op' => 'parse_current_rollback_journal_for_reader_cache_source',
            'path' => $journalPath,
            'journal_source_digest' => $journalSourceDigest,
            'page_numbers' => $journalPageNumbers,
        ]];

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];
        $validCache = [];
        foreach ($readerCache as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next181 cache page {$pageNumber} is outside the database image");
            }

            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['dirty']) {
                $reason = 'dirty_reader_cache_from_aborted_current_journal_source';
            } elseif ($entry['master_journal_digest'] === $pendingMasterDigest) {
                $reason = 'reader_cache_pending_master_journal_source_rejected';
            } elseif ($entry['master_journal_digest'] !== $masterDigest) {
                $reason = 'reader_cache_master_journal_digest_mismatch_next181';
            } elseif ($entry['journal_source_digest'] !== $journalSourceDigest) {
                $reason = 'reader_cache_rollback_journal_source_digest_mismatch';
            } elseif ($entry['journal_page_count'] !== count($journal->pages)) {
                $reason = 'reader_cache_rollback_journal_page_count_mismatch';
            } elseif ($entry['journal_initial_page_count'] !== $journal->header->initialDatabasePageCount) {
                $reason = 'reader_cache_rollback_journal_initial_size_mismatch';
            } elseif ($entry['journal_page_numbers'] !== $journalPageNumbers) {
                $reason = 'reader_cache_rollback_journal_page_set_mismatch';
            } elseif ($entry['pinned'] && $entry['image'] !== $currentImage) {
                $reason = 'pinned_reader_cache_image_predates_current_rollback_source';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $reason = 'reader_cache_source_id_predates_current_rollback_source';
            } elseif ($entry['epoch'] !== $currentEpoch) {
                $reason = 'reader_cache_epoch_predates_current_rollback_source';
            }

            if ($reason !== null) {
                $invalidated[] = $pageNumber;
                $operations[] = [
                    'op' => 'invalidate_reader_cache_after_rollback_journal_source_recheck',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                    'requires_reopen' => true,
                ];
            } elseif ($entry['image'] !== $currentImage) {
                $refreshed[] = $pageNumber;
                $validCache[$pageNumber] = [
                    'image' => $currentImage,
                    'source' => 'reader-cache-refreshed-current-rollback-source',
                ];
                $operations[] = [
                    'op' => 'refresh_reader_cache_from_current_rollback_journal_source',
                    'page_number' => $pageNumber,
                ];
            } else {
                $retained[] = $pageNumber;
                $validCache[$pageNumber] = [
                    'image' => $entry['image'],
                    'source' => 'reader-cache-retained-current-rollback-source',
                ];
                $operations[] = [
                    'op' => 'retain_reader_cache_after_current_rollback_journal_source_check',
                    'page_number' => $pageNumber,
                ];
            }

            $rows[] = [
                'label' => $entry['label'],
                'page_number' => $pageNumber,
                'admitted' => $reason === null,
                'reason' => $reason ?? ($entry['image'] === $currentImage ? 'reader_cache_matches_current_rollback_journal_source' : 'reader_cache_refreshed_from_current_rollback_journal_source'),
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'source_id_before' => $entry['source_id'],
                'epoch_before' => $entry['epoch'],
                'master_journal_digest_matches_current' => $entry['master_journal_digest'] === $masterDigest,
                'master_journal_digest_matches_pending' => $entry['master_journal_digest'] === $pendingMasterDigest,
                'journal_source_digest_matches_current' => $entry['journal_source_digest'] === $journalSourceDigest,
                'journal_page_count_before' => $entry['journal_page_count'],
                'journal_page_count_current' => count($journal->pages),
                'journal_initial_page_count_before' => $entry['journal_initial_page_count'],
                'journal_initial_page_count_current' => $journal->header->initialDatabasePageCount,
                'journal_page_numbers_before' => $entry['journal_page_numbers'],
                'journal_page_numbers_current' => $journalPageNumbers,
                'image_matches_current_source' => $entry['image'] === $currentImage,
                'cache_prefix' => self::label($entry['image']),
                'current_prefix' => self::label($currentImage),
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next181 read page {$pageNumber} is outside the database image");
            }
            $cache = $validCache[$pageNumber] ?? null;
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cache !== null,
                'source' => $cache['source'] ?? $database[$pageNumber]['source'],
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
                'prefix' => self::label($cache['image'] ?? $database[$pageNumber]['image']),
                'journal_source_digest' => $journalSourceDigest,
            ];
            $operations[] = [
                'op' => $cache !== null ? 'next_read_uses_rebased_rollback_reader_cache' : 'next_read_uses_current_rollback_journal_source',
                'page_number' => $pageNumber,
            ];
        }

        $writes = [];
        foreach ($writePages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next181 write page {$pageNumber} is outside the database image");
            }
            $before = $database[$pageNumber]['image'];
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'next-write-after-rollback-journal-reader-cache',
            ];
            $writes[] = [
                'page_number' => $pageNumber,
                'before_prefix' => self::label($before),
                'after_prefix' => self::label($image),
                'journal_before_from_current_rollback_source' => true,
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
            ];
            $operations[] = [
                'op' => 'capture_next_write_before_image_after_rollback_reader_cache_rebase',
                'page_number' => $pageNumber,
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next181',
            'reason' => 'current_master_journal_membership_rejects_pending_source_reader_cache',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $members,
            'pending_members' => $pendingMembers,
            'pending_members_added' => $addedPendingMembers,
            'pending_members_removed' => $removedPendingMembers,
            'current_master_journal_digest' => $masterDigest,
            'pending_master_journal_digest' => $pendingMasterDigest,
            'current_journal_header' => $journal->header->toArray(),
            'current_journal_page_numbers' => $journalPageNumbers,
            'current_journal_source_digest' => $journalSourceDigest,
            'input_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'recovered_source' => ['id' => $recoveredSourceId, 'epoch' => $recoveredEpoch],
            'reader_rows' => $rows,
            'retained_cache_page_numbers' => $retained,
            'refreshed_cache_page_numbers' => $refreshed,
            'invalidated_cache_page_numbers' => $invalidated,
            'requires_reader_reopen' => $invalidated !== [],
            'next_reads' => $reads,
            'next_writes' => $writes,
            'operations' => $operations,
            'final_prefixes' => self::prefixes($database),
            'final_sources' => self::sources($database),
            'final_database_bytes' => self::sourceBytes($database, $pageSize),
            'source_digest' => hash('sha256', $recoveredSourceId . '|' . implode(',', $retained) . '|' . implode(',', $refreshed) . '|' . implode(',', $invalidated)),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next181',
                'sqlite-pager-reader-cache-pending-master-source-fence-next181',
                'sqlite-pager-master-journal-reader-cache-current-source-next170',
                'sqlite-pager-master-journal-reader-cache-current-source-next178',
                'sqlite-rollback-journal-current-source-parse',
            ],
            'non_overlap' => 'Adds pending master-journal membership rejection for reader-cache entries whose bytes may still match current recovery; avoids next170 rollback-journal source digest/page-set fencing and next178 member generation/delete-state fencing.',
        ];
    }

    /**
     * @param list<int> $pageNumbers
     */
    private static function journalSourceDigest(string $journalPath, string $journalBytes, array $pageNumbers, SQLiteRollbackJournalHeader $header): string
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
    private static function sourceMap(string $bytes, int $pageSize): array
    {
        $map = [];
        foreach (str_split($bytes, $pageSize) as $index => $image) {
            $map[$index + 1] = ['image' => $image, 'source' => 'database-before-rollback-journal-reader-cache'];
        }

        return $map;
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private static function normalizeImages(array $images, int $pageSize, string $label, bool $allowEmpty): array
    {
        if ($images === [] && !$allowEmpty) {
            throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next181 requires {$label} pages");
        }
        $normalized = [];
        foreach ($images as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next181 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next181 {$label} page {$pageNumber} must be page-size bytes");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,label?:string,master_journal_digest?:string,journal_source_digest?:string,journal_page_count?:int,journal_initial_page_count?:int,journal_page_numbers?:list<int>}> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,dirty:bool,pinned:bool,label:string,master_journal_digest:string,journal_source_digest:string,journal_page_count:int,journal_initial_page_count:int,journal_page_numbers:list<int>}>
     */
    private static function normalizeReaderCache(array $cache, int $pageSize): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next181 cache page {$pageNumber} must be page-size bytes");
            }
            $sourceId = (string) ($entry['source_id'] ?? '');
            $masterDigest = (string) ($entry['master_journal_digest'] ?? '');
            $journalDigest = (string) ($entry['journal_source_digest'] ?? '');
            if ($sourceId === '' || $masterDigest === '' || $journalDigest === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 cache entries require source id and journal digests');
            }
            $epoch = $entry['epoch'] ?? 0;
            $journalPageCount = $entry['journal_page_count'] ?? null;
            $initialPageCount = $entry['journal_initial_page_count'] ?? null;
            foreach (['epoch' => $epoch, 'journal_page_count' => $journalPageCount, 'journal_initial_page_count' => $initialPageCount] as $name => $value) {
                if (!is_int($value) || $value < 0 || ($name === 'epoch' && $value < 1)) {
                    throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next181 cache {$name} must be a valid integer");
                }
            }
            $journalPageNumbers = $entry['journal_page_numbers'] ?? null;
            if (!is_array($journalPageNumbers)) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 cache journal page numbers must be a list');
            }
            foreach ($journalPageNumbers as $number) {
                if (!is_int($number) || $number < 1) {
                    throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next181 cache journal page numbers must be one-based integers');
                }
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'label' => (string) ($entry['label'] ?? ('reader-cache-page-' . $pageNumber)),
                'master_journal_digest' => $masterDigest,
                'journal_source_digest' => $journalDigest,
                'journal_page_count' => $journalPageCount,
                'journal_initial_page_count' => $initialPageCount,
                'journal_page_numbers' => array_values($journalPageNumbers),
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
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next181 {$label} page numbers must be one-based integers");
            }
        }
    }

    /**
     * @return list<string>
     */
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

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 80), ". \0");
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @return array<int,string>
     */
    private static function prefixes(array $source): array
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
                throw new \RuntimeException('SQLite pager master-journal reader-cache next181 final image is not page-size bytes');
            }
            $bytes .= $entry['image'];
        }

        return $bytes;
    }
}
