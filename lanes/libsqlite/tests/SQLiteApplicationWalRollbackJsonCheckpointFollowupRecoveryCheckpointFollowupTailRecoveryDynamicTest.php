<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;
use PortLibs\LibSqlite\SQLiteWal;

ini_set('memory_limit', '1536M');
unset($tests);

$tailFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailFailureScenarios(18);
$tailRecoveryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryScenariosFromTailFailureScenarios($tailFailureScenarios);
$directTailRecoveryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryScenarios(18);

$tests = [
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery exposes requested scenario count' => static function (TestRunner $t) use ($tailRecoveryScenarios): void {
        $t->same(18, count($tailRecoveryScenarios));
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery direct factory matches base factory' => static function (TestRunner $t) use ($tailRecoveryScenarios, $directTailRecoveryScenarios): void {
        $t->same(array_column($tailRecoveryScenarios, 'tenant_id'), array_column($directTailRecoveryScenarios, 'tenant_id'));
        $t->same(array_column($tailRecoveryScenarios, 'expected_followup_recovery_checkpoint_followup_tail_recovery_pages'), array_column($directTailRecoveryScenarios, 'expected_followup_recovery_checkpoint_followup_tail_recovery_pages'));
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery covers checkpoint reset modes' => static function (TestRunner $t) use ($tailRecoveryScenarios): void {
        $modes = array_values(array_unique(array_column($tailRecoveryScenarios, 'followup_recovery_checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery covers both page sizes' => static function (TestRunner $t) use ($tailRecoveryScenarios): void {
        $pageSizes = array_values(array_unique(array_column($tailRecoveryScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery covers json text and jsonb rows' => static function (TestRunner $t) use ($tailRecoveryScenarios): void {
        $jsonModes = array_values(array_unique(array_column($tailRecoveryScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery rejects zero scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryScenarios(0);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery rejects empty failure base scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryScenariosFromTailFailureScenarios([]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery small batch remains deterministic' => static function (TestRunner $t): void {
        $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryScenarios(4);
        $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
        $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'followup_recovery_checkpoint_mode'));
        $t->same([[1321, 3021, 2621], [1322, 3022, 2622], [1323, 3023, 2623], [1324, 3024, 2624]], array_column($smallBatch, 'expected_followup_recovery_checkpoint_followup_tail_recovery_pages'));
        $t->same([3, 3, 3, 3], array_map(static fn (array $scenario): int => $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_plan']['wal_frame_count_before'], $smallBatch));
        $t->same([6, 6, 6, 6], array_map(static fn (array $scenario): int => $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_plan']['wal_frame_count_after'], $smallBatch));
    },
];

foreach ($tailRecoveryScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $tailPlan = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_plan'];
    $recoveryPlan = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_plan'];
    $prefix = 'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail recovery seed ' . $seed . ' ';

    $tests[$prefix . 'starts after final followup tail rollback'] = static function (TestRunner $t) use ($tailPlan, $recoveryPlan, $scenario): void {
        $t->same('rolled_back_current_json_batch', $tailPlan['status']);
        $t->same(3, $tailPlan['wal_frame_count_after']);
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_recovery_input_database_hash'], hash('sha256', (string) $recoveryPlan['database_bytes_before']));
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_recovery_input_wal_hash'], hash('sha256', (string) $recoveryPlan['wal_bytes_before']));
    };
    $tests[$prefix . 'commits corrected final-followup tail recovery'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $t->same('ready', $recoveryPlan['status']);
        $t->same(false, $recoveryPlan['rollback_required']);
        $t->same(3, $recoveryPlan['applied_statement_count']);
        $t->same(0, $recoveryPlan['failed_statement_count']);
    };
    $tests[$prefix . 'uses final-followup tail recovery transaction and savepoint names'] = static function (TestRunner $t) use ($recoveryPlan, $seed): void {
        $t->same('application_followup_recovery_checkpoint_followup_tail_recovery_json_import_' . $seed, $recoveryPlan['transaction']);
        $t->same('followup_recovery_checkpoint_followup_tail_recovery_json_batch_' . $seed, $recoveryPlan['savepoint']);
    };
    $tests[$prefix . 'appends exactly recovery frames after committed final followup prefix'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['committed_prefix_frame_count_after_final_tail_failure'], $recoveryPlan['wal_frame_count_before']);
        $t->same($scenario['committed_prefix_frame_count_after_final_tail_failure'] + 3, $recoveryPlan['wal_frame_count_after']);
        $t->same(3, $recoveryPlan['materialized_wal_frame_count']);
        $t->same(0, $recoveryPlan['discarded_wal_frame_count']);
        $t->same(false, $recoveryPlan['wal_truncated']);
    };
    $tests[$prefix . 'records recovery page numbers and tenant ids'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_pages'], array_column($recoveryPlan['import_plan']['applied'], 'page_number'));
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($recoveryPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'records corrected final tail recovery insert'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_inserted_key'], $recoveryPlan['import_plan']['applied'][1]['key_name']);
        $t->same(true, in_array($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_inserted_id'], array_column($recoveryPlan['import_plan']['final_rows'], 'setting_id'), true));
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_inserted_key_retained']);
    };
    $tests[$prefix . 'updates the prior final followup row'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_inserted_key'], $recoveryPlan['import_plan']['applied'][2]['key_name']);
        $value = $recoveryPlan['import_plan']['applied'][2]['key_value'];
        $decoded = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : [];
        $t->same(true, $decoded['after_final_followup_tail_recovery_seen'] ?? null);
    };
    $tests[$prefix . 'retains committed rows and excludes failed tail rows'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_final_followup_key_retained']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_prior_recovery_key_retained']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_prior_followup_key_retained']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_failed_tail_key_retained']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_failed_bad_key_retained']);
    };
    $tests[$prefix . 'keeps jsonb mode on recovery catalog update'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $value = $recoveryPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'keeps inserted recovery row as canonical json text'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $value = $recoveryPlan['import_plan']['applied'][1]['key_value'];
        $t->same(true, is_string($value));
        $t->same(['recovered_after_final_followup_tail' => true], json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR));
    };
    $tests[$prefix . 'savepoint boundary carries final followup prefix pages'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $transactionPages = $recoveryPlan['import_plan']['savepoint_state'][0]['page_numbers'];
        $expectedPages = $scenario['expected_followup_recovery_checkpoint_followup_pages'];
        sort($transactionPages);
        sort($expectedPages);
        $t->same($expectedPages, $transactionPages);
    };
    $tests[$prefix . 'rollback preview remains scoped to recovery pages'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $expectedPages = $scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_pages'];
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
        foreach ($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count_after_final_tail_failure'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number', substr($recoveryPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'recovery wal checksums continue after final followup prefix'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $checksumSeed = SQLiteWal::checksumPair(substr($recoveryPlan['wal_bytes_after'], 0, 24), false);
        for ($frameIndex = 0; $frameIndex < $scenario['committed_prefix_frame_count_after_final_tail_failure']; $frameIndex++) {
            $frameOffset = 32 + ($frameIndex * $frameSize);
            $frame = substr($recoveryPlan['wal_bytes_after'], $frameOffset, $frameSize);
            $checksumSeed = SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        }
        foreach ($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count_after_final_tail_failure'] + $index) * $frameSize);
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
        foreach ($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + (($scenario['committed_prefix_frame_count_after_final_tail_failure'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($recoveryPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, 0, intdiv(strlen($recoveryPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'adds one final row over committed final followup boundary'] = static function (TestRunner $t) use ($recoveryPlan, $scenario): void {
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_recovery_base_row_count'] + 1, count($recoveryPlan['import_plan']['final_rows']));
    };
    $tests[$prefix . 'records recovery dependencies'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $t->same(true, in_array('sqlite-application-json-import-savepoint-current', $recoveryPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-savepoint-wal-rollback-current', $recoveryPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-current-batch-byte-truncation', $recoveryPlan['dependencies'], true));
    };
    $tests[$prefix . 'has no failed statements after corrected recovery commit'] = static function (TestRunner $t) use ($recoveryPlan): void {
        $t->same([], $recoveryPlan['failed_statements']);
        $t->same([], $recoveryPlan['import_plan']['failed']);
    };
}

unset(
    $tailFailureScenarios,
    $tailRecoveryScenarios,
    $directTailRecoveryScenarios,
    $scenario,
    $seed,
    $tailPlan,
    $recoveryPlan,
    $prefix
);

return $tests;
