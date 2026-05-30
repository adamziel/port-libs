<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRealUpstreamPagerWalDynamicCorpusPlan
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function wal2HeaderRecoveryCases(): array
    {
        $recover = [
            'recover:0:exclusive', 'recover:1-2:exclusive',
            'readmark1:exclusive', 'readmark1:unlock',
            'readmark2:exclusive', 'readmark2:unlock',
            'readmark3:exclusive', 'readmark3:unlock',
            'readmark4:exclusive', 'readmark4:unlock',
            'recover:1-2:unlock', 'recover:0:unlock',
            'readmark1:shared', 'readmark1:shared-unlock',
        ];
        $initSlot = ['readmark1:exclusive', 'readmark1:unlock', 'readmark1:shared', 'readmark1:shared-unlock'];

        $rows = [
            [2, 5, 5, 15, 0, $recover],
            [3, 6, 6, 21, 1, $recover],
            [4, 7, 7, 28, 2, $recover],
            [5, 8, 8, 36, 3, $recover],
            [6, 9, 9, 45, 4, $recover],
            [7, 10, 10, 55, 5, $recover],
            [8, 11, 11, 66, 6, $recover],
            [9, 12, 12, 78, 7, $recover],
            [10, 13, 13, 91, 8, $recover],
            [11, 14, 14, 105, 9, $recover],
            [12, 15, 15, 120, -1, $initSlot],
        ];

        return array_map(
            static fn (array $row): array => self::caseRow('wal2-1.' . $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], 'wal-index header recovery'),
            $rows
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal2StaleHeaderCases(): array
    {
        $locks = [
            'writer:exclusive', 'writer:unlock',
            'readmark1:exclusive', 'readmark1:unlock',
            'readmark1:shared', 'readmark1:shared-unlock',
        ];
        $rows = [
            [2, 5, 4, 10, 5, 15, 0],
            [3, 6, 5, 15, 6, 21, 1],
            [4, 7, 6, 21, 7, 28, 2],
            [5, 8, 7, 28, 8, 36, 3],
            [6, 9, 8, 36, 9, 45, 4],
            [7, 10, 9, 45, 10, 55, 5],
            [8, 11, 10, 55, 11, 66, 6],
            [9, 12, 11, 66, 12, 78, 7],
        ];

        return array_map(
            static fn (array $row): array => self::caseRow('wal2-2.' . $row[0], $row[1], $row[4], $row[5], $row[6], $locks, 'stale but checksum-valid wal-index header') + [
                'stale_count' => $row[2],
                'stale_sum' => $row[3],
                'stale_snapshot_used' => true,
                'recovered_snapshot_used' => true,
            ],
            $rows
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function wal2NoSharedMemoryOpenCase(): array
    {
        return [
            'upstream' => 'wal2.test wal2-4.1..4.3',
            'wal_checkpoint_result' => ['wal', 0, 3, 3],
            'noshm_read' => ['code' => 1, 'message' => 'unable to open database file'],
            'shm_read' => ['code' => 0, 'rows' => [['need xShmOpen to see this']]],
            'requires_shm_interfaces' => true,
            'dependencies' => ['real-upstream-corpus-wal2', 'sqlite-wal-shm-open-required'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function wal2CheckpointRecoveryLockCase(): array
    {
        return [
            'upstream' => 'wal2.test wal2-5.1',
            'checkpoint_forces_recovery' => true,
            'expected_locks' => [
                'checkpoint:exclusive',
                'writer:exclusive',
                'recover:exclusive',
                'readmark1:exclusive',
                'readmark1:unlock',
                'readmark2:exclusive',
                'readmark2:unlock',
                'readmark3:exclusive',
                'readmark3:unlock',
                'readmark4:exclusive',
                'readmark4:unlock',
                'recover:unlock',
                'writer:unlock',
                'readmark0:exclusive',
                'readmark0:unlock',
                'checkpoint:unlock',
            ],
            'transition_state' => 'checkpoint-client-runs-recovery-before-backfill',
            'dependencies' => ['real-upstream-corpus-wal2', 'sqlite-wal-checkpoint-recovery-locks'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function caseRow(string $upstream, int $inserted, int $count, int $sum, int $headerField, array $locks, string $behavior): array
    {
        return [
            'upstream' => 'wal2.test ' . $upstream,
            'inserted' => $inserted,
            'result_count' => $count,
            'result_sum' => $sum,
            'expected_sum' => intdiv($count * ($count + 1), 2),
            'wal_index_header_field' => $headerField,
            'header_corrupted' => $headerField >= 0,
            'behavior' => $behavior,
            'locks' => $locks,
            'lock_count' => count($locks),
            'reader_sees_consistent_snapshot' => true,
            'dependencies' => ['real-upstream-corpus-wal2', 'sqlite-wal-index-header-recovery'],
        ];
    }
}
