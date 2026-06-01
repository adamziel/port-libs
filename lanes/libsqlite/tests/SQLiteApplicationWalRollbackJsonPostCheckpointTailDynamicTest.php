<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;
use PortLibs\LibSqlite\SQLiteWal;

ini_set('memory_limit', '1536M');

$tailFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailFailureScenarios(18);
$tailRecoveryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryScenariosFromTailFailureScenarios($tailFailureScenarios);
$tailRecoveryCheckpointScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointScenariosFromTailRecoveryScenarios($tailRecoveryScenarios);
$tailRecoveryCheckpointFollowupScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupScenariosFromCheckpointScenarios($tailRecoveryCheckpointScenarios);

$tests = [
    'sqlite application wal rollback json post checkpoint tail dynamic failure exposes requested scenario count' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $t->same(18, count($tailFailureScenarios));
    },
    'sqlite application wal rollback json post checkpoint tail dynamic failure covers checkpoint reset modes' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $modes = array_values(array_unique(array_column($tailFailureScenarios, 'checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json post checkpoint tail dynamic failure covers both page sizes' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $pageSizes = array_values(array_unique(array_column($tailFailureScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json post checkpoint tail dynamic failure covers json text and jsonb rows' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $jsonModes = array_values(array_unique(array_column($tailFailureScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json post checkpoint tail dynamic failure keeps a two frame committed prefix' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $t->same([2], array_values(array_unique(array_column($tailFailureScenarios, 'committed_prefix_frame_count'))));
    },
    'sqlite application wal rollback json post checkpoint tail dynamic recovery exposes requested scenario count' => static function (TestRunner $t) use ($tailRecoveryScenarios): void {
        $t->same(18, count($tailRecoveryScenarios));
    },
    'sqlite application wal rollback json post checkpoint tail dynamic recovery covers checkpoint reset modes' => static function (TestRunner $t) use ($tailRecoveryScenarios): void {
        $modes = array_values(array_unique(array_column($tailRecoveryScenarios, 'checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json post checkpoint tail dynamic recovery covers both page sizes' => static function (TestRunner $t) use ($tailRecoveryScenarios): void {
        $pageSizes = array_values(array_unique(array_column($tailRecoveryScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json post checkpoint tail dynamic recovery covers json text and jsonb rows' => static function (TestRunner $t) use ($tailRecoveryScenarios): void {
        $jsonModes = array_values(array_unique(array_column($tailRecoveryScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json post checkpoint tail recovery checkpoint exposes requested scenario count' => static function (TestRunner $t) use ($tailRecoveryCheckpointScenarios): void {
        $t->same(18, count($tailRecoveryCheckpointScenarios));
    },
    'sqlite application wal rollback json post checkpoint tail recovery checkpoint covers checkpoint reset modes' => static function (TestRunner $t) use ($tailRecoveryCheckpointScenarios): void {
        $modes = array_values(array_unique(array_column($tailRecoveryCheckpointScenarios, 'checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json post checkpoint tail recovery checkpoint covers both page sizes' => static function (TestRunner $t) use ($tailRecoveryCheckpointScenarios): void {
        $pageSizes = array_values(array_unique(array_column($tailRecoveryCheckpointScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json post checkpoint tail recovery checkpoint covers json text and jsonb rows' => static function (TestRunner $t) use ($tailRecoveryCheckpointScenarios): void {
        $jsonModes = array_values(array_unique(array_column($tailRecoveryCheckpointScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json post checkpoint tail recovery checkpoint followup exposes requested scenario count' => static function (TestRunner $t) use ($tailRecoveryCheckpointFollowupScenarios): void {
        $t->same(18, count($tailRecoveryCheckpointFollowupScenarios));
    },
    'sqlite application wal rollback json post checkpoint tail recovery checkpoint followup covers checkpoint reset modes' => static function (TestRunner $t) use ($tailRecoveryCheckpointFollowupScenarios): void {
        $modes = array_values(array_unique(array_column($tailRecoveryCheckpointFollowupScenarios, 'checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json post checkpoint tail recovery checkpoint followup covers both page sizes' => static function (TestRunner $t) use ($tailRecoveryCheckpointFollowupScenarios): void {
        $pageSizes = array_values(array_unique(array_column($tailRecoveryCheckpointFollowupScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json post checkpoint tail recovery checkpoint followup covers json text and jsonb rows' => static function (TestRunner $t) use ($tailRecoveryCheckpointFollowupScenarios): void {
        $jsonModes = array_values(array_unique(array_column($tailRecoveryCheckpointFollowupScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json post checkpoint tail recovery checkpoint followup creates headers only after truncate' => static function (TestRunner $t) use ($tailRecoveryCheckpointFollowupScenarios): void {
        $started = array_values(array_unique(array_column($tailRecoveryCheckpointFollowupScenarios, 'tail_recovery_checkpoint_followup_started_new_wal_header')));
        sort($started);
        $t->same([false, true], $started);
    },
    'sqlite application wal rollback json post checkpoint tail dynamic rejects zero failure scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailFailureScenarios(0);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json post checkpoint tail dynamic rejects empty failure base scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailFailureScenariosFromFollowupScenarios([]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json post checkpoint tail dynamic rejects zero recovery scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryScenarios(0);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json post checkpoint tail dynamic rejects empty recovery base scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryScenariosFromTailFailureScenarios([]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json post checkpoint tail dynamic rejects zero recovery checkpoint scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointScenarios(0);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json post checkpoint tail dynamic rejects empty recovery checkpoint base scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointScenariosFromTailRecoveryScenarios([]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json post checkpoint tail dynamic rejects zero recovery checkpoint followup scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupScenarios(0);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json post checkpoint tail dynamic rejects empty recovery checkpoint followup base scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupScenariosFromCheckpointScenarios([]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json post checkpoint tail dynamic small failure batch remains deterministic' => static function (TestRunner $t): void {
        $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailFailureScenarios(4);
        $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
        $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'checkpoint_mode'));
        $t->same([[1321, 1931], [1322, 1932], [1323, 1933], [1324, 1934]], array_column($smallBatch, 'expected_post_checkpoint_tail_pages'));
        $t->same([5, 5, 5, 5], array_map(static fn (array $scenario): int => $scenario['post_checkpoint_tail_failure_plan']['wal_frame_count_before'], $smallBatch));
        $t->same([2, 2, 2, 2], array_map(static fn (array $scenario): int => $scenario['post_checkpoint_tail_failure_plan']['wal_frame_count_after'], $smallBatch));
    },
    'sqlite application wal rollback json post checkpoint tail dynamic small recovery batch remains deterministic' => static function (TestRunner $t): void {
        $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryScenarios(4);
        $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
        $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'checkpoint_mode'));
        $t->same([[1321, 1941, 2021], [1322, 1942, 2022], [1323, 1943, 2023], [1324, 1944, 2024]], array_column($smallBatch, 'expected_post_checkpoint_recovery_pages'));
        $t->same([5, 5, 5, 5], array_map(static fn (array $scenario): int => $scenario['post_checkpoint_tail_recovery_plan']['wal_frame_count_after'], $smallBatch));
    },
    'sqlite application wal rollback json post checkpoint tail dynamic small recovery checkpoint batch remains deterministic' => static function (TestRunner $t): void {
        $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointScenarios(4);
        $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
        $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'checkpoint_mode'));
        $t->same([[3, 4, 5], [3, 4, 5], [3, 4, 5], [3, 4, 5]], array_column($smallBatch, 'tail_recovery_applied_frame_indexes'));
        $t->same([[1321, 1941, 2021], [1322, 1942, 2022], [1323, 1943, 2023], [1324, 1944, 2024]], array_column($smallBatch, 'tail_recovery_applied_page_numbers'));
        $t->same(['restart_wal', 'truncate_wal', 'restart_wal', 'truncate_wal'], array_column($smallBatch, 'expected_tail_recovery_checkpoint_action'));
    },
    'sqlite application wal rollback json post checkpoint tail dynamic small recovery checkpoint followup batch remains deterministic' => static function (TestRunner $t): void {
        $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupScenarios(4);
        $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
        $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'checkpoint_mode'));
        $t->same([false, true, false, true], array_column($smallBatch, 'tail_recovery_checkpoint_followup_started_new_wal_header'));
        $t->same([[1321, 2221], [1322, 2222], [1323, 2223], [1324, 2224]], array_column($smallBatch, 'expected_tail_recovery_checkpoint_followup_pages'));
        $t->same([2, 2, 2, 2], array_map(static fn (array $scenario): int => $scenario['tail_recovery_checkpoint_followup_plan']['wal_frame_count_after'], $smallBatch));
    },
];

foreach ($tailFailureScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $followupPlan = $scenario['post_checkpoint_followup_plan'];
    $tailPlan = $scenario['post_checkpoint_tail_failure_plan'];
    $prefix = 'sqlite application wal rollback json post checkpoint tail failure seed ' . $seed . ' ';

    $tests[$prefix . 'starts after committed post-checkpoint followup'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same('ready', $followupPlan['status']);
        $t->same(2, $followupPlan['wal_frame_count_after']);
        $t->same(true, in_array($scenario['checkpoint_mode'], ['restart', 'truncate'], true));
    };
    $tests[$prefix . 'starts from followup database and wal hashes'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['post_checkpoint_followup_database_hash'], hash('sha256', (string) $tailPlan['database_bytes_before']));
        $t->same($scenario['post_checkpoint_followup_wal_hash'], hash('sha256', (string) $tailPlan['wal_bytes_after']));
    };
    $tests[$prefix . 'rolls back only the post-checkpoint tail batch'] = static function (TestRunner $t) use ($tailPlan): void {
        $t->same('rolled_back_current_json_batch', $tailPlan['status']);
        $t->same(true, $tailPlan['rollback_required']);
        $t->same(true, $tailPlan['database_restored_to_before']);
    };
    $tests[$prefix . 'uses post-checkpoint tail transaction and savepoint names'] = static function (TestRunner $t) use ($tailPlan, $seed): void {
        $t->same('application_post_checkpoint_tail_json_import_' . $seed, $tailPlan['transaction']);
        $t->same('post_checkpoint_tail_json_batch_' . $seed, $tailPlan['savepoint']);
    };
    $tests[$prefix . 'truncates three tail frames back to the followup prefix'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['committed_prefix_frame_count'] + 3, $tailPlan['wal_frame_count_before']);
        $t->same($scenario['committed_prefix_frame_count'], $tailPlan['wal_frame_count_after']);
        $t->same(3, $tailPlan['discarded_wal_frame_count']);
        $t->same(true, $tailPlan['wal_truncated']);
    };
    $tests[$prefix . 'discards only post-checkpoint tail frames'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $start = $scenario['committed_prefix_frame_count'] + 1;
        $t->same([$start, $start + 1], array_column($tailPlan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
        $t->same($scenario['expected_post_checkpoint_tail_pages'], $tailPlan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'restores only tail pages from outer rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $expected = $scenario['expected_post_checkpoint_tail_pages'];
        $restored = $tailPlan['rollback_to_savepoint']['restored_page_numbers'];
        sort($expected);
        sort($restored);
        $t->same($expected, $restored);
    };
    $tests[$prefix . 'records malformed tail statement'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same([$scenario['expected_failed_statement']], $tailPlan['failed_statements']);
        $t->same(1, $tailPlan['failed_statement_count']);
    };
    $tests[$prefix . 'applies catalog update and inserted tail row before failure'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same(2, $tailPlan['applied_statement_count']);
        $t->same($scenario['expected_post_checkpoint_tail_pages'], array_column($tailPlan['import_plan']['applied'], 'page_number'));
        $t->same($scenario['expected_post_checkpoint_tail_inserted_key'], $tailPlan['import_plan']['applied'][1]['key_name']);
    };
    $tests[$prefix . 'retains tenant id and jsonb mode in attempted tail rows'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($tailPlan['import_plan']['applied'], 'tenant_id'));
        $value = $tailPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'statement rollback restores malformed page and discards malformed frame'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $start = $scenario['committed_prefix_frame_count'] + 1;
        $failedRollback = $tailPlan['import_plan']['failed'][0]['rollback'];
        $t->same($start + 1, $failedRollback['rollback_to_wal_frame']);
        $t->same([$start + 2], array_column($failedRollback['discarded_wal_frames'], 'frame_index'));
        $t->same([$scenario['tail_broken_page']], $failedRollback['restored_page_numbers']);
    };
    $tests[$prefix . 'savepoint boundary carries committed followup pages'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $transactionPages = $tailPlan['import_plan']['savepoint_state'][0]['page_numbers'];
        $expectedPages = $scenario['committed_prefix_pages'];
        sort($transactionPages);
        sort($expectedPages);
        $t->same($expectedPages, $transactionPages);
    };
    $tests[$prefix . 'tail wal frame pages follow committed prefix'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $walBytes = (string) $tailPlan['wal_bytes_before'];
        foreach (array_merge($scenario['expected_post_checkpoint_tail_pages'], [$scenario['tail_broken_page']]) as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($walBytes, $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'tail wal checksums continue after followup prefix'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $walBytes = (string) $tailPlan['wal_bytes_before'];
        $checksumSeed = SQLiteWal::checksumPair(substr($walBytes, 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['committed_prefix_frame_count']; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($walBytes, $frameOffset, $frameSize);
            $checksumSeed = SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        foreach (array_merge($scenario['expected_post_checkpoint_tail_pages'], [$scenario['tail_broken_page']]) as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frame = substr($walBytes, $frameOffset, $frameSize);
            $checksumSeed = SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'outer rollback leaves database and rows at followup boundary'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['post_checkpoint_followup_database_hash'], hash('sha256', (string) $tailPlan['restored_database_bytes']));
        $t->same($scenario['post_checkpoint_followup_final_row_count'] + 1, count($tailPlan['import_plan']['final_rows']));
    };
}

foreach ($tailRecoveryScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $tailPlan = $scenario['post_checkpoint_tail_failure_plan'];
    $recoveryPlan = $scenario['post_checkpoint_tail_recovery_plan'];
    $prefix = 'sqlite application wal rollback json post checkpoint tail recovery seed ' . $seed . ' ';

    $tests[$prefix . 'starts after post-checkpoint tail rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same('rolled_back_current_json_batch', $tailPlan['status']);
        $t->same($scenario['post_checkpoint_tail_failure_database_hash'], hash('sha256', (string) $tailPlan['restored_database_bytes']));
        $t->same($scenario['post_checkpoint_tail_failure_wal_hash'], hash('sha256', (string) $tailPlan['wal_bytes_after']));
    };
    $tests[$prefix . 'commits corrected post-checkpoint recovery'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $t->same('ready', $recoveryPlan['status']);
        $t->same(false, $recoveryPlan['rollback_required']);
        $t->same(3, $recoveryPlan['applied_statement_count']);
        $t->same(0, $recoveryPlan['failed_statement_count']);
    };
    $tests[$prefix . 'uses post-checkpoint recovery transaction and savepoint names'] = static function (TestRunner $t) use ($recoveryPlan, $seed): void {
        $t->same('application_post_checkpoint_recovery_json_import_' . $seed, $recoveryPlan['transaction']);
        $t->same('post_checkpoint_recovery_json_batch_' . $seed, $recoveryPlan['savepoint']);
    };
    $tests[$prefix . 'starts from rolled back tail database and wal'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['post_checkpoint_tail_failure_database_hash'], hash('sha256', (string) $recoveryPlan['database_bytes_before']));
        $t->same($scenario['post_checkpoint_tail_failure_wal_hash'], hash('sha256', (string) $recoveryPlan['wal_bytes_before']));
    };
    $tests[$prefix . 'appends exactly recovery frames'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same(3, $recoveryPlan['materialized_wal_frame_count']);
        $t->same($scenario['committed_prefix_frame_count'] + 3, $recoveryPlan['wal_frame_count_after']);
        $t->same(0, $recoveryPlan['discarded_wal_frame_count']);
        $t->same(false, $recoveryPlan['wal_truncated']);
    };
    $tests[$prefix . 'records recovery page numbers and tenant ids'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['expected_post_checkpoint_recovery_pages'], array_column($recoveryPlan['import_plan']['applied'], 'page_number'));
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($recoveryPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'records corrected recovery insert'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['expected_post_checkpoint_recovery_inserted_key'], $recoveryPlan['import_plan']['applied'][1]['key_name']);
        $t->same(true, in_array($scenario['expected_post_checkpoint_recovery_inserted_id'], array_column($recoveryPlan['import_plan']['final_rows'], 'setting_id'), true));
        $t->same(true, $scenario['post_checkpoint_recovery_inserted_key_retained']);
    };
    $tests[$prefix . 'updates prior post-checkpoint followup insert'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['expected_followup_inserted_key'], $recoveryPlan['import_plan']['applied'][2]['key_name']);
        $value = $recoveryPlan['import_plan']['applied'][2]['key_value'];
        $decoded = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : [];
        $t->same(true, $decoded['post_checkpoint_recovery_seen'] ?? null);
    };
    $tests[$prefix . 'retains followup insert and excludes failed tail insert'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['post_checkpoint_followup_inserted_key_retained_after_recovery']);
        $t->same(false, $scenario['post_checkpoint_tail_inserted_key_retained_after_recovery']);
        $t->same(false, $scenario['rejected_prior_tail_key_retained_after_followup']);
        $t->same(false, $scenario['rejected_post_recovery_tail_key_retained_after_followup']);
    };
    $tests[$prefix . 'keeps jsonb mode on recovery catalog update'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $value = $recoveryPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'keeps inserted recovery row as canonical json text'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $value = $recoveryPlan['import_plan']['applied'][1]['key_value'];
        $t->same(true, is_string($value));
        $t->same(['recovered' => true], json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR));
    };
    $tests[$prefix . 'savepoint boundary carries post-checkpoint prefix pages'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $transactionPages = $recoveryPlan['import_plan']['savepoint_state'][0]['page_numbers'];
        $expectedPages = $scenario['committed_prefix_pages'];
        sort($transactionPages);
        sort($expectedPages);
        $t->same($expectedPages, $transactionPages);
    };
    $tests[$prefix . 'rollback preview remains scoped to recovery pages'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $expectedPages = $scenario['expected_post_checkpoint_recovery_pages'];
        $restoredPages = $recoveryPlan['rollback_to_savepoint']['restored_page_numbers'];
        $discardedPages = $recoveryPlan['wal_rollback_to_savepoint']['discarded_page_numbers'];
        sort($expectedPages);
        sort($restoredPages);
        sort($discardedPages);
        $t->same($expectedPages, $restoredPages);
        $t->same($expectedPages, $discardedPages);
    };
    $tests[$prefix . 'appended wal frame pages match recovery pages'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_post_checkpoint_recovery_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($recoveryPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'recovery wal checksums continue after tail rollback prefix'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = SQLiteWal::checksumPair(substr($recoveryPlan['wal_bytes_after'], 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['committed_prefix_frame_count']; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($recoveryPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        foreach ($scenario['expected_post_checkpoint_recovery_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frame = substr($recoveryPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'only final recovery frame is a commit frame'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_post_checkpoint_recovery_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($recoveryPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, 0, intdiv(strlen($recoveryPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'adds one final row over tail rollback boundary'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['post_checkpoint_tail_failure_row_count'] + 1, count($recoveryPlan['import_plan']['final_rows']));
    };
    $tests[$prefix . 'records recovery dependencies'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $t->same(true, in_array('sqlite-application-json-import-savepoint-current', $recoveryPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-savepoint-wal-rollback-current', $recoveryPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-current-batch-byte-truncation', $recoveryPlan['dependencies'], true));
    };
    $tests[$prefix . 'records recovery statement names'] = static function (TestRunner $t) use ($recoveryPlan, $seed): void {
        $t->same([
            'post_checkpoint_recovery_catalog_' . $seed,
            'post_checkpoint_recovery_insert_' . $seed,
            'post_checkpoint_recovery_followup_seen_' . $seed,
        ], array_column($recoveryPlan['import_plan']['applied'], 'statement'));
    };
    $tests[$prefix . 'has no failed statements after recovery commit'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $t->same([], $recoveryPlan['failed_statements']);
        $t->same([], $recoveryPlan['import_plan']['failed']);
    };
}

foreach ($tailRecoveryCheckpointScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $recoveryPlan = $scenario['post_checkpoint_tail_recovery_plan'];
    $checkpointPlan = $scenario['tail_recovery_checkpoint_plan'];
    $releasedCheckpoint = $scenario['tail_recovery_released_checkpoint'];
    $pinnedCheckpoint = $scenario['tail_recovery_pinned_checkpoint'];
    $prefix = 'sqlite application wal rollback json post checkpoint tail recovery checkpoint seed ' . $seed . ' ';

    $tests[$prefix . 'starts from committed tail recovery wal bytes'] = static function (TestRunner $t) use ($scenario, $recoveryPlan): void {
        $t->same('ready', $recoveryPlan['status']);
        $t->same(5, $recoveryPlan['wal_frame_count_after']);
        $t->same($scenario['tail_recovery_checkpoint_database_bytes_before_hash'], hash('sha256', (string) $recoveryPlan['database_bytes_before']));
    };
    $tests[$prefix . 'has one committed recovery transaction after the followup prefix'] = static function (TestRunner $t) use ($checkpointPlan): void {
        $t->same(5, $checkpointPlan['last_commit_frame']);
        $t->same([1, 2, 3, 4, 5], array_column($checkpointPlan['frames'], 'frame_index'));
        $t->same(['superseded_by_later_committed_frame', 'superseded_by_later_committed_frame', 'checkpointed_to_database', 'checkpointed_to_database', 'checkpointed_to_database'], array_column($checkpointPlan['frames'], 'reason'));
    };
    $tests[$prefix . 'checkpoint applies only latest recovery frame images'] = static function (TestRunner $t) use ($scenario): void {
        $t->same([3, 4, 5], $scenario['tail_recovery_applied_frame_indexes']);
        $t->same($scenario['expected_post_checkpoint_recovery_pages'], $scenario['tail_recovery_applied_page_numbers']);
        $t->same([1, 2], $scenario['tail_recovery_superseded_frame_indexes']);
        $t->same($scenario['committed_prefix_pages'], $scenario['tail_recovery_superseded_page_numbers']);
    };
    $tests[$prefix . 'released checkpoint materializes corrected recovery pages'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        $t->same(true, $scenario['tail_recovery_checkpointed_pages_match']);
        $t->same(3, $releasedCheckpoint['checkpointed_frame_count']);
        $t->same(0, $releasedCheckpoint['remaining_committed_frame_count']);
        $t->same(0, $releasedCheckpoint['uncommitted_frame_count']);
    };
    $tests[$prefix . 'released checkpoint resets or truncates wal generation'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        $t->same(false, $releasedCheckpoint['busy']);
        $t->same($scenario['checkpoint_mode'], $releasedCheckpoint['mode']);
        $t->same($scenario['expected_tail_recovery_checkpoint_action'], $releasedCheckpoint['wal_action']);
        $t->same($scenario['expected_tail_recovery_released_wal_bytes_length'], $releasedCheckpoint['wal_bytes_length']);
        $t->same($scenario['checkpoint_mode'] === 'truncate' ? null : 'array', is_array($releasedCheckpoint['wal_header']) ? 'array' : null);
    };
    $tests[$prefix . 'pinned reader preserves final recovery frame'] = static function (TestRunner $t) use ($scenario, $pinnedCheckpoint): void {
        $t->same(true, $pinnedCheckpoint['busy']);
        $t->same('reader_blocks_checkpoint_completion', $pinnedCheckpoint['reason']);
        $t->same('preserve_wal', $pinnedCheckpoint['wal_action']);
        $t->same($scenario['tail_recovery_checkpoint_reader_end_frame'], $pinnedCheckpoint['reader_end_frame']);
        $t->same(2, $pinnedCheckpoint['checkpointed_frame_count']);
        $t->same(1, $pinnedCheckpoint['remaining_committed_frame_count']);
    };
    $tests[$prefix . 'pinned reader applies catalog and inserted recovery pages only'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['tail_recovery_pinned_catalog_matches_recovery']);
        $t->same(true, $scenario['tail_recovery_pinned_insert_page_matches_recovery']);
        $t->same(false, $scenario['tail_recovery_pinned_followup_page_matches_final_recovery']);
    };
    $tests[$prefix . 'released database contains corrected inserted recovery row page'] = static function (TestRunner $t) use ($releasedCheckpoint, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $insertPage = (int) $scenario['expected_post_checkpoint_recovery_pages'][1];
        $offset = ($insertPage - 1) * $pageSize;
        $page = substr((string) $releasedCheckpoint['database_bytes'], $offset, $pageSize);
        $t->contains('post_checkpoint_recovery_payload_' . $scenario['seed'], $page);
    };
    $tests[$prefix . 'released database materializes final followup page image'] = static function (TestRunner $t) use ($releasedCheckpoint, $pinnedCheckpoint, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $followupPage = (int) $scenario['expected_post_checkpoint_recovery_pages'][2];
        $offset = ($followupPage - 1) * $pageSize;
        $releasedPage = substr((string) $releasedCheckpoint['database_bytes'], $offset, $pageSize);
        $pinnedPage = substr((string) $pinnedCheckpoint['database_bytes'], $offset, $pageSize);
        $t->contains($scenario['expected_followup_inserted_key'], $releasedPage);
        $t->same(false, $releasedPage === $pinnedPage);
    };
    $tests[$prefix . 'pinned database has not materialized final followup frame'] = static function (TestRunner $t) use ($releasedCheckpoint, $pinnedCheckpoint, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $followupPage = (int) $scenario['expected_post_checkpoint_recovery_pages'][2];
        $offset = ($followupPage - 1) * $pageSize;
        $releasedPage = substr((string) $releasedCheckpoint['database_bytes'], $offset, $pageSize);
        $pinnedPage = substr((string) $pinnedCheckpoint['database_bytes'], $offset, $pageSize);
        $t->contains($scenario['expected_followup_inserted_key'], $pinnedPage);
        $t->same(false, $pinnedPage === $releasedPage);
    };
    $tests[$prefix . 'keeps corrected row keys and excludes rejected tail key'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['tail_recovery_inserted_key_retained_after_checkpoint']);
        $t->same(false, $scenario['tail_recovery_rejected_tail_key_retained_after_checkpoint']);
        $t->same(true, $scenario['post_checkpoint_followup_inserted_key_retained_after_recovery']);
    };
    $tests[$prefix . 'records durable checkpoint dependencies'] = static function (TestRunner $t) use ($releasedCheckpoint): void {
        $t->same(true, in_array('sqlite-wal-checkpoint', $releasedCheckpoint['dependencies'], true));
        $t->same(true, in_array('durable-sidecar-write', $releasedCheckpoint['dependencies'], true));
    };
}

foreach ($tailRecoveryCheckpointFollowupScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $releasedCheckpoint = $scenario['tail_recovery_released_checkpoint'];
    $recoveryPlan = $scenario['post_checkpoint_tail_recovery_plan'];
    $followupPlan = $scenario['tail_recovery_checkpoint_followup_plan'];
    $prefix = 'sqlite application wal rollback json post checkpoint tail recovery checkpoint followup seed ' . $seed . ' ';

    $tests[$prefix . 'starts from released tail recovery checkpoint bytes'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        $t->same(false, $releasedCheckpoint['busy']);
        $t->same(true, $scenario['tail_recovery_checkpointed_pages_match']);
        $t->same($scenario['tail_recovery_checkpoint_followup_input_database_hash'], hash('sha256', (string) $releasedCheckpoint['database_bytes']));
    };
    $tests[$prefix . 'uses reset or truncated checkpoint wal header'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same(0, $followupPlan['wal_frame_count_before']);
        $t->same(32, $scenario['tail_recovery_checkpoint_followup_wal_header_length']);
        $t->same($scenario['checkpoint_mode'] === 'truncate', $scenario['tail_recovery_checkpoint_followup_started_new_wal_header']);
    };
    $tests[$prefix . 'commits a fresh two frame followup transaction'] = static function (TestRunner $t) use ($followupPlan): void {
        $t->same('ready', $followupPlan['status']);
        $t->same(false, $followupPlan['rollback_required']);
        $t->same(2, $followupPlan['materialized_wal_frame_count']);
        $t->same(2, $followupPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'uses tail recovery checkpoint followup names'] = static function (TestRunner $t) use ($followupPlan, $seed): void {
        $t->same('application_tail_recovery_checkpoint_followup_json_import_' . $seed, $followupPlan['transaction']);
        $t->same('tail_recovery_checkpoint_followup_json_batch_' . $seed, $followupPlan['savepoint']);
    };
    $tests[$prefix . 'starts from released checkpoint database image'] = static function (TestRunner $t) use ($followupPlan, $releasedCheckpoint): void {
        $t->same((string) $releasedCheckpoint['database_bytes'], $followupPlan['database_bytes_before']);
    };
    $tests[$prefix . 'records followup applied pages'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['expected_tail_recovery_checkpoint_followup_pages'], array_column($followupPlan['import_plan']['applied'], 'page_number'));
    };
    $tests[$prefix . 'retains tenant id and jsonb mode in catalog row'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($followupPlan['import_plan']['applied'], 'tenant_id'));
        $value = $followupPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'inserts only one new durable followup row'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['tail_recovery_checkpoint_followup_base_row_count'] + 1, count($followupPlan['import_plan']['final_rows']));
        $t->same($scenario['expected_tail_recovery_checkpoint_followup_inserted_key'], $followupPlan['import_plan']['applied'][1]['key_name']);
        $rowsByKey = [];
        foreach ($followupPlan['import_plan']['final_rows'] as $row) {
            $rowsByKey[$row['key_name']] = $row;
        }
        $t->same($scenario['expected_tail_recovery_checkpoint_followup_inserted_id'], $rowsByKey[$scenario['expected_tail_recovery_checkpoint_followup_inserted_key']]['setting_id']);
    };
    $tests[$prefix . 'keeps corrected recovery rows and excludes rolled back tail'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['tail_recovery_checkpoint_followup_inserted_key_retained']);
        $t->same(true, $scenario['tail_recovery_checkpoint_followup_recovery_key_retained']);
        $t->same(true, $scenario['tail_recovery_checkpoint_followup_prior_followup_key_retained']);
        $t->same(false, $scenario['tail_recovery_checkpoint_followup_rejected_tail_key_retained']);
    };
    $tests[$prefix . 'does not inherit failed statements from previous tail rollback'] = static function (TestRunner $t) use ($followupPlan, $recoveryPlan): void {
        $t->same([], $followupPlan['failed_statements']);
        $t->same([], $followupPlan['import_plan']['failed']);
        $t->same([], $recoveryPlan['failed_statements']);
    };
    $tests[$prefix . 'rollback preview begins at empty checkpoint wal generation'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same(0, $followupPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $t->same($scenario['expected_tail_recovery_checkpoint_followup_pages'], $followupPlan['wal_rollback_to_savepoint']['discarded_page_numbers']);
    };
    $tests[$prefix . 'followup wal frame pages start at frame one'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_tail_recovery_checkpoint_followup_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frameHeader = unpack('Npage_number', substr($followupPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'followup wal checksums start from reset checkpoint header'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $walBytes = (string) $followupPlan['wal_bytes_after'];
        $checksumSeed = SQLiteWal::checksumPair(substr($walBytes, 0, 24), false);
        foreach ($scenario['expected_tail_recovery_checkpoint_followup_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frame = substr($walBytes, $frameOffset, $frameSize);
            $checksumSeed = SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
            $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($frame, 0, 24));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
            $t->same($checksumSeed, [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']]);
        }
    };
    $tests[$prefix . 'only final followup frame is a commit frame'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_tail_recovery_checkpoint_followup_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($followupPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, intdiv(strlen($followupPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'records source hashes and dependencies'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['tail_recovery_checkpoint_followup_input_wal_hash'], hash('sha256', $scenario['tail_recovery_checkpoint_followup_wal_bytes']));
        $t->same(true, in_array('sqlite-application-json-import-savepoint-current', $followupPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-current-batch-byte-truncation', $followupPlan['dependencies'], true));
    };
}

return $tests;
