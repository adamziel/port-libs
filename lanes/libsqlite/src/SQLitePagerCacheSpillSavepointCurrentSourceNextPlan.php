<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerCacheSpillSavepointCurrentSourceNextPlan
{
    /**
     * @param list<array{page:int,image:string,current_image?:string,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,walFrame?:int}> $cachePages
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
        string $journalMode = 'delete',
        bool $journalSynced = true,
        string $lockState = 'reserved',
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager cache-spill savepoint current-source page size must be positive');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager cache-spill savepoint current-source database must be page-size aligned');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill savepoint current-source savepoint name must not be empty');
        }
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager cache-spill savepoint current-source requires cache pages');
        }

        $normalized = self::normalizeCachePages($cachePages, $databaseBytes, $pageSize);
        $rollbackPlan = $savepoints->rollbackToImagePlan($savepointName, $pageSize);
        $restorePages = array_flip($rollbackPlan['restored_page_numbers']);
        $admitted = [];
        $rejected = [];
        $sources = [];

        foreach ($normalized as $cachePage) {
            $page = $cachePage['page'];
            $databaseImage = self::pageImage($databaseBytes, $page, $pageSize);
            $currentImage = $cachePage['current_image'] ?? $databaseImage;
            $dirty = (bool) ($cachePage['dirty'] ?? true);
            $pinned = (bool) ($cachePage['pinned'] ?? false);
            $hasSavepointImage = isset($restorePages[$page]);
            $currentMatches = $currentImage === $databaseImage;
            $reasons = [];

            if (!$dirty) {
                $reasons[] = 'cache_page_clean';
            }
            if ($pinned) {
                $reasons[] = 'cache_page_pinned';
            }
            if (!$currentMatches) {
                $reasons[] = 'current_source_mismatch';
            }
            if (!$hasSavepointImage) {
                $reasons[] = 'missing_savepoint_before_image';
            }

            $sources[$page] = [
                'page' => $page,
                'cache_prefix' => self::prefix($cachePage['image']),
                'current_prefix' => self::prefix($currentImage),
                'database_prefix' => self::prefix($databaseImage),
                'current_matches_database' => $currentMatches,
                'has_savepoint_before_image' => $hasSavepointImage,
                'dirty' => $dirty,
                'pinned' => $pinned,
                'admitted' => $reasons === [],
                'rejected_reasons' => $reasons,
            ];

            if ($reasons === []) {
                $admitted[] = $cachePage;
            } else {
                $rejected[$page] = $reasons;
            }
        }

        ksort($sources, SORT_NUMERIC);
        ksort($rejected, SORT_NUMERIC);

        $spill = SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext107(
            (int) (strlen($databaseBytes) / $pageSize),
            $cacheSize,
            $spillThreshold,
            self::spillInputs($admitted),
            $journalMode,
            $journalSynced,
            $lockState,
            $cacheSpillEnabled,
            $maxSpillPages
        );

        $spilledPages = $spill['next']['spilled_pages'] ?? [];
        $admittedPages = array_column($admitted, 'page');
        sort($admittedPages, SORT_NUMERIC);
        $rejectedPages = array_keys($rejected);
        sort($rejectedPages, SORT_NUMERIC);

        return [
            'status' => $spilledPages !== []
                ? 'pager_cache_spill_savepoint_current_source_next137'
                : 'pager_cache_spill_savepoint_current_source_deferred_next137',
            'reason' => $spilledPages !== []
                ? 'cache_spill_uses_current_source_savepoint_before_images'
                : 'cache_spill_deferred_after_savepoint_current_source_filter',
            'savepoint' => $savepointName,
            'page_size' => $pageSize,
            'journal_mode' => strtolower(trim($journalMode)),
            'admitted_page_numbers' => $admittedPages,
            'rejected_page_numbers' => $rejectedPages,
            'rejected_pages' => $rejected,
            'source_checks' => $sources,
            'savepoint_restore_page_numbers' => $rollbackPlan['restored_page_numbers'],
            'savepoint_missing_page_numbers' => $rollbackPlan['missing_page_numbers'],
            'spill' => $spill,
            'spilled_page_numbers' => $spilledPages,
            'wal_frame_pages' => $spill['next']['wal_frame_pages'] ?? [],
            'operations' => array_values(array_merge(
                self::filterOperations($admittedPages, $rejected),
                $spill['operations'] ?? []
            )),
            'dependencies' => array_values(array_unique(array_merge(
                $spill['dependencies'] ?? [],
                [
                    'sqlite-pager-cache-spill-savepoint-current-source-next137',
                    'sqlite-pager-cache-spill-journalmode-current-source-next107',
                    'sqlite-savepoint-page-image-rollback',
                ]
            ))),
        ];
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,image:string,current_image?:string,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,walFrame?:int}>
     */
    private static function normalizeCachePages(array $cachePages, string $databaseBytes, int $pageSize): array
    {
        $normalized = [];
        $seen = [];
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'] ?? null;
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException('SQLite pager cache-spill savepoint current-source pages must be one-based integers');
            }
            if (isset($seen[$page])) {
                throw new \InvalidArgumentException('SQLite pager cache-spill savepoint current-source cache pages must be unique');
            }
            $seen[$page] = true;
            if ((($page - 1) * $pageSize) + $pageSize > strlen($databaseBytes)) {
                throw new \InvalidArgumentException("SQLite pager cache-spill savepoint current-source page {$page} is outside the database image");
            }
            $image = $cachePage['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager cache-spill savepoint current-source image for page {$page} must match page size");
            }
            if (isset($cachePage['current_image']) && (!is_string($cachePage['current_image']) || strlen($cachePage['current_image']) !== $pageSize)) {
                throw new \InvalidArgumentException("SQLite pager cache-spill savepoint current-source current image for page {$page} must match page size");
            }
            $cachePage['image'] = $image;
            $normalized[] = $cachePage;
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,walFrame?:int}>
     */
    private static function spillInputs(array $cachePages): array
    {
        return array_map(
            static fn (array $cachePage): array => array_filter(
                [
                    'page' => $cachePage['page'],
                    'bytes' => $cachePage['bytes'] ?? null,
                    'journaled' => $cachePage['journaled'] ?? null,
                    'dirty' => $cachePage['dirty'] ?? null,
                    'pinned' => $cachePage['pinned'] ?? null,
                    'walFrame' => $cachePage['walFrame'] ?? null,
                ],
                static fn (mixed $value): bool => $value !== null
            ),
            $cachePages
        );
    }

    /**
     * @param list<int> $admittedPages
     * @param array<int,list<string>> $rejectedPages
     * @return list<array<string,mixed>>
     */
    private static function filterOperations(array $admittedPages, array $rejectedPages): array
    {
        $operations = [];
        foreach ($admittedPages as $page) {
            $operations[] = [
                'op' => 'admit_savepoint_cache_spill_page',
                'page' => $page,
                'reason' => 'current_source_matches_and_savepoint_before_image_exists',
            ];
        }
        foreach ($rejectedPages as $page => $reasons) {
            $operations[] = [
                'op' => 'defer_savepoint_cache_spill_page',
                'page' => $page,
                'reasons' => $reasons,
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
