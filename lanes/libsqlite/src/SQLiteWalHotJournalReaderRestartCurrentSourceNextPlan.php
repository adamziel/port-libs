<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan
{
    public static function hotJournalReaderRestartPlan(
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
    ): array
    {
        $impl = new class {
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

                    $base = SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan::plan(
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
        };

        return $impl::plan($databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, $pageNumbers, $readerEndFrame, $reservedLock, $requiresSuperJournal, $superJournalExists);
    }

    public static function hotJournalReaderRestartSeparatedWalPlan(
        string $databasePath,
        string $dirtyDatabaseBytes,
        string $journalBytes,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $restartedWalBytes,
        array $pageNumbers,
        int $readerEndFrame,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array
    {
        $impl = new class {
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
                    string $restartedWalBytes,
                    array $pageNumbers,
                    int $readerEndFrame,
                    bool $reservedLock = false,
                    bool $requiresSuperJournal = false,
                    ?bool $superJournalExists = null
                ): array {
                    if ($restartedWalBytes === '') {
                        throw new \InvalidArgumentException('SQLite WAL hot-journal reader restart current-source next143 requires restarted WAL bytes');
                    }
                    if ($readerEndFrame < 0 || $readerEndFrame > $currentWal->frameCount()) {
                        throw new \InvalidArgumentException('SQLite WAL hot-journal reader restart current-source next143 reader frame is outside the current WAL range');
                    }

                    $base = SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan::hotJournalReaderRestartPlan(
                        $databasePath,
                        $dirtyDatabaseBytes,
                        $journalBytes,
                        $currentWal,
                        $currentWalBytes,
                        $pageNumbers,
                        $readerEndFrame,
                        $reservedLock,
                        $requiresSuperJournal,
                        $superJournalExists
                    );

                    $pageSize = (int) $base['page_size'];
                    $hotDatabaseBytes = (string) ($base['base_plan']['hot_journal']['database_bytes'] ?? '');
                    if ($hotDatabaseBytes === '') {
                        throw new \UnexpectedValueException('SQLite WAL hot-journal reader restart current-source next143 requires recovered hot-journal database bytes');
                    }

                    $restartedWal = SQLiteWal::parse($restartedWalBytes, $pageSize, true);
                    self::assertRestartGeneration($currentWal, $restartedWal);

                    $currentRows = [];
                    $nextRows = [];
                    foreach ($pageNumbers as $pageNumber) {
                        if (!is_int($pageNumber) || $pageNumber < 1) {
                            throw new \InvalidArgumentException('SQLite WAL hot-journal reader restart current-source next143 pages must be one-based integers');
                        }

                        $currentRows[] = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $readerEndFrame);
                        $nextRows[] = $restartedWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $restartedWal->frameCount());
                    }

                    $rows = [];
                    foreach ($pageNumbers as $index => $pageNumber) {
                        $baseReader = $base['reader_restart_rows'][$index] ?? null;
                        $currentImage = (string) $currentRows[$index]['image'];
                        $nextImage = (string) $nextRows[$index]['image'];
                        $rows[] = [
                            'page_number' => $pageNumber,
                            'hot_recovered_label' => self::databaseLabel($hotDatabaseBytes, $pageSize, $pageNumber),
                            'current_source' => $currentRows[$index]['source'],
                            'current_frame' => $currentRows[$index]['frame_index'],
                            'current_label' => self::label($currentImage),
                            'base_reader_label' => is_array($baseReader) ? self::label((string) $baseReader['image']) : null,
                            'next_source' => $nextRows[$index]['source'],
                            'next_frame' => $nextRows[$index]['frame_index'],
                            'next_label' => self::label($nextImage),
                            'reader_matches_base_restart' => is_array($baseReader) && $currentImage === (string) $baseReader['image'],
                            'next_separated_from_current_reader' => $nextImage !== $currentImage || $nextRows[$index]['source'] !== $currentRows[$index]['source'],
                            'source_transition' => $currentRows[$index]['source'] . '>restart-boundary>' . $nextRows[$index]['source'],
                        ];
                    }

                    $separatedRows = array_values(array_filter(
                        $rows,
                        static fn (array $row): bool => (bool) $row['next_separated_from_current_reader']
                    ));
                    $readerMatches = !in_array(false, array_column($rows, 'reader_matches_base_restart'), true);
                    $currentSource = self::walSource($currentWal, $currentWalBytes);
                    $nextSource = self::walSource($restartedWal, $restartedWalBytes);
                    $nextSourceSeparated = $currentSource['sha256'] !== $nextSource['sha256']
                        && ($nextSource['checkpoint_sequence'] > $currentSource['checkpoint_sequence']
                            || $nextSource['salt_1'] !== $currentSource['salt_1']
                            || $nextSource['salt_2'] !== $currentSource['salt_2']);
                    $status = (bool) $base['hot_recovered']
                        && (bool) $base['current_source_reused_for_reader_restart']
                        && $readerMatches
                        && $nextSourceSeparated
                        ? 'wal-hot-journal-reader-restart-current-source-next143'
                        : 'wal-hot-journal-reader-restart-current-source-blocked-next143';

                    return [
                        'status' => $status,
                        'reason' => $status === 'wal-hot-journal-reader-restart-current-source-next143'
                            ? 'hot_journal_reader_restarts_on_current_wal_source_before_next_generation'
                            : 'hot_journal_reader_restart_current_source_not_separated',
                        'database_path' => $databasePath,
                        'journal_path' => $base['journal_path'],
                        'wal_path' => $base['wal_path'],
                        'page_size' => $pageSize,
                        'reader_end_frame' => $readerEndFrame,
                        'hot_recovered' => (bool) $base['hot_recovered'],
                        'current_reader_preserved' => (bool) $base['current_source_reused_for_reader_restart'],
                        'reader_matches_base_restart' => $readerMatches,
                        'next_source_separated' => $nextSourceSeparated,
                        'current_wal_source' => $currentSource,
                        'next_wal_source' => $nextSource,
                        'current_wal_sha256' => hash('sha256', $currentWalBytes),
                        'next_wal_sha256' => hash('sha256', $restartedWalBytes),
                        'current_sources' => self::column($currentRows, 'source'),
                        'current_frame_indexes' => self::column($currentRows, 'frame_index'),
                        'next_sources' => self::column($nextRows, 'source'),
                        'next_frame_indexes' => self::column($nextRows, 'frame_index'),
                        'current_labels' => array_column($rows, 'current_label'),
                        'next_labels' => array_column($rows, 'next_label'),
                        'next_separated_page_numbers' => array_column($separatedRows, 'page_number'),
                        'next_separated_page_count' => count($separatedRows),
                        'source_transitions' => array_column($rows, 'source_transition'),
                        'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
                        'rows' => $rows,
                        'operation_reasons' => array_merge($base['operation_reasons'], [
                            'restart_current_reader_from_hot_journal_current_source_next143',
                            'open_next_reader_on_restarted_wal_generation_next143',
                        ]),
                        'base_plan' => $base,
                        'dependencies' => array_values(array_unique(array_merge(
                            $base['dependencies'],
                            [
                                'sqlite-wal-hot-journal-reader-restart-current-source-next143',
                                'sqlite-wal-hot-journal-reader-restart-current-source-next131',
                                'sqlite-wal-restart-generation-boundary',
                            ]
                        ))),
                        'dependency_closure' => 'no new support component needed; reuses native rollback-journal hot recovery, WAL parsing, and reader snapshot current-source helpers',
                        'non_overlap' => 'avoids accepted next131 hot-journal preserved-reader restart and next140 checkpoint reader restart by proving the combined hot-recovered current source remains pinned while a distinct restarted WAL generation serves later readers',
                    ];
                }

                private static function assertRestartGeneration(SQLiteWal $previous, SQLiteWal $next): void
                {
                    if ($next->header->checkpointSequence <= $previous->header->checkpointSequence) {
                        throw new \InvalidArgumentException('SQLite WAL hot-journal reader restart current-source next143 restarted WAL must advance the checkpoint sequence');
                    }
                    if ($next->header->salt1 === $previous->header->salt1 && $next->header->salt2 === $previous->header->salt2) {
                        throw new \InvalidArgumentException('SQLite WAL hot-journal reader restart current-source next143 restarted WAL must use a distinct salt pair');
                    }
                }

                /**
                 * @return array{magic:int,version:int,page_size:int,checkpoint_sequence:int,salt_1:int,salt_2:int,frame_count:int,sha256:string}
                 */
                private static function walSource(SQLiteWal $wal, string $walBytes): array
                {
                    return [
                        'magic' => $wal->header->magic,
                        'version' => $wal->header->formatVersion,
                        'page_size' => $wal->header->pageSize,
                        'checkpoint_sequence' => $wal->header->checkpointSequence,
                        'salt_1' => $wal->header->salt1,
                        'salt_2' => $wal->header->salt2,
                        'frame_count' => $wal->frameCount(),
                        'sha256' => hash('sha256', $walBytes),
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

                private static function databaseLabel(string $databaseBytes, int $pageSize, int $pageNumber): string
                {
                    return self::label(substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize));
                }

                private static function label(string $image): string
                {
                    return rtrim(substr($image, 0, 96), ".\0");
                }
        };

        return $impl::plan($databasePath, $dirtyDatabaseBytes, $journalBytes, $currentWal, $currentWalBytes, $restartedWalBytes, $pageNumbers, $readerEndFrame, $reservedLock, $requiresSuperJournal, $superJournalExists);
    }
}
