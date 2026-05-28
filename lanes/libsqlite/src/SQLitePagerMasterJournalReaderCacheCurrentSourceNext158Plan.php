<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerMasterJournalReaderCacheCurrentSourceNext158Plan
{
    /**
     * @param array<int,string> $masterRecoveredPages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,end_frame?:int,pinned?:bool,source?:string}> $readerCachePages
     * @param list<int> $nextReadPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $masterJournalPath,
        ?string $cachedMasterJournalBytes,
        ?string $currentMasterJournalBytes,
        string $databaseBytes,
        int $pageSize,
        array $masterRecoveredPages,
        array $readerCachePages,
        array $nextReadPages,
        string $currentSourceId,
        int $currentSourceEpoch = 1,
        int $currentReaderEndFrame = 0,
        bool $refreshCleanReaderCache = true,
    ): array {
        if ($databasePath === '' || $masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next158 requires database and master-journal paths');
        }
        if ($currentMasterJournalBytes === null || trim($currentMasterJournalBytes) === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next158 requires current master-journal bytes');
        }
        if (!str_contains($currentMasterJournalBytes, $databasePath . '-journal')) {
            throw new \RuntimeException('SQLite pager master-journal reader-cache next158 current master journal does not reference the database journal');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next158 requires database bytes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next158 page size must be a power of two at least 512');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next158 database bytes must be page-size aligned');
        }
        if ($currentSourceId === '') {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next158 requires a current source id');
        }
        if ($currentSourceEpoch < 1 || $currentReaderEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next158 source epoch and reader frame must be non-negative');
        }
        if ($nextReadPages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next158 requires next read pages');
        }

        $database = self::sourceMap($databaseBytes, $pageSize);
        $masterRecoveredPages = self::normalizeImages($masterRecoveredPages, $pageSize, 'master recovered');
        $readerCachePages = self::normalizeReaderCache($readerCachePages, $pageSize);
        self::assertPageList($nextReadPages, 'next read');

        $cachedMembers = self::members($cachedMasterJournalBytes);
        $currentMembers = self::members($currentMasterJournalBytes);
        $nextSourceId = self::sourceId($masterJournalPath, $currentMembers);
        $nextEpoch = $currentSourceEpoch + 1;
        $cacheStale = $cachedMembers !== $currentMembers;

        $operations = [[
            'op' => 'read_current_master_journal_for_reader_cache',
            'path' => $masterJournalPath,
            'bytes' => strlen($currentMasterJournalBytes),
            'reason' => 'reader_cache_must_follow_current_master_journal_source',
        ]];
        if ($cacheStale) {
            $operations[] = [
                'op' => 'discard_cached_master_journal_members_for_reader_cache',
                'path' => $masterJournalPath,
                'reason' => 'cached_master_journal_members_do_not_match_current_source',
            ];
        }

        foreach ($masterRecoveredPages as $pageNumber => $image) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next158 recovered page {$pageNumber} is outside the database image");
            }
            $database[$pageNumber] = [
                'image' => $image,
                'source' => 'master-journal-reader-recovered-current-source',
            ];
            $operations[] = [
                'op' => 'restore_master_journal_reader_page',
                'page_number' => $pageNumber,
                'reason' => 'recover_current_source_before_reader_cache_reuse',
            ];
        }

        $validReaderCache = [];
        $retained = [];
        $refreshed = [];
        $invalidated = [];
        $rows = [];

        foreach ($readerCachePages as $pageNumber => $entry) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next158 reader page {$pageNumber} is outside the database image");
            }
            $currentImage = $database[$pageNumber]['image'];
            $reason = null;
            if ($entry['source_id'] !== $currentSourceId) {
                $reason = 'stale_reader_cache_source_id';
            } elseif ($entry['epoch'] !== $currentSourceEpoch) {
                $reason = 'stale_reader_cache_source_epoch';
            } elseif ($entry['end_frame'] !== $currentReaderEndFrame) {
                $reason = 'stale_reader_end_frame_after_master_recovery';
            } elseif ($entry['pinned'] && $entry['image'] !== $currentImage) {
                $reason = 'pinned_reader_cache_predates_master_recovery';
            } elseif ($entry['image'] !== $currentImage && !$refreshCleanReaderCache) {
                $reason = 'stale_reader_cache_refresh_disabled';
            }

            if ($reason !== null) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $entry['source'],
                    'source_id' => $entry['source_id'],
                    'epoch' => $entry['epoch'],
                    'end_frame' => $entry['end_frame'],
                    'pinned' => $entry['pinned'],
                    'reason' => $reason,
                ];
                $operations[] = [
                    'op' => 'invalidate_master_journal_reader_cache_page',
                    'page_number' => $pageNumber,
                    'reason' => $reason,
                ];
            } elseif ($entry['image'] !== $currentImage) {
                $refreshed[] = $pageNumber;
                $validReaderCache[$pageNumber] = [
                    'image' => $currentImage,
                    'source' => 'master-journal-reader-cache-refreshed-current-source',
                    'source_id' => $nextSourceId,
                    'epoch' => $nextEpoch,
                    'end_frame' => $currentReaderEndFrame,
                ];
                $operations[] = [
                    'op' => 'refresh_master_journal_reader_cache_page',
                    'page_number' => $pageNumber,
                    'source_id' => $nextSourceId,
                    'epoch' => $nextEpoch,
                    'reason' => 'clean_reader_cache_image_predates_current_source',
                ];
            } else {
                $retained[] = $pageNumber;
                $validReaderCache[$pageNumber] = [
                    'image' => $entry['image'],
                    'source' => $entry['source'],
                    'source_id' => $nextSourceId,
                    'epoch' => $nextEpoch,
                    'end_frame' => $currentReaderEndFrame,
                ];
                $operations[] = [
                    'op' => 'retain_master_journal_reader_cache_page',
                    'page_number' => $pageNumber,
                    'source_id' => $nextSourceId,
                    'epoch' => $nextEpoch,
                    'reason' => 'reader_cache_page_matches_current_source',
                ];
            }

            $rows[$pageNumber] = [
                'page_number' => $pageNumber,
                'pinned' => $entry['pinned'],
                'source_id_before' => $entry['source_id'],
                'epoch_before' => $entry['epoch'],
                'end_frame_before' => $entry['end_frame'],
                'image_matches_current_source' => $entry['image'] === $currentImage,
                'current_prefix' => self::label($currentImage),
                'reader_prefix' => self::label($entry['image']),
            ];
        }
        ksort($validReaderCache, SORT_NUMERIC);

        $readResults = [];
        foreach ($nextReadPages as $pageNumber) {
            if (!isset($database[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next158 read page {$pageNumber} is outside the database image");
            }
            $entry = $validReaderCache[$pageNumber] ?? null;
            $hit = is_array($entry);
            $image = $hit ? $entry['image'] : $database[$pageNumber]['image'];
            $readResults[] = [
                'page_number' => $pageNumber,
                'reader_cache_hit' => $hit,
                'source' => $hit ? $entry['source'] : $database[$pageNumber]['source'],
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'end_frame' => $currentReaderEndFrame,
                'prefix' => self::label($image),
            ];
            $operations[] = [
                'op' => $hit ? 'next_read_master_journal_reader_cache_hit' : 'next_read_master_journal_reader_cache_miss',
                'page_number' => $pageNumber,
                'source_id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reason' => 'next_read_uses_master_journal_reader_current_source',
            ];
        }

        return [
            'status' => 'pager-master-journal-reader-cache-current-source-next158',
            'reason' => 'master_journal_recovery_rebases_reader_cache_before_next_read',
            'database_path' => $databasePath,
            'master_journal_path' => $masterJournalPath,
            'page_size' => $pageSize,
            'cached_members' => $cachedMembers,
            'current_members' => $currentMembers,
            'reader_cache_stale_rejected' => $cacheStale,
            'current_source' => [
                'id' => $currentSourceId,
                'epoch' => $currentSourceEpoch,
                'reader_end_frame' => $currentReaderEndFrame,
            ],
            'next_source' => [
                'id' => $nextSourceId,
                'epoch' => $nextEpoch,
                'reader_end_frame' => $currentReaderEndFrame,
            ],
            'master_recovered_page_numbers' => array_keys($masterRecoveredPages),
            'retained_reader_cache_page_numbers' => $retained,
            'refreshed_reader_cache_page_numbers' => $refreshed,
            'invalidated_reader_cache_page_numbers' => array_column($invalidated, 'page_number'),
            'invalidated_reader_cache_entries' => $invalidated,
            'reader_cache_rows' => array_values($rows),
            'next_reads' => $readResults,
            'final_reader_cache_page_numbers' => array_keys($validReaderCache),
            'final_reader_cache_sources' => self::cacheSources($validReaderCache),
            'final_prefixes' => self::prefixes($database),
            'final_sources' => self::sources($database),
            'final_database_bytes' => self::sourceBytes($database, $pageSize),
            'operations' => $operations,
            'source_digest' => hash('sha256', implode('|', self::sources($database)) . '|' . implode(',', array_keys($validReaderCache))),
            'dependencies' => [
                'sqlite-pager-master-journal-reader-cache-current-source-next158',
                'sqlite-pager-master-journal-cache-recovery-current-source-next122',
                'sqlite-pager-master-journal-hot-cache-current-source-next136',
            ],
        ];
    }

    /**
     * @param array<int,string> $pages
     * @return array<int,string>
     */
    private static function normalizeImages(array $pages, int $pageSize, string $label): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next158 {$label} pages are required");
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next158 {$label} page numbers must be one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next158 {$label} page {$pageNumber} image must match page size");
            }
            $normalized[$pageNumber] = $image;
        }

        return $normalized;
    }

    /**
     * @param array<int,array{image:string,source_id?:string,epoch?:int,end_frame?:int,pinned?:bool,source?:string}> $pages
     * @return array<int,array{image:string,source_id:string,epoch:int,end_frame:int,pinned:bool,source:string}>
     */
    private static function normalizeReaderCache(array $pages, int $pageSize): array
    {
        if ($pages === []) {
            throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next158 reader cache pages are required');
        }
        ksort($pages, SORT_NUMERIC);
        $normalized = [];
        foreach ($pages as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager master-journal reader-cache next158 reader cache page numbers must be one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next158 reader cache page {$pageNumber} image must match page size");
            }
            $epoch = $entry['epoch'] ?? 0;
            $endFrame = $entry['end_frame'] ?? 0;
            if (!is_int($epoch) || $epoch < 0 || !is_int($endFrame) || $endFrame < 0) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next158 reader cache page {$pageNumber} epoch and end frame must be non-negative");
            }
            $sourceId = isset($entry['source_id']) && is_string($entry['source_id']) ? $entry['source_id'] : '';
            $normalized[$pageNumber] = [
                'image' => $entry['image'],
                'source_id' => $sourceId,
                'epoch' => $epoch,
                'end_frame' => $endFrame,
                'pinned' => (bool) ($entry['pinned'] ?? false),
                'source' => isset($entry['source']) && is_string($entry['source']) && $entry['source'] !== '' ? $entry['source'] : 'reader-cache-before-master-journal-recovery',
            ];
        }

        return $normalized;
    }

    /**
     * @param list<int> $pages
     */
    private static function assertPageList(array $pages, string $label): void
    {
        foreach ($pages as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager master-journal reader-cache next158 {$label} pages must be one-based integers");
            }
        }
    }

    /**
     * @return array<int,array{image:string,source:string}>
     */
    private static function sourceMap(string $databaseBytes, int $pageSize): array
    {
        $map = [];
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $map[$pageNumber] = [
                'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                'source' => 'database-before-master-journal-reader-recovery',
            ];
        }

        return $map;
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
     * @param list<string> $members
     */
    private static function sourceId(string $masterJournalPath, array $members): string
    {
        return 'master-reader-cache:' . substr(hash('sha256', $masterJournalPath . '|' . implode('|', $members)), 0, 16);
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     */
    private static function sourceBytes(array $source, int $pageSize): string
    {
        ksort($source, SORT_NUMERIC);
        $bytes = '';
        $maxPage = max(array_keys($source));
        for ($pageNumber = 1; $pageNumber <= $maxPage; $pageNumber++) {
            $bytes .= $source[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
        }

        return $bytes;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @return array<int,string>
     */
    private static function prefixes(array $source): array
    {
        ksort($source, SORT_NUMERIC);
        $prefixes = [];
        foreach ($source as $pageNumber => $entry) {
            $prefixes[$pageNumber] = self::label($entry['image']);
        }

        return $prefixes;
    }

    /**
     * @param array<int,array{image:string,source:string}> $source
     * @return array<int,string>
     */
    private static function sources(array $source): array
    {
        ksort($source, SORT_NUMERIC);
        $sources = [];
        foreach ($source as $pageNumber => $entry) {
            $sources[$pageNumber] = $entry['source'];
        }

        return $sources;
    }

    /**
     * @param array<int,array<string,mixed>> $cache
     * @return array<int,string>
     */
    private static function cacheSources(array $cache): array
    {
        ksort($cache, SORT_NUMERIC);
        $sources = [];
        foreach ($cache as $pageNumber => $entry) {
            $sources[$pageNumber] = (string) $entry['source'];
        }

        return $sources;
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
