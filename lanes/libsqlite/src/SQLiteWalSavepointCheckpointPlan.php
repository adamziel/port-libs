<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalSavepointCheckpointPlan
{
    /**
     * @return array{status:string,savepoint:string,mode:string,reader_end_frame:int|null,original_frame_count:int,retained_frame_count:int,discarded_frame_count:int,truncate_to_bytes:int,current_wal_bytes:string,current_wal_bytes_length:int,current_checkpoint:array<string, mixed>,current_durable:array<string, mixed>,can_checkpoint:bool,can_reset:bool,can_truncate:bool,busy:bool,reason:string,dependencies:list<string>}
     */
    public static function afterRollbackTo(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        string $mode = 'passive',
        ?int $readerEndFrame = null
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint requires a savepoint name');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint requires database bytes');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['passive', 'full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite checkpoint mode after savepoint rollback: {$mode}");
        }

        $truncation = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $currentWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $currentWal = SQLiteWal::parse($currentWalBytes, $wal->header->pageSize, true);
        $checkpoint = $currentWal->checkpointModePlan($databaseBytes, $mode, $readerEndFrame);
        $durable = $currentWal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);

        return [
            'status' => $checkpoint['busy'] ? 'busy' : 'ready',
            'savepoint' => $savepoint,
            'mode' => $mode,
            'reader_end_frame' => $readerEndFrame,
            'original_frame_count' => $truncation['original_frame_count'],
            'retained_frame_count' => $truncation['retained_frame_count'],
            'discarded_frame_count' => $truncation['discarded_frame_count'],
            'truncate_to_bytes' => $truncation['truncate_to_bytes'],
            'current_wal_bytes' => $currentWalBytes,
            'current_wal_bytes_length' => strlen($currentWalBytes),
            'current_checkpoint' => $checkpoint,
            'current_durable' => $durable,
            'can_checkpoint' => $checkpoint['total_committable_frame_count'] > 0,
            'can_reset' => $checkpoint['can_reset'],
            'can_truncate' => $checkpoint['can_truncate'],
            'busy' => $checkpoint['busy'],
            'reason' => $checkpoint['reason'],
            'dependencies' => array_values(array_unique(array_merge(
                $truncation['discarded_frame_count'] > 0 ? ['sqlite-savepoint-wal-current-prefix'] : [],
                $durable['dependencies'],
                ['sqlite-wal-savepoint-checkpoint-current']
            ))),
        ];
    }
}
