<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

ini_set('memory_limit', '1536M');

$followupScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupScenarios(18);
$tailFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailFailureScenariosFromFollowupScenarios($followupScenarios);
$directTailFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailFailureScenarios(18);

$tests = [
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail failure exposes requested scenario count' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $t->same(18, count($tailFailureScenarios));
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail failure direct factory matches base factory' => static function (TestRunner $t) use ($tailFailureScenarios, $directTailFailureScenarios): void {
        $t->same(array_column($tailFailureScenarios, 'tenant_id'), array_column($directTailFailureScenarios, 'tenant_id'));
        $t->same(array_column($tailFailureScenarios, 'expected_followup_recovery_checkpoint_followup_tail_pages'), array_column($directTailFailureScenarios, 'expected_followup_recovery_checkpoint_followup_tail_pages'));
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail failure covers checkpoint reset modes' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $modes = array_values(array_unique(array_column($tailFailureScenarios, 'followup_recovery_checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail failure covers both page sizes' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $pageSizes = array_values(array_unique(array_column($tailFailureScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail failure covers json text and jsonb rows' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $jsonModes = array_values(array_unique(array_column($tailFailureScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail failure rejects zero scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailFailureScenarios(0);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail failure rejects empty followup base scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailFailureScenariosFromFollowupScenarios([]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail failure small batch remains deterministic' => static function (TestRunner $t): void {
        $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailFailureScenarios(4);
        $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
        $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'followup_recovery_checkpoint_mode'));
        $t->same([[1321, 2821], [1322, 2822], [1323, 2823], [1324, 2824]], array_column($smallBatch, 'expected_followup_recovery_checkpoint_followup_tail_pages'));
        $t->same([6, 6, 6, 6], array_map(static fn (array $scenario): int => $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_plan']['wal_frame_count_before'], $smallBatch));
        $t->same([3, 3, 3, 3], array_map(static fn (array $scenario): int => $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_plan']['wal_frame_count_after'], $smallBatch));
    },
];

foreach ($tailFailureScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $followupPlan = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_plan'];
    $tailPlan = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_plan'];
    $prefix = 'sqlite application wal rollback json checkpoint followup recovery checkpoint followup tail failure seed ' . $seed . ' ';

    $tests[$prefix . 'starts from committed final followup state'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same('ready', $followupPlan['status']);
        $t->same(3, $followupPlan['wal_frame_count_after']);
        $t->same(hash('sha256', (string) $followupPlan['database_bytes_after_import']), $scenario['followup_recovery_checkpoint_followup_tail_input_database_hash']);
        $t->same(hash('sha256', (string) $followupPlan['wal_bytes_after']), $scenario['followup_recovery_checkpoint_followup_tail_input_wal_hash']);
    };
    $tests[$prefix . 'rolls back only the new final-followup tail batch'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same('rolled_back_current_json_batch', $tailPlan['status']);
        $t->same(true, $tailPlan['rollback_required']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_restored_to_final_followup_database']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_wal_truncated_to_final_followup_prefix']);
    };
    $tests[$prefix . 'uses final-followup tail transaction and savepoint names'] = static function (TestRunner $t) use ($tailPlan, $seed): void {
        $t->same('application_followup_recovery_checkpoint_followup_tail_json_import_' . $seed, $tailPlan['transaction']);
        $t->same('followup_recovery_checkpoint_followup_tail_json_batch_' . $seed, $tailPlan['savepoint']);
    };
    $tests[$prefix . 'preserves final followup WAL prefix and truncates tail frames'] = static function (TestRunner $t) use ($followupPlan, $tailPlan): void {
        $t->same($followupPlan['wal_bytes_after'], $tailPlan['wal_bytes_after']);
        $t->same(6, $tailPlan['wal_frame_count_before']);
        $t->same(3, $tailPlan['wal_frame_count_after']);
        $t->same(3, $tailPlan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'keeps final followup database image after rollback'] = static function (TestRunner $t) use ($followupPlan, $tailPlan): void {
        $t->same($followupPlan['database_bytes_after_import'], $tailPlan['restored_database_bytes']);
        $t->same($followupPlan['database_bytes_after_import'], $tailPlan['database_bytes_before']);
        $t->same(true, $tailPlan['database_changed_before_rollback']);
    };
    $tests[$prefix . 'records tail applied pages and tenant ids before rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_pages'], array_column($tailPlan['import_plan']['applied'], 'page_number'));
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($tailPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'inserts tail row before outer rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_inserted_key_visible_before_outer_rollback']);
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_inserted_key'], $tailPlan['import_plan']['applied'][1]['key_name']);
        $t->same(true, $tailPlan['import_plan']['applied'][1]['inserted_setting']);
    };
    $tests[$prefix . 'removes malformed inserted row at statement rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_failed_key_visible_before_outer_rollback']);
        $t->same([$scenario['expected_followup_recovery_checkpoint_followup_tail_failed_statement']], $tailPlan['failed_statements']);
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_failed_key'], $tailPlan['import_plan']['failed'][0]['key_name']);
        $t->contains('SQLite JSON5 input ended before a value', $tailPlan['import_plan']['failed'][0]['error']);
    };
    $tests[$prefix . 'restores only failed inserted page at statement level'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_failed_id'], $tailPlan['import_plan']['failed'][0]['tenant_id'] === $scenario['tenant_id'] ? $scenario['expected_followup_recovery_checkpoint_followup_tail_failed_id'] : null);
        $t->same([2920 + $scenario['seed']], $tailPlan['import_plan']['failed'][0]['rollback']['restored_page_numbers']);
        $t->same([$scenario['followup_recovery_checkpoint_followup_tail_started_after_frame'] + 3], $scenario['followup_recovery_checkpoint_followup_tail_failed_statement_discarded_frame_indexes']);
    };
    $tests[$prefix . 'outer rollback restores successful tail pages'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $restoredPages = $tailPlan['rollback_to_savepoint']['restored_page_numbers'];
        sort($restoredPages);
        $expectedPages = $scenario['expected_followup_recovery_checkpoint_followup_tail_pages'];
        sort($expectedPages);
        $t->same($expectedPages, $restoredPages);
        $t->same([$scenario['followup_recovery_checkpoint_followup_tail_started_after_frame'] + 1, $scenario['followup_recovery_checkpoint_followup_tail_started_after_frame'] + 2], $scenario['followup_recovery_checkpoint_followup_tail_outer_discarded_frame_indexes']);
    };
    $tests[$prefix . 'retains previous successful keys before outer rollback preview'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_final_followup_key_retained_before_outer_rollback']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_key_retained_before_outer_rollback']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_prior_followup_key_retained']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_failed_tail_key_retained']);
    };
    $tests[$prefix . 'keeps jsonb mode on tail catalog update'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $value = $tailPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'tail inserted row remains canonical json text before outer rollback'] = static function (TestRunner $t) use ($tailPlan): void {
        $value = $tailPlan['import_plan']['applied'][1]['key_value'];
        $t->same(true, is_string($value));
        $t->same(['tail_after_final_followup' => true], json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR));
    };
    $tests[$prefix . 'rollback preview begins after final followup frame prefix'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_started_after_frame'], $tailPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $discardedPages = $tailPlan['wal_rollback_to_savepoint']['discarded_page_numbers'];
        $expectedPages = $scenario['expected_followup_recovery_checkpoint_followup_tail_pages'];
        sort($discardedPages);
        sort($expectedPages);
        $t->same($expectedPages, $discardedPages);
    };
    $tests[$prefix . 'tail wal input contains appended success and failed frames'] = static function (TestRunner $t) use ($scenario, $tailPlan): void {
        $t->same(hash('sha256', $scenario['followup_recovery_checkpoint_followup_tail_wal_bytes']), hash('sha256', $tailPlan['wal_bytes_before']));
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_started_after_frame'] + 3, $tailPlan['wal_frame_count_before']);
    };
    $tests[$prefix . 'tail wal output is byte prefix of input'] = static function (TestRunner $t) use ($tailPlan): void {
        $t->same(substr($tailPlan['wal_bytes_before'], 0, strlen($tailPlan['wal_bytes_after'])), $tailPlan['wal_bytes_after']);
        $t->same(true, strlen($tailPlan['wal_bytes_after']) < strlen($tailPlan['wal_bytes_before']));
        $t->same(strlen($tailPlan['wal_bytes_after']), $tailPlan['wal_truncate_to_bytes']);
    };
    $tests[$prefix . 'records source dependencies'] = static function (TestRunner $t) use ($tailPlan): void {
        $t->same(true, in_array('sqlite-application-json-import-savepoint-current', $tailPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-savepoint-wal-rollback-current', $tailPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-current-batch-byte-truncation', $tailPlan['dependencies'], true));
    };
}

return $tests;
