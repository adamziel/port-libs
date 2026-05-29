<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext154Plan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $dirtyDatabaseBytes,
        string $journalBytes,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $readerDatabaseBytes,
        string $readerWalBytes,
        string $checkpointDatabaseBytes,
        string $savepointName,
        int $savepointEndFrame,
        array $pageNumbers,
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($checkpointDatabaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next154 requires checkpoint database bytes');
        }
        if ($savepointName === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next154 requires a savepoint name');
        }
        if ($savepointEndFrame < 0 || $savepointEndFrame > $currentWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next154 savepoint frame is outside the current WAL range');
        }

        $base = SQLiteWalCheckpointHotJournalReaderCurrentSourceNext144Plan::plan(
            $databasePath,
            $dirtyDatabaseBytes,
            $journalBytes,
            $currentWal,
            $currentWalBytes,
            $readerDatabaseBytes,
            $readerWalBytes,
            $pageNumbers,
            $readerEndFrame,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        $pageSize = (int) $base['page_size'];
        self::assertPageAligned($checkpointDatabaseBytes, $pageSize);
        $hotDatabaseBytes = (string) ($base['hot_journal']['database_bytes'] ?? '');
        if ($hotDatabaseBytes === '') {
            throw new \UnexpectedValueException('SQLite WAL hot-journal savepoint checkpoint current-source next154 requires recovered hot-journal database bytes');
        }
        self::assertPageAligned($hotDatabaseBytes, $pageSize);

        $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
        $readerEndFrame = (int) $base['reader_end_frame'];
        $currentEndFrame = $currentWal->frameCount();

        $readerRows = [];
        $savepointRows = [];
        $tailRows = [];
        $checkpointRows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next154 pages must be one-based integers');
            }

            $readerRows[] = $readerWal->readerSnapshotPageImage($readerDatabaseBytes, $pageNumber, $readerEndFrame);
            $savepointRows[] = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $savepointEndFrame);
            $tailRows[] = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $currentEndFrame);
            $checkpointRows[] = self::databaseRow($checkpointDatabaseBytes, $pageSize, $pageNumber);
        }

        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $readerImage = (string) $readerRows[$index]['image'];
            $savepointImage = (string) $savepointRows[$index]['image'];
            $tailImage = (string) $tailRows[$index]['image'];
            $checkpointImage = (string) $checkpointRows[$index]['image'];
            $rows[] = [
                'page_number' => $pageNumber,
                'reader_source' => $readerRows[$index]['source'],
                'reader_frame' => $readerRows[$index]['frame_index'],
                'savepoint_source' => $savepointRows[$index]['source'],
                'savepoint_frame' => $savepointRows[$index]['frame_index'],
                'tail_source' => $tailRows[$index]['source'],
                'tail_frame' => $tailRows[$index]['frame_index'],
                'checkpoint_source' => $checkpointRows[$index]['source'],
                'reader_label' => self::label($readerImage),
                'savepoint_label' => self::label($savepointImage),
                'tail_label' => self::label($tailImage),
                'checkpoint_label' => self::label($checkpointImage),
                'checkpoint_matches_savepoint' => $checkpointImage === $savepointImage,
                'tail_differs_from_savepoint' => $tailImage !== $savepointImage || $tailRows[$index]['source'] !== $savepointRows[$index]['source'],
                'reader_preserved' => $readerImage === $savepointImage && $readerRows[$index]['source'] === $savepointRows[$index]['source'],
                'source_transition' => $readerRows[$index]['source'] . '>savepoint-' . $savepointRows[$index]['source'] . '>tail-' . $tailRows[$index]['source'] . '>checkpoint-db',
            ];
        }

        $mismatchedCheckpointPages = self::pageColumn(array_filter(
            $rows,
            static fn (array $row): bool => $row['checkpoint_matches_savepoint'] === false
        ));
        $tailDiscardedPages = self::pageColumn(array_filter(
            $rows,
            static fn (array $row): bool => $row['tail_differs_from_savepoint'] === true
        ));
        $readerPreserved = !in_array(false, array_column($rows, 'reader_preserved'), true);
        $checkpointMatchesSavepoint = $mismatchedCheckpointPages === [];
        $tailFramesDiscarded = $tailDiscardedPages !== [];
        $checkpointAllowed = (bool) $base['hot_recovered']
            && $checkpointMatchesSavepoint
            && $readerPreserved
            && $savepointEndFrame <= $readerEndFrame;
        $status = !(bool) $base['hot_recovered']
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next154'
            : ($checkpointAllowed
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next154'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-deferred-next154');

        return [
            'status' => $status,
            'reason' => !(bool) $base['hot_recovered']
                ? (string) $base['reason']
                : ($checkpointAllowed
                    ? 'checkpoint_database_matches_savepoint_visible_current_source_after_hot_journal_recovery'
                    : 'checkpoint_database_or_reader_frame_does_not_match_savepoint_visible_current_source'),
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $pageSize,
            'savepoint' => $savepointName,
            'savepoint_end_frame' => $savepointEndFrame,
            'reader_end_frame' => $readerEndFrame,
            'current_end_frame' => $currentEndFrame,
            'hot_recovered' => (bool) $base['hot_recovered'],
            'base_checkpoint_allowed' => (bool) $base['checkpoint_allowed'],
            'checkpoint_allowed' => $checkpointAllowed,
            'checkpoint_matches_savepoint' => $checkpointMatchesSavepoint,
            'reader_preserved_at_savepoint' => $readerPreserved,
            'tail_frames_discarded_by_savepoint' => $tailFramesDiscarded,
            'checkpoint_mismatched_page_numbers' => $mismatchedCheckpointPages,
            'tail_discarded_page_numbers' => $tailDiscardedPages,
            'tail_discarded_page_count' => count($tailDiscardedPages),
            'reader_sources' => self::column($readerRows, 'source'),
            'reader_frame_indexes' => self::column($readerRows, 'frame_index'),
            'savepoint_sources' => self::column($savepointRows, 'source'),
            'savepoint_frame_indexes' => self::column($savepointRows, 'frame_index'),
            'tail_sources' => self::column($tailRows, 'source'),
            'tail_frame_indexes' => self::column($tailRows, 'frame_index'),
            'checkpoint_sources' => self::column($checkpointRows, 'source'),
            'reader_labels' => array_column($rows, 'reader_label'),
            'savepoint_labels' => array_column($rows, 'savepoint_label'),
            'tail_labels' => array_column($rows, 'tail_label'),
            'checkpoint_labels' => array_column($rows, 'checkpoint_label'),
            'checkpoint_database_source' => self::databaseSource($checkpointDatabaseBytes, $pageSize),
            'hot_database_source' => $base['current_database_source'],
            'current_wal_source' => $base['current_wal_source'],
            'reader_wal_source' => $base['reader_wal_source'],
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'operation_reasons' => array_merge($base['operation_reasons'], $checkpointAllowed ? [
                'apply_checkpoint_at_savepoint_visible_frame_next154',
                'discard_wal_tail_after_savepoint_before_reset_next154',
            ] : [
                'defer_checkpoint_until_savepoint_visible_source_matches_next154',
            ]),
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next154',
                'sqlite-wal-checkpoint-hot-journal-reader-current-source-next144',
                'sqlite-savepoint-visible-wal-frame-boundary',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses native hot-journal recovery, WAL reader snapshots, and savepoint-visible frame-boundary comparison',
            'non_overlap' => 'avoids accepted next148 end-of-WAL checkpoint matching, WAL byte truncation, and VFS writer application by validating checkpoint database bytes against the savepoint-visible WAL frame boundary after hot-journal recovery',
        ];
    }

    private static function assertPageAligned(string $bytes, int $pageSize): void
    {
        if (strlen($bytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next154 checkpoint database bytes must be page-size aligned');
        }
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databaseRow(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL hot-journal savepoint checkpoint current-source next154 page {$pageNumber} is outside the checkpoint database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'checkpoint-database',
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $pageCount,
        ];
    }

    /**
     * @return array{bytes:int,page_size:int,page_count:int,sha256:string}
     */
    private static function databaseSource(string $databaseBytes, int $pageSize): array
    {
        return [
            'bytes' => strlen($databaseBytes),
            'page_size' => $pageSize,
            'page_count' => intdiv(strlen($databaseBytes), $pageSize),
            'sha256' => hash('sha256', $databaseBytes),
        ];
    }

    /**
     * @param iterable<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function pageColumn(iterable $rows): array
    {
        $pages = [];
        foreach ($rows as $row) {
            $pages[] = (int) $row['page_number'];
        }

        return $pages;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function column(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
