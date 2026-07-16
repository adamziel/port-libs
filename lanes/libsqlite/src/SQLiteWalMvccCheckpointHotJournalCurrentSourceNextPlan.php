<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalMvccCheckpointHotJournalCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        string $walBytes,
        string $databasePath,
        array $pageNumbers,
        ?int $databasePageSize = null,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL MVCC hot-journal checkpoint requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL MVCC hot-journal checkpoint requires page numbers');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL MVCC hot-journal checkpoint pages must be one-based integers');
            }
        }
        if ($journal->toBytes() !== $journalBytes) {
            throw new \InvalidArgumentException('SQLite WAL MVCC hot-journal checkpoint parsed journal does not match current-source bytes');
        }

        $dirtyWal = SQLiteWal::parse($walBytes, $databasePageSize, true);
        $dirtyEndFrame = $dirtyWal->frameCount();
        $dirtyReader = self::readerRows($dirtyWal, $databaseBytes, $pageNumbers, $dirtyEndFrame);

        $recovery = SQLitePagerHotJournalWalRecoveryPlan::recover(
            $journal,
            $databaseBytes,
            $journalBytes,
            $walBytes,
            $databasePath,
            $databasePageSize,
            $databaseReservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        $hotDatabaseBytes = $recovery['payloads'][$databasePath . '#hot-journal'] ?? $databaseBytes;
        if (!is_string($hotDatabaseBytes)) {
            throw new \UnexpectedValueException('SQLite WAL MVCC hot-journal recovery did not expose a database image');
        }

        $committedWalBytes = (string) $recovery['wal_recovery']['committed_wal_bytes'];
        $committedWal = SQLiteWal::parse($committedWalBytes, $databasePageSize, true);
        $committedEndFrame = $committedWal->frameCount();
        $hotReader = self::readerRows($committedWal, $hotDatabaseBytes, $pageNumbers, $committedEndFrame);

        $checkpointDatabaseBytes = $recovery['payloads'][$databasePath . '#wal-checkpoint']
            ?? $recovery['wal_recovery']['checkpoint_database_bytes']
            ?? $hotDatabaseBytes;
        if (!is_string($checkpointDatabaseBytes)) {
            throw new \UnexpectedValueException('SQLite WAL MVCC checkpoint did not expose a database image');
        }

        $nextReader = self::databaseRows($checkpointDatabaseBytes, self::pageSize($committedWal, $databasePageSize, $checkpointDatabaseBytes), $pageNumbers);

        $dirtySources = self::column($dirtyReader, 'source');
        $hotSources = self::column($hotReader, 'source');
        $nextSources = self::column($nextReader, 'source');
        $dirtyImages = self::column($dirtyReader, 'image');
        $hotImages = self::column($hotReader, 'image');
        $nextImages = self::column($nextReader, 'image');

        return [
            'status' => $recovery['hot_recovered']
                ? 'wal-mvcc-hot-journal-checkpoint-current-source-next107'
                : 'wal-mvcc-hot-journal-checkpoint-skipped-current-source-next107',
            'reason' => $recovery['hot_recovered']
                ? 'hot_journal_restored_before_committed_wal_checkpoint_mvcc_boundary'
                : 'hot_journal_not_hot_committed_wal_checkpoint_mvcc_boundary',
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'page_size' => self::pageSize($committedWal, $databasePageSize, $checkpointDatabaseBytes),
            'page_numbers' => array_values($pageNumbers),
            'dirty_reader_end_frame' => $dirtyEndFrame,
            'hot_reader_end_frame' => $committedEndFrame,
            'next_reader_end_frame' => 0,
            'hot_recovered' => $recovery['hot_recovered'],
            'journal_action' => $recovery['journal_action'],
            'journal_bytes_match' => true,
            'wal_bytes_match' => $dirtyWal->toBytes() === $walBytes,
            'wal_status' => $recovery['wal_status'],
            'committed_frame_count' => $recovery['committed_frame_count'],
            'discarded_valid_tail_frame_count' => $recovery['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $recovery['discarded_corrupt_tail_frame_count'],
            'dirty_reader' => $dirtyReader,
            'hot_reader' => $hotReader,
            'next_reader' => $nextReader,
            'dirty_reader_sources' => $dirtySources,
            'hot_reader_sources' => $hotSources,
            'next_reader_sources' => $nextSources,
            'dirty_reader_frame_indexes' => self::column($dirtyReader, 'frame_index'),
            'hot_reader_frame_indexes' => self::column($hotReader, 'frame_index'),
            'next_reader_frame_indexes' => self::column($nextReader, 'frame_index'),
            'dirty_to_hot_images_match' => $dirtyImages === $hotImages,
            'hot_to_next_images_match' => $hotImages === $nextImages,
            'dirty_reader_keeps_original_wal_snapshot' => in_array('wal', $dirtySources, true),
            'hot_reader_uses_recovered_database' => in_array('database', $hotSources, true),
            'next_reader_uses_checkpoint_database' => !in_array('wal', $nextSources, true),
            'checkpoint_database_bytes' => strlen($checkpointDatabaseBytes),
            'hot_database_bytes' => strlen($hotDatabaseBytes),
            'committed_wal_bytes' => strlen($committedWalBytes),
            'operation_reasons' => self::column($recovery['operations'], 'reason'),
            'operations' => $recovery['operations'],
            'payload_keys' => array_keys($recovery['payloads']),
            'hot_recovery' => $recovery,
            'source_digest' => hash('sha256', $databaseBytes . $journalBytes . $walBytes . implode(',', $pageNumbers)),
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                [
                    'sqlite-wal-mvcc-checkpoint-hotjournal-current-source-next107',
                    'sqlite-hot-journal-before-wal-checkpoint-current-source',
                    'sqlite-mvcc-reader-boundary-current-next',
                ]
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
            $rows[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $endFrame);
        }

        return $rows;
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string,mixed>>
     */
    private static function databaseRows(string $databaseBytes, int $pageSize, array $pageNumbers): array
    {
        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $offset = ($pageNumber - 1) * $pageSize;
            if ($offset + $pageSize > strlen($databaseBytes)) {
                $rows[] = [
                    'page_number' => $pageNumber,
                    'source' => 'missing',
                    'frame_index' => null,
                    'database_offset' => $offset,
                    'image' => '',
                    'snapshot_end_frame' => 0,
                    'snapshot_commit_frame' => null,
                    'database_page_count' => intdiv(strlen($databaseBytes), $pageSize),
                ];
                continue;
            }
            $rows[] = [
                'page_number' => $pageNumber,
                'source' => 'database',
                'frame_index' => null,
                'database_offset' => $offset,
                'image' => substr($databaseBytes, $offset, $pageSize),
                'snapshot_end_frame' => 0,
                'snapshot_commit_frame' => null,
                'database_page_count' => intdiv(strlen($databaseBytes), $pageSize),
            ];
        }

        return $rows;
    }

    private static function pageSize(SQLiteWal $wal, ?int $databasePageSize, string $databaseBytes): int
    {
        if ($wal->header->pageSize >= 512) {
            return $wal->header->pageSize;
        }
        if ($databasePageSize !== null && $databasePageSize >= 512) {
            return $databasePageSize;
        }
        if ($databaseBytes !== '') {
            return SQLiteHeader::parse($databaseBytes)->pageSize;
        }

        throw new \InvalidArgumentException('SQLite WAL MVCC hot-journal checkpoint requires a page size');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function column(array $rows, string $key): array
    {
        return array_map(static fn (array $row): mixed => $row[$key] ?? null, $rows);
    }
}
