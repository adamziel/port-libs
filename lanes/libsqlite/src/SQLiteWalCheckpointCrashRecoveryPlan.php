<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalCheckpointCrashRecoveryPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,mode:string,crash_phase:string,database_path:string,wal_path:string,recovery:array<string,mixed>,persisted_database_bytes:string,persisted_wal_bytes:string,persisted_wal_bytes_length:int,persisted_wal_action:string,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string|null>,next_reader_sources:list<string|null>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,images_match:bool,next_uses_checkpoint_database:bool,next_replays_persisted_wal:bool,next_uses_reset_wal:bool,discarded_valid_tail_frame_count:int,discarded_corrupt_tail_frame_count:int,operations_applied:list<array<string,mixed>>,operations_pending:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function recoverFromWalBytes(
        string $walBytes,
        string $databaseBytes,
        string $databasePath,
        array $pageNumbers,
        string $mode = 'restart',
        string $crashPhase = 'after_database_sync',
        ?int $databasePageSize = null
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint recovery requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint recovery requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint recovery requires restart or truncate mode');
        }

        $crashPhase = strtolower(trim($crashPhase));
        if (!in_array($crashPhase, ['after_database_sync', 'after_wal_sidecar_write', 'after_directory_sync'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL checkpoint recovery crash phase: {$crashPhase}");
        }

        $recovery = SQLiteWal::transactionRecoveryBoundary($walBytes, $databaseBytes, $databasePageSize);
        $currentWal = $recovery['wal'];
        $committedWal = $recovery['committed_wal'];
        $pageSize = $currentWal->header->pageSize !== 0
            ? $currentWal->header->pageSize
            : ($databasePageSize ?? SQLiteHeader::parse($databaseBytes)->pageSize);
        $currentEndFrame = $recovery['valid_frame_count'];
        $checkpointDatabaseBytes = $recovery['checkpoint_database_bytes'] ?? $databaseBytes;
        $restartWalBytes = substr($recovery['committed_wal_bytes'], 0, 32);
        $persistedDatabaseBytes = $recovery['can_checkpoint'] ? $checkpointDatabaseBytes : $databaseBytes;
        $persistedWalBytes = match ($crashPhase) {
            'after_database_sync' => $walBytes,
            'after_wal_sidecar_write', 'after_directory_sync' => $mode === 'truncate' ? '' : $restartWalBytes,
        };
        $persistedWalAction = match ($crashPhase) {
            'after_database_sync' => 'preserve_pre_recovery_wal',
            default => $mode === 'truncate' ? 'truncate_recovered_wal' : 'restart_recovered_wal',
        };

        $nextWal = null;
        $nextEndFrame = 0;
        if ($persistedWalBytes !== '') {
            $nextRecovery = SQLiteWal::transactionRecoveryBoundary($persistedWalBytes, $persistedDatabaseBytes, $pageSize);
            $nextWal = $nextRecovery['committed_wal'];
            $nextEndFrame = $nextRecovery['committed_frame_count'];
        }

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint recovery pages must be integers');
            }

            $current[] = self::safeWalVisibility($currentWal, $databaseBytes, $pageNumber, $currentEndFrame);
            $next[] = $nextWal === null || $nextEndFrame === 0
                ? self::safeDatabaseVisibility($persistedDatabaseBytes, $pageSize, $pageNumber)
                : self::safeWalVisibility($nextWal, $persistedDatabaseBytes, $pageNumber, $nextEndFrame);
        }

        $writeOperations = self::recoveredWriteOperations($databasePath, $mode, $crashPhase, $recovery, $persistedWalBytes);

        return [
            'status' => $recovery['status'] === 'corrupt' ? 'recovered_without_checkpoint' : 'recovered',
            'reason' => match ($crashPhase) {
                'after_database_sync' => 'checkpoint_database_durable_recovery_replays_committed_prefix',
                'after_wal_sidecar_write' => 'checkpoint_wal_recovery_sidecar_durable_before_directory_sync',
                'after_directory_sync' => 'checkpoint_wal_recovery_fully_durable',
            },
            'mode' => $mode,
            'crash_phase' => $crashPhase,
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'recovery' => $recovery,
            'persisted_database_bytes' => $persistedDatabaseBytes,
            'persisted_wal_bytes' => $persistedWalBytes,
            'persisted_wal_bytes_length' => strlen($persistedWalBytes),
            'persisted_wal_action' => $persistedWalAction,
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::column($current, 'source'),
            'next_reader_sources' => self::column($next, 'source'),
            'current_reader_frame_indexes' => self::column($current, 'frame_index'),
            'next_reader_frame_indexes' => self::column($next, 'frame_index'),
            'current_reader_errors' => self::errors($current),
            'next_reader_errors' => self::errors($next),
            'images_match' => self::images($current) === self::images($next),
            'next_uses_checkpoint_database' => $persistedDatabaseBytes !== $databaseBytes,
            'next_replays_persisted_wal' => $crashPhase === 'after_database_sync' && $nextEndFrame > 0,
            'next_uses_reset_wal' => $crashPhase !== 'after_database_sync' && $mode === 'restart',
            'discarded_valid_tail_frame_count' => $recovery['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $recovery['discarded_corrupt_tail_frame_count'],
            'operations_applied' => $writeOperations['applied'],
            'operations_pending' => $writeOperations['pending'],
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                ['sqlite-wal-checkpoint-recovery-current-next']
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,mode:string,crash_phase:string,database_path:string,wal_path:string,checkpoint:array<string,mixed>,persisted_database_bytes:string,persisted_wal_bytes:string,persisted_wal_bytes_length:int,persisted_wal_action:string,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string|null>,next_reader_sources:list<string|null>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,images_match:bool,next_uses_checkpoint_database:bool,next_replays_persisted_wal:bool,next_uses_reset_wal:bool,operations_applied:list<array<string,mixed>>,operations_pending:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function plan(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        array $pageNumbers,
        string $mode = 'restart',
        string $crashPhase = 'after_database_sync',
        ?int $readerEndFrame = null
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checkpoint crash recovery requires a database path');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint crash recovery requires at least one page number');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint crash recovery requires restart or truncate mode');
        }

        $crashPhase = strtolower(trim($crashPhase));
        if (!in_array($crashPhase, ['after_database_sync', 'after_wal_sidecar_write', 'after_directory_sync'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL checkpoint crash phase: {$crashPhase}");
        }

        $checkpoint = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        $writePlan = SQLiteWalFileWritePlan::checkpoint($wal, $databaseBytes, $databasePath, $mode, $readerEndFrame, false, false);
        $currentEndFrame = $readerEndFrame ?? $wal->frameCount();

        if ($checkpoint['busy']) {
            return [
                'status' => 'busy',
                'reason' => $checkpoint['reason'],
                'mode' => $mode,
                'crash_phase' => $crashPhase,
                'database_path' => $databasePath,
                'wal_path' => $databasePath . '-wal',
                'checkpoint' => $checkpoint,
                'persisted_database_bytes' => $databaseBytes,
                'persisted_wal_bytes' => $wal->toBytes(),
                'persisted_wal_bytes_length' => strlen($wal->toBytes()),
                'persisted_wal_action' => 'preserve_wal',
                'current_reader_end_frame' => $currentEndFrame,
                'next_reader_end_frame' => $currentEndFrame,
                'current_reader' => [],
                'next_reader' => [],
                'current_reader_sources' => [],
                'next_reader_sources' => [],
                'current_reader_frame_indexes' => [],
                'next_reader_frame_indexes' => [],
                'current_reader_errors' => [],
                'next_reader_errors' => [],
                'images_match' => false,
                'next_uses_checkpoint_database' => false,
                'next_replays_persisted_wal' => false,
                'next_uses_reset_wal' => false,
                'operations_applied' => [],
                'operations_pending' => $writePlan['operations'],
                'dependencies' => array_values(array_unique(array_merge(
                    $checkpoint['dependencies'],
                    $writePlan['dependencies'],
                    ['sqlite-wal-checkpoint-crash-recovery-current-next']
                ))),
            ];
        }

        $persistedDatabaseBytes = $checkpoint['database_bytes'];
        $persistedWalBytes = match ($crashPhase) {
            'after_database_sync' => $wal->toBytes(),
            'after_wal_sidecar_write', 'after_directory_sync' => $checkpoint['wal_bytes'],
        };
        $persistedWalAction = match ($crashPhase) {
            'after_database_sync' => 'preserve_pre_reset_wal',
            default => $checkpoint['wal_action'],
        };

        $nextWal = $persistedWalBytes === ''
            ? null
            : SQLiteWal::parse($persistedWalBytes, $wal->header->pageSize, $wal->checksumsValidated);
        $nextEndFrame = $nextWal?->frameCount() ?? 0;

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite WAL checkpoint crash recovery pages must be integers');
            }

            $current[] = self::safeWalVisibility($wal, $databaseBytes, $pageNumber, $currentEndFrame);
            $next[] = $nextWal === null
                ? self::safeDatabaseVisibility($persistedDatabaseBytes, $wal->header->pageSize, $pageNumber)
                : self::safeWalVisibility($nextWal, $persistedDatabaseBytes, $pageNumber, $nextEndFrame);
        }

        return [
            'status' => 'recovered',
            'reason' => match ($crashPhase) {
                'after_database_sync' => 'checkpoint_database_durable_wal_replayed_idempotently',
                'after_wal_sidecar_write' => 'checkpoint_wal_sidecar_state_recovered_before_directory_sync',
                'after_directory_sync' => 'checkpoint_fully_durable_after_directory_sync',
            },
            'mode' => $mode,
            'crash_phase' => $crashPhase,
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'checkpoint' => $checkpoint,
            'persisted_database_bytes' => $persistedDatabaseBytes,
            'persisted_wal_bytes' => $persistedWalBytes,
            'persisted_wal_bytes_length' => strlen($persistedWalBytes),
            'persisted_wal_action' => $persistedWalAction,
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::column($current, 'source'),
            'next_reader_sources' => self::column($next, 'source'),
            'current_reader_frame_indexes' => self::column($current, 'frame_index'),
            'next_reader_frame_indexes' => self::column($next, 'frame_index'),
            'current_reader_errors' => self::errors($current),
            'next_reader_errors' => self::errors($next),
            'images_match' => self::images($current) === self::images($next),
            'next_uses_checkpoint_database' => $persistedDatabaseBytes !== $databaseBytes,
            'next_replays_persisted_wal' => $crashPhase === 'after_database_sync',
            'next_uses_reset_wal' => $crashPhase !== 'after_database_sync',
            'operations_applied' => self::appliedOperations($writePlan['operations'], $crashPhase),
            'operations_pending' => self::pendingOperations($writePlan['operations'], $crashPhase),
            'dependencies' => array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                $writePlan['dependencies'],
                ['sqlite-wal-checkpoint-crash-recovery-current-next']
            ))),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function safeWalVisibility(SQLiteWal $wal, string $databaseBytes, int $pageNumber, ?int $snapshotEndFrame): array
    {
        try {
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $snapshotEndFrame);
        } catch (\OutOfBoundsException $e) {
            $snapshot = $wal->readerSnapshot($databaseBytes, $snapshotEndFrame);

            return [
                'page_number' => $pageNumber,
                'source' => 'missing',
                'frame_index' => null,
                'database_offset' => null,
                'image' => null,
                'snapshot_end_frame' => $snapshot['end_frame'],
                'snapshot_commit_frame' => $snapshot['commit_frame']?->index,
                'database_page_count' => $snapshot['database_page_count'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function safeDatabaseVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint crash recovery page numbers are one-based');
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL checkpoint crash recovery requires page-aligned database bytes');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $databasePageCount) {
            return [
                'page_number' => $pageNumber,
                'source' => 'missing',
                'frame_index' => null,
                'database_offset' => null,
                'image' => null,
                'snapshot_end_frame' => 0,
                'snapshot_commit_frame' => null,
                'database_page_count' => $databasePageCount,
                'error' => "SQLite WAL checkpoint crash recovery base page {$pageNumber} is missing from the database image",
            ];
        }

        $offset = ($pageNumber - 1) * $pageSize;

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => $offset,
            'image' => substr($databaseBytes, $offset, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $databasePageCount,
        ];
    }

    /**
     * @param list<array<string,mixed>> $operations
     * @return list<array<string,mixed>>
     */
    private static function appliedOperations(array $operations, string $crashPhase): array
    {
        return array_slice($operations, 0, self::operationCutoff($crashPhase));
    }

    /**
     * @param list<array<string,mixed>> $operations
     * @return list<array<string,mixed>>
     */
    private static function pendingOperations(array $operations, string $crashPhase): array
    {
        return array_slice($operations, self::operationCutoff($crashPhase));
    }

    private static function operationCutoff(string $crashPhase): int
    {
        return match ($crashPhase) {
            'after_database_sync' => 2,
            'after_wal_sidecar_write' => 3,
            'after_directory_sync' => PHP_INT_MAX,
        };
    }

    /**
     * @param array<string,mixed> $recovery
     * @return array{applied:list<array<string,mixed>>,pending:list<array<string,mixed>>}
     */
    private static function recoveredWriteOperations(string $databasePath, string $mode, string $crashPhase, array $recovery, string $persistedWalBytes): array
    {
        $operations = [];
        if ($recovery['can_checkpoint']) {
            $operations[] = [
                'target' => $databasePath,
                'reason' => 'checkpoint_recovered_committed_wal_prefix',
                'bytes' => strlen((string) $recovery['checkpoint_database_bytes']),
            ];
            $operations[] = [
                'target' => $databasePath,
                'reason' => 'sync_checkpoint_database_image',
                'bytes' => 0,
            ];
        }

        $operations[] = [
            'target' => $databasePath . '-wal',
            'reason' => $mode === 'truncate' ? 'truncate_recovered_wal' : 'restart_recovered_wal_header',
            'bytes' => strlen($persistedWalBytes),
        ];
        $operations[] = [
            'target' => $databasePath . '-wal',
            'reason' => 'sync_recovered_wal_sidecar',
            'bytes' => 0,
        ];
        $operations[] = [
            'target' => dirname($databasePath),
            'reason' => 'persist_recovered_checkpoint_directory_entries',
            'bytes' => 0,
        ];

        $cutoff = match ($crashPhase) {
            'after_database_sync' => $recovery['can_checkpoint'] ? 2 : 0,
            'after_wal_sidecar_write' => $recovery['can_checkpoint'] ? 3 : 1,
            'after_directory_sync' => PHP_INT_MAX,
        };

        return [
            'applied' => array_slice($operations, 0, $cutoff),
            'pending' => array_slice($operations, $cutoff),
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

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function errors(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            if (isset($row['error']) && is_string($row['error'])) {
                $errors[] = $row['error'];
            }
        }

        return $errors;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string|null>
     */
    private static function images(array $rows): array
    {
        return array_map(static fn (array $row): ?string => is_string($row['image'] ?? null) ? $row['image'] : null, $rows);
    }
}
