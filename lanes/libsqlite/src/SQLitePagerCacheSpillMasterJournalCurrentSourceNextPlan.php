<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerCacheSpillMasterJournalCurrentSourceNextPlan
{
    /**
     * @param array<int,array{image:string,before_image?:string,master_member?:string,source_id?:string,epoch?:int,dirty?:bool,journaled?:bool,pinned?:bool,bytes?:int,walFrame?:int}> $cachePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $journalPath,
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        ?string $nextMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $cachePages,
        int $cacheSize,
        int $spillThreshold,
        string $journalMode = 'delete',
        bool $journalSynced = true,
        string $lockState = 'reserved',
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null,
        string $currentSourceId = 'master-journal-current-source',
        int $currentSourceEpoch = 1,
    ): array {
        if ($databasePath === '' || $journalPath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill master-journal current-source requires database, journal, and master journal paths');
        }
        if ($currentMasterJournalBytes === null || trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill master-journal current-source requires current master journal bytes');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill master-journal current-source requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager cache-spill master-journal current-source page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager cache-spill master-journal current-source database bytes must be page-size aligned');
        }
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager cache-spill master-journal current-source requires cache pages');
        }
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager cache-spill master-journal current-source requires a current source id');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager cache-spill master-journal current-source source epoch must be positive');
        }

        $cachedMembers = self::members($cachedMasterJournalBytes);
        $currentMembers = self::members($currentMasterJournalBytes);
        $nextMembers = self::members($nextMasterJournalBytes);
        $currentMember = in_array($journalPath, $currentMembers, true);
        $nextMember = $nextMembers === [] || in_array($journalPath, $nextMembers, true);
        if (!$currentMember) {
            throw new \RuntimeException('SQLite pager cache-spill master-journal current master journal does not reference the rollback journal');
        }

        $pageCount = (int) (strlen($databaseBytes) / $pageSize);
        $database = self::databaseMap($databaseBytes, $pageSize);
        $cachePages = self::normalizeCache($cachePages, $pageSize, $pageCount);
        $cachedStale = $cachedMembers !== $currentMembers;

        $operations = [];
        if ($cachedStale) {
            $operations[] = [
                'op' => 'discard_cached_master_journal_before_cache_spill',
                'path' => $masterJournalPath,
                'reason' => 'cached_master_journal_members_do_not_match_current_source',
            ];
        }
        $operations[] = [
            'op' => 'read_current_master_journal_before_cache_spill',
            'path' => $masterJournalPath,
            'member' => $journalPath,
            'bytes' => strlen($currentMasterJournalBytes),
        ];

        $admitted = [];
        $rejected = [];
        $rows = [];
        foreach ($cachePages as $pageNumber => $entry) {
            $beforeImage = $entry['before_image'] ?? $database[$pageNumber];
            $reasons = [];
            if (!$entry['dirty']) {
                $reasons[] = 'cache_page_clean';
            }
            if (!$entry['journaled']) {
                $reasons[] = 'missing_rollback_source';
            }
            if ($entry['pinned']) {
                $reasons[] = 'cache_page_pinned';
            }
            if ($entry['source_id'] !== $currentSourceId) {
                $reasons[] = 'stale_master_source_id';
            }
            if ($entry['epoch'] !== $currentSourceEpoch) {
                $reasons[] = 'stale_master_source_epoch';
            }
            if ($entry['master_member'] !== $journalPath) {
                $reasons[] = 'wrong_master_journal_member';
            }
            if (!$nextMember) {
                $reasons[] = 'journal_removed_from_next_master_source';
            }
            if ($beforeImage !== $database[$pageNumber]) {
                $reasons[] = 'before_image_mismatch_current_database';
            }

            $rows[$pageNumber] = [
                'page_number' => $pageNumber,
                'dirty' => $entry['dirty'],
                'journaled' => $entry['journaled'],
                'pinned' => $entry['pinned'],
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'master_member' => $entry['master_member'],
                'cache_prefix' => self::prefix($entry['image']),
                'before_prefix' => self::prefix($beforeImage),
                'database_prefix' => self::prefix($database[$pageNumber]),
                'matches_current_database' => $beforeImage === $database[$pageNumber],
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
                    'op' => 'admit_master_journal_cache_spill_page',
                    'page' => $pageNumber,
                    'reason' => 'dirty_page_before_image_matches_current_master_journal_source',
                ];
            } else {
                $rejected[$pageNumber] = $reasons;
                $operations[] = [
                    'op' => 'defer_master_journal_cache_spill_page',
                    'page' => $pageNumber,
                    'reasons' => $reasons,
                ];
            }
        }
        ksort($rows, SORT_NUMERIC);
        ksort($rejected, SORT_NUMERIC);

        $spill = SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext(
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

        return [
            'status' => $spilledPages === []
                ? 'pager_cache_spill_master_journal_current_source_deferred'
                : 'pager_cache_spill_master_journal_current_source',
            'reason' => $spilledPages === []
                ? 'cache_spill_deferred_after_master_journal_current_source_filter'
                : 'cache_spill_pages_admitted_from_current_master_journal_source',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'master_journal_path' => $masterJournalPath,
            'cached_members' => $cachedMembers,
            'current_members' => $currentMembers,
            'next_members' => $nextMembers,
            'cached_master_stale' => $cachedStale,
            'current_master_member' => $currentMember,
            'next_master_member' => $nextMember,
            'page_size' => $pageSize,
            'journal_mode' => strtolower(trim($journalMode)),
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentSourceEpoch,
            ],
            'admitted_page_numbers' => $admittedPages,
            'rejected_page_numbers' => $rejectedPages,
            'rejected_pages' => $rejected,
            'source_checks' => array_values($rows),
            'source_checks_by_page' => $rows,
            'spill' => $spill,
            'spilled_page_numbers' => $spilledPages,
            'wal_frame_pages' => $spill['next']['wal_frame_pages'] ?? [],
            'operations' => array_values(array_merge($operations, $spill['operations'] ?? [])),
            'source_digest' => hash('sha256', $databasePath . $journalPath . implode('|', $currentMembers) . implode('|', array_column($rows, 'database_prefix')) . implode(',', $spilledPages)),
            'dependencies' => array_values(array_unique(array_merge(
                $spill['dependencies'] ?? [],
                [
                    'sqlite-pager-cache-spill-master-journal-current-source',
                    'sqlite-master-journal-current-source-recheck',
                    'sqlite-pager-cache-spill-journalmode-current-source-next107',
                ]
            ))),
        ];
    }

    /**
     * @return list<string>
     */
    private static function members(?string $bytes): array
    {
        if ($bytes === null || trim($bytes) === '') {
            return [];
        }

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
     * @return array<int,string>
     */
    private static function databaseMap(string $databaseBytes, int $pageSize): array
    {
        $pages = [];
        $pageCount = (int) (strlen($databaseBytes) / $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[$pageNumber] = substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize);
        }

        return $pages;
    }

    /**
     * @param array<int,array<string,mixed>> $cachePages
     * @return array<int,array{image:string,before_image?:string,master_member:string,source_id:string,epoch:int,dirty:bool,journaled:bool,pinned:bool,bytes:int,walFrame?:int}>
     */
    private static function normalizeCache(array $cachePages, int $pageSize, int $pageCount): array
    {
        $normalized = [];
        foreach ($cachePages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager cache-spill master-journal current-source cache page numbers must be one-based integers');
            }
            if ($pageNumber > $pageCount) {
                throw new \InvalidArgumentException("SQLite pager cache-spill master-journal current-source cache page {$pageNumber} is outside the database image");
            }
            $image = $entry['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager cache-spill master-journal current-source cache page {$pageNumber} image must match page size");
            }
            if (isset($entry['before_image']) && (!is_string($entry['before_image']) || strlen($entry['before_image']) !== $pageSize)) {
                throw new \InvalidArgumentException("SQLite pager cache-spill master-journal current-source before image for page {$pageNumber} must match page size");
            }
            $masterMember = $entry['master_member'] ?? null;
            if (!is_string($masterMember) || $masterMember === '') {
                throw new \InvalidArgumentException("SQLite pager cache-spill master-journal current-source cache page {$pageNumber} master member must be non-empty");
            }
            $sourceId = $entry['source_id'] ?? 'master-journal-current-source';
            if (!is_string($sourceId) || $sourceId === '') {
                throw new \InvalidArgumentException("SQLite pager cache-spill master-journal current-source cache page {$pageNumber} source id must be non-empty");
            }
            $epoch = $entry['epoch'] ?? 1;
            if (!is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException("SQLite pager cache-spill master-journal current-source cache page {$pageNumber} epoch must be positive");
            }
            $bytes = $entry['bytes'] ?? $pageSize;
            if (!is_int($bytes) || $bytes < 0) {
                throw new \InvalidArgumentException("SQLite pager cache-spill master-journal current-source cache page {$pageNumber} bytes must be non-negative");
            }
            if (isset($entry['walFrame']) && (!is_int($entry['walFrame']) || $entry['walFrame'] < 1)) {
                throw new \InvalidArgumentException("SQLite pager cache-spill master-journal current-source cache page {$pageNumber} WAL frame must be positive");
            }

            $normalized[$pageNumber] = [
                'image' => $image,
                'master_member' => $masterMember,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'dirty' => (bool) ($entry['dirty'] ?? true),
                'journaled' => (bool) ($entry['journaled'] ?? true),
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'bytes' => $bytes,
            ];
            if (isset($entry['before_image'])) {
                $normalized[$pageNumber]['before_image'] = $entry['before_image'];
            }
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
