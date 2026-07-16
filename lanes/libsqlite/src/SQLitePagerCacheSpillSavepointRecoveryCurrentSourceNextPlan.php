<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan
{
    /**
     * @param list<array{page:int,image:string,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,current_image?:string}> $cachePages
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
        string $databaseBytes,
        int $pageSize,
        string $savepointName,
        SQLiteSavepointStack $savepoints,
        array $cachePages,
        int $cacheSize,
        int $spillThreshold,
        bool $journalSynced = true,
        string $lockState = 'reserved',
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager cache-spill savepoint recovery page size must be positive');
        }
        if (strlen($databaseBytes) === 0 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager cache-spill savepoint recovery database must be page-size aligned');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill savepoint recovery savepoint name must not be empty');
        }
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager cache-spill savepoint recovery requires cache pages');
        }

        $normalized = self::normalizeCachePages($cachePages, $databaseBytes, $pageSize);
        $spill = SQLitePagerDirtyPageCacheSpillPlan::currentNext(
            (int) (strlen($databaseBytes) / $pageSize),
            $cacheSize,
            $spillThreshold,
            self::spillInputs($normalized),
            $journalSynced,
            $lockState,
            $cacheSpillEnabled,
            $maxSpillPages
        );

        $spilledPageNumbers = $spill['next']['spilled_pages'] ?? [];
        $spilledDatabaseBytes = self::applySpilledPages($databaseBytes, $normalized, $spilledPageNumbers, $pageSize);
        $rollbackPlan = $savepoints->rollbackToImagePlan($savepointName, $pageSize);
        $rolledBackBytes = $savepoints->rollbackToDatabaseImage($savepointName, $spilledDatabaseBytes, $pageSize);
        $rollbackToPlan = $savepoints->rollbackToPlan($savepointName);

        $pageSources = [];
        $restoredSpilledPages = [];
        $spillSurvivedPages = [];
        $unspilledDirtyPages = [];
        $currentSourceMismatchPages = [];
        foreach ($normalized as $cachePage) {
            $page = $cachePage['page'];
            $beforeImage = self::pageImage($databaseBytes, $page, $pageSize);
            $afterSpillImage = self::pageImage($spilledDatabaseBytes, $page, $pageSize);
            $afterRollbackImage = self::pageImage($rolledBackBytes, $page, $pageSize);
            $wasSpilled = in_array($page, $spilledPageNumbers, true);
            $restoredBySavepoint = in_array($page, $rollbackPlan['restored_page_numbers'], true)
                && $afterRollbackImage === $beforeImage;

            if (($cachePage['current_image'] ?? $beforeImage) !== $beforeImage) {
                $currentSourceMismatchPages[] = $page;
            }
            if ($wasSpilled && $restoredBySavepoint) {
                $restoredSpilledPages[] = $page;
            }
            if ($wasSpilled && !$restoredBySavepoint && $afterRollbackImage === $cachePage['image']) {
                $spillSurvivedPages[] = $page;
            }
            if (!$wasSpilled && ($cachePage['dirty'] ?? true)) {
                $unspilledDirtyPages[] = $page;
            }

            $pageSources[$page] = [
                'page' => $page,
                'was_spilled' => $wasSpilled,
                'was_pinned' => (bool) ($cachePage['pinned'] ?? false),
                'before_prefix' => self::prefix($beforeImage),
                'spilled_prefix' => self::prefix($afterSpillImage),
                'rolled_back_prefix' => self::prefix($afterRollbackImage),
                'spill_matches_cache_image' => $afterSpillImage === $cachePage['image'],
                'rollback_matches_before_image' => $afterRollbackImage === $beforeImage,
                'rollback_matches_spilled_image' => $afterRollbackImage === $cachePage['image'],
            ];
        }

        sort($restoredSpilledPages, SORT_NUMERIC);
        sort($spillSurvivedPages, SORT_NUMERIC);
        sort($unspilledDirtyPages, SORT_NUMERIC);
        sort($currentSourceMismatchPages, SORT_NUMERIC);
        ksort($pageSources, SORT_NUMERIC);

        $recovered = ($spill['status'] ?? null) === 'spilled'
            && $currentSourceMismatchPages === []
            && $spillSurvivedPages === []
            && $restoredSpilledPages !== [];

        return [
            'status' => $recovered
                ? 'pager_cache_spill_savepoint_recovery_current_source_next120'
                : 'pager_cache_spill_savepoint_recovery_blocked_next120',
            'reason' => $recovered
                ? 'rollback_to_restores_savepoint_images_after_cache_spill'
                : 'cache_spill_savepoint_recovery_missing_current_source_or_restore',
            'savepoint' => $savepointName,
            'page_size' => $pageSize,
            'current_source_verified' => $currentSourceMismatchPages === [],
            'current_source_mismatch_pages' => $currentSourceMismatchPages,
            'spill' => $spill,
            'spilled_page_numbers' => $spilledPageNumbers,
            'restored_spilled_page_numbers' => $restoredSpilledPages,
            'spill_survived_page_numbers' => $spillSurvivedPages,
            'unspilled_dirty_page_numbers' => $unspilledDirtyPages,
            'rollback_page_numbers' => $rollbackToPlan['rollback_page_numbers'],
            'rollback_restored_page_numbers' => $rollbackPlan['restored_page_numbers'],
            'rollback_missing_page_numbers' => $rollbackPlan['missing_page_numbers'],
            'page_sources' => $pageSources,
            'spilled_database_bytes' => $spilledDatabaseBytes,
            'rolled_back_database_bytes' => $rolledBackBytes,
            'operations' => array_values(array_merge(
                $spill['operations'] ?? [],
                self::recoveryOperations($spilledPageNumbers, $restoredSpilledPages, $savepointName)
            )),
            'dependencies' => array_values(array_unique(array_merge(
                $spill['dependencies'] ?? [],
                [
                    'sqlite-pager-cache-spill-savepoint-recovery-current-source-next120',
                    'sqlite-savepoint-page-image-rollback',
                    'sqlite-pager-cache-spill-current-source',
                ]
            ))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,image:string,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,current_image?:string}>
     */
    private static function normalizeCachePages(array $cachePages, string $databaseBytes, int $pageSize): array
    {
        $normalized = [];
        $seen = [];
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'] ?? null;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager cache-spill savepoint recovery pages must be one-based integers');
            }
            if (isset($seen[$page])) {
                throw new \InvalidArgumentException('SQLite pager cache-spill savepoint recovery cache pages must be unique');
            }
            $seen[$page] = true;
            $offset = ($page - 1) * $pageSize;
            if ($offset + $pageSize > strlen($databaseBytes)) {
                throw new \InvalidArgumentException("SQLite pager cache-spill savepoint recovery page {$page} is outside the database image");
            }

            $image = $cachePage['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager cache-spill savepoint recovery image for page {$page} must match page size");
            }
            if (isset($cachePage['current_image']) && (!is_string($cachePage['current_image']) || strlen($cachePage['current_image']) !== $pageSize)) {
                throw new \InvalidArgumentException("SQLite pager cache-spill savepoint recovery current image for page {$page} must match page size");
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
    private static function spillInputs(array $cachePages): array
    {
        return array_map(
            static fn (array $cachePage): array => array_filter(
                [
                    'page' => $cachePage['page'],
                    'bytes' => $cachePage['bytes'] ?? null,
                    'journaled' => $cachePage['journaled'] ?? true,
                    'dirty' => $cachePage['dirty'] ?? true,
                    'pinned' => $cachePage['pinned'] ?? false,
                ],
                static fn (mixed $value): bool => $value !== null
            ),
            $cachePages
        );
    }

    /**
     * @param list<array{page:int,image:string}> $cachePages
     * @param list<int> $spilledPageNumbers
     */
    private static function applySpilledPages(string $databaseBytes, array $cachePages, array $spilledPageNumbers, int $pageSize): string
    {
        $byPage = [];
        foreach ($cachePages as $cachePage) {
            $byPage[$cachePage['page']] = $cachePage['image'];
        }

        $spilled = $databaseBytes;
        foreach ($spilledPageNumbers as $page) {
            if (!isset($byPage[$page])) {
                continue;
            }
            $offset = ($page - 1) * $pageSize;
            $spilled = substr_replace($spilled, $byPage[$page], $offset, $pageSize);
        }

        return $spilled;
    }

    /**
     * @param list<int> $spilledPageNumbers
     * @param list<int> $restoredSpilledPages
     * @return list<array<string,mixed>>
     */
    private static function recoveryOperations(array $spilledPageNumbers, array $restoredSpilledPages, string $savepointName): array
    {
        $operations = [];
        foreach ($spilledPageNumbers as $page) {
            $operations[] = [
                'op' => 'spill_dirty_page_to_database_image',
                'page' => $page,
                'reason' => 'cache_spill_before_savepoint_rollback',
            ];
        }
        foreach ($restoredSpilledPages as $page) {
            $operations[] = [
                'op' => 'restore_spilled_page_from_savepoint_image',
                'page' => $page,
                'savepoint' => $savepointName,
                'reason' => 'rollback_to_undoes_cache_spill',
            ];
        }

        return $operations;
    }

    private static function pageImage(string $databaseBytes, int $pageNumber, int $pageSize): string
    {
        return substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize);
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 56), "\0.");
    }
}
