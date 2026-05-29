<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        SQLiteSavepointStack $stack,
        string $savepoint,
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        SQLiteWal $wal,
        string $walBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        bool $reservedLock = false
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 requires a savepoint name');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 requires a database path');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 requires rollback journal bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 requires page numbers');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 requires restart or truncate mode');
        }

        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 requires a concrete WAL page size');
        }
        if ($databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 database bytes must be page-size aligned');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 WAL bytes do not match parsed WAL');
        }

        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 journal page size does not match WAL page size');
        }

        $rollbackPlan = $stack->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $rollbackWalBytes = $stack->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $rollbackWal = SQLiteWal::parse($rollbackWalBytes, $pageSize, true);
        $readerEndFrame ??= $rollbackWal->frameCount();
        if ($readerEndFrame < 0 || $readerEndFrame > $rollbackWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 reader frame is outside the retained WAL range');
        }

        $hot = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes, $reservedLock);
        $hotDatabaseBytes = (string) $hot['database_bytes'];
        $checkpoint = $rollbackWal->durableCheckpointResult($hotDatabaseBytes, $mode, $readerEndFrame);
        $releasedCheckpoint = $rollbackWal->durableCheckpointResult($hotDatabaseBytes, $mode);
        $checkpointWal = $checkpoint['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse((string) $checkpoint['wal_bytes'], $pageSize, true);
        $releasedWal = $releasedCheckpoint['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse((string) $releasedCheckpoint['wal_bytes'], $pageSize, true);

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 pages must be integers');
            }

            $dirty = self::databaseVisibility($databaseBytes, $pageSize, $pageNumber);
            $hotCurrent = self::databaseVisibility($hotDatabaseBytes, $pageSize, $pageNumber);
            $rolledReader = $rollbackWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $readerEndFrame);
            $pinnedNext = $checkpointWal === null
                ? self::databaseVisibility((string) $checkpoint['database_bytes'], $pageSize, $pageNumber)
                : $checkpointWal->readerSnapshotPageImage((string) $checkpoint['database_bytes'], $pageNumber, $checkpointWal->frameCount());
            $releasedNext = $releasedWal === null
                ? self::databaseVisibility((string) $releasedCheckpoint['database_bytes'], $pageSize, $pageNumber)
                : $releasedWal->readerSnapshotPageImage((string) $releasedCheckpoint['database_bytes'], $pageNumber, $releasedWal->frameCount());

            $rows[] = [
                'page_number' => $pageNumber,
                'dirty_source' => $dirty['source'],
                'hot_current_source' => $hotCurrent['source'],
                'rolled_reader_source' => $rolledReader['source'],
                'pinned_next_source' => $pinnedNext['source'],
                'released_next_source' => $releasedNext['source'],
                'rolled_reader_frame' => $rolledReader['frame_index'],
                'pinned_next_frame' => $pinnedNext['frame_index'],
                'released_next_frame' => $releasedNext['frame_index'],
                'hot_replaced_dirty_image' => $dirty['image'] !== $hotCurrent['image'],
                'savepoint_rollback_changed_reader' => $rolledReader['image'] !== $hotCurrent['image'],
                'pinned_preserves_rolled_reader_image' => $pinnedNext['image'] === $rolledReader['image'],
                'released_preserves_rolled_reader_image' => $releasedNext['image'] === $rolledReader['image'],
                'source_transition' => $dirty['source'] . '>' . $hotCurrent['source'] . '>' . $rolledReader['source'] . '>' . $pinnedNext['source'] . '>' . $releasedNext['source'],
                'dirty_label' => self::label((string) $dirty['image']),
                'hot_current_label' => self::label((string) $hotCurrent['image']),
                'rolled_reader_label' => self::label((string) $rolledReader['image']),
                'pinned_next_label' => self::label((string) $pinnedNext['image']),
                'released_next_label' => self::label((string) $releasedNext['image']),
            ];
        }

        $rolledSources = array_column($rows, 'rolled_reader_source');
        $pinnedSources = array_column($rows, 'pinned_next_source');
        $releasedSources = array_column($rows, 'released_next_source');
        $hotPages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => (bool) $row['hot_replaced_dirty_image'])
        ));

        return [
            'status' => $hot['recovered']
                ? 'wal-checkpoint-savepoint-hot-journal-current-source-next126'
                : 'wal-checkpoint-savepoint-hot-journal-blocked-next126',
            'reason' => $hot['recovered']
                ? 'hot_journal_recovery_then_savepoint_wal_prefix_checkpoint'
                : $hot['reason'],
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'original_frame_count' => $rollbackPlan['original_frame_count'],
            'retained_frame_count' => $rollbackPlan['retained_frame_count'],
            'discarded_frame_count' => $rollbackPlan['discarded_frame_count'],
            'truncate_to_bytes' => $rollbackPlan['truncate_to_bytes'],
            'original_wal_bytes_length' => $rollbackPlan['original_wal_bytes'],
            'retained_wal_bytes_length' => strlen($rollbackWalBytes),
            'hot_recovered' => (bool) $hot['recovered'],
            'hot_journal_reason' => $hot['hot_journal']['reason'],
            'journal_action' => $hot['journal_action'],
            'hot_restored_page_numbers' => $hotPages,
            'journal_page_numbers' => array_keys($journal->pageImages()),
            'checkpoint_busy' => $checkpoint['busy'],
            'checkpoint_reason' => $checkpoint['reason'],
            'checkpoint_wal_action' => $checkpoint['wal_action'],
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'checkpoint_wal_bytes_length' => strlen((string) $checkpoint['wal_bytes']),
            'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'rolled_reader_sources' => $rolledSources,
            'pinned_next_sources' => $pinnedSources,
            'released_next_sources' => $releasedSources,
            'rolled_reader_source_counts' => array_count_values($rolledSources),
            'pinned_next_source_counts' => array_count_values($pinnedSources),
            'released_next_source_counts' => array_count_values($releasedSources),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'rolled_reader_uses_hot_current_source' => !in_array(false, array_map(
                static fn (array $row): bool => $row['rolled_reader_source'] === 'wal' || !$row['savepoint_rollback_changed_reader'],
                $rows
            ), true),
            'checkpoint_preserved_rolled_reader_images' => !in_array(false, array_column($rows, 'pinned_preserves_rolled_reader_image'), true),
            'released_preserved_rolled_reader_images' => !in_array(false, array_column($rows, 'released_preserves_rolled_reader_image'), true),
            'reader_release_unblocked_checkpoint' => (bool) $checkpoint['busy'] && !(bool) $releasedCheckpoint['busy'],
            'current_source_verified' => (bool) $hot['recovered'] && $rollbackPlan['needs_truncate'],
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'discarded_wal_frames' => $rollbackPlan['discarded_wal_frames'],
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                [
                    'sqlite-wal-checkpoint-savepoint-hot-journal-current-source-next126',
                    'sqlite-rollback-journal-hot-recovery',
                    'sqlite-savepoint-wal-prefix-truncation',
                    'sqlite-wal-durable-checkpoint-result',
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
            throw new \InvalidArgumentException('SQLite WAL checkpoint savepoint hot-journal next126 database image must be page aligned');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL checkpoint savepoint hot-journal next126 page {$pageNumber} is outside the database image");
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
