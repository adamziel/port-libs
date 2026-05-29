<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNext130Plan
{
    /**
     * @param list<string> $cachedMasterMembers
     * @param list<string> $currentMasterMembers
     * @param array<string,array<int,string>> $databaseImages
     * @param array<string,array<int,string>> $masterRecoveredPages
     * @param array<string,array<int,string>> $savepointBeforeImages
     * @param array<string,array<int,string>> $savepointWrites
     * @param array<string,array<int,string>> $retryWrites
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
        int $pageSize,
        string $masterJournalPath,
        string $savepointName,
        array $cachedMasterMembers,
        array $currentMasterMembers,
        array $databaseImages,
        array $masterRecoveredPages,
        array $savepointBeforeImages,
        array $savepointWrites,
        array $retryWrites,
        bool $releaseAfterRetry = false
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal recovery current-source page size must be a power of two at least 512');
        }
        if ($masterJournalPath === '' || $savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal recovery current-source requires a master-journal path and savepoint name');
        }
        if ($currentMasterMembers === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal recovery current-source requires current master-journal members');
        }
        if ($databaseImages === [] || $masterRecoveredPages === [] || $savepointBeforeImages === [] || $savepointWrites === [] || $retryWrites === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal recovery current-source requires database, recovery, savepoint, and retry pages');
        }

        $cachedMasterMembers = self::normalizeMembers($cachedMasterMembers, 'cached');
        $currentMasterMembers = self::normalizeMembers($currentMasterMembers, 'current');
        $staleCachedMembers = array_values(array_diff($cachedMasterMembers, $currentMasterMembers));
        $newCurrentMembers = array_values(array_diff($currentMasterMembers, $cachedMasterMembers));

        $sources = [];
        $operations = [[
            'op' => 'read_current_master_journal_members',
            'path' => $masterJournalPath,
            'member_count' => count($currentMasterMembers),
            'reason' => 'discard_cached_master_journal_members_before_savepoint_recovery',
        ]];

        if ($staleCachedMembers !== []) {
            $operations[] = [
                'op' => 'discard_cached_master_journal_members',
                'members' => $staleCachedMembers,
                'reason' => 'cached_master_journal_members_are_not_current_recovery_source',
            ];
        }

        foreach ($databaseImages as $databasePath => $pages) {
            if (!is_string($databasePath) || $databasePath === '') {
                throw new \InvalidArgumentException('SQLite pager savepoint master-journal recovery current-source database paths must be non-empty strings');
            }
            $journalPath = $databasePath . '-journal';
            if (!in_array($journalPath, $currentMasterMembers, true)) {
                throw new \RuntimeException("SQLite pager savepoint master-journal recovery current-source missing current master member for {$databasePath}");
            }
            $sources[$databasePath] = [];
            foreach (self::normalizePages($pages, $pageSize, "{$databasePath} database") as $pageNumber => $image) {
                $sources[$databasePath][$pageNumber] = [
                    'image' => $image,
                    'source' => 'stale-database-before-master-recovery',
                ];
            }
        }

        foreach ($masterRecoveredPages as $databasePath => $pages) {
            if (!isset($sources[$databasePath])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal recovery current-source recovered database {$databasePath} is not open");
            }
            foreach (self::normalizePages($pages, $pageSize, "{$databasePath} recovered") as $pageNumber => $image) {
                if (!isset($sources[$databasePath][$pageNumber])) {
                    throw new \InvalidArgumentException("SQLite pager savepoint master-journal recovery current-source recovered page {$pageNumber} is outside {$databasePath}");
                }
                $sources[$databasePath][$pageNumber] = [
                    'image' => $image,
                    'source' => 'master-journal-recovered-current-source',
                ];
                $operations[] = [
                    'op' => 'restore_master_journal_page',
                    'path' => $databasePath,
                    'page_number' => $pageNumber,
                    'reason' => 'recover_current_source_before_savepoint_rollback',
                ];
            }
        }

        $recoveredSources = $sources;
        $recoveredBytes = self::bytesByDatabase($sources, $pageSize);
        $capturedBefore = [];
        foreach ($savepointBeforeImages as $databasePath => $pages) {
            if (!isset($sources[$databasePath])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal recovery current-source savepoint database {$databasePath} is not open");
            }
            foreach (self::normalizePages($pages, $pageSize, "{$databasePath} savepoint-before") as $pageNumber => $image) {
                $actual = $sources[$databasePath][$pageNumber]['image'] ?? null;
                if ($actual !== $image) {
                    throw new \RuntimeException("SQLite pager savepoint master-journal recovery current-source savepoint page {$pageNumber} for {$databasePath} is not captured from recovered current source");
                }
                $capturedBefore[$databasePath][$pageNumber] = $image;
                $operations[] = [
                    'op' => 'record_savepoint_before_image',
                    'path' => $databasePath,
                    'savepoint' => $savepointName,
                    'page_number' => $pageNumber,
                    'source' => $sources[$databasePath][$pageNumber]['source'],
                    'reason' => 'savepoint_before_image_uses_master_recovered_current_source',
                ];
            }
        }

        foreach ($savepointWrites as $databasePath => $pages) {
            if (!isset($sources[$databasePath])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal recovery current-source write database {$databasePath} is not open");
            }
            foreach (self::normalizePages($pages, $pageSize, "{$databasePath} savepoint-write") as $pageNumber => $image) {
                if (!isset($capturedBefore[$databasePath][$pageNumber])) {
                    throw new \InvalidArgumentException("SQLite pager savepoint master-journal recovery current-source savepoint write page {$pageNumber} for {$databasePath} needs a before image");
                }
                $sources[$databasePath][$pageNumber] = [
                    'image' => $image,
                    'source' => 'savepoint-write-before-rollback-to',
                ];
                $operations[] = [
                    'op' => 'write_savepoint_page',
                    'path' => $databasePath,
                    'savepoint' => $savepointName,
                    'page_number' => $pageNumber,
                    'reason' => 'write_after_master_recovery_inside_open_savepoint',
                ];
            }
        }
        $dirtySources = $sources;
        $dirtyBytes = self::bytesByDatabase($sources, $pageSize);

        foreach ($capturedBefore as $databasePath => $pages) {
            foreach ($pages as $pageNumber => $image) {
                $sources[$databasePath][$pageNumber] = [
                    'image' => $image,
                    'source' => 'rollback-to-savepoint-master-recovered-before-image',
                ];
                $operations[] = [
                    'op' => 'rollback_to_savepoint_before_image',
                    'path' => $databasePath,
                    'savepoint' => $savepointName,
                    'page_number' => $pageNumber,
                    'reason' => 'rollback_to_restores_master_recovered_current_source',
                ];
            }
        }
        $rollbackBytes = self::bytesByDatabase($sources, $pageSize);

        foreach ($retryWrites as $databasePath => $pages) {
            if (!isset($sources[$databasePath])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal recovery current-source retry database {$databasePath} is not open");
            }
            foreach (self::normalizePages($pages, $pageSize, "{$databasePath} retry") as $pageNumber => $image) {
                if (!isset($sources[$databasePath][$pageNumber])) {
                    $sources[$databasePath][$pageNumber] = [
                        'image' => str_repeat("\0", $pageSize),
                        'source' => 'zero-fill-current-source',
                    ];
                }
                $operations[] = [
                    'op' => 'capture_retry_before_image',
                    'path' => $databasePath,
                    'savepoint' => $savepointName,
                    'page_number' => $pageNumber,
                    'source' => $sources[$databasePath][$pageNumber]['source'],
                    'reason' => 'retry_statement_captures_post_rollback_current_source',
                ];
                $sources[$databasePath][$pageNumber] = [
                    'image' => $image,
                    'source' => 'retry-write-after-master-savepoint-recovery',
                ];
                $operations[] = [
                    'op' => 'write_retry_page',
                    'path' => $databasePath,
                    'savepoint' => $savepointName,
                    'page_number' => $pageNumber,
                    'reason' => 'retry_write_uses_recovered_current_source_after_rollback_to',
                ];
            }
        }

        $releaseMergedPages = [];
        if ($releaseAfterRetry) {
            foreach ($sources as $databasePath => $pages) {
                foreach ($pages as $pageNumber => $entry) {
                    if ($entry['source'] === 'retry-write-after-master-savepoint-recovery') {
                        $releaseMergedPages[$databasePath][] = $pageNumber;
                    }
                }
                if (isset($releaseMergedPages[$databasePath])) {
                    sort($releaseMergedPages[$databasePath], SORT_NUMERIC);
                }
            }
            $operations[] = [
                'op' => 'release_savepoint_after_retry',
                'savepoint' => $savepointName,
                'merged_pages' => $releaseMergedPages,
                'reason' => 'release_keeps_retry_pages_after_master_recovery_rollback_to',
            ];
        }

        return [
            'status' => 'pager_savepoint_master_journal_recovery_current_source_next130',
            'reason' => 'master_journal_recovery_establishes_current_source_before_rollback_to_savepoint_retry',
            'page_size' => $pageSize,
            'master_journal_path' => $masterJournalPath,
            'savepoint' => $savepointName,
            'cached_master_members' => $cachedMasterMembers,
            'current_master_members' => $currentMasterMembers,
            'stale_cached_members' => $staleCachedMembers,
            'new_current_members' => $newCurrentMembers,
            'database_paths' => array_keys($sources),
            'master_recovered_page_numbers' => self::pageNumbersByDatabase($masterRecoveredPages, $pageSize, 'master-recovered'),
            'savepoint_before_page_numbers' => self::pageNumbersByDatabase($capturedBefore, $pageSize, 'captured-before'),
            'savepoint_write_page_numbers' => self::pageNumbersByDatabase($savepointWrites, $pageSize, 'savepoint-write'),
            'retry_write_page_numbers' => self::pageNumbersByDatabase($retryWrites, $pageSize, 'retry-write'),
            'release_merged_page_numbers' => $releaseMergedPages,
            'recovered_prefixes' => self::prefixesByDatabase($recoveredSources, $pageSize),
            'dirty_prefixes' => self::prefixesByDatabase($dirtySources, $pageSize, $savepointWrites),
            'rollback_prefixes' => self::prefixesByDatabase(self::sourcesFromBytes($rollbackBytes, $pageSize), $pageSize),
            'final_prefixes' => self::prefixesByDatabase($sources, $pageSize),
            'final_sources' => self::sourcesByDatabase($sources),
            'recovered_database_bytes' => $recoveredBytes,
            'dirty_database_bytes' => $dirtyBytes,
            'rollback_database_bytes' => $rollbackBytes,
            'final_database_bytes' => self::bytesByDatabase($sources, $pageSize),
            'current_source_verified' => true,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-savepoint-master-journal-recovery-current-source-next130',
                'sqlite-master-journal-current-source-before-rollback-to-savepoint',
                'sqlite-savepoint-rollback-restores-master-recovered-images',
                'sqlite-retry-captures-post-rollback-current-source',
            ],
        ];
    }

    /**
     * @param list<string> $members
     * @return list<string>
     */
    private static function normalizeMembers(array $members, string $label): array
    {
        $normalized = [];
        foreach ($members as $member) {
            if (!is_string($member) || $member === '') {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal recovery {$label} members must be non-empty strings");
            }
            $normalized[] = $member;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePages(array $pages, int $pageSize, string $label): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException("SQLite pager savepoint master-journal recovery {$label} pages must not be empty");
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal recovery {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal recovery {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param array<string,array<int,array{image:string,source:string}>> $sources
     * @return array<string,string>
     */
    private static function bytesByDatabase(array $sources, int $pageSize): array
    {
        $bytes = [];
        foreach ($sources as $databasePath => $pages) {
            ksort($pages, SORT_NUMERIC);
            $max = max(array_keys($pages));
            $image = '';
            for ($pageNumber = 1; $pageNumber <= $max; $pageNumber++) {
                $image .= $pages[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            }
            $bytes[$databasePath] = $image;
        }

        return $bytes;
    }

    /**
     * @param array<string,string> $databaseBytes
     * @return array<string,array<int,array{image:string,source:string}>>
     */
    private static function sourcesFromBytes(array $databaseBytes, int $pageSize): array
    {
        $sources = [];
        foreach ($databaseBytes as $databasePath => $bytes) {
            $pageCount = intdiv(strlen($bytes), $pageSize);
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $sources[$databasePath][$pageNumber] = [
                    'image' => substr($bytes, ($pageNumber - 1) * $pageSize, $pageSize),
                    'source' => 'rollback-preview',
                ];
            }
        }

        return $sources;
    }

    /**
     * @param array<string,array<int,string>> $pagesByDatabase
     * @return array<string,list<int>>
     */
    private static function pageNumbersByDatabase(array $pagesByDatabase, int $pageSize, string $label): array
    {
        $numbers = [];
        foreach ($pagesByDatabase as $databasePath => $pages) {
            $normalized = self::normalizePages($pages, $pageSize, "{$label} {$databasePath}");
            $numbers[$databasePath] = array_keys($normalized);
        }

        return $numbers;
    }

    /**
     * @param array<string,array<int,array{image:string,source:string}>> $sources
     * @param array<string,array<int,string>>|null $onlyPages
     * @return array<string,array<int,string>>
     */
    private static function prefixesByDatabase(array $sources, int $pageSize, ?array $onlyPages = null): array
    {
        $prefixes = [];
        foreach ($sources as $databasePath => $pages) {
            $pageNumbers = isset($onlyPages[$databasePath])
                ? array_keys($onlyPages[$databasePath])
                : array_keys($pages);
            sort($pageNumbers, SORT_NUMERIC);
            foreach ($pageNumbers as $pageNumber) {
                $image = $pages[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
                $prefixes[$databasePath][$pageNumber] = rtrim(substr($image, 0, 64), "\0.");
            }
        }

        return $prefixes;
    }

    /**
     * @param array<string,array<int,array{image:string,source:string}>> $sources
     * @return array<string,array<int,string>>
     */
    private static function sourcesByDatabase(array $sources): array
    {
        $result = [];
        foreach ($sources as $databasePath => $pages) {
            ksort($pages, SORT_NUMERIC);
            foreach ($pages as $pageNumber => $entry) {
                $result[$databasePath][$pageNumber] = $entry['source'];
            }
        }

        return $result;
    }
}
