<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointCacheSpillHotJournalCurrentSourceNext151Plan
{
    /**
     * @param array<int,string> $hotJournalBeforeImages
     * @param list<array{page:int,image:string,current_image?:string,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,walFrame?:int}> $cachePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        array $hotJournalBeforeImages,
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
        bool $deleteHotJournalAfterRecovery = true,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint cache-spill hot-journal current-source next151 requires a database path');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint cache-spill hot-journal current-source next151 page size must be a power of two at least 512');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint cache-spill hot-journal current-source next151 database bytes must be page-size aligned');
        }
        if ($hotJournalBeforeImages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint cache-spill hot-journal current-source next151 requires hot-journal pages');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint cache-spill hot-journal current-source next151 requires a savepoint name');
        }
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint cache-spill hot-journal current-source next151 requires cache pages');
        }

        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        $hotJournalBeforeImages = self::normalizeImages($hotJournalBeforeImages, $pageSize, $pageCount, 'hot-journal');
        $recoveredDatabaseBytes = self::applyPages($databaseBytes, $hotJournalBeforeImages, $pageSize);
        $cachePages = self::normalizeCachePages($cachePages, $recoveredDatabaseBytes, $pageSize);
        $rollbackPlan = $savepoints->rollbackToImagePlan($savepointName, $pageSize);
        $savepointImages = self::savepointImagesByPage($savepoints->rollbackToPageImages($savepointName));

        $admitted = [];
        $rejected = [];
        $sourceChecks = [];
        $operations = self::hotJournalOperations($databasePath, array_keys($hotJournalBeforeImages), $deleteHotJournalAfterRecovery);

        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'];
            $recoveredImage = self::pageImage($recoveredDatabaseBytes, $page, $pageSize);
            $currentImage = $cachePage['current_image'] ?? $recoveredImage;
            $savepointImage = $savepointImages[$page] ?? null;
            $dirty = (bool) ($cachePage['dirty'] ?? true);
            $journaled = (bool) ($cachePage['journaled'] ?? true);
            $pinned = (bool) ($cachePage['pinned'] ?? false);
            $reasons = [];

            if (!$dirty) {
                $reasons[] = 'cache_page_clean';
            }
            if (!$journaled) {
                $reasons[] = 'missing_rollback_source';
            }
            if ($pinned) {
                $reasons[] = 'cache_page_pinned';
            }
            if ($currentImage !== $recoveredImage) {
                $reasons[] = 'current_source_mismatch_after_hot_journal_recovery';
            }
            if ($savepointImage === null) {
                $reasons[] = 'missing_savepoint_before_image';
            } elseif ($savepointImage !== $recoveredImage) {
                $reasons[] = 'stale_savepoint_before_image_before_hot_journal_recovery';
            }

            $sourceChecks[$page] = [
                'page' => $page,
                'cache_prefix' => self::prefix($cachePage['image']),
                'current_prefix' => self::prefix($currentImage),
                'recovered_prefix' => self::prefix($recoveredImage),
                'savepoint_prefix' => $savepointImage === null ? null : self::prefix($savepointImage),
                'hot_journal_page' => isset($hotJournalBeforeImages[$page]),
                'dirty' => $dirty,
                'journaled' => $journaled,
                'pinned' => $pinned,
                'current_matches_recovered' => $currentImage === $recoveredImage,
                'savepoint_matches_recovered' => $savepointImage !== null && $savepointImage === $recoveredImage,
                'admitted' => $reasons === [],
                'rejected_reasons' => $reasons,
            ];

            if ($reasons === []) {
                $admitted[] = [
                    'page' => $page,
                    'image' => $cachePage['image'],
                    'current_image' => $currentImage,
                    'bytes' => $cachePage['bytes'] ?? null,
                    'journaled' => true,
                    'dirty' => true,
                    'pinned' => false,
                    'walFrame' => $cachePage['walFrame'] ?? null,
                ];
                $operations[] = [
                    'op' => 'admit_hot_journal_savepoint_cache_spill_page',
                    'page' => $page,
                    'reason' => 'savepoint_before_image_matches_recovered_current_source',
                ];
            } else {
                $rejected[$page] = $reasons;
                $operations[] = [
                    'op' => 'defer_hot_journal_savepoint_cache_spill_page',
                    'page' => $page,
                    'reasons' => $reasons,
                ];
            }
        }

        ksort($sourceChecks, SORT_NUMERIC);
        ksort($rejected, SORT_NUMERIC);

        $spill = SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext107(
            $pageCount,
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
        $spilledDatabaseBytes = $spilledPages === []
            ? $recoveredDatabaseBytes
            : self::applySpilledPages($recoveredDatabaseBytes, $admitted, $spilledPages, $pageSize);
        $hotPages = array_keys($hotJournalBeforeImages);
        sort($hotPages, SORT_NUMERIC);
        $admittedPages = array_column($admitted, 'page');
        sort($admittedPages, SORT_NUMERIC);
        $rejectedPages = array_keys($rejected);
        sort($rejectedPages, SORT_NUMERIC);

        return [
            'status' => $spilledPages === []
                ? 'pager_savepoint_cache_spill_hot_journal_current_source_deferred_next151'
                : 'pager_savepoint_cache_spill_hot_journal_current_source_next151',
            'reason' => $spilledPages === []
                ? 'cache_spill_deferred_until_hot_journal_savepoint_source_is_rejournaled'
                : 'cache_spill_uses_savepoint_before_images_rebased_to_hot_journal_current_source',
            'database_path' => $databasePath,
            'hot_journal_path' => $databasePath . '-journal',
            'savepoint' => $savepointName,
            'page_size' => $pageSize,
            'page_count' => $pageCount,
            'journal_mode' => strtolower(trim($journalMode)),
            'delete_hot_journal_after_recovery' => $deleteHotJournalAfterRecovery,
            'hot_journal_page_numbers' => $hotPages,
            'savepoint_restore_page_numbers' => $rollbackPlan['restored_page_numbers'],
            'savepoint_missing_page_numbers' => $rollbackPlan['missing_page_numbers'],
            'admitted_page_numbers' => $admittedPages,
            'rejected_page_numbers' => $rejectedPages,
            'rejected_pages' => $rejected,
            'source_checks' => array_values($sourceChecks),
            'source_checks_by_page' => $sourceChecks,
            'spill' => $spill,
            'spilled_page_numbers' => $spilledPages,
            'wal_frame_pages' => $spill['next']['wal_frame_pages'] ?? [],
            'hot_journal_recovered_database_bytes' => $recoveredDatabaseBytes,
            'spilled_database_bytes' => $spilledDatabaseBytes,
            'operations' => array_values(array_merge($operations, $spill['operations'] ?? [])),
            'source_digest' => hash('sha256', $databasePath . $savepointName . implode('', $hotJournalBeforeImages) . implode(',', $spilledPages)),
            'dependencies' => array_values(array_unique(array_merge(
                $spill['dependencies'] ?? [],
                [
                    'sqlite-pager-savepoint-cache-spill-hot-journal-current-source-next151',
                    'sqlite-pager-cache-spill-journalmode-current-source-next107',
                    'sqlite-savepoint-page-image-rollback',
                    'sqlite-hot-journal-recovery-before-cache-spill',
                ]
            ))),
        ];
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private static function normalizeImages(array $images, int $pageSize, int $pageCount, string $label): array
    {
        $normalized = [];
        foreach ($images as $page => $image) {
            if (!is_int($page) || $page < 1 || $page > $pageCount) {
                throw new \InvalidArgumentException("SQLite pager savepoint cache-spill hot-journal current-source next151 {$label} pages must be one-based pages inside the database image");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint cache-spill hot-journal current-source next151 {$label} page {$page} image must match page size");
            }
            $normalized[$page] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,image:string,current_image?:string,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,walFrame?:int}>
     */
    private static function normalizeCachePages(array $cachePages, string $databaseBytes, int $pageSize): array
    {
        $normalized = [];
        $seen = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'] ?? null;
            if (!is_int($page) || $page < 1 || $page > $pageCount) {
                throw new \InvalidArgumentException('SQLite pager savepoint cache-spill hot-journal current-source next151 cache pages must be one-based pages inside the database image');
            }
            if (isset($seen[$page])) {
                throw new \InvalidArgumentException('SQLite pager savepoint cache-spill hot-journal current-source next151 cache pages must be unique');
            }
            $seen[$page] = true;
            $image = $cachePage['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint cache-spill hot-journal current-source next151 cache page {$page} image must match page size");
            }
            if (isset($cachePage['current_image']) && (!is_string($cachePage['current_image']) || strlen($cachePage['current_image']) !== $pageSize)) {
                throw new \InvalidArgumentException("SQLite pager savepoint cache-spill hot-journal current-source next151 cache page {$page} current image must match page size");
            }
            $bytes = $cachePage['bytes'] ?? null;
            if ($bytes !== null && (!is_int($bytes) || $bytes < 0)) {
                throw new \InvalidArgumentException("SQLite pager savepoint cache-spill hot-journal current-source next151 cache page {$page} bytes must be non-negative");
            }
            $walFrame = $cachePage['walFrame'] ?? null;
            if ($walFrame !== null && (!is_int($walFrame) || $walFrame < 1)) {
                throw new \InvalidArgumentException("SQLite pager savepoint cache-spill hot-journal current-source next151 cache page {$page} WAL frame must be positive");
            }
            $cachePage['image'] = $image;
            $normalized[] = $cachePage;
        }

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,frame:string}> $restoredPages
     * @return array<int,string>
     */
    private static function savepointImagesByPage(array $restoredPages): array
    {
        $images = [];
        foreach ($restoredPages as $pageNumber => $page) {
            $images[$pageNumber] = $page['image'];
        }

        return $images;
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

    /** @param array<int,string> $pages */
    private static function applyPages(string $databaseBytes, array $pages, int $pageSize): string
    {
        foreach ($pages as $page => $image) {
            $databaseBytes = substr_replace($databaseBytes, $image, ($page - 1) * $pageSize, $pageSize);
        }

        return $databaseBytes;
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
        foreach ($spilledPageNumbers as $page) {
            $databaseBytes = substr_replace($databaseBytes, $byPage[$page], ($page - 1) * $pageSize, $pageSize);
        }

        return $databaseBytes;
    }

    /** @param list<int> $pages */
    private static function hotJournalOperations(string $databasePath, array $pages, bool $deleteHotJournal): array
    {
        sort($pages, SORT_NUMERIC);
        $operations = [[
            'op' => 'open_hot_journal_for_savepoint_cache_spill',
            'path' => $databasePath . '-journal',
            'reason' => 'recover_hot_journal_before_validating_savepoint_spill_sources',
        ]];
        foreach ($pages as $page) {
            $operations[] = [
                'op' => 'restore_hot_journal_page_for_savepoint_spill',
                'path' => $databasePath,
                'page' => $page,
                'reason' => 'hot_journal_before_image_becomes_current_source_for_savepoint_spill',
            ];
        }
        if ($deleteHotJournal) {
            $operations[] = [
                'op' => 'delete_hot_journal_after_savepoint_spill_recovery',
                'path' => $databasePath . '-journal',
                'reason' => 'hot_journal_recovery_complete_before_cache_spill',
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
