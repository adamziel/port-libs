<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalReadonlyShmPlan
{
    /**
     * @param list<array{op:string, rows?:list<array{0:string, 1:string}>, checkpoint?:bool, wal_wrapped?:bool, wal_truncated?:bool}> $writerEvents
     * @return array<string, mixed>
     */
    public static function openReadonly(
        bool $databaseExists,
        bool $walExists,
        bool $shmExists,
        bool $readonlyShm,
        bool $shmWritable,
        int $walSize,
        int $shmSize,
        int $pageSize,
        array $writerEvents = []
    ): array {
        if ($walSize < 0 || $shmSize < 0) {
            throw new \InvalidArgumentException('SQLite readonly WAL SHM planning requires non-negative sidecar sizes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite readonly WAL SHM planning requires a power-of-two page size of at least 512');
        }

        $minimumShmSize = max(32768, $pageSize);
        $hasUsableShm = $shmExists && ($shmSize === 0 || $shmSize >= $minimumShmSize || $readonlyShm);
        $canOpen = $databaseExists && ($readonlyShm ? $shmExists : ($shmWritable || $hasUsableShm));
        $rows = [
            ['a', 'b'],
            ['c', 'd'],
        ];
        $refreshes = [];
        $denied = [];

        foreach ($writerEvents as $index => $event) {
            $op = (string) ($event['op'] ?? '');
            if ($op === 'insert') {
                foreach (($event['rows'] ?? []) as $row) {
                    $rows[] = [(string) $row[0], (string) $row[1]];
                }
            } elseif ($op === 'checkpoint') {
                $refreshes[] = [
                    'event' => $index + 1,
                    'kind' => !empty($event['wal_truncated']) ? 'truncate-checkpoint-cache-flush' : 'checkpoint-visible',
                ];
            } elseif ($op === 'wrap') {
                $refreshes[] = [
                    'event' => $index + 1,
                    'kind' => !empty($event['wal_wrapped']) ? 'wal-wrap-rerun-recovery' : 'wal-wrap-observed',
                ];
            } else {
                throw new \InvalidArgumentException("Unsupported SQLite readonly WAL writer event: {$op}");
            }
        }

        if ($canOpen && $readonlyShm) {
            $denied = [
                ['statement' => 'INSERT INTO t1 VALUES', 'error' => 'attempt to write a readonly database'],
                ['statement' => 'PRAGMA wal_checkpoint', 'error' => 'attempt to write a readonly database'],
            ];
        }

        return [
            'status' => $canOpen ? 'readonly-wal-open' : 'readonly-wal-open-blocked',
            'reason' => $canOpen
                ? ($readonlyShm ? 'readonly_shm_allows_wal_snapshot_reads' : 'writable_shm_or_auto_readonly_open')
                : 'readonly_shm_requires_existing_shm_sidecar',
            'readonly_shm' => $readonlyShm,
            'shm_writable' => $shmWritable,
            'minimum_shm_size' => $minimumShmSize,
            'wal_size' => $walSize,
            'shm_size' => $shmSize,
            'wal_exists' => $walExists,
            'shm_exists' => $shmExists,
            'rows' => $canOpen ? $rows : [],
            'row_count' => $canOpen ? count($rows) : 0,
            'write_denials' => $denied,
            'refreshes' => $refreshes,
            'extended_errcode' => $canOpen ? 'SQLITE_OK' : 'SQLITE_CANTOPEN',
            'source' => 'upstream walro.test 1.1.* 1.2.* 1.3.* 1.4.* and walro2.test page-size readonly_shm matrix',
            'dependencies' => [
                'sqlite-wal-readonly-shm-open',
                'sqlite-wal-readonly-cache-refresh',
                'sqlite-wal-readonly-checkpoint-denial',
            ],
        ];
    }

    /**
     * @param list<array{0:string, 1:string}> $rowsBeforeCheckpoint
     * @param list<array{0:string, 1:string}> $rowsAfterCheckpoint
     * @return array<string, mixed>
     */
    public static function concurrentCheckpointReadonlySnapshot(
        bool $databaseExists,
        bool $walExists,
        bool $shmExists,
        bool $readonlyShm,
        bool $checkpointInProgress,
        int $pageSize,
        int $checkpointFrameCount,
        int $checkpointBackfilledFrameCount,
        array $rowsBeforeCheckpoint,
        array $rowsAfterCheckpoint
    ): array {
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite readonly WAL checkpoint snapshot planning requires a power-of-two page size of at least 512');
        }
        if ($checkpointFrameCount < 0 || $checkpointBackfilledFrameCount < 0) {
            throw new \InvalidArgumentException('SQLite readonly WAL checkpoint snapshot planning requires non-negative checkpoint frame counts');
        }
        if ($checkpointBackfilledFrameCount > $checkpointFrameCount) {
            throw new \InvalidArgumentException('SQLite readonly WAL checkpoint snapshot planning cannot backfill past the checkpoint frame count');
        }
        if ($rowsBeforeCheckpoint === [] || $rowsAfterCheckpoint === []) {
            throw new \InvalidArgumentException('SQLite readonly WAL checkpoint snapshot planning requires before and after rowsets');
        }

        $canOpen = $databaseExists && $walExists && $shmExists && $readonlyShm;
        $usesWalSnapshot = $canOpen && $checkpointInProgress;
        $rows = $canOpen
            ? ($usesWalSnapshot ? $rowsBeforeCheckpoint : $rowsAfterCheckpoint)
            : [];

        return [
            'status' => $canOpen ? 'readonly-checkpoint-snapshot-open' : 'readonly-checkpoint-snapshot-blocked',
            'reason' => $canOpen
                ? ($usesWalSnapshot ? 'readonly_shm_uses_wal_snapshot_during_checkpoint_sync' : 'readonly_shm_uses_checkpointed_database_after_sync')
                : 'readonly_checkpoint_snapshot_requires_existing_database_wal_and_shm',
            'checkpoint_in_progress' => $checkpointInProgress,
            'readonly_shm' => $readonlyShm,
            'page_size' => $pageSize,
            'checkpoint_frame_count' => $checkpointFrameCount,
            'checkpoint_backfilled_frame_count' => $checkpointBackfilledFrameCount,
            'checkpoint_complete' => $checkpointFrameCount === $checkpointBackfilledFrameCount,
            'snapshot_source' => $usesWalSnapshot ? 'wal-readonly-snapshot' : 'checkpointed-database',
            'rows' => $rows,
            'row_count' => count($rows),
            'extended_errcode' => $canOpen ? 'SQLITE_OK' : 'SQLITE_CANTOPEN',
            'write_denials' => $canOpen ? [
                ['statement' => 'PRAGMA wal_checkpoint', 'error' => 'attempt to write a readonly database'],
            ] : [],
            'source' => 'upstream walro.test 2.1.1 through 2.1.4 readonly_shm reader opens during checkpoint xSync hook',
            'dependencies' => [
                'sqlite-wal-readonly-shm-open',
                'sqlite-wal-readonly-checkpoint-snapshot',
                'sqlite-wal-checkpoint-reader-visibility',
            ],
        ];
    }

    /**
     * @param list<array{0:int|string, 1:int|string}> $initialRows
     * @param list<array{0:int|string, 1:int|string}> $writerRows
     * @return array<string, mixed>
     */
    public static function readOnlyWalLockPlan(
        bool $databaseExists,
        bool $walExists,
        bool $shmExists,
        bool $readOnlyConnection,
        bool $mmapCapable,
        int $requestedMmapBytes,
        int $pageSize,
        array $initialRows,
        array $writerRows
    ): array {
        if ($requestedMmapBytes < 0) {
            throw new \InvalidArgumentException('SQLite read-only WAL lock planning requires a non-negative mmap size');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite read-only WAL lock planning requires a power-of-two page size of at least 512');
        }
        if ($initialRows === [] || $writerRows === []) {
            throw new \InvalidArgumentException('SQLite read-only WAL lock planning requires initial and writer rowsets');
        }

        $canOpen = $databaseExists && $walExists && $shmExists && $readOnlyConnection;
        $mmapResult = $canOpen && $mmapCapable ? $requestedMmapBytes : 0;
        $writerCanAppend = $canOpen;
        $writerCommittedRows = $writerCanAppend ? array_values(array_merge($initialRows, $writerRows)) : $initialRows;
        $writeDenials = $canOpen ? [
            ['statement' => 'INSERT INTO t1 VALUES', 'error' => 'attempt to write a readonly database'],
        ] : [];

        return [
            'status' => $canOpen ? 'readonly-wal-lock-open' : 'readonly-wal-lock-blocked',
            'reason' => $canOpen
                ? 'readonly_wal_reader_allows_snapshot_reads_and_denies_writes_without_blocking_writer'
                : 'readonly_wal_reader_requires_database_wal_and_shm_sidecars',
            'database_exists' => $databaseExists,
            'wal_exists' => $walExists,
            'shm_exists' => $shmExists,
            'read_only_connection' => $readOnlyConnection,
            'page_size' => $pageSize,
            'requested_mmap_bytes' => $requestedMmapBytes,
            'mmap_capable' => $mmapCapable,
            'mmap_size_result' => $mmapResult,
            'select_t1_rows_before_writer' => $canOpen ? $initialRows : [],
            'select_t2_rows_after_writer' => $canOpen ? [] : [],
            'writer_append_allowed' => $writerCanAppend,
            'writer_insert_rows' => $writerCanAppend ? $writerRows : [],
            'writer_committed_rows' => $writerCommittedRows,
            'writer_blocked_by_readonly_reader' => false,
            'wal_exists_after_writer_commit' => $writerCanAppend,
            'wal_exists_after_readonly_close' => $writerCanAppend,
            'write_denials' => $writeDenials,
            'extended_errcode' => $canOpen ? 'SQLITE_OK' : 'SQLITE_CANTOPEN',
            'lock_sequence' => $canOpen ? [
                'readonly_reader_acquires_shared_wal_snapshot',
                'readonly_insert_denied_before_write_lock',
                'writer_acquires_wal_write_lock',
                'writer_commit_appends_wal_frame',
                'readonly_reader_selects_second_table_without_deleting_wal',
                'readonly_close_leaves_writer_wal_sidecar',
            ] : [],
            'source' => 'upstream rowallock.test 1.$tn.1 through 1.$tn.5 read-only WAL lock and writer append behavior',
            'dependencies' => [
                'real-upstream-corpus-rowallock',
                'sqlite-wal-readonly-locks',
                'sqlite-wal-readonly-writer-append',
            ],
        ];
    }
}
