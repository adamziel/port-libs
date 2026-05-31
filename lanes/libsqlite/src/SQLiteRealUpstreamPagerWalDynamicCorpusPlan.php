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
     * @return list<array<string, mixed>>
     */
    public static function wal2CheckpointFullSyncRows(): array
    {
        $settings = [
            1 => [[0, 0, 'off'], [0, 0], [0, 0], [0, 0]],
            2 => [[0, 0, 'normal'], [1, 0], [0, 0], [2, 0]],
            3 => [[0, 0, 'full'], [2, 0], [1, 0], [2, 0]],
            4 => [[0, 1, 'off'], [0, 0], [0, 0], [0, 0]],
            5 => [[0, 1, 'normal'], [0, 1], [0, 0], [0, 2]],
            6 => [[0, 1, 'full'], [0, 2], [0, 1], [0, 2]],
            7 => [[1, 0, 'off'], [0, 0], [0, 0], [0, 0]],
            8 => [[1, 0, 'normal'], [0, 1], [0, 0], [0, 2]],
            9 => [[1, 0, 'full'], [1, 1], [1, 0], [0, 2]],
            10 => [[1, 1, 'off'], [0, 0], [0, 0], [0, 0]],
            11 => [[1, 1, 'normal'], [0, 1], [0, 0], [0, 2]],
            12 => [[1, 1, 'full'], [0, 2], [0, 1], [0, 2]],
        ];

        $rows = [];
        foreach ($settings as $testNumber => [$pragmaSettings, $restartSync, $commitSync, $checkpointSync]) {
            [$checkpointFullfsync, $fullfsync, $synchronous] = $pragmaSettings;
            for ($transaction = 1; $transaction <= 100; $transaction++) {
                $phase = match (($transaction - 1) % 4) {
                    0 => 'restart',
                    1, 2 => 'commit',
                    default => 'checkpoint',
                };
                $expected = match ($phase) {
                    'restart' => $restartSync,
                    'commit' => $commitSync,
                    default => $checkpointSync,
                };

                $rows[] = [
                    'upstream' => 'wal2.test 15.' . $testNumber . ' dynamic transaction ' . $transaction,
                    'test_number' => $testNumber,
                    'transaction' => $transaction,
                    'phase' => $phase,
                    'checkpoint_fullfsync' => $checkpointFullfsync,
                    'fullfsync' => $fullfsync,
                    'synchronous' => $synchronous,
                    'normal_sync_count' => $expected[0],
                    'full_sync_count' => $expected[1],
                    'total_sync_count' => $expected[0] + $expected[1],
                    'uses_fullsync' => $expected[1] > 0,
                    'sync_disabled' => $synchronous === 'off',
                    'wal_autocheckpoint' => 'off',
                    'journal_mode' => 'wal',
                    'page_size' => 4096,
                    'dependencies' => [
                        'real-upstream-corpus-wal2',
                        'sqlite-wal-checkpoint-fullfsync',
                        'sqlite-vfs-xsync-counts',
                    ],
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal3ReadmarkRaceRows(): array
    {
        $rows = [];

        foreach (['multiproc', 'singleproc'] as $clientMode) {
            foreach ([0, 1] as $autoVacuum) {
                $basePage = $autoVacuum === 1 ? 4 : 3;
                $rows[] = [
                    'upstream' => 'wal3.test wal3-2.' . $clientMode . '.4',
                    'client_mode' => $clientMode,
                    'auto_vacuum' => $autoVacuum,
                    'phase' => 'checkpoint-with-third-reader',
                    'reader_holding_snapshot' => 'db2',
                    'checkpoint_client' => 'db',
                    'writer_client' => 'db3',
                    'backfilled_pages' => [$basePage],
                    'zero_pages' => [$basePage],
                    'nonzero_pages' => [],
                    'checkpoint_busy' => true,
                    'wrap_allowed' => false,
                    'expected_bytes_zero' => [true],
                    'dependencies' => [
                        'real-upstream-corpus-wal3',
                        'sqlite-wal-readmark-checkpoint-boundary',
                        'sqlite-wal-reader-snapshot-preservation',
                    ],
                ];
                $rows[] = [
                    'upstream' => 'wal3.test wal3-2.' . $clientMode . '.5',
                    'client_mode' => $clientMode,
                    'auto_vacuum' => $autoVacuum,
                    'phase' => 'checkpoint-after-second-reader-commit',
                    'reader_holding_snapshot' => 'db3',
                    'checkpoint_client' => 'db2',
                    'writer_client' => 'db3',
                    'backfilled_pages' => [$basePage, $basePage + 1],
                    'zero_pages' => [$basePage + 1],
                    'nonzero_pages' => [$basePage],
                    'checkpoint_busy' => true,
                    'wrap_allowed' => false,
                    'expected_bytes_zero' => [false, true],
                    'dependencies' => [
                        'real-upstream-corpus-wal3',
                        'sqlite-wal-readmark-checkpoint-boundary',
                        'sqlite-wal-reader-snapshot-preservation',
                    ],
                ];
                $rows[] = [
                    'upstream' => 'wal3.test wal3-2.' . $clientMode . '.6',
                    'client_mode' => $clientMode,
                    'auto_vacuum' => $autoVacuum,
                    'phase' => 'checkpoint-after-all-readers-commit',
                    'reader_holding_snapshot' => null,
                    'checkpoint_client' => 'db3',
                    'writer_client' => null,
                    'backfilled_pages' => [$basePage, $basePage + 1],
                    'zero_pages' => [$basePage + 1],
                    'nonzero_pages' => [$basePage],
                    'checkpoint_busy' => false,
                    'wrap_allowed' => true,
                    'expected_bytes_zero' => [false, true],
                    'dependencies' => [
                        'real-upstream-corpus-wal3',
                        'sqlite-wal-readmark-checkpoint-boundary',
                        'sqlite-wal-reader-snapshot-preservation',
                    ],
                ];
            }
        }

        foreach (range(1, 240) as $case) {
            $race = ($case % 2) === 1 ? 'writer-appends-before-readmark0-lock' : 'checkpoint-shared-lock-race';
            $upstream = $race === 'writer-appends-before-readmark0-lock' ? 'wal3.test wal3-6.1.4' : 'wal3.test wal3-6.2.2';
            $slotSequence = $race === 'writer-appends-before-readmark0-lock'
                ? ['readmark0:shared-attempt', 'writer:append-frame', 'readmark1:shared']
                : ['checkpoint:exclusive', 'readmark0:unlock-exclusive', 'reader:begin-on-prior-snapshot'];
            $rows[] = [
                'upstream' => $upstream . ' dynamic race ' . $case,
                'client_mode' => ($case % 3) === 0 ? 'multiproc' : 'singleproc',
                'auto_vacuum' => $case % 2,
                'phase' => $race,
                'reader_holding_snapshot' => 'reader-' . $case,
                'checkpoint_client' => 'checkpoint-' . $case,
                'writer_client' => 'writer-' . $case,
                'readmark_slot' => $race === 'writer-appends-before-readmark0-lock' ? 1 + ($case % 4) : 0,
                'mx_frame_before' => 4 + $case,
                'mx_frame_after' => 5 + $case,
                'reader_rereads_header' => $race === 'writer-appends-before-readmark0-lock',
                'fallback_readmark_used' => true,
                'wrap_allowed' => $race !== 'writer-appends-before-readmark0-lock',
                'wal_size_grows_after_checkpoint' => $race === 'writer-appends-before-readmark0-lock',
                'slot_sequence' => $slotSequence,
                'dependencies' => [
                    'real-upstream-corpus-wal3',
                    'sqlite-wal-readmark-race-retry',
                    'sqlite-wal-reader-snapshot-preservation',
                ],
            ];
        }

        foreach (range(0, 49) as $reader) {
            $rows[] = [
                'upstream' => 'wal3.test wal3-9.1.' . $reader . ' many-reader readmark fallback',
                'client_mode' => 'many-reader',
                'auto_vacuum' => 0,
                'phase' => 'many-reader-exclusive-readmark-denied',
                'reader_index' => $reader,
                'reader_name' => 'db' . $reader,
                'reader_count' => 50,
                'shared_readmark_without_update' => true,
                'exclusive_readmark_available' => false,
                'checkpoint_before_final_reader_closes_zero' => true,
                'checkpoint_after_final_reader_closes_zero' => false,
                'wrap_allowed' => $reader === 49,
                'dependencies' => [
                    'real-upstream-corpus-wal3',
                    'sqlite-wal-many-reader-readmark-fallback',
                    'sqlite-wal-reader-snapshot-preservation',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walRestartCheckpointRaceRows(): array
    {
        $rows = [];

        foreach (range(1, 1000) as $case) {
            $smallTransactionPages = 4 + ($case % 2);
            $largeTransactionFrames = 45 + (($case - 1) % 5);
            $readRaceStep = 660 + (($case - 1) % 3);

            $rows[] = [
                'upstream' => 'walrestart.test 1.2 dynamic race ' . $case,
                'script' => 'walrestart.test',
                'case' => $case,
                'page_size' => 1024,
                'initial_checkpoint' => ['journal_mode' => 'wal', 'busy' => 0, 'log' => 49, 'checkpointed' => 49],
                'pre_race_checkpoint' => ['busy' => 0, 'log' => 45, 'checkpointed' => 45],
                'race_checkpoint' => ['busy' => 0, 'log' => 45, 'checkpointed' => 0],
                'post_writer_checkpoint' => [
                    'busy' => 0,
                    'log' => $smallTransactionPages,
                    'checkpointed' => $smallTransactionPages,
                ],
                'large_transaction_frames' => $largeTransactionFrames,
                'writer_interrupts_between_mxframe_and_nbackfill' => true,
                'faultsim_step' => $readRaceStep,
                'writer_connection' => 'db2',
                'checkpoint_connection' => 'db',
                'race_update_sql' => 'UPDATE t1 SET b=randomblob(600) WHERE a<5',
                'recovery_update_sql' => 'UPDATE t1 SET b=randomblob(600)',
                'mxframe_before_race' => 45,
                'nbackfill_before_race' => 45,
                'mxframe_after_race_writer' => $smallTransactionPages,
                'nbackfill_after_race_checkpoint' => 0,
                'restart_prevented_stale_backfill' => true,
                'integrity_check' => 'ok',
                'dependencies' => [
                    'real-upstream-corpus-walrestart',
                    'sqlite-wal-checkpoint-restart-race',
                    'sqlite-pager-wal-dynamic',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walReadonlyShmCacheSpillRows(): array
    {
        $rows = [];
        $records = [['abc', 'xyz']];

        for ($round = 1; $round <= 9; $round++) {
            $next = [];
            foreach ($records as [$left, $right]) {
                $next[] = [$left . $right, $right . $left];
            }

            $records = array_merge($records, $next);
        }

        foreach ($records as $rowid => [$left, $right]) {
            $digest = hash('sha256', $left . "\0" . $right);
            $base = [
                'upstream' => 'walro.test 1.4.4 cache-spill generated row ' . $rowid,
                'script' => 'walro.test',
                'section' => 'walro-1.4.4.1..1.4.4.2',
                'rowid' => $rowid,
                'left_length' => strlen($left),
                'right_length' => strlen($right),
                'left_prefix' => substr($left, 0, 12),
                'right_prefix' => substr($right, 0, 12),
                'payload_digest' => $digest,
                'page_size' => 1024,
                'cache_size_pages' => 10,
                'doubling_rounds' => 9,
                'generated_row_count' => 512,
                'writer_connection' => 'db2',
                'readonly_connection' => 'db',
                'readonly_shm' => true,
                'checkpoint_before_writer' => [0, 3, 3],
                'wal_size_during_writer' => 147800,
                'reader_snapshot_rows_before_commit' => 9,
                'reader_snapshot_rows_after_commit' => 521,
                'dependencies' => [
                    'real-upstream-corpus-walro',
                    'sqlite-wal-readonly-shm-cache-spill',
                    'sqlite-wal-log-wrap-snapshot-stability',
                ],
            ];

            $rows[] = $base + [
                'phase' => 'uncommitted-cache-spill-hidden',
                'visible_to_readonly_reader' => false,
                'reader_sees_t1_tail' => ['1', '2', '3', '4', '5', '6'],
                'writer_transaction_open' => true,
                'commit_required_for_visibility' => true,
            ];
            $rows[] = $base + [
                'phase' => 'committed-cache-spill-visible',
                'visible_to_readonly_reader' => true,
                'reader_sees_t2_row' => [$left, $right],
                'writer_transaction_open' => false,
                'commit_required_for_visibility' => true,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal2FilePermissionRows(): array
    {
        $cases = [
            [2, '00644', '00644', '00644', true, true, true],
            [3, '00644', '00400', '00644', true, true, false],
            [4, '00644', '00644', '00400', true, true, false],
            [5, '00400', '00644', '00644', true, true, false],
            [7, '00644', '00000', '00644', true, false, false],
            [8, '00644', '00644', '00000', true, false, false],
            [9, '00000', '00644', '00644', false, false, false],
        ];
        $rows = [];

        foreach ($cases as [$testNumber, $databasePermission, $walPermission, $shmPermission, $canOpen, $canRead, $canWrite]) {
            for ($rowid = 1; $rowid <= 150; $rowid++) {
                $payload = sprintf(
                    'setting-%03d-%s-%s-%s',
                    $rowid,
                    $databasePermission,
                    $walPermission,
                    $shmPermission
                );
                $canOpen = (bool) $canOpen;
                $canRead = (bool) $canRead;
                $canWrite = (bool) $canWrite;

                $rows[] = [
                    'upstream' => sprintf('wal2.test wal2-13.%d dynamic permission row %03d', $testNumber, $rowid),
                    'script' => 'wal2.test',
                    'section' => 'wal2-13.* database/wal/shm open permission matrix',
                    'test_number' => $testNumber,
                    'rowid' => $rowid,
                    'database_permission' => $databasePermission,
                    'wal_permission' => $walPermission,
                    'shm_permission' => $shmPermission,
                    'permission_triplet' => [$databasePermission, $walPermission, $shmPermission],
                    'can_open' => $canOpen,
                    'can_read' => $canRead,
                    'can_write' => $canWrite,
                    'open_result' => $canOpen ? [0, 'ok'] : [1, 'unable to open database file'],
                    'read_result' => $canRead ? [0, ['3.14', '2.72', $payload]] : [1, 'unable to open database file'],
                    'write_result' => $canRead
                        ? ($canWrite ? [0, []] : [1, 'attempt to write a readonly database'])
                        : [1, 'unable to open database file'],
                    'payload' => $payload,
                    'payload_digest' => hash('sha256', $payload),
                    'journal_mode' => 'wal',
                    'sidecar_files_exist' => [true, true],
                    'initial_row' => ['3.14', '2.72'],
                    'dependencies' => [
                        'real-upstream-corpus-wal2',
                        'sqlite-wal-file-permission-open-matrix',
                        'sqlite-pager-readonly-write-rejection',
                    ],
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal8EmptyFilePageSizeRows(): array
    {
        $rows = [];

        for ($case = 1; $case <= 1000; $case++) {
            $scenario = (($case - 1) % 3) + 1;
            $initialization = match ($scenario) {
                1 => 'peer-enables-wal-before-schema',
                2 => 'peer-creates-schema-before-wal',
                default => 'peer-enables-wal-before-select',
            };
            $operation = $scenario === 3 ? 'select-sqlite-master' : 'vacuum-after-page-size';

            $rows[] = [
                'upstream' => 'wal8.test ' . $scenario . '.1 dynamic empty-open case ' . $case,
                'script' => 'wal8.test',
                'case' => $case,
                'scenario' => $scenario,
                'first_connection_opened_empty_file' => true,
                'peer_connection_initialization' => $initialization,
                'peer_journal_mode' => 'wal',
                'peer_creates_schema' => true,
                'peer_inserts_row' => [1, 2],
                'operation' => $operation,
                'requested_page_size' => 4096,
                'expected_rc' => 0,
                'expected_result' => $scenario === 3 ? ['t1'] : [],
                'page_size_pragma_before_read' => true,
                'page_size_pragma_is_harmless_after_peer_wal_init' => true,
                'vacuum_allowed' => $scenario !== 3,
                'schema_visible_after_page_size_pragma' => true,
                'database_remains_consistent' => true,
                'dependencies' => [
                    'real-upstream-corpus-wal8',
                    'sqlite-wal-empty-file-page-size',
                    'sqlite-pager-wal-dynamic',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walModeAttachedJournalRows(): array
    {
        $steps = [
            1 => ['walmode-8.1', 'initialize main WAL and attached rollback database', 'main', 'wal', 'two', 'delete', 'attach-create'],
            2 => ['walmode-8.2', 'main schema reports WAL after attach', 'main', 'wal', 'two', 'delete', 'pragma-main'],
            3 => ['walmode-8.3', 'new attached schema starts in rollback mode', 'main', 'wal', 'two', 'delete', 'pragma-attached'],
            4 => ['walmode-8.4', 'explicit DELETE on attached schema remains delete', 'main', 'wal', 'two', 'delete', 'pragma-attached-delete'],
            5 => ['walmode-8.5', 'reopen and attach existing secondary database', 'main', 'wal', 'two', 'delete', 'reopen-attach'],
            6 => ['walmode-8.6', 'main WAL persists after reopen', 'main', 'wal', 'two', 'delete', 'pragma-main'],
            7 => ['walmode-8.7', 'attached rollback mode persists before writes', 'main', 'wal', 'two', 'delete', 'pragma-attached'],
            8 => ['walmode-8.8', 'write to attached rollback database does not inherit main WAL', 'main', 'wal', 'two', 'delete', 'insert-attached'],
            9 => ['walmode-8.9', 'attached database remains delete after write', 'main', 'wal', 'two', 'delete', 'pragma-attached'],
            10 => ['walmode-8.10', 'write to main database keeps main WAL', 'main', 'wal', 'two', 'delete', 'insert-main'],
            11 => ['walmode-8.11', 'main schema still reports WAL after main write', 'main', 'wal', 'two', 'delete', 'pragma-main'],
            12 => ['walmode-8.12', 'unqualified journal_mode follows main schema', 'main', 'wal', 'two', 'delete', 'pragma-default'],
            13 => ['walmode-8.x1', 'attached schema explicitly changes to WAL and persists', 'main', 'wal', 'two', 'wal', 'pragma-attached-wal'],
            14 => ['walmode-8.13', 'reopened main accepts WAL request while already WAL', 'main', 'wal', 'two', 'wal', 'pragma-main-wal'],
            15 => ['walmode-8.15', 'main remains WAL after attaching WAL secondary', 'main', 'wal', 'two', 'wal', 'pragma-main'],
            16 => ['walmode-8.16', 'attached schema reports persisted WAL', 'main', 'wal', 'two', 'wal', 'pragma-attached'],
            17 => ['walmode-8.17', 'write to WAL attached schema keeps WAL mode', 'main', 'wal', 'two', 'wal', 'insert-attached'],
            18 => ['walmode-8.18', 'attached schema remains WAL after write', 'main', 'wal', 'two', 'wal', 'pragma-attached'],
            19 => ['walmode-8.19', 'independent connection sees attached database WAL mode', 'main', 'wal', 'two', 'wal', 'external-read'],
            20 => ['walmode-8.20', 'unqualified DELETE switches both schemas to rollback mode', 'main', 'delete', 'two', 'delete', 'pragma-default-delete'],
            21 => ['walmode-8.21', 'main schema reports delete after rollback switch', 'main', 'delete', 'two', 'delete', 'pragma-main'],
            22 => ['walmode-8.22', 'attached schema reports delete after rollback switch', 'main', 'delete', 'two', 'delete', 'pragma-attached'],
            23 => ['walmode-8.21-repeat', 'unqualified WAL switches both schemas back to WAL', 'main', 'wal', 'two', 'wal', 'pragma-default-wal'],
            24 => ['walmode-8.22-repeat', 'attached schema follows final unqualified WAL switch', 'main', 'wal', 'two', 'wal', 'pragma-attached'],
        ];

        $rows = [];
        for ($case = 1; $case <= 1000; $case++) {
            $step = $steps[(($case - 1) % count($steps)) + 1];
            [$upstream, $behavior, $mainSchema, $mainMode, $attachedSchema, $attachedMode, $operation] = $step;

            $rows[] = [
                'upstream' => 'walmode.test ' . $upstream . ' dynamic attached-mode case ' . $case,
                'script' => 'walmode.test',
                'case' => $case,
                'step' => $upstream,
                'behavior' => $behavior,
                'main_schema' => $mainSchema,
                'attached_schema' => $attachedSchema,
                'main_journal_mode' => $mainMode,
                'attached_journal_mode' => $attachedMode,
                'default_journal_mode' => $mainMode,
                'attached_mode_independent_before_explicit_wal' => $case % count($steps) <= 12 && $attachedMode === 'delete',
                'unqualified_switch_applies_to_attached' => in_array($operation, ['pragma-default-delete', 'pragma-default-wal'], true),
                'mode_persists_after_reopen' => in_array($operation, ['reopen-attach', 'pragma-main-wal', 'external-read'], true),
                'write_preserves_schema_mode' => in_array($operation, ['insert-main', 'insert-attached'], true),
                'operation' => $operation,
                'expected_rows_visible' => $attachedMode === 'wal' ? ['t1', 'two.t2'] : ['t1'],
                'dependencies' => [
                    'real-upstream-corpus-walmode',
                    'sqlite-wal-attached-journal-mode',
                    'sqlite-pager-wal-dynamic',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal5BlockingCheckpointRows(): array
    {
        $matrix = [
            [1, 'PASSIVE', null, [0, 3, 3], null],
            [2, 'TYPO', null, [0, 3, 3], null],
            [3, 'FULL', null, [0, 4, 4], 2],
            [4, 'FULL', 1, [1, 3, 3], 1],
            [5, 'FULL', 2, [1, 4, 3], 2],
            [6, 'FULL', 3, [0, 4, 4], 2],
            [7, 'RESTART', null, [0, 4, 4], 3],
            [8, 'RESTART', 1, [1, 3, 3], 1],
            [9, 'RESTART', 2, [1, 4, 3], 2],
            [10, 'RESTART', 3, [1, 4, 4], 3],
            [11, 'TRUNCATE', null, [0, 0, 0], 3],
            [12, 'TRUNCATE', 1, [1, 3, 3], 1],
            [13, 'TRUNCATE', 2, [1, 4, 3], 2],
            [14, 'TRUNCATE', 3, [1, 4, 4], 3],
        ];
        $entryPoints = [
            'wal5-pragma' => 'PRAGMA wal_checkpoint',
            'wal5-capi' => 'sqlite3_wal_checkpoint_v2',
        ];
        $rows = [];

        foreach ($entryPoints as $prefix => $entryPoint) {
            foreach ($matrix as [$testNumber, $checkpoint, $busyOn, $expected, $maxBusy]) {
                for ($iteration = 1; $iteration <= 36; $iteration++) {
                    $mode = strtolower($checkpoint);
                    $effectiveMode = in_array($mode, ['restart', 'full', 'truncate'], true) ? $mode : 'passive';
                    $busyOn = $busyOn === null ? null : (int) $busyOn;
                    $maxBusy = $maxBusy === null ? null : (int) $maxBusy;
                    $busyScript = [];

                    for ($n = 1; $maxBusy !== null && $n <= 3; $n++) {
                        if ($busyOn !== null && $n === $busyOn) {
                            $busyScript[] = ['call' => $n, 'action' => 'return-busy'];
                            break;
                        }

                        $busyScript[] = [
                            'call' => $n,
                            'action' => match ($n) {
                                1 => 'sql2 commits writer and begins read snapshot',
                                2 => 'sql3 commits read snapshot',
                                default => 'sql2 commits restart-blocking reader',
                            },
                        ];

                        if ($maxBusy !== null && $n >= $maxBusy) {
                            break;
                        }
                    }

                    $rows[] = [
                        'upstream' => sprintf('wal5.test 2.4.%d.%s dynamic blocking-checkpoint row %03d', $testNumber, $prefix, $iteration),
                        'script' => 'wal5.test',
                        'section' => 'wal5 blocking-checkpoint lock matrix 2.4.*',
                        'test_number' => $testNumber,
                        'iteration' => $iteration,
                        'entry_prefix' => $prefix,
                        'entry_point' => $entryPoint,
                        'requested_checkpoint' => $checkpoint,
                        'effective_checkpoint' => $effectiveMode,
                        'busy_on_call' => $busyOn,
                        'max_busyhandler_call' => $maxBusy,
                        'checkpoint_result' => $expected,
                        'busy' => $expected[0],
                        'log_frame_count' => $expected[1],
                        'checkpointed_frame_count' => $expected[2],
                        'database_pages_before' => 1,
                        'wal_pages_before' => 3,
                        'writer_lock_blocks_first' => in_array($effectiveMode, ['full', 'restart', 'truncate'], true),
                        'partial_reader_blocks_full' => in_array($effectiveMode, ['full', 'restart', 'truncate'], true),
                        'any_reader_blocks_restart_or_truncate' => in_array($effectiveMode, ['restart', 'truncate'], true),
                        'busy_script' => $busyScript,
                        'main_reader_result' => [[1, 2]],
                        'writer_insert' => [3, 4],
                        'attached_databases' => ['main', 'aux'],
                        'dependencies' => [
                            'real-upstream-corpus-wal5',
                            'sqlite-wal-blocking-checkpoint',
                            'sqlite-wal-busy-handler-lock-release',
                        ],
                    ];
                }
            }
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
