<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalSavepointRecoveryPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,savepoint:string,rollback_to_frame:int,original_frame_count:int,retained_frame_count:int,discarded_frame_count:int,truncate_to_bytes:int,current_wal_bytes:string,current_wal_bytes_length:int,current_reader_end_frame:int,next_reader_end_frame:int,committed_end_offset:int,recovery_end_offset:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,images_match:bool,next_uses_checkpoint_database:bool,discarded_valid_tail_frame_count:int,discarded_corrupt_tail_frame_count:int,can_checkpoint:bool,checkpoint_database_page_count:int|null,dependencies:list<string>}
     */
    public static function currentNextAfterRollbackTo(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        array $pageNumbers
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint recovery requires a savepoint name');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL savepoint recovery requires at least one page number');
        }

        $truncation = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $currentWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $boundary = SQLiteWal::corruptRecoveryCurrentNextBoundary(
            $currentWalBytes,
            $databaseBytes,
            $pageNumbers,
            $wal->header->pageSize
        );
        $recovery = SQLiteWal::transactionRecoveryBoundary($currentWalBytes, $databaseBytes, $wal->header->pageSize);

        return [
            'status' => $boundary['status'],
            'reason' => $boundary['reason'],
            'savepoint' => $savepoint,
            'rollback_to_frame' => $truncation['rollback_to_frame'],
            'original_frame_count' => $truncation['original_frame_count'],
            'retained_frame_count' => $truncation['retained_frame_count'],
            'discarded_frame_count' => $truncation['discarded_frame_count'],
            'truncate_to_bytes' => $truncation['truncate_to_bytes'],
            'current_wal_bytes' => $currentWalBytes,
            'current_wal_bytes_length' => strlen($currentWalBytes),
            'current_reader_end_frame' => $boundary['current_reader_end_frame'],
            'next_reader_end_frame' => $boundary['next_reader_end_frame'],
            'committed_end_offset' => $boundary['committed_end_offset'],
            'recovery_end_offset' => $boundary['recovery_end_offset'],
            'current_reader' => $boundary['current_reader'],
            'next_reader' => $boundary['next_reader'],
            'current_reader_sources' => $boundary['current_reader_sources'],
            'next_reader_sources' => $boundary['next_reader_sources'],
            'current_reader_frame_indexes' => $boundary['current_reader_frame_indexes'],
            'next_reader_frame_indexes' => $boundary['next_reader_frame_indexes'],
            'current_reader_errors' => $boundary['current_reader_errors'],
            'next_reader_errors' => $boundary['next_reader_errors'],
            'images_match' => $boundary['images_match'],
            'next_uses_checkpoint_database' => $boundary['next_uses_checkpoint_database'],
            'discarded_valid_tail_frame_count' => $boundary['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $boundary['discarded_corrupt_tail_frame_count'],
            'can_checkpoint' => $recovery['can_checkpoint'],
            'checkpoint_database_page_count' => $recovery['checkpoint_database_page_count'],
            'dependencies' => array_values(array_unique(array_merge(
                ['sqlite-wal-savepoint-recovery-current-next'],
                $truncation['discarded_frame_count'] > 0 ? ['sqlite-savepoint-wal-current-prefix'] : [],
                $boundary['dependencies']
            ))),
        ];
    }
}
