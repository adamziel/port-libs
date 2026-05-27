<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalRecoveryPlan
{
    /**
     * @return array{status:string,database_path:string,wal_path:string,reason:string,database_bytes:int,wal_bytes:int,last_commit_frame:int|null,committed_transaction_count:int,applied_page_count:int,uncommitted_frame_count:int,operations:list<array<string,mixed>>,frames:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function recover(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        bool $readOnly = false,
        bool $immutable = false,
        bool $directorySync = true,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL recovery requires a database path');
        }
        if ($readOnly || $immutable) {
            throw new \LogicException('SQLite WAL recovery requires a writable database handle');
        }

        $checkpoint = $wal->checkpointPlan($databaseBytes);
        $lastCommitFrame = $wal->lastCommitFrame();
        $walPath = $databasePath . '-wal';
        $committedTransactions = $wal->committedTransactions();
        $frames = [];
        $appliedPageCount = 0;

        foreach ($checkpoint['frames'] as $frame) {
            $recoveryAction = 'skip';
            if ($frame['applied']) {
                $recoveryAction = 'restore_database_page';
                $appliedPageCount++;
            }

            $frames[] = [
                'frame_index' => $frame['frame_index'],
                'page_number' => $frame['page_number'],
                'database_offset' => $frame['database_offset'],
                'applied' => $frame['applied'],
                'reason' => $frame['reason'],
                'recovery_action' => $recoveryAction,
            ];
        }
        if ($checkpoint['last_commit_frame'] === null && $wal->frames !== []) {
            foreach ($wal->frames as $frame) {
                $frames[] = [
                    'frame_index' => $frame->index,
                    'page_number' => $frame->pageNumber,
                    'database_offset' => ($frame->pageNumber - 1) * ($wal->header->pageSize > 0 ? $wal->header->pageSize : SQLiteHeader::parse($databaseBytes)->pageSize),
                    'applied' => false,
                    'reason' => 'after_last_commit',
                    'recovery_action' => 'skip',
                ];
            }
        }

        if ($lastCommitFrame === null) {
            return [
                'status' => 'skipped',
                'database_path' => $databasePath,
                'wal_path' => $walPath,
                'reason' => count($wal->frames) === 0 ? 'wal_has_no_frames' : 'wal_has_no_committed_transaction',
                'database_bytes' => strlen($databaseBytes),
                'wal_bytes' => strlen($wal->toBytes()),
                'last_commit_frame' => null,
                'committed_transaction_count' => 0,
                'applied_page_count' => 0,
                'uncommitted_frame_count' => count($wal->frames),
                'operations' => [],
                'frames' => $frames,
                'dependencies' => ['sqlite-wal-recovery', 'sqlite-wal-frame-commit-scan'],
            ];
        }

        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => $checkpoint['final_database_bytes'],
                'durable' => false,
                'reason' => 'recover_committed_wal_frames_to_database',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => $checkpoint['final_database_bytes'],
                'durable' => false,
                'reason' => 'trim_database_to_last_wal_commit_size',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_recovered_wal_database',
            ],
            [
                'op' => 'write',
                'path' => $walPath,
                'offset' => 0,
                'bytes' => strlen($wal->toBytes()),
                'durable' => false,
                'reason' => $wal->uncommittedFrameCount() > 0 ? 'preserve_wal_with_uncommitted_tail' : 'preserve_wal_after_recovery',
            ],
            [
                'op' => 'sync',
                'path' => $walPath,
                'durable' => true,
                'reason' => 'sync_recovered_wal_sidecar',
            ],
        ];
        if ($directorySync) {
            $operations[] = [
                'op' => 'sync_directory',
                'path' => dirname($databasePath),
                'durable' => true,
                'reason' => 'persist_recovered_database_and_wal_entries',
            ];
        }

        return [
            'status' => 'ready',
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'reason' => $wal->uncommittedFrameCount() > 0 ? 'committed_wal_frames_recovered_with_tail_preserved' : 'committed_wal_frames_recovered',
            'database_bytes' => $checkpoint['final_database_bytes'],
            'wal_bytes' => strlen($wal->toBytes()),
            'last_commit_frame' => $lastCommitFrame->index,
            'committed_transaction_count' => count($committedTransactions),
            'applied_page_count' => $appliedPageCount,
            'uncommitted_frame_count' => $wal->uncommittedFrameCount(),
            'operations' => $operations,
            'frames' => $frames,
            'dependencies' => ['sqlite-wal-recovery', 'sqlite-wal-frame-commit-scan', 'vfs-file-write-coordination'],
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function payloads(SQLiteWal $wal, string $databaseBytes, string $databasePath): array
    {
        $checkpoint = $wal->checkpointDatabaseImage($databaseBytes);

        return [
            $databasePath => $checkpoint,
            $databasePath . '-wal' => $wal->toBytes(),
        ];
    }
}
