<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalSavepointCacheCurrentSourceNext138Plan
{
    /**
     * @param array<int,string> $masterRecoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,source?:string}> $cachePages
     * @param array<int,string> $savepointWrites
     * @param array<int,string> $retryStatementWrites
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        string $savepointName,
        string $retryStatementName,
        array $masterRecoveredPages,
        array $cachePages,
        array $savepointWrites,
        array $retryStatementWrites,
        array $readPages,
        string $currentSourceId,
        int $currentSourceEpoch = 1,
        bool $releaseSavepointAfterRetry = false,
    ): array {
        if ($savepointName === '' || $retryStatementName === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next138 requires savepoint and statement names');
        }
        if ($savepointWrites === [] || $retryStatementWrites === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next138 requires savepoint and retry statement writes');
        }

        $savepointWrites = self::normalizeImages($savepointWrites, $pageSize, 'savepoint write');
        $retryStatementWrites = self::normalizeImages($retryStatementWrites, $pageSize, 'retry statement write');
        self::assertPageList($readPages);

        $hot = SQLitePagerMasterJournalHotCacheCurrentSourceNext136Plan::plan(
            $databasePath,
            $masterJournalPath,
            $cachedMasterJournalBytes,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $masterRecoveredPages,
            $cachePages,
            array_values(array_unique(array_merge($readPages, array_keys($savepointWrites), array_keys($retryStatementWrites)))),
            [],
            $currentSourceId,
            $currentSourceEpoch,
            true,
        );

        $nextSource = $hot['next_source'];
        $nextSourceId = (string) $nextSource['id'];
        $nextEpoch = (int) $nextSource['epoch'];
        $source = self::sourceMap((string) $hot['final_database_bytes'], $pageSize, 'master-journal-hot-current-source');
        $operations = $hot['operations'];
        $savepointBefore = [];
        $retryBefore = [];

        foreach ($savepointWrites as $pageNumber => $image) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal savepoint cache next138 savepoint page {$pageNumber} is outside the database image");
            }
            $savepointBefore[$pageNumber] = $source[$pageNumber]['image'];
            $operations[] = [
                'op' => 'capture_savepoint_before_image_after_master_hot_cache',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'before_prefix' => self::label($source[$pageNumber]['image']),
                'reason' => 'savepoint_journal_captures_rebased_master_hot_current_source',
            ];
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-write-before-rollback-to',
                'dirty' => true,
            ];
            $operations[] = [
                'op' => 'write_savepoint_page_after_master_hot_cache',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ];
        }

        foreach ($savepointBefore as $pageNumber => $image) {
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'rollback-to-savepoint-master-hot-before-image',
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'rollback_to_savepoint_master_hot_before_image',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reason' => 'rollback_to_restores_master_journal_hot_current_source',
            ];
        }

        foreach ($retryStatementWrites as $pageNumber => $image) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal savepoint cache next138 retry page {$pageNumber} is outside the database image");
            }
            $retryBefore[$pageNumber] = $source[$pageNumber]['image'];
            $operations[] = [
                'op' => 'capture_retry_statement_before_image_after_savepoint_rollback',
                'statement' => $retryStatementName,
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'before_prefix' => self::label($source[$pageNumber]['image']),
                'reason' => 'retry_statement_captures_restored_master_hot_current_source',
            ];
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'retry-statement-write-after-savepoint-rollback',
                'dirty' => true,
            ];
            $operations[] = [
                'op' => 'write_retry_statement_page_after_savepoint_rollback',
                'statement' => $retryStatementName,
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ];
        }

        $reads = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal savepoint cache next138 read page {$pageNumber} is outside the database image");
            }
            $reads[] = [
                'page_number' => $pageNumber,
                'source' => $source[$pageNumber]['source'],
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => (bool) $source[$pageNumber]['dirty'],
                'prefix' => self::label($source[$pageNumber]['image']),
                'matches_savepoint_before_image' => isset($savepointBefore[$pageNumber]) && $source[$pageNumber]['image'] === $savepointBefore[$pageNumber],
                'matches_retry_before_image' => isset($retryBefore[$pageNumber]) && $source[$pageNumber]['image'] === $retryBefore[$pageNumber],
            ];
            $operations[] = [
                'op' => 'read_after_master_hot_savepoint_retry',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ];
        }

        $releaseMergedPages = [];
        if ($releaseSavepointAfterRetry) {
            $releaseMergedPages = array_values(array_unique(array_merge(
                array_keys($savepointBefore),
                array_keys($retryStatementWrites)
            )));
            sort($releaseMergedPages, SORT_NUMERIC);
            $operations[] = [
                'op' => 'release_savepoint_after_master_hot_retry',
                'savepoint' => $savepointName,
                'merged_page_numbers' => $releaseMergedPages,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reason' => 'release_keeps_retry_pages_on_rebased_current_source',
            ];
        }

        ksort($source, SORT_NUMERIC);

        return [
            'status' => 'pager-master-journal-savepoint-cache-current-source-next138',
            'reason' => 'master_journal_hot_cache_rebases_savepoint_before_retry_statement',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'savepoint' => [
                'name' => $savepointName,
                'still_active_after_rollback_to' => true,
                'released_after_retry' => $releaseSavepointAfterRetry,
                'before_page_numbers' => array_keys($savepointBefore),
                'rollback_restored_page_numbers' => array_keys($savepointBefore),
                'release_merged_page_numbers' => $releaseMergedPages,
            ],
            'retry_statement' => [
                'name' => $retryStatementName,
                'before_page_numbers' => array_keys($retryBefore),
                'write_page_numbers' => array_keys($retryStatementWrites),
            ],
            'hot_cache_status' => $hot['status'],
            'cache_stale_rejected' => $hot['cache_stale_rejected'],
            'retained_cache_page_numbers' => $hot['retained_cache_page_numbers'],
            'refreshed_cache_page_numbers' => $hot['refreshed_cache_page_numbers'],
            'invalidated_cache_page_numbers' => $hot['invalidated_cache_page_numbers'],
            'current_source' => $hot['current_source'],
            'next_source' => $hot['next_source'],
            'savepoint_before_prefixes' => self::prefixes($savepointBefore),
            'retry_statement_before_prefixes' => self::prefixes($retryBefore),
            'final_prefixes' => self::prefixesFromSource($source),
            'final_sources' => self::sources($source),
            'dirty_page_numbers' => self::dirtyPageNumbers($source),
            'reads' => $reads,
            'operations' => $operations,
            'final_database_bytes' => self::sourceBytes($source, $pageSize),
            'source_digest' => hash('sha256', implode('|', self::sources($source)) . '|' . implode(',', self::dirtyPageNumbers($source))),
            'dependencies' => [
                'sqlite-pager-master-journal-savepoint-cache-current-source-next138',
                'sqlite-pager-master-journal-hot-cache-current-source-next136',
                'sqlite-pager-master-journal-savepoint-cache-current-source-next125',
                'sqlite-savepoint-rollback-to-rebased-pager-cache-current-source',
            ],
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizeImages(array $pages, int $pageSize, string $label): array
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException("SQLite pager master-journal savepoint cache next138 {$label} page size must be positive");
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal savepoint cache next138 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal savepoint cache next138 {$label} page {$pageNumber} image must match page size");
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
                throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next138 read pages must be one-based integers');
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
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function prefixes(array $pages): array
    {
        ksort($pages, SORT_NUMERIC);
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
        ksort($source, SORT_NUMERIC);
        $prefixes = [];
        foreach ($source as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::label($entry['image']);
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
     * @return array<int,string>
     */
    private static function sources(array $source): array
    {
        ksort($source, SORT_NUMERIC);
        $sources = [];
        foreach ($source as $pageNumber => $entry) {
            $sources[$pageNumber] = $entry['source'];
        }

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

        return $pages;
    }

    /**
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
     */
    private static function sourceBytes(array $source, int $pageSize): string
    {
        ksort($source, SORT_NUMERIC);
        $bytes = '';
        $maxPage = max(array_keys($source));
        for ($pageNumber = 1; $pageNumber <= $maxPage; $pageNumber++) {
            $bytes .= $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
        }

        return $bytes;
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
