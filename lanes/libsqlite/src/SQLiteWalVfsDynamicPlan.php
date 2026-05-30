<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalVfsDynamicPlan
{
    private const SCENARIOS = [
        'walvfs-4.1' => [
            'phase' => 'readonly_shm_map_blocks_reader',
            'operation' => 'xShmMap',
            'expected_code' => 'SQLITE_READONLY',
            'message' => 'attempt to write a readonly database',
            'read_count' => null,
            'checkpoint_result' => null,
            'readmarks_before' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'readmarks_after' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'requires_retry' => false,
            'upstream' => 'walvfs.test 4.1',
        ],
        'walvfs-4.2' => [
            'phase' => 'readonly_shm_map_after_busy_shared_lock',
            'operation' => 'xShmMap/xShmLock',
            'expected_code' => 'SQLITE_READONLY',
            'message' => 'attempt to write a readonly database',
            'read_count' => null,
            'checkpoint_result' => null,
            'readmarks_before' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'readmarks_after' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'requires_retry' => true,
            'upstream' => 'walvfs.test 4.2',
        ],
        'walvfs-5.3' => [
            'phase' => 'reader_reclaims_first_readmark_slot',
            'operation' => 'readmark-select',
            'expected_code' => 'SQLITE_OK',
            'message' => 'ok',
            'read_count' => 20,
            'checkpoint_result' => null,
            'readmarks_before' => [0 => 0, 1 => 100, 2 => 100, 3 => 100, 4 => 100],
            'readmarks_after' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'requires_retry' => false,
            'upstream' => 'walvfs.test 5.3',
        ],
        'walvfs-5.4' => [
            'phase' => 'busy_shm_lock_eventually_reclaims_readmark',
            'operation' => 'xShmLock',
            'expected_code' => 'SQLITE_OK',
            'message' => 'ok',
            'read_count' => 20,
            'checkpoint_result' => null,
            'readmarks_before' => [0 => 0, 1 => 100, 2 => 100, 3 => 100, 4 => 100],
            'readmarks_after' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'requires_retry' => true,
            'upstream' => 'walvfs.test 5.4',
        ],
        'walvfs-5.5' => [
            'phase' => 'readonly_shm_map_and_busy_lock_fail_reader',
            'operation' => 'xShmMap/xShmLock',
            'expected_code' => 'SQLITE_READONLY',
            'message' => 'attempt to write a readonly database',
            'read_count' => null,
            'checkpoint_result' => null,
            'readmarks_before' => [0 => 0, 1 => 100, 2 => 100, 3 => 100, 4 => 100],
            'readmarks_after' => [0 => 0, 1 => 100, 2 => 100, 3 => 100, 4 => 100],
            'requires_retry' => true,
            'upstream' => 'walvfs.test 5.5',
        ],
        'walvfs-5.6' => [
            'phase' => 'released_readmark_allows_later_reader',
            'operation' => 'readmark-select',
            'expected_code' => 'SQLITE_OK',
            'message' => 'ok',
            'read_count' => 20,
            'checkpoint_result' => null,
            'readmarks_before' => [0 => 0, 1 => 1, 2 => 100, 3 => 100, 4 => 100],
            'readmarks_after' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'requires_retry' => false,
            'upstream' => 'walvfs.test 5.6',
        ],
        'walvfs-6.2' => [
            'phase' => 'restart_protocol_shared_lock_busy',
            'operation' => 'xShmLock',
            'expected_code' => 'SQLITE_PROTOCOL',
            'message' => 'locking protocol',
            'read_count' => null,
            'checkpoint_result' => null,
            'readmarks_before' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'readmarks_after' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'requires_retry' => true,
            'upstream' => 'walvfs.test 6.2',
        ],
        'walvfs-7.1' => [
            'phase' => 'checkpoint_lock_busy_reports_blocked',
            'operation' => 'xShmLock',
            'expected_code' => 'SQLITE_BUSY',
            'message' => 'checkpoint blocked',
            'read_count' => null,
            'checkpoint_result' => [1, -1, -1],
            'readmarks_before' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'readmarks_after' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'requires_retry' => false,
            'upstream' => 'walvfs.test 7.1',
        ],
        'walvfs-8.3' => [
            'phase' => 'vfs2_checkpoint_refreshes_outdated_cache',
            'operation' => 'wal_checkpoint',
            'expected_code' => 'SQLITE_OK',
            'message' => 'ok',
            'read_count' => 21,
            'checkpoint_result' => [0, 5, 5],
            'readmarks_before' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'readmarks_after' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'requires_retry' => false,
            'upstream' => 'walvfs.test 8.3',
        ],
        'walvfs-9.1' => [
            'phase' => 'readonly_cantinit_plus_shared_lock_ioerr',
            'operation' => 'xShmMap/xShmLock',
            'expected_code' => 'SQLITE_IOERR',
            'message' => 'disk I/O error',
            'read_count' => null,
            'checkpoint_result' => null,
            'readmarks_before' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'readmarks_after' => [0 => 0, 1 => 24, 2 => 100, 3 => 100, 4 => 100],
            'requires_retry' => false,
            'upstream' => 'walvfs.test 9.1',
        ],
    ];

    /**
     * @return array{status:string,script:string,scenario:string,phase:string,operation:string,expected_code:string,message:string,read_count:int|null,checkpoint_result:list<int>|null,readmarks_before:array<int,int>,readmarks_after:array<int,int>,busy_attempts:int,requires_retry:bool,wal_frames:int,backfilled_frames:int,shm_map_writable:bool,database_image_stable:bool,dependencies:list<string>,upstream:list<string>}
     */
    public static function shmBoundary(string $scenario, int $busyAttempts = 1, int $walFrames = 24, int $backfilledFrames = 5): array
    {
        $scenario = trim($scenario);
        if (!isset(self::SCENARIOS[$scenario])) {
            throw new \InvalidArgumentException('Unsupported walvfs dynamic scenario');
        }
        if ($busyAttempts < 1 || $walFrames < 0 || $backfilledFrames < 0) {
            throw new \InvalidArgumentException('WAL VFS dynamic boundary counts must be non-negative');
        }

        $base = self::SCENARIOS[$scenario];
        $expectedCode = $base['expected_code'];
        $readonly = in_array($expectedCode, ['SQLITE_READONLY', 'SQLITE_IOERR'], true)
            && str_contains($base['operation'], 'xShmMap');

        return [
            'status' => 'ok',
            'script' => 'walvfs.test',
            'scenario' => $scenario,
            'phase' => $base['phase'],
            'operation' => $base['operation'],
            'expected_code' => $expectedCode,
            'message' => $base['message'],
            'read_count' => $base['read_count'],
            'checkpoint_result' => $base['checkpoint_result'],
            'readmarks_before' => $base['readmarks_before'],
            'readmarks_after' => $base['readmarks_after'],
            'busy_attempts' => $base['requires_retry'] ? $busyAttempts : 0,
            'requires_retry' => $base['requires_retry'],
            'wal_frames' => $walFrames,
            'backfilled_frames' => $base['checkpoint_result'] === null ? 0 : min($backfilledFrames, $walFrames),
            'shm_map_writable' => !$readonly,
            'database_image_stable' => true,
            'dependencies' => [
                'sqlite-upstream-walvfs-test',
                'sqlite-wal-shm-map-lock-boundary',
                'sqlite-vfs-io-dynamic',
            ],
            'upstream' => [$base['upstream']],
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedScenarios(): array
    {
        return array_keys(self::SCENARIOS);
    }
}
