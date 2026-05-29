<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalCacheSpillCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $hotJournalBeforeImages
     * @param list<array{page:int,image:string,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,current_image?:string}> $cachePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $staleDatabaseBytes,
        int $pageSize,
        array $hotJournalBeforeImages,
        array $cachePages,
        int $cacheSize,
        int $spillThreshold,
        bool $journalSynced = true,
        string $lockState = 'reserved',
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null,
        bool $deleteHotJournalAfterRecovery = true,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill current-source requires a database path');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill current-source page size must be a power of two at least 512');
        }
        if ($staleDatabaseBytes === '' || strlen($staleDatabaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill current-source database bytes must be page-size aligned');
        }
        if ($hotJournalBeforeImages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill current-source requires hot-journal before images');
        }
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill current-source requires cache pages');
        }

        $pageCount = intdiv(strlen($staleDatabaseBytes), $pageSize);
        $hotJournalBeforeImages = self::normalizePageImages($hotJournalBeforeImages, $pageSize, $pageCount, 'hot-journal');
        $cachePages = self::normalizeCachePages($cachePages, $staleDatabaseBytes, $pageSize);
        $currentSourceBytes = self::applyPages($staleDatabaseBytes, $hotJournalBeforeImages, $pageSize);

        $currentSourceMismatchPages = [];
        $staleSpillSourcePages = [];
        $cacheSource = [];
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'];
            $currentImage = self::pageImage($currentSourceBytes, $page, $pageSize);
            $staleImage = self::pageImage($staleDatabaseBytes, $page, $pageSize);
            if (($cachePage['current_image'] ?? $currentImage) !== $currentImage) {
                $currentSourceMismatchPages[] = $page;
            }
            if ($staleImage !== $currentImage && $cachePage['image'] === $staleImage) {
                $staleSpillSourcePages[] = $page;
            }
            $cacheSource[$page] = [
                'page' => $page,
                'stale_prefix' => self::prefix($staleImage),
                'current_source_prefix' => self::prefix($currentImage),
                'cache_prefix' => self::prefix($cachePage['image']),
                'was_hot_journal_recovered' => isset($hotJournalBeforeImages[$page]),
                'cache_matches_current_source' => $cachePage['image'] === $currentImage,
                'cache_matches_stale_source' => $cachePage['image'] === $staleImage,
                'current_image_verified' => !in_array($page, $currentSourceMismatchPages, true),
            ];
        }
        sort($currentSourceMismatchPages, SORT_NUMERIC);
        sort($staleSpillSourcePages, SORT_NUMERIC);

        $spill = SQLitePagerDirtyPageCacheSpillPlan::currentNext(
            $pageCount,
            $cacheSize,
            $spillThreshold,
            self::spillInputs($cachePages),
            $journalSynced,
            $lockState,
            $cacheSpillEnabled && $currentSourceMismatchPages === [] && $staleSpillSourcePages === [],
            $maxSpillPages
        );
        if ($staleSpillSourcePages !== [] && !in_array('stale_cache_image_before_hot_journal_recovery', $spill['blocked_reasons'], true)) {
            $spill['blocked_reasons'][] = 'stale_cache_image_before_hot_journal_recovery';
        }
        if ($currentSourceMismatchPages !== [] && !in_array('current_source_mismatch_after_hot_journal_recovery', $spill['blocked_reasons'], true)) {
            $spill['blocked_reasons'][] = 'current_source_mismatch_after_hot_journal_recovery';
        }

        $spilledPageNumbers = $spill['next']['spilled_pages'] ?? [];
        $spilledDatabaseBytes = $currentSourceMismatchPages === [] && $staleSpillSourcePages === []
            ? self::applySpilledPages($currentSourceBytes, $cachePages, $spilledPageNumbers, $pageSize)
            : $currentSourceBytes;

        $hotRecoveredPages = array_keys($hotJournalBeforeImages);
        sort($hotRecoveredPages, SORT_NUMERIC);
        ksort($cacheSource, SORT_NUMERIC);

        $recovered = $currentSourceMismatchPages === []
            && $staleSpillSourcePages === []
            && ($spill['status'] ?? null) === 'spilled'
            && $spilledPageNumbers !== [];

        return [
            'status' => $recovered
                ? 'pager_hot_journal_cache_spill_current_source_next127'
                : 'pager_hot_journal_cache_spill_current_source_blocked_next127',
            'reason' => $recovered
                ? 'hot_journal_recovered_before_cache_spill_current_source'
                : 'cache_spill_blocked_until_hot_journal_current_source_is_verified',
            'database_path' => $databasePath,
            'hot_journal_path' => $databasePath . '-journal',
            'page_size' => $pageSize,
            'page_count' => $pageCount,
            'delete_hot_journal_after_recovery' => $deleteHotJournalAfterRecovery,
            'hot_journal_recovered_page_numbers' => $hotRecoveredPages,
            'current_source_verified' => $currentSourceMismatchPages === [],
            'current_source_mismatch_pages' => $currentSourceMismatchPages,
            'stale_spill_source_pages' => $staleSpillSourcePages,
            'spill' => $spill,
            'spilled_page_numbers' => $spilledPageNumbers,
            'hot_journal_recovered_database_bytes' => $currentSourceBytes,
            'spilled_database_bytes' => $spilledDatabaseBytes,
            'cache_page_sources' => $cacheSource,
            'operations' => array_values(array_merge(
                self::hotJournalOperations($databasePath, $hotRecoveredPages, $deleteHotJournalAfterRecovery),
                $spill['operations'] ?? []
            )),
            'dependencies' => array_values(array_unique(array_merge(
                $spill['dependencies'] ?? [],
                [
                    'sqlite-pager-hot-journal-cache-spill-current-source-next127',
                    'sqlite-hot-journal-recovery-before-cache-spill',
                    'sqlite-pager-cache-spill-current-source',
                ]
            ))),
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePageImages(array $pages, int $pageSize, int $pageCount, string $label): array
    {
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $page => $image) {
            if (!is_int($page) || $page < 1 || $page > $pageCount) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill {$label} pages must be one-based pages inside the database image");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill {$label} page {$page} image must match page size");
            }
            $normalized[$page] = $image;
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,image:string,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,current_image?:string}>
     */
    private static function normalizeCachePages(array $cachePages, string $databaseBytes, int $pageSize): array
    {
        $normalized = [];
        $seen = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'] ?? null;
            if (!is_int($page) || $page < 1 || $page > $pageCount) {
                throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill cache pages must be one-based pages inside the database image');
            }
            if (isset($seen[$page])) {
                throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill cache pages must be unique');
            }
            $seen[$page] = true;
            $image = $cachePage['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill cache image for page {$page} must match page size");
            }
            if (isset($cachePage['current_image']) && (!is_string($cachePage['current_image']) || strlen($cachePage['current_image']) !== $pageSize)) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill current image for page {$page} must match page size");
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

    /** @return list<array<string,mixed>> */
    private static function hotJournalOperations(string $databasePath, array $pages, bool $deleteHotJournal): array
    {
        $operations = [[
            'op' => 'open_hot_journal',
            'path' => $databasePath . '-journal',
            'reason' => 'recover_hot_journal_before_cache_spill_source_selection',
        ]];
        foreach ($pages as $page) {
            $operations[] = [
                'op' => 'restore_hot_journal_page',
                'path' => $databasePath,
                'page' => $page,
                'reason' => 'hot_journal_before_image_becomes_current_source_for_spill',
            ];
        }
        if ($deleteHotJournal) {
            $operations[] = [
                'op' => 'delete_hot_journal',
                'path' => $databasePath . '-journal',
                'reason' => 'hot_journal_recovery_complete_before_cache_spill',
            ];
        }

        return $operations;
    }

    private static function pageImage(string $databaseBytes, int $page, int $pageSize): string
    {
        return substr($databaseBytes, ($page - 1) * $pageSize, $pageSize);
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 72), ".\0 ");
    }
}
