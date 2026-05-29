<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerWalSavepointHotCacheCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $hotRecoveredPages
     * @param array<int,array{page:int,image:string,commit_frame?:bool}> $walFrames
     * @param array<int,array{image:string,source_id?:string,generation?:int,frame?:int,dirty?:bool,pinned?:bool,source?:string}> $cachePages
     * @param list<int> $nextReadPages
     * @param array<int,string> $nextWritePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $savepointName,
        int $rollbackToFrame,
        array $hotRecoveredPages,
        array $walFrames,
        array $cachePages,
        array $nextReadPages,
        array $nextWritePages,
        string $currentSourceId,
        int $currentGeneration,
        bool $refreshCleanStaleCache = true,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 requires a database path');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 database bytes must be page-size aligned');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 savepoint name must not be empty');
        }
        if ($rollbackToFrame < 0) {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 rollback frame must be non-negative');
        }
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 requires a current source id');
        }
        if ($currentGeneration < 1) {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 generation must be positive');
        }
        if ($nextReadPages === [] && $nextWritePages === []) {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 requires next read or write pages');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $hotRecoveredPages = self::normalizeImages($hotRecoveredPages, $pageSize, 'hot recovered');
        $walFrames = self::normalizeWalFrames($walFrames, $databaseBytes, $pageSize);
        $cachePages = self::normalizeCache($cachePages, $pageSize);
        self::assertPageList($nextReadPages, 'next read');
        $nextWritePages = self::normalizeOptionalImages($nextWritePages, $pageSize, 'next write');

        foreach ($hotRecoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 hot page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'frame' => 0,
                'source' => 'hot-recovered-database-current-source',
            ];
        }

        $discardedFrames = [];
        foreach ($walFrames as $frameIndex => $frame) {
            if ($frameIndex > $rollbackToFrame) {
                $discardedFrames[] = $frameIndex;
                continue;
            }
            $database[$frame['page']] = [
                'image' => $frame['image'],
                'frame' => $frameIndex,
                'source' => $frame['commit_frame']
                    ? 'retained-commit-wal-frame-current-source'
                    : 'retained-wal-frame-current-source',
            ];
        }

        $nextSourceId = 'wal-savepoint-hot-cache:' . substr(hash('sha256', $databasePath . '|' . $savepointName . '|' . $rollbackToFrame . '|' . $currentSourceId), 0, 16);
        $nextGeneration = $currentGeneration + 1;
        $operations = [
            [
                'op' => 'recover_hot_database_pages_before_wal_savepoint_cache',
                'page_numbers' => array_keys($hotRecoveredPages),
                'reason' => 'hot_recovery_establishes_current_database_source_before_wal_prefix',
            ],
            [
                'op' => 'rollback_wal_to_savepoint_frame_for_hot_cache',
                'savepoint' => $savepointName,
                'rollback_to_frame' => $rollbackToFrame,
                'discarded_frames' => $discardedFrames,
                'reason' => 'savepoint_rollback_discards_newer_wal_frames_before_cache_reuse',
            ],
        ];

        $validCache = [];
        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $blocked = [];
        $rows = [];

        foreach ($cachePages as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 cache page {$pageNumber} is outside the database image");
            }
            $current = $database[$pageNumber];
            $stale = $entry['frame'] > $rollbackToFrame
                || $entry['source_id'] !== $currentSourceId
                || $entry['generation'] !== $currentGeneration
                || $entry['image'] !== $current['image'];
            $invalidateReason = null;
            $blockReason = null;
            if ($entry['dirty'] && $stale) {
                $invalidateReason = 'dirty_cache_page_from_discarded_wal_savepoint_frame';
            } elseif ($entry['pinned'] && $stale) {
                $invalidateReason = 'pinned_reader_cache_predates_hot_wal_current_source';
            } elseif ($entry['source_id'] !== $currentSourceId) {
                $invalidateReason = 'stale_wal_hot_cache_source_id';
            } elseif ($entry['generation'] !== $currentGeneration) {
                $invalidateReason = 'stale_wal_hot_cache_generation';
            } elseif ($entry['image'] !== $current['image'] && !$refreshCleanStaleCache) {
                $blockReason = 'stale_wal_hot_cache_refresh_disabled';
            }

            if ($invalidateReason !== null || $blockReason !== null) {
                $reason = $invalidateReason ?? $blockReason;
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'frame' => $entry['frame'],
                    'source_id' => $entry['source_id'],
                    'generation' => $entry['generation'],
                    'dirty' => $entry['dirty'],
                    'pinned' => $entry['pinned'],
                    'reason' => $reason,
                ];
                if ($blockReason !== null) {
                    $blocked[] = $pageNumber;
                }
                $operations[] = [
                    'op' => 'invalidate_wal_savepoint_hot_cache_page',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                ];
            } elseif ($stale) {
                $refreshed[] = $pageNumber;
                $validCache[$pageNumber] = [
                    'image' => $current['image'],
                    'source' => 'wal-savepoint-hot-cache-refreshed-current-source',
                    'source_id' => $nextSourceId,
                    'generation' => $nextGeneration,
                    'frame' => $current['frame'],
                    'dirty' => false,
                ];
                $operations[] = [
                    'op' => 'refresh_wal_savepoint_hot_cache_page',
                    'page_number' => $pageNumber,
                    'source_frame' => $current['frame'],
                    'reason' => 'clean_cache_image_is_stale_after_hot_recovery_and_savepoint_rollback',
                ];
            } else {
                $retained[] = $pageNumber;
                $validCache[$pageNumber] = [
                    'image' => $entry['image'],
                    'source' => $entry['source'],
                    'source_id' => $nextSourceId,
                    'generation' => $nextGeneration,
                    'frame' => $entry['frame'],
                    'dirty' => false,
                ];
                $operations[] = [
                    'op' => 'retain_wal_savepoint_hot_cache_page',
                    'page_number' => $pageNumber,
                    'source_frame' => $entry['frame'],
                    'reason' => 'cache_page_matches_hot_recovered_wal_savepoint_current_source',
                ];
            }

            $rows[$pageNumber] = [
                'page_number' => $pageNumber,
                'cache_frame' => $entry['frame'],
                'source_frame' => $current['frame'],
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'stale_before_action' => $stale,
                'matches_current_after' => isset($validCache[$pageNumber]) && $validCache[$pageNumber]['image'] === $current['image'],
                'before_prefix' => self::label($entry['image']),
                'current_prefix' => self::label($current['image']),
            ];
        }

        $nextReads = [];
        foreach (array_values(array_unique($nextReadPages)) as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 read page {$pageNumber} is outside the database image");
            }
            $cache = $validCache[$pageNumber] ?? null;
            $hit = is_array($cache);
            $image = $hit ? $cache['image'] : $database[$pageNumber]['image'];
            $nextReads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $hit,
                'source' => $hit ? $cache['source'] : $database[$pageNumber]['source'],
                'source_frame' => $hit ? $cache['frame'] : $database[$pageNumber]['frame'],
                'source_id' => $nextSourceId,
                'generation' => $nextGeneration,
                'prefix' => self::label($image),
            ];
            $operations[] = [
                'op' => $hit ? 'next_read_wal_savepoint_hot_cache_hit' : 'next_read_wal_savepoint_hot_cache_miss',
                'page_number' => $pageNumber,
                'reason' => 'next_read_uses_cache_only_after_hot_wal_current_source_validation',
            ];
        }

        $nextWrites = [];
        foreach ($nextWritePages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 write page {$pageNumber} is outside the database image");
            }
            $before = $database[$pageNumber];
            $database[$pageNumber] = [
                'image' => $image,
                'frame' => $rollbackToFrame + count($nextWrites) + 1,
                'source' => 'next-write-after-wal-savepoint-hot-cache',
            ];
            $validCache[$pageNumber] = [
                'image' => $image,
                'source' => 'next-write-after-wal-savepoint-hot-cache',
                'source_id' => $nextSourceId,
                'generation' => $nextGeneration,
                'frame' => $database[$pageNumber]['frame'],
                'dirty' => true,
            ];
            $nextWrites[] = [
                'page_number' => $pageNumber,
                'before_prefix' => self::label($before['image']),
                'before_frame' => $before['frame'],
                'after_prefix' => self::label($image),
                'journal_before_from_current_source' => true,
            ];
            $operations[] = [
                'op' => 'capture_next_write_before_image_from_wal_savepoint_hot_current_source',
                'page_number' => $pageNumber,
                'before_frame' => $before['frame'],
                'reason' => 'next_write_journal_uses_hot_recovered_savepoint_current_source',
            ];
        }

        ksort($validCache, SORT_NUMERIC);
        ksort($database, SORT_NUMERIC);
        ksort($rows, SORT_NUMERIC);

        return [
            'status' => $blocked === []
                ? 'pager-wal-savepoint-hot-cache-current-source-next139'
                : 'pager-wal-savepoint-hot-cache-blocked-current-source-next139',
            'reason' => $blocked === []
                ? 'hot_recovery_and_savepoint_rollback_rebase_wal_cache_before_next_read_write'
                : 'stale_clean_wal_cache_pages_remain_after_hot_recovery_and_savepoint_rollback',
            'database_path' => $databasePath,
            'savepoint' => $savepointName,
            'page_size' => $pageSize,
            'rollback_to_frame' => $rollbackToFrame,
            'discarded_wal_frames' => $discardedFrames,
            'current_source' => ['id' => $currentSourceId, 'generation' => $currentGeneration],
            'next_source' => ['id' => $nextSourceId, 'generation' => $nextGeneration],
            'hot_recovered_page_numbers' => array_keys($hotRecoveredPages),
            'retained_cache_page_numbers' => $retained,
            'refreshed_cache_page_numbers' => $refreshed,
            'invalidated_cache_page_numbers' => array_column($invalidated, 'page_number'),
            'blocked_cache_page_numbers' => $blocked,
            'invalidated_cache_entries' => $invalidated,
            'cache_rows' => array_values($rows),
            'next_reads' => $nextReads,
            'next_writes' => $nextWrites,
            'final_cache_page_numbers' => array_keys($validCache),
            'final_cache_dirty_page_numbers' => self::dirtyPageNumbers($validCache),
            'final_prefixes' => self::prefixes($database),
            'final_sources' => self::sources($database),
            'final_database_bytes' => self::sourceBytes($database, $pageSize),
            'operations' => $operations,
            'source_digest' => hash('sha256', implode('|', self::prefixes($database)) . '|' . implode(',', array_keys($validCache))),
            'dependencies' => [
                'sqlite-pager-wal-savepoint-hot-cache-current-source-next139',
                'sqlite-pager-savepoint-wal-cache-recovery-current-source-next133',
                'sqlite-pager-master-journal-hot-cache-current-source-next136',
            ],
        ];
    }

    /**
     * @return array<int,array{image:string,frame:int,source:string}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize): array
    {
        $map = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $map[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'frame' => 0,
                'source' => 'database-before-wal-savepoint-hot-cache',
            ];
        }

        return $map;
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizeImages(array $pages, int $pageSize, string $label): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 {$label} pages are required");
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizeOptionalImages(array $pages, int $pageSize, string $label): array
    {
        return $pages === [] ? [] : self::normalizeImages($pages, $pageSize, $label);
    }

    /**
     * @param array<int,array{page:int,image:string,commit_frame?:bool}> $walFrames
     * @return array<int,array{page:int,image:string,commit_frame:bool}>
     */
    private static function normalizeWalFrames(array $walFrames, string $databaseBytes, int $pageSize): array
    {
        if ($walFrames === []) {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 WAL frames are required');
        }
        ksort($walFrames, SORT_NUMERIC);
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        $normalized = [];
        $last = 0;
        foreach ($walFrames as $frameIndex => $frame) {
            if (!is_int($frameIndex) || $frameIndex < 1 || $frameIndex <= $last) {
                throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 WAL frame indexes must be increasing one-based integers');
            }
            $pageNumber = $frame['page'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1 || $pageNumber > $pageCount) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 WAL frame {$frameIndex} page is outside the database image");
            }
            if (!isset($frame['image']) || !is_string($frame['image']) || strlen($frame['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 WAL frame {$frameIndex} image must match page size");
            }
            $normalized[$frameIndex] = [
                'page' => $pageNumber,
                'image' => $frame['image'],
                'commit_frame' => (bool) ($frame['commit_frame'] ?? false),
            ];
            $last = $frameIndex;
        }

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,generation?:int,frame?:int,dirty?:bool,pinned?:bool,source?:string}> $pages
     * @return array<int,array{image:string,source_id:string,generation:int,frame:int,dirty:bool,pinned:bool,source:string}>
     */
    private static function normalizeCache(array $pages, int $pageSize): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 cache pages are required');
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager WAL savepoint hot-cache next139 cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 cache page {$pageNumber} image must match page size");
            }
            $frame = $entry['frame'] ?? 0;
            $generation = $entry['generation'] ?? 0;
            if (!is_int($frame) || $frame < 0) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 cache page {$pageNumber} frame must be non-negative");
            }
            if (!is_int($generation) || $generation < 0) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 cache page {$pageNumber} generation must be non-negative");
            }
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '',
                'generation' => $generation,
                'frame' => $frame,
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'source' => isset($entry['source']) && is_string($entry['source']) && $entry['source'] !== '' ? $entry['source'] : 'pager-wal-cache-before-hot-savepoint-recovery',
            ];
        }

        return $normalized;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages, string $label): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager WAL savepoint hot-cache next139 {$label} pages must be one-based integers");
            }
        }
    }

    /**
     * @param array<int,array{image:string,frame:int,source:string}> $source
     * @return array<int,string>
     */
    private static function prefixes(array $source): array
    {
        ksort($source, SORT_NUMERIC);
        $prefixes = [];
        foreach ($source as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::label($entry['image']);
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,frame:int,source:string}> $source
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
     * @param array<int,array<string,mixed>> $cache
     * @return list<int>
     */
    private static function dirtyPageNumbers(array $cache): array
    {
        $pages = [];
        foreach ($cache as $pageNumber => $entry) {
            if (($entry['dirty'] ?? false) === true) {
                $pages[] = $pageNumber;
            }
        }

        return $pages;
    }

    /**
     * @param array<int,array{image:string,frame:int,source:string}> $source
     */
    private static function sourceBytes(array $source, int $pageSize): string
    {
        ksort($source, SORT_NUMERIC);
        $bytes = '';
        $max = max(array_keys($source));
        for ($pageNumber = 1; $pageNumber <= $max; $pageNumber++) {
            $bytes .= $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
        }

        return $bytes;
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
