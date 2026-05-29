<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalMasterSavepointCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $currentCache
     * @param array<int,string> $savepointWrites
     * @param list<int> $readPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        ?string $nextMasterJournalBytes,
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        string $savepoint,
        array $currentCache,
        array $savepointWrites,
        array $readPages,
        bool $databaseReservedLock = false,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 requires a database path');
        }
        if ($masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 requires a master-journal path');
        }
        if ($currentMasterJournalBytes === null || trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 requires current master-journal bytes');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 requires a savepoint name');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 requires rollback-journal bytes');
        }
        if ($journal->toBytes() !== $journalBytes) {
            throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 journal bytes do not match parsed journal');
        }
        if ($currentCache === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 requires current cache pages');
        }
        if ($savepointWrites === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 requires savepoint writes');
        }
        if ($readPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 requires read pages');
        }

        $pageSize = $journal->header->pageSize;
        self::assertPages($currentCache, $pageSize, 'cache');
        self::assertPages($savepointWrites, $pageSize, 'savepoint');
        self::assertReadPages($readPages);
        self::assertDatabase($databaseBytes, $pageSize);

        $journalPath = $databasePath . '-journal';
        $cachedMembers = self::members($cachedMasterJournalBytes);
        $currentMembers = self::members($currentMasterJournalBytes);
        $nextMembers = self::members($nextMasterJournalBytes);
        $currentMember = in_array($journalPath, $currentMembers, true);
        $nextMember = in_array($journalPath, $nextMembers, true);
        if (!$currentMember) {
            throw new \RuntimeException('SQLite pager hot-journal master savepoint next134 current master journal does not name the database journal');
        }

        $cachedStale = $cachedMembers !== $currentMembers || !$nextMember;
        $hot = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes, $databaseReservedLock, true, $nextMember);
        $hotRecovered = (bool) $hot['recovered'];
        $recoveredDatabaseBytes = $hotRecovered ? $hot['database_bytes'] : $databaseBytes;
        $recoveredPages = self::databasePages($recoveredDatabaseBytes, $pageSize);

        $invalidated = [];
        foreach ($currentCache as $pageNumber => $image) {
            $reason = array_key_exists($pageNumber, $recoveredPages)
                ? 'hot_journal_recovered_page'
                : 'stale_master_journal_current_source';
            $invalidated[] = [
                'page_number' => $pageNumber,
                'reason' => $reason,
                'bytes' => strlen($image),
            ];
        }

        $beforeImages = [];
        $afterWrites = $recoveredPages;
        foreach ($savepointWrites as $pageNumber => $image) {
            $beforeImages[$pageNumber] = $afterWrites[$pageNumber] ?? str_repeat("\0", $pageSize);
            $afterWrites[$pageNumber] = $image;
        }
        ksort($afterWrites, SORT_NUMERIC);

        $afterRollback = $afterWrites;
        foreach ($beforeImages as $pageNumber => $image) {
            $afterRollback[$pageNumber] = $image;
        }
        ksort($afterRollback, SORT_NUMERIC);

        $reads = [];
        foreach ($readPages as $pageNumber) {
            $image = $afterRollback[$pageNumber] ?? str_repeat("\0", $pageSize);
            $reads[] = [
                'page_number' => $pageNumber,
                'source' => array_key_exists($pageNumber, $beforeImages)
                    ? 'savepoint-rollback-before-image'
                    : ($hotRecovered ? 'hot-journal-recovered-current-source' : 'current-database-source'),
                'zero_filled_short_read' => !array_key_exists($pageNumber, $afterRollback),
                'label' => self::label($image),
            ];
        }

        $operations = [];
        if ($cachedStale) {
            $operations[] = [
                'op' => 'discard_cached_master_journal_source',
                'path' => $masterJournalPath,
                'reason' => 'cached_master_journal_members_do_not_match_current_source_next134',
            ];
        }
        $operations[] = [
            'op' => 'read_current_master_journal',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
            'reason' => 'read_current_master_journal_before_hot_journal_savepoint_next134',
        ];
        if ($hotRecovered) {
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($recoveredDatabaseBytes),
                'payload_key' => $databasePath . '#hot-journal-next134',
                'reason' => 'restore_hot_journal_current_source_before_savepoint_next134',
            ];
            $operations[] = [
                'op' => 'delete',
                'path' => $journalPath,
                'reason' => 'delete_hot_journal_after_master_current_source_recovery_next134',
            ];
        }
        foreach ($savepointWrites as $pageNumber => $image) {
            $operations[] = [
                'op' => 'capture_savepoint_before_image',
                'page_number' => $pageNumber,
                'bytes' => strlen($beforeImages[$pageNumber]),
                'reason' => 'capture_from_hot_recovered_master_current_source_next134',
            ];
            $operations[] = [
                'op' => 'write_savepoint_page',
                'page_number' => $pageNumber,
                'bytes' => strlen($image),
                'reason' => 'write_page_inside_open_savepoint_next134',
            ];
        }
        foreach ($beforeImages as $pageNumber => $image) {
            $operations[] = [
                'op' => 'rollback_savepoint_before_image',
                'page_number' => $pageNumber,
                'bytes' => strlen($image),
                'reason' => 'rollback_open_savepoint_after_hot_journal_master_source_next134',
            ];
        }

        return [
            'status' => $hotRecovered
                ? 'pager_hot_journal_master_savepoint_current_source_next134'
                : 'pager_hot_journal_master_savepoint_current_source_blocked_next134',
            'reason' => $hotRecovered
                ? ($cachedStale ? 'stale_master_cache_rejected_before_savepoint_current_source' : 'master_cache_matches_current_savepoint_source')
                : $hot['reason'],
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'master_journal_path' => $masterJournalPath,
            'savepoint' => $savepoint,
            'page_size' => $pageSize,
            'cached_stale_rejected' => $cachedStale,
            'cached_members' => $cachedMembers,
            'current_members' => $currentMembers,
            'next_members' => $nextMembers,
            'current_master_member' => $currentMember,
            'next_master_member' => $nextMember,
            'hot_recovered' => $hotRecovered,
            'hot_journal_reason' => $hot['hot_journal']['reason'],
            'journal_action' => $hot['journal_action'],
            'invalidated_cache_pages' => array_column($invalidated, 'page_number'),
            'invalidated_cache_entries' => $invalidated,
            'recovered_page_numbers' => array_keys($recoveredPages),
            'savepoint_written_pages' => array_keys($savepointWrites),
            'savepoint_rollback_pages' => array_keys($beforeImages),
            'read_page_numbers' => $readPages,
            'read_sources' => array_column($reads, 'source'),
            'read_labels' => array_column($reads, 'label'),
            'reads' => $reads,
            'before_image_labels' => self::labels($beforeImages),
            'after_write_labels' => self::labels($afterWrites),
            'after_rollback_labels' => self::labels($afterRollback),
            'hot_recovered_labels' => self::labels($recoveredPages),
            'operations' => $operations,
            'payloads' => $hotRecovered ? [$databasePath . '#hot-journal-next134' => $recoveredDatabaseBytes] : [],
            'hot_journal' => $hot,
            'dependencies' => [
                'sqlite-pager-hot-journal-master-savepoint-current-source-next134',
                'sqlite-rollback-journal-hot-recovery',
                'sqlite-master-journal-current-source-recheck',
                'sqlite-savepoint-before-image-current-source',
            ],
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
            if ($line !== '') {
                $members[$line] = $line;
            }
        }

        return array_values($members);
    }

    /**
     * @param array<int,string> $pages
     */
    private static function assertPages(array $pages, int $pageSize, string $label): void
    {
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal master savepoint next134 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal master savepoint next134 {$label} page {$pageNumber} image must match page size");
            }
        }
    }

    /**
     * @param list<int> $pages
     */
    private static function assertReadPages(array $pages): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 read pages must be one-based integers');
            }
        }
    }

    private static function assertDatabase(string $databaseBytes, int $pageSize): void
    {
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager hot-journal master savepoint next134 database bytes must be page aligned');
        }
    }

    /**
     * @return array<int,string>
     */
    private static function databasePages(string $databaseBytes, int $pageSize): array
    {
        $pages = [];
        $count = intdiv(strlen($databaseBytes), $pageSize);
        for ($i = 0; $i < $count; $i++) {
            $pages[$i + 1] = substr($databaseBytes, $i * $pageSize, $pageSize);
        }

        return $pages;
    }

    private static function label(string $image): string
    {
        return rtrim(strtok($image, "\0") ?: $image, ".\0");
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function labels(array $pages): array
    {
        $labels = [];
        foreach ($pages as $pageNumber => $image) {
            $labels[$pageNumber] = self::label($image);
        }

        return $labels;
    }
}
