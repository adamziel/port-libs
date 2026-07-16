<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerCacheSpillSavepointMasterCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $masterRecoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,dirty?:bool,pinned?:bool,source?:string,bytes?:int,journaled?:bool,walFrame?:int}> $cachePages
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        string $savepointName,
        SQLiteSavepointStack $savepoints,
        array $masterRecoveredPages,
        array $cachePages,
        array $readPages,
        string $currentSourceId,
        int $currentSourceEpoch,
        int $cacheSize,
        int $spillThreshold,
        string $journalMode = 'delete',
        bool $journalSynced = true,
        string $lockState = 'reserved',
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null,
    ): array {
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager cache-spill savepoint master current-source next141 requires cache pages');
        }

        $cachePages = self::normalizeCachePages($cachePages, $pageSize);
        self::assertPageList($readPages);

        $hot = SQLitePagerMasterJournalHotCacheCurrentSourceNextPlan::plan(
            $databasePath,
            $masterJournalPath,
            $cachedMasterJournalBytes,
            $currentMasterJournalBytes,
            $databaseBytes,
            $pageSize,
            $masterRecoveredPages,
            self::hotCacheInput($cachePages),
            array_values(array_unique(array_merge($readPages, array_keys($cachePages)))),
            [],
            $currentSourceId,
            $currentSourceEpoch,
            true,
        );

        $currentBytes = (string) $hot['final_database_bytes'];
        $eligible = [];
        $rejected = [];
        $sourceRows = [];
        $nextSourceId = (string) $hot['next_source']['id'];
        $nextEpoch = (int) $hot['next_source']['epoch'];

        foreach ($cachePages as $pageNumber => $entry) {
            $reasons = [];
            if (($entry['source_id'] ?? '') !== $currentSourceId) {
                $reasons[] = 'stale_master_journal_source_id';
            }
            if (($entry['epoch'] ?? 0) !== $currentSourceEpoch) {
                $reasons[] = 'stale_master_journal_source_epoch';
            }
            if (($entry['dirty'] ?? true) !== true) {
                $reasons[] = 'cache_page_clean';
            }
            if (($entry['pinned'] ?? false) === true) {
                $reasons[] = 'cache_page_pinned';
            }

            $currentImage = self::pageImage($currentBytes, $pageNumber, $pageSize);
            if (($entry['image'] ?? '') === $currentImage) {
                $reasons[] = 'cache_page_not_dirty_against_recovered_current_source';
            }

            $sourceRows[$pageNumber] = [
                'page_number' => $pageNumber,
                'source_id_before' => $entry['source_id'],
                'epoch_before' => $entry['epoch'],
                'next_source_id' => $nextSourceId,
                'next_epoch' => $nextEpoch,
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
                'eligible_for_savepoint_spill' => $reasons === [],
                'rejected_reasons' => $reasons,
            ];

            if ($reasons === []) {
                $eligible[] = [
                    'page' => $pageNumber,
                    'image' => $entry['image'],
                    'current_image' => $currentImage,
                    'bytes' => $entry['bytes'] ?? $pageSize,
                    'journaled' => $entry['journaled'] ?? true,
                    'dirty' => true,
                    'pinned' => false,
                    'walFrame' => $entry['walFrame'] ?? null,
                ];
            } else {
                $rejected[$pageNumber] = $reasons;
            }
        }

        ksort($sourceRows, SORT_NUMERIC);
        ksort($rejected, SORT_NUMERIC);

        $spill = SQLitePagerCacheSpillSavepointCurrentSourceNextPlan::currentSourceNext(
            $currentBytes,
            $pageSize,
            $savepointName,
            $savepoints,
            $eligible !== [] ? $eligible : [[
                'page' => 1,
                'image' => self::pageImage($currentBytes, 1, $pageSize),
                'current_image' => self::pageImage($currentBytes, 1, $pageSize),
                'dirty' => false,
            ]],
            $cacheSize,
            $spillThreshold,
            $journalMode,
            $journalSynced,
            $lockState,
            $cacheSpillEnabled,
            $maxSpillPages,
        );

        $spilled = $eligible === [] ? [] : $spill['spilled_page_numbers'];
        $eligiblePages = array_column($eligible, 'page');
        sort($eligiblePages, SORT_NUMERIC);

        return [
            'status' => $spilled !== []
                ? 'pager-cache-spill-savepoint-master-current-source-next141'
                : 'pager-cache-spill-savepoint-master-current-source-deferred-next141',
            'reason' => $spilled !== []
                ? 'master_journal_current_source_filters_cache_before_savepoint_spill'
                : 'master_journal_current_source_deferred_all_cache_spill_pages',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'savepoint' => $savepointName,
            'page_size' => $pageSize,
            'journal_mode' => strtolower(trim($journalMode)),
            'hot_cache_status' => $hot['status'],
            'cache_stale_rejected' => $hot['cache_stale_rejected'],
            'retained_cache_page_numbers' => $hot['retained_cache_page_numbers'],
            'refreshed_cache_page_numbers' => $hot['refreshed_cache_page_numbers'],
            'invalidated_cache_page_numbers' => $hot['invalidated_cache_page_numbers'],
            'current_source' => $hot['current_source'],
            'next_source' => $hot['next_source'],
            'eligible_page_numbers' => $eligiblePages,
            'master_rejected_page_numbers' => array_keys($rejected),
            'master_rejected_pages' => $rejected,
            'source_checks' => $sourceRows,
            'spill' => $spill,
            'spilled_page_numbers' => $spilled,
            'wal_frame_pages' => $eligible === [] ? [] : ($spill['wal_frame_pages'] ?? []),
            'operations' => array_values(array_merge(
                $hot['operations'] ?? [],
                self::filterOperations($eligiblePages, $rejected),
                $eligible === [] ? [] : ($spill['operations'] ?? [])
            )),
            'final_database_bytes' => $currentBytes,
            'source_digest' => hash('sha256', implode(',', $eligiblePages) . '|' . implode(',', array_keys($rejected)) . '|' . $nextSourceId),
            'dependencies' => array_values(array_unique(array_merge(
                $hot['dependencies'] ?? [],
                $spill['dependencies'] ?? [],
                [
                    'sqlite-pager-cache-spill-savepoint-master-current-source-next141',
                    'sqlite-pager-master-journal-hot-cache-current-source-next136',
                    'sqlite-pager-cache-spill-savepoint-current-source-next137',
                ]
            ))),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $cachePages
     * @return array<int,array{image:string,source_id:string,epoch:int,dirty:bool,pinned:bool,source:string,bytes?:int,journaled?:bool,walFrame?:int}>
     */
    private static function normalizeCachePages(array $cachePages, int $pageSize): array
    {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager cache-spill savepoint master current-source next141 page size must be a power of two at least 512');
        }
        ksort($cachePages, SORT_NUMERIC);
        $normalized = [];
        foreach ($cachePages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager cache-spill savepoint master current-source next141 cache page numbers must be one-based integers');
            }
            $image = $entry['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager cache-spill savepoint master current-source next141 cache page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = [
                'image' => $image,
                'source_id' => (string) ($entry['source_id'] ?? ''),
                'epoch' => (int) ($entry['epoch'] ?? 0),
                'dirty' => (bool) ($entry['dirty'] ?? true),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'source' => (string) ($entry['source'] ?? 'pager-cache'),
                'bytes' => isset($entry['bytes']) ? (int) $entry['bytes'] : null,
                'journaled' => isset($entry['journaled']) ? (bool) $entry['journaled'] : null,
                'walFrame' => isset($entry['walFrame']) ? (int) $entry['walFrame'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $cachePages
     * @return array<int,array{image:string,source_id:string,epoch:int,dirty:bool,pinned:bool,source:string}>
     */
    private static function hotCacheInput(array $cachePages): array
    {
        $input = [];
        foreach ($cachePages as $pageNumber => $entry) {
            $input[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'dirty' => $entry['dirty'],
                'pinned' => $entry['pinned'],
                'source' => $entry['source'],
            ];
        }

        return $input;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager cache-spill savepoint master current-source next141 read pages must be one-based integers');
            }
        }
    }

    /**
     * @param list<int> $eligiblePages
     * @param array<int,list<string>> $rejectedPages
     * @return list<array<string,mixed>>
     */
    private static function filterOperations(array $eligiblePages, array $rejectedPages): array
    {
        $operations = [];
        foreach ($eligiblePages as $page) {
            $operations[] = [
                'op' => 'admit_master_current_source_cache_spill_page',
                'page_number' => $page,
                'reason' => 'cache_page_source_matches_current_master_journal_before_savepoint_spill',
            ];
        }
        foreach ($rejectedPages as $page => $reasons) {
            $operations[] = [
                'op' => 'reject_master_current_source_cache_spill_page',
                'page_number' => $page,
                'reasons' => $reasons,
            ];
        }

        return $operations;
    }

    private static function pageImage(string $databaseBytes, int $pageNumber, int $pageSize): string
    {
        return str_pad(substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize), $pageSize, "\0");
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 56), "\0.");
    }
}
