<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;
use PortLibs\LibSqlite\SQLiteWal;

$tailFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailFailureScenarios(18);
$tailRecoveryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryScenariosFromTailFailureScenarios($tailFailureScenarios);

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

return $tests;
