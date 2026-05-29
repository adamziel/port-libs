<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalRecoveryCheckpointSavepointCurrentSourceNextPlan
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
        string $databasePath,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        ?int $databasePageSize = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source requires a savepoint name');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source requires WAL bytes');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source requires database bytes');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source requires page numbers');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source requires restart or truncate mode');
        }

        $recovery = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $databasePageSize);
        $validWal = $recovery['wal'];
        $committedWal = $recovery['committed_wal'];
        $committedWalBytes = $recovery['committed_wal_bytes'];
        $pageSize = $committedWal->header->pageSize !== 0 ? $committedWal->header->pageSize : $databasePageSize;
        if ($pageSize === null || $pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source requires a concrete page size');
        }

        $beforeReaderEndFrame = $readerEndFrame ?? $recovery['valid_frame_count'];
        $recoveredReaderEndFrame = min($recovery['committed_frame_count'], $beforeReaderEndFrame);
        $beforeReader = self::readerRows($validWal, $databaseBytes, $pageNumbers, $beforeReaderEndFrame);
        $recoveredReader = $recoveredReaderEndFrame === 0
            ? self::databaseRows($databaseBytes, $pageSize, $pageNumbers)
            : self::readerRows($committedWal, $databaseBytes, $pageNumbers, $recoveredReaderEndFrame);

        $checkpoint = SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
            $savepoints,
            $savepoint,
            $committedWal,
            $committedWalBytes,
            $databaseBytes,
            $mode,
            $readerEndFrame
        );
        $currentWal = SQLiteWal::parse($checkpoint['current_wal_bytes'], $pageSize, true);
        $currentReaderEndFrame = $currentWal->frameCount();
        $currentReader = $currentReaderEndFrame === 0
            ? self::databaseRows($databaseBytes, $pageSize, $pageNumbers)
            : self::readerRows($currentWal, $databaseBytes, $pageNumbers, $currentReaderEndFrame);

        $durable = $checkpoint['current_durable'];
        $nextWal = $durable['wal_bytes'] === '' ? null : SQLiteWal::parse($durable['wal_bytes'], $pageSize, true);
        $nextReaderEndFrame = $nextWal?->frameCount() ?? 0;
        $nextReader = $nextWal === null || $nextReaderEndFrame === 0
            ? self::databaseRows($durable['database_bytes'], $pageSize, $pageNumbers)
            : self::readerRows($nextWal, $durable['database_bytes'], $pageNumbers, $nextReaderEndFrame);

        $rows = [];
        foreach ($pageNumbers as $offset => $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source page numbers must be integers');
            }
            $rows[] = [
                'page_number' => $pageNumber,
                'before_source' => $beforeReader[$offset]['source'],
                'recovered_source' => $recoveredReader[$offset]['source'],
                'current_source' => $currentReader[$offset]['source'],
                'next_source' => $nextReader[$offset]['source'],
                'before_frame' => $beforeReader[$offset]['frame_index'],
                'recovered_frame' => $recoveredReader[$offset]['frame_index'],
                'current_frame' => $currentReader[$offset]['frame_index'],
                'next_frame' => $nextReader[$offset]['frame_index'],
                'tail_recovery_changed_current' => $beforeReader[$offset]['image'] !== $recoveredReader[$offset]['image'],
                'savepoint_rollback_changed_current' => $recoveredReader[$offset]['image'] !== $currentReader[$offset]['image'],
                'checkpoint_changed_next' => $currentReader[$offset]['image'] !== $nextReader[$offset]['image'],
                'before_label' => rtrim(substr($beforeReader[$offset]['image'], 0, 96), ".\0"),
                'recovered_label' => rtrim(substr($recoveredReader[$offset]['image'], 0, 96), ".\0"),
                'current_label' => rtrim(substr($currentReader[$offset]['image'], 0, 96), ".\0"),
                'next_label' => rtrim(substr($nextReader[$offset]['image'], 0, 96), ".\0"),
            ];
        }

        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath . '-wal',
                'offset' => 0,
                'bytes' => strlen($committedWalBytes),
                'reason' => 'recover_committed_wal_prefix_before_savepoint_checkpoint',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath . '-wal',
                'bytes' => strlen($committedWalBytes),
                'reason' => 'discard_valid_and_corrupt_wal_tail_before_savepoint_checkpoint',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath . '-wal',
                'bytes' => $checkpoint['truncate_to_bytes'],
                'reason' => 'rollback_savepoint_to_recovered_wal_prefix',
            ],
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($durable['database_bytes']),
                'reason' => 'checkpoint_recovered_savepoint_wal_prefix',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_database_after_recovered_savepoint_checkpoint',
            ],
        ];
        if ($durable['wal_action'] === 'restart_wal') {
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath . '-wal',
                'offset' => 0,
                'bytes' => strlen($durable['wal_bytes']),
                'reason' => 'restart_wal_after_recovered_savepoint_checkpoint',
            ];
        } elseif ($durable['wal_action'] === 'truncate_wal') {
            $operations[] = [
                'op' => 'truncate',
                'path' => $databasePath . '-wal',
                'bytes' => 0,
                'reason' => 'truncate_wal_after_recovered_savepoint_checkpoint',
            ];
        }

        $beforeImages = self::column($beforeReader, 'image');
        $recoveredImages = self::column($recoveredReader, 'image');
        $currentImages = self::column($currentReader, 'image');
        $nextImages = self::column($nextReader, 'image');

        return [
            'status' => $recovery['status'] === 'valid' ? 'ready' : 'recovered',
            'reason' => $recovery['reason'],
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'page_size' => $pageSize,
            'valid_frame_count' => $recovery['valid_frame_count'],
            'committed_frame_count' => $recovery['committed_frame_count'],
            'total_frame_slots' => $recovery['total_frame_slots'],
            'first_invalid_frame' => $recovery['first_invalid_frame'],
            'recovery_end_offset' => $recovery['recovery_end_offset'],
            'committed_end_offset' => $recovery['committed_end_offset'],
            'discarded_valid_tail_frame_count' => $recovery['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $recovery['discarded_corrupt_tail_frame_count'],
            'before_reader_end_frame' => $beforeReaderEndFrame,
            'recovered_reader_end_frame' => $recoveredReaderEndFrame,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'retained_frame_count' => $checkpoint['retained_frame_count'],
            'savepoint_discarded_frame_count' => $checkpoint['discarded_frame_count'],
            'wal_action' => $durable['wal_action'],
            'checkpoint_reason' => $durable['reason'],
            'checkpoint_busy' => $durable['busy'],
            'before_reader' => $beforeReader,
            'recovered_reader' => $recoveredReader,
            'current_reader' => $currentReader,
            'next_reader' => $nextReader,
            'before_sources' => self::column($beforeReader, 'source'),
            'recovered_sources' => self::column($recoveredReader, 'source'),
            'current_sources' => self::column($currentReader, 'source'),
            'next_sources' => self::column($nextReader, 'source'),
            'before_frame_indexes' => self::column($beforeReader, 'frame_index'),
            'recovered_frame_indexes' => self::column($recoveredReader, 'frame_index'),
            'current_frame_indexes' => self::column($currentReader, 'frame_index'),
            'next_frame_indexes' => self::column($nextReader, 'frame_index'),
            'tail_recovery_changed_images' => $beforeImages !== $recoveredImages,
            'savepoint_rollback_changed_images' => $recoveredImages !== $currentImages,
            'current_to_next_images_match' => $currentImages === $nextImages,
            'next_uses_checkpoint_database' => !in_array('wal', self::column($nextReader, 'source'), true),
            'rows' => $rows,
            'recovery' => $recovery,
            'checkpoint' => $checkpoint,
            'operations' => $operations,
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                $checkpoint['dependencies'],
                ['sqlite-wal-recovery-checkpoint-savepoint-current-source-next100']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string,mixed>>
     */
    private static function readerRows(SQLiteWal $wal, string $databaseBytes, array $pageNumbers, int $readerEndFrame): array
    {
        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source page numbers must be integers');
            }
            $rows[] = $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $readerEndFrame);
        }

        return $rows;
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string,mixed>>
     */
    private static function databaseRows(string $databaseBytes, int $pageSize, array $pageNumbers): array
    {
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source database image must be page aligned');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL recovery checkpoint savepoint current-source page numbers must be integers');
            }
            if ($pageNumber < 1 || $pageNumber > $databasePageCount) {
                throw new \OutOfBoundsException("SQLite WAL recovery checkpoint savepoint current-source page {$pageNumber} is outside the database image");
            }

            $rows[] = [
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

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function column(array $rows, string $key): array
    {
        return array_map(static fn (array $row): mixed => $row[$key], $rows);
    }
}
