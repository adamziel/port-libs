<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerStatementJournalSavepointCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $currentSourcePages
     * @param array<int,string> $savepointBeforeImages
     * @param array<int,string> $statementBeforeImages
     * @param array<int,string> $statementWrites
     * @param array<int,string> $nextStatementBeforeImages
     * @param array<int,string> $nextStatementWrites
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $savepointName,
        string $statementName,
        string $nextStatementName,
        array $currentSourcePages,
        array $savepointBeforeImages,
        array $statementBeforeImages,
        array $statementWrites,
        array $nextStatementBeforeImages,
        array $nextStatementWrites,
        bool $releaseSavepointAfterRetry = false,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager statement savepoint current-source requires a database path');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager statement savepoint current-source requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager statement savepoint current-source page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager statement savepoint current-source database bytes must be page-size aligned');
        }
        if ($savepointName === '' || $statementName === '' || $nextStatementName === '') {
            throw new \InvalidArgumentException('SQLite pager statement savepoint current-source requires savepoint and statement names');
        }
        if ($currentSourcePages === [] || $savepointBeforeImages === [] || $statementBeforeImages === [] || $statementWrites === [] || $nextStatementBeforeImages === [] || $nextStatementWrites === []) {
            throw new \InvalidArgumentException('SQLite pager statement savepoint current-source requires non-empty page sets');
        }

        $currentSourcePages = self::normalizePages($currentSourcePages, $pageSize, 'current-source');
        $savepointBeforeImages = self::normalizePages($savepointBeforeImages, $pageSize, 'savepoint-before');
        $statementBeforeImages = self::normalizePages($statementBeforeImages, $pageSize, 'statement-before');
        $statementWrites = self::normalizePages($statementWrites, $pageSize, 'statement-write');
        $nextStatementBeforeImages = self::normalizePages($nextStatementBeforeImages, $pageSize, 'next-statement-before');
        $nextStatementWrites = self::normalizePages($nextStatementWrites, $pageSize, 'next-statement-write');

        $source = self::sourceMap($databaseBytes, $pageSize, 'current-database');
        foreach ($currentSourcePages as $pageNumber => $pageImage) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager statement savepoint current-source page {$pageNumber} is outside the database image");
            }
            if ($source[$pageNumber]['image'] !== $pageImage) {
                throw new \RuntimeException("SQLite pager statement savepoint current-source page {$pageNumber} is stale");
            }
        }

        $operations = [];
        foreach ($statementWrites as $pageNumber => $pageImage) {
            if (!isset($currentSourcePages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager statement savepoint current-source statement write page {$pageNumber} must be part of the verified current source");
            }
            if ($currentSourcePages[$pageNumber] !== $pageImage) {
                throw new \RuntimeException("SQLite pager statement savepoint current-source statement write page {$pageNumber} does not match current source");
            }
            $operations[] = [
                'op' => 'verify_failed_statement_page',
                'statement' => $statementName,
                'page_number' => $pageNumber,
                'source' => 'current-source',
            ];
        }

        foreach ($statementBeforeImages as $pageNumber => $pageImage) {
            $source[$pageNumber] = [
                'image' => $pageImage,
                'source' => 'statement-journal-before-image',
            ];
            $operations[] = [
                'op' => 'restore_statement_before_image',
                'statement' => $statementName,
                'page_number' => $pageNumber,
                'reason' => 'rollback_failed_statement_inside_active_savepoint',
            ];
        }

        $statementRollbackBytes = self::sourceBytes($source, $pageSize);
        $statementRollbackPrefixes = self::prefixesFromSource($source, array_keys($statementBeforeImages), $pageSize);

        foreach ($nextStatementBeforeImages as $pageNumber => $pageImage) {
            $actual = $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            if ($actual !== $pageImage) {
                throw new \RuntimeException("SQLite pager statement savepoint current-source next statement page {$pageNumber} is not the restored current source");
            }
            $operations[] = [
                'op' => 'capture_next_statement_before_image',
                'statement' => $nextStatementName,
                'page_number' => $pageNumber,
                'source' => $source[$pageNumber]['source'] ?? 'zero-fill',
            ];
        }

        foreach ($nextStatementWrites as $pageNumber => $pageImage) {
            if (!isset($nextStatementBeforeImages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager statement savepoint current-source next statement page {$pageNumber} needs a before image");
            }
            $source[$pageNumber] = [
                'image' => $pageImage,
                'source' => 'next-statement-write',
            ];
            $operations[] = [
                'op' => 'write_next_statement_page',
                'statement' => $nextStatementName,
                'page_number' => $pageNumber,
                'reason' => 'retry_statement_uses_statement_rollback_current_source',
            ];
        }

        $releaseMergedPages = [];
        if ($releaseSavepointAfterRetry) {
            $releaseMergedPages = array_values(array_unique(array_merge(
                array_keys($savepointBeforeImages),
                array_keys($statementBeforeImages),
                array_keys($nextStatementWrites)
            )));
            sort($releaseMergedPages, SORT_NUMERIC);
            $operations[] = [
                'op' => 'release_savepoint',
                'savepoint' => $savepointName,
                'merged_page_numbers' => $releaseMergedPages,
                'reason' => 'merge_statement_retry_pages_into_outer_transaction',
            ];
        }

        $finalBytes = self::sourceBytes($source, $pageSize);

        return [
            'status' => 'pager_statement_journal_savepoint_current_source_next102',
            'reason' => 'statement_journal_rollback_keeps_active_savepoint_current_source_for_retry',
            'database_path' => $databasePath,
            'page_size' => $pageSize,
            'savepoint' => $savepointName,
            'statement' => $statementName,
            'next_statement' => $nextStatementName,
            'release_savepoint_after_retry' => $releaseSavepointAfterRetry,
            'current_source_verified' => true,
            'current_source_page_numbers' => array_keys($currentSourcePages),
            'savepoint_before_page_numbers' => array_keys($savepointBeforeImages),
            'statement_write_page_numbers' => array_keys($statementWrites),
            'statement_restored_page_numbers' => array_keys($statementBeforeImages),
            'next_statement_page_numbers' => array_keys($nextStatementWrites),
            'release_merged_page_numbers' => $releaseMergedPages,
            'current_source_prefixes' => self::prefixes($currentSourcePages),
            'savepoint_before_prefixes' => self::prefixes($savepointBeforeImages),
            'statement_rollback_prefixes' => $statementRollbackPrefixes,
            'next_statement_before_prefixes' => self::prefixes($nextStatementBeforeImages),
            'final_prefixes' => self::prefixesFromSource($source, array_keys($source), $pageSize),
            'final_sources' => self::sources($source),
            'statement_rollback_database_bytes' => $statementRollbackBytes,
            'final_database_bytes' => $finalBytes,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-statement-journal-savepoint-current-source-next102',
                'sqlite-statement-journal-current-source-guard',
                'sqlite-statement-rollback-keeps-savepoint',
                'sqlite-savepoint-release-after-statement-retry',
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
        foreach ($pages as $pageNumber => $pageImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager statement savepoint {$label} page numbers must be one-based integers");
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager statement savepoint {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $pageImage;
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
        foreach ($pages as $pageNumber => $pageImage) {
            $prefixes[$pageNumber] = rtrim(substr($pageImage, 0, 64), ".\0");
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

        return $sources;
    }
}
