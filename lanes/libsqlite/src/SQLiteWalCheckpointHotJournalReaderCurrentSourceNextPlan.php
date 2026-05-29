<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan
{
    public static function next122Plan(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        SQLiteWal $wal,
        string $walBytes,
        array $pageNumbers,
        string $mode = 'restart',
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
                    SQLiteWal $wal,
                    string $walBytes,
                    array $pageNumbers,
                    string $mode = 'restart',
                    ?int $readerEndFrame = null,
                    bool $reservedLock = false,
                    bool $requiresSuperJournal = false,
                    ?bool $superJournalExists = null
                ): array {
                    if ($databasePath === '') {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next122 requires a database path');
                    }
                    if ($journalBytes === '') {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next122 requires rollback journal bytes');
                    }
                    if ($pageNumbers === []) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next122 requires page numbers');
                    }

                    $mode = strtolower(trim($mode));
                    if (!in_array($mode, ['restart', 'truncate'], true)) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next122 requires restart or truncate mode');
                    }

                    $pageSize = $wal->header->pageSize;
                    if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next122 database bytes must be page-size aligned');
                    }
                    if ($wal->toBytes() !== $walBytes) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next122 WAL bytes do not match parsed WAL');
                    }

                    $journal = SQLiteRollbackJournal::parse($journalBytes, true);
                    if ($journal->header->pageSize !== $pageSize) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next122 journal page size does not match WAL page size');
                    }

                    $readerEndFrame ??= $wal->frameCount();
                    if ($readerEndFrame < 0 || $readerEndFrame > $wal->frameCount()) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next122 reader frame is outside the WAL frame range');
                    }

                    $hot = $journal->hotJournalRecoveryResult(
                        $databaseBytes,
                        $journalBytes,
                        $reservedLock,
                        $requiresSuperJournal,
                        $superJournalExists
                    );
                    $recoveredDatabaseBytes = (string) $hot['database_bytes'];

                    $pinnedCheckpoint = $wal->durableCheckpointResult($recoveredDatabaseBytes, $mode, $readerEndFrame);
                    $releasedCheckpoint = $wal->durableCheckpointResult($recoveredDatabaseBytes, $mode);
                    $pinnedWal = $pinnedCheckpoint['wal_bytes'] === ''
                        ? null
                        : SQLiteWal::parse((string) $pinnedCheckpoint['wal_bytes'], $pageSize, true);
                    $releasedWal = $releasedCheckpoint['wal_bytes'] === ''
                        ? null
                        : SQLiteWal::parse((string) $releasedCheckpoint['wal_bytes'], $pageSize, true);

                    $rows = [];
                    foreach ($pageNumbers as $pageNumber) {
                        if (!is_int($pageNumber)) {
                            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next122 pages must be integers');
                        }

                        $dirty = self::databaseVisibility($databaseBytes, $pageSize, $pageNumber);
                        $recovered = self::databaseVisibility($recoveredDatabaseBytes, $pageSize, $pageNumber);
                        $reader = $wal->readerSnapshotPageImage($recoveredDatabaseBytes, $pageNumber, $readerEndFrame);
                        $pinned = $pinnedWal === null
                            ? self::databaseVisibility((string) $pinnedCheckpoint['database_bytes'], $pageSize, $pageNumber)
                            : $pinnedWal->readerSnapshotPageImage((string) $pinnedCheckpoint['database_bytes'], $pageNumber, $pinnedWal->frameCount());
                        $released = $releasedWal === null
                            ? self::databaseVisibility((string) $releasedCheckpoint['database_bytes'], $pageSize, $pageNumber)
                            : $releasedWal->readerSnapshotPageImage((string) $releasedCheckpoint['database_bytes'], $pageNumber, $releasedWal->frameCount());

                        $rows[] = [
                            'page_number' => $pageNumber,
                            'dirty_source' => $dirty['source'],
                            'hot_current_source' => $recovered['source'],
                            'reader_source' => $reader['source'],
                            'pinned_next_source' => $pinned['source'],
                            'released_next_source' => $released['source'],
                            'reader_frame' => $reader['frame_index'],
                            'pinned_next_frame' => $pinned['frame_index'],
                            'released_next_frame' => $released['frame_index'],
                            'hot_replaced_dirty_image' => $dirty['image'] !== $recovered['image'],
                            'reader_uses_hot_current_source' => $reader['source'] === 'wal' || $reader['image'] === $recovered['image'],
                            'pinned_preserves_reader_image' => $reader['image'] === $pinned['image'],
                            'released_preserves_reader_image' => $reader['image'] === $released['image'],
                            'source_transition' => $dirty['source'] . '>' . $recovered['source'] . '>' . $reader['source'] . '>' . $pinned['source'] . '>' . $released['source'],
                            'dirty_label' => self::label((string) $dirty['image']),
                            'hot_current_label' => self::label((string) $recovered['image']),
                            'reader_label' => self::label((string) $reader['image']),
                            'pinned_next_label' => self::label((string) $pinned['image']),
                            'released_next_label' => self::label((string) $released['image']),
                        ];
                    }

                    $hotPages = array_values(array_map(
                        static fn (array $row): int => (int) $row['page_number'],
                        array_filter($rows, static fn (array $row): bool => (bool) $row['hot_replaced_dirty_image'])
                    ));
                    $readerSources = array_column($rows, 'reader_source');
                    $pinnedSources = array_column($rows, 'pinned_next_source');
                    $releasedSources = array_column($rows, 'released_next_source');

                    return [
                        'status' => $hot['recovered']
                            ? 'wal-checkpoint-hot-journal-reader-current-source-next122'
                            : 'wal-checkpoint-hot-journal-reader-current-source-blocked-next122',
                        'reason' => $hot['recovered']
                            ? 'hot_journal_recovery_precedes_wal_reader_checkpoint_current_source'
                            : $hot['reason'],
                        'database_path' => $databasePath,
                        'journal_path' => $databasePath . '-journal',
                        'wal_path' => $databasePath . '-wal',
                        'mode' => $mode,
                        'page_size' => $pageSize,
                        'reader_end_frame' => $readerEndFrame,
                        'hot_recovered' => (bool) $hot['recovered'],
                        'hot_journal_reason' => $hot['hot_journal']['reason'],
                        'journal_action' => $hot['journal_action'],
                        'recovered_database_bytes_length' => strlen($recoveredDatabaseBytes),
                        'hot_restored_page_numbers' => $hotPages,
                        'journal_page_numbers' => array_keys($journal->pageImages()),
                        'pinned_checkpoint_busy' => $pinnedCheckpoint['busy'],
                        'pinned_checkpoint_reason' => $pinnedCheckpoint['reason'],
                        'pinned_wal_action' => $pinnedCheckpoint['wal_action'],
                        'released_checkpoint_busy' => $releasedCheckpoint['busy'],
                        'released_checkpoint_reason' => $releasedCheckpoint['reason'],
                        'released_wal_action' => $releasedCheckpoint['wal_action'],
                        'pinned_wal_bytes_length' => strlen((string) $pinnedCheckpoint['wal_bytes']),
                        'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
                        'reader_sources' => $readerSources,
                        'pinned_next_sources' => $pinnedSources,
                        'released_next_sources' => $releasedSources,
                        'reader_source_counts' => array_count_values($readerSources),
                        'pinned_next_source_counts' => array_count_values($pinnedSources),
                        'released_next_source_counts' => array_count_values($releasedSources),
                        'rows' => $rows,
                        'source_transitions' => array_column($rows, 'source_transition'),
                        'reader_uses_hot_current_source' => !in_array(false, array_column($rows, 'reader_uses_hot_current_source'), true),
                        'pinned_checkpoint_preserved_reader_images' => !in_array(false, array_column($rows, 'pinned_preserves_reader_image'), true),
                        'released_checkpoint_preserved_reader_images' => !in_array(false, array_column($rows, 'released_preserves_reader_image'), true),
                        'reader_release_unblocked_checkpoint' => (bool) $pinnedCheckpoint['busy'] && !(bool) $releasedCheckpoint['busy'],
                        'current_source_verified' => (bool) $hot['recovered'],
                        'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
                        'dependencies' => array_values(array_unique(array_merge(
                            $pinnedCheckpoint['dependencies'],
                            $releasedCheckpoint['dependencies'],
                            [
                                'sqlite-wal-checkpoint-hot-journal-reader-current-source-next122',
                                'sqlite-rollback-journal-hot-recovery',
                                'sqlite-wal-reader-checkpoint-current-source',
                            ]
                        ))),
                    ];
                }

                /**
                 * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
                 */
                private static function databaseVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
                {
                    if (strlen($databaseBytes) % $pageSize !== 0) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next122 database image must be page aligned');
                    }

                    $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
                    if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
                        throw new \OutOfBoundsException("SQLite WAL checkpoint hot-journal reader current-source next122 page {$pageNumber} is outside the database image");
                    }

                    return [
                        'page_number' => $pageNumber,
                        'source' => 'database',
                        'frame_index' => null,
                        'database_offset' => ($pageNumber - 1) * $pageSize,
                        'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
                        'snapshot_end_frame' => 0,
                        'snapshot_commit_frame' => null,
                        'database_page_count' => $databasePageCount,
                    ];
                }

                private static function label(string $image): string
                {
                    return rtrim(substr($image, 0, 96), ".\0");
                }
        };

        return $impl::plan($databasePath, $databaseBytes, $journalBytes, $wal, $walBytes, $pageNumbers, $mode, $readerEndFrame, $reservedLock, $requiresSuperJournal, $superJournalExists);
    }

    public static function next144Plan(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $readerDatabaseBytes,
        string $readerWalBytes,
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
                    string $readerDatabaseBytes,
                    string $readerWalBytes,
                    array $pageNumbers,
                    ?int $readerEndFrame = null,
                    bool $reservedLock = false,
                    bool $requiresSuperJournal = false,
                    ?bool $superJournalExists = null
                ): array {
                    if ($databasePath === '') {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires a database path');
                    }
                    if ($databaseBytes === '') {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires database bytes');
                    }
                    if ($journalBytes === '') {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires rollback journal bytes');
                    }
                    if ($currentWalBytes === '' || $readerWalBytes === '') {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires WAL bytes');
                    }
                    if ($readerDatabaseBytes === '') {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires reader database bytes');
                    }
                    if ($pageNumbers === []) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 requires page numbers');
                    }
                    if ($currentWal->toBytes() !== $currentWalBytes) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 parsed WAL does not match current bytes');
                    }

                    $pageSize = $currentWal->header->pageSize;
                    self::assertPageAligned($databaseBytes, $pageSize, 'database');
                    self::assertPageAligned($readerDatabaseBytes, $pageSize, 'reader database');
                    foreach ($pageNumbers as $pageNumber) {
                        if (!is_int($pageNumber) || $pageNumber < 1) {
                            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 pages must be one-based integers');
                        }
                    }

                    $journal = SQLiteRollbackJournal::parse($journalBytes, true);
                    if ($journal->header->pageSize !== $pageSize) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 journal page size does not match WAL page size');
                    }

                    $readerWal = SQLiteWal::parse($readerWalBytes, $pageSize, true);
                    $readerEndFrame ??= $readerWal->frameCount();
                    if ($readerEndFrame < 0 || $readerEndFrame > $readerWal->frameCount()) {
                        throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal reader current-source next144 reader frame is outside the reader WAL frame range');
                    }

                    $hot = $journal->hotJournalRecoveryResult(
                        $databaseBytes,
                        $journalBytes,
                        $reservedLock,
                        $requiresSuperJournal,
                        $superJournalExists
                    );
                    $hotDatabaseBytes = (string) $hot['database_bytes'];
                    $currentDatabaseSource = self::databaseSource($hotDatabaseBytes, $pageSize);
                    $readerDatabaseSource = self::databaseSource($readerDatabaseBytes, $pageSize);
                    $currentWalSource = self::walSource($currentWal, $currentWalBytes);
                    $readerWalSource = self::walSource($readerWal, $readerWalBytes);
                    $walSourceMatches = $currentWalSource === $readerWalSource;
                    $databaseSourceMatches = $currentDatabaseSource === $readerDatabaseSource;

                    $readerRows = [];
                    $currentRows = [];
                    foreach ($pageNumbers as $pageNumber) {
                        $readerRows[] = $readerWal->readerSnapshotPageImage($readerDatabaseBytes, $pageNumber, $readerEndFrame);
                        $currentRows[] = $currentWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, min($readerEndFrame, $currentWal->frameCount()));
                    }

                    $rows = [];
                    foreach ($pageNumbers as $index => $pageNumber) {
                        $readerImage = (string) $readerRows[$index]['image'];
                        $currentImage = (string) $currentRows[$index]['image'];
                        $rows[] = [
                            'page_number' => $pageNumber,
                            'reader_source' => $readerRows[$index]['source'],
                            'current_source' => $currentRows[$index]['source'],
                            'reader_frame' => $readerRows[$index]['frame_index'],
                            'current_frame' => $currentRows[$index]['frame_index'],
                            'reader_label' => self::label($readerImage),
                            'current_label' => self::label($currentImage),
                            'reader_image_matches_current' => $readerImage === $currentImage,
                            'database_source_transition' => $readerDatabaseSource['sha256'] . '>' . $currentDatabaseSource['sha256'],
                            'source_transition' => $readerRows[$index]['source'] . '>' . $currentRows[$index]['source'] . '>' . ($databaseSourceMatches ? 'same-db-source' : 'reopen-db-source'),
                        ];
                    }

                    $checkpointAllowed = (bool) $hot['recovered'] && $walSourceMatches && $databaseSourceMatches;
                    $status = !$hot['recovered']
                        ? 'wal-checkpoint-hot-journal-reader-current-source-blocked-next144'
                        : ($checkpointAllowed
                            ? 'wal-checkpoint-hot-journal-reader-current-source-next144'
                            : 'wal-checkpoint-hot-journal-reader-current-source-reopen-next144');

                    return [
                        'status' => $status,
                        'reason' => !$hot['recovered']
                            ? $hot['reason']
                            : ($checkpointAllowed
                                ? 'reader_wal_and_database_source_match_hot_journal_checkpoint_current_source'
                                : 'reader_database_source_mismatch_requires_reopen_before_checkpoint_reset'),
                        'database_path' => $databasePath,
                        'journal_path' => $databasePath . '-journal',
                        'wal_path' => $databasePath . '-wal',
                        'page_size' => $pageSize,
                        'reader_end_frame' => $readerEndFrame,
                        'hot_recovered' => (bool) $hot['recovered'],
                        'journal_action' => $hot['journal_action'],
                        'wal_source_matches_current' => $walSourceMatches,
                        'database_source_matches_current' => $databaseSourceMatches,
                        'checkpoint_allowed' => $checkpointAllowed,
                        'reader_reopen_required' => (bool) $hot['recovered'] && (!$walSourceMatches || !$databaseSourceMatches),
                        'current_database_source' => $currentDatabaseSource,
                        'reader_database_source' => $readerDatabaseSource,
                        'current_wal_source' => $currentWalSource,
                        'reader_wal_source' => $readerWalSource,
                        'current_frame_count' => $currentWal->frameCount(),
                        'reader_frame_count' => $readerWal->frameCount(),
                        'reader_sources' => self::column($readerRows, 'source'),
                        'current_sources' => self::column($currentRows, 'source'),
                        'reader_frame_indexes' => self::column($readerRows, 'frame_index'),
                        'current_frame_indexes' => self::column($currentRows, 'frame_index'),
                        'reader_images_match_current' => !in_array(false, array_column($rows, 'reader_image_matches_current'), true),
                        'mismatched_page_numbers' => array_values(array_map(
                            static fn (array $row): int => (int) $row['page_number'],
                            array_filter($rows, static fn (array $row): bool => $row['reader_image_matches_current'] === false)
                        )),
                        'rows' => $rows,
                        'source_transitions' => array_column($rows, 'source_transition'),
                        'operation_reasons' => $checkpointAllowed
                            ? [
                                'pin_reader_database_source_after_hot_journal_recovery_next144',
                                'allow_checkpoint_reset_for_matching_reader_source_next144',
                            ]
                            : [
                                'restore_hot_journal_database_before_reader_source_recheck_next144',
                                'preserve_current_wal_until_database_source_reader_reopens_next144',
                                'defer_checkpoint_reset_for_stale_database_source_next144',
                            ],
                        'hot_journal' => $hot,
                        'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
                        'dependencies' => [
                            'sqlite-wal-checkpoint-hot-journal-reader-current-source-next144',
                            'sqlite-hot-journal-recovery',
                            'sqlite-wal-reader-current-source-validation',
                            'sqlite-reader-database-source-token',
                        ],
                    ];
                }

                private static function assertPageAligned(string $bytes, int $pageSize, string $label): void
                {
                    if (strlen($bytes) % $pageSize !== 0) {
                        throw new \InvalidArgumentException("SQLite WAL checkpoint hot-journal reader current-source next144 {$label} bytes must be page-size aligned");
                    }
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

        return $impl::plan($databasePath, $databaseBytes, $journalBytes, $currentWal, $currentWalBytes, $readerDatabaseBytes, $readerWalBytes, $pageNumbers, $readerEndFrame, $reservedLock, $requiresSuperJournal, $superJournalExists);
    }
}
