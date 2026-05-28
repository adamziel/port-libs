<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext166Plan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string}> $readerCachePages
     * @param list<int> $checkpointPages
     * @param array<string,list<int>> $releasedSavepointPages
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $innerSavepoint,
        string $outerSavepoint,
        array $hotJournalPages,
        array $savepointBeforePages,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        SQLiteWal $nextWal,
        string $nextWalBytes,
        array $readerCachePages,
        array $checkpointPages,
        array $releasedSavepointPages,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        int $currentSourceEpoch = 1,
    ): array {
        if ($outerSavepoint === '' || $outerSavepoint === $innerSavepoint) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next166 requires a distinct outer savepoint');
        }
        self::assertReleasedPages($releasedSavepointPages);

        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext161Plan::plan(
            $databasePath,
            $databaseBytes,
            $pageSize,
            $innerSavepoint,
            $hotJournalPages,
            $savepointBeforePages,
            $currentWal,
            $currentWalBytes,
            $nextWal,
            $nextWalBytes,
            $readerCachePages,
            $checkpointPages,
            $mode,
            $readerEndFrame,
            $currentSourceEpoch,
        );

        $savepointPages = $base['savepoint_rollback_page_numbers'];
        $releasedInnerPages = $releasedSavepointPages[$innerSavepoint] ?? [];
        $missingReleasePages = array_values(array_diff($savepointPages, $releasedInnerPages));
        sort($missingReleasePages, SORT_NUMERIC);

        $releaseRows = [];
        foreach ($savepointPages as $pageNumber) {
            $releaseRows[] = [
                'savepoint' => $innerSavepoint,
                'outer_savepoint' => $outerSavepoint,
                'page_number' => $pageNumber,
                'released_to_outer' => in_array($pageNumber, $releasedInnerPages, true),
                'checkpoint_label' => self::rowLabel($base, $pageNumber, 'checkpoint_label'),
                'next_label' => self::rowLabel($base, $pageNumber, 'next_label'),
            ];
        }

        $barrierRows = [];
        foreach ($base['rows'] as $row) {
            $pageNumber = (int) $row['page_number'];
            $barrierRows[] = [
                'page_number' => $pageNumber,
                'write_order' => count($barrierRows) + 1,
                'requires_release_fence' => in_array($pageNumber, $savepointPages, true),
                'cache_admitted' => (bool) $row['cache_admitted'],
                'checkpoint_source' => $row['checkpoint_source'],
                'next_source' => $row['next_source'],
            ];
        }

        $releaseComplete = $missingReleasePages === [];
        $baseReady = $base['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next161';
        $status = $baseReady && $releaseComplete
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-release-next166'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-release-blocked-next166';

        $releaseOperations = [
            [
                'op' => 'validate_inner_savepoint_release_pages_before_checkpoint_publish',
                'savepoint' => $innerSavepoint,
                'outer_savepoint' => $outerSavepoint,
                'missing_pages' => $missingReleasePages,
            ],
            [
                'op' => 'fence_checkpoint_current_source_after_savepoint_release',
                'source_id' => $base['current_source_token']['id'],
                'epoch' => $base['current_source_token']['epoch'],
                'release_complete' => $releaseComplete,
            ],
            [
                'op' => 'publish_next_wal_after_release_checkpoint_fence',
                'source_id' => $base['next_source_token']['id'],
                'epoch' => $base['next_source_token']['epoch'],
                'requires_reader_reopen' => $base['requires_reader_reopen'],
            ],
        ];

        return array_merge($base, [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-release-next166'
                ? 'savepoint_release_lineage_fenced_before_checkpoint_current_source_publish'
                : 'savepoint_release_lineage_missing_before_checkpoint_current_source_publish',
            'inner_savepoint' => $innerSavepoint,
            'outer_savepoint' => $outerSavepoint,
            'released_savepoint_pages' => $releasedSavepointPages,
            'released_inner_page_numbers' => $releasedInnerPages,
            'missing_release_page_numbers' => $missingReleasePages,
            'release_complete' => $releaseComplete,
            'release_rows' => $releaseRows,
            'writer_barrier_rows' => $barrierRows,
            'writer_barrier_page_order' => array_column($barrierRows, 'page_number'),
            'release_operations' => $releaseOperations,
            'operation_names_next166' => array_merge($base['operation_names'], array_column($releaseOperations, 'op')),
            'source_digest_next166' => hash('sha256', $base['source_digest'] . '|' . $innerSavepoint . '|' . $outerSavepoint . '|' . implode(',', $releasedInnerPages)),
            'dependencies_next166' => [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next166',
                'sqlite-savepoint-release-lineage-current-source-fence',
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next161',
            ],
            'dependency_closure_next166' => 'no new support component needed; reuses WAL parsing, durable checkpoint planning, and next161 current-source cache fences while adding RELEASE lineage validation',
            'non_overlap_next166' => 'does not repeat accepted WAL byte truncation, rollback-journal apply, checkpoint transaction, or next161 cache-token fencing; this slice adds RELEASE-to-outer-savepoint lineage before checkpoint current-source publication',
        ]);
    }

    /**
     * @param array<string,list<int>> $releasedSavepointPages
     */
    private static function assertReleasedPages(array $releasedSavepointPages): void
    {
        if ($releasedSavepointPages === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next166 requires released savepoint page lineage');
        }
        foreach ($releasedSavepointPages as $name => $pages) {
            if (!is_string($name) || $name === '' || $pages === []) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next166 release lineage requires named savepoints with pages');
            }
            foreach ($pages as $pageNumber) {
                if (!is_int($pageNumber) || $pageNumber < 1) {
                    throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next166 release page numbers must be one-based integers');
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $base
     */
    private static function rowLabel(array $base, int $pageNumber, string $field): ?string
    {
        foreach ($base['rows'] as $row) {
            if ((int) $row['page_number'] === $pageNumber) {
                return is_string($row[$field] ?? null) ? $row[$field] : null;
            }
        }

        return null;
    }
}
