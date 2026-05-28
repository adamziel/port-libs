<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalReaderRestartCurrentSourceNext131Plan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        SQLiteWal $wal,
        string $walBytes,
        array $pageNumbers,
        int $readerEndFrame,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($readerEndFrame < 0 || $readerEndFrame > $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal reader restart next131 reader frame is outside the WAL frame range');
        }

        $base = SQLiteWalHotJournalCheckpointRestartCurrentSourceNext129Plan::plan(
            $databasePath,
            $databaseBytes,
            $journalBytes,
            $wal,
            $walBytes,
            $pageNumbers,
            $readerEndFrame,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        $pageSize = $wal->header->pageSize;
        $pinnedWalBytes = (string) $base['pinned_checkpoint']['wal_bytes'];
        $releasedWalBytes = (string) $base['released_checkpoint']['wal_bytes'];
        $hotDatabaseBytes = (string) $base['hot_journal']['database_bytes'];
        $releasedDatabaseBytes = (string) $base['released_checkpoint']['database_bytes'];
        $restartedCurrentWal = $pinnedWalBytes === ''
            ? null
            : SQLiteWal::parse($pinnedWalBytes, $pageSize, true);
        $releasedWal = $releasedWalBytes === ''
            ? null
            : SQLiteWal::parse($releasedWalBytes, $pageSize, true);

        $restartRows = [];
        $nextRows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal reader restart next131 pages must be one-based integers');
            }

            $restartRows[] = $restartedCurrentWal === null
                ? self::databaseVisibility($hotDatabaseBytes, $pageSize, $pageNumber, 'checkpoint-database')
                : $restartedCurrentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $readerEndFrame);
            $nextRows[] = $releasedWal === null
                ? self::databaseVisibility($releasedDatabaseBytes, $pageSize, $pageNumber, 'restart-database')
                : $releasedWal->readerSnapshotPageImage($releasedDatabaseBytes, $pageNumber, $releasedWal->frameCount());
        }

        $originalRows = $base['rows'];
        $restartMatches = [];
        $nextSeparated = [];
        $transitions = [];
        foreach ($originalRows as $index => $row) {
            $restartMatches[] = $row['reader_label'] === self::label((string) $restartRows[$index]['image']);
            $nextSeparated[] = $row['pinned_next_label'] !== self::label((string) $nextRows[$index]['image'])
                || $row['pinned_next_source'] !== $nextRows[$index]['source'];
            $transitions[] = implode('>', [
                $row['reader_source'],
                $restartRows[$index]['source'],
                $nextRows[$index]['source'],
            ]);
        }

        $currentSourceReused = hash('sha256', $walBytes) === hash('sha256', $pinnedWalBytes);
        $restartHeaderSeparated = $releasedWalBytes !== '' && hash('sha256', $releasedWalBytes) !== hash('sha256', $walBytes);
        $status = (bool) $base['hot_recovered'] && (bool) $base['pinned_checkpoint_busy'] && $currentSourceReused
            ? 'wal-hot-journal-reader-restart-current-source-next131'
            : 'wal-hot-journal-reader-restart-current-source-blocked-next131';

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-reader-restart-current-source-next131'
                ? 'reader_restart_reuses_preserved_current_wal_after_hot_journal_recovery'
                : $base['reason'],
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'hot_recovered' => (bool) $base['hot_recovered'],
            'pinned_checkpoint_busy' => (bool) $base['pinned_checkpoint_busy'],
            'released_checkpoint_busy' => (bool) $base['released_checkpoint_busy'],
            'pinned_wal_action' => $base['pinned_wal_action'],
            'released_wal_action' => $base['released_wal_action'],
            'current_source_reused_for_reader_restart' => $currentSourceReused,
            'restart_header_separated_for_next_reader' => $restartHeaderSeparated,
            'reader_restart_sources' => self::column($restartRows, 'source'),
            'reader_restart_frame_indexes' => self::column($restartRows, 'frame_index'),
            'next_generation_sources' => self::column($nextRows, 'source'),
            'next_generation_frame_indexes' => self::column($nextRows, 'frame_index'),
            'reader_restart_rows' => $restartRows,
            'next_generation_rows' => $nextRows,
            'reader_restart_labels' => array_map(static fn (array $row): string => self::label((string) $row['image']), $restartRows),
            'next_generation_labels' => array_map(static fn (array $row): string => self::label((string) $row['image']), $nextRows),
            'reader_restart_matches_original_reader' => !in_array(false, $restartMatches, true),
            'next_generation_separated_from_pinned_reader' => in_array(true, $nextSeparated, true),
            'reader_restart_transitions' => $transitions,
            'current_reader_wal_sha256' => hash('sha256', $walBytes),
            'restarted_current_reader_wal_sha256' => hash('sha256', $pinnedWalBytes),
            'next_generation_wal_sha256' => hash('sha256', $releasedWalBytes),
            'operation_reasons' => array_merge($base['operation_reasons'], [
                'restart_current_reader_from_preserved_wal_next131',
                'open_next_reader_on_restarted_generation_next131',
            ]),
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'],
                [
                    'sqlite-wal-hot-journal-reader-restart-current-source-next131',
                    'sqlite-wal-hot-journal-checkpoint-restart-current-source-next129',
                ]
            ))),
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databaseVisibility(string $databaseBytes, int $pageSize, int $pageNumber, string $source): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal reader restart next131 database bytes must be page aligned');
        }

        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL hot-journal reader restart next131 page {$pageNumber} is outside the database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => $source,
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $pageCount,
        ];
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
