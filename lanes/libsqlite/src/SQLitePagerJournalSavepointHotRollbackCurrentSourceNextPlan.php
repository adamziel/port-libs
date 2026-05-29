<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerJournalSavepointHotRollbackCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $savepointBeforeImages
     * @param array<int,string> $retryWrites
     * @return array<string,mixed>
     */
    public static function currentSourceNext(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        int $pageSize,
        string $savepointName,
        array $savepointBeforeImages,
        array $retryWrites,
        ?string $masterJournalPath = null,
        ?string $masterJournalBytes = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager journal savepoint hot rollback next118 requires a database path');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager journal savepoint hot rollback next118 database bytes must be page-size aligned');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager journal savepoint hot rollback next118 requires rollback-journal bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager journal savepoint hot rollback next118 page size must be a power of two at least 512');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager journal savepoint hot rollback next118 requires a savepoint name');
        }
        if ($savepointBeforeImages === [] || $retryWrites === []) {
            throw new \InvalidArgumentException('SQLite pager journal savepoint hot rollback next118 requires savepoint before-images and retry writes');
        }

        $savepointBeforeImages = self::normalizePages($savepointBeforeImages, $pageSize, 'savepoint before-image');
        $retryWrites = self::normalizePages($retryWrites, $pageSize, 'retry write');
        foreach ($retryWrites as $pageNumber => $_image) {
            if (!isset($savepointBeforeImages[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager journal savepoint hot rollback next118 retry page {$pageNumber} requires a recovered before-image");
            }
        }

        $journalPath = $databasePath . '-journal';
        $masterMembers = self::masterJournalMembers($masterJournalBytes ?? '');
        $listedInMaster = $masterJournalPath === null
            || $masterMembers === []
            || in_array($journalPath, $masterMembers, true);

        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        $hot = $journal->hotJournalRecoveryResult(
            $databaseBytes,
            $journalBytes,
            $reservedLock,
            $requiresSuperJournal,
            $requiresSuperJournal ? (bool) $superJournalExists : $listedInMaster
        );

        $recoveredBytes = (string) $hot['database_bytes'];
        $operations = [];
        $payloads = [];
        $sourceMismatches = [];

        if ($hot['recovered']) {
            $payloads[$databasePath . '#hot-rollback-current-source-next118'] = $recoveredBytes;
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'payload_key' => $databasePath . '#hot-rollback-current-source-next118',
                'offset' => 0,
                'bytes' => strlen($recoveredBytes),
                'reason' => 'restore_hot_rollback_before_savepoint_retry_current_source',
            ];
            $operations[] = [
                'op' => 'delete',
                'path' => $journalPath,
                'reason' => 'delete_hot_journal_before_savepoint_retry_current_source',
            ];
        }

        foreach ($savepointBeforeImages as $pageNumber => $beforeImage) {
            $currentImage = self::pageImage($recoveredBytes, $pageNumber, $pageSize);
            if ($beforeImage !== $currentImage) {
                $sourceMismatches[] = $pageNumber;
            }
            $operations[] = [
                'op' => 'capture_savepoint_before_image',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'source' => 'hot-rollback-current-source',
                'matches_recovered_current' => $beforeImage === $currentImage,
                'reason' => 'savepoint_before_image_must_use_recovered_current_source',
            ];
        }

        $retryBytes = $recoveredBytes;
        foreach ($retryWrites as $pageNumber => $pageImage) {
            $retryBytes = self::replacePage($retryBytes, $pageNumber, $pageSize, $pageImage);
            $operations[] = [
                'op' => 'write_savepoint_retry_page',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'reason' => 'retry_statement_writes_after_hot_rollback_current_source',
            ];
        }

        $rollbackBytes = $retryBytes;
        foreach ($savepointBeforeImages as $pageNumber => $beforeImage) {
            $rollbackBytes = self::replacePage($rollbackBytes, $pageNumber, $pageSize, $beforeImage);
            $operations[] = [
                'op' => 'rollback_savepoint_retry_page',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'reason' => 'rollback_to_savepoint_restores_hot_rollback_current_source',
            ];
        }

        $currentSourceVerified = $hot['recovered'] === true && $sourceMismatches === [] && $listedInMaster;

        return [
            'status' => $currentSourceVerified
                ? 'pager_journal_savepoint_hot_rollback_current_source_next118'
                : 'pager_journal_savepoint_hot_rollback_current_source_blocked_next118',
            'reason' => $currentSourceVerified
                ? 'savepoint_retry_uses_hot_rollback_current_source'
                : 'savepoint_retry_current_source_not_verified_after_hot_rollback',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'master_journal_path' => $masterJournalPath,
            'master_journal_members' => $masterMembers,
            'listed_in_master_journal' => $listedInMaster,
            'savepoint' => $savepointName,
            'hot_recovered' => $hot['recovered'],
            'hot_journal_reason' => $hot['hot_journal']['reason'],
            'journal_action' => $hot['journal_action'],
            'current_source_verified' => $currentSourceVerified,
            'source_mismatch_pages' => $sourceMismatches,
            'savepoint_before_page_numbers' => array_keys($savepointBeforeImages),
            'retry_page_numbers' => array_keys($retryWrites),
            'recovered_prefixes' => self::prefixes($recoveredBytes, array_keys($savepointBeforeImages), $pageSize),
            'retry_prefixes' => self::prefixes($retryBytes, array_keys($retryWrites), $pageSize),
            'rollback_prefixes' => self::prefixes($rollbackBytes, array_keys($savepointBeforeImages), $pageSize),
            'dirty_prefixes' => self::prefixes($databaseBytes, array_keys($savepointBeforeImages), $pageSize),
            'recovered_database_bytes' => $recoveredBytes,
            'retry_database_bytes' => $retryBytes,
            'rollback_database_bytes' => $rollbackBytes,
            'images_match_after_rollback' => $rollbackBytes === $recoveredBytes,
            'operations' => $operations,
            'payloads' => $payloads,
            'dependencies' => [
                'sqlite-pager-journal-savepoint-hot-rollback-current-source-next118',
                'sqlite-rollback-journal-hot-recovery',
                'sqlite-savepoint-page-image-rollback',
                'sqlite-current-source-before-image-guard',
            ],
        ];
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
                throw new \InvalidArgumentException("SQLite pager journal savepoint hot rollback next118 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager journal savepoint hot rollback next118 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private static function masterJournalMembers(string $bytes): array
    {
        $members = [];
        foreach (preg_split('/\r?\n/', $bytes) ?: [] as $line) {
            $member = trim($line);
            if ($member !== '') {
                $members[$member] = $member;
            }
        }

        return array_values($members);
    }

    private static function pageImage(string $databaseBytes, int $pageNumber, int $pageSize): string
    {
        $offset = ($pageNumber - 1) * $pageSize;

        return str_pad(substr($databaseBytes, $offset, $pageSize), $pageSize, "\0");
    }

    private static function replacePage(string $databaseBytes, int $pageNumber, int $pageSize, string $image): string
    {
        $offset = ($pageNumber - 1) * $pageSize;
        if ($offset >= strlen($databaseBytes)) {
            $databaseBytes = str_pad($databaseBytes, $offset + $pageSize, "\0");
        }

        return substr_replace($databaseBytes, $image, $offset, $pageSize);
    }

    /**
     * @param list<int> $pageNumbers
     * @return array<int,string>
     */
    private static function prefixes(string $databaseBytes, array $pageNumbers, int $pageSize): array
    {
        $prefixes = [];
        foreach ($pageNumbers as $pageNumber) {
            $prefixes[$pageNumber] = rtrim(substr(self::pageImage($databaseBytes, $pageNumber, $pageSize), 0, 64), "\0.");
        }

        return $prefixes;
    }
}
