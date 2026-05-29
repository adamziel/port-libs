<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerRollbackJournalCurrentPlan
{
    /**
     * @param array<int, string> $dirtyPages 1-indexed page numbers to post-commit images.
     * @return array<string, mixed>
     */
    public static function admitCurrentJournal(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        array $dirtyPages,
        int $pageSize,
        bool $journalSynced = true,
        bool $databaseReservedLock = false,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager rollback-journal current plan requires a database path');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager rollback-journal current plan requires database bytes');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite pager rollback-journal current plan requires rollback journal bytes');
        }
        if ($dirtyPages === []) {
            throw new \InvalidArgumentException('SQLite pager rollback-journal current plan requires dirty pages');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager rollback-journal current plan page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager rollback-journal current plan requires database bytes aligned to page size');
        }

        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite pager rollback-journal current plan journal page size does not match pager page size');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($journal->header->initialDatabasePageCount !== $databasePageCount) {
            throw new \InvalidArgumentException('SQLite pager rollback-journal current plan journal initial database size does not match current database image');
        }

        ksort($dirtyPages);
        $journalImages = $journal->pageImages();
        $pageChecks = [];
        $admittedPages = [];
        $rejectedPages = [];
        foreach ($dirtyPages as $pageNumber => $dirtyImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager rollback-journal current plan dirty page numbers must be positive integers');
            }
            if (!is_string($dirtyImage) || strlen($dirtyImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager rollback-journal current plan dirty page {$pageNumber} image must match page size");
            }

            $currentImage = self::pageImage($databaseBytes, $pageNumber, $pageSize);
            $journalImage = $journalImages[$pageNumber] ?? null;
            $reasons = [];
            if ($journalImage === null) {
                $reasons[] = 'missing_current_rollback_image';
            } elseif ($journalImage !== $currentImage) {
                $reasons[] = 'rollback_image_not_from_current_database_page';
            }
            if ($dirtyImage === $currentImage) {
                $reasons[] = 'dirty_page_matches_current_database_page';
            }

            $admitted = $reasons === [];
            if ($admitted) {
                $admittedPages[] = $pageNumber;
            } else {
                $rejectedPages[$pageNumber] = $reasons;
            }

            $pageChecks[] = [
                'page_number' => $pageNumber,
                'current_prefix' => self::prefix($currentImage),
                'journal_prefix' => $journalImage === null ? null : self::prefix($journalImage),
                'dirty_prefix' => self::prefix($dirtyImage),
                'current_journal_match' => $journalImage === $currentImage,
                'dirty_changes_current' => $dirtyImage !== $currentImage,
                'admitted' => $admitted,
                'reasons' => $reasons,
            ];
        }

        $blockedReasons = [];
        if (!$journalSynced) {
            $blockedReasons[] = 'rollback_journal_not_synced';
        }
        if ($databaseReservedLock) {
            $blockedReasons[] = 'database_reserved_lock_held_by_other_writer';
        }
        if ($admittedPages === []) {
            $blockedReasons[] = 'no_dirty_pages_have_current_rollback_images';
        }
        if ($rejectedPages !== []) {
            $blockedReasons[] = 'some_dirty_pages_lack_current_rollback_images';
        }

        $admitted = $blockedReasons === [];
        $nextBytes = $admitted ? self::applyPages($databaseBytes, $dirtyPages, $pageSize) : $databaseBytes;
        $operations = self::operations($databasePath, $admittedPages, $pageSize, $admitted);

        return [
            'status' => $admitted ? 'pager_rollback_journal_current_admitted' : 'pager_rollback_journal_current_blocked',
            'reason' => $admitted ? 'rollback_journal_images_match_current_database_pages' : 'rollback_journal_current_source_not_admitted',
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'page_size' => $pageSize,
            'database_page_count' => $databasePageCount,
            'journal_page_count' => $journal->pageCount(),
            'journal_initial_database_page_count' => $journal->header->initialDatabasePageCount,
            'journal_synced' => $journalSynced,
            'database_reserved_lock' => $databaseReservedLock,
            'admitted_pages' => $admittedPages,
            'rejected_pages' => $rejectedPages,
            'blocked_reasons' => $blockedReasons,
            'page_checks' => $pageChecks,
            'current_database_bytes' => $databaseBytes,
            'next_database_bytes' => $nextBytes,
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-rollback-journal-current',
                'sqlite-rollback-journal-checksum-validation',
                'sqlite-current-database-page-image-fence',
            ],
            'non_overlap' => 'Pager current rollback-journal admission validates current database page images before dirty-page visibility; it does not repeat rollback commit apply, hot rollback recovery, super-journal commit, WAL byte truncation, or numbered CurrentSourceNext helper consolidation.',
        ];
    }

    private static function pageImage(string $databaseBytes, int $pageNumber, int $pageSize): string
    {
        $offset = ($pageNumber - 1) * $pageSize;
        if ($offset >= strlen($databaseBytes)) {
            return '';
        }

        return substr($databaseBytes, $offset, $pageSize);
    }

    /**
     * @param array<int, string> $dirtyPages
     */
    private static function applyPages(string $databaseBytes, array $dirtyPages, int $pageSize): string
    {
        $nextBytes = $databaseBytes;
        foreach ($dirtyPages as $pageNumber => $pageImage) {
            $offset = ($pageNumber - 1) * $pageSize;
            if ($offset > strlen($nextBytes)) {
                $nextBytes = str_pad($nextBytes, $offset, "\0");
            }
            $nextBytes = substr_replace($nextBytes, $pageImage, $offset, $pageSize);
        }

        return $nextBytes;
    }

    /**
     * @param list<int> $admittedPages
     * @return list<array<string, mixed>>
     */
    private static function operations(string $databasePath, array $admittedPages, int $pageSize, bool $admitted): array
    {
        if (!$admitted) {
            return [[
                'op' => 'preserve_current_reader',
                'path' => $databasePath,
                'reason' => 'rollback_journal_current_source_not_admitted',
            ]];
        }

        $operations = [[
            'op' => 'admit_rollback_journal_current_source',
            'path' => $databasePath . '-journal',
            'reason' => 'all_dirty_pages_have_current_rollback_images',
        ]];
        foreach ($admittedPages as $pageNumber) {
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => ($pageNumber - 1) * $pageSize,
                'bytes' => $pageSize,
                'reason' => "write_dirty_page_after_current_journal_admission_{$pageNumber}",
            ];
        }

        return $operations;
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 72), "\0.");
    }
}
