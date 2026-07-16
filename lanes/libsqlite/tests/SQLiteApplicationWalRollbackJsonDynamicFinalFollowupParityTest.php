<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;
use PortLibs\LibSqlite\SQLiteWal;

unset($tests);

$finalFollowupScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupScenarios(12);

function application_wal_rollback_json_final_followup_frame_header(string $walBytes, int $pageSize, int $frameIndex): array
{
    $frameOffset = 32 + (($frameIndex - 1) * (24 + $pageSize));
    $header = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr($walBytes, $frameOffset, 24));
    if ($header === false) {
        throw new RuntimeException('Unable to decode final followup WAL frame header');
    }

    return [
        'page_number' => (int) $header['page_number'],
        'commit' => (int) $header['commit'],
        'checksum_1' => (int) $header['checksum_1'],
        'checksum_2' => (int) $header['checksum_2'],
    ];
}

function application_wal_rollback_json_final_followup_checksum_pairs(string $walBytes, int $pageSize, int $frameCount): array
{
    $frameSize = 24 + $pageSize;
    $checksumSeed = SQLiteWal::checksumPair(substr($walBytes, 0, 24), false);
    $pairs = [];
    for ($frameIndex = 1; $frameIndex <= $frameCount; $frameIndex++) {
        $frameOffset = 32 + (($frameIndex - 1) * $frameSize);
        $frame = substr($walBytes, $frameOffset, $frameSize);
        $checksumSeed = SQLiteWal::checksumPair(substr($frame, 0, 8) . substr($frame, 24), false, $checksumSeed[0], $checksumSeed[1]);
        $pairs[] = [$checksumSeed[0], $checksumSeed[1]];
    }

    return $pairs;
}

$tests = [
    'sqlite application wal rollback json dynamic final followup exposes requested scenario count' => static function (TestRunner $t) use ($finalFollowupScenarios): void {
        $t->same(12, count($finalFollowupScenarios));
    },
    'sqlite application wal rollback json dynamic final followup covers restart and truncate reset modes' => static function (TestRunner $t) use ($finalFollowupScenarios): void {
        $modes = array_values(array_unique(array_column($finalFollowupScenarios, 'followup_recovery_checkpoint_followup_tail_recovery_checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json dynamic final followup starts new WAL headers only after truncate' => static function (TestRunner $t) use ($finalFollowupScenarios): void {
        $byMode = [];
        foreach ($finalFollowupScenarios as $scenario) {
            $byMode[$scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_mode']][] = $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_started_new_wal_header'];
        }

        $t->same(array_fill(0, 6, false), $byMode['restart']);
        $t->same(array_fill(0, 6, true), $byMode['truncate']);
    },
    'sqlite application wal rollback json dynamic final followup covers both page sizes' => static function (TestRunner $t) use ($finalFollowupScenarios): void {
        $pageSizes = array_values(array_unique(array_column($finalFollowupScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json dynamic final followup covers json text and jsonb rows' => static function (TestRunner $t) use ($finalFollowupScenarios): void {
        $jsonModes = array_values(array_unique(array_column($finalFollowupScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json dynamic final followup keeps tenant streams unique' => static function (TestRunner $t) use ($finalFollowupScenarios): void {
        $tenantIds = array_column($finalFollowupScenarios, 'tenant_id');
        $t->same(count($tenantIds), count(array_unique($tenantIds)));
    },
    'sqlite application wal rollback json dynamic final followup rejects zero scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupScenarios(0);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json dynamic final followup rejects empty checkpoint base scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupScenariosFromCheckpointScenarios([]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json dynamic final followup small batch remains deterministic' => static function (TestRunner $t): void {
        $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupTailRecoveryCheckpointFollowupScenarios(4);
        $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
        $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'followup_recovery_checkpoint_followup_tail_recovery_checkpoint_mode'));
        $t->same([false, true, false, true], array_column($smallBatch, 'followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_started_new_wal_header'));
        $t->same([[1321, 3221, 3021], [1322, 3222, 3022], [1323, 3223, 3023], [1324, 3224, 3024]], array_column($smallBatch, 'expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_pages'));
        $t->same([3, 3, 3, 3], array_map(static fn (array $scenario): int => $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_plan']['wal_frame_count_after'], $smallBatch));
    },
];

foreach ($finalFollowupScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $plan = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_plan'];
    $releasedCheckpoint = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_tail_recovery_released_checkpoint'];
    $prefix = 'sqlite application wal rollback json dynamic final followup seed ' . $seed . ' ';

    $tests[$prefix . 'starts after released tail recovery checkpoint'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        $t->same(false, $releasedCheckpoint['busy']);
        $t->same(true, $releasedCheckpoint['can_reset']);
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_action'], $releasedCheckpoint['wal_action']);
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_released_wal_bytes_length'], $releasedCheckpoint['wal_bytes_length']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpointed_pages_match']);
    };
    $tests[$prefix . 'starts from checkpointed database and reset wal hashes'] = static function (TestRunner $t) use ($scenario, $plan): void {
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_input_database_hash'], hash('sha256', (string) $plan['database_bytes_before']));
        $t->same($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_input_wal_hash'], hash('sha256', (string) $plan['wal_bytes_before']));
    };
    $tests[$prefix . 'starts at a clean WAL savepoint boundary'] = static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan['wal_frame_count_before']);
        $t->same(0, $plan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $t->same([], $plan['import_plan']['savepoint_state'][0]['page_numbers']);
    };
    $tests[$prefix . 'commits final followup without rollback'] = static function (TestRunner $t) use ($plan): void {
        $t->same('ready', $plan['status']);
        $t->same(false, $plan['rollback_required']);
        $t->same([], $plan['failed_statements']);
        $t->same(0, $plan['failed_statement_count']);
    };
    $tests[$prefix . 'materializes the three final followup frames'] = static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan['applied_statement_count']);
        $t->same(3, $plan['materialized_wal_frame_count']);
        $t->same(3, $plan['wal_frame_count_after']);
        $t->same(false, $plan['wal_truncated']);
    };
    $tests[$prefix . 'uses final followup transaction and savepoint names'] = static function (TestRunner $t) use ($plan, $seed): void {
        $t->same('application_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_json_import_' . $seed, $plan['transaction']);
        $t->same('followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_json_batch_' . $seed, $plan['savepoint']);
    };
    $tests[$prefix . 'records expected final followup pages and tenant ids'] = static function (TestRunner $t) use ($scenario, $plan): void {
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_pages'], array_column($plan['import_plan']['applied'], 'page_number'));
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($plan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'keeps jsonb mode on final catalog update'] = static function (TestRunner $t) use ($scenario, $plan): void {
        $value = $plan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'retains final followup and recovery keys'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_inserted_key_retained']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_tail_recovery_key_retained']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_final_followup_key_retained']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_prior_recovery_key_retained']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_prior_followup_key_retained']);
    };
    $tests[$prefix . 'keeps rejected tail keys absent'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_failed_tail_key_retained']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_failed_bad_key_retained']);
    };
    $tests[$prefix . 'uses reset WAL header shape from checkpoint mode'] = static function (TestRunner $t) use ($scenario, $plan, $releasedCheckpoint): void {
        $t->same(32, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_wal_header_length']);
        $t->same(32, strlen((string) $plan['wal_bytes_before']));
        if ($scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_mode'] === 'truncate') {
            $t->same('', $releasedCheckpoint['wal_bytes']);
            $t->same(true, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_started_new_wal_header']);
            return;
        }

        $t->same(32, strlen((string) $releasedCheckpoint['wal_bytes']));
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_started_new_wal_header']);
    };
    $tests[$prefix . 'records final followup inserted row identity'] = static function (TestRunner $t) use ($scenario, $plan): void {
        $t->same([false, true, false], array_column($plan['import_plan']['applied'], 'inserted_setting'));
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_inserted_key'], $plan['import_plan']['applied'][1]['key_name']);
        $t->same(true, in_array($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_inserted_id'], array_column($plan['import_plan']['final_rows'], 'setting_id'), true));
    };
    $tests[$prefix . 'savepoint rollback preview covers final followup frames'] = static function (TestRunner $t) use ($scenario, $plan): void {
        $expectedPages = $scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_pages'];
        $restoredPages = $plan['rollback_to_savepoint']['restored_page_numbers'];
        $discardedPages = $plan['wal_rollback_to_savepoint']['discarded_page_numbers'];
        sort($expectedPages);
        sort($restoredPages);
        sort($discardedPages);

        $t->same([1, 2, 3], array_column($plan['wal_rollback_to_savepoint']['discarded_wal_frames'], 'frame_index'));
        $t->same($expectedPages, $discardedPages);
        $t->same($expectedPages, $restoredPages);
    };
    $tests[$prefix . 'appended WAL frame pages start at frame one'] = static function (TestRunner $t) use ($scenario, $plan): void {
        $pageSize = (int) $scenario['page_size'];
        foreach ($scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_pages'] as $index => $pageNumber) {
            $header = application_wal_rollback_json_final_followup_frame_header((string) $plan['wal_bytes_after'], $pageSize, $index + 1);
            $t->same($pageNumber, $header['page_number']);
        }
    };
    $tests[$prefix . 'appended WAL checksums chain from reset header'] = static function (TestRunner $t) use ($scenario, $plan): void {
        $pageSize = (int) $scenario['page_size'];
        $pairs = application_wal_rollback_json_final_followup_checksum_pairs((string) $plan['wal_bytes_after'], $pageSize, 3);
        foreach ($pairs as $index => $pair) {
            $header = application_wal_rollback_json_final_followup_frame_header((string) $plan['wal_bytes_after'], $pageSize, $index + 1);
            $t->same($pair, [$header['checksum_1'], $header['checksum_2']]);
        }
    };
    $tests[$prefix . 'only final appended WAL frame is a commit frame'] = static function (TestRunner $t) use ($scenario, $plan): void {
        $pageSize = (int) $scenario['page_size'];
        $commits = [];
        for ($frameIndex = 1; $frameIndex <= 3; $frameIndex++) {
            $header = application_wal_rollback_json_final_followup_frame_header((string) $plan['wal_bytes_after'], $pageSize, $frameIndex);
            $commits[] = $header['commit'];
        }

        $t->same([0, 0, intdiv(strlen((string) $plan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'extends database image for final inserted page'] = static function (TestRunner $t) use ($scenario, $plan): void {
        $pageCount = intdiv(strlen((string) $plan['database_bytes_after_import']), (int) $scenario['page_size']);
        $t->same(true, $pageCount >= $scenario['expected_followup_recovery_checkpoint_followup_tail_recovery_checkpoint_followup_pages'][1]);
    };
}

unset($finalFollowupScenarios, $scenario, $plan, $releasedCheckpoint, $prefix, $seed);

return $tests;
