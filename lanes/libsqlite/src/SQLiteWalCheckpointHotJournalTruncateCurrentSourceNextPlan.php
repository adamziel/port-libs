<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointHotJournalTruncateCurrentSourceNextPlan
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
        SQLiteWal $wal,
        string $walBytes,
        array $pageNumbers,
        ?int $pinnedReaderEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 requires a database path');
        }
        if ($dirtyDatabaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 requires database bytes');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 requires rollback journal bytes');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 requires a savepoint name');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 requires WAL bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 requires page numbers');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 WAL bytes do not match parsed WAL');
        }

        $pageSize = $wal->header->pageSize;
        if (strlen($dirtyDatabaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 database bytes must be page-size aligned');
        }

        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 pages must be integers');
            }
        }

        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 journal page size does not match WAL page size');
        }

        $hot = $journal->hotJournalRecoveryResult(
            $dirtyDatabaseBytes,
            $journalBytes,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        $hotDatabaseBytes = (string) $hot['database_bytes'];

        if (!(bool) $hot['recovered']) {
            return [
                'status' => 'wal-checkpoint-hot-journal-truncate-current-source-blocked-next138',
                'reason' => $hot['reason'],
                'database_path' => $databasePath,
                'journal_path' => $databasePath . '-journal',
                'wal_path' => $databasePath . '-wal',
                'savepoint' => $savepoint,
                'mode' => 'truncate',
                'page_size' => $pageSize,
                'hot_recovered' => false,
                'hot_journal_reason' => $hot['hot_journal']['reason'],
                'journal_action' => $hot['journal_action'],
                'current_source_verified' => false,
                'dependencies' => [
                    'sqlite-rollback-journal-hot-recovery',
                    'sqlite-wal-checkpoint-hot-journal-truncate-current-source-next138',
                ],
            ];
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $pageSize, true);
        $pinnedReaderEndFrame ??= $retainedWal->frameCount();
        if ($pinnedReaderEndFrame < 0 || $pinnedReaderEndFrame > $retainedWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 reader frame is outside the retained WAL range');
        }

        $pinnedCheckpoint = $retainedWal->durableCheckpointResult($hotDatabaseBytes, 'truncate', $pinnedReaderEndFrame);
        $releasedCheckpoint = $retainedWal->durableCheckpointResult($hotDatabaseBytes, 'truncate');
        $pinnedWal = $pinnedCheckpoint['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse((string) $pinnedCheckpoint['wal_bytes'], $pageSize, true);

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $dirty = self::databasePage($dirtyDatabaseBytes, $pageSize, $pageNumber);
            $hotCurrent = self::databasePage($hotDatabaseBytes, $pageSize, $pageNumber);
            $current = $pinnedReaderEndFrame === 0
                ? self::databasePage($hotDatabaseBytes, $pageSize, $pageNumber)
                : $retainedWal->readerSnapshotPageImage($hotDatabaseBytes, $pageNumber, $pinnedReaderEndFrame);
            $pinnedNext = $pinnedWal === null
                ? self::databasePage((string) $pinnedCheckpoint['database_bytes'], $pageSize, $pageNumber)
                : $pinnedWal->readerSnapshotPageImage((string) $pinnedCheckpoint['database_bytes'], $pageNumber, $pinnedWal->frameCount());
            $releasedNext = self::databasePage((string) $releasedCheckpoint['database_bytes'], $pageSize, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'dirty_source' => $dirty['source'],
                'hot_current_source' => $hotCurrent['source'],
                'current_source' => $current['source'],
                'pinned_next_source' => $pinnedNext['source'],
                'released_next_source' => $releasedNext['source'],
                'current_frame' => $current['frame_index'],
                'pinned_next_frame' => $pinnedNext['frame_index'],
                'released_next_frame' => $releasedNext['frame_index'],
                'dirty_label' => self::label((string) $dirty['image']),
                'hot_current_label' => self::label((string) $hotCurrent['image']),
                'current_label' => self::label((string) $current['image']),
                'pinned_next_label' => self::label((string) $pinnedNext['image']),
                'released_next_label' => self::label((string) $releasedNext['image']),
                'hot_replaced_dirty_image' => $dirty['image'] !== $hotCurrent['image'],
                'current_uses_hot_source' => $current['source'] === 'wal' || $current['image'] === $hotCurrent['image'],
                'pinned_preserves_current' => $current['image'] === $pinnedNext['image'],
                'released_preserves_current' => $current['image'] === $releasedNext['image'],
                'source_transition' => $dirty['source'] . '>' . $hotCurrent['source'] . '>' . $current['source'] . '>' . $pinnedNext['source'] . '>' . $releasedNext['source'],
            ];
        }

        $currentSources = array_column($rows, 'current_source');
        $pinnedSources = array_column($rows, 'pinned_next_source');
        $releasedSources = array_column($rows, 'released_next_source');

        return [
            'status' => $pinnedCheckpoint['busy'] && !$releasedCheckpoint['busy'] && $releasedCheckpoint['wal_action'] === 'truncate_wal'
                ? 'wal-checkpoint-hot-journal-truncate-current-source-next138'
                : 'wal-checkpoint-hot-journal-truncate-current-source-next138-' . ($pinnedCheckpoint['busy'] ? 'busy' : 'ready'),
            'reason' => 'hot_journal_recovery_precedes_savepoint_truncated_wal_checkpoint',
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'savepoint' => $savepoint,
            'mode' => 'truncate',
            'page_size' => $pageSize,
            'reader_end_frame' => $pinnedReaderEndFrame,
            'hot_recovered' => true,
            'hot_journal_reason' => $hot['hot_journal']['reason'],
            'journal_action' => $hot['journal_action'],
            'journal_page_numbers' => array_keys($journal->pageImages()),
            'hot_database_bytes_length' => strlen($hotDatabaseBytes),
            'original_frame_count' => $rollback['original_frame_count'],
            'retained_frame_count' => $rollback['retained_frame_count'],
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'discarded_frame_indexes' => array_column($rollback['discarded_wal_frames'], 'frame_index'),
            'discarded_page_numbers' => array_values(array_unique(array_map('intval', array_column($rollback['discarded_wal_frames'], 'page_number')))),
            'truncate_to_bytes' => $rollback['truncate_to_bytes'],
            'retained_wal_bytes_length' => strlen($retainedWalBytes),
            'retained_wal_sha256' => hash('sha256', $retainedWalBytes),
            'pinned_checkpoint_busy' => $pinnedCheckpoint['busy'],
            'pinned_checkpoint_reason' => $pinnedCheckpoint['reason'],
            'pinned_wal_action' => $pinnedCheckpoint['wal_action'],
            'pinned_wal_bytes_length' => strlen((string) $pinnedCheckpoint['wal_bytes']),
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'released_database_bytes_length' => strlen((string) $releasedCheckpoint['database_bytes']),
            'current_sources' => $currentSources,
            'pinned_next_sources' => $pinnedSources,
            'released_next_sources' => $releasedSources,
            'current_source_counts' => array_count_values($currentSources),
            'pinned_next_source_counts' => array_count_values($pinnedSources),
            'released_next_source_counts' => array_count_values($releasedSources),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'hot_recovery_replaced_dirty_images' => in_array(true, array_column($rows, 'hot_replaced_dirty_image'), true),
            'current_reader_uses_hot_current_source' => !in_array(false, array_column($rows, 'current_uses_hot_source'), true),
            'pinned_checkpoint_preserved_current_images' => !in_array(false, array_column($rows, 'pinned_preserves_current'), true),
            'released_checkpoint_preserved_current_images' => !in_array(false, array_column($rows, 'released_preserves_current'), true),
            'reader_release_unblocked_truncate' => (bool) $pinnedCheckpoint['busy'] && !(bool) $releasedCheckpoint['busy'] && $releasedCheckpoint['wal_action'] === 'truncate_wal',
            'released_reader_uses_checkpoint_database' => !in_array('wal', $releasedSources, true),
            'current_source_verified' => $rollback['needs_truncate'] && $retainedWal->frameCount() === $rollback['retained_frame_count'],
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $pinnedCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                [
                    'sqlite-rollback-journal-hot-recovery',
                    'sqlite-savepoint-wal-prefix-truncation',
                    'sqlite-wal-truncate-next-open-reader',
                    'sqlite-wal-checkpoint-hot-journal-truncate-current-source-next138',
                ]
            ))),
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint hot-journal truncate current-source next138 database image must be page aligned');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL checkpoint hot-journal truncate current-source next138 page {$pageNumber} is outside the database image");
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
