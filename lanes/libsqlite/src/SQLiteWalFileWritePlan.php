<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalFileWritePlan
{
    /**
     * @return array{database_path:string,wal_path:string,mode:string,read_only:bool,immutable:bool,busy:bool,reason:string,wal_action:string,database_bytes:int,wal_bytes:int,operations:list<array{op:string,path:string,bytes?:int,offset?:int,durable?:bool,reason?:string}>,dependencies:list<string>}
     */
    public static function checkpoint(
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        string $mode = 'passive',
        ?int $readerEndFrame = null,
        bool $readOnly = false,
        bool $immutable = false,
        bool $directorySync = true,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL file-write plan requires a database path');
        }
        if ($readOnly || $immutable) {
            throw new \LogicException('SQLite WAL checkpoint file-write plan requires a writable database handle');
        }

        $result = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        $walPath = $databasePath . '-wal';
        if ($result['mode'] === 'noop') {
            return [
                'database_path' => $databasePath,
                'wal_path' => $walPath,
                'mode' => $result['mode'],
                'read_only' => $readOnly,
                'immutable' => $immutable,
                'busy' => $result['busy'],
                'reason' => $result['reason'],
                'wal_action' => $result['wal_action'],
                'database_bytes' => strlen($result['database_bytes']),
                'wal_bytes' => strlen($result['wal_bytes']),
                'operations' => [],
                'dependencies' => ['sqlite-wal-checkpoint', 'durable-sidecar-write', 'vfs-file-write-coordination'],
            ];
        }

        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($result['database_bytes']),
                'durable' => false,
                'reason' => 'checkpoint_database_pages',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_checkpointed_database',
            ],
        ];

        if ($result['wal_action'] === 'truncate_wal') {
            $operations[] = [
                'op' => 'truncate',
                'path' => $walPath,
                'bytes' => 0,
                'durable' => false,
                'reason' => 'truncate_reset_wal',
            ];
        } else {
            $operations[] = [
                'op' => 'write',
                'path' => $walPath,
                'offset' => 0,
                'bytes' => strlen($result['wal_bytes']),
                'durable' => false,
                'reason' => $result['wal_action'] === 'restart_wal' ? 'write_restarted_wal_header' : 'preserve_wal_sidecar',
            ];
            $operations[] = [
                'op' => 'sync',
                'path' => $walPath,
                'durable' => true,
                'reason' => 'sync_wal_sidecar',
            ];
        }

        if ($directorySync) {
            $operations[] = [
                'op' => 'sync_directory',
                'path' => dirname($databasePath),
                'durable' => true,
                'reason' => 'persist_database_and_wal_directory_entries',
            ];
        }

        return [
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'mode' => $result['mode'],
            'read_only' => $readOnly,
            'immutable' => $immutable,
            'busy' => $result['busy'],
            'reason' => $result['reason'],
            'wal_action' => $result['wal_action'],
            'database_bytes' => strlen($result['database_bytes']),
            'wal_bytes' => strlen($result['wal_bytes']),
            'operations' => $operations,
            'dependencies' => ['sqlite-wal-checkpoint', 'durable-sidecar-write', 'vfs-file-write-coordination'],
        ];
    }
}
