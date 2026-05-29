<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerCacheSpillHotJournalReaderCurrentSourceNext147Plan
{
    /**
     * @param list<array{page:int,image:string,current_image?:string,bytes?:int,dirty?:bool,pinned?:bool,journaled?:bool,walFrame?:int,readerPinned?:bool,nextGeneration?:bool}> $cachePages
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $dirtyDatabaseBytes,
        string $journalBytes,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $restartedWalBytes,
        array $cachePages,
        array $pageNumbers,
        int $readerEndFrame,
        int $cacheSize,
        int $spillThreshold,
        bool $reservedLock = false,
        bool $walSynced = true,
        bool $cacheSpillEnabled = true,
        ?int $maxSpillPages = null,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($cachePages === []) {
            throw new \InvalidArgumentException('SQLite pager cache-spill hot-journal reader current-source next147 requires cache pages');
        }

        $reader = SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::next143Plan(
            $databasePath,
            $dirtyDatabaseBytes,
            $journalBytes,
            $currentWal,
            $currentWalBytes,
            $restartedWalBytes,
            $pageNumbers,
            $readerEndFrame,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        $pageSize = (int) $reader['page_size'];
        $pageCount = intdiv(strlen($dirtyDatabaseBytes), $pageSize);
        $hotDatabaseBytes = (string) ($reader['base_plan']['base_plan']['hot_journal']['database_bytes'] ?? '');
        if ($hotDatabaseBytes === '') {
            throw new \UnexpectedValueException('SQLite pager cache-spill hot-journal reader current-source next147 requires hot-journal recovered database bytes');
        }

        $currentSources = self::currentReaderSources($currentWal, $hotDatabaseBytes, $pageSize, $pageCount, $readerEndFrame);
        $nextWal = SQLiteWal::parse($restartedWalBytes, $pageSize, true);
        $nextSources = self::currentReaderSources($nextWal, $hotDatabaseBytes, $pageSize, $pageCount, $nextWal->frameCount());
        $cachePages = self::normalizeCachePages($cachePages, $pageCount, $pageSize);

        $admitted = [];
        $rejected = [];
        $rows = [];

        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'];
            $source = $currentSources[$page];
            $nextSource = $nextSources[$page];
            $currentImage = $cachePage['current_image'] ?? $source['image'];
            $dirty = (bool) ($cachePage['dirty'] ?? true);
            $pinned = (bool) ($cachePage['pinned'] ?? false);
            $journaled = (bool) ($cachePage['journaled'] ?? true);
            $readerPinned = (bool) ($cachePage['readerPinned'] ?? false);
            $nextGeneration = (bool) ($cachePage['nextGeneration'] ?? false);
            $reasons = [];

            if (!$dirty) {
                $reasons[] = 'cache_page_clean';
            }
            if ($pinned) {
                $reasons[] = 'cache_page_pinned';
            }
            if (!$journaled) {
                $reasons[] = 'cache_page_not_journaled';
            }
            if ($readerPinned) {
                $reasons[] = 'reader_pinned_current_source_page';
            }
            if ($nextGeneration) {
                $reasons[] = 'cache_page_from_next_wal_generation';
            }
            if ($currentImage !== $source['image']) {
                $reasons[] = 'hot_journal_reader_current_source_mismatch';
            }
            if ($cachePage['image'] === $nextSource['image'] && $nextSource['source'] === 'wal') {
                $reasons[] = 'cache_image_matches_next_generation_wal';
            }

            $rows[$page] = [
                'page' => $page,
                'cache_prefix' => self::prefix($cachePage['image']),
                'current_prefix' => self::prefix($source['image']),
                'next_prefix' => self::prefix($nextSource['image']),
                'current_source' => $source['source'],
                'current_frame' => $source['frame'],
                'next_source' => $nextSource['source'],
                'next_frame' => $nextSource['frame'],
                'dirty' => $dirty,
                'pinned' => $pinned,
                'journaled' => $journaled,
                'reader_pinned' => $readerPinned,
                'next_generation' => $nextGeneration,
                'current_image_verified' => $currentImage === $source['image'],
                'admitted' => $reasons === [],
                'rejected_reasons' => $reasons,
            ];

            if ($reasons === []) {
                $admitted[] = $cachePage;
            } else {
                $rejected[$page] = $reasons;
            }
        }

        ksort($rows, SORT_NUMERIC);
        ksort($rejected, SORT_NUMERIC);

        $spill = SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext107(
            $pageCount,
            $cacheSize,
            $spillThreshold,
            self::spillInputs($admitted),
            'wal',
            $walSynced,
            'exclusive',
            $cacheSpillEnabled,
            $maxSpillPages
        );

        $spilledPages = $spill['next']['spilled_pages'] ?? [];
        $admittedPages = array_column($admitted, 'page');
        sort($admittedPages, SORT_NUMERIC);
        $rejectedPages = array_keys($rejected);
        sort($rejectedPages, SORT_NUMERIC);
        $nextWalFrameStart = $nextWal->frameCount() + 1;
        $appendFrames = [];
        foreach ($spilledPages as $offset => $page) {
            $appendFrames[] = [
                'frame_index' => $nextWalFrameStart + $offset,
                'page' => $page,
                'source' => 'cache-spill-after-hot-journal-reader-current-source',
            ];
        }

        $status = (bool) $reader['hot_recovered']
            && (bool) $reader['current_reader_preserved']
            && $spilledPages !== []
            ? 'pager_cache_spill_hot_journal_reader_current_source_next147'
            : 'pager_cache_spill_hot_journal_reader_current_source_deferred_next147';

        return [
            'status' => $status,
            'reason' => $status === 'pager_cache_spill_hot_journal_reader_current_source_next147'
                ? 'cache_spill_uses_hot_journal_reader_current_source_before_next_wal_generation'
                : 'cache_spill_deferred_until_hot_journal_reader_current_source_is_safe',
            'database_path' => $databasePath,
            'journal_path' => $reader['journal_path'],
            'wal_path' => $reader['wal_path'],
            'page_size' => $pageSize,
            'page_count' => $pageCount,
            'reader_end_frame' => $readerEndFrame,
            'hot_recovered' => (bool) $reader['hot_recovered'],
            'current_reader_preserved' => (bool) $reader['current_reader_preserved'],
            'next_source_separated' => (bool) $reader['next_source_separated'],
            'current_wal_sha256' => $reader['current_wal_sha256'],
            'next_wal_sha256' => $reader['next_wal_sha256'],
            'admitted_page_numbers' => $admittedPages,
            'rejected_page_numbers' => $rejectedPages,
            'rejected_pages' => $rejected,
            'source_checks' => $rows,
            'spill' => $spill,
            'spilled_page_numbers' => $spilledPages,
            'next_wal_frame_start' => $nextWalFrameStart,
            'appended_wal_frames' => $appendFrames,
            'operation_reasons' => array_merge($reader['operation_reasons'], [
                'verify_cache_pages_against_hot_journal_reader_current_source_next147',
                'append_cache_spill_frames_after_next_wal_generation_next147',
            ]),
            'operations' => array_values(array_merge(
                self::filterOperations($admittedPages, $rejected),
                $spill['operations'] ?? []
            )),
            'base_reader_plan' => $reader,
            'dependencies' => array_values(array_unique(array_merge(
                $reader['dependencies'],
                $spill['dependencies'] ?? [],
                [
                    'sqlite-pager-cache-spill-hot-journal-reader-current-source-next147',
                    'sqlite-wal-hot-journal-reader-restart-current-source-next143',
                    'sqlite-pager-cache-spill-journalmode-current-source-next107',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native hot rollback-journal recovery, WAL reader snapshots, restarted WAL generation parsing, and cache-spill routing',
            'non_overlap' => 'avoids accepted hot-journal reader restart next143 and pager cache-spill WAL savepoint next143 by proving spill admission is checked against the pinned hot-journal current reader before writes append to the next WAL generation',
        ];
    }

    /**
     * @return array<int,array{image:string,source:string,frame:?int}>
     */
    private static function currentReaderSources(SQLiteWal $wal, string $databaseBytes, int $pageSize, int $pageCount, int $readerEndFrame): array
    {
        $sources = [];
        for ($page = 1; $page <= $pageCount; $page++) {
            $snapshot = $wal->readerSnapshotPageImage($databaseBytes, $page, $readerEndFrame);
            $sources[$page] = [
                'image' => (string) $snapshot['image'],
                'source' => (string) $snapshot['source'],
                'frame' => $snapshot['frame_index'],
            ];
        }

        return $sources;
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,image:string,current_image?:string,bytes?:int,dirty?:bool,pinned?:bool,journaled?:bool,walFrame?:int,readerPinned?:bool,nextGeneration?:bool}>
     */
    private static function normalizeCachePages(array $cachePages, int $pageCount, int $pageSize): array
    {
        $normalized = [];
        $seen = [];
        foreach ($cachePages as $cachePage) {
            $page = $cachePage['page'] ?? null;
            if (!is_int($page) || $page < 1 || $page > $pageCount) {
                throw new \InvalidArgumentException('SQLite pager cache-spill hot-journal reader current-source next147 cache pages must be one-based pages inside the database image');
            }
            if (isset($seen[$page])) {
                throw new \InvalidArgumentException('SQLite pager cache-spill hot-journal reader current-source next147 cache pages must be unique');
            }
            $seen[$page] = true;
            $image = $cachePage['image'] ?? null;
            if (!is_string($image) || strlen($image) !== $pageSize) {
                throw new \InvalidArgumentException('SQLite pager cache-spill hot-journal reader current-source next147 cache image must match page size');
            }
            if (isset($cachePage['current_image']) && (!is_string($cachePage['current_image']) || strlen($cachePage['current_image']) !== $pageSize)) {
                throw new \InvalidArgumentException('SQLite pager cache-spill hot-journal reader current-source next147 current image must match page size');
            }
            if (isset($cachePage['bytes']) && (!is_int($cachePage['bytes']) || $cachePage['bytes'] < 0)) {
                throw new \InvalidArgumentException('SQLite pager cache-spill hot-journal reader current-source next147 cache bytes must be non-negative');
            }
            if (isset($cachePage['walFrame']) && (!is_int($cachePage['walFrame']) || $cachePage['walFrame'] < 1)) {
                throw new \InvalidArgumentException('SQLite pager cache-spill hot-journal reader current-source next147 cache WAL frame must be positive');
            }
            $cachePage['image'] = $image;
            $normalized[] = $cachePage;
        }

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $cachePages
     * @return list<array{page:int,bytes?:int,journaled?:bool,dirty?:bool,pinned?:bool,walFrame?:int}>
     */
    private static function spillInputs(array $cachePages): array
    {
        return array_map(
            static fn (array $cachePage): array => array_filter(
                [
                    'page' => $cachePage['page'],
                    'bytes' => $cachePage['bytes'] ?? null,
                    'journaled' => $cachePage['journaled'] ?? null,
                    'dirty' => $cachePage['dirty'] ?? null,
                    'pinned' => $cachePage['pinned'] ?? null,
                    'walFrame' => $cachePage['walFrame'] ?? null,
                ],
                static fn (mixed $value): bool => $value !== null
            ),
            $cachePages
        );
    }

    /**
     * @param list<int> $admittedPages
     * @param array<int,list<string>> $rejectedPages
     * @return list<array<string,mixed>>
     */
    private static function filterOperations(array $admittedPages, array $rejectedPages): array
    {
        $operations = [];
        foreach ($admittedPages as $page) {
            $operations[] = [
                'op' => 'admit_hot_journal_reader_cache_spill_page',
                'page' => $page,
                'reason' => 'cache_page_matches_pinned_hot_journal_reader_current_source',
            ];
        }
        foreach ($rejectedPages as $page => $reasons) {
            $operations[] = [
                'op' => 'defer_hot_journal_reader_cache_spill_page',
                'page' => $page,
                'reasons' => $reasons,
            ];
        }

        return $operations;
    }

    private static function prefix(string $bytes): string
    {
        return rtrim(substr($bytes, 0, 64), ".\0 ");
    }
}
