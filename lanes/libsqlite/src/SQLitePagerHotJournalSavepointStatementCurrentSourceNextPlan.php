<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalSavepointStatementCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $currentSourcePages
     * @param array<int,string> $savepointWrites
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
        array $hotJournalPages,
        array $currentSourcePages,
        array $savepointWrites,
        array $statementBeforeImages,
        array $statementWrites,
        array $nextStatementBeforeImages,
        array $nextStatementWrites,
        bool $reservedLock = false,
        bool $superJournalRequired = false,
        bool $superJournalExists = false,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint statement current-source requires a database path');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint statement current-source page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint statement current-source database bytes must be page-size aligned');
        }
        if ($savepointName === '' || $statementName === '' || $nextStatementName === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint statement current-source requires savepoint and statement names');
        }
        if ($hotJournalPages === [] || $currentSourcePages === [] || $savepointWrites === [] || $statementBeforeImages === [] || $statementWrites === [] || $nextStatementBeforeImages === [] || $nextStatementWrites === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint statement current-source requires non-empty page sets');
        }

        $hotJournalPages = self::normalizePages($hotJournalPages, $pageSize, 'hot-journal');
        $currentSourcePages = self::normalizePages($currentSourcePages, $pageSize, 'current-source');
        $savepointWrites = self::normalizePages($savepointWrites, $pageSize, 'savepoint-write');
        $statementBeforeImages = self::normalizePages($statementBeforeImages, $pageSize, 'statement-before');
        $statementWrites = self::normalizePages($statementWrites, $pageSize, 'statement-write');
        $nextStatementBeforeImages = self::normalizePages($nextStatementBeforeImages, $pageSize, 'next-statement-before');
        $nextStatementWrites = self::normalizePages($nextStatementWrites, $pageSize, 'next-statement-write');
        self::verifyCurrentSource($databaseBytes, $currentSourcePages, $pageSize);

        $canRecoverHotJournal = !$reservedLock && (!$superJournalRequired || $superJournalExists);
        $operations = [];
        $payloads = [];
        $source = self::sourceMap($databaseBytes, $pageSize, 'current-database');

        if ($canRecoverHotJournal) {
            foreach ($hotJournalPages as $pageNumber => $pageImage) {
                $source[$pageNumber] = [
                    'image' => $pageImage,
                    'source' => 'hot-journal',
                ];
            }
            $payloads[$databasePath . '#hot-journal-next97'] = self::sourceBytes($source, $pageSize);
            $operations[] = [
                'op' => 'write_database',
                'path' => $databasePath,
                'payload_key' => $databasePath . '#hot-journal-next97',
                'page_numbers' => array_keys($hotJournalPages),
                'reason' => 'restore_hot_journal_before_savepoint_statement_current_source',
            ];
            $operations[] = [
                'op' => 'delete_journal',
                'path' => $databasePath . '-journal',
                'reason' => 'delete_hot_journal_before_savepoint_statement_retry',
            ];
        }

        $savepointBefore = [];
        foreach ($savepointWrites as $pageNumber => $pageImage) {
            $savepointBefore[$pageNumber] = $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            $operations[] = [
                'op' => 'capture_savepoint_before_image',
                'page_number' => $pageNumber,
                'source' => $source[$pageNumber]['source'] ?? 'zero-fill',
            ];
            $source[$pageNumber] = [
                'image' => $pageImage,
                'source' => 'savepoint-write',
            ];
            $operations[] = [
                'op' => 'write_savepoint_page',
                'page_number' => $pageNumber,
                'reason' => 'write_current_savepoint_page_after_hot_journal',
            ];
        }

        foreach ($statementBeforeImages as $pageNumber => $pageImage) {
            $actual = $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            if ($actual !== $pageImage) {
                throw new \RuntimeException("SQLite pager hot-journal statement before image for page {$pageNumber} is stale");
            }
            $operations[] = [
                'op' => 'capture_statement_before_image',
                'page_number' => $pageNumber,
                'source' => $source[$pageNumber]['source'] ?? 'zero-fill',
            ];
        }

        foreach ($statementWrites as $pageNumber => $pageImage) {
            $source[$pageNumber] = [
                'image' => $pageImage,
                'source' => 'failed-statement-write',
            ];
            $operations[] = [
                'op' => 'write_statement_page',
                'page_number' => $pageNumber,
                'reason' => 'write_failed_statement_page_under_savepoint',
            ];
        }

        $failedStatementBytes = self::sourceBytes($source, $pageSize);
        $payloads[$databasePath . '#failed-statement-next97'] = $failedStatementBytes;

        foreach ($statementBeforeImages as $pageNumber => $pageImage) {
            $source[$pageNumber] = [
                'image' => $pageImage,
                'source' => 'statement-rollback-before-image',
            ];
            $operations[] = [
                'op' => 'restore_statement_before_image',
                'page_number' => $pageNumber,
                'reason' => 'rollback_failed_statement_before_retry',
            ];
        }

        $statementRollbackBytes = self::sourceBytes($source, $pageSize);
        $statementRollbackPrefixes = self::prefixesFromSource($source, array_keys($statementBeforeImages), $pageSize);
        $payloads[$databasePath . '#statement-rollback-next97'] = $statementRollbackBytes;

        foreach ($nextStatementBeforeImages as $pageNumber => $pageImage) {
            $actual = $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            if ($actual !== $pageImage) {
                throw new \RuntimeException("SQLite pager hot-journal next statement before image for page {$pageNumber} is stale");
            }
            $operations[] = [
                'op' => 'capture_next_statement_before_image',
                'page_number' => $pageNumber,
                'source' => $source[$pageNumber]['source'] ?? 'zero-fill',
            ];
        }

        foreach ($nextStatementWrites as $pageNumber => $pageImage) {
            $source[$pageNumber] = [
                'image' => $pageImage,
                'source' => 'next-statement-write',
            ];
            $operations[] = [
                'op' => 'write_next_statement_page',
                'page_number' => $pageNumber,
                'reason' => 'retry_statement_uses_statement_rollback_current_source',
            ];
        }

        ksort($source, SORT_NUMERIC);
        $finalBytes = self::sourceBytes($source, $pageSize);
        $payloads[$databasePath . '#next-statement-next97'] = $finalBytes;
        $operations[] = [
            'op' => 'sync_database',
            'path' => $databasePath,
            'durable' => true,
            'reason' => 'sync_retry_statement_after_hot_journal_statement_recovery',
        ];

        return [
            'status' => $canRecoverHotJournal ? 'pager_hot_journal_savepoint_statement_current_source_next97' : 'pager_hot_journal_savepoint_statement_current_source_blocked_next97',
            'reason' => $canRecoverHotJournal ? 'hot_journal_recovery_precedes_savepoint_statement_retry' : ($reservedLock ? 'reserved_lock_preserves_hot_journal_before_statement_retry' : 'missing_super_journal_preserves_hot_journal_before_statement_retry'),
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'page_size' => $pageSize,
            'savepoint' => $savepointName,
            'statement' => $statementName,
            'next_statement' => $nextStatementName,
            'hot_recovered' => $canRecoverHotJournal,
            'reserved_lock' => $reservedLock,
            'super_journal_required' => $superJournalRequired,
            'super_journal_exists' => $superJournalExists,
            'current_source_verified' => true,
            'hot_journal_page_numbers' => array_keys($hotJournalPages),
            'current_source_page_numbers' => array_keys($currentSourcePages),
            'savepoint_captured_page_numbers' => array_keys($savepointBefore),
            'statement_restored_page_numbers' => array_keys($statementBeforeImages),
            'next_statement_page_numbers' => array_keys($nextStatementWrites),
            'current_source_prefixes' => self::prefixes($currentSourcePages),
            'hot_journal_prefixes' => self::prefixes($hotJournalPages),
            'statement_rollback_prefixes' => $statementRollbackPrefixes,
            'final_page_numbers' => array_keys($source),
            'final_sources' => self::sources($source),
            'failed_statement_database_bytes' => $failedStatementBytes,
            'statement_rollback_database_bytes' => $statementRollbackBytes,
            'final_database_bytes' => $finalBytes,
            'operations' => $operations,
            'payloads' => $payloads,
            'dependencies' => [
                'sqlite-pager-hot-journal-savepoint-statement-current-source-next97',
                'sqlite-hot-journal-recovery',
                'sqlite-statement-journal-rollback-current-source',
                'sqlite-savepoint-before-image-current-source',
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
                throw new \InvalidArgumentException("SQLite pager hot-journal {$label} page numbers must be one-based integers");
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $pageImage;
        }

        return $normalized;
    }

    /**
     * @param array<int,string> $pages
     */
    private static function verifyCurrentSource(string $databaseBytes, array $pages, int $pageSize): void
    {
        foreach ($pages as $pageNumber => $pageImage) {
            if (substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize) !== $pageImage) {
                throw new \InvalidArgumentException('SQLite pager hot-journal current-source pages must match the current database image');
            }
        }
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
            $prefixes[$pageNumber] = rtrim(substr($pageImage, 0, 48), ".\0");
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
            $prefixes[$pageNumber] = rtrim(substr($source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize), 0, 48), ".\0");
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
