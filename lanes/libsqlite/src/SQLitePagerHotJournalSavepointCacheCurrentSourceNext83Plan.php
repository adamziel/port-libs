<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalSavepointCacheCurrentSourceNext83Plan
{
    /**
     * @param array<int,array{image:string,source?:string,epoch?:int}> $currentCache
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $currentWrites
     * @param array<int,string> $nextWrites
     * @return array{status:string,page_size:int,savepoint:string,current_source_epoch:int,next_source_epoch:int,cache:array<string,mixed>,savepoint:array<string,mixed>,next:array<string,mixed>,operations:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentSourceNext(
        int $pageSize,
        string $savepoint,
        array $currentCache,
        array $hotJournalPages,
        array $currentWrites,
        array $nextWrites,
        int $currentSourceEpoch = 1,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache page size must be positive');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache savepoint name must not be empty');
        }
        if ($hotJournalPages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache requires recovered hot-journal pages');
        }
        if ($currentWrites === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache requires current savepoint writes');
        }
        if ($nextWrites === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache requires next retry writes');
        }
        if ($currentSourceEpoch < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache source epoch must be positive');
        }

        self::assertCache($currentCache, $pageSize);
        self::assertPages($hotJournalPages, $pageSize, 'hot-journal');
        self::assertPages($currentWrites, $pageSize, 'current');
        self::assertPages($nextWrites, $pageSize, 'next');

        $nextEpoch = $currentSourceEpoch + 1;
        $recoveredPageNumbers = array_keys($hotJournalPages);
        sort($recoveredPageNumbers, SORT_NUMERIC);

        $invalidated = [];
        $preserved = [];
        $currentSourcePages = $currentCache;
        foreach ($currentCache as $pageNumber => $entry) {
            $epoch = (int) ($entry['epoch'] ?? 0);
            $source = (string) ($entry['source'] ?? 'unknown');
            $recovered = array_key_exists($pageNumber, $hotJournalPages);
            if ($recovered || $epoch !== $currentSourceEpoch) {
                $invalidated[] = [
                    'page_number' => $pageNumber,
                    'source' => $source,
                    'epoch' => $epoch,
                    'reason' => $recovered ? 'hot_journal_recovered_page' : 'stale_current_source_epoch',
                ];
                unset($currentSourcePages[$pageNumber]);
                continue;
            }

            $preserved[] = [
                'page_number' => $pageNumber,
                'source' => $source,
                'epoch' => $epoch,
            ];
        }

        foreach ($hotJournalPages as $pageNumber => $image) {
            $currentSourcePages[$pageNumber] = [
                'image' => $image,
                'source' => 'hot-journal',
                'epoch' => $nextEpoch,
            ];
        }
        ksort($currentSourcePages, SORT_NUMERIC);

        $beforeImages = [];
        $operations = [];
        foreach ($currentWrites as $pageNumber => $image) {
            $before = $currentSourcePages[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            $source = $currentSourcePages[$pageNumber]['source'] ?? 'zero-fill';
            $epoch = (int) ($currentSourcePages[$pageNumber]['epoch'] ?? $nextEpoch);
            $beforeImages[$pageNumber] = $before;
            $operations[] = [
                'op' => 'capture_savepoint_before_image',
                'page_number' => $pageNumber,
                'source' => $source,
                'epoch' => $epoch,
                'bytes' => strlen($before),
                'reason' => 'capture_after_hot_journal_cache_invalidation',
            ];
            $currentSourcePages[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-current-write',
                'epoch' => $nextEpoch,
            ];
            $operations[] = [
                'op' => 'write_current_savepoint_page',
                'page_number' => $pageNumber,
                'source' => 'savepoint-current-write',
                'epoch' => $nextEpoch,
                'bytes' => strlen($image),
            ];
        }

        foreach ($beforeImages as $pageNumber => $image) {
            $currentSourcePages[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-rollback-before-image',
                'epoch' => $nextEpoch,
            ];
            $operations[] = [
                'op' => 'restore_savepoint_before_image',
                'page_number' => $pageNumber,
                'source' => 'savepoint-rollback-before-image',
                'epoch' => $nextEpoch,
                'bytes' => strlen($image),
            ];
        }

        $nextCaptured = [];
        foreach ($nextWrites as $pageNumber => $image) {
            $before = $currentSourcePages[$pageNumber]['image'] ?? str_repeat("\0", $pageSize);
            $source = $currentSourcePages[$pageNumber]['source'] ?? 'zero-fill';
            $nextCaptured[] = [
                'page_number' => $pageNumber,
                'source' => $source,
                'epoch' => (int) ($currentSourcePages[$pageNumber]['epoch'] ?? $nextEpoch),
                'matches_savepoint_before_image' => isset($beforeImages[$pageNumber]) && $beforeImages[$pageNumber] === $before,
                'zero_filled_short_read' => $source === 'zero-fill',
            ];
            $operations[] = [
                'op' => 'capture_next_retry_before_image',
                'page_number' => $pageNumber,
                'source' => $source,
                'epoch' => (int) ($currentSourcePages[$pageNumber]['epoch'] ?? $nextEpoch),
                'bytes' => strlen($before),
                'reason' => 'retry_uses_current_source_after_hot_journal_recovery',
            ];
            $currentSourcePages[$pageNumber] = [
                'image' => $image,
                'source' => 'savepoint-next-write',
                'epoch' => $nextEpoch,
            ];
            $operations[] = [
                'op' => 'write_next_retry_page',
                'page_number' => $pageNumber,
                'source' => 'savepoint-next-write',
                'epoch' => $nextEpoch,
                'bytes' => strlen($image),
            ];
        }

        ksort($currentSourcePages, SORT_NUMERIC);

        return [
            'status' => 'hot_journal_savepoint_cache_current_source_next',
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'current_source_epoch' => $currentSourceEpoch,
            'next_source_epoch' => $nextEpoch,
            'cache' => [
                'invalidated_page_numbers' => array_column($invalidated, 'page_number'),
                'invalidated_entries' => $invalidated,
                'preserved_page_numbers' => array_column($preserved, 'page_number'),
                'preserved_entries' => $preserved,
                'recovered_page_numbers' => $recoveredPageNumbers,
                'final_page_numbers' => array_keys($currentSourcePages),
                'final_sources' => self::sources($currentSourcePages),
            ],
            'savepoint' => [
                'name' => $savepoint,
                'captured_page_numbers' => array_keys($beforeImages),
                'captured_sources' => self::capturedSources($operations, 'capture_savepoint_before_image'),
                'rollback_restored_page_numbers' => array_keys($beforeImages),
            ],
            'next' => [
                'written_page_numbers' => array_keys($nextWrites),
                'captured_pages' => $nextCaptured,
                'final_sources' => self::sources($currentSourcePages),
            ],
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-hot-journal-savepoint-cache-current-source-next83',
                'sqlite-hot-journal-recovery',
                'sqlite-savepoint-page-image-rollback',
                'sqlite-pager-cache-current-source',
            ],
        ];
    }

    /**
     * @param array<int,array{image:string,source?:string,epoch?:int}> $cache
     */
    private static function assertCache(array $cache, int $pageSize): void
    {
        foreach ($cache as $pageNumber => $entry) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal savepoint cache page numbers are one-based integers');
            }
            if (!isset($entry['image']) || !is_string($entry['image']) || strlen($entry['image']) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache page {$pageNumber} image must match the page size");
            }
        }
    }

    /**
     * @param array<int,string> $pages
     */
    private static function assertPages(array $pages, int $pageSize, string $label): void
    {
        foreach ($pages as $pageNumber => $image) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache {$label} page numbers are one-based integers");
            }
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal savepoint cache {$label} page {$pageNumber} image must match the page size");
            }
        }
    }

    /**
     * @param array<int,array{image:string,source?:string,epoch?:int}> $pages
     * @return array<int,string>
     */
    private static function sources(array $pages): array
    {
        $sources = [];
        foreach ($pages as $pageNumber => $entry) {
            $sources[$pageNumber] = (string) ($entry['source'] ?? 'unknown');
        }

        return $sources;
    }

    /**
     * @param list<array<string,mixed>> $operations
     * @return array<int,string>
     */
    private static function capturedSources(array $operations, string $op): array
    {
        $sources = [];
        foreach ($operations as $operation) {
            if (($operation['op'] ?? null) === $op && isset($operation['page_number'])) {
                $sources[(int) $operation['page_number']] = (string) ($operation['source'] ?? 'unknown');
            }
        }

        return $sources;
    }
}
