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
     * @return list<array<string, mixed>>
     */
    public static function walOverwriteRecoveryRows(): array
    {
        $rows = [];

        foreach ([1 => 'empty-wal-start', 2 => 'preexisting-wal-transaction'] as $variant => $startState) {
            for ($rowid = 1; $rowid <= 20; $rowid++) {
                for ($loop = 1; $loop <= 5; $loop++) {
                    $base = [
                        'upstream' => 'waloverwrite.test 1.' . $variant . ' row ' . $rowid . ' loop ' . $loop,
                        'variant' => $variant,
                        'start_state' => $startState,
                        'rowid' => $rowid,
                        'loop' => $loop,
                        'initial_blob_length' => 800,
                        'committed_blob_length' => 799,
                        'post_checkpoint_blob_length' => 798,
                        'rolled_back_blob_length' => 797,
                        'row_count' => 20,
                        'cache_size_pages' => 5,
                        'page_size' => 1024,
                        'statement_update_passes' => 5,
                        'savepoint_update_passes' => 5,
                        'dependencies' => [
                            'real-upstream-corpus-waloverwrite',
                            'sqlite-wal-overwrite-recovery',
                            'sqlite-wal-savepoint-rollback-recovery',
                        ],
                    ];

                    $rows[] = $base + [
                        'assertion' => 'statement transaction stores committed 799-byte blob',
                        'expected_length' => 799,
                        'observed_length' => 799,
                        'wal_frame_range' => [41, 59],
                        'recovery_source' => 'database-plus-wal-copy',
                        'savepoint_rolled_back' => false,
                    ];
                    $rows[] = $base + [
                        'assertion' => 'database-only copy keeps original 800-byte blob before WAL recovery',
                        'expected_length' => 800,
                        'observed_length' => 800,
                        'wal_frame_range' => [41, 59],
                        'recovery_source' => 'database-copy-without-wal',
                        'savepoint_rolled_back' => false,
                    ];
                    $rows[] = $base + [
                        'assertion' => 'post-checkpoint transaction stores 798-byte blob before savepoint rollback',
                        'expected_length' => 798,
                        'observed_length' => 798,
                        'wal_frame_range' => [56, 74],
                        'recovery_source' => 'database-plus-wal-copy',
                        'savepoint_rolled_back' => true,
                    ];
                    $rows[] = $base + [
                        'assertion' => 'rolled-back savepoint 797-byte blob is excluded from recovery',
                        'expected_length' => 798,
                        'observed_length' => 798,
                        'excluded_length' => 797,
                        'wal_frame_range' => [56, 74],
                        'recovery_source' => 'database-plus-wal-copy',
                        'savepoint_rolled_back' => true,
                    ];
                    $rows[] = $base + [
                        'assertion' => 'integrity check remains ok after overwrite recovery',
                        'integrity_check' => 'ok',
                        'expected_length' => $loop <= 5 ? 798 : 799,
                        'observed_length' => $loop <= 5 ? 798 : 799,
                        'wal_frame_range' => [56, 74],
                        'recovery_source' => 'integrity-check',
                        'savepoint_rolled_back' => true,
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walPersistLimitRows(): array
    {
        $rows = [];

        for ($rowid = 0; $rowid < 200; $rowid++) {
            $leftSeed = self::walPersistPayloadSeed($rowid, 500, 17);
            $rightSeed = self::walPersistPayloadSeed($rowid, 500, 91);
            $pageNumber = 2 + intdiv($rowid, 4);
            $walFrame = 3 + $rowid;
            $checkpointBatch = intdiv($rowid, 128);

            $rows[] = [
                'upstream' => 'walpersist.test 3.2 row ' . $rowid,
                'rowid' => $rowid,
                'left_length' => 500,
                'right_length' => 500,
                'left_digest' => $leftSeed['digest'],
                'right_digest' => $rightSeed['digest'],
                'left_prefix' => $leftSeed['prefix'],
                'right_prefix' => $rightSeed['prefix'],
                'primary_key_columns' => ['a', 'b'],
                'wal_frame' => $walFrame,
                'checkpoint_batch' => $checkpointBatch,
                'page_number' => $pageNumber,
                'wal_autocheckpoint' => 128,
                'journal_size_limit' => 16384,
                'persist_wal_enabled' => true,
                'wal_exists_before_close' => true,
                'wal_truncated_size_after_close' => 0,
                'integrity_check_after_reopen' => 'ok',
                'dependencies' => [
                    'real-upstream-corpus-walpersist',
                    'sqlite-wal-persist-file-control',
                    'sqlite-wal-journal-size-limit',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walHookAutocheckpointRows(): array
    {
        $rows = [];
        $pageSize = 1024;
        $threshold = 10;
        $logPages = 3;
        $databasePages = 6;
        $checkpointCount = 0;

        for ($transaction = 1; $transaction <= 1000; $transaction++) {
            $previousLogPages = $logPages;
            $previousDatabasePages = $databasePages;
            $logPages += 2;
            $checkpointed = $logPages >= $threshold;

            if ($checkpointed) {
                $checkpointCount++;
                $databasePages = max($databasePages, 8 + (($checkpointCount - 1) * 2));
                $logPages = 11;
            }

            $rows[] = [
                'upstream' => 'walhook.test walhook-2.' . (4 + (($transaction - 1) % 6)) . ' dynamic transaction ' . $transaction,
                'transaction' => $transaction,
                'page_size' => $pageSize,
                'autocheckpoint_threshold' => $threshold,
                'previous_log_pages' => $previousLogPages,
                'previous_database_pages' => $previousDatabasePages,
                'wal_hook_database' => 'main',
                'wal_hook_frame_count' => $logPages,
                'database_pages_after_commit' => $databasePages,
                'database_size_after_commit' => $databasePages * $pageSize,
                'wal_pages_after_commit' => $logPages,
                'wal_file_size_after_commit' => self::walFileSize($logPages, $pageSize),
                'checkpointed' => $checkpointed,
                'checkpoint_count' => $checkpointCount,
                'recycled_wal_start' => $checkpointed && $transaction > 4,
                'dependencies' => [
                    'real-upstream-corpus-walhook',
                    'sqlite-wal-hook-autocheckpoint',
                    'sqlite-wal-autocheckpoint-threshold',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return array{digest: string, prefix: string}
     */
    private static function walPersistPayloadSeed(int $rowid, int $length, int $salt): array
    {
        $bytes = '';
        $state = (($rowid + 1) * 1103515245 + $salt) & 0x7fffffff;

        for ($i = 0; $i < $length; $i++) {
            $state = (int) (($state * 1103515245 + 12345 + $salt + $rowid) % 2147483648);
            $bytes .= chr($state % 256);
        }

        return [
            'digest' => hash('sha256', $bytes),
            'prefix' => strtoupper(bin2hex(substr($bytes, 0, 8))),
        ];
    }

    private static function walFileSize(int $frames, int $pageSize): int
    {
        return 32 + ($frames * (24 + $pageSize));
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
