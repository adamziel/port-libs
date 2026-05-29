<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalSavepointCacheCurrentSourceNextPlan
{


    /**
     * @param list<array{database_path:string,current_database_bytes:string,current_journal_bytes:string,stale_database_bytes?:string,stale_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @param array<int,string> $retryPageWrites
     * @param array<int,array{image:string,source?:string,source_id?:string,epoch?:int,dirty?:bool}> $cachePages
     * @param list<int> $releaseReadPages
     * @return array<string,mixed>
     */
    public static function currentSourceNext125(
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        array $databases,
        int $pageSize,
        string $primaryDatabasePath,
        string $savepointName,
        SQLiteSavepointStack $savepoints,
        array $retryPageWrites,
        array $cachePages,
        array $releaseReadPages,
        string $currentSourceId,
        int $currentSourceEpoch = 1,
    ): array {
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 requires a current source id');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 source epoch must be positive');
        }
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 requires cache pages');
        }
        if ($releaseReadPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 requires release read pages');
        }

        self::assertCachePages125($cachePages, $pageSize);
        self::assertPageList125($releaseReadPages);

        $recovery = SQLitePagerMasterJournalCacheRecoveryCurrentSourceNext122Plan::currentSourceNext(
            $masterJournalPath,
            $cachedMasterJournalBytes,
            $currentMasterJournalBytes,
            $databases,
            $pageSize,
            $primaryDatabasePath,
            $savepointName,
            clone $savepoints,
            $retryPageWrites
        );

        $currentMembers = $recovery['current_members'] ?? [];
        $nextSourceId = self::sourceId125($masterJournalPath, is_array($currentMembers) ? $currentMembers : []);
        $nextEpoch = $currentSourceEpoch + 1;
        $currentSourceVerified = ($recovery['current_source_verified'] ?? false) === true;
        $recoveredBytes = (string) ($recovery['recovery']['retry_recovery']['recovered_database_bytes'] ?? '');
        $rollbackBytes = (string) ($recovery['payloads'][$primaryDatabasePath . '#master-savepoint-rollback-preview-next108'] ?? $recoveredBytes);
        $finalBytes = (string) ($recovery['payloads'][$primaryDatabasePath . '#master-savepoint-current-source-next108'] ?? $recoveredBytes);
        $capturedPages = self::intList125($recovery['recovery']['captured_page_numbers'] ?? []);
        $rollbackPages = self::intList125($recovery['rollback_preview']['restored_page_numbers'] ?? []);

        $validCache = [];
        $invalidated = [];
        $operations = $recovery['operations'] ?? [];
        if (!is_array($operations)) {
            $operations = [];
        }

        foreach ($cachePages as $pageNumber => $entry) {
            $image = $entry['image'];
            $dirty = ($entry['dirty'] ?? false) === true;
            $sourceId = (string) ($entry['source_id'] ?? '');
            $epoch = (int) ($entry['epoch'] ?? 0);
            $source = (string) ($entry['source'] ?? 'unknown');
            $reason = null;
            if (!$currentSourceVerified) {
                $reason = 'master_journal_current_source_unverified';
            } elseif ($dirty) {
                $reason = 'dirty_cache_from_aborted_savepoint_retry';
            } elseif ($sourceId !== $currentSourceId) {
                $reason = 'stale_current_source_id';
            } elseif ($epoch !== $currentSourceEpoch) {
                $reason = 'stale_current_source_epoch';
            } elseif (!self::matchesAnyPageImage125($image, $pageNumber, $pageSize, [$recoveredBytes, $rollbackBytes, $finalBytes])) {
                $reason = 'cached_image_not_from_recovered_current_source';
            }

            if ($reason !== null) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'epoch' => $epoch,
                    'dirty' => $dirty,
                    'reason' => $reason,
                ];
                $operations[] = [
                    'op' => 'invalidate_master_journal_savepoint_cache_page',
                    'page_number' => $pageNumber,
                    'source' => $source,
                    'reason' => $reason,
                ];
                continue;
            }

            $validCache[$pageNumber] = [
                'image' => $image,
                'source' => $source,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => false,
            ];
        }

        foreach (array_unique(array_merge($capturedPages, $rollbackPages)) as $pageNumber) {
            $image = self::pageImage125($rollbackBytes !== '' ? $rollbackBytes : $recoveredBytes, $pageNumber, $pageSize);
            $validCache[$pageNumber] = [
                'image' => $image,
                'source' => in_array($pageNumber, $capturedPages, true)
                    ? 'master-journal-savepoint-before-image'
                    : 'master-journal-savepoint-rollback-image',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'install_master_journal_savepoint_cache_page',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reason' => 'cache_page_from_recovered_master_journal_savepoint_source',
            ];
        }
        ksort($validCache, SORT_NUMERIC);

        $releaseReads = [];
        foreach ($releaseReadPages as $pageNumber) {
            $entry = $validCache[$pageNumber] ?? null;
            $hit = is_array($entry)
                && ($entry['source_id'] ?? null) === $nextSourceId
                && ($entry['epoch'] ?? null) === $nextEpoch
                && ($entry['dirty'] ?? true) === false;
            $releaseReads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $hit,
                'source' => $hit ? (string) $entry['source'] : 'pager-read-miss',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'prefix' => $hit ? self::prefix125((string) $entry['image']) : '',
                'zero_filled_short_read' => !$hit && self::pageImage125($rollbackBytes, $pageNumber, $pageSize) === str_repeat("\0", $pageSize),
            ];
            $operations[] = [
                'op' => $hit ? 'release_read_master_journal_cache_hit' : 'release_read_master_journal_cache_miss',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reason' => 'read_after_savepoint_release_uses_current_source_cache',
            ];
        }

        $status = $currentSourceVerified
            ? 'pager_master_journal_savepoint_cache_current_source_next125'
            : 'pager_master_journal_savepoint_cache_current_source_blocked_next125';

        return [
            'status' => $status,
            'reason' => $currentSourceVerified
                ? 'savepoint_cache_pages_rebased_to_master_journal_current_source'
                : 'master_journal_current_source_not_verified_for_savepoint_cache',
            'master_journal_path' => $masterJournalPath,
            'primary_database_path' => $primaryDatabasePath,
            'savepoint' => $savepointName,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentSourceEpoch,
            ],
            'next_source' => [
                'id' => $nextSourceId,
                'epoch' => $nextEpoch,
            ],
            'current_source_verified' => $currentSourceVerified,
            'cache_stale_rejected' => ($recovery['cache_stale_rejected'] ?? false) === true,
            'cache' => [
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'installed_page_numbers' => array_values(array_unique(array_merge($capturedPages, $rollbackPages))),
                'final_page_numbers' => array_keys($validCache),
                'final_sources' => self::sources125($validCache),
                'final_source_ids' => self::sourceIds125($validCache),
                'dirty_page_numbers' => self::dirtyPageNumbers125($validCache),
            ],
            'release_reads' => $releaseReads,
            'recovery' => $recovery,
            'operations' => $operations,
            'payloads' => $recovery['payloads'] ?? [],
            'dependencies' => array_values(array_unique(array_merge(
                is_array($recovery['dependencies'] ?? null) ? $recovery['dependencies'] : [],
                [
                    'sqlite-pager-master-journal-savepoint-cache-current-source-next125',
                    'sqlite-pager-master-journal-cache-recovery-current-source-next122',
                    'sqlite-pager-cache-current-source-token',
                ]
            ))),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $cachePages
     */
    private static function assertCachePages125(array $cachePages, int $pageSize): void
    {
        foreach ($cachePages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal savepoint cache next125 page {$pageNumber} image must match page size");
            }
        }
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList125(array $pages): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal savepoint cache next125 release read pages must be one-based integers');
            }
        }
    }

    /**
     * @param list<mixed> $values
     * @return list<int>
     */
    private static function intList125(array $values): array
    {
        $ints = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $ints[] = $value;
            }
        }

        return $ints;
    }

    /**
     * @param list<string> $images
     */
    private static function matchesAnyPageImage125(string $image, int $pageNumber, int $pageSize, array $images): bool
    {
        foreach ($images as $databaseBytes) {
            if ($databaseBytes !== '' && $image === self::pageImage125($databaseBytes, $pageNumber, $pageSize)) {
                return true;
            }
        }

        return false;
    }

    private static function pageImage125(string $databaseBytes, int $pageNumber, int $pageSize): string
    {
        $offset = ($pageNumber - 1) * $pageSize;

        return str_pad(substr($databaseBytes, $offset, $pageSize), $pageSize, "\0");
    }

    /**
     * @param list<string> $members
     */
    private static function sourceId125(string $masterJournalPath, array $members): string
    {
        return 'master-journal:' . substr(hash('sha256', $masterJournalPath . "\n" . implode("\n", $members)), 0, 16);
    }

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return array<int,string>
     */
    private static function sources125(array $pages): array
    {
        $sources = [];
        foreach ($pages as $pageNumber => $entry) {
            $sources[$pageNumber] = (string) ($entry['source'] ?? 'unknown');
        }

        return $sources;
    }

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return array<int,string>
     */
    private static function sourceIds125(array $pages): array
    {
        $sourceIds = [];
        foreach ($pages as $pageNumber => $entry) {
            $sourceIds[$pageNumber] = (string) ($entry['source_id'] ?? '');
        }

        return $sourceIds;
    }

    /**
     * @param array<int,array<string,mixed>> $pages
     * @return list<int>
     */
    private static function dirtyPageNumbers125(array $pages): array
    {
        $dirty = [];
        foreach ($pages as $pageNumber => $entry) {
            if (($entry['dirty'] ?? false) === true) {
                $dirty[] = $pageNumber;
            }
        }

        return $dirty;
    }

    private static function prefix125(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 56), "\0.");
    }


    /**
     * @param array<int,string> $masterRecoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,source?:string}> $cachePages
     * @param array<int,string> $savepointWrites
     * @param array<int,string> $retryStatementWrites
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function plan138(
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

        $savepointWrites = self::normalizeImages138($savepointWrites, $pageSize, 'savepoint write');
        $retryStatementWrites = self::normalizeImages138($retryStatementWrites, $pageSize, 'retry statement write');
        self::assertPageList138($readPages);

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
        $source = self::sourceMap138((string) $hot['final_database_bytes'], $pageSize, 'master-journal-hot-current-source');
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
                'before_prefix' => self::label138($source[$pageNumber]['image']),
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
                'before_prefix' => self::label138($source[$pageNumber]['image']),
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
                'prefix' => self::label138($source[$pageNumber]['image']),
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
            'savepoint_before_prefixes' => self::prefixes138($savepointBefore),
            'retry_statement_before_prefixes' => self::prefixes138($retryBefore),
            'final_prefixes' => self::prefixesFromSource138($source),
            'final_sources' => self::sources138($source),
            'dirty_page_numbers' => self::dirtyPageNumbers138($source),
            'reads' => $reads,
            'operations' => $operations,
            'final_database_bytes' => self::sourceBytes138($source, $pageSize),
            'source_digest' => hash('sha256', implode('|', self::sources138($source)) . '|' . implode(',', self::dirtyPageNumbers138($source))),
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
    private static function normalizeImages138(array $pages, int $pageSize, string $label): array
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
    private static function assertPageList138(array $pages): void
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
    private static function sourceMap138(string $databaseBytes, int $pageSize, string $source): array
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
    private static function prefixes138(array $pages): array
    {
        ksort($pages, SORT_NUMERIC);
        $prefixes = [];
        foreach ($pages as $pageNumber => $image) {
            $prefixes[$pageNumber] = self::label138($image);
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
     * @return array<int,string>
     */
    private static function prefixesFromSource138(array $source): array
    {
        ksort($source, SORT_NUMERIC);
        $prefixes = [];
        foreach ($source as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::label138($entry['image']);
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
     * @return array<int,string>
     */
    private static function sources138(array $source): array
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
    private static function dirtyPageNumbers138(array $source): array
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
    private static function sourceBytes138(array $source, int $pageSize): string
    {
        ksort($source, SORT_NUMERIC);
        $bytes = '';
        $maxPage = max(array_keys($source));
        for ($pageNumber = 1; $pageNumber <= $maxPage; $pageNumber++) {
            $bytes .= $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
        }

        return $bytes;
    }

    private static function label138(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
