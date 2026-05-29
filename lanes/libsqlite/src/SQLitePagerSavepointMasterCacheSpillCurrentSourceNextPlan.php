<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointMasterCacheSpillCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $databasePages
     * @param array<int,string> $masterRecoveredPages
     * @param list<array{page:int,image:string,dirty?:bool,journaled?:bool,pinned?:bool,source_id?:string,epoch?:int,bytes?:int}> $cachePages
     * @param list<int> $rollbackReadPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        string $savepointName,
        int $pageSize,
        array $databasePages,
        array $masterRecoveredPages,
        array $cachePages,
        int $cacheSize,
        int $spillThreshold,
        string $currentSourceId,
        int $currentEpoch,
        bool $masterJournalSynced,
        bool $releaseAfterSpill,
        array $rollbackReadPages,
        string $lockState = 'reserved',
        ?int $maxSpillPages = null,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '' || $savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 requires database, master journal, and savepoint names');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 page size must be a power of two at least 512');
        }
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 requires a current source id');
        }
        if ($currentEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 current epoch must be positive');
        }
        if ($cacheSize < 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 cache size must not be negative');
        }
        if ($spillThreshold < 1) {
            throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 spill threshold must be positive');
        }
        if ($maxSpillPages !== null && $maxSpillPages < 1) {
            throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 max spill pages must be positive');
        }

        $database = self::normalizePages($databasePages, $pageSize, 'database');
        $recovered = self::normalizeOptionalPages($masterRecoveredPages, $pageSize, 'master recovered');
        $cache = self::normalizeCache($cachePages, $pageSize, $currentSourceId, $currentEpoch);
        self::assertReadPages($rollbackReadPages, count($database));

        $source = [];
        foreach ($database as $pageNumber => $image) {
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'database-before-master-journal-recovery',
                'dirty' => false,
            ];
        }

        $operations = [];
        foreach ($recovered as $pageNumber => $image) {
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master cache-spill next144 master recovered page {$pageNumber} is outside the database image");
            }
            $source[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-current-source-recovered-page',
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'apply_master_journal_recovered_page_before_cache_spill',
                'page_number' => $pageNumber,
                'source_id' => $currentSourceId,
                'epoch' => $currentEpoch,
            ];
        }

        $beforeImages = [];
        $spillInput = [];
        $blocked = [];
        $cacheRows = [];
        foreach ($cache as $entry) {
            $pageNumber = $entry['page'];
            if (!isset($source[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master cache-spill next144 cache page {$pageNumber} is outside the database image");
            }

            $matchesSource = $entry['source_id'] === $currentSourceId
                && $entry['epoch'] === $currentEpoch
                && $entry['image'] !== $source[$pageNumber]['image'];
            $journaledForSavepoint = $entry['journaled'] && $matchesSource;
            if ($entry['dirty'] && !$journaledForSavepoint) {
                $blocked[] = [
                    'page_number' => $pageNumber,
                    'reason' => $entry['journaled'] ? 'cache_page_source_mismatch_for_savepoint' : 'dirty_page_lacks_savepoint_before_image',
                ];
            }

            if ($journaledForSavepoint) {
                $beforeImages[$pageNumber] = $source[$pageNumber]['image'];
            }

            $spillInput[] = [
                'page' => $pageNumber,
                'bytes' => $entry['bytes'],
                'dirty' => $entry['dirty'],
                'journaled' => $journaledForSavepoint,
                'pinned' => $entry['pinned'],
            ];
            $cacheRows[$pageNumber] = [
                'page_number' => $pageNumber,
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'journaled_for_savepoint' => $journaledForSavepoint,
                'source_matches_current' => $matchesSource,
                'before_prefix' => self::label($source[$pageNumber]['image']),
                'cache_prefix' => self::label($entry['image']),
            ];
        }

        $spill = SQLitePagerDirtyPageCacheSpillPlan::currentNext(
            count($database),
            $cacheSize,
            $spillThreshold,
            $spillInput,
            $masterJournalSynced,
            $lockState,
            $blocked === [],
            $maxSpillPages
        );

        $spilled = $spill['next']['spilled_pages'];
        foreach ($spilled as $pageNumber) {
            $cacheEntry = self::cacheEntry($cache, $pageNumber);
            $source[$pageNumber] = [
                'image' => $cacheEntry['image'],
                'source' => 'cache-spill-write-after-savepoint-before-image',
                'dirty' => false,
            ];
            $operations[] = [
                'op' => 'spill_savepoint_journaled_cache_page_to_database',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'before_prefix' => self::label($beforeImages[$pageNumber] ?? ''),
                'after_prefix' => self::label($cacheEntry['image']),
            ];
        }

        $rollbackReads = [];
        foreach (array_values(array_unique($rollbackReadPages)) as $pageNumber) {
            $image = $source[$pageNumber]['image'];
            if (isset($beforeImages[$pageNumber])) {
                $image = $beforeImages[$pageNumber];
            }
            $rollbackReads[] = [
                'page_number' => $pageNumber,
                'prefix' => self::label($image),
                'restored_from_savepoint_before_image' => isset($beforeImages[$pageNumber]),
                'spilled_before_rollback_to' => in_array($pageNumber, $spilled, true),
            ];
            $operations[] = [
                'op' => 'rollback_to_savepoint_reads_master_current_before_image',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
            ];
        }

        $releasePages = $releaseAfterSpill ? $spilled : [];
        if ($releaseAfterSpill && $spilled !== []) {
            $operations[] = [
                'op' => 'release_savepoint_after_cache_spill_keeps_database_pages',
                'savepoint' => $savepointName,
                'page_numbers' => $releasePages,
            ];
        }

        ksort($source, SORT_NUMERIC);
        ksort($cacheRows, SORT_NUMERIC);

        return [
            'status' => $spilled === []
                ? 'pager-savepoint-master-cache-spill-deferred-current-source-next144'
                : 'pager-savepoint-master-cache-spill-current-source-next144',
            'reason' => $spilled === []
                ? 'cache_spill_deferred_until_master_journal_savepoint_before_images_are_current'
                : 'master_journal_current_source_savepoint_before_images_guard_cache_spill',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'savepoint' => $savepointName,
            'page_size' => $pageSize,
            'current_source' => ['id' => $currentSourceId, 'epoch' => $currentEpoch],
            'master_journal_synced' => $masterJournalSynced,
            'release_after_spill' => $releaseAfterSpill,
            'master_recovered_page_numbers' => array_keys($recovered),
            'savepoint_before_page_numbers' => array_keys($beforeImages),
            'spilled_page_numbers' => $spilled,
            'blocked_cache_pages' => $blocked,
            'cache_rows' => array_values($cacheRows),
            'spill_plan_status' => $spill['status'],
            'spill_blocked_reasons' => $spill['blocked_reasons'],
            'spill_operations' => $spill['operations'],
            'rollback_reads' => $rollbackReads,
            'release_page_numbers' => $releasePages,
            'final_prefixes' => self::prefixes($source),
            'final_sources' => self::sources($source),
            'final_database_bytes' => self::sourceBytes($source, $pageSize),
            'operations' => $operations,
            'source_digest' => hash('sha256', implode('|', self::prefixes($source)) . '|' . implode(',', $spilled) . '|' . implode(',', array_keys($beforeImages))),
            'dependencies' => [
                'sqlite-pager-savepoint-master-cache-spill-current-source-next144',
                'sqlite-pager-cache-spill-current-next71',
                'sqlite-pager-master-journal-savepoint-cache-current-source-next138',
                'sqlite-savepoint-before-image-required-before-cache-spill',
            ],
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePages(array $pages, int $pageSize, string $label): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException("SQLite pager savepoint master cache-spill next144 {$label} pages are required");
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager savepoint master cache-spill next144 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint master cache-spill next144 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizeOptionalPages(array $pages, int $pageSize, string $label): array
    {
        return $pages === [] ? [] : self::normalizePages($pages, $pageSize, $label);
    }

    /**
     * @param list<array{page:int,image:string,dirty?:bool,journaled?:bool,pinned?:bool,source_id?:string,epoch?:int,bytes?:int}> $cachePages
     * @return list<array{page:int,image:string,dirty:bool,journaled:bool,pinned:bool,source_id:string,epoch:int,bytes:int}>
     */
    private static function normalizeCache(array $cachePages, int $pageSize, string $currentSourceId, int $currentEpoch): array
    {
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 cache pages are required');
        }

        $seen = [];
        $normalized = [];
        foreach ($cachePages as $entry) {
            $pageNumber = $entry['page'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 cache page numbers must be one-based integers');
            }
            if (isset($seen[$pageNumber])) {
                throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 cache pages must be unique');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint master cache-spill next144 cache page {$pageNumber} image must match page size");
            }
            $bytes = $entry['bytes'] ?? strlen($entry['image']);
            if (!is_int($bytes) || $bytes < 0) {
                throw new \InvalidArgumentException("SQLite pager savepoint master cache-spill next144 cache page {$pageNumber} bytes must be non-negative");
            }
            $normalized[] = [
                'page' => $pageNumber,
                'image' => $entry['image'],
                'dirty' => (bool) ($entry['dirty'] ?? true),
                'journaled' => (bool) ($entry['journaled'] ?? true),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'source_id' => isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : $currentSourceId,
                'epoch' => isset($entry['epoch']) && is_int($entry['epoch']) ? $entry['epoch'] : $currentEpoch,
                'bytes' => $bytes,
            ];
            $seen[$pageNumber] = true;
        }

        usort($normalized, static fn (array $left, array $right): int => $left['page'] <=> $right['page']);

        return $normalized;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertReadPages(array $pages, int $pageCount): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1 || $pageNumber > $pageCount) {
                throw new \InvalidArgumentException('SQLite pager savepoint master cache-spill next144 rollback read pages must be inside the database image');
            }
        }
    }

    /**
     * @param list<array{page:int,image:string,dirty:bool,journaled:bool,pinned:bool,source_id:string,epoch:int,bytes:int}> $cache
     * @return array{page:int,image:string,dirty:bool,journaled:bool,pinned:bool,source_id:string,epoch:int,bytes:int}
     */
    private static function cacheEntry(array $cache, int $pageNumber): array
    {
        foreach ($cache as $entry) {
            if ($entry['page'] === $pageNumber) {
                return $entry;
            }
        }

        throw new \LogicException("SQLite pager savepoint master cache-spill next144 missing cache page {$pageNumber}");
    }

    /**
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
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
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
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
     * @param array<int,array{image:string,source:string,dirty:bool}> $source
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
