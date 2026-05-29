<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan
{
    public static function next120Plan(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $readerWalBytes,
        string $databasePath,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        ?int $databasePageSize = null,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array
    {
        $impl = new class {
                /**
                 * @param list<int> $pageNumbers
                 * @return array<string,mixed>
                 */
                public static function plan(
                    SQLiteRollbackJournal $journal,
                    string $databaseBytes,
                    string $journalBytes,
                    SQLiteSavepointStack $savepoints,
                    string $savepoint,
                    SQLiteWal $wal,
                    string $walBytes,
                    string $readerWalBytes,
                    string $databasePath,
                    array $pageNumbers,
                    string $mode = 'restart',
                    ?int $readerEndFrame = null,
                    ?int $databasePageSize = null,
                    bool $databaseReservedLock = false,
                    bool $requiresSuperJournal = false,
                    ?bool $superJournalExists = null,
                ): array {
                    if ($readerWalBytes === '') {
                        throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint reader current-source next120 requires reader WAL bytes');
                    }

                    $pageSize = $databasePageSize ?? ($wal->header->pageSize >= 512 ? $wal->header->pageSize : null);
                    if ($pageSize === null) {
                        throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint reader current-source next120 requires a page size');
                    }

                    $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
                    $readerEndFrame ??= $readerWal->frameCount();
                    if ($readerEndFrame < 0) {
                        throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint reader current-source next120 reader frame must be non-negative');
                    }

                    $pinned = SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan::plan(
                        $journal,
                        $databaseBytes,
                        $journalBytes,
                        $savepoints,
                        $savepoint,
                        $wal,
                        $walBytes,
                        $databasePath,
                        $pageNumbers,
                        $mode,
                        $readerEndFrame,
                        $pageSize,
                        $databaseReservedLock,
                        $requiresSuperJournal,
                        $superJournalExists
                    );
                    $released = SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan::plan(
                        $journal,
                        $databaseBytes,
                        $journalBytes,
                        $savepoints,
                        $savepoint,
                        $wal,
                        $walBytes,
                        $databasePath,
                        $pageNumbers,
                        $mode,
                        null,
                        $pageSize,
                        $databaseReservedLock,
                        $requiresSuperJournal,
                        $superJournalExists
                    );

                    $readerRows = self::readerRows($readerWal, $databaseBytes, $pageNumbers, $readerEndFrame);
                    $readerSources = self::column($readerRows, 'source');
                    $readerFrames = self::column($readerRows, 'frame_index');
                    $readerImages = self::column($readerRows, 'image');
                    $currentImages = self::column($pinned['current_reader'], 'image');
                    $releasedImages = self::column($released['next_reader'], 'image');

                    return [
                        'status' => !$pinned['hot_recovered']
                            ? 'hot-journal-checkpoint-reader-current-source-skipped-next120'
                            : ($pinned['checkpoint_busy'] && !$released['checkpoint_busy']
                            ? 'hot-journal-checkpoint-reader-current-source-next120'
                            : 'hot-journal-checkpoint-reader-current-source-' . ($pinned['checkpoint_busy'] ? 'busy' : 'ready') . '-next120'),
                        'reason' => !$pinned['hot_recovered']
                            ? 'hot_journal_not_hot_checkpoint_reader_current_source'
                            : ($pinned['checkpoint_busy'] && !$released['checkpoint_busy']
                            ? 'reader_source_blocks_reset_until_release_while_checkpoint_uses_current_wal_prefix'
                            : $pinned['checkpoint_reason']),
                        'database_path' => $databasePath,
                        'journal_path' => $databasePath . '-journal',
                        'wal_path' => $databasePath . '-wal',
                        'savepoint' => $savepoint,
                        'mode' => $mode,
                        'page_size' => $pageSize,
                        'reader_end_frame' => $readerEndFrame,
                        'reader_wal_frame_count' => $readerWal->frameCount(),
                        'reader_wal_bytes_length' => strlen($readerWalBytes),
                        'current_wal_bytes_length' => $pinned['checkpoint']['current_wal_bytes_length'],
                        'reader_wal_sha256' => hash('sha256', $readerWalBytes),
                        'current_wal_sha256' => hash('sha256', (string) $pinned['checkpoint']['current_wal_bytes']),
                        'reader_source_matches_current' => hash_equals(hash('sha256', $readerWalBytes), hash('sha256', (string) $pinned['checkpoint']['current_wal_bytes'])),
                        'hot_recovered' => $pinned['hot_recovered'],
                        'journal_action' => $pinned['journal_action'],
                        'pinned_checkpoint_busy' => $pinned['checkpoint_busy'],
                        'pinned_checkpoint_reason' => $pinned['checkpoint_reason'],
                        'pinned_wal_action' => $pinned['wal_action'],
                        'released_checkpoint_busy' => $released['checkpoint_busy'],
                        'released_checkpoint_reason' => $released['checkpoint_reason'],
                        'released_wal_action' => $released['wal_action'],
                        'retained_frame_count' => $pinned['retained_frame_count'],
                        'savepoint_discarded_frame_count' => $pinned['savepoint_discarded_frame_count'],
                        'reader_rows' => $readerRows,
                        'reader_sources' => $readerSources,
                        'reader_frame_indexes' => $readerFrames,
                        'current_sources' => $pinned['current_sources'],
                        'pinned_next_sources' => $pinned['next_sources'],
                        'released_next_sources' => $released['next_sources'],
                        'current_frame_indexes' => $pinned['current_frame_indexes'],
                        'pinned_next_frame_indexes' => $pinned['next_frame_indexes'],
                        'released_next_frame_indexes' => $released['next_frame_indexes'],
                        'reader_uses_stale_tail' => $readerImages !== $currentImages,
                        'pinned_preserves_reader_wal' => in_array('wal', $pinned['next_sources'], true),
                        'released_uses_checkpoint_database' => !in_array('wal', $released['next_sources'], true),
                        'current_to_released_images_match' => $currentImages === $releasedImages,
                        'reader_to_current_images_match' => $readerImages === $currentImages,
                        'source_transitions' => self::sourceTransitions($readerSources, $pinned['current_sources'], $pinned['next_sources'], $released['next_sources']),
                        'rows' => self::rows($pageNumbers, $readerRows, $pinned['current_reader'], $pinned['next_reader'], $released['next_reader']),
                        'pinned' => $pinned,
                        'released' => $released,
                        'dependencies' => array_values(array_unique(array_merge(
                            $pinned['dependencies'],
                            $released['dependencies'],
                            ['sqlite-wal-hot-journal-checkpoint-reader-current-source-next120']
                        ))),
                    ];
                }

                /**
                 * @param list<int> $pageNumbers
                 * @return list<array<string,mixed>>
                 */
                private static function readerRows(SQLiteWal $wal, string $databaseBytes, array $pageNumbers, int $endFrame): array
                {
                    $rows = [];
                    foreach ($pageNumbers as $pageNumber) {
                        if (!is_int($pageNumber) || $pageNumber < 1) {
                            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint reader current-source next120 page numbers must be one-based integers');
                        }
                        $rows[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $endFrame);
                    }

                    return $rows;
                }

                /**
                 * @param list<array<string,mixed>> $rows
                 * @return list<mixed>
                 */
                private static function column(array $rows, string $column): array
                {
                    return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
                }

                /**
                 * @param list<string> $reader
                 * @param list<string> $current
                 * @param list<string> $pinned
                 * @param list<string> $released
                 * @return list<string>
                 */
                private static function sourceTransitions(array $reader, array $current, array $pinned, array $released): array
                {
                    $transitions = [];
                    foreach ($reader as $index => $source) {
                        $transitions[] = $source . '>' . ($current[$index] ?? 'missing') . '>' . ($pinned[$index] ?? 'missing') . '>' . ($released[$index] ?? 'missing');
                    }

                    return $transitions;
                }

                /**
                 * @param list<int> $pageNumbers
                 * @param list<array<string,mixed>> $reader
                 * @param list<array<string,mixed>> $current
                 * @param list<array<string,mixed>> $pinned
                 * @param list<array<string,mixed>> $released
                 * @return list<array<string,mixed>>
                 */
                private static function rows(array $pageNumbers, array $reader, array $current, array $pinned, array $released): array
                {
                    $rows = [];
                    foreach ($pageNumbers as $index => $pageNumber) {
                        $readerImage = (string) ($reader[$index]['image'] ?? '');
                        $currentImage = (string) ($current[$index]['image'] ?? '');
                        $pinnedImage = (string) ($pinned[$index]['image'] ?? '');
                        $releasedImage = (string) ($released[$index]['image'] ?? '');
                        $rows[] = [
                            'page_number' => $pageNumber,
                            'reader_source' => $reader[$index]['source'] ?? null,
                            'current_source' => $current[$index]['source'] ?? null,
                            'pinned_next_source' => $pinned[$index]['source'] ?? null,
                            'released_next_source' => $released[$index]['source'] ?? null,
                            'reader_frame' => $reader[$index]['frame_index'] ?? null,
                            'current_frame' => $current[$index]['frame_index'] ?? null,
                            'pinned_next_frame' => $pinned[$index]['frame_index'] ?? null,
                            'released_next_frame' => $released[$index]['frame_index'] ?? null,
                            'reader_tail_ignored_by_current' => $readerImage !== $currentImage,
                            'pinned_preserves_current' => $pinnedImage === $currentImage,
                            'released_preserves_current' => $releasedImage === $currentImage,
                            'reader_label' => self::label($readerImage),
                            'current_label' => self::label($currentImage),
                            'pinned_next_label' => self::label($pinnedImage),
                            'released_next_label' => self::label($releasedImage),
                        ];
                    }

                    return $rows;
                }

                private static function label(string $image): string
                {
                    return rtrim(substr($image, 0, 96), ".\0");
                }
        };

        return $impl::plan($journal, $databaseBytes, $journalBytes, $savepoints, $savepoint, $wal, $walBytes, $readerWalBytes, $databasePath, $pageNumbers, $mode, $readerEndFrame, $databasePageSize, $databaseReservedLock, $requiresSuperJournal, $superJournalExists);
    }

    public static function next135Plan(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $nextWalBytes,
        array $pageNumbers,
        ?int $readerEndFrame = null,
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
                    SQLiteWal $currentWal,
                    string $currentWalBytes,
                    string $nextWalBytes,
                    array $pageNumbers,
                    ?int $readerEndFrame = null,
                    bool $reservedLock = false,
                    bool $requiresSuperJournal = false,
                    ?bool $superJournalExists = null
                ): array {
                    if ($nextWalBytes === '') {
                        throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint reader current-source next135 requires next WAL bytes');
                    }

                    $base = SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan::plan(
                        $databasePath,
                        $databaseBytes,
                        $journalBytes,
                        $currentWal,
                        $currentWalBytes,
                        $currentWalBytes,
                        $pageNumbers,
                        $readerEndFrame,
                        $reservedLock,
                        $requiresSuperJournal,
                        $superJournalExists
                    );

                    $pageSize = (int) $base['page_size'];
                    $nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
                    $hotDatabaseBytes = (string) ($base['restart_plan']['hot_journal']['database_bytes'] ?? '');
                    if ($hotDatabaseBytes === '' && isset($base['base_plan']['hot_journal']['database_bytes'])) {
                        $hotDatabaseBytes = (string) $base['base_plan']['hot_journal']['database_bytes'];
                    }
                    if ($hotDatabaseBytes === '' && (bool) $base['hot_recovered'] === false) {
                        $hotDatabaseBytes = $databaseBytes;
                    }
                    if ($hotDatabaseBytes === '') {
                        throw new \UnexpectedValueException('SQLite WAL hot-journal checkpoint reader current-source next135 requires recovered hot-journal database bytes');
                    }

                    $readerEndFrame = (int) $base['reader_end_frame'];
                    $currentRows = [];
                    $nextRows = [];
                    foreach ($pageNumbers as $pageNumber) {
                        if (!is_int($pageNumber) || $pageNumber < 1) {
                            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint reader current-source next135 pages must be one-based integers');
                        }

                        $currentRows[] = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $readerEndFrame);
                        $nextRows[] = $nextWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $nextWal->frameCount());
                    }

                    $currentSource = self::walSource($currentWal, $currentWalBytes);
                    $nextSource = self::walSource($nextWal, $nextWalBytes);
                    $nextSourceSeparated = $currentSource['sha256'] !== $nextSource['sha256']
                        && ($nextSource['checkpoint_sequence'] > $currentSource['checkpoint_sequence']
                            || $nextSource['salt_1'] !== $currentSource['salt_1']
                            || $nextSource['salt_2'] !== $currentSource['salt_2']);

                    $rows = [];
                    foreach ($pageNumbers as $index => $pageNumber) {
                        $currentImage = (string) $currentRows[$index]['image'];
                        $nextImage = (string) $nextRows[$index]['image'];
                        $rows[] = [
                            'page_number' => $pageNumber,
                            'current_source' => $currentRows[$index]['source'],
                            'next_source' => $nextRows[$index]['source'],
                            'current_frame' => $currentRows[$index]['frame_index'],
                            'next_frame' => $nextRows[$index]['frame_index'],
                            'current_label' => self::label($currentImage),
                            'next_label' => self::label($nextImage),
                            'next_generation_changed_image' => $currentImage !== $nextImage,
                            'source_transition' => $currentRows[$index]['source'] . '>checkpoint-reader>' . $nextRows[$index]['source'],
                        ];
                    }

                    $changedPages = array_values(array_map(
                        static fn (array $row): int => (int) $row['page_number'],
                        array_filter($rows, static fn (array $row): bool => (bool) $row['next_generation_changed_image'])
                    ));
                    $status = (bool) $base['checkpoint_allowed'] && $nextSourceSeparated
                        ? 'wal-hot-journal-checkpoint-reader-current-source-next135'
                        : 'wal-hot-journal-checkpoint-reader-current-source-blocked-next135';

                    return [
                        'status' => $status,
                        'reason' => $status === 'wal-hot-journal-checkpoint-reader-current-source-next135'
                            ? 'current_reader_source_survives_hot_journal_checkpoint_before_next_wal_generation'
                            : 'current_reader_source_not_ready_for_next_wal_generation',
                        'database_path' => $databasePath,
                        'journal_path' => $base['journal_path'],
                        'wal_path' => $base['wal_path'],
                        'page_size' => $pageSize,
                        'reader_end_frame' => $readerEndFrame,
                        'hot_recovered' => (bool) $base['hot_recovered'],
                        'checkpoint_allowed' => (bool) $base['checkpoint_allowed'],
                        'reader_source_matches_current' => (bool) $base['reader_source_matches_current'],
                        'next_source_separated' => $nextSourceSeparated,
                        'current_wal_source' => $currentSource,
                        'next_wal_source' => $nextSource,
                        'current_wal_sha256' => hash('sha256', $currentWalBytes),
                        'next_wal_sha256' => hash('sha256', $nextWalBytes),
                        'current_frame_count' => $currentWal->frameCount(),
                        'next_frame_count' => $nextWal->frameCount(),
                        'current_sources' => self::column($currentRows, 'source'),
                        'next_sources' => self::column($nextRows, 'source'),
                        'current_frame_indexes' => self::column($currentRows, 'frame_index'),
                        'next_frame_indexes' => self::column($nextRows, 'frame_index'),
                        'next_changed_page_numbers' => $changedPages,
                        'next_changed_page_count' => count($changedPages),
                        'rows' => $rows,
                        'source_transitions' => array_column($rows, 'source_transition'),
                        'operation_reasons' => array_merge($base['operation_reasons'], [
                            'pin_current_reader_source_through_hot_journal_checkpoint_next135',
                            'open_next_writer_on_separate_wal_generation_next135',
                        ]),
                        'base_plan' => $base,
                        'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
                        'dependencies' => array_values(array_unique(array_merge(
                            $base['dependencies'],
                            [
                                'sqlite-wal-hot-journal-checkpoint-reader-current-source-next135',
                                'sqlite-wal-checkpoint-reader-hot-journal-current-source-next132',
                                'sqlite-wal-next-generation-source-separation',
                            ]
                        ))),
                    ];
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

                private static function label(string $image): string
                {
                    return rtrim(substr($image, 0, 96), ".\0");
                }
        };

        return $impl::plan($databasePath, $databaseBytes, $journalBytes, $currentWal, $currentWalBytes, $nextWalBytes, $pageNumbers, $readerEndFrame, $reservedLock, $requiresSuperJournal, $superJournalExists);
    }
}
