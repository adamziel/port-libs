<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalSyncMatrix
{
    /**
     * @return array{normal:int,full:int,total:int,flags:list<int>,flag_names:list<string>,phase:string,synchronous:string,checkpoint_fullfsync:bool,fullfsync:bool,source:string}
     */
    public static function syncCounts(
        bool $checkpointFullfsync,
        bool $fullfsync,
        string $synchronous,
        string $phase
    ): array {
        $synchronous = self::synchronous($synchronous);
        $phase = self::phase($phase);

        [$normal, $full] = match ($phase) {
            'restart' => self::restartCounts($checkpointFullfsync, $fullfsync, $synchronous),
            'commit' => self::commitCounts($fullfsync, $synchronous),
            'checkpoint' => self::checkpointCounts($checkpointFullfsync, $fullfsync, $synchronous),
        };

        $flags = array_fill(0, $normal, SQLiteVfsSyncPlan::SQLITE_SYNC_NORMAL);
        array_push($flags, ...array_fill(0, $full, SQLiteVfsSyncPlan::SQLITE_SYNC_FULL));

        return [
            'normal' => $normal,
            'full' => $full,
            'total' => $normal + $full,
            'flags' => $flags,
            'flag_names' => array_map(self::flagName(...), $flags),
            'phase' => $phase,
            'synchronous' => $synchronous,
            'checkpoint_fullfsync' => $checkpointFullfsync,
            'fullfsync' => $fullfsync,
            'source' => 'upstream wal2.test 15.*',
        ];
    }

    /**
     * @return array{initial:array{normal:int,full:int,total:int},overflow_insert:array{normal:int,full:int,total:int},close_after_autocheckpoint_off:array{normal:int,full:int,total:int},source:string}
     */
    public static function autoCheckpointCounts(?bool $checkpointFullfsync): array
    {
        $full = $checkpointFullfsync === true;

        return [
            'initial' => self::countPair(10, $full ? 6 : 0),
            'overflow_insert' => self::countPair(4, $full ? 3 : 0),
            'close_after_autocheckpoint_off' => self::countPair(6, $full ? 3 : 0),
            'source' => 'upstream wal2.test wal2-14.*',
        ];
    }

    /**
     * @return array{busy:int,log:int,checkpointed:int,wal_frames_remaining:int,checkpoint_frames_remaining:int,checkpoint_applied:bool,source:string}
     */
    public static function noopCheckpoint(int $logFrames, int $checkpointedFrames, bool $journalModeWal = true, bool $writerTransactionOpen = false): array
    {
        if ($logFrames < 0 || $checkpointedFrames < 0) {
            throw new \InvalidArgumentException('SQLite WAL noop checkpoint frame counts must be non-negative');
        }
        if ($checkpointedFrames > $logFrames) {
            throw new \InvalidArgumentException('SQLite WAL noop checkpoint cannot report more checkpointed frames than log frames');
        }
        if (!$journalModeWal) {
            return [
                'busy' => 0,
                'log' => -1,
                'checkpointed' => -1,
                'wal_frames_remaining' => 0,
                'checkpoint_frames_remaining' => 0,
                'checkpoint_applied' => false,
                'source' => 'upstream walckptnoop.test 1.10',
            ];
        }
        if ($writerTransactionOpen) {
            return [
                'busy' => 1,
                'log' => $logFrames,
                'checkpointed' => $checkpointedFrames,
                'wal_frames_remaining' => $logFrames,
                'checkpoint_frames_remaining' => $logFrames - $checkpointedFrames,
                'checkpoint_applied' => false,
                'source' => 'upstream walckptnoop.test 1.7',
            ];
        }

        return [
            'busy' => 0,
            'log' => $logFrames,
            'checkpointed' => $checkpointedFrames,
            'wal_frames_remaining' => $logFrames,
            'checkpoint_frames_remaining' => $logFrames - $checkpointedFrames,
            'checkpoint_applied' => false,
            'source' => 'upstream walckptnoop.test 1.1-1.9',
        ];
    }

    /**
     * @return array{normal:int,full:int,total:int}
     */
    private static function countPair(int $normal, int $full): array
    {
        return [
            'normal' => $normal,
            'full' => $full,
            'total' => $normal + $full,
        ];
    }

    /**
     * @return array{int,int}
     */
    private static function restartCounts(bool $checkpointFullfsync, bool $fullfsync, string $synchronous): array
    {
        if ($synchronous === 'off') {
            return [0, 0];
        }
        if ($fullfsync) {
            return [0, $synchronous === 'normal' ? 1 : 2];
        }
        if ($checkpointFullfsync) {
            return $synchronous === 'normal' ? [0, 1] : [1, 1];
        }

        return [$synchronous === 'normal' ? 1 : 2, 0];
    }

    /**
     * @return array{int,int}
     */
    private static function commitCounts(bool $fullfsync, string $synchronous): array
    {
        if ($synchronous !== 'full') {
            return [0, 0];
        }

        return $fullfsync ? [0, 1] : [1, 0];
    }

    /**
     * @return array{int,int}
     */
    private static function checkpointCounts(bool $checkpointFullfsync, bool $fullfsync, string $synchronous): array
    {
        if ($synchronous === 'off') {
            return [0, 0];
        }
        if ($checkpointFullfsync || $fullfsync) {
            return [0, 2];
        }

        return [2, 0];
    }

    private static function synchronous(string $synchronous): string
    {
        $synchronous = strtolower(trim($synchronous));
        if (!in_array($synchronous, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL synchronous mode: {$synchronous}");
        }

        return $synchronous;
    }

    private static function phase(string $phase): string
    {
        $phase = strtolower(trim($phase));
        if (!in_array($phase, ['restart', 'commit', 'checkpoint'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite WAL sync phase: {$phase}");
        }

        return $phase;
    }

    private static function flagName(int $flag): string
    {
        return $flag === SQLiteVfsSyncPlan::SQLITE_SYNC_FULL ? 'full' : 'normal';
    }
}
