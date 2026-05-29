<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan
{
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
        string $databasePath,
        array $pageNumbers,
        ?int $databasePageSize = null,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source next124 requires a savepoint name');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source next124 requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source next124 requires page numbers');
        }
        if ($journal->toBytes() !== $journalBytes) {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source next124 parsed journal does not match current bytes');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source next124 parsed WAL does not match current bytes');
        }

        $pageSize = self::pageSize($wal, $databasePageSize, $databaseBytes);
        $dirtyWal = SQLiteWal::parse($walBytes, $pageSize, true);
        $dirtyReader = self::readerRows($dirtyWal, $databaseBytes, $pageNumbers, $dirtyWal->frameCount());

        $recovery = SQLitePagerHotJournalWalRecoveryPlan::recover(
            $journal,
            $databaseBytes,
            $journalBytes,
            $walBytes,
            $databasePath,
            $pageSize,
            $databaseReservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        $hotDatabaseBytes = $recovery['payloads'][$databasePath . '#hot-journal'] ?? $databaseBytes;
        if (!is_string($hotDatabaseBytes)) {
            throw new \UnexpectedValueException('SQLite pager hot-journal WAL savepoint current-source next124 did not expose recovered database bytes');
        }

        $committedWalBytes = (string) $recovery['wal_recovery']['committed_wal_bytes'];
        $committedWal = SQLiteWal::parse($committedWalBytes, $pageSize, true);
        $hotReaderEndFrame = $committedWal->frameCount();
        $hotReader = $hotReaderEndFrame === 0
            ? self::databaseRows($hotDatabaseBytes, $pageSize, $pageNumbers)
            : self::readerRows($committedWal, $hotDatabaseBytes, $pageNumbers, $hotReaderEndFrame);

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $committedWal, $committedWalBytes);
        $currentWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $committedWal, $committedWalBytes);
        $currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
        $currentReaderEndFrame = $currentWal->frameCount();
        $currentReader = $currentReaderEndFrame === 0
            ? self::databaseRows($hotDatabaseBytes, $pageSize, $pageNumbers)
            : self::readerRows($currentWal, $hotDatabaseBytes, $pageNumbers, $currentReaderEndFrame);

        $dirtyImages = self::column($dirtyReader, 'image');
        $hotImages = self::column($hotReader, 'image');
        $currentImages = self::column($currentReader, 'image');

        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($hotDatabaseBytes),
                'reason' => 'restore_hot_journal_database_before_wal_savepoint_current_source_next124',
            ],
            [
                'op' => 'write',
                'path' => $databasePath . '-wal',
                'offset' => 0,
                'bytes' => strlen($committedWalBytes),
                'reason' => 'recover_committed_wal_prefix_before_savepoint_rollback_current_source_next124',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath . '-wal',
                'bytes' => strlen($committedWalBytes),
                'reason' => 'discard_wal_tail_before_savepoint_rollback_current_source_next124',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath . '-wal',
                'bytes' => $rollback['truncate_to_bytes'],
                'reason' => 'rollback_savepoint_to_hot_journal_recovered_wal_prefix_next124',
            ],
            [
                'op' => 'delete',
                'path' => $databasePath . '-journal',
                'durable' => false,
                'reason' => 'delete_hot_journal_after_current_source_recovery_next124',
            ],
        ];

        return [
            'status' => $recovery['hot_recovered']
                ? 'pager-hot-journal-wal-savepoint-current-source-next124'
                : 'pager-hot-journal-wal-savepoint-current-source-skipped-next124',
            'reason' => $recovery['hot_recovered']
                ? 'hot_journal_recovered_before_wal_savepoint_rollback_current_source'
                : 'hot_journal_not_recovered_before_wal_savepoint_rollback_current_source',
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'savepoint' => $savepoint,
            'page_size' => $pageSize,
            'page_numbers' => array_values($pageNumbers),
            'hot_recovered' => $recovery['hot_recovered'],
            'journal_action' => $recovery['journal_action'],
            'wal_status' => $recovery['wal_status'],
            'committed_frame_count' => $recovery['committed_frame_count'],
            'discarded_valid_tail_frame_count' => $recovery['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $recovery['discarded_corrupt_tail_frame_count'],
            'dirty_reader_end_frame' => $dirtyWal->frameCount(),
            'hot_reader_end_frame' => $hotReaderEndFrame,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'retained_frame_count' => $rollback['retained_frame_count'],
            'savepoint_discarded_frame_count' => $rollback['discarded_frame_count'],
            'wal_truncate_to_bytes' => $rollback['truncate_to_bytes'],
            'committed_wal_bytes_length' => strlen($committedWalBytes),
            'current_wal_bytes_length' => strlen($currentWalBytes),
            'dirty_reader' => $dirtyReader,
            'hot_reader' => $hotReader,
            'current_reader' => $currentReader,
            'dirty_sources' => self::column($dirtyReader, 'source'),
            'hot_sources' => self::column($hotReader, 'source'),
            'current_sources' => self::column($currentReader, 'source'),
            'dirty_frame_indexes' => self::column($dirtyReader, 'frame_index'),
            'hot_frame_indexes' => self::column($hotReader, 'frame_index'),
            'current_frame_indexes' => self::column($currentReader, 'frame_index'),
            'hot_recovery_changed_images' => $dirtyImages !== $hotImages,
            'savepoint_rollback_changed_images' => $hotImages !== $currentImages,
            'current_uses_recovered_hot_database' => $recovery['hot_recovered'],
            'current_uses_savepoint_wal_prefix' => in_array('wal', self::column($currentReader, 'source'), true),
            'source_transitions' => self::sourceTransitions(
                self::column($dirtyReader, 'source'),
                self::column($hotReader, 'source'),
                self::column($currentReader, 'source')
            ),
            'rows' => self::rows($pageNumbers, $dirtyReader, $hotReader, $currentReader),
            'hot_recovery' => $recovery,
            'rollback' => $rollback,
            'operations' => $operations,
            'operation_reasons' => self::column($operations, 'reason'),
            'current_source_verified' => $journal->toBytes() === $journalBytes && $wal->toBytes() === $walBytes,
            'current_wal_sha256' => hash('sha256', $walBytes),
            'committed_wal_sha256' => hash('sha256', $committedWalBytes),
            'retained_wal_sha256' => hash('sha256', $currentWalBytes),
            'source_digest' => hash('sha256', $databaseBytes . $journalBytes . $committedWalBytes . $savepoint),
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                $rollback['discarded_wal_frames'] === [] ? [] : ['sqlite-wal-savepoint-byte-truncation'],
                [
                    'sqlite-pager-hot-journal-wal-savepoint-current-source-next124',
                    'sqlite-hot-journal-before-wal-savepoint-current-source',
                    'sqlite-savepoint-rollback-uses-recovered-wal-prefix',
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
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source next124 page numbers must be one-based integers');
            }
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
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source next124 page numbers must be one-based integers');
            }
            $offset = ($pageNumber - 1) * $pageSize;
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

        throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source next124 requires a page size');
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
     * @param list<string> $dirty
     * @param list<string> $hot
     * @param list<string> $current
     * @return list<string>
     */
    private static function sourceTransitions(array $dirty, array $hot, array $current): array
    {
        $transitions = [];
        foreach ($dirty as $index => $source) {
            $transitions[] = $source . '>' . ($hot[$index] ?? 'missing') . '>' . ($current[$index] ?? 'missing');
        }

        return $transitions;
    }

    /**
     * @param list<int> $pageNumbers
     * @param list<array<string,mixed>> $dirty
     * @param list<array<string,mixed>> $hot
     * @param list<array<string,mixed>> $current
     * @return list<array<string,mixed>>
     */
    private static function rows(array $pageNumbers, array $dirty, array $hot, array $current): array
    {
        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $dirtyImage = (string) ($dirty[$index]['image'] ?? '');
            $hotImage = (string) ($hot[$index]['image'] ?? '');
            $currentImage = (string) ($current[$index]['image'] ?? '');
            $rows[] = [
                'page_number' => $pageNumber,
                'dirty_source' => $dirty[$index]['source'] ?? null,
                'hot_source' => $hot[$index]['source'] ?? null,
                'current_source' => $current[$index]['source'] ?? null,
                'dirty_frame' => $dirty[$index]['frame_index'] ?? null,
                'hot_frame' => $hot[$index]['frame_index'] ?? null,
                'current_frame' => $current[$index]['frame_index'] ?? null,
                'hot_recovery_changed_current' => $dirtyImage !== $hotImage,
                'savepoint_rollback_changed_current' => $hotImage !== $currentImage,
                'dirty_label' => self::label($dirtyImage),
                'hot_label' => self::label($hotImage),
                'current_label' => self::label($currentImage),
            ];
        }

        return $rows;
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
