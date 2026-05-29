<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan
{
    /**
     * @param list<array{database_path:string,current_database_bytes:string,current_journal_bytes:string,stale_database_bytes?:string,stale_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @param array<int,string> $retryPageWrites
     * @param list<array{page:int,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,stale_image?:string}> $cachePages
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
        string $journalMode = 'delete',
        bool $journalSynced = true,
        string $lockState = 'reserved',
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null,
    ): array {
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal cache-spill savepoint current-source requires cache pages');
        }

        $recovery = SQLitePagerMasterJournalSavepointCurrentSourceNextPlan::currentSourceNext(
            $masterJournalPath,
            $masterJournalBytes,
            $databases,
            $pageSize,
            $primaryDatabasePath,
            $savepointName,
            $savepoints,
            $retryPageWrites
        );

        $cacheInput = self::normalizeCachePages($cachePages, $pageSize);
        $pageCount = self::pageCount((string) (($recovery['retry_recovery']['recovered_database_bytes'] ?? '') ?: self::databaseBytes($databases, $primaryDatabasePath)), $pageSize, $retryPageWrites, $cacheInput);
        $spill = SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext107(
            $pageCount,
            $cacheSize,
            $spillThreshold,
            self::cachePageInputs($cacheInput),
            $journalMode,
            $journalSynced,
            $lockState,
            $cacheSpillEnabled,
            $maxSpillPages
        );

        $currentBytes = (string) (($recovery['retry_recovery']['recovered_database_bytes'] ?? '') ?: self::databaseBytes($databases, $primaryDatabasePath));
        $rollbackBytes = is_array($recovery['payloads'] ?? null)
            ? (string) ($recovery['payloads'][$primaryDatabasePath . '#master-savepoint-rollback-preview-next108'] ?? $currentBytes)
            : $currentBytes;

        $spilledPages = $spill['next']['spilled_pages'] ?? [];
        $spillSources = [];
        $staleRejectedPages = [];
        $sourceMismatches = [];
        foreach ($cacheInput as $cachePage) {
            $page = $cachePage['page'];
            if (!in_array($page, $spilledPages, true)) {
                if (isset($cachePage['stale_image'])) {
                    $staleRejectedPages[] = $page;
                }
                continue;
            }

            $currentImage = self::pageImage($currentBytes, $page, $pageSize);
            $rollbackImage = self::pageImage($rollbackBytes, $page, $pageSize);
            $staleImage = $cachePage['stale_image'] ?? null;
            $usesStale = is_string($staleImage) && $staleImage === $cachePage['image'];
            $matchesCurrent = $cachePage['image'] === $currentImage || $cachePage['image'] === $rollbackImage;
            if ($usesStale || !$matchesCurrent) {
                $sourceMismatches[] = $page;
            }
            $spillSources[$page] = [
                'page' => $page,
                'prefix' => self::prefix($cachePage['image']),
                'matches_recovered_current' => $cachePage['image'] === $currentImage,
                'matches_rollback_preview' => $cachePage['image'] === $rollbackImage,
                'uses_stale_dirty_cache' => $usesStale,
            ];
        }

        $currentSourceVerified = ($recovery['current_source_verified'] ?? false) === true && $sourceMismatches === [];

        return [
            'status' => $currentSourceVerified && ($spill['status'] ?? null) === 'spilled'
                ? 'master_journal_cache_spill_savepoint_current_source_next114'
                : 'master_journal_cache_spill_savepoint_current_source_blocked_next114',
            'reason' => $currentSourceVerified
                ? 'cache_spill_pages_use_master_journal_savepoint_current_source'
                : 'cache_spill_source_mismatch_after_master_journal_savepoint',
            'primary_database_path' => $primaryDatabasePath,
            'savepoint' => $savepointName,
            'journal_mode' => strtolower(trim($journalMode)),
            'current_source_verified' => $currentSourceVerified,
            'recovery' => $recovery,
            'spill' => $spill,
            'spilled_page_sources' => $spillSources,
            'spilled_page_count' => count($spilledPages),
            'stale_rejected_pages' => $staleRejectedPages,
            'source_mismatch_pages' => $sourceMismatches,
            'rollback_preview_bytes' => strlen($rollbackBytes),
            'operations' => array_values(array_merge(
                $recovery['operations'] ?? [],
                self::spillOperations($spill['operations'] ?? [], $primaryDatabasePath)
            )),
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'] ?? [],
                $spill['dependencies'] ?? [],
                ['sqlite-pager-master-journal-cache-spill-savepoint-current-source-next114']
            ))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,image:string,stale_image?:string}>
     */
    private static function normalizeCachePages(array $cachePages, int $pageSize): array
    {
        $normalized = [];
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'] ?? null;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal cache-spill pages must be one-based integers');
            }
            $image = $cachePage['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager master-journal cache-spill page images must match page size');
            }
            if (isset($cachePage['stale_image']) && (!is_string($cachePage['stale_image']) || strlen($cachePage['stale_image']) !== $pageSize)) {
                throw new \InvalidArgumentException('SQLite pager master-journal cache-spill stale page images must match page size');
            }
            $cachePage['image'] = $image;
            $normalized[] = $cachePage;
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool}>
     */
    private static function cachePageInputs(array $cachePages): array
    {
        return array_map(
            static fn (array $cachePage): array => array_filter(
                [
                    'page' => $cachePage['page'],
                    'bytes' => $cachePage['bytes'] ?? null,
                    'journaled' => $cachePage['journaled'] ?? null,
                    'dirty' => $cachePage['dirty'] ?? null,
                    'pinned' => $cachePage['pinned'] ?? null,
                ],
                static fn (mixed $value): bool => $value !== null
            ),
            $cachePages
        );
    }

    /**
     * @param array<int,string> $retryPageWrites
     * @param list<array{page:int}> $cachePages
     */
    private static function pageCount(string $databaseBytes, int $pageSize, array $retryPageWrites, array $cachePages): int
    {
        $pageCount = max(1, (int) ceil(strlen($databaseBytes) / $pageSize));
        foreach (array_keys($retryPageWrites) as $page) {
            if (is_int($page)) {
                $pageCount = max($pageCount, $page);
            }
        }
        foreach ($cachePages as $cachePage) {
            $pageCount = max($pageCount, $cachePage['page']);
        }

        return $pageCount;
    }

    /**
     * @param list<array<string,mixed>> $operations
     * @return list<array<string,mixed>>
     */
    private static function spillOperations(array $operations, string $databasePath): array
    {
        foreach ($operations as &$operation) {
            $operation['path'] = $operation['path'] ?? $databasePath;
            $operation['reason'] = ($operation['reason'] ?? 'cache_spill') . '_after_master_journal_savepoint_current_source';
        }
        unset($operation);

        return $operations;
    }

    /**
     * @param list<array<string,mixed>> $databases
     */
    private static function databaseBytes(array $databases, string $databasePath): string
    {
        foreach ($databases as $database) {
            if (($database['database_path'] ?? null) === $databasePath) {
                return (string) ($database['current_database_bytes'] ?? '');
            }
        }

        throw new \InvalidArgumentException("SQLite pager master-journal cache-spill database is missing: {$databasePath}");
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
