<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerCacheSpillWalSavepointCurrentSourceNextPlan
{
    /**
     * @param array<int,array{page:int,image:string,commit_frame?:bool}> $walFrames
     * @param list<array{page:int,image:string,current_image?:string,bytes?:int,dirty?:bool,pinned?:bool,journaled?:bool,walFrame?:int}> $cachePages
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $savepointName,
        SQLiteSavepointStack $savepoints,
        array $walFrames,
        array $cachePages,
        int $cacheSize,
        int $spillThreshold,
        bool $walSynced = true,
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 requires a database path');
        }
        if ($databaseBytes === '' || $pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 database bytes must be page-size aligned');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 savepoint name must not be empty');
        }
        if ($walFrames === []) {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 requires WAL frames');
        }
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 requires cache pages');
        }

        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        $rollbackPlan = $savepoints->walRollbackToPlan($savepointName);
        $imagePlan = $savepoints->rollbackToImagePlan($savepointName, $pageSize);
        $rollbackToFrame = $rollbackPlan['rollback_to_frame'];
        $savepointPages = array_flip($imagePlan['restored_page_numbers']);
        $walFrames = self::normalizeWalFrames($walFrames, $pageCount, $pageSize);

        $currentSources = self::databaseSources($databaseBytes, $pageSize);
        $discardedWalPages = [];
        foreach ($walFrames as $frameIndex => $frame) {
            if ($frameIndex > $rollbackToFrame) {
                $discardedWalPages[$frame['page']] = true;
                continue;
            }
            $currentSources[$frame['page']] = [
                'image' => $frame['image'],
                'source' => $frame['commit_frame'] ? 'retained-commit-wal-frame' : 'retained-wal-frame',
                'frame' => $frameIndex,
            ];
        }

        $cachePages = self::normalizeCachePages($cachePages, $pageCount, $pageSize);
        $admitted = [];
        $rejected = [];
        $sourceRows = [];

        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'];
            $source = $currentSources[$page];
            $currentImage = $cachePage['current_image'] ?? $source['image'];
            $dirty = (bool) ($cachePage['dirty'] ?? true);
            $pinned = (bool) ($cachePage['pinned'] ?? false);
            $journaled = (bool) ($cachePage['journaled'] ?? true);
            $walFrame = $cachePage['walFrame'] ?? null;
            $reasons = [];

            if (!$dirty) {
                $reasons[] = 'cache_page_clean';
            }
            if ($pinned) {
                $reasons[] = 'cache_page_pinned';
            }
            if (!$journaled) {
                $reasons[] = 'cache_page_not_journaled';
            }
            if (!isset($savepointPages[$page])) {
                $reasons[] = 'missing_savepoint_before_image';
            }
            if ($currentImage !== $source['image']) {
                $reasons[] = 'wal_savepoint_current_source_mismatch';
            }
            if (is_int($walFrame) && $walFrame > $rollbackToFrame) {
                $reasons[] = 'cache_page_from_discarded_wal_savepoint_frame';
            }
            if (isset($discardedWalPages[$page]) && $cachePage['image'] === ($walFrames[$walFrame]['image'] ?? null)) {
                $reasons[] = 'cache_image_matches_discarded_wal_tail';
            }

            $sourceRows[$page] = [
                'page' => $page,
                'cache_prefix' => self::prefix($cachePage['image']),
                'current_prefix' => self::prefix($source['image']),
                'current_source' => $source['source'],
                'current_frame' => $source['frame'],
                'cache_wal_frame' => $walFrame,
                'dirty' => $dirty,
                'pinned' => $pinned,
                'journaled' => $journaled,
                'has_savepoint_before_image' => isset($savepointPages[$page]),
                'current_image_verified' => $currentImage === $source['image'],
                'from_discarded_wal_frame' => is_int($walFrame) && $walFrame > $rollbackToFrame,
                'admitted' => $reasons === [],
                'rejected_reasons' => $reasons,
            ];

            if ($reasons === []) {
                $admitted[] = $cachePage;
            } else {
                $rejected[$page] = $reasons;
            }
        }

        ksort($sourceRows, SORT_NUMERIC);
        ksort($rejected, SORT_NUMERIC);

        $spill = SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(
            $pageCount,
            $cacheSize,
            $spillThreshold,
            self::spillInputs($admitted),
            'wal',
            $walSynced,
            'exclusive',
            $cacheSpillEnabled,
            $maxSpillPages
        );

        $spilledPages = $spill['next']['spilled_pages'] ?? [];
        $admittedPages = array_column($admitted, 'page');
        sort($admittedPages, SORT_NUMERIC);
        $rejectedPages = array_keys($rejected);
        sort($rejectedPages, SORT_NUMERIC);
        $nextWalFrame = $rollbackToFrame + 1;
        $appendFrames = [];
        foreach ($spilledPages as $offset => $page) {
            $appendFrames[] = [
                'frame_index' => $nextWalFrame + $offset,
                'page' => $page,
                'source' => 'cache-spill-after-rollback-to-savepoint',
            ];
        }

        return [
            'status' => $spilledPages !== []
                ? 'pager_cache_spill_wal_savepoint_current_source_next143'
                : 'pager_cache_spill_wal_savepoint_current_source_deferred_next143',
            'reason' => $spilledPages !== []
                ? 'wal_cache_spill_after_savepoint_rollback_uses_verified_current_source'
                : 'wal_cache_spill_deferred_until_savepoint_current_source_is_verified',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'savepoint' => $savepointName,
            'page_size' => $pageSize,
            'page_count' => $pageCount,
            'rollback_to_frame' => $rollbackToFrame,
            'discarded_wal_frames' => array_column($rollbackPlan['discarded_wal_frames'], 'frame_index'),
            'discarded_wal_pages' => $rollbackPlan['discarded_page_numbers'],
            'savepoint_restore_page_numbers' => $imagePlan['restored_page_numbers'],
            'admitted_page_numbers' => $admittedPages,
            'rejected_page_numbers' => $rejectedPages,
            'rejected_pages' => $rejected,
            'source_checks' => $sourceRows,
            'spill' => $spill,
            'spilled_page_numbers' => $spilledPages,
            'next_wal_frame_start' => $nextWalFrame,
            'appended_wal_frames' => $appendFrames,
            'operations' => array_values(array_merge(
                [[
                    'op' => 'rollback_wal_to_savepoint_before_cache_spill',
                    'savepoint' => $savepointName,
                    'rollback_to_frame' => $rollbackToFrame,
                    'discarded_frames' => array_column($rollbackPlan['discarded_wal_frames'], 'frame_index'),
                    'reason' => 'savepoint_rollback_discards_newer_wal_frames_before_cache_spill',
                ]],
                self::filterOperations($admittedPages, $rejected),
                $spill['operations'] ?? []
            )),
            'dependencies' => array_values(array_unique(array_merge(
                $spill['dependencies'] ?? [],
                [
                    'sqlite-pager-cache-spill-wal-savepoint-current-source-next143',
                    'sqlite-pager-cache-spill-savepoint-current-source-next137',
                    'sqlite-wal-savepoint-byte-truncation',
                    'sqlite-pager-cache-spill-journalmode-current-source-next107',
                ]
            ))),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $walFrames
     * @return array<int,array{page:int,image:string,commit_frame:bool}>
     */
    private static function normalizeWalFrames(array $walFrames, int $pageCount, int $pageSize): array
    {
        $normalized = [];
        foreach ($walFrames as $frameIndex => $frame) {
            if (!is_int($frameIndex) || $frameIndex < 1) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 WAL frame indexes must be one-based');
            }
            $page = $frame['page'] ?? null;
            if (!is_int($page) || $page < 1 || $page > $pageCount) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 WAL frame page is outside the database image');
            }
            $image = $frame['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 WAL frame image must match page size');
            }
            $normalized[$frameIndex] = [
                'page' => $page,
                'image' => $image,
                'commit_frame' => (bool) ($frame['commit_frame'] ?? false),
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,image:string,current_image?:string,bytes?:int,dirty?:bool,pinned?:bool,journaled?:bool,walFrame?:int}>
     */
    private static function normalizeCachePages(array $cachePages, int $pageCount, int $pageSize): array
    {
        $normalized = [];
        $seen = [];
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'] ?? null;
            if (!is_int($page) || $page < 1 || $page > $pageCount) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 cache pages must be one-based pages inside the database image');
            }
            if (isset($seen[$page])) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 cache pages must be unique');
            }
            $seen[$page] = true;
            $image = $cachePage['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 cache image must match page size');
            }
            if (isset($cachePage['current_image']) && (!is_string($cachePage['current_image']) || strlen($cachePage['current_image']) !== $pageSize)) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 current image must match page size');
            }
            if (isset($cachePage['bytes']) && (!is_int($cachePage['bytes']) || $cachePage['bytes'] < 0)) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 cache bytes must be non-negative');
            }
            if (isset($cachePage['walFrame']) && (!is_int($cachePage['walFrame']) || $cachePage['walFrame'] < 1)) {
                throw new \InvalidArgumentException('SQLite pager cache-spill WAL savepoint current-source next143 cache WAL frame must be positive');
            }
            $cachePage['image'] = $image;
            $normalized[] = $cachePage;
        }

        return $normalized;
    }

    /**
     * @return array<int,array{image:string,source:string,frame:int}>
     */
    private static function databaseSources(string $databaseBytes, int $pageSize): array
    {
        $sources = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($page = 1; $page <= $pageCount; $page++) {
            $sources[$page] = [
                'image' => substr($databaseBytes, ($page - 1) * $pageSize, $pageSize),
                'source' => 'database',
                'frame' => 0,
            ];
        }

        return $sources;
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
                'op' => 'admit_wal_savepoint_cache_spill_page',
                'page' => $page,
                'reason' => 'current_source_matches_retained_wal_prefix_and_savepoint_before_image_exists',
            ];
        }
        foreach ($rejectedPages as $page => $reasons) {
            $operations[] = [
                'op' => 'defer_wal_savepoint_cache_spill_page',
                'page' => $page,
                'reasons' => $reasons,
            ];
        }

        return $operations;
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 64), ".\0 ");
    }
}
