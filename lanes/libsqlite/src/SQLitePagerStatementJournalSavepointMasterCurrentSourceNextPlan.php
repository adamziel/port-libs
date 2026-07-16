<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerStatementJournalSavepointMasterCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $masterRecoveredPages
     * @param array<int,string> $savepointBeforeImages
     * @param array<int,string> $statementBeforeImages
     * @param array<int,string> $statementWrites
     * @param array<int,string> $nextStatementBeforeImages
     * @param array<int,string> $nextStatementWrites
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $masterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        string $savepointName,
        string $failedStatementName,
        string $nextStatementName,
        array $masterRecoveredPages,
        array $savepointBeforeImages,
        array $statementBeforeImages,
        array $statementWrites,
        array $nextStatementBeforeImages,
        array $nextStatementWrites,
        bool $releaseSavepointAfterRetry = false,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager statement/savepoint master current-source requires database and master-journal paths');
        }
        if ($masterJournalBytes === null || $masterJournalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager statement/savepoint master current-source requires master-journal bytes');
        }
        if (!str_contains($masterJournalBytes, $databasePath . '-journal')) {
            throw new \RuntimeException('SQLite pager statement/savepoint master current-source master journal does not reference the database journal');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager statement/savepoint master current-source requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager statement/savepoint master current-source page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager statement/savepoint master current-source database bytes must be page-size aligned');
        }
        if ($savepointName === '' || $failedStatementName === '' || $nextStatementName === '') {
            throw new \InvalidArgumentException('SQLite pager statement/savepoint master current-source requires savepoint and statement names');
        }
        foreach ([
            'master-recovered' => $masterRecoveredPages,
            'savepoint-before' => $savepointBeforeImages,
            'statement-before' => $statementBeforeImages,
            'statement-write' => $statementWrites,
            'next-statement-before' => $nextStatementBeforeImages,
            'next-statement-write' => $nextStatementWrites,
        ] as $label => $pages) {
            if ($pages === []) {
                throw new \InvalidArgumentException("SQLite pager statement/savepoint master current-source {$label} pages must not be empty");
            }
        }

        $masterRecoveredPages = self::normalizePages($masterRecoveredPages, $pageSize, 'master-recovered');
        $savepointBeforeImages = self::normalizePages($savepointBeforeImages, $pageSize, 'savepoint-before');
        $statementBeforeImages = self::normalizePages($statementBeforeImages, $pageSize, 'statement-before');
        $statementWrites = self::normalizePages($statementWrites, $pageSize, 'statement-write');
        $nextStatementBeforeImages = self::normalizePages($nextStatementBeforeImages, $pageSize, 'next-statement-before');
        $nextStatementWrites = self::normalizePages($nextStatementWrites, $pageSize, 'next-statement-write');

        $source = self::sourceMap($databaseBytes, $pageSize, 'stale-database-before-master-recovery');
        $operations = [[
            'op' => 'read_master_journal',
            'path' => $masterJournalPath,
            'bytes' => strlen($masterJournalBytes),
            'reason' => 'master_journal_names_database_rollback_journal_before_statement_savepoint',
        ]];

        foreach ($masterRecoveredPages as $pageNumber => $image) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager statement/savepoint master current-source recovered page {$pageNumber} is outside the database image");
            }
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-recovered-current-source',
            ];
            $operations[] = [
                'op' => 'restore_master_journal_page',
                'path' => $databasePath,
                'page_number' => $pageNumber,
                'reason' => 'recover_current_source_before_statement_subjournal',
            ];
        }
        $masterRecoveredBytes = self::sourceBytes($source, $pageSize);

        foreach ($savepointBeforeImages as $pageNumber => $image) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager statement/savepoint master current-source savepoint page {$pageNumber} is outside the recovered source");
            }
            $operations[] = [
                'op' => 'record_savepoint_before_image',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'source' => $source[$pageNumber]['source'],
                'reason' => 'savepoint_records_master_recovered_current_source',
            ];
        }

        foreach ($statementWrites as $pageNumber => $image) {
            if (!isset($statementBeforeImages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager statement/savepoint master current-source statement page {$pageNumber} needs a before image");
            }
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'failed-statement-write',
            ];
            $operations[] = [
                'op' => 'write_failed_statement_page',
                'statement' => $failedStatementName,
                'page_number' => $pageNumber,
                'reason' => 'failed_statement_writes_after_master_recovery',
            ];
        }
        $failedStatementBytes = self::sourceBytes($source, $pageSize);

        foreach ($statementBeforeImages as $pageNumber => $image) {
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'statement-journal-before-image',
            ];
            $operations[] = [
                'op' => 'restore_statement_before_image',
                'statement' => $failedStatementName,
                'page_number' => $pageNumber,
                'reason' => 'rollback_failed_statement_without_discarding_savepoint',
            ];
        }
        $statementRollbackBytes = self::sourceBytes($source, $pageSize);
        $statementRollbackPrefixes = self::prefixesFromSource($source, array_keys($statementBeforeImages), $pageSize);

        foreach ($nextStatementBeforeImages as $pageNumber => $image) {
            $actual = $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            if ($actual !== $image) {
                throw new \RuntimeException("SQLite pager statement/savepoint master current-source next statement page {$pageNumber} is not the current source after statement rollback");
            }
            $operations[] = [
                'op' => 'capture_next_statement_before_image',
                'statement' => $nextStatementName,
                'page_number' => $pageNumber,
                'source' => $source[$pageNumber]['source'] ?? 'zero-fill',
                'reason' => 'next_statement_journal_captures_master_recovered_statement_rollback_source',
            ];
        }

        foreach ($nextStatementWrites as $pageNumber => $image) {
            if (!isset($nextStatementBeforeImages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager statement/savepoint master current-source next statement page {$pageNumber} needs a before image");
            }
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'next-statement-write',
            ];
            $operations[] = [
                'op' => 'write_next_statement_page',
                'statement' => $nextStatementName,
                'page_number' => $pageNumber,
                'reason' => 'retry_statement_uses_master_recovered_statement_rollback_source',
            ];
        }

        $releaseMergedPages = [];
        if ($releaseSavepointAfterRetry) {
            $releaseMergedPages = array_values(array_unique(array_merge(
                array_keys($masterRecoveredPages),
                array_keys($savepointBeforeImages),
                array_keys($statementBeforeImages),
                array_keys($nextStatementWrites)
            )));
            sort($releaseMergedPages, SORT_NUMERIC);
            $operations[] = [
                'op' => 'release_savepoint',
                'savepoint' => $savepointName,
                'merged_page_numbers' => $releaseMergedPages,
                'reason' => 'release_merges_retry_pages_after_statement_journal_cleanup',
            ];
        }

        $finalBytes = self::sourceBytes($source, $pageSize);

        return [
            'status' => 'pager_statement_journal_savepoint_master_current_source_next123',
            'reason' => 'master_journal_recovery_precedes_statement_rollback_inside_active_savepoint',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'savepoint' => $savepointName,
            'failed_statement' => $failedStatementName,
            'next_statement' => $nextStatementName,
            'release_savepoint_after_retry' => $releaseSavepointAfterRetry,
            'current_source_verified' => true,
            'master_recovered_page_numbers' => array_keys($masterRecoveredPages),
            'savepoint_before_page_numbers' => array_keys($savepointBeforeImages),
            'statement_write_page_numbers' => array_keys($statementWrites),
            'statement_restored_page_numbers' => array_keys($statementBeforeImages),
            'next_statement_page_numbers' => array_keys($nextStatementWrites),
            'release_merged_page_numbers' => $releaseMergedPages,
            'master_recovered_prefixes' => self::prefixes($masterRecoveredPages),
            'savepoint_before_prefixes' => self::prefixes($savepointBeforeImages),
            'statement_write_prefixes' => self::prefixes($statementWrites),
            'statement_rollback_prefixes' => $statementRollbackPrefixes,
            'next_statement_before_prefixes' => self::prefixes($nextStatementBeforeImages),
            'final_prefixes' => self::prefixesFromSource($source, array_keys($source), $pageSize),
            'final_sources' => self::sources($source),
            'master_recovered_database_bytes' => $masterRecoveredBytes,
            'failed_statement_database_bytes' => $failedStatementBytes,
            'statement_rollback_database_bytes' => $statementRollbackBytes,
            'final_database_bytes' => $finalBytes,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-statement-journal-savepoint-master-current-source-next123',
                'sqlite-master-journal-current-source-before-statement-subjournal',
                'sqlite-statement-journal-rollback-keeps-active-savepoint',
                'sqlite-savepoint-release-after-master-current-source-retry',
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
                throw new \InvalidArgumentException("SQLite pager statement/savepoint master {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager statement/savepoint master {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize, string $source): array
    {
        $map = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $map[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'source' => $source,
            ];
        }

        return $map;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
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
            $prefixes[$pageNumber] = rtrim(substr($image, 0, 64), ".\0");
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @param list<int> $pageNumbers
     * @return array<int,string>
     */
    private static function prefixesFromSource(array $source, array $pageNumbers, int $pageSize): array
    {
        $prefixes = [];
        foreach ($pageNumbers as $pageNumber) {
            $prefixes[$pageNumber] = rtrim(substr($source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize), 0, min(64, $pageSize)), ".\0");
        }
        ksort($prefixes, SORT_NUMERIC);

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
        ksort($sources, SORT_NUMERIC);

        return $sources;
    }
}
