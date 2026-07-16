<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalSavepointCheckpointReaderRecoveryCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        ?int $databasePageSize = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 requires a savepoint name');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 requires WAL bytes');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 requires database bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 requires page numbers');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 requires restart or truncate mode');
        }

        $recovery = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $databasePageSize);
        $validWal = $recovery['wal'];
        $committedWal = $recovery['committed_wal'];
        $pageSize = $committedWal->header->pageSize !== 0 ? $committedWal->header->pageSize : $databasePageSize;
        if ($pageSize === null || $pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 requires a concrete page size');
        }

        $originalReaderEndFrame = $readerEndFrame ?? $recovery['total_frame_slots'];
        if ($originalReaderEndFrame < 0) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 reader frame must be non-negative');
        }

        $validReaderEndFrame = min($originalReaderEndFrame, $recovery['valid_frame_count']);
        $recoveredReaderEndFrame = min($originalReaderEndFrame, $recovery['committed_frame_count']);
        $beforeReader = self::readerRows($validWal, $databaseBytes, $pageNumbers, $validReaderEndFrame, $pageSize);
        $recoveredReader = self::readerRows($committedWal, $databaseBytes, $pageNumbers, $recoveredReaderEndFrame, $pageSize);

        $checkpoint = SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
            $savepoints,
            $savepoint,
            $committedWal,
            $recovery['committed_wal_bytes'],
            $databaseBytes,
            $mode,
            $recoveredReaderEndFrame
        );
        $currentWal = SQLiteWal::parse($checkpoint['current_wal_bytes'], $pageSize, true);
        $currentReaderEndFrame = min($recoveredReaderEndFrame, $currentWal->frameCount());
        $currentReader = self::readerRows($currentWal, $databaseBytes, $pageNumbers, $currentReaderEndFrame, $pageSize);

        $pinnedCheckpoint = $currentWal->durableCheckpointResult($databaseBytes, $mode, $currentReaderEndFrame);
        $releasedCheckpoint = $currentWal->durableCheckpointResult($databaseBytes, $mode);
        $pinnedReader = self::checkpointReaderRows($pinnedCheckpoint, $databaseBytes, $pageNumbers, $pageSize);
        $releasedReader = self::checkpointReaderRows($releasedCheckpoint, $databaseBytes, $pageNumbers, $pageSize);

        $rows = [];
        foreach ($pageNumbers as $offset => $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 pages must be integers');
            }
            $rows[] = [
                'page_number' => $pageNumber,
                'before_source' => $beforeReader[$offset]['source'],
                'recovered_source' => $recoveredReader[$offset]['source'],
                'current_source' => $currentReader[$offset]['source'],
                'pinned_next_source' => $pinnedReader[$offset]['source'],
                'released_next_source' => $releasedReader[$offset]['source'],
                'before_frame' => $beforeReader[$offset]['frame_index'],
                'recovered_frame' => $recoveredReader[$offset]['frame_index'],
                'current_frame' => $currentReader[$offset]['frame_index'],
                'pinned_next_frame' => $pinnedReader[$offset]['frame_index'],
                'released_next_frame' => $releasedReader[$offset]['frame_index'],
                'tail_recovery_changed_current' => $beforeReader[$offset]['image'] !== $recoveredReader[$offset]['image'],
                'savepoint_rollback_changed_current' => $recoveredReader[$offset]['image'] !== $currentReader[$offset]['image'],
                'pinned_checkpoint_preserved_current' => $currentReader[$offset]['image'] === $pinnedReader[$offset]['image'],
                'released_checkpoint_preserved_current' => $currentReader[$offset]['image'] === $releasedReader[$offset]['image'],
                'transition' => $beforeReader[$offset]['source'] . '>' . $recoveredReader[$offset]['source'] . '>' . $currentReader[$offset]['source'] . '>' . $pinnedReader[$offset]['source'] . '>' . $releasedReader[$offset]['source'],
                'before_label' => self::label((string) $beforeReader[$offset]['image']),
                'recovered_label' => self::label((string) $recoveredReader[$offset]['image']),
                'current_label' => self::label((string) $currentReader[$offset]['image']),
                'pinned_next_label' => self::label((string) $pinnedReader[$offset]['image']),
                'released_next_label' => self::label((string) $releasedReader[$offset]['image']),
            ];
        }

        $beforeImages = self::column($beforeReader, 'image');
        $recoveredImages = self::column($recoveredReader, 'image');
        $currentImages = self::column($currentReader, 'image');
        $pinnedImages = self::column($pinnedReader, 'image');
        $releasedImages = self::column($releasedReader, 'image');

        return [
            'status' => $pinnedCheckpoint['busy'] && !$releasedCheckpoint['busy']
                ? 'reader-recovered-savepoint-checkpoint-release-unblocks-next111'
                : 'reader-recovered-savepoint-checkpoint-' . ($pinnedCheckpoint['busy'] ? 'busy' : 'ready') . '-next111',
            'reason' => $recovery['reason'],
            'mode' => $mode,
            'savepoint' => $savepoint,
            'page_size' => $pageSize,
            'original_reader_end_frame' => $originalReaderEndFrame,
            'valid_reader_end_frame' => $validReaderEndFrame,
            'recovered_reader_end_frame' => $recoveredReaderEndFrame,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'valid_frame_count' => $recovery['valid_frame_count'],
            'committed_frame_count' => $recovery['committed_frame_count'],
            'total_frame_slots' => $recovery['total_frame_slots'],
            'first_invalid_frame' => $recovery['first_invalid_frame'],
            'discarded_valid_tail_frame_count' => $recovery['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $recovery['discarded_corrupt_tail_frame_count'],
            'retained_frame_count' => $checkpoint['retained_frame_count'],
            'discarded_savepoint_frame_count' => $checkpoint['discarded_frame_count'],
            'pinned_checkpoint_busy' => $pinnedCheckpoint['busy'],
            'pinned_checkpoint_reason' => $pinnedCheckpoint['reason'],
            'pinned_wal_action' => $pinnedCheckpoint['wal_action'],
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'current_wal_bytes_length' => strlen($checkpoint['current_wal_bytes']),
            'pinned_wal_bytes_length' => strlen((string) $pinnedCheckpoint['wal_bytes']),
            'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'before_sources' => self::column($beforeReader, 'source'),
            'recovered_sources' => self::column($recoveredReader, 'source'),
            'current_sources' => self::column($currentReader, 'source'),
            'pinned_next_sources' => self::column($pinnedReader, 'source'),
            'released_next_sources' => self::column($releasedReader, 'source'),
            'before_frame_indexes' => self::column($beforeReader, 'frame_index'),
            'recovered_frame_indexes' => self::column($recoveredReader, 'frame_index'),
            'current_frame_indexes' => self::column($currentReader, 'frame_index'),
            'pinned_next_frame_indexes' => self::column($pinnedReader, 'frame_index'),
            'released_next_frame_indexes' => self::column($releasedReader, 'frame_index'),
            'tail_recovery_changed_images' => $beforeImages !== $recoveredImages,
            'savepoint_rollback_changed_images' => $recoveredImages !== $currentImages,
            'pinned_checkpoint_preserved_images' => $currentImages === $pinnedImages,
            'released_checkpoint_preserved_images' => $currentImages === $releasedImages,
            'released_reader_uses_checkpoint_database' => !in_array('wal', self::column($releasedReader, 'source'), true),
            'reader_release_unblocked_checkpoint' => (bool) $pinnedCheckpoint['busy'] && !(bool) $releasedCheckpoint['busy'],
            'rows' => $rows,
            'transitions' => array_column($rows, 'transition'),
            'reader_recovered_from_stale_end_frame' => $originalReaderEndFrame !== $recoveredReaderEndFrame,
            'recovery' => $recovery,
            'savepoint_checkpoint' => $checkpoint,
            'pinned_checkpoint' => $pinnedCheckpoint,
            'released_checkpoint' => $releasedCheckpoint,
            'operations' => [
                ['op' => 'recover-reader', 'frame' => $recoveredReaderEndFrame, 'reason' => 'clamp_reader_to_recovered_committed_wal_prefix'],
                ['op' => 'rollback-savepoint', 'frame' => $currentReaderEndFrame, 'reason' => 'apply_savepoint_rollback_to_current_wal_prefix'],
                ['op' => 'checkpoint-pinned-reader', 'action' => $pinnedCheckpoint['wal_action'], 'reason' => $pinnedCheckpoint['reason']],
                ['op' => 'checkpoint-released-reader', 'action' => $releasedCheckpoint['wal_action'], 'reason' => $releasedCheckpoint['reason']],
            ],
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                $checkpoint['dependencies'],
                $pinnedCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                ['sqlite-wal-savepoint-checkpoint-reader-recovery-current-source-next111']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string,mixed>>
     */
    private static function readerRows(SQLiteWal $wal, string $databaseBytes, array $pageNumbers, int $endFrame, int $pageSize): array
    {
        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 pages must be integers');
            }
            $rows[] = $endFrame === 0
                ? self::databaseRow($databaseBytes, $pageSize, $pageNumber)
                : $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $endFrame);
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $checkpoint
     * @param list<int> $pageNumbers
     * @return list<array<string,mixed>>
     */
    private static function checkpointReaderRows(array $checkpoint, string $databaseBytes, array $pageNumbers, int $pageSize): array
    {
        $checkpointDatabaseBytes = (string) ($checkpoint['database_bytes'] ?? $databaseBytes);
        $walBytes = (string) ($checkpoint['wal_bytes'] ?? '');
        if ($walBytes === '') {
            $rows = [];
            foreach ($pageNumbers as $pageNumber) {
                if (!is_int($pageNumber)) {
                    throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 pages must be integers');
                }
                $rows[] = self::databaseRow($checkpointDatabaseBytes, $pageSize, $pageNumber);
            }

            return $rows;
        }

        $wal = SQLiteWal::parse($walBytes, $pageSize, true);

        return self::readerRows($wal, $checkpointDatabaseBytes, $pageNumbers, $wal->frameCount(), $pageSize);
    }

    /**
     * @return array<string,mixed>
     */
    private static function databaseRow(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint reader recovery current-source next111 database image must be page aligned');
        }
        $pageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber < 1 || $pageNumber > $pageCount) {
            throw new \OutOfBoundsException("SQLite WAL savepoint checkpoint reader recovery current-source next111 page {$pageNumber} is outside the database image");
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
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
}
