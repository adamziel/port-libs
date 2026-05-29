<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext155Plan
{
    /**
     * @param array<int,string> $recoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,reader_generation?:int,pinned?:bool,dirty?:bool,shared_lock?:bool,source?:string}> $readerCachePages
     * @param list<int> $nextReadPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $journalPath,
        string $masterJournalPath,
        ?string $masterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $recoveredPages,
        array $readerCachePages,
        array $nextReadPages,
        string $currentSourceId,
        int $currentSourceEpoch,
        int $currentReaderGeneration,
        bool $allowCleanRefresh = true,
    ): array {
        if ($databasePath === '' || $journalPath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 requires database, journal, and master journal paths');
        }
        if ($masterJournalBytes === null || trim($masterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 requires master journal bytes');
        }
        $members = self::members($masterJournalBytes);
        if (!in_array($journalPath, $members, true)) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next155 master journal does not reference the rollback journal');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 database bytes must be page-size aligned');
        }
        if ($recoveredPages === [] || $readerCachePages === [] || $nextReadPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 requires recovered pages, reader cache pages, and next reads');
        }
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 requires a current source id');
        }
        if ($currentSourceEpoch < 1 || $currentReaderGeneration < 1) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 source epoch and reader generation must be positive');
        }

        $pageCount = (int) (strlen($databaseBytes) / $pageSize);
        $database = self::databaseMap($databaseBytes, $pageSize);
        $recoveredPages = self::normalizeImages($recoveredPages, $pageSize, $pageCount, 'recovered');
        $readerCachePages = self::normalizeCache($readerCachePages, $pageSize, $pageCount);
        self::assertPageList($nextReadPages, $pageCount);

        $nextSourceId = 'master-reader-cache:' . substr(hash('sha256', $databasePath . $masterJournalPath . implode("\n", $members)), 0, 16);
        $nextEpoch = $currentSourceEpoch + 1;
        $nextReaderGeneration = $currentReaderGeneration + 1;
        $operations = [[
            'op' => 'read_master_journal_for_reader_cache',
            'path' => $masterJournalPath,
            'member' => $journalPath,
            'reason' => 'reader_cache_must_follow_current_master_journal_source',
        ]];

        foreach ($recoveredPages as $pageNumber => $image) {
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-recovered-current-reader-source',
            ];
            $operations[] = [
                'op' => 'restore_master_journal_page_before_reader_cache',
                'page_number' => $pageNumber,
                'reason' => 'recover_current_source_before_reader_cache_reuse',
            ];
        }

        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $validCache = [];
        $rows = [];

        foreach ($readerCachePages as $pageNumber => $entry) {
            $currentImage = $database[$pageNumber]['image'];
            $reasons = [];
            if (!$entry['shared_lock']) {
                $reasons[] = 'reader_without_shared_lock';
            }
            if ($entry['dirty']) {
                $reasons[] = 'dirty_reader_cache_page';
            }
            if ($entry['pinned'] && $entry['image'] !== $currentImage) {
                $reasons[] = 'pinned_reader_cache_predates_master_recovery';
            }
            if ($entry['source_id'] !== $currentSourceId) {
                $reasons[] = 'stale_master_source_id';
            }
            if ($entry['epoch'] !== $currentSourceEpoch) {
                $reasons[] = 'stale_master_source_epoch';
            }
            if ($entry['reader_generation'] !== $currentReaderGeneration) {
                $reasons[] = 'stale_reader_generation';
            }
            if ($entry['image'] !== $currentImage && !$allowCleanRefresh) {
                $reasons[] = 'clean_reader_cache_refresh_disabled';
            }

            $rows[$pageNumber] = [
                'page_number' => $pageNumber,
                'source_id' => $entry['source_id'],
                'epoch' => $entry['epoch'],
                'reader_generation' => $entry['reader_generation'],
                'shared_lock' => $entry['shared_lock'],
                'pinned' => $entry['pinned'],
                'dirty' => $entry['dirty'],
                'cache_prefix' => self::prefix($entry['image']),
                'current_prefix' => self::prefix($currentImage),
                'image_matches_current_source' => $entry['image'] === $currentImage,
                'reasons' => $reasons,
            ];

            if ($reasons !== []) {
                $invalidated[$pageNumber] = $reasons;
                $operations[] = [
                    'op' => 'invalidate_master_journal_reader_cache_page',
                    'page_number' => $pageNumber,
                    'reasons' => $reasons,
                ];
                continue;
            }

            if ($entry['image'] === $currentImage) {
                $retained[] = $pageNumber;
                $operations[] = [
                    'op' => 'retain_master_journal_reader_cache_page',
                    'page_number' => $pageNumber,
                    'reason' => 'reader_cache_page_matches_current_source',
                ];
            } else {
                $refreshed[] = $pageNumber;
                $operations[] = [
                    'op' => 'refresh_master_journal_reader_cache_page',
                    'page_number' => $pageNumber,
                    'reason' => 'clean_reader_cache_image_predates_master_recovery',
                ];
            }
            $validCache[$pageNumber] = [
                'image' => $currentImage,
                'source' => $entry['image'] === $currentImage ? $entry['source'] : 'master-journal-reader-cache-refreshed-current-source',
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reader_generation' => $nextReaderGeneration,
            ];
        }

        $reads = [];
        foreach ($nextReadPages as $pageNumber) {
            $cacheEntry = $validCache[$pageNumber] ?? null;
            $cacheHit = is_array($cacheEntry);
            $image = $cacheHit ? $cacheEntry['image'] : $database[$pageNumber]['image'];
            $reads[] = [
                'page_number' => $pageNumber,
                'cache_hit' => $cacheHit,
                'source' => $cacheHit ? $cacheEntry['source'] : $database[$pageNumber]['source'],
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reader_generation' => $nextReaderGeneration,
                'prefix' => self::prefix($image),
            ];
            $operations[] = [
                'op' => $cacheHit ? 'next_reader_master_journal_cache_hit' : 'next_reader_master_journal_cache_miss',
                'page_number' => $pageNumber,
                'reason' => 'next_reader_uses_master_journal_current_source',
            ];
        }

        ksort($rows, SORT_NUMERIC);
        ksort($invalidated, SORT_NUMERIC);
        sort($retained, SORT_NUMERIC);
        sort($refreshed, SORT_NUMERIC);

        return [
            'status' => 'pager_master_journal_reader_cache_current_source_next155',
            'reason' => 'master_journal_recovery_revalidates_reader_cache_before_next_read',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'master_journal_path' => $masterJournalPath,
            'master_members' => $members,
            'page_size' => $pageSize,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentSourceEpoch,
                'reader_generation' => $currentReaderGeneration,
            ],
            'next_source' => [
                'id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reader_generation' => $nextReaderGeneration,
            ],
            'recovered_page_numbers' => array_keys($recoveredPages),
            'retained_cache_page_numbers' => $retained,
            'refreshed_cache_page_numbers' => $refreshed,
            'invalidated_cache_page_numbers' => array_keys($invalidated),
            'invalidated_cache_pages' => $invalidated,
            'reader_cache_rows' => array_values($rows),
            'reader_cache_rows_by_page' => $rows,
            'next_reads' => $reads,
            'operations' => $operations,
            'source_digest' => hash('sha256', $databasePath . $journalPath . $masterJournalPath . implode('', array_column($database, 'image')) . implode(',', array_keys($invalidated))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next155',
                'sqlite-master-journal-current-source-member-validation',
                'sqlite-pager-reader-cache-current-source',
            ],
        ];
    }

    /** @return list<string> */
    private static function members(string $bytes): array
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

    /** @return array<int,array{image:string,source:string}> */
    private static function databaseMap(string $databaseBytes, int $pageSize): array
    {
        $pages = [];
        $pageCount = (int) (strlen($databaseBytes) / $pageSize);
        for ($page = 1; $page <= $pageCount; $page++) {
            $pages[$page] = [
                'image' => substr($databaseBytes, ($page - 1) * $pageSize, $pageSize),
                'source' => 'database-before-master-journal-recovery',
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
            if (!is_int($pageNumber) || $pageNumber < 1 || $pageNumber > $pageCount) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next155 {$label} page number is outside the database image");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next155 {$label} page image must match the page size");
            }
            $normalized[$pageNumber] = $image;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $cache
     * @return array<int,array{image:string,source_id:string,epoch:int,reader_generation:int,pinned:bool,dirty:bool,shared_lock:bool,source:string}>
     */
    private static function normalizeCache(array $cache, int $pageSize, int $pageCount): array
    {
        $normalized = [];
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1 || $pageNumber > $pageCount) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 cache page number is outside the database image');
            }
            $image = $entry['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 cache image must match the page size');
            }
            $sourceId = (string) ($entry['source_id'] ?? '');
            if ($sourceId === '') {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 cache source id is required');
            }
            $epoch = (int) ($entry['epoch'] ?? 0);
            $generation = (int) ($entry['reader_generation'] ?? 0);
            if ($epoch < 1 || $generation < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 cache epoch and generation must be positive');
            }
            $normalized[$pageNumber] = [
                'image' => $image,
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'reader_generation' => $generation,
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'dirty' => (bool) ($entry['dirty'] ?? false),
                'shared_lock' => (bool) ($entry['shared_lock'] ?? true),
                'source' => (string) ($entry['source'] ?? 'reader-cache'),
            ];
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /** @param list<int> $pages */
    private static function assertPageList(array $pages, int $pageCount): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1 || $pageNumber > $pageCount) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next155 read page number is outside the database image');
            }
        }
    }

    private static function prefix(string $image): string
    {
        return rtrim(substr($image, 0, 56), ".\0 ");
    }
}
