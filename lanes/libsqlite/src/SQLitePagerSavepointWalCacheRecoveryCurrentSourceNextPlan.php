<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointWalCacheRecoveryCurrentSourceNextPlan
{
    /**
     * @param array<int,array{image:string,frame:int,source?:string,dirty?:bool}> $cachePages
     * @param array<int,array{page:int,image:string,commit_frame?:bool}> $walFrames
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databaseBytes,
        int $pageSize,
        string $savepointName,
        SQLiteSavepointStack $savepoints,
        array $cachePages,
        array $walFrames,
        array $readPages,
        bool $refreshStaleCache = true,
    ): array {
        if ($databaseBytes === '' || $pageSize < 1 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL cache recovery next133 requires page-size aligned database bytes');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL cache recovery next133 savepoint must not be empty');
        }
        if ($cachePages === [] || $walFrames === [] || $readPages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint WAL cache recovery next133 requires cache pages, WAL frames, and read pages');
        }

        $cachePages = self::normalizeCachePages($cachePages, $pageSize);
        $walFrames = self::normalizeWalFrames($walFrames, $databaseBytes, $pageSize);
        $readPages = self::normalizePageList($readPages, 'read');
        $rollback = $savepoints->walRollbackToPlan($savepointName);
        $rollbackToFrame = $rollback['rollback_to_frame'];
        $discardedFrameSet = [];
        foreach ($rollback['discarded_wal_frames'] as $frame) {
            $discardedFrameSet[$frame['frame_index']] = true;
        }

        $retainedSources = self::retainedSources($databaseBytes, $pageSize, $walFrames, $rollbackToFrame);
        $stalePages = [];
        $refreshedPages = [];
        $retainedPages = [];
        $blockedPages = [];
        $cacheRows = [];
        $operations = [[
            'op' => 'rollback_to_savepoint_wal_prefix',
            'savepoint' => $savepointName,
            'rollback_to_frame' => $rollbackToFrame,
            'discarded_frame_count' => count($rollback['discarded_wal_frames']),
            'reason' => 'savepoint_rollback_discards_wal_frames_before_cache_reuse',
        ]];

        foreach ($cachePages as $pageNumber => $entry) {
            if (!isset($retainedSources[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint WAL cache recovery next133 cache page {$pageNumber} is outside the database image");
            }
            $frame = $entry['frame'];
            $source = $retainedSources[$pageNumber];
            $stale = isset($discardedFrameSet[$frame]) || $frame > $rollbackToFrame || $entry['image'] !== $source['image'];
            $afterImage = $entry['image'];
            $sourceAfter = $entry['source'];
            if ($stale) {
                $stalePages[] = $pageNumber;
                if ($refreshStaleCache) {
                    $afterImage = $source['image'];
                    $sourceAfter = 'savepoint-rollback-refreshed-wal-cache';
                    $refreshedPages[] = $pageNumber;
                    $operations[] = [
                        'op' => 'refresh_wal_cache_page_after_savepoint_rollback',
                        'page_number' => $pageNumber,
                        'cache_frame' => $frame,
                        'source_frame' => $source['frame'],
                        'reason' => 'cache_frame_was_discarded_or_no_longer_matches_current_source',
                    ];
                } else {
                    $sourceAfter = 'stale-savepoint-wal-cache-blocked';
                    $blockedPages[] = $pageNumber;
                    $operations[] = [
                        'op' => 'block_stale_wal_cache_page_after_savepoint_rollback',
                        'page_number' => $pageNumber,
                        'cache_frame' => $frame,
                        'reason' => 'stale_wal_cache_reuse_disabled',
                    ];
                }
            } else {
                $retainedPages[] = $pageNumber;
                $operations[] = [
                    'op' => 'retain_wal_cache_page_after_savepoint_rollback',
                    'page_number' => $pageNumber,
                    'cache_frame' => $frame,
                    'reason' => 'cache_frame_survives_rollback_to_savepoint',
                ];
            }

            $cacheRows[$pageNumber] = [
                'page_number' => $pageNumber,
                'cache_frame' => $frame,
                'source_frame' => $source['frame'],
                'dirty_before' => $entry['dirty'],
                'stale_before_refresh' => $stale,
                'refreshed' => $stale && $refreshStaleCache,
                'blocked' => $stale && !$refreshStaleCache,
                'source_before' => $entry['source'],
                'source_after' => $sourceAfter,
                'before_prefix' => self::label($entry['image']),
                'current_prefix' => self::label($source['image']),
                'after_prefix' => self::label($afterImage),
                'matches_current_source_after' => $afterImage === $source['image'],
            ];
        }

        $readRows = [];
        foreach ($readPages as $pageNumber) {
            if (!isset($retainedSources[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint WAL cache recovery next133 read page {$pageNumber} is outside the database image");
            }
            $cacheRow = $cacheRows[$pageNumber] ?? null;
            $source = $retainedSources[$pageNumber];
            $readRows[] = [
                'page_number' => $pageNumber,
                'source_frame' => $source['frame'],
                'source' => $cacheRow !== null && $cacheRow['matches_current_source_after']
                    ? $cacheRow['source_after']
                    : $source['source'],
                'prefix' => $cacheRow['after_prefix'] ?? self::label($source['image']),
                'cache_hit' => $cacheRow !== null && $cacheRow['matches_current_source_after'],
            ];
            $operations[] = [
                'op' => 'read_page_after_savepoint_wal_cache_recovery',
                'page_number' => $pageNumber,
                'source_frame' => $source['frame'],
                'reason' => 'reader_uses_cache_only_after_current_source_validation',
            ];
        }

        sort($stalePages, SORT_NUMERIC);
        sort($refreshedPages, SORT_NUMERIC);
        sort($retainedPages, SORT_NUMERIC);
        sort($blockedPages, SORT_NUMERIC);
        ksort($cacheRows, SORT_NUMERIC);

        $currentSourceVerified = $blockedPages === [] && $stalePages === $refreshedPages;

        return [
            'status' => $currentSourceVerified
                ? 'pager-savepoint-wal-cache-recovery-current-source-next133'
                : 'pager-savepoint-wal-cache-recovery-blocked-current-source-next133',
            'reason' => $currentSourceVerified
                ? 'rollback_to_savepoint_refreshes_cache_pages_sourced_from_discarded_wal_frames'
                : 'stale_wal_cache_pages_remain_after_savepoint_rollback',
            'savepoint' => $savepointName,
            'page_size' => $pageSize,
            'rollback_to_frame' => $rollbackToFrame,
            'discarded_wal_frames' => $rollback['discarded_wal_frames'],
            'discarded_page_numbers' => $rollback['discarded_page_numbers'],
            'stale_cache_page_numbers' => $stalePages,
            'refreshed_cache_page_numbers' => $refreshedPages,
            'retained_cache_page_numbers' => $retainedPages,
            'blocked_cache_page_numbers' => $blockedPages,
            'current_source_verified' => $currentSourceVerified,
            'cache_rows' => array_values($cacheRows),
            'read_rows' => $readRows,
            'operations' => $operations,
            'source_digest' => hash('sha256', implode('|', array_map(static fn (array $row): string => $row['after_prefix'], $cacheRows))),
            'dependencies' => [
                'sqlite-pager-savepoint-wal-cache-recovery-current-source-next133',
                'sqlite-savepoint-wal-rollback-prefix',
                'sqlite-pager-cache-current-source-validation',
            ],
        ];
    }

    /**
     * @param array<int,array{image:string,frame:int,source?:string,dirty?:bool}> $cachePages
     * @return array<int,array{image:string,frame:int,source:string,dirty:bool}>
     */
    private static function normalizeCachePages(array $cachePages, int $pageSize): array
    {
        ksort($cachePages, SORT_NUMERIC);
        $normalized = [];
        foreach ($cachePages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager savepoint WAL cache recovery next133 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint WAL cache recovery next133 cache page {$pageNumber} image must match page size");
            }
            if (!isset($entry['frame']) || !is_int($entry['frame']) || $entry['frame'] < 0) {
                throw new \InvalidArgumentException("SQLite pager savepoint WAL cache recovery next133 cache page {$pageNumber} frame must be a non-negative integer");
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'frame' => $entry['frame'],
                'source' => isset($entry['source']) && is_string($entry['source']) && $entry['source'] !== '' ? $entry['source'] : 'wal-cache-before-savepoint-rollback',
                'dirty' => (bool) ($entry['dirty'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int,array{page:int,image:string,commit_frame?:bool}> $walFrames
     * @return array<int,array{page:int,image:string,commit_frame:bool}>
     */
    private static function normalizeWalFrames(array $walFrames, string $databaseBytes, int $pageSize): array
    {
        ksort($walFrames, SORT_NUMERIC);
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        $normalized = [];
        $lastFrame = 0;
        foreach ($walFrames as $frameIndex => $frame) {
            if (!is_int($frameIndex) || $frameIndex < 1 || $frameIndex <= $lastFrame) {
                throw new \InvalidArgumentException('SQLite pager savepoint WAL cache recovery next133 WAL frame indexes must be increasing one-based integers');
            }
            $pageNumber = $frame['page'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || $pageNumber > $pageCount) {
                throw new \InvalidArgumentException("SQLite pager savepoint WAL cache recovery next133 WAL frame {$frameIndex} page is outside the database image");
            }
            if (!isset($frame['image']) || !is_string($frame['image']) || strlen($frame['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint WAL cache recovery next133 WAL frame {$frameIndex} image must match page size");
            }
            $normalized[$frameIndex] = [
                'page' => $pageNumber,
                'image' => $frame['image'],
                'commit_frame' => (bool) ($frame['commit_frame'] ?? false),
            ];
            $lastFrame = $frameIndex;
        }

        return $normalized;
    }

    /**
     * @param array<int,array{page:int,image:string,commit_frame:bool}> $walFrames
     * @return array<int,array{image:string,frame:int,source:string}>
     */
    private static function retainedSources(string $databaseBytes, int $pageSize, array $walFrames, int $rollbackToFrame): array
    {
        $sources = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $sources[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'frame' => 0,
                'source' => 'database-before-wal-savepoint-rollback',
            ];
        }
        foreach ($walFrames as $frameIndex => $frame) {
            if ($frameIndex > $rollbackToFrame) {
                continue;
            }
            $sources[$frame['page']] = [
                'image' => $frame['image'],
                'frame' => $frameIndex,
                'source' => 'retained-wal-frame-before-savepoint-rollback',
            ];
        }

        return $sources;
    }

    /**
     * @param list<int> $pages
     * @return list<int>
     */
    private static function normalizePageList(array $pages, string $label): array
    {
        $normalized = [];
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager savepoint WAL cache recovery next133 {$label} pages must be one-based integers");
            }
            $normalized[] = $pageNumber;
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
