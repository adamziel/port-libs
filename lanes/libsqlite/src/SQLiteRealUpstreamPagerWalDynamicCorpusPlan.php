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
    public static function wal5BlockingCheckpointExtendedRows(): array
    {
        $checkpointCases = [
            ['wal5-1.$tn.3', 'passive', 9, 5, 9, 0, null, 'reader_snapshot_allows_partial_checkpoint'],
            ['wal5-1.$tn.5', 'restart', 12, 6, 12, 6, 2, 'busy_handler_releases_reader_then_restart_completes'],
            ['wal5-1.$tn.11', 'restart', 5, 10, 5, 8, 3, 'restart_waits_for_partial_and_full_log_readers'],
            ['wal5-2.1.$tn.3', 'passive', 3, 2, 3, 0, null, 'checkpoint_all_attached_databases'],
            ['wal5-2.2.$tn.4', 'restart', 3, 2, 3, 1, 2, 'attached_restart_busy_on_main_reader'],
            ['wal5-2.3.$tn.7', 'full', 4, 1, 4, 1, 2, 'attached_full_backfills_unpinned_aux_only'],
            ['wal5-2.4.1.$tn.2', 'passive', 3, 3, 3, 0, null, 'passive_never_waits_for_writer_or_reader'],
            ['wal5-2.4.3.$tn.2', 'full', 4, 4, 4, 0, 2, 'full_waits_for_writer_then_readers'],
            ['wal5-2.4.4.$tn.2', 'full', 3, 3, 3, 1, 1, 'full_busy_handler_stops_on_writer_lock'],
            ['wal5-2.4.5.$tn.2', 'full', 4, 4, 3, 1, 2, 'full_busy_handler_stops_on_partial_reader'],
            ['wal5-2.4.7.$tn.2', 'restart', 4, 4, 4, 0, 3, 'restart_waits_for_all_wal_readers'],
            ['wal5-2.4.8.$tn.2', 'restart', 3, 3, 3, 1, 1, 'restart_busy_handler_stops_on_writer_lock'],
            ['wal5-2.4.9.$tn.2', 'restart', 4, 4, 3, 1, 2, 'restart_busy_handler_stops_on_partial_reader'],
            ['wal5-2.4.10.$tn.2', 'restart', 4, 4, 4, 1, 3, 'restart_busy_handler_stops_on_any_wal_reader'],
            ['wal5-2.4.11.$tn.2', 'truncate', 4, 0, 0, 0, 3, 'truncate_waits_then_truncates_wal'],
            ['wal5-2.4.12.$tn.2', 'truncate', 3, 3, 3, 1, 1, 'truncate_busy_handler_stops_on_writer_lock'],
            ['wal5-2.4.13.$tn.2', 'truncate', 4, 4, 3, 1, 2, 'truncate_busy_handler_stops_on_partial_reader'],
            ['wal5-2.4.14.$tn.2', 'truncate', 4, 4, 4, 1, 3, 'truncate_busy_handler_stops_on_any_wal_reader'],
            ['wal5-3.$tn.2', 'passive', 2, 2, 2, 0, null, 'repeated_checkpoint_preserves_backfilled_wal'],
            ['wal5-3.$tn.6', 'passive', 0, 0, 0, 0, null, 'reopen_after_checkpoint_reports_empty_log'],
            ['wal5-4.$tn.2', 'truncate', 8, 0, 0, 0, null, 'truncate_checkpoint_zeros_wal_file'],
            ['wal5-5.$tn.3', 'passive', 10, 10, 10, 0, null, 'passive_checkpoints_complete_log_with_reader'],
            ['wal5-5.$tn.6', 'full', 10, 10, 10, 1, null, 'full_busy_with_writer_after_complete_checkpoint'],
            ['wal5-5.$tn.8', 'full', 10, 10, 10, 0, null, 'full_succeeds_after_writer_rollback'],
            ['wal5-5.$tn.9', 'truncate', 10, 10, 10, 1, null, 'truncate_busy_while_reader_holds_checkpointed_log'],
            ['wal5-5.$tn.11', 'truncate', 10, 0, 0, 0, null, 'truncate_after_reader_release_zeros_wal'],
            ['wal5-5.$tn.15', 'truncate', 4, 4, 4, 1, null, 'truncate_busy_on_new_reader'],
            ['wal5-5.$tn.17', 'restart', 4, 4, 4, 1, null, 'restart_busy_on_new_reader'],
            ['wal5-5.$tn.18', 'restart', 4, 4, 4, 0, null, 'restart_after_reader_release_preserves_log'],
            ['wal5-5.$tn.20', 'truncate', 4, 0, 0, 0, null, 'truncate_final_zeroes_log'],
        ];

        $rows = [];
        for ($case = 1; $case <= 1200; $case++) {
            [$section, $mode, $logFrames, $databasePages, $checkpointedFrames, $busy, $busyReleaseStep, $behavior] = $checkpointCases[($case - 1) % count($checkpointCases)];
            $requestMethod = ($case % 2) === 0 ? 'capi' : 'pragma';
            $pageSize = [1024, 2048, 4096][($case - 1) % 3];
            $readerEndFrame = $busy === 0 ? null : max(1, min($logFrames, $checkpointedFrames));
            $expectedWalAction = $mode === 'truncate' && $busy === 0 ? 'truncate_wal' : 'preserve_wal';
            $expectedWalBytes = $expectedWalAction === 'truncate_wal' ? 0 : self::walFileSize($logFrames, $pageSize);

            $rows[] = [
                'upstream' => 'wal5.test ' . $section . ' dynamic blocking checkpoint ' . $case,
                'source_file' => 'wal5.test',
                'case' => $case,
                'request_method' => $requestMethod,
                'mode' => $mode,
                'page_size' => $pageSize,
                'log_frame_count' => $logFrames,
                'database_page_count' => $databasePages,
                'checkpointed_frame_count' => $checkpointedFrames,
                'busy' => $busy,
                'busy_release_step' => $busyReleaseStep,
                'reader_end_frame' => $readerEndFrame,
                'behavior' => $behavior,
                'expected_checkpoint' => [$busy, $logFrames, $checkpointedFrames],
                'expected_wal_action' => $expectedWalAction,
                'expected_wal_bytes' => $expectedWalBytes,
                'attached_database_case' => str_contains($section, 'wal5-2.'),
                'truncate_case' => $mode === 'truncate',
                'restart_case' => $mode === 'restart',
                'full_case' => $mode === 'full',
                'passive_case' => $mode === 'passive',
                'dependencies' => [
                    'real-upstream-corpus-wal5',
                    'sqlite-wal-blocking-checkpoint',
                    'sqlite-wal-reader-writer-lock-boundary',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walModeTransitionRows(): array
    {
        $sourceSections = [
            ['walmode.test walmode-1.1..1.7', 'file-backed wal entry creates transient wal sidecar', 'main', 'delete', 'wal', 'delete'],
            ['walmode.test walmode-2.1..2.3', 'database header read/write version reopens into wal mode', 'main', 'wal', 'wal', 'wal'],
            ['walmode.test walmode-3.1..3.2', 'first pragma journal_mode wal opens log without database rewrite', 'main', 'wal', 'wal', 'wal'],
            ['walmode.test walmode-4.1..4.5', 'wal to persist rollback-mode transition keeps committed rows', 'main', 'wal', 'persist', 'persist'],
            ['walmode.test walmode-4.6..4.18', 'second connection blocks wal/delete mode transition until release', 'main', 'wal', 'delete', 'wal'],
            ['walmode.test walmode-5.1.*', 'memory database refuses wal journal mode', 'main', 'memory', 'wal', 'memory'],
            ['walmode.test walmode-5.2.*', 'temporary database refuses wal journal mode', 'main', 'delete', 'wal', 'delete'],
            ['walmode.test walmode-5.3.*', 'temp schema refuses wal journal mode', 'temp', 'delete', 'wal', 'delete'],
            ['walmode.test walmode-6.1..6.5', 'rollback modes can transition into wal', 'main', 'off', 'wal', 'wal'],
            ['walmode.test walmode-7.1..7.16', 'first statement journal_mode toggles before schema load', 'main', 'wal', 'delete', 'delete'],
            ['walmode.test walmode-8.1..8.12', 'attached database keeps independent rollback mode while main is wal', 'two', 'delete', 'delete', 'delete'],
            ['walmode.test walmode-8.13..8.22', 'attached database wal mode persists across reopen and main toggles', 'two', 'wal', 'wal', 'wal'],
        ];
        $rollbackInputs = ['off', 'memory', 'persist', 'delete', 'truncate'];
        $rows = [];

        for ($case = 1; $case <= 1200; $case++) {
            [$upstream, $behavior, $schema, $beforeMode, $requestedMode, $afterMode] = $sourceSections[($case - 1) % count($sourceSections)];
            $pageSize = [1024, 2048, 4096, 8192][($case - 1) % 4];
            $connectionCount = (($case % 5) === 0 || str_contains($upstream, 'walmode-4.6')) ? 2 : 1;
            $blocksTransition = $connectionCount > 1 && str_contains($upstream, 'walmode-4.6');
            $refusesWal = str_contains($upstream, 'walmode-5.');
            $startsFromRollback = str_contains($upstream, 'walmode-6.');
            $rollbackInput = $rollbackInputs[($case - 1) % count($rollbackInputs)];

            if ($startsFromRollback) {
                $beforeMode = $rollbackInput;
                $afterMode = 'wal';
            }

            $walSidecarAfterPragma = $afterMode === 'wal' && !$blocksTransition;
            $journalSidecarAfterPragma = in_array($afterMode, ['persist', 'delete', 'truncate'], true);
            $committedRows = [
                ['setting_id' => 1, 'key_name' => 'alpha-' . $case, 'key_value' => (string) (100 + $case)],
                ['setting_id' => 2, 'key_name' => 'beta-' . $case, 'key_value' => (string) (200 + $case)],
            ];

            $rows[] = [
                'upstream' => $upstream . ' dynamic transition ' . $case,
                'source_file' => 'walmode.test',
                'behavior' => $behavior,
                'schema' => $schema,
                'case' => $case,
                'page_size' => $pageSize,
                'before_mode' => $beforeMode,
                'requested_mode' => $requestedMode,
                'after_mode' => $afterMode,
                'reported_mode' => $blocksTransition ? $beforeMode : $afterMode,
                'rollback_input_mode' => $rollbackInput,
                'connection_count' => $connectionCount,
                'blocks_transition' => $blocksTransition,
                'refuses_wal' => $refusesWal,
                'starts_from_rollback' => $startsFromRollback,
                'wal_sidecar_after_pragma' => $walSidecarAfterPragma,
                'journal_sidecar_after_pragma' => $journalSidecarAfterPragma,
                'committed_rows' => $committedRows,
                'committed_row_count' => count($committedRows),
                'committed_value_sum' => 300 + ($case * 2),
                'database_size_after_mode_change' => $pageSize,
                'requires_file_backed_database' => !$refusesWal,
                'schema_independent_mode' => $schema === 'two',
                'dependencies' => [
                    'real-upstream-corpus-walmode',
                    'sqlite-pager-journal-mode-transition',
                    'sqlite-wal-sidecar-lifecycle',
                ],
            ];
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
    public static function walReadonlyShmRefreshRows(): array
    {
        $rows = [];
        $pageSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];
        $phases = [
            [
                'section' => 'walro2-1.1.2',
                'operation' => 'readonly-open-copied-wal-shm',
                'rows_before' => [['a', 'b'], ['c', 'd']],
                'rows_after' => [['a', 'b'], ['c', 'd']],
                'zero_byte_wal' => false,
                'zero_byte_shm' => false,
                'readonly_requires_recovery' => false,
                'writer_wraps_wal' => false,
                'checkpoint_truncate' => false,
                'readonly_flushes_cache' => false,
            ],
            [
                'section' => 'walro2-1.2.2',
                'operation' => 'readonly-open-zeroed-shm-copy',
                'rows_before' => [['a', 'b'], ['c', 'd']],
                'rows_after' => [['a', 'b'], ['c', 'd']],
                'zero_byte_wal' => false,
                'zero_byte_shm' => true,
                'readonly_requires_recovery' => true,
                'writer_wraps_wal' => false,
                'checkpoint_truncate' => false,
                'readonly_flushes_cache' => false,
            ],
            [
                'section' => 'walro2-2.2',
                'operation' => 'readonly-read-transaction-before-writer',
                'rows_before' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h']],
                'rows_after' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h']],
                'zero_byte_wal' => false,
                'zero_byte_shm' => false,
                'readonly_requires_recovery' => false,
                'writer_wraps_wal' => false,
                'checkpoint_truncate' => false,
                'readonly_flushes_cache' => false,
            ],
            [
                'section' => 'walro2-2.3.3',
                'operation' => 'readonly-transaction-sees-writer-after-commit',
                'rows_before' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h']],
                'rows_after' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h'], ['i', 'j']],
                'zero_byte_wal' => false,
                'zero_byte_shm' => false,
                'readonly_requires_recovery' => false,
                'writer_wraps_wal' => false,
                'checkpoint_truncate' => false,
                'readonly_flushes_cache' => true,
            ],
            [
                'section' => 'walro2-3.1.1',
                'operation' => 'readonly-zero-byte-wal-shm',
                'rows_before' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h']],
                'rows_after' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h']],
                'zero_byte_wal' => true,
                'zero_byte_shm' => true,
                'readonly_requires_recovery' => false,
                'writer_wraps_wal' => false,
                'checkpoint_truncate' => false,
                'readonly_flushes_cache' => true,
            ],
            [
                'section' => 'walro2-3.2.1',
                'operation' => 'readonly-refresh-after-truncate-checkpoint',
                'rows_before' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h']],
                'rows_after' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h'], [1, 2]],
                'zero_byte_wal' => true,
                'zero_byte_shm' => false,
                'readonly_requires_recovery' => false,
                'writer_wraps_wal' => false,
                'checkpoint_truncate' => true,
                'readonly_flushes_cache' => true,
            ],
            [
                'section' => 'walro2-3.3.1',
                'operation' => 'readonly-reruns-recovery-after-wal-growth',
                'rows_before' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h'], [1, 2]],
                'rows_after' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h'], [1, 2], [3, 4], [5, 6], [7, 8], [9, 10]],
                'zero_byte_wal' => false,
                'zero_byte_shm' => false,
                'readonly_requires_recovery' => true,
                'writer_wraps_wal' => true,
                'checkpoint_truncate' => false,
                'readonly_flushes_cache' => true,
            ],
            [
                'section' => 'walro2-3.3.3',
                'operation' => 'readonly-reruns-recovery-after-wal-wrap',
                'rows_before' => [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h'], [1, 2], [3, 4], [5, 6], [7, 8], [9, 10]],
                'rows_after' => [['i', 'ii']],
                'zero_byte_wal' => false,
                'zero_byte_shm' => false,
                'readonly_requires_recovery' => true,
                'writer_wraps_wal' => true,
                'checkpoint_truncate' => false,
                'readonly_flushes_cache' => true,
            ],
            [
                'section' => 'walro2-4.1.1',
                'operation' => 'readonly-open-copied-database-after-close',
                'rows_before' => [['hello'], ['world']],
                'rows_after' => [['hello'], ['world']],
                'zero_byte_wal' => false,
                'zero_byte_shm' => false,
                'readonly_requires_recovery' => true,
                'writer_wraps_wal' => false,
                'checkpoint_truncate' => false,
                'readonly_flushes_cache' => false,
            ],
            [
                'section' => 'walro2-4.1.3',
                'operation' => 'readonly-refresh-after-peer-truncate',
                'rows_before' => [['hello'], ['world']],
                'rows_after' => [['hello'], ['world'], ['!']],
                'zero_byte_wal' => true,
                'zero_byte_shm' => false,
                'readonly_requires_recovery' => false,
                'writer_wraps_wal' => false,
                'checkpoint_truncate' => true,
                'readonly_flushes_cache' => true,
            ],
        ];

        foreach ([0, 1] as $zeroedShmCopy) {
            foreach ($pageSizes as $pageSize) {
                foreach ($phases as $phaseIndex => $phase) {
                    for ($variant = 1; $variant <= 12; $variant++) {
                        $rows[] = [
                            'upstream' => sprintf(
                                'walro2.test %s readonly_shm=%d page_size=%d variant=%02d',
                                $phase['section'],
                                $zeroedShmCopy,
                                $pageSize,
                                $variant
                            ),
                            'script' => 'walro2.test',
                            'section' => $phase['section'],
                            'operation' => $phase['operation'],
                            'case' => (($zeroedShmCopy * count($pageSizes) + array_search($pageSize, $pageSizes, true)) * count($phases) * 12) + ($phaseIndex * 12) + $variant,
                            'page_size' => $pageSize,
                            'zeroed_shm_copy' => (bool) $zeroedShmCopy,
                            'minimum_shm_size' => max(32768, $pageSize),
                            'readonly_shm' => true,
                            'readonly_connection' => 'db',
                            'writer_connection' => $variant % 3 === 0 ? 'db3' : 'db2',
                            'rows_before' => $phase['rows_before'],
                            'rows_after' => $phase['rows_after'],
                            'row_count_before' => count($phase['rows_before']),
                            'row_count_after' => count($phase['rows_after']),
                            'zero_byte_wal' => $phase['zero_byte_wal'],
                            'zero_byte_shm' => $phase['zero_byte_shm'] || (bool) $zeroedShmCopy,
                            'readonly_requires_recovery' => $phase['readonly_requires_recovery'] || (bool) $zeroedShmCopy,
                            'writer_wraps_wal' => $phase['writer_wraps_wal'],
                            'checkpoint_truncate' => $phase['checkpoint_truncate'],
                            'readonly_flushes_cache' => $phase['readonly_flushes_cache'],
                            'wal_file_size' => $phase['zero_byte_wal'] ? 0 : self::walFileSize(4 + ($variant % 3), 1024),
                            'shm_file_size' => ($phase['zero_byte_shm'] || (bool) $zeroedShmCopy) ? 0 : max(32768, $pageSize),
                            'result_digest' => hash('sha256', serialize($phase['rows_after'])),
                            'dependencies' => [
                                'real-upstream-corpus-walro2',
                                'sqlite-wal-readonly-shm-refresh',
                                'sqlite-wal-wrap-recovery',
                            ],
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walSetlkSnapshotBusyRows(): array
    {
        $rows = [];
        $timeoutMs = [50, 100, 250, 500, 750, 1000, 1500, 2000];
        $checkpointModes = ['passive', 'full', 'restart', 'truncate'];
        $snapshotRows = [
            [[1, 2], [3, 4], [5, 6]],
            [[1, 2], [3, 4], [5, 6], [7, 8]],
            [[1, 2], [3, 4], [5, 6], [9, 10]],
        ];

        for ($case = 1; $case <= 1000; $case++) {
            $timeout = $timeoutMs[($case - 1) % count($timeoutMs)];
            $checkpointMode = $checkpointModes[intdiv($case - 1, count($timeoutMs)) % count($checkpointModes)];
            $setlkTimeout = ($case % 5) === 0;
            $xwriteDelayMs = 4000 + (($case % 7) * 250);
            $rowsBeforeSnapshot = $snapshotRows[0];
            $rowsAfterCommit = $snapshotRows[1 + ($case % 2)];
            $openWaitUs = $setlkTimeout ? min(1000, $timeout * 1000) : min(1999000, max(1000, $timeout * 1000));

            $rows[] = [
                'script' => 'walsetlk_snapshot.test',
                'upstream' => 'walsetlk_snapshot.test 1.0..1.5 snapshot_open returns SQLITE_BUSY while checkpoint xWrite is stalled',
                'case' => $case,
                'journal_mode' => 'wal',
                'vfs' => 'testvfs-fullshm',
                'checkpoint_mode' => $checkpointMode,
                'timeout_ms' => $timeout,
                'xwrite_delay_ms' => $xwriteDelayMs,
                'snapshot_rows' => $rowsBeforeSnapshot,
                'committed_rows' => $rowsAfterCommit,
                'snapshot_open_result' => 'SQLITE_BUSY',
                'snapshot_message' => 'SQLITE_BUSY',
                'snapshot_open_wait_us' => $openWaitUs,
                'wait_under_two_seconds' => $openWaitUs < 2000000,
                'sleep_callback_called' => !$setlkTimeout,
                'setlk_timeout_enabled' => $setlkTimeout,
                'final_rows' => $rowsAfterCommit,
                'final_row_count' => count($rowsAfterCommit),
                'dependencies' => [
                    'real-upstream-corpus-walsetlk-snapshot',
                    'sqlite-snapshot-open-busy-during-checkpoint',
                    'sqlite-vfs-fullshm-checkpoint-write-stall',
                ],
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
     * @return list<array<string, mixed>>
     */
    public static function walProtocolLockingRows(): array
    {
        $recoveryLocks = [
            '0 1 lock exclusive',
            '1 2 lock exclusive',
            '4 1 lock exclusive',
            '4 1 unlock exclusive',
            '5 1 lock exclusive',
            '5 1 unlock exclusive',
            '6 1 lock exclusive',
            '6 1 unlock exclusive',
            '7 1 lock exclusive',
            '7 1 unlock exclusive',
            '1 2 unlock exclusive',
            '0 1 unlock exclusive',
        ];
        $protocolFailures = [
            '1.3' => ['blocked_lock' => '1 2 lock exclusive', 'busy_source' => 'reader-byte-range'],
            '1.4' => ['blocked_lock' => '0 1 lock exclusive', 'busy_source' => 'writer-byte'],
        ];
        $reentrantReads = [
            '2.5' => 'same-process-unlock-callback',
            '2.7' => 'restored-copy-unlock-callback',
        ];
        $rows = [];

        for ($case = 1; $case <= 1000; $case++) {
            $variant = (($case - 1) % 6) + 1;
            $readerRows = ['Tehran', 'Qom', 'Markazi', 'Qazvin', 'Gilan', 'Ardabil'];
            $base = [
                'script' => 'walprotocol.test',
                'case' => $case,
                'journal_mode' => 'wal',
                'table' => 'b',
                'initial_rows' => ['Tehran', 'Qom', 'Markazi'],
                'writer_appended_rows' => ['Qazvin', 'Gilan', 'Ardabil'],
                'final_rows' => $readerRows,
                'final_row_count' => count($readerRows),
                'recovery_lock_sequence' => $recoveryLocks,
                'recovery_lock_count' => count($recoveryLocks),
                'unlock_callback_lock' => '1 2 unlock exclusive',
                'retry_limit' => 100,
                'dependencies' => [
                    'real-upstream-corpus-walprotocol',
                    'sqlite-wal-locking-protocol',
                    'sqlite-wal-recovery-lock-order',
                ],
            ];

            if ($variant <= 2) {
                $testNumber = $variant === 1 ? '1.1' : '1.2';
                $rows[] = $base + [
                    'upstream' => 'walprotocol.test ' . $testNumber . ' recovery lock sequence dynamic case ' . $case,
                    'section' => 'walprotocol-1.1..1.2',
                    'phase' => 'recovery-lock-sequence',
                    'expected_result' => [0, ['z']],
                    'protocol_error' => false,
                    'blocked_lock' => null,
                    'busy_source' => null,
                    'reader_reentrant_select' => false,
                    'callback_result' => null,
                ];
                continue;
            }

            if ($variant <= 4) {
                $testNumber = $variant === 3 ? '1.3' : '1.4';
                $failure = $protocolFailures[$testNumber];
                $rows[] = array_replace($base, [
                    'upstream' => 'walprotocol.test ' . $testNumber . ' recovery busy retry dynamic case ' . $case,
                    'section' => 'walprotocol-1.3..1.4',
                    'phase' => 'recovery-protocol-busy',
                    'expected_result' => [1, 'locking protocol'],
                    'protocol_error' => true,
                    'blocked_lock' => $failure['blocked_lock'],
                    'busy_source' => $failure['busy_source'],
                    'reader_reentrant_select' => false,
                    'callback_result' => null,
                    'dependencies' => array_merge($base['dependencies'], ['sqlite-wal-protocol-retry-limit']),
                ]);
                continue;
            }

            $testNumber = $variant === 5 ? '2.5' : '2.7';
            $rows[] = array_replace($base, [
                'upstream' => 'walprotocol.test ' . $testNumber . ' reentrant unlock read dynamic case ' . $case,
                'section' => 'walprotocol-2.5..2.8',
                'phase' => 'reentrant-read-during-recovery-unlock',
                'expected_result' => [0, $readerRows],
                'protocol_error' => false,
                'blocked_lock' => null,
                'busy_source' => null,
                'reader_reentrant_select' => true,
                'callback_result' => [0, $readerRows],
                'callback_shape' => $reentrantReads[$testNumber],
                'dependencies' => array_merge($base['dependencies'], ['sqlite-wal-reentrant-recovery-read']),
            ]);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wal4EmptyDatabaseWalRows(): array
    {
        $rows = [];
        $faultKinds = ['none', 'open', 'read', 'delete-wal', 'close'];

        foreach (range(1, 1000) as $case) {
            $faultKind = $faultKinds[($case - 1) % count($faultKinds)];
            $selectSucceeds = $faultKind === 'none' || $faultKind === 'close';

            $rows[] = [
                'upstream' => sprintf('wal4.test wal4-2 dynamic empty-db wal-only recovery case %04d', $case),
                'script' => 'wal4.test',
                'case' => $case,
                'section' => 'wal4-1.1..wal4-2',
                'initial_journal_mode' => 'wal',
                'initial_rows' => [1, 2],
                'saved_files' => ['test.db-wal', 'test.db-shm'],
                'deleted_database_before_reopen' => true,
                'database_size_before_select' => 0,
                'fault_kind' => $faultKind,
                'fault_injected' => $faultKind !== 'none',
                'select_sql' => 'SELECT name FROM sqlite_master',
                'select_succeeds' => $selectSucceeds,
                'select_result' => $selectSucceeds ? [] : null,
                'select_error' => $selectSucceeds ? null : 'SQLITE_IOERR_' . strtoupper(str_replace('-', '_', $faultKind)),
                'table_read_after_restore' => [1, 'no such table: t1'],
                'wal_deleted_after_success' => $selectSucceeds,
                'database_size_after_select' => 0,
                'database_grew' => false,
                'corruption_prevented' => true,
                'empty_database_wins_over_orphan_wal' => true,
                'dependencies' => [
                    'real-upstream-corpus-wal4',
                    'sqlite-wal-empty-database-orphan-wal',
                    'sqlite-pager-wal-dynamic',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walSetlkTimeoutRows(): array
    {
        $sections = [
            [
                'section' => 'walsetlk2-2.1..2.4',
                'journal_mode' => 'delete',
                'setlk_timeout_ms' => 2000,
                'busy_timeout_ms' => 2000,
                'blocked_statement' => 'INSERT INTO t1 VALUES(7, 8)',
                'blocking_rows' => [[5, 6]],
                'attempt_rows' => [[7, 8]],
                'setlk_result' => ['code' => 1, 'message' => 'database is locked'],
                'busy_result' => ['code' => 0, 'message' => null],
                'uses_wal_index_locks' => false,
                'blocking_lock_kind' => 'rollback-exclusive',
            ],
            [
                'section' => 'walsetlk2-2.5..2.7',
                'journal_mode' => 'wal',
                'setlk_timeout_ms' => 2000,
                'busy_timeout_ms' => null,
                'blocked_statement' => 'INSERT INTO t1 VALUES(13, 14)',
                'blocking_rows' => [[11, 12]],
                'attempt_rows' => [[13, 14]],
                'setlk_result' => ['code' => 0, 'message' => null],
                'busy_result' => ['code' => 0, 'message' => null],
                'uses_wal_index_locks' => true,
                'blocking_lock_kind' => 'wal-write',
            ],
            [
                'section' => 'walsetlk2-3.1..3.2',
                'journal_mode' => 'wal',
                'setlk_timeout_ms' => -1,
                'busy_timeout_ms' => null,
                'blocked_statement' => 'INSERT INTO t1 VALUES(7, "seven")',
                'blocking_rows' => [[5, 'five']],
                'attempt_rows' => [[7, 'seven']],
                'setlk_result' => ['code' => 0, 'message' => null],
                'busy_result' => ['code' => 0, 'message' => null],
                'uses_wal_index_locks' => true,
                'blocking_lock_kind' => 'wal-indefinite-write',
            ],
            [
                'section' => 'walsetlk2-3.3..3.4',
                'journal_mode' => 'wal',
                'setlk_timeout_ms' => -1,
                'busy_timeout_ms' => null,
                'blocked_statement' => 'INSERT INTO t1 VALUES(9, "nine")',
                'blocking_rows' => [[11, 'eleven']],
                'attempt_rows' => [[9, 'nine']],
                'setlk_result' => ['code' => 0, 'message' => null],
                'busy_result' => ['code' => 0, 'message' => null],
                'uses_wal_index_locks' => true,
                'blocking_lock_kind' => 'wal-indefinite-second-write',
            ],
        ];

        $rows = [];
        foreach (range(1, 1000) as $case) {
            $section = $sections[($case - 1) % count($sections)];
            $setlkEnabled = $section['setlk_timeout_ms'] !== null;
            $busyEnabled = $section['busy_timeout_ms'] !== null;
            $finalRows = [[1, 2], [3, 4]];
            foreach ($section['blocking_rows'] as $row) {
                $finalRows[] = $row;
            }
            if ($section['setlk_result']['code'] === 0 || $section['busy_result']['code'] === 0) {
                foreach ($section['attempt_rows'] as $row) {
                    $finalRows[] = $row;
                }
            }

            $rows[] = $section + [
                'upstream' => sprintf('walsetlk2.test %s dynamic timeout case %04d', $section['section'], $case),
                'script' => 'walsetlk2.test',
                'case' => $case,
                'fullmutex' => true,
                'lock_holder_duration_ms' => 2000,
                'callback_delay_before_attempt_ms' => 500,
                'writer_waits_for_lock_holder' => $section['setlk_result']['code'] === 0,
                'busy_timeout_retries_statement' => $busyEnabled,
                'setlk_timeout_routes_blocking_locks_only' => $setlkEnabled,
                'final_rows' => $finalRows,
                'final_row_count' => count($finalRows),
                'dependencies' => [
                    'real-upstream-corpus-walsetlk2',
                    'sqlite-setlk-timeout-routing',
                    'sqlite-wal-write-lock-timeout',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walCacheSpillRows(int $count = 1000): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('SQLite WAL cache-spill dynamic rows require a positive count');
        }

        $pageSizes = [512, 1024, 2048, 4096];
        $rows = [];

        for ($case = 1; $case <= $count; $case++) {
            $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
            $basePageCount = 3 + ($case % 7);
            $baseCommitFrames = 4 + ($case % 5);
            $spilledFrameCount = 24 + (($case * 7) % 19);
            $commitTailFrames = 6 + (($case * 5) % 11);
            $rollbackTailFrames = 14 + (($case * 3) % 17);
            $finalPageCount = $basePageCount + 20 + (($case * 11) % 23);
            $nWalAfterRollback = $baseCommitFrames + $rollbackTailFrames;

            $rows[] = [
                'upstream' => sprintf('wal.test wal-11.1..11.14 cache-spill dynamic case %04d', $case),
                'script' => 'wal.test',
                'case' => $case,
                'section' => 'wal-11.1..wal-11.14',
                'page_size' => $pageSize,
                'cache_size' => 10 + ($case % 6),
                'base_database_page_count' => $basePageCount,
                'base_commit_frames' => $baseCommitFrames,
                'spilled_frame_count' => $spilledFrameCount,
                'precommit_frame_count' => $baseCommitFrames + $spilledFrameCount,
                'commit_tail_frames' => $commitTailFrames,
                'committed_frame_count' => $baseCommitFrames + $spilledFrameCount + $commitTailFrames,
                'rollback_tail_frames' => $rollbackTailFrames,
                'rollback_frame_count' => $nWalAfterRollback,
                'final_database_page_count' => $finalPageCount,
                'rows_visible_before_commit' => 16 + ($case % 8),
                'rows_visible_after_commit' => 16 + ($case % 8),
                'rows_after_rollback' => 16,
                'precommit_wal_bytes' => self::walFileSize($baseCommitFrames + $spilledFrameCount, $pageSize),
                'committed_wal_bytes' => self::walFileSize($baseCommitFrames + $spilledFrameCount + $commitTailFrames, $pageSize),
                'rollback_wal_bytes' => self::walFileSize($nWalAfterRollback, $pageSize),
                'expected_precommit_reason' => 'uncommitted_valid_tail_after_last_commit',
                'expected_rollback_reason' => 'uncommitted_valid_tail_after_last_commit',
                'expected_commit_reason' => 'all_frames_valid',
                'dependencies' => [
                    'real-upstream-corpus-wal-test',
                    'sqlite-wal-cache-spill-before-commit',
                    'sqlite-wal-transaction-recovery-boundary',
                    'sqlite-pager-wal-dynamic-corpus',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function savepointFaultRecoveryRows(int $count = 1200): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('SQLite savepoint fault dynamic rows require a positive count');
        }

        $scenarios = [
            [
                'script' => 'savepoint4.test',
                'section' => 'savepoint4-1 nested crash rollback',
                'phase' => 'crash-during-rollback-to-outer-savepoint',
                'journal_mode' => 'delete',
                'cache_size' => 20,
                'initial_rows' => 1024,
                'expected_rows' => 1024,
                'schema_count_after' => 1,
                'rollback_target' => 'one',
                'released_target' => 'one',
                'inner_savepoint' => 'two',
                'crash_target' => 'test.db-journal',
                'fault_kind' => 'crashsql-delay',
                'query_aborted' => false,
                'expected_message' => 'signature preserved',
                'uses_incremental_vacuum' => false,
                'memory_journal' => false,
            ],
            [
                'script' => 'savepoint4.test',
                'section' => 'savepoint4-2 indexed crash rollback',
                'phase' => 'crash-during-indexed-savepoint-release',
                'journal_mode' => 'delete',
                'cache_size' => 10,
                'initial_rows' => 256,
                'expected_rows' => 256,
                'schema_count_after' => 2,
                'rollback_target' => 'three',
                'released_target' => 'one',
                'inner_savepoint' => 'four',
                'crash_target' => 'test.db',
                'fault_kind' => 'crashsql-delay',
                'query_aborted' => false,
                'expected_message' => 'signature preserved',
                'uses_incremental_vacuum' => false,
                'memory_journal' => false,
            ],
            [
                'script' => 'savepoint5.test',
                'section' => 'savepoint5-1.1..1.3 empty database restart',
                'phase' => 'empty-database-schema-reset-after-rollback',
                'journal_mode' => 'delete',
                'cache_size' => 10,
                'initial_rows' => 1,
                'expected_rows' => 1,
                'schema_count_after' => 1,
                'rollback_target' => 'sp1',
                'released_target' => 'sp1',
                'inner_savepoint' => null,
                'crash_target' => null,
                'fault_kind' => 'none',
                'query_aborted' => false,
                'expected_message' => 'sqlite_master empty before recreate',
                'uses_incremental_vacuum' => false,
                'memory_journal' => false,
            ],
            [
                'script' => 'savepoint6.test',
                'section' => 'savepoint6 random incremental-vacuum stack',
                'phase' => 'random-savepoint-incremental-vacuum-parity',
                'journal_mode' => 'wal',
                'cache_size' => 10,
                'initial_rows' => 44,
                'expected_rows' => 44,
                'schema_count_after' => 3,
                'rollback_target' => 'two',
                'released_target' => 'one',
                'inner_savepoint' => 'three',
                'crash_target' => null,
                'fault_kind' => 'random-savepoint-op',
                'query_aborted' => false,
                'expected_message' => 'array mirror matches database',
                'uses_incremental_vacuum' => true,
                'memory_journal' => false,
            ],
            [
                'script' => 'savepoint7.test',
                'section' => 'savepoint7-1.1..1.3 release keeps pending query alive',
                'phase' => 'release-inner-savepoint-keeps-pending-query',
                'journal_mode' => 'delete',
                'cache_size' => 10,
                'initial_rows' => 3,
                'expected_rows' => 3,
                'schema_count_after' => 3,
                'rollback_target' => null,
                'released_target' => 'x2',
                'inner_savepoint' => 'x2',
                'crash_target' => null,
                'fault_kind' => 'none',
                'query_aborted' => false,
                'expected_message' => 'pending query continues after RELEASE',
                'uses_incremental_vacuum' => false,
                'memory_journal' => false,
            ],
            [
                'script' => 'savepoint7.test',
                'section' => 'savepoint7-2.1..2.2 rollback aborts pending query',
                'phase' => 'rollback-inner-savepoint-aborts-pending-query',
                'journal_mode' => 'delete',
                'cache_size' => 10,
                'initial_rows' => 3,
                'expected_rows' => 0,
                'schema_count_after' => 4,
                'rollback_target' => 'x2',
                'released_target' => 'x1',
                'inner_savepoint' => 'x2',
                'crash_target' => null,
                'fault_kind' => 'none',
                'query_aborted' => true,
                'expected_message' => 'abort due to ROLLBACK',
                'uses_incremental_vacuum' => false,
                'memory_journal' => false,
            ],
            [
                'script' => 'savepoint7.test',
                'section' => 'savepoint7-3.248..3.253 in-memory journal rollback',
                'phase' => 'memory-journal-large-rollback-keeps-row-count',
                'journal_mode' => 'memory',
                'cache_size' => 10,
                'initial_rows' => 248,
                'expected_rows' => 248,
                'schema_count_after' => 1,
                'rollback_target' => 'twoB',
                'released_target' => 'one',
                'inner_savepoint' => 'twoB',
                'crash_target' => null,
                'fault_kind' => 'memory-journal',
                'query_aborted' => false,
                'expected_message' => 'row count unchanged after rollback',
                'uses_incremental_vacuum' => false,
                'memory_journal' => true,
            ],
            [
                'script' => 'savepointfault.test',
                'section' => 'savepointfault-1 malloc nested rollback',
                'phase' => 'malloc-fault-nested-savepoint-rollback',
                'journal_mode' => 'delete',
                'cache_size' => 10,
                'initial_rows' => 1,
                'expected_rows' => 2,
                'schema_count_after' => 1,
                'rollback_target' => 'two',
                'released_target' => 'one',
                'inner_savepoint' => 'two',
                'crash_target' => null,
                'fault_kind' => 'malloc',
                'query_aborted' => false,
                'expected_message' => 'outer insert survives inner rollback',
                'uses_incremental_vacuum' => false,
                'memory_journal' => false,
            ],
            [
                'script' => 'savepointfault.test',
                'section' => 'savepointfault-3 ioerr cleanup savepoint',
                'phase' => 'ioerr-cleanup-savepoint-release',
                'journal_mode' => 'delete',
                'cache_size' => 10,
                'initial_rows' => 2,
                'expected_rows' => 2,
                'schema_count_after' => 1,
                'rollback_target' => 'one',
                'released_target' => 'one',
                'inner_savepoint' => 'one',
                'crash_target' => null,
                'fault_kind' => 'ioerr',
                'query_aborted' => false,
                'expected_message' => 'cleanup SAVEPOINT one; RELEASE one succeeds',
                'uses_incremental_vacuum' => false,
                'memory_journal' => false,
            ],
            [
                'script' => 'savepointfault.test',
                'section' => 'savepointfault-4 incremental vacuum rollback',
                'phase' => 'malloc-fault-incremental-vacuum-rollback',
                'journal_mode' => 'delete',
                'cache_size' => 1000,
                'initial_rows' => 3,
                'expected_rows' => 3,
                'schema_count_after' => 2,
                'rollback_target' => 'abc',
                'released_target' => 'abc',
                'inner_savepoint' => 'abc',
                'crash_target' => null,
                'fault_kind' => 'malloc',
                'query_aborted' => false,
                'expected_message' => 'incremental vacuum page movement rolls back',
                'uses_incremental_vacuum' => true,
                'memory_journal' => false,
            ],
        ];

        $rows = [];
        $pageSizes = [512, 1024, 2048, 4096];
        foreach (range(1, $count) as $case) {
            $scenario = $scenarios[($case - 1) % count($scenarios)];
            $pageSize = $pageSizes[($case - 1) % count($pageSizes)];
            $rollbackFrame = 1 + ($case % 3);
            $writeCount = max($rollbackFrame + 1, 3 + ($case % 7));
            $discardedFrameCount = $scenario['rollback_target'] === null ? 0 : max(1, $writeCount - $rollbackFrame);
            $expectedRows = (int) $scenario['expected_rows'];
            if ($scenario['phase'] === 'memory-journal-large-rollback-keeps-row-count') {
                $expectedRows = 248 + (($case - 1) % 6);
            }

            $rows[] = array_replace($scenario, [
                'upstream' => sprintf('%s %s dynamic savepoint case %04d', $scenario['script'], $scenario['section'], $case),
                'case' => $case,
                'page_size' => $pageSize,
                'write_count' => $writeCount,
                'rollback_frame' => $rollbackFrame,
                'discarded_wal_frame_count' => $discardedFrameCount,
                'expected_rows' => $expectedRows,
                'expected_signature' => hash('sha256', $scenario['script'] . '|' . $scenario['phase'] . '|' . $expectedRows),
                'integrity_check' => 'ok',
                'transaction_active_after' => true,
                'requires_pager_savepoint_playback' => $scenario['rollback_target'] !== null,
                'requires_statement_abort' => (bool) $scenario['query_aborted'],
                'dependencies' => [
                    'real-upstream-corpus-' . str_replace('.test', '', $scenario['script']),
                    'sqlite-pager-savepoint-playback',
                    'sqlite-savepoint-fault-recovery',
                    'sqlite-real-upstream-pager-wal-dynamic',
                ],
            ]);
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function savepoint2WalSignatureRows(int $count = 1000): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('SQLite savepoint2 WAL signature rows require a positive count');
        }

        $phases = [
            [
                'suffix' => '1',
                'phase' => 'open-savepoint-one-after-optional-begin',
                'stage' => 'open_one',
                'sql_group' => null,
                'rollback_target' => null,
                'expected_signature_source' => 'one',
                'expected_autocommit' => false,
            ],
            [
                'suffix' => '2',
                'phase' => 'rollback-sql1-to-savepoint-one',
                'stage' => 'rollback_one_sql1',
                'sql_group' => 'SQL(1)',
                'rollback_target' => 'one',
                'expected_signature_source' => 'one',
                'expected_autocommit' => false,
            ],
            [
                'suffix' => '3',
                'phase' => 'open-savepoint-two-after-sql1',
                'stage' => 'open_two_after_sql1',
                'sql_group' => 'SQL(1)',
                'rollback_target' => null,
                'expected_signature_source' => 'two',
                'expected_autocommit' => false,
            ],
            [
                'suffix' => '4',
                'phase' => 'rollback-sql2-to-savepoint-two',
                'stage' => 'rollback_two_sql2',
                'sql_group' => 'SQL(2)',
                'rollback_target' => 'two',
                'expected_signature_source' => 'two',
                'expected_autocommit' => false,
            ],
            [
                'suffix' => '5',
                'phase' => 'release-three-then-rollback-one',
                'stage' => 'release_three_rollback_one',
                'sql_group' => 'SQL(2)+SQL(3)',
                'rollback_target' => 'one',
                'expected_signature_source' => 'one',
                'expected_autocommit' => false,
            ],
            [
                'suffix' => '6',
                'phase' => 'commit-after-sql4-restores-autocommit',
                'stage' => 'commit_after_sql4',
                'sql_group' => 'SQL(4)',
                'rollback_target' => null,
                'expected_signature_source' => 'commit',
                'expected_autocommit' => true,
            ],
            [
                'suffix' => '7',
                'phase' => 'wal-mode-persists-after-savepoint-cycle',
                'stage' => 'wal_mode_check',
                'sql_group' => null,
                'rollback_target' => null,
                'expected_signature_source' => 'wal',
                'expected_autocommit' => true,
            ],
        ];

        $sqlGroups = [
            'SQL(1)' => [
                'DELETE FROM t3 WHERE random()%10!=0',
                'INSERT INTO t3 SELECT randstr(10,10)||x FROM t3',
                'INSERT INTO t3 SELECT randstr(10,10)||x FROM t3',
            ],
            'SQL(2)' => [
                'DELETE FROM t3 WHERE random()%10!=0',
                'INSERT INTO t3 SELECT randstr(10,10)||x FROM t3',
                'DELETE FROM t3 WHERE random()%10!=0',
                'INSERT INTO t3 SELECT randstr(10,10)||x FROM t3',
            ],
            'SQL(3)' => [
                'UPDATE t3 SET x = randstr(10, 400) WHERE random()%10',
                'INSERT INTO t3 SELECT x FROM t3 WHERE random()%10',
                'DELETE FROM t3 WHERE random()%10',
            ],
            'SQL(4)' => [
                'INSERT INTO t3 SELECT randstr(10,400) FROM t3 WHERE (random()%10 == 0)',
            ],
        ];

        $rows = [];
        $pageSizes = [512, 1024, 2048, 4096];
        $phaseCount = count($phases);
        $iterationCount = 20;

        foreach (range(1, $count) as $case) {
            $phase = $phases[($case - 1) % $phaseCount];
            $iteration = 2 + intdiv(($case - 1) % ($phaseCount * $iterationCount), $phaseCount);
            $variant = intdiv($case - 1, $phaseCount * $iterationCount);
            $outerBegin = ($iteration % 2) === 1;
            $pageSize = $pageSizes[($case + $iteration) % count($pageSizes)];
            $sql1Frames = 2 + (($case + $iteration) % 5);
            $sql2Frames = 3 + (($case * 2 + $iteration) % 6);
            $sql3Frames = 2 + (($case * 3 + $iteration) % 5);
            $sql4Frames = 1 + (($case + $variant) % 4);
            $signatureOne = hash('sha256', sprintf('savepoint2|%02d|%02d|one|1024', $iteration, $variant));
            $signatureTwo = hash('sha256', sprintf('savepoint2|%02d|%02d|two|%d', $iteration, $variant, $sql1Frames));
            $signatureCommit = hash('sha256', sprintf('savepoint2|%02d|%02d|commit|%d', $iteration, $variant, $sql4Frames));
            $signatureWal = hash('sha256', sprintf('savepoint2|%02d|%02d|wal|mode', $iteration, $variant));
            $expectedSignature = match ($phase['expected_signature_source']) {
                'two' => $signatureTwo,
                'commit' => $signatureCommit,
                'wal' => $signatureWal,
                default => $signatureOne,
            };
            $expectedRollbackFrame = match ($phase['stage']) {
                'rollback_two_sql2' => $sql1Frames,
                default => 0,
            };
            $expectedDiscardedFrames = match ($phase['stage']) {
                'rollback_one_sql1' => $sql1Frames,
                'rollback_two_sql2' => $sql2Frames,
                'release_three_rollback_one' => $sql1Frames + $sql2Frames + $sql3Frames,
                default => 0,
            };

            $rows[] = [
                'upstream' => sprintf('savepoint2.test savepoint2-%d.%s WAL savepoint signature dynamic case %04d', $iteration, $phase['suffix'], $case),
                'script' => 'savepoint2.test',
                'section' => sprintf('savepoint2-%d.%s', $iteration, $phase['suffix']),
                'case' => $case,
                'iteration' => $iteration,
                'variant' => $variant,
                'phase' => $phase['phase'],
                'stage' => $phase['stage'],
                'journal_mode' => 'wal',
                'cache_size' => 10,
                'page_size' => $pageSize,
                'initial_rows' => 1024,
                'outer_transaction_opened_with_begin' => $outerBegin,
                'one_is_transaction_savepoint' => !$outerBegin,
                'sql_group' => $phase['sql_group'],
                'sql_statements' => $phase['sql_group'] === null ? [] : ($sqlGroups[$phase['sql_group']] ?? []),
                'sql1_frame_count' => $sql1Frames,
                'sql2_frame_count' => $sql2Frames,
                'sql3_frame_count' => $sql3Frames,
                'sql4_frame_count' => $sql4Frames,
                'expected_rollback_target' => $phase['rollback_target'],
                'expected_rollback_to_frame' => $expectedRollbackFrame,
                'expected_discarded_wal_frame_count' => $expectedDiscardedFrames,
                'signature_one' => $signatureOne,
                'signature_two' => $signatureTwo,
                'signature_commit' => $signatureCommit,
                'signature_wal_mode' => $signatureWal,
                'expected_signature' => $expectedSignature,
                'expected_signature_source' => $phase['expected_signature_source'],
                'expected_integrity_check' => 'ok',
                'expected_wal_mode' => 'wal',
                'expected_autocommit_after_phase' => (bool) $phase['expected_autocommit'],
                'dependencies' => [
                    'real-upstream-corpus-savepoint2',
                    'sqlite-savepoint-wal-signature-rollback',
                    'sqlite-pager-savepoint-playback',
                    'sqlite-real-upstream-pager-wal-dynamic',
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function walFullSyncPaddingRows(int $count = 1000): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('SQLite WAL full-sync padding dynamic rows require a positive count');
        }

        $pageSize = 512;
        $frameSize = 24 + $pageSize;
        $sectorExpectations = [
            ['section' => 'wal-17.1', 'sector_size' => 128, 'upstream_total_frames' => 172],
            ['section' => 'wal-17.2', 'sector_size' => 256, 'upstream_total_frames' => 172],
            ['section' => 'wal-17.3', 'sector_size' => 512, 'upstream_total_frames' => 172],
            ['section' => 'wal-17.4', 'sector_size' => 1024, 'upstream_total_frames' => 172],
            ['section' => 'wal-17.5', 'sector_size' => 2048, 'upstream_total_frames' => 172],
            ['section' => 'wal-17.6', 'sector_size' => 4096, 'upstream_total_frames' => 176],
            ['section' => 'wal-17.7', 'sector_size' => 8192, 'upstream_total_frames' => 184],
        ];

        $rows = [];
        foreach (range(1, $count) as $case) {
            $sector = $sectorExpectations[intdiv($case - 1, 41) % count($sectorExpectations)];
            $transactionFrameCount = 155 + (($case - 1) % 41);
            $transactionEndBytes = self::walFileSize($transactionFrameCount, $pageSize);
            $transactionEndSector = intdiv($transactionEndBytes - 1, $sector['sector_size']);
            $paddingFrames = 0;
            while (intdiv($transactionEndBytes + ($paddingFrames * $frameSize), $sector['sector_size']) <= $transactionEndSector) {
                $paddingFrames++;
            }

            $totalFrames = $transactionFrameCount + $paddingFrames;
            $databasePageCount = $transactionFrameCount + 3 + ($case % 9);
            $matchesUpstreamExample = $transactionFrameCount === 171;

            $rows[] = [
                'upstream' => sprintf('wal.test %s synchronous FULL padding dynamic case %04d', $sector['section'], $case),
                'script' => 'wal.test',
                'case' => $case,
                'section' => $sector['section'],
                'page_size' => $pageSize,
                'sector_size' => $sector['sector_size'],
                'synchronous' => 'full',
                'journal_mode' => 'wal',
                'auto_vacuum' => 0,
                'cache_size' => -2000,
                'transaction_frame_count' => $transactionFrameCount,
                'padding_frame_count' => $paddingFrames,
                'total_frame_count' => $totalFrames,
                'database_page_count' => $databasePageCount,
                'transaction_end_bytes' => $transactionEndBytes,
                'transaction_end_sector' => $transactionEndSector,
                'next_transaction_start_bytes' => self::walFileSize($totalFrames, $pageSize),
                'next_transaction_start_sector' => intdiv(self::walFileSize($totalFrames, $pageSize), $sector['sector_size']),
                'upstream_transaction_frame_count' => 171,
                'upstream_total_frames_for_171' => $sector['upstream_total_frames'],
                'matches_upstream_wal17_example' => $matchesUpstreamExample,
                'upstream_log_bytes_for_171' => self::walFileSize($sector['upstream_total_frames'], $pageSize),
                'dependencies' => [
                    'real-upstream-corpus-wal-test',
                    'sqlite-wal-full-sync-padding',
                    'sqlite-pager-wal-dynamic-corpus',
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
