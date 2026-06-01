<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;
use PortLibs\LibSqlite\SQLiteWal;

ini_set('memory_limit', '1536M');
unset($tests);

$followupScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupScenarios(18);
$tailFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailFailureScenariosFromFollowupScenarios($followupScenarios);
$directTailFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailFailureScenarios(18);

function application_wal_rollback_json_final_checkpoint_followup_tail_frame_header(string $walBytes, int $pageSize, int $frameIndex): array
{
    $frameOffset = 32 + (($frameIndex - 1) * (24 + $pageSize));
    $header = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($walBytes, $frameOffset, 24));
    if ($header === false) {
        throw new RuntimeException('Unable to decode final checkpoint followup tail WAL frame header');
    }

    return [
        'page_number' => (int) $header['page_number'],
        'commit' => (int) $header['commit'],
        'checksum_1' => (int) $header['checksum_1'],
        'checksum_2' => (int) $header['checksum_2'],
    ];
}

function application_wal_rollback_json_final_checkpoint_followup_tail_checksum_pairs(string $walBytes, int $pageSize, int $frameCount): array
{
    $frameSize = 24 + $pageSize;
    $checksumSeed = SQLiteWal::checksumPair(substr($walBytes, 0, 24), false);
    $pairs = [];
    for ($frameIndex = 1; $frameIndex <= $frameCount; $frameIndex++) {
        $frameOffset = 32 + (($frameIndex - 1) * $frameSize);
        $frame = substr($walBytes, $frameOffset, $frameSize);
        $checksumSeed = SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        $pairs[$frameIndex] = [$checksumSeed[0], $checksumSeed[1]];
    }

    return $pairs;
}

$tests = [
    'sqlite application wal rollback json final checkpoint followup tail failure exposes requested scenario count' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $t->same(18, count($tailFailureScenarios));
    },
    'sqlite application wal rollback json final checkpoint followup tail failure direct factory matches base factory' => static function (TestRunner $t) use ($tailFailureScenarios, $directTailFailureScenarios): void {
        $t->same(array_column($tailFailureScenarios, 'tenant_id'), array_column($directTailFailureScenarios, 'tenant_id'));
        $t->same(array_column($tailFailureScenarios, 'expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_pages'), array_column($directTailFailureScenarios, 'expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_pages'));
    },
    'sqlite application wal rollback json final checkpoint followup tail failure covers checkpoint reset modes' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $modes = array_values(array_unique(array_column($tailFailureScenarios, 'followup_recovery_checkpoint_followup_tail_recovery_checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json final checkpoint followup tail failure covers both page sizes' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $pageSizes = array_values(array_unique(array_column($tailFailureScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json final checkpoint followup tail failure covers json text and jsonb rows' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $jsonModes = array_values(array_unique(array_column($tailFailureScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json final checkpoint followup tail failure preserves restart truncate header origin' => static function (TestRunner $t) use ($tailFailureScenarios): void {
        $started = array_values(array_unique(array_column($tailFailureScenarios, 'followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_started_new_wal_header')));
        sort($started);
        $t->same([false, true], $started);
    },
    'sqlite application wal rollback json final checkpoint followup tail failure rejects zero scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailFailureScenarios(0);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json final checkpoint followup tail failure rejects empty followup base scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailFailureScenariosFromFollowupScenarios([]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json final checkpoint followup tail failure small batch remains deterministic' => static function (TestRunner $t): void {
        $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupTailFailureScenarios(4);
        $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
        $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'followup_recovery_checkpoint_followup_tail_recovery_checkpoint_mode'));
        $t->same([false, true, false, true], array_column($smallBatch, 'followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_started_new_wal_header'));
        $t->same([[1321, 3421], [1322, 3422], [1323, 3423], [1324, 3424]], array_column($smallBatch, 'expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_pages'));
        $t->same([6, 6, 6, 6], array_map(static fn (array $scenario): int => $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_plan']['wal_frame_count_before'], $smallBatch));
        $t->same([3, 3, 3, 3], array_map(static fn (array $scenario): int => $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_plan']['wal_frame_count_after'], $smallBatch));
    },
];

foreach ($tailFailureScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $followupPlan = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_plan'];
    $tailPlan = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_plan'];
    $prefix = 'sqlite application wal rollback json final checkpoint followup tail failure seed ' . $seed . ' ';

    $tests[$prefix . 'starts from committed final checkpoint followup state'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same('ready', $followupPlan['status']);
        $t->same(3, $followupPlan['wal_frame_count_after']);
        $t->same(hash('sha256', (string) $followupPlan['database_bytes_after_import']), $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_input_database_hash']);
        $t->same(hash('sha256', (string) $followupPlan['wal_bytes_after']), $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_input_wal_hash']);
    };
    $tests[$prefix . 'rolls back only the new final checkpoint followup tail batch'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same('rolled_back_current_json_batch', $tailPlan['status']);
        $t->same(true, $tailPlan['rollback_required']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_restored_to_final_followup_database']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_wal_truncated_to_final_followup_prefix']);
    };
    $tests[$prefix . 'uses final checkpoint followup tail transaction and savepoint names'] = static function (TestRunner $t) use ($tailPlan, $seed): void {
        $t->same('application_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_json_import_' . $seed, $tailPlan['transaction']);
        $t->same('followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_json_batch_' . $seed, $tailPlan['savepoint']);
    };
    $tests[$prefix . 'preserves final checkpoint followup WAL prefix and truncates tail frames'] = static function (TestRunner $t) use ($followupPlan, $tailPlan): void {
        $t->same($followupPlan['wal_bytes_after'], $tailPlan['wal_bytes_after']);
        $t->same(6, $tailPlan['wal_frame_count_before']);
        $t->same(3, $tailPlan['wal_frame_count_after']);
        $t->same(3, $tailPlan['discarded_wal_frame_count']);
    };
    $tests[$prefix . 'keeps final checkpoint followup database image after rollback'] = static function (TestRunner $t) use ($followupPlan, $tailPlan): void {
        $t->same($followupPlan['database_bytes_after_import'], $tailPlan['restored_database_bytes']);
        $t->same($followupPlan['database_bytes_after_import'], $tailPlan['database_bytes_before']);
        $t->same(true, $tailPlan['database_changed_before_rollback']);
    };
    $tests[$prefix . 'records tail applied pages and tenant ids before rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_pages'], array_column($tailPlan['import_plan']['applied'], 'page_number'));
        $t->same([$scenario['tenant_id'], $scenario['tenant_id']], array_column($tailPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'inserts tail row before outer rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_inserted_key_visible_before_outer_rollback']);
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_inserted_key'], $tailPlan['import_plan']['applied'][1]['key_name']);
        $t->same(true, $tailPlan['import_plan']['applied'][1]['inserted_setting']);
    };
    $tests[$prefix . 'removes malformed inserted row at statement rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_failed_key_visible_before_outer_rollback']);
        $t->same([$scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_failed_statement']], $tailPlan['failed_statements']);
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_failed_key'], $tailPlan['import_plan']['failed'][0]['key_name']);
        $t->contains('SQLite JSON5 input ended before a value', $tailPlan['import_plan']['failed'][0]['error']);
    };
    $tests[$prefix . 'restores only failed inserted page at statement level'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_failed_id'], $scenario['tenant_id'] === $tailPlan['import_plan']['failed'][0]['tenant_id'] ? $scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_failed_id'] : null);
        $t->same([3520 + $scenario['seed']], $tailPlan['import_plan']['failed'][0]['rollback']['restored_page_numbers']);
        $t->same([$scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_started_after_frame'] + 3], $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_failed_statement_discarded_frame_indexes']);
    };
    $tests[$prefix . 'outer rollback restores successful tail pages'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $restoredPages = $tailPlan['rollback_to_savepoint']['restored_page_numbers'];
        $expectedPages = $scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_pages'];
        sort($restoredPages);
        sort($expectedPages);
        $t->same($expectedPages, $restoredPages);
        $t->same([$scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_started_after_frame'] + 1, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_started_after_frame'] + 2], $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_outer_discarded_frame_indexes']);
    };
    $tests[$prefix . 'retains previous successful keys before outer rollback preview'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_checkpoint_followup_key_retained_before_outer_rollback']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_tail_recovery_key_retained_before_outer_rollback']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_final_followup_key_retained_before_outer_rollback']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_prior_recovery_key_retained_before_outer_rollback']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_prior_followup_key_retained_before_outer_rollback']);
    };
    $tests[$prefix . 'excludes previous failed tail keys before outer rollback preview'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_failed_tail_key_retained_before_outer_rollback']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_failed_bad_key_retained_before_outer_rollback']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_failed_tail_key_retained']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_failed_bad_key_retained']);
    };
    $tests[$prefix . 'keeps jsonb mode on tail catalog update'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $value = $tailPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'tail inserted row remains canonical json text before outer rollback'] = static function (TestRunner $t) use ($tailPlan): void {
        $value = $tailPlan['import_plan']['applied'][1]['key_value'];
        $t->same(true, is_string($value));
        $t->same(['tail_after_final_tail_recovery_checkpoint_followup' => true], json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR));
    };
    $tests[$prefix . 'rollback preview begins after final checkpoint followup frame prefix'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_started_after_frame'], $tailPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $discardedPages = $tailPlan['wal_rollback_to_savepoint']['discarded_page_numbers'];
        $expectedPages = $scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_pages'];
        sort($discardedPages);
        sort($expectedPages);
        $t->same($expectedPages, $discardedPages);
    };
    $tests[$prefix . 'tail wal input contains appended success and failed frames'] = static function (TestRunner $t) use ($scenario, $tailPlan): void {
        $t->same(hash('sha256', $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_wal_bytes']), hash('sha256', $tailPlan['wal_bytes_before']));
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_started_after_frame'] + 3, $tailPlan['wal_frame_count_before']);
    };
    $tests[$prefix . 'tail wal output is byte prefix of input'] = static function (TestRunner $t) use ($tailPlan): void {
        $t->same(substr($tailPlan['wal_bytes_before'], 0, strlen($tailPlan['wal_bytes_after'])), $tailPlan['wal_bytes_after']);
        $t->same(true, strlen($tailPlan['wal_bytes_after']) < strlen($tailPlan['wal_bytes_before']));
        $t->same(strlen($tailPlan['wal_bytes_after']), $tailPlan['wal_truncate_to_bytes']);
    };
    $tests[$prefix . 'tail wal checksums continue from final checkpoint followup prefix'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $pairs = application_wal_rollback_json_final_checkpoint_followup_tail_checksum_pairs((string) $tailPlan['wal_bytes_before'], $pageSize, 6);
        foreach ([4, 5, 6] as $frameIndex) {
            $header = application_wal_rollback_json_final_checkpoint_followup_tail_frame_header((string) $tailPlan['wal_bytes_before'], $pageSize, $frameIndex);
            $t->same($pairs[$frameIndex], [$header['checksum_1'], $header['checksum_2']]);
        }
    };
    $tests[$prefix . 'tail wal pages and commit markers stay non committed before rollback'] = static function (TestRunner $t) use ($tailPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $headers = [];
        foreach ([4, 5, 6] as $frameIndex) {
            $headers[] = application_wal_rollback_json_final_checkpoint_followup_tail_frame_header((string) $tailPlan['wal_bytes_before'], $pageSize, $frameIndex);
        }
        $t->same([$scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_pages'][0], $scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_pages'][1], 3520 + $scenario['seed']], array_column($headers, 'page_number'));
        $t->same([0, 0, 0], array_column($headers, 'commit'));
    };
    $tests[$prefix . 'records source dependencies'] = static function (TestRunner $t) use ($tailPlan): void {
        $t->same(true, in_array('sqlite-application-json-import-savepoint-current', $tailPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-savepoint-wal-rollback-current', $tailPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-current-batch-byte-truncation', $tailPlan['dependencies'], true));
    };
}

unset(
    $followupScenarios,
    $tailFailureScenarios,
    $directTailFailureScenarios,
    $scenario,
    $seed,
    $followupPlan,
    $tailPlan,
    $prefix
);

return $tests;
