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
     * @return list<array<string, mixed>>
     */
    public static function walCheckpointNoopRows(): array
    {
        $value = 123;
        $rows = [];

        for ($rowid = 1; $rowid <= 1000; $rowid++) {
            $bytes = '';
            $sum = 0;
            for ($i = 0; $i < 64; $i++) {
                $value = (int) ((1103515245 * $value + 12345) % 2147483648);
                $byte = $value % 256;
                $sum += $byte;
                $bytes .= chr($byte);
            }

            $rows[] = [
                'upstream' => 'walckptnoop.test 1.0 row ' . $rowid,
                'rowid' => $rowid,
                'hex' => strtoupper(bin2hex($bytes)),
                'byte_sum' => $sum,
                'length' => 64,
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-noop'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walCheckpointNoopCases(): array
    {
        return [
            [
                'upstream' => 'walckptnoop.test 1.1',
                'statement' => 'PRAGMA wal_checkpoint = noop',
                'checkpoint' => [0, 298, 0],
                'mode' => 'noop',
                'log_frame_count' => 298,
                'checkpointed_frame_count' => 0,
                'busy' => 0,
                'changes_database' => false,
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-noop'],
            ],
            [
                'upstream' => 'walckptnoop.test 1.2',
                'statement' => 'PRAGMA wal_checkpoint = noop',
                'checkpoint' => [0, 298, 0],
                'mode' => 'noop',
                'log_frame_count' => 298,
                'checkpointed_frame_count' => 0,
                'busy' => 0,
                'changes_database' => false,
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-noop'],
            ],
            [
                'upstream' => 'walckptnoop.test 1.3',
                'statement' => 'PRAGMA wal_checkpoint = passive',
                'checkpoint' => [0, 298, 298],
                'mode' => 'passive',
                'log_frame_count' => 298,
                'checkpointed_frame_count' => 298,
                'busy' => 0,
                'changes_database' => true,
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-passive'],
            ],
            [
                'upstream' => 'walckptnoop.test 1.4',
                'statement' => 'PRAGMA wal_checkpoint = noop',
                'checkpoint' => [0, 298, 298],
                'mode' => 'noop',
                'log_frame_count' => 298,
                'checkpointed_frame_count' => 298,
                'busy' => 0,
                'changes_database' => false,
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-noop'],
            ],
            [
                'upstream' => 'walckptnoop.test 1.5',
                'statement' => 'PRAGMA wal_checkpoint = noop after restore',
                'checkpoint' => [0, 298, 0],
                'mode' => 'noop',
                'log_frame_count' => 298,
                'checkpointed_frame_count' => 0,
                'busy' => 0,
                'changes_database' => false,
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-noop'],
            ],
            [
                'upstream' => 'walckptnoop.test 1.6',
                'statement' => 'PRAGMA wal_checkpoint = noop without wal frames',
                'checkpoint' => [0, 0, 0],
                'mode' => 'noop',
                'log_frame_count' => 0,
                'checkpointed_frame_count' => 0,
                'busy' => 0,
                'changes_database' => false,
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-noop'],
            ],
            [
                'upstream' => 'walckptnoop.test 1.7',
                'statement' => 'DELETE transaction then PRAGMA wal_checkpoint = noop',
                'checkpoint' => [1, 'database table is locked'],
                'mode' => 'noop',
                'log_frame_count' => 0,
                'checkpointed_frame_count' => 0,
                'busy' => 1,
                'changes_database' => false,
                'error' => 'database table is locked',
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-noop-locked'],
            ],
            [
                'upstream' => 'walckptnoop.test 1.8',
                'statement' => 'COMMIT then PRAGMA wal_checkpoint = noop',
                'checkpoint' => [0, 5, 0],
                'mode' => 'noop',
                'log_frame_count' => 5,
                'checkpointed_frame_count' => 0,
                'busy' => 0,
                'changes_database' => false,
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-noop'],
            ],
            [
                'upstream' => 'walckptnoop.test 1.9',
                'statement' => 'sqlite3_wal_checkpoint_v2 db noop',
                'checkpoint' => [0, 5, 0],
                'mode' => 'noop',
                'log_frame_count' => 5,
                'checkpointed_frame_count' => 0,
                'busy' => 0,
                'changes_database' => false,
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-v2-noop'],
            ],
            [
                'upstream' => 'walckptnoop.test 1.10',
                'statement' => 'PRAGMA journal_mode = delete; PRAGMA wal_checkpoint = noop',
                'checkpoint' => [0, -1, -1],
                'mode' => 'noop',
                'journal_mode' => 'delete',
                'log_frame_count' => -1,
                'checkpointed_frame_count' => -1,
                'busy' => 0,
                'changes_database' => false,
                'dependencies' => ['real-upstream-corpus-walckptnoop', 'sqlite-wal-checkpoint-noop-rollback-mode'],
            ],
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
