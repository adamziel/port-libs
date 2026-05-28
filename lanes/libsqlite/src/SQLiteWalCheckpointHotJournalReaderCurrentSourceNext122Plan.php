<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointHotJournalReaderCurrentSourceNext122Plan
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
}
