<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerRollbackCacheSpillMasterCurrentSourceNextPlan
{
    /**
     * @param list<array{database_path:string,current_database_bytes:string,current_journal_bytes:string,stale_database_bytes?:string,stale_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @param array<int,string> $retryPageWrites
     * @param list<array{page:int,image:string,source_id:string,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,stale_image?:string,statement?:string}> $cachePages
     * @param list<int> $retryReads
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
        string $masterJournalPath,
        ?string $masterJournalBytes,
        array $databases,
        int $pageSize,
        string $primaryDatabasePath,
        string $savepointName,
        SQLiteSavepointStack $savepoints,
        array $retryPageWrites,
        array $cachePages,
        int $cacheSize,
        int $spillThreshold,
        string $currentSourceId,
        string $recoveredSourceId,
        array $retryReads,
        string $journalMode = 'delete',
        bool $journalSynced = true,
        string $lockState = 'reserved',
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null,
    ): array {
        self::validateSourceIds($currentSourceId, $recoveredSourceId);
        if ($retryReads === []) {
            throw new \InvalidArgumentException('SQLite pager rollback cache-spill retry reads are required');
        }

        $cacheInput = self::normalizeCachePages($cachePages, $pageSize);
        $spillRecovery = SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext(
            $masterJournalPath,
            $masterJournalBytes,
            $databases,
            $pageSize,
            $primaryDatabasePath,
            $savepointName,
            $savepoints,
            $retryPageWrites,
            self::stripCacheSourceIds($cacheInput),
            $cacheSize,
            $spillThreshold,
            $journalMode,
            $journalSynced,
            $lockState,
            $cacheSpillEnabled,
            $maxSpillPages
        );

        $currentBytes = self::recoveredDatabaseBytes($spillRecovery, $databases, $primaryDatabasePath);
        $rollbackBytes = (string) ($spillRecovery['rollback_preview_bytes'] ?? '') !== ''
            ? (string) (($spillRecovery['recovery']['payloads'][$primaryDatabasePath . '#master-savepoint-rollback-preview-next108'] ?? null) ?: $currentBytes)
            : $currentBytes;

        $spilledPages = $spillRecovery['spill']['next']['spilled_pages'] ?? [];
        $admittedPages = [];
        $stalePages = [];
        $mismatchPages = [];
        $pinnedPages = [];
        $admissionRows = [];

        foreach ($cacheInput as $cachePage) {
            $page = $cachePage['page'];
            if (($cachePage['pinned'] ?? false) === true) {
                $pinnedPages[] = $page;
            }
            if (!in_array($page, $spilledPages, true)) {
                continue;
            }

            $matchesCurrent = $cachePage['image'] === self::pageImage($currentBytes, $page, $pageSize);
            $matchesRollback = $cachePage['image'] === self::pageImage($rollbackBytes, $page, $pageSize);
            $sourceCurrent = $cachePage['source_id'] === $recoveredSourceId;
            $usesStaleSource = $cachePage['source_id'] === $currentSourceId || (($cachePage['stale_image'] ?? null) === $cachePage['image']);
            if ($usesStaleSource) {
                $stalePages[] = $page;
            }
            if (!$sourceCurrent || (!$matchesCurrent && !$matchesRollback)) {
                $mismatchPages[] = $page;
            }
            if ($sourceCurrent && !$usesStaleSource && ($matchesCurrent || $matchesRollback)) {
                $admittedPages[] = $page;
            }

            $admissionRows[$page] = [
                'page' => $page,
                'statement' => $cachePage['statement'] ?? null,
                'source_id' => $cachePage['source_id'],
                'next_source_id' => $recoveredSourceId,
                'prefix' => self::prefix($cachePage['image']),
                'matches_recovered_current' => $matchesCurrent,
                'matches_rollback_preview' => $matchesRollback,
                'uses_stale_dirty_cache' => $usesStaleSource,
                'admitted_for_spill' => in_array($page, $admittedPages, true),
            ];
        }

        sort($admittedPages, SORT_NUMERIC);
        sort($stalePages, SORT_NUMERIC);
        sort($mismatchPages, SORT_NUMERIC);
        sort($pinnedPages, SORT_NUMERIC);

        $retryRows = self::retryRows($retryReads, $currentBytes, $rollbackBytes, $pageSize, $recoveredSourceId, $admittedPages);
        $blockedReasons = [];
        if (($spillRecovery['status'] ?? '') !== 'master_journal_cache_spill_savepoint_current_source_next114') {
            $blockedReasons[] = 'master_journal_current_source_not_verified';
        }
        if ($mismatchPages !== []) {
            $blockedReasons[] = 'stale_cache_generation_for_spill_pages';
        }
        if (($spillRecovery['spill']['status'] ?? null) !== 'spilled') {
            $blockedReasons[] = 'cache_spill_deferred';
        }

        return [
            'status' => $blockedReasons === []
                ? 'pager_rollback_cache_spill_master_current_source_next121'
                : 'pager_rollback_cache_spill_master_current_source_blocked_next121',
            'reason' => $blockedReasons === []
                ? 'rollback_recovery_rekeys_cache_spill_pages_to_master_current_source'
                : 'rollback_recovery_rejects_stale_cache_spill_generation',
            'primary_database_path' => $primaryDatabasePath,
            'savepoint' => $savepointName,
            'journal_mode' => strtolower(trim($journalMode)),
            'current_source_id' => $currentSourceId,
            'recovered_source_id' => $recoveredSourceId,
            'spill_recovery' => $spillRecovery,
            'spilled_pages' => $spilledPages,
            'admitted_spill_pages' => $admittedPages,
            'stale_cache_pages' => $stalePages,
            'source_mismatch_pages' => $mismatchPages,
            'pinned_cache_pages' => $pinnedPages,
            'retry_reads' => $retryRows,
            'admission' => array_values($admissionRows),
            'blocked_reasons' => $blockedReasons,
            'operations' => self::operations($spillRecovery['operations'] ?? [], $admittedPages, $stalePages, $retryRows, $primaryDatabasePath, $recoveredSourceId),
            'dependencies' => array_values(array_unique(array_merge(
                $spillRecovery['dependencies'] ?? [],
                [
                    'sqlite-pager-rollback-cache-spill-master-current-source-next121',
                    'sqlite-pager-master-journal-cache-spill-savepoint-current-source-next114',
                    'sqlite-pager-cache-generation-after-rollback',
                ]
            ))),
        ];
    }

    private static function validateSourceIds(string $currentSourceId, string $recoveredSourceId): void
    {
        if (trim($currentSourceId) === '' || trim($recoveredSourceId) === '') {
            throw new \InvalidArgumentException('SQLite pager rollback cache-spill source ids must be non-empty');
        }
        if ($currentSourceId === $recoveredSourceId) {
            throw new \InvalidArgumentException('SQLite pager rollback cache-spill recovered source id must advance');
        }
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,image:string,source_id:string,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,stale_image?:string,statement?:string}>
     */
    private static function normalizeCachePages(array $cachePages, int $pageSize): array
    {
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager rollback cache-spill pages are required');
        }
        $normalized = [];
        $seen = [];
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'] ?? null;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager rollback cache-spill pages must be one-based integers');
            }
            if (isset($seen[$page])) {
                throw new \InvalidArgumentException('SQLite pager rollback cache-spill pages must be unique');
            }
            $seen[$page] = true;

            $image = $cachePage['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager rollback cache-spill page images must match page size');
            }
            $sourceId = $cachePage['source_id'] ?? null;
            if (!is_string($sourceId) || trim($sourceId) === '') {
                throw new \InvalidArgumentException('SQLite pager rollback cache-spill source ids must be non-empty');
            }
            if (isset($cachePage['stale_image']) && (!is_string($cachePage['stale_image']) || strlen($cachePage['stale_image']) !== $pageSize)) {
                throw new \InvalidArgumentException('SQLite pager rollback cache-spill stale images must match page size');
            }
            if (isset($cachePage['statement']) && (!is_string($cachePage['statement']) || trim($cachePage['statement']) === '')) {
                throw new \InvalidArgumentException('SQLite pager rollback cache-spill statement names must be non-empty');
            }

            $cachePage['page'] = $page;
            $cachePage['image'] = $image;
            $cachePage['source_id'] = $sourceId;
            $normalized[] = $cachePage;
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array<string,mixed>>
     */
    private static function stripCacheSourceIds(array $cachePages): array
    {
        return array_map(static function (array $cachePage): array {
            unset($cachePage['source_id'], $cachePage['statement']);

            return $cachePage;
        }, $cachePages);
    }

    /**
     * @param list<int> $retryReads
     * @param list<int> $admittedPages
     * @return list<array<string,mixed>>
     */
    private static function retryRows(array $retryReads, string $currentBytes, string $rollbackBytes, int $pageSize, string $recoveredSourceId, array $admittedPages): array
    {
        $rows = [];
        foreach ($retryReads as $page) {
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager rollback cache-spill retry pages must be one-based integers');
            }
            $currentImage = self::pageImage($currentBytes, $page, $pageSize);
            $rollbackImage = self::pageImage($rollbackBytes, $page, $pageSize);
            $seeded = in_array($page, $admittedPages, true) || $currentImage !== str_repeat("\0", $pageSize) || $rollbackImage !== str_repeat("\0", $pageSize);
            $rows[] = [
                'page' => $page,
                'source_id' => $seeded ? $recoveredSourceId : null,
                'cache_seeded' => $seeded,
                'source' => $seeded ? (in_array($page, $admittedPages, true) ? 'admitted-spill-cache' : 'recovered-current-source') : 'pager-read-miss',
                'image_prefix' => $seeded ? self::prefix($rollbackImage !== str_repeat("\0", $pageSize) ? $rollbackImage : $currentImage) : null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $recoveryOperations
     * @param list<int> $admittedPages
     * @param list<int> $stalePages
     * @param list<array<string,mixed>> $retryRows
     * @return list<array<string,mixed>>
     */
    private static function operations(array $recoveryOperations, array $admittedPages, array $stalePages, array $retryRows, string $databasePath, string $sourceId): array
    {
        $operations = $recoveryOperations;
        foreach ($stalePages as $page) {
            $operations[] = [
                'op' => 'expire_dirty_cache_page',
                'path' => $databasePath,
                'page' => $page,
                'reason' => 'stale_cache_generation_after_master_journal_rollback',
            ];
        }
        foreach ($admittedPages as $page) {
            $operations[] = [
                'op' => 'admit_cache_spill_page',
                'path' => $databasePath,
                'page' => $page,
                'source_id' => $sourceId,
                'reason' => 'recovered_current_source_page_admitted_for_cache_spill',
            ];
        }
        foreach ($retryRows as $row) {
            $operations[] = [
                'op' => $row['cache_seeded'] ? 'seed_retry_cache_page' : 'retry_cache_miss',
                'path' => $databasePath,
                'page' => $row['page'],
                'source_id' => $row['source_id'],
                'reason' => $row['cache_seeded'] ? 'retry_read_uses_recovered_current_source' : 'retry_read_misses_after_cache_generation_reset',
            ];
        }

        return $operations;
    }

    /**
     * @param list<array<string,mixed>> $databases
     */
    private static function recoveredDatabaseBytes(array $spillRecovery, array $databases, string $primaryDatabasePath): string
    {
        $bytes = $spillRecovery['recovery']['retry_recovery']['recovered_database_bytes'] ?? null;
        if (is_string($bytes) && $bytes !== '') {
            return $bytes;
        }
        foreach ($databases as $database) {
            if (($database['database_path'] ?? null) === $primaryDatabasePath) {
                return (string) ($database['current_database_bytes'] ?? '');
            }
        }

        throw new \InvalidArgumentException("SQLite pager rollback cache-spill database is missing: {$primaryDatabasePath}");
    }

    private static function pageImage(string $databaseBytes, int $pageNumber, int $pageSize): string
    {
        $offset = ($pageNumber - 1) * $pageSize;

        return str_pad(substr($databaseBytes, $offset, $pageSize), $pageSize, "\0");
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 56), "\0.");
    }
}
