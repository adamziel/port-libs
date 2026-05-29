<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $dirtyDatabaseBytes,
        string $journalBytes,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        string $nextWalBytes,
        array $pageNumbers,
        ?int $currentReaderEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 requires a database path');
        }
        if ($dirtyDatabaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 requires database bytes');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 requires rollback journal bytes');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 requires a savepoint name');
        }
        if ($currentWalBytes === '' || $nextWalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 requires current and next WAL bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 requires page numbers');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 pages must be one-based integers');
            }
        }
        if ($currentWal->toBytes() !== $currentWalBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 current WAL bytes do not match parsed WAL');
        }

        $pageSize = $currentWal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 requires a concrete WAL page size');
        }
        if (strlen($dirtyDatabaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 database bytes must be page-size aligned');
        }

        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 journal page size does not match WAL page size');
        }

        $hot = $journal->hotJournalRecoveryResult(
            $dirtyDatabaseBytes,
            $journalBytes,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        if (!(bool) $hot['recovered']) {
            return [
                'status' => 'wal-hot-journal-checkpoint-savepoint-current-source-blocked-next141',
                'reason' => $hot['reason'],
                'database_path' => $databasePath,
                'journal_path' => $databasePath . '-journal',
                'wal_path' => $databasePath . '-wal',
                'savepoint' => $savepoint,
                'page_size' => $pageSize,
                'hot_recovered' => false,
                'journal_action' => $hot['journal_action'],
                'checkpoint_allowed' => false,
                'current_source_verified' => false,
                'dependencies' => [
                    'sqlite-rollback-journal-hot-recovery',
                    'sqlite-wal-hot-journal-checkpoint-savepoint-current-source-next141',
                ],
            ];
        }

        $hotDatabaseBytes = (string) $hot['database_bytes'];
        $transaction = SQLiteWal::transactionRecoveryBoundary($currentWalBytes, $hotDatabaseBytes, $pageSize);
        $committedWal = $transaction['committed_wal'];
        $committedWalBytes = (string) $transaction['committed_wal_bytes'];
        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $committedWal, $committedWalBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $committedWal, $committedWalBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $pageSize, true);
        $nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

        $currentReaderEndFrame ??= $retainedWal->frameCount();
        if ($currentReaderEndFrame < 0 || $currentReaderEndFrame > $retainedWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint savepoint current-source next141 current reader frame is outside the retained WAL range');
        }

        $checkpoint = $retainedWal->durableCheckpointResult($hotDatabaseBytes, 'restart', $currentReaderEndFrame);
        $checkpointDatabaseBytes = (string) $checkpoint['database_bytes'];
        $checkpointWalBytes = (string) $checkpoint['wal_bytes'];
        $checkpointWal = $checkpointWalBytes === '' ? null : SQLiteWal::parse($checkpointWalBytes, $pageSize, true);
        $checkpointReaderEndFrame = $checkpointWal?->frameCount() ?? 0;

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $dirty = self::databasePage($dirtyDatabaseBytes, $pageSize, $pageNumber);
            $hotRow = self::databasePage($hotDatabaseBytes, $pageSize, $pageNumber);
            $current = $currentReaderEndFrame === 0
                ? self::databasePage($hotDatabaseBytes, $pageSize, $pageNumber)
                : $retainedWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $currentReaderEndFrame);
            $checkpointRow = $checkpointWal === null
                ? self::databasePage($checkpointDatabaseBytes, $pageSize, $pageNumber)
                : $checkpointWal->readerSnapshotPageImage($checkpointDatabaseBytes, $pageNumber, $checkpointReaderEndFrame);
            $next = $nextWal->readerSnapshotPageImage($checkpointDatabaseBytes, $pageNumber, $nextWal->frameCount());

            $rows[] = [
                'page_number' => $pageNumber,
                'dirty_source' => $dirty['source'],
                'hot_source' => $hotRow['source'],
                'current_source' => $current['source'],
                'checkpoint_source' => $checkpointRow['source'],
                'next_source' => $next['source'],
                'current_frame' => $current['frame_index'],
                'checkpoint_frame' => $checkpointRow['frame_index'],
                'next_frame' => $next['frame_index'],
                'dirty_label' => self::label((string) $dirty['image']),
                'hot_label' => self::label((string) $hotRow['image']),
                'current_label' => self::label((string) $current['image']),
                'checkpoint_label' => self::label((string) $checkpointRow['image']),
                'next_label' => self::label((string) $next['image']),
                'hot_recovery_changed_dirty' => $dirty['image'] !== $hotRow['image'],
                'checkpoint_preserves_current' => $current['image'] === $checkpointRow['image'],
                'next_generation_changed_current' => $current['image'] !== $next['image'],
                'source_transition' => $dirty['source'] . '>' . $hotRow['source'] . '>' . $current['source'] . '>' . $checkpointRow['source'] . '>' . $next['source'],
            ];
        }

        $currentSources = array_column($rows, 'current_source');
        $checkpointSources = array_column($rows, 'checkpoint_source');
        $nextSources = array_column($rows, 'next_source');
        $sourceSeparated = (
            $retainedWal->header->checkpointSequence !== $nextWal->header->checkpointSequence
            || $retainedWal->header->salt1 !== $nextWal->header->salt1
            || $retainedWal->header->salt2 !== $nextWal->header->salt2
        ) && hash('sha256', $retainedWalBytes) !== hash('sha256', $nextWalBytes);

        return [
            'status' => (bool) $checkpoint['busy'] && $sourceSeparated
                ? 'wal-hot-journal-checkpoint-savepoint-current-source-next141'
                : 'wal-hot-journal-checkpoint-savepoint-current-source-blocked-next141',
            'reason' => (bool) $checkpoint['busy']
                ? 'current_reader_keeps_savepoint_wal_source_after_hot_journal_checkpoint'
                : 'current_reader_not_pinned_after_savepoint_checkpoint',
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'savepoint' => $savepoint,
            'page_size' => $pageSize,
            'page_numbers' => array_values($pageNumbers),
            'hot_recovered' => true,
            'journal_action' => $hot['journal_action'],
            'transaction_reason' => $transaction['reason'],
            'original_frame_count' => $transaction['valid_frame_count'],
            'committed_frame_count' => $transaction['committed_frame_count'],
            'retained_frame_count' => $rollback['retained_frame_count'],
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'discarded_frame_indexes' => array_column($rollback['discarded_wal_frames'], 'frame_index'),
            'discarded_page_numbers' => array_values(array_unique(array_map('intval', array_column($rollback['discarded_wal_frames'], 'page_number')))),
            'current_reader_end_frame' => $currentReaderEndFrame,
            'checkpoint_reader_end_frame' => $checkpointReaderEndFrame,
            'next_reader_end_frame' => $nextWal->frameCount(),
            'checkpoint_allowed' => true,
            'checkpoint_busy' => $checkpoint['busy'],
            'checkpoint_reason' => $checkpoint['reason'],
            'checkpoint_wal_action' => $checkpoint['wal_action'],
            'checkpoint_wal_bytes_length' => strlen($checkpointWalBytes),
            'checkpoint_database_bytes_length' => strlen($checkpointDatabaseBytes),
            'current_wal_source' => [
                'checkpoint_sequence' => $retainedWal->header->checkpointSequence,
                'salt_1' => $retainedWal->header->salt1,
                'salt_2' => $retainedWal->header->salt2,
                'sha256' => hash('sha256', $retainedWalBytes),
            ],
            'next_wal_source' => [
                'checkpoint_sequence' => $nextWal->header->checkpointSequence,
                'salt_1' => $nextWal->header->salt1,
                'salt_2' => $nextWal->header->salt2,
                'sha256' => hash('sha256', $nextWalBytes),
            ],
            'next_source_separated' => $sourceSeparated,
            'current_sources' => $currentSources,
            'checkpoint_sources' => $checkpointSources,
            'next_sources' => $nextSources,
            'current_frame_indexes' => array_column($rows, 'current_frame'),
            'checkpoint_frame_indexes' => array_column($rows, 'checkpoint_frame'),
            'next_frame_indexes' => array_column($rows, 'next_frame'),
            'current_source_counts' => array_count_values($currentSources),
            'checkpoint_source_counts' => array_count_values($checkpointSources),
            'next_source_counts' => array_count_values($nextSources),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'hot_recovery_replaced_dirty_images' => in_array(true, array_column($rows, 'hot_recovery_changed_dirty'), true),
            'checkpoint_preserved_current_images' => !in_array(false, array_column($rows, 'checkpoint_preserves_current'), true),
            'next_changed_page_numbers' => array_values(array_map(
                static fn (array $row): int => (int) $row['page_number'],
                array_filter($rows, static fn (array $row): bool => (bool) $row['next_generation_changed_current'])
            )),
            'current_source_verified' => $rollback['needs_truncate'] && in_array('wal', $currentSources, true),
            'source_digest' => hash('sha256', $databasePath . $journalBytes . $retainedWalBytes . $nextWalBytes . implode('|', array_column($rows, 'source_transition'))),
            'operation_reasons' => [
                'recover_hot_journal_before_savepoint_checkpoint_next141',
                'rollback_savepoint_to_current_wal_source_next141',
                'preserve_current_reader_during_restart_checkpoint_next141',
                'open_next_writer_on_separate_wal_source_next141',
            ],
            'dependencies' => array_values(array_unique(array_merge(
                $transaction['dependencies'],
                $checkpoint['dependencies'],
                [
                    'sqlite-rollback-journal-hot-recovery',
                    'sqlite-savepoint-wal-prefix-truncation',
                    'sqlite-wal-hot-journal-checkpoint-savepoint-current-source-next141',
                    'sqlite-wal-next-generation-source-separation',
                ]
            ))),
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL hot-journal checkpoint savepoint current-source next141 page {$pageNumber} is outside the database image");
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
