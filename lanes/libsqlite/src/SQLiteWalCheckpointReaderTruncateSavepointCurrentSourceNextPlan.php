<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointReaderTruncateSavepointCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        ?int $pinnedReaderEndFrame = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader truncate savepoint current-source next128 requires a savepoint name');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader truncate savepoint current-source next128 requires WAL bytes');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader truncate savepoint current-source next128 requires database bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader truncate savepoint current-source next128 requires page numbers');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader truncate savepoint current-source next128 source bytes do not match parsed WAL');
        }

        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader truncate savepoint current-source next128 database image must be page aligned');
        }

        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint reader truncate savepoint current-source next128 pages must be integers');
            }
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $pageSize, true);
        $pinnedReaderEndFrame ??= $retainedWal->frameCount();
        if ($pinnedReaderEndFrame < 0 || $pinnedReaderEndFrame > $retainedWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader truncate savepoint current-source next128 reader frame is outside the retained WAL range');
        }

        $pinnedCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, 'truncate', $pinnedReaderEndFrame);
        $releasedCheckpoint = $retainedWal->durableCheckpointResult($databaseBytes, 'truncate', null);
        $pinnedWal = $pinnedCheckpoint['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse((string) $pinnedCheckpoint['wal_bytes'], $pageSize, true);

        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $current = $pinnedReaderEndFrame === 0
                ? self::databasePage($databaseBytes, $pageSize, $pageNumber)
                : $retainedWal->readerSnapshotPageImage($databaseBytes, $pageNumber, $pinnedReaderEndFrame);
            $pinnedNext = $pinnedWal === null
                ? self::databasePage((string) $pinnedCheckpoint['database_bytes'], $pageSize, $pageNumber)
                : $pinnedWal->readerSnapshotPageImage((string) $pinnedCheckpoint['database_bytes'], $pageNumber, $pinnedWal->frameCount());
            $releasedNext = self::databasePage((string) $releasedCheckpoint['database_bytes'], $pageSize, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'current_source' => $current['source'],
                'pinned_next_source' => $pinnedNext['source'],
                'released_next_source' => $releasedNext['source'],
                'current_frame' => $current['frame_index'],
                'pinned_next_frame' => $pinnedNext['frame_index'],
                'released_next_frame' => $releasedNext['frame_index'],
                'current_label' => self::label((string) $current['image']),
                'pinned_next_label' => self::label((string) $pinnedNext['image']),
                'released_next_label' => self::label((string) $releasedNext['image']),
                'pinned_preserves_current' => $current['image'] === $pinnedNext['image'],
                'released_preserves_current' => $current['image'] === $releasedNext['image'],
                'source_transition' => $current['source'] . '>' . $pinnedNext['source'] . '>' . $releasedNext['source'],
            ];
        }

        $currentSources = array_column($rows, 'current_source');
        $pinnedSources = array_column($rows, 'pinned_next_source');
        $releasedSources = array_column($rows, 'released_next_source');

        return [
            'status' => $pinnedCheckpoint['busy'] && !$releasedCheckpoint['busy'] && $releasedCheckpoint['wal_action'] === 'truncate_wal'
                ? 'wal-checkpoint-reader-truncate-savepoint-current-source-next128'
                : 'wal-checkpoint-reader-truncate-savepoint-current-source-next128-' . ($pinnedCheckpoint['busy'] ? 'busy' : 'ready'),
            'savepoint' => $savepoint,
            'mode' => 'truncate',
            'page_size' => $pageSize,
            'reader_end_frame' => $pinnedReaderEndFrame,
            'original_frame_count' => $rollback['original_frame_count'],
            'retained_frame_count' => $rollback['retained_frame_count'],
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'truncate_to_bytes' => $rollback['truncate_to_bytes'],
            'retained_wal_bytes_length' => strlen($retainedWalBytes),
            'retained_wal_sha256' => hash('sha256', $retainedWalBytes),
            'current_source' => self::sourceSummary($retainedWal, $retainedWalBytes),
            'discarded_wal_frames' => $rollback['discarded_wal_frames'],
            'discarded_frame_indexes' => array_column($rollback['discarded_wal_frames'], 'frame_index'),
            'discarded_page_numbers' => array_values(array_unique(array_map('intval', array_column($rollback['discarded_wal_frames'], 'page_number')))),
            'pinned_checkpoint_busy' => $pinnedCheckpoint['busy'],
            'pinned_checkpoint_reason' => $pinnedCheckpoint['reason'],
            'pinned_wal_action' => $pinnedCheckpoint['wal_action'],
            'pinned_checkpointed_frame_count' => $pinnedCheckpoint['checkpointed_frame_count'],
            'pinned_wal_bytes_length' => strlen((string) $pinnedCheckpoint['wal_bytes']),
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'released_checkpointed_frame_count' => $releasedCheckpoint['checkpointed_frame_count'],
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
            'pinned_checkpoint_preserved_images' => !in_array(false, array_column($rows, 'pinned_preserves_current'), true),
            'released_checkpoint_preserved_images' => !in_array(false, array_column($rows, 'released_preserves_current'), true),
            'reader_release_unblocked_truncate' => (bool) $pinnedCheckpoint['busy'] && !(bool) $releasedCheckpoint['busy'] && $releasedCheckpoint['wal_action'] === 'truncate_wal',
            'released_reader_uses_checkpoint_database' => !in_array('wal', $releasedSources, true),
            'current_source_verified' => $rollback['needs_truncate'] && $retainedWal->frameCount() === $rollback['retained_frame_count'],
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'dependencies' => array_values(array_unique(array_merge(
                $pinnedCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                [
                    'sqlite-wal-checkpoint-reader-truncate-savepoint-current-source-next128',
                    'sqlite-savepoint-wal-prefix-truncation',
                    'sqlite-wal-durable-checkpoint-result',
                ]
            ))),
        ];
    }

    /**
     * @return array{checkpoint_sequence:int,salt1:int,salt2:int,page_size:int,frame_count:int,wal_bytes_length:int,wal_sha256:string}
     */
    private static function sourceSummary(SQLiteWal $wal, string $walBytes): array
    {
        return [
            'checkpoint_sequence' => $wal->header->checkpointSequence,
            'salt1' => $wal->header->salt1,
            'salt2' => $wal->header->salt2,
            'page_size' => $wal->header->pageSize,
            'frame_count' => $wal->frameCount(),
            'wal_bytes_length' => strlen($walBytes),
            'wal_sha256' => hash('sha256', $walBytes),
        ];
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePage(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint reader truncate savepoint current-source next128 database image must be page aligned');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
            throw new \OutOfBoundsException("SQLite WAL checkpoint reader truncate savepoint current-source next128 page {$pageNumber} is outside the database image");
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
