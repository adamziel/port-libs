<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalCacheSpillMasterCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,array{image:string,current_image?:string,source_id?:string,epoch?:int,dirty?:bool,journaled?:bool,pinned?:bool,bytes?:int,source?:string,walFrame?:int}> $cachePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $journalPath,
        string $masterJournalPath,
        ?string $masterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $hotJournalPages,
        array $cachePages,
        int $cacheSize,
        int $spillThreshold,
        string $journalMode = 'delete',
        bool $journalSynced = true,
        string $lockState = 'reserved',
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null,
        string $currentSourceId = 'master-hot-current-source',
        int $currentSourceEpoch = 1,
    ): array {
        if ($databasePath === '' || $journalPath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill master current-source next145 requires database, journal, and master journal paths');
        }
        if ($masterJournalBytes === null || trim($masterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill master current-source next145 requires master journal bytes');
        }
        $masterMembers = self::masterMembers($masterJournalBytes);
        if (!in_array($journalPath, $masterMembers, true)) {
            throw new \RuntimeException('SQLite pager hot-journal cache-spill master current-source next145 master journal does not reference the hot journal');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill master current-source next145 requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill master current-source next145 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill master current-source next145 database bytes must be page-size aligned');
        }
        if ($hotJournalPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill master current-source next145 requires hot-journal pages');
        }
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill master current-source next145 requires cache pages');
        }
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill master current-source next145 requires a current source id');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill master current-source next145 source epoch must be positive');
        }

        $pageCount = (int) (strlen($databaseBytes) / $pageSize);
        $database = self::databaseMap($databaseBytes, $pageSize);
        $hotJournalPages = self::normalizeImages($hotJournalPages, $pageSize, $pageCount, 'hot-journal');
        $cachePages = self::normalizeCache($cachePages, $pageSize, $pageCount);

        $operations = [[
            'op' => 'read_master_journal_for_cache_spill',
            'path' => $masterJournalPath,
            'member' => $journalPath,
            'reason' => 'cache_spill_after_hot_journal_must_use_current_master_source',
        ]];

        foreach ($hotJournalPages as $pageNumber => $image) {
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'hot-journal-master-current-source',
            ];
            $operations[] = [
                'op' => 'restore_hot_journal_page_before_cache_spill',
                'page_number' => $pageNumber,
                'reason' => 'dirty_cache_spill_must_follow_recovered_current_source',
            ];
        }

        $admitted = [];
        $rejected = [];
        $sourceRows = [];
        foreach ($cachePages as $pageNumber => $entry) {
            $currentImage = $database[$pageNumber]['image'];
            $entryCurrentImage = $entry['current_image'] ?? $currentImage;
            $dirty = $entry['dirty'];
            $journaled = $entry['journaled'];
            $pinned = $entry['pinned'];
            $sourceId = $entry['source_id'];
            $epoch = $entry['epoch'];
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
            if ($sourceId !== $currentSourceId) {
                $reasons[] = 'stale_master_source_id';
            }
            if ($epoch !== $currentSourceEpoch) {
                $reasons[] = 'stale_master_source_epoch';
            }
            if ($entryCurrentImage !== $currentImage) {
                $reasons[] = 'current_source_mismatch_after_hot_recovery';
            }

            $sourceRows[$pageNumber] = [
                'page_number' => $pageNumber,
                'dirty' => $dirty,
                'journaled' => $journaled,
                'pinned' => $pinned,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($entryCurrentImage),
                'recovered_prefix' => self::prefix($currentImage),
                'matches_recovered_current_source' => $entryCurrentImage === $currentImage,
                'hot_journal_page' => isset($hotJournalPages[$pageNumber]),
                'admitted' => $reasons === [],
                'rejected_reasons' => $reasons,
            ];

            if ($reasons === []) {
                $admitted[] = [
                    'page' => $pageNumber,
                    'bytes' => $entry['bytes'],
                    'journaled' => true,
                    'dirty' => true,
                    'pinned' => false,
                    'walFrame' => $entry['walFrame'],
                ];
                $operations[] = [
                    'op' => 'admit_master_hot_cache_spill_page',
                    'page' => $pageNumber,
                    'reason' => 'dirty_cache_page_matches_master_hot_current_source',
                ];
            } else {
                $rejected[$pageNumber] = $reasons;
                $operations[] = [
                    'op' => 'defer_master_hot_cache_spill_page',
                    'page' => $pageNumber,
                    'reasons' => $reasons,
                ];
            }
        }
        ksort($sourceRows, SORT_NUMERIC);
        ksort($rejected, SORT_NUMERIC);

        $spill = SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext107(
            $pageCount,
            $cacheSize,
            $spillThreshold,
            $admitted,
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
        $spillOperations = $spill['operations'] ?? [];

        return [
            'status' => $spilledPages === []
                ? 'pager_hot_journal_cache_spill_master_current_source_deferred_next145'
                : 'pager_hot_journal_cache_spill_master_current_source_next145',
            'reason' => $spilledPages === []
                ? 'cache_spill_deferred_after_master_hot_current_source_filter'
                : 'cache_spill_pages_rebased_to_master_hot_current_source',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'master_journal_path' => $masterJournalPath,
            'master_members' => $masterMembers,
            'page_size' => $pageSize,
            'journal_mode' => strtolower(trim($journalMode)),
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentSourceEpoch,
            ],
            'hot_journal_page_numbers' => array_keys($hotJournalPages),
            'admitted_page_numbers' => $admittedPages,
            'rejected_page_numbers' => $rejectedPages,
            'rejected_pages' => $rejected,
            'source_checks' => array_values($sourceRows),
            'source_checks_by_page' => $sourceRows,
            'spill' => $spill,
            'spilled_page_numbers' => $spilledPages,
            'wal_frame_pages' => $spill['next']['wal_frame_pages'] ?? [],
            'operations' => array_values(array_merge($operations, $spillOperations)),
            'source_digest' => hash('sha256', $databasePath . $journalPath . $masterJournalBytes . implode('', array_column($sourceRows, 'recovered_prefix')) . implode(',', $spilledPages)),
            'dependencies' => array_values(array_unique(array_merge(
                $spill['dependencies'] ?? [],
                [
                    'sqlite-pager-hot-journal-cache-spill-master-current-source-next145',
                    'sqlite-master-journal-current-source-member-validation',
                    'sqlite-pager-cache-spill-journalmode-current-source-next107',
                    'sqlite-hot-journal-before-cache-spill',
                ]
            ))),
        ];
    }

    /**
     * @return list<string>
     */
    private static function masterMembers(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/\r?\n/', $bytes) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $members, true)) {
                $members[] = $line;
            }
        }

        return $members;
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function databaseMap(string $databaseBytes, int $pageSize): array
    {
        $pages = [];
        $pageCount = (int) (strlen($databaseBytes) / $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'source' => 'database-current-source-before-hot-recovery',
            ];
        }

        return $pages;
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private static function normalizeImages(array $images, int $pageSize, int $pageCount, string $label): array
    {
        $normalized = [];
        foreach ($images as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill master current-source next145 {$label} page numbers must be one-based integers");
            }
            if ($pageNumber > $pageCount) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill master current-source next145 {$label} page {$pageNumber} is outside the database image");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill master current-source next145 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $cachePages
     * @return array<int,array{image:string,current_image?:string,source_id:string,epoch:int,dirty:bool,journaled:bool,pinned:bool,bytes:int,source:string,walFrame?:int}>
     */
    private static function normalizeCache(array $cachePages, int $pageSize, int $pageCount): array
    {
        $normalized = [];
        foreach ($cachePages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal cache-spill master current-source next145 cache page numbers must be one-based integers');
            }
            if ($pageNumber > $pageCount) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill master current-source next145 cache page {$pageNumber} is outside the database image");
            }
            $image = $entry['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill master current-source next145 cache page {$pageNumber} image must match page size");
            }
            if (isset($entry['current_image']) && (!is_string($entry['current_image']) || strlen($entry['current_image']) !== $pageSize)) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill master current-source next145 current image for page {$pageNumber} must match page size");
            }
            $sourceId = $entry['source_id'] ?? 'master-hot-current-source';
            if (!is_string($sourceId) || $sourceId === '') {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill master current-source next145 cache page {$pageNumber} source id must be non-empty");
            }
            $epoch = $entry['epoch'] ?? 1;
            if (!is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill master current-source next145 cache page {$pageNumber} epoch must be positive");
            }
            $bytes = $entry['bytes'] ?? $pageSize;
            if (!is_int($bytes) || $bytes < 0) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill master current-source next145 cache page {$pageNumber} bytes must be non-negative");
            }
            if (isset($entry['walFrame']) && (!is_int($entry['walFrame']) || $entry['walFrame'] < 1)) {
                throw new \InvalidArgumentException("SQLite pager hot-journal cache-spill master current-source next145 cache page {$pageNumber} WAL frame must be positive");
            }

            $normalized[$pageNumber] = [
                'image' => $image,
                'current_image' => $entry['current_image'] ?? null,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'dirty' => (bool) ($entry['dirty'] ?? true),
                'journaled' => (bool) ($entry['journaled'] ?? true),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'bytes' => $bytes,
                'source' => is_string($entry['source'] ?? null) ? $entry['source'] : 'pager-cache',
            ];
            if (isset($entry['walFrame'])) {
                $normalized[$pageNumber]['walFrame'] = $entry['walFrame'];
            }
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 56), "\0.");
    }
}
