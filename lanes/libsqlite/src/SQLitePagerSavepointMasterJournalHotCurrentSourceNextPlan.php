<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointMasterJournalHotCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $hotRecoveredPages
     * @param array<int,string> $savepointWrites
     * @param array<int,string> $retryWrites
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        string $savepointName,
        string $retryStatementName,
        array $hotRecoveredPages,
        array $savepointWrites,
        array $retryWrites,
        array $readPages,
        bool $releaseSavepointAfterRetry = false,
        bool $commitOuterTransaction = false,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal hot current-source next140 requires database and master-journal paths');
        }
        if ($currentMasterJournalBytes === null || $currentMasterJournalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal hot current-source next140 requires current master-journal bytes');
        }
        if (!str_contains($currentMasterJournalBytes, $databasePath . '-journal')) {
            throw new \RuntimeException('SQLite pager savepoint master-journal hot current-source next140 current master journal does not name the database journal');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal hot current-source next140 requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal hot current-source next140 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal hot current-source next140 database bytes must be page-size aligned');
        }
        if ($savepointName === '' || $retryStatementName === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal hot current-source next140 requires savepoint and retry statement names');
        }
        if ($hotRecoveredPages === [] || $savepointWrites === [] || $retryWrites === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal hot current-source next140 requires recovered, savepoint, retry, and read pages');
        }

        $hotRecoveredPages = self::normalizePages($hotRecoveredPages, $pageSize, 'hot recovered');
        $savepointWrites = self::normalizePages($savepointWrites, $pageSize, 'savepoint write');
        $retryWrites = self::normalizePages($retryWrites, $pageSize, 'retry write');
        self::assertPageList($readPages);

        $source = self::sourceMap($databaseBytes, $pageSize, 'crashed-database-before-hot-master-current-source');
        $operations = [[
            'op' => 'read_current_master_journal_before_hot_recovery',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
            'reason' => 'current_master_journal_names_hot_rollback_journal',
        ]];

        foreach ($hotRecoveredPages as $pageNumber => $image) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal hot current-source next140 recovered page {$pageNumber} is outside the database image");
            }
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'hot-master-journal-current-source',
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'restore_hot_master_journal_page',
                'path' => $databasePath,
                'page_number' => $pageNumber,
                'reason' => 'recover_hot_journal_from_current_master_before_savepoint',
            ];
        }

        $hotRecoveredBytes = self::sourceBytes($source, $pageSize);
        $savepointBefore = [];
        foreach ($savepointWrites as $pageNumber => $image) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal hot current-source next140 savepoint page {$pageNumber} is outside the recovered source");
            }
            $savepointBefore[$pageNumber] = $source[$pageNumber]['image'];
            $operations[] = [
                'op' => 'capture_savepoint_before_image_from_hot_current_source',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'before_prefix' => self::label($source[$pageNumber]['image']),
                'source' => $source[$pageNumber]['source'],
                'reason' => 'savepoint_subjournal_uses_hot_recovered_current_source',
            ];
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-write-before-rollback-to',
                'dirty' => true,
            ];
            $operations[] = [
                'op' => 'write_savepoint_page_after_hot_current_source',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'reason' => 'failed_savepoint_write_dirties_recovered_page',
            ];
        }

        $savepointDirtyBytes = self::sourceBytes($source, $pageSize);
        foreach ($savepointBefore as $pageNumber => $image) {
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'rollback-to-savepoint-hot-current-source-before-image',
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'rollback_to_savepoint_hot_current_source_before_image',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'reason' => 'rollback_to_restores_hot_current_source_before_retry',
            ];
        }

        $afterRollbackBytes = self::sourceBytes($source, $pageSize);
        $retryBefore = [];
        foreach ($retryWrites as $pageNumber => $image) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal hot current-source next140 retry page {$pageNumber} is outside the recovered source");
            }
            $retryBefore[$pageNumber] = $source[$pageNumber]['image'];
            $operations[] = [
                'op' => 'capture_retry_before_image_after_savepoint_rollback',
                'statement' => $retryStatementName,
                'page_number' => $pageNumber,
                'before_prefix' => self::label($source[$pageNumber]['image']),
                'source' => $source[$pageNumber]['source'],
                'reason' => 'retry_statement_captures_rollback_to_current_source',
            ];
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'retry-write-after-savepoint-hot-current-source',
                'dirty' => true,
            ];
            $operations[] = [
                'op' => 'write_retry_page_after_savepoint_rollback',
                'statement' => $retryStatementName,
                'page_number' => $pageNumber,
                'reason' => 'retry_statement_dirties_recovered_current_source',
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal hot current-source next140 read page {$pageNumber} is outside the database image");
            }
            $reads[] = [
                'page_number' => $pageNumber,
                'source' => $source[$pageNumber]['source'],
                'dirty' => (bool) $source[$pageNumber]['dirty'],
                'prefix' => self::label($source[$pageNumber]['image']),
                'matches_savepoint_before_image' => isset($savepointBefore[$pageNumber]) && $source[$pageNumber]['image'] === $savepointBefore[$pageNumber],
                'matches_retry_before_image' => isset($retryBefore[$pageNumber]) && $source[$pageNumber]['image'] === $retryBefore[$pageNumber],
            ];
            $operations[] = [
                'op' => 'read_after_savepoint_hot_current_source_retry',
                'page_number' => $pageNumber,
            ];
        }

        $releaseMergedPages = [];
        if ($releaseSavepointAfterRetry) {
            $releaseMergedPages = array_values(array_unique(array_merge(array_keys($savepointBefore), array_keys($retryWrites))));
            sort($releaseMergedPages, SORT_NUMERIC);
            $operations[] = [
                'op' => 'release_savepoint_after_hot_current_source_retry',
                'savepoint' => $savepointName,
                'merged_page_numbers' => $releaseMergedPages,
                'reason' => 'release_keeps_retry_pages_but_outer_master_journal_still_commits_group',
            ];
        }

        $journalAction = $commitOuterTransaction ? 'delete_rollback_journal_after_outer_commit' : 'preserve_rollback_journal_until_outer_commit';
        $masterAction = $commitOuterTransaction ? 'delete_master_journal_after_all_named_journals_commit' : 'preserve_master_journal_until_outer_commit';
        $operations[] = [
            'op' => $commitOuterTransaction ? 'delete_database_rollback_journal' : 'preserve_database_rollback_journal',
            'path' => $databasePath . '-journal',
            'reason' => $journalAction,
        ];
        $operations[] = [
            'op' => $commitOuterTransaction ? 'delete_master_journal' : 'preserve_master_journal',
            'path' => $masterJournalPath,
            'reason' => $masterAction,
        ];

        ksort($source, SORT_NUMERIC);
        $finalBytes = self::sourceBytes($source, $pageSize);

        return [
            'status' => 'pager-savepoint-master-journal-hot-current-source-next140',
            'reason' => 'hot_master_journal_recovery_seeds_savepoint_and_retry_current_source',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'savepoint' => [
                'name' => $savepointName,
                'before_page_numbers' => array_keys($savepointBefore),
                'rollback_restored_page_numbers' => array_keys($savepointBefore),
                'released_after_retry' => $releaseSavepointAfterRetry,
                'release_merged_page_numbers' => $releaseMergedPages,
                'outer_transaction_committed' => $commitOuterTransaction,
            ],
            'retry_statement' => [
                'name' => $retryStatementName,
                'before_page_numbers' => array_keys($retryBefore),
                'write_page_numbers' => array_keys($retryWrites),
            ],
            'hot_recovered_page_numbers' => array_keys($hotRecoveredPages),
            'hot_recovered_prefixes' => self::prefixes($hotRecoveredPages),
            'savepoint_before_prefixes' => self::prefixes($savepointBefore),
            'savepoint_write_prefixes' => self::prefixes($savepointWrites),
            'retry_before_prefixes' => self::prefixes($retryBefore),
            'retry_write_prefixes' => self::prefixes($retryWrites),
            'final_prefixes' => self::prefixesFromSource($source),
            'final_sources' => self::sources($source),
            'dirty_page_numbers' => self::dirtyPageNumbers($source),
            'reads' => $reads,
            'journal_action' => $journalAction,
            'master_journal_action' => $masterAction,
            'hot_recovered_database_bytes' => $hotRecoveredBytes,
            'savepoint_dirty_database_bytes' => $savepointDirtyBytes,
            'after_rollback_database_bytes' => $afterRollbackBytes,
            'final_database_bytes' => $finalBytes,
            'operations' => $operations,
            'source_digest' => hash('sha256', implode('|', self::sources($source)) . '|' . implode(',', self::dirtyPageNumbers($source)) . '|' . $masterAction),
            'dependencies' => [
                'sqlite-pager-savepoint-master-journal-hot-current-source-next140',
                'sqlite-current-master-journal-before-savepoint-hot-recovery',
                'sqlite-savepoint-rollback-to-hot-current-source-before-retry',
                'sqlite-master-journal-delete-deferred-until-outer-commit',
            ],
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePages(array $pages, int $pageSize, string $label): array
    {
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal hot next140 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal hot next140 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager savepoint master-journal hot next140 read pages must be one-based integers');
            }
        }
    }

    /**
     * @return array<int,array{image:string,source:string,dirty:bool}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize, string $source): array
    {
        $map = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $map[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'source' => $source,
                'dirty' => false,
            ];
        }

        return $map;
    }

    /**
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
     */
    private static function sourceBytes(array $source, int $pageSize): string
    {
        ksort($source, SORT_NUMERIC);
        $maxPage = max(array_keys($source));
        $bytes = '';
        for ($pageNumber = 1; $pageNumber <= $maxPage; $pageNumber++) {
            $bytes .= $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
        }

        return $bytes;
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function prefixes(array $pages): array
    {
        $prefixes = [];
        foreach ($pages as $pageNumber => $image) {
            $prefixes[$pageNumber] = self::label($image);
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
     * @return array<int,string>
     */
    private static function prefixesFromSource(array $source): array
    {
        $prefixes = [];
        foreach ($source as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::label($entry['image']);
        }
        ksort($prefixes, SORT_NUMERIC);

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
     * @return array<int,string>
     */
    private static function sources(array $source): array
    {
        $sources = [];
        foreach ($source as $pageNumber => $entry) {
            $sources[$pageNumber] = $entry['source'];
        }
        ksort($sources, SORT_NUMERIC);

        return $sources;
    }

    /**
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
     * @return list<int>
     */
    private static function dirtyPageNumbers(array $source): array
    {
        $pages = [];
        foreach ($source as $pageNumber => $entry) {
            if ($entry['dirty']) {
                $pages[] = $pageNumber;
            }
        }
        sort($pages, SORT_NUMERIC);

        return $pages;
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 72), ".\0");
    }
}
