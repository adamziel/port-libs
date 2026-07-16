<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerSavepointMasterJournalReaderCurrentSourceNextPlan
{
    /**
     * @param array<int,string> $masterRecoveredPages
     * @param array<int,string> $savepointBeforeImages
     * @param list<array{page_number:int,source_id:string,epoch:int,pinned?:bool,kind?:string,label?:string}> $readerSnapshots
     * @param list<int> $nextReadPages
     * @param array<int,string> $nextWritePages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $masterRecoveredPages,
        string $savepointName,
        array $savepointBeforeImages,
        array $readerSnapshots,
        array $nextReadPages,
        array $nextWritePages,
        string $currentSourceId,
        int $currentSourceEpoch = 1,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 requires database and master-journal paths');
        }
        if ($currentMasterJournalBytes === null || trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 requires current master-journal bytes');
        }
        if (!str_contains($currentMasterJournalBytes, $databasePath . '-journal')) {
            throw new \RuntimeException('SQLite pager savepoint master-journal reader next146 current master journal does not reference the database journal');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 database bytes must be page-size aligned');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 requires a savepoint name');
        }
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 requires a current source id');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 source epoch must be positive');
        }
        if ($readerSnapshots === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 requires reader snapshots');
        }
        if ($nextReadPages === [] && $nextWritePages === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 requires next read or write pages');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $masterRecoveredPages = self::normalizeImages($masterRecoveredPages, $pageSize, 'master recovered');
        $savepointBeforeImages = self::normalizeImages($savepointBeforeImages, $pageSize, 'savepoint before');
        self::assertPageList($nextReadPages, 'next read');
        $nextWritePages = self::normalizeOptionalImages($nextWritePages, $pageSize, 'next write');

        $currentMembers = self::members($currentMasterJournalBytes);
        $recoveredSourceId = self::sourceId($masterJournalPath, $currentMembers);
        $recoveredEpoch = $currentSourceEpoch + 1;
        $savepointSourceId = $recoveredSourceId . ':rollback-to:' . $savepointName;
        $savepointEpoch = $recoveredEpoch + 1;

        $operations = [[
            'op' => 'read_current_master_journal_for_reader_source',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
            'reason' => 'reader_source_must_follow_hot_master_journal_recovery',
        ]];

        foreach ($masterRecoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal reader next146 recovered page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-recovered-current-source',
            ];
            $operations[] = [
                'op' => 'restore_master_journal_page_before_reader_check',
                'page_number' => $pageNumber,
                'source_id' => $recoveredSourceId,
                'epoch' => $recoveredEpoch,
            ];
        }

        foreach ($savepointBeforeImages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal reader next146 savepoint page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-rollback-current-source',
            ];
            $operations[] = [
                'op' => 'rollback_to_savepoint_before_reader_reopen',
                'savepoint' => $savepointName,
                'page_number' => $pageNumber,
                'source_id' => $savepointSourceId,
                'epoch' => $savepointEpoch,
            ];
        }

        $readerRows = [];
        $blocked = [];
        $admitted = [];
        foreach ($readerSnapshots as $index => $reader) {
            $pageNumber = $reader['page_number'] ?? null;
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 reader page numbers must be one-based integers');
            }
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal reader next146 reader page {$pageNumber} is outside the database image");
            }
            $sourceId = $reader['source_id'] ?? '';
            $epoch = $reader['epoch'] ?? 0;
            if (!is_string($sourceId) || $sourceId === '') {
                throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 reader source ids must be non-empty strings');
            }
            if (!is_int($epoch) || $epoch < 1) {
                throw new \InvalidArgumentException('SQLite pager savepoint master-journal reader next146 reader epochs must be positive integers');
            }
            $pinned = (bool) ($reader['pinned'] ?? true);
            $kind = (string) ($reader['kind'] ?? 'read');
            $label = (string) ($reader['label'] ?? ('reader-' . $index));
            $reason = null;
            if ($sourceId !== $savepointSourceId) {
                $reason = str_starts_with($sourceId, $recoveredSourceId)
                    ? 'reader_predates_savepoint_rollback_source'
                    : 'reader_predates_master_journal_recovery';
            } elseif ($epoch !== $savepointEpoch) {
                $reason = 'reader_epoch_predates_savepoint_rollback';
            }

            $row = [
                'label' => $label,
                'kind' => $kind,
                'page_number' => $pageNumber,
                'pinned' => $pinned,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'current_prefix' => self::label($database[$pageNumber]['image']),
                'admitted' => $reason === null,
                'reason' => $reason ?? 'reader_matches_savepoint_current_source',
            ];
            $readerRows[] = $row;

            if ($reason === null) {
                $admitted[] = $row;
                $operations[] = [
                    'op' => 'admit_reader_after_savepoint_master_journal_source_check',
                    'reader' => $label,
                    'page_number' => $pageNumber,
                ];
            } else {
                $blocked[] = $row;
                $operations[] = [
                    'op' => 'block_stale_reader_after_savepoint_master_journal_source_check',
                    'reader' => $label,
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                    'requires_reopen' => true,
                ];
            }
        }

        $readResults = [];
        foreach ($nextReadPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal reader next146 read page {$pageNumber} is outside the database image");
            }
            $readResults[] = [
                'page_number' => $pageNumber,
                'source' => $database[$pageNumber]['source'],
                'source_id' => $savepointSourceId,
                'epoch' => $savepointEpoch,
                'prefix' => self::label($database[$pageNumber]['image']),
            ];
            $operations[] = [
                'op' => 'next_reader_uses_savepoint_master_journal_current_source',
                'page_number' => $pageNumber,
            ];
        }

        $writeResults = [];
        foreach ($nextWritePages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal reader next146 write page {$pageNumber} is outside the database image");
            }
            $beforeImage = $database[$pageNumber]['image'];
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'next-write-after-savepoint-reader-reopen',
            ];
            $writeResults[] = [
                'page_number' => $pageNumber,
                'before_prefix' => self::label($beforeImage),
                'after_prefix' => self::label($image),
                'journal_before_from_savepoint_current_source' => true,
                'source_id' => $savepointSourceId,
                'epoch' => $savepointEpoch,
            ];
            $operations[] = [
                'op' => 'capture_next_write_before_image_after_reader_reopen',
                'page_number' => $pageNumber,
                'source_id' => $savepointSourceId,
                'epoch' => $savepointEpoch,
            ];
        }

        return [
            'status' => 'pager-savepoint-master-journal-reader-current-source-next146',
            'reason' => 'savepoint_rollback_after_master_journal_recovery_reopens_stale_readers',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'current_members' => $currentMembers,
            'input_source' => ['id' => $currentSourceId, 'epoch' => $currentSourceEpoch],
            'recovered_source' => ['id' => $recoveredSourceId, 'epoch' => $recoveredEpoch],
            'savepoint_source' => ['id' => $savepointSourceId, 'epoch' => $savepointEpoch],
            'master_recovered_page_numbers' => array_keys($masterRecoveredPages),
            'savepoint_rollback_page_numbers' => array_keys($savepointBeforeImages),
            'reader_rows' => $readerRows,
            'blocked_reader_labels' => array_values(array_map(static fn (array $row): string => $row['label'], $blocked)),
            'admitted_reader_labels' => array_values(array_map(static fn (array $row): string => $row['label'], $admitted)),
            'requires_reader_reopen' => $blocked !== [],
            'next_reads' => $readResults,
            'next_writes' => $writeResults,
            'final_sources' => array_map(static fn (array $entry): string => $entry['source'], $database),
            'final_prefixes' => array_map(static fn (array $entry): string => self::label($entry['image']), $database),
            'final_database_bytes' => implode('', array_map(static fn (array $entry): string => $entry['image'], $database)),
            'operations' => $operations,
            'source_digest' => hash('sha256', $recoveredSourceId . '|' . $savepointSourceId . '|' . implode(',', $currentMembers)),
            'dependencies' => [
                'sqlite-pager-savepoint-master-journal-reader-current-source-next146',
                'sqlite-pager-master-journal-hot-cache-current-source-next136',
                'sqlite-pager-master-journal-savepoint-cache-current-source-next138',
            ],
        ];
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize): array
    {
        $pages = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $pages[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'source' => 'database-before-master-journal-recovery',
            ];
        }
        return $pages;
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private static function normalizeImages(array $images, int $pageSize, string $label): array
    {
        if ($images === []) {
            throw new \InvalidArgumentException("SQLite pager savepoint master-journal reader next146 requires {$label} page images");
        }
        $normalized = [];
        foreach ($images as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal reader next146 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal reader next146 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);
        return $normalized;
    }

    /**
     * @param array<int,string> $images
     * @return array<int,string>
     */
    private static function normalizeOptionalImages(array $images, int $pageSize, string $label): array
    {
        if ($images === []) {
            return [];
        }
        return self::normalizeImages($images, $pageSize, $label);
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages, string $label): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal reader next146 {$label} pages must be one-based integers");
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function members(?string $masterJournalBytes): array
    {
        if ($masterJournalBytes === null || trim($masterJournalBytes) === '') {
            return [];
        }
        $members = [];
        foreach (preg_split('/\R+/', trim($masterJournalBytes)) ?: [] as $member) {
            $member = trim($member);
            if ($member !== '' && !in_array($member, $members, true)) {
                $members[] = $member;
            }
        }
        return $members;
    }

    /**
     * @param list<string> $members
     */
    private static function sourceId(string $masterJournalPath, array $members): string
    {
        return 'master-savepoint-reader:' . hash('sha256', $masterJournalPath . '|' . implode('|', $members));
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 72), ".\0 ");
    }
}
