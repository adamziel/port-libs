<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $retryWrites
     * @param list<int> $nextReadPages
     * @return array<string,mixed>
     */
    public static function plan(
        int $pageSize,
        string $databasePath,
        string $journalPath,
        string $masterJournalPath,
        ?string $masterJournalBytes,
        string $currentDatabaseBytes,
        string $savepointName,
        array $hotJournalPages,
        array $retryWrites,
        array $nextReadPages,
        bool $reservedLock = false,
        bool $readOnly = false,
    ): array {
        if ($databasePath === '' || $journalPath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal master current-source next142 requires database, journal, and master-journal paths');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal master current-source next142 requires a savepoint name');
        }
        if ($readOnly) {
            throw new \RuntimeException('SQLite pager savepoint hot-journal master current-source next142 cannot recover in read-only mode');
        }
        if ($reservedLock) {
            throw new \RuntimeException('SQLite pager savepoint hot-journal master current-source next142 hot journal is not recoverable while a reserved lock is held');
        }
        if ($masterJournalBytes === null || trim($masterJournalBytes) === '') {
            throw new \RuntimeException('SQLite pager savepoint hot-journal master current-source next142 requires current master-journal bytes');
        }
        if (!in_array($journalPath, self::members($masterJournalBytes), true)) {
            throw new \RuntimeException('SQLite pager savepoint hot-journal master current-source next142 master journal does not name the database journal');
        }
        if ($currentDatabaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal master current-source next142 requires current database bytes');
        }
        if (strlen($currentDatabaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal master current-source next142 database bytes must be page-size aligned');
        }

        self::validatePageSize($pageSize);
        $hotJournalPages = self::normalizePages($hotJournalPages, $pageSize, 'hot journal');
        $retryWrites = self::normalizePages($retryWrites, $pageSize, 'retry write');
        $nextReadPages = self::normalizeReadPages($nextReadPages);
        if ($hotJournalPages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal master current-source next142 requires hot-journal pages');
        }
        if ($retryWrites === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal master current-source next142 requires retry writes');
        }
        if ($nextReadPages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal master current-source next142 requires next read pages');
        }

        $dirtyDatabase = self::bytesToPages($currentDatabaseBytes, $pageSize);
        $recoveredDatabase = $dirtyDatabase;
        $operations = [[
            'op' => 'read_master_journal',
            'path' => $masterJournalPath,
            'member' => $journalPath,
            'reason' => 'verify_hot_journal_is_current_master_member',
        ]];

        foreach ($hotJournalPages as $pageNumber => $image) {
            $recoveredDatabase[$pageNumber] = $image;
            $operations[] = [
                'op' => 'restore_hot_journal_page',
                'path' => $databasePath,
                'page_number' => $pageNumber,
                'reason' => 'master_journal_hot_recovery_before_savepoint_retry',
            ];
        }

        $beforeImages = [];
        $finalDatabase = $recoveredDatabase;
        foreach ($retryWrites as $pageNumber => $image) {
            $before = $recoveredDatabase[$pageNumber] ?? str_repeat("\0", $pageSize);
            $beforeImages[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_savepoint_before_image',
                'path' => $databasePath,
                'page_number' => $pageNumber,
                'source' => isset($recoveredDatabase[$pageNumber]) ? 'hot-journal-master-current-source' : 'zero-fill',
                'reason' => 'capture_retry_before_image_after_master_hot_recovery',
            ];
            $finalDatabase[$pageNumber] = $image;
            $operations[] = [
                'op' => 'write_retry_savepoint_page',
                'path' => $databasePath,
                'page_number' => $pageNumber,
                'reason' => 'retry_write_after_master_hot_recovery',
            ];
        }

        $rollbackDatabase = $finalDatabase;
        foreach ($beforeImages as $pageNumber => $image) {
            $rollbackDatabase[$pageNumber] = $image;
            $operations[] = [
                'op' => 'rollback_savepoint_before_image',
                'path' => $databasePath,
                'page_number' => $pageNumber,
                'reason' => 'rollback_retry_to_master_hot_current_source',
            ];
        }

        $releaseReads = [];
        foreach ($nextReadPages as $pageNumber) {
            $image = $rollbackDatabase[$pageNumber] ?? str_repeat("\0", $pageSize);
            $source = isset($rollbackDatabase[$pageNumber]) ? 'master-hot-current-source' : 'zero-fill';
            $releaseReads[] = [
                'page_number' => $pageNumber,
                'source' => $source,
                'prefix' => self::prefix($image),
                'matches_hot_journal' => isset($hotJournalPages[$pageNumber]) && $hotJournalPages[$pageNumber] === $image,
                'matches_dirty_current' => isset($dirtyDatabase[$pageNumber]) && $dirtyDatabase[$pageNumber] === $image,
                'zero_filled_short_read' => !isset($rollbackDatabase[$pageNumber]),
            ];
            $operations[] = [
                'op' => 'release_read_current_source_page',
                'path' => $databasePath,
                'page_number' => $pageNumber,
                'source' => $source,
                'reason' => 'next_read_after_savepoint_rollback_uses_master_hot_current_source',
            ];
        }

        $payloadKey = $databasePath . '#pager-savepoint-hot-journal-master-current-source-next142';
        $rollbackPayloadKey = $databasePath . '#pager-savepoint-hot-journal-master-current-source-rollback-next142';

        return [
            'status' => 'pager_savepoint_hot_journal_master_current_source_next142',
            'reason' => 'savepoint_retry_before_images_follow_master_hot_journal_recovery',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'master_journal_path' => $masterJournalPath,
            'master_members' => self::members($masterJournalBytes),
            'savepoint' => [
                'name' => $savepointName,
                'retry_page_numbers' => array_keys($retryWrites),
                'captured_page_numbers' => array_keys($beforeImages),
                'rollback_restored_page_numbers' => array_keys($beforeImages),
            ],
            'hot_journal_page_numbers' => array_keys($hotJournalPages),
            'captured_sources' => self::capturedSources($beforeImages, $recoveredDatabase),
            'dirty_prefixes' => self::prefixes($dirtyDatabase),
            'hot_recovered_prefixes' => self::prefixes($recoveredDatabase),
            'retry_prefixes' => self::prefixes($finalDatabase),
            'rollback_prefixes' => self::prefixes($rollbackDatabase),
            'release_reads' => $releaseReads,
            'operations' => $operations,
            'payloads' => [
                $payloadKey => self::pagesToBytes($finalDatabase, $pageSize),
                $rollbackPayloadKey => self::pagesToBytes($rollbackDatabase, $pageSize),
            ],
            'source_digest' => hash('sha256', implode('|', self::prefixes($rollbackDatabase)) . '|' . $masterJournalPath . '|' . $savepointName),
            'dependencies' => [
                'sqlite-pager-savepoint-hot-journal-master-current-source-next142',
                'sqlite-master-journal-current-source-member-validation',
                'sqlite-hot-journal-before-savepoint-retry',
                'sqlite-savepoint-before-image-after-hot-journal-recovery',
            ],
        ];
    }

    private static function validatePageSize(int $pageSize): void
    {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint hot-journal master current-source next142 page size must be a power of two at least 512');
        }
    }

    /**
     * @return list<string>
     */
    private static function members(string $masterJournalBytes): array
    {
        $members = [];
        foreach (preg_split('/\r?\n/', $masterJournalBytes) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !in_array($line, $members, true)) {
                $members[] = $line;
            }
        }

        return $members;
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizePages(array $pages, int $pageSize, string $label): array
    {
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager savepoint hot-journal master current-source next142 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint hot-journal master current-source next142 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param list<int> $pages
     * @return list<int>
     */
    private static function normalizeReadPages(array $pages): array
    {
        $normalized = [];
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager savepoint hot-journal master current-source next142 read page numbers must be one-based integers');
            }
            if (!in_array($pageNumber, $normalized, true)) {
                $normalized[] = $pageNumber;
            }
        }
        sort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @return array<int,string>
     */
    private static function bytesToPages(string $bytes, int $pageSize): array
    {
        $pages = [];
        $count = intdiv(strlen($bytes), $pageSize);
        for ($i = 0; $i < $count; $i++) {
            $pages[$i + 1] = substr($bytes, $i * $pageSize, $pageSize);
        }

        return $pages;
    }

    /**
     * @param array<int,string> $pages
     */
    private static function pagesToBytes(array $pages, int $pageSize): string
    {
        if ($pages === []) {
            return '';
        }

        $max = max(array_keys($pages));
        $bytes = '';
        for ($page = 1; $page <= $max; $page++) {
            $bytes .= $pages[$page] ?? str_repeat("\0", $pageSize);
        }

        return $bytes;
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function prefixes(array $pages): array
    {
        $prefixes = [];
        foreach ($pages as $pageNumber => $image) {
            $prefixes[$pageNumber] = self::prefix($image);
        }

        return $prefixes;
    }

    /**
     * @param array<int,string> $beforeImages
     * @param array<int,string> $recoveredDatabase
     * @return array<int,string>
     */
    private static function capturedSources(array $beforeImages, array $recoveredDatabase): array
    {
        $sources = [];
        foreach ($beforeImages as $pageNumber => $image) {
            $sources[$pageNumber] = isset($recoveredDatabase[$pageNumber]) && $recoveredDatabase[$pageNumber] === $image
                ? 'hot-journal-master-current-source'
                : 'zero-fill';
        }

        return $sources;
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 56), "\0.");
    }
}
