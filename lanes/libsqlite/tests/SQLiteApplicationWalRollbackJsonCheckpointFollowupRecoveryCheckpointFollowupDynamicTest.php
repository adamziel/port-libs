<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;
use PortLibs\LibSqlite\SQLiteWal;

ini_set('memory_limit', '1536M');
unset($tests);

$checkpointScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointScenarios(18);
$followupScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupScenariosFromCheckpointScenarios($checkpointScenarios);
$directFollowupScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupScenarios(18);

$tests = [
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup exposes requested scenario count' => static function (TestRunner $t) use ($followupScenarios): void {
        $t->same(18, count($followupScenarios));
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup direct factory matches base factory' => static function (TestRunner $t) use ($followupScenarios, $directFollowupScenarios): void {
        $t->same(array_column($followupScenarios, 'tenant_id'), array_column($directFollowupScenarios, 'tenant_id'));
        $t->same(array_column($followupScenarios, 'expected_followup_recovery_checkpoint_followup_pages'), array_column($directFollowupScenarios, 'expected_followup_recovery_checkpoint_followup_pages'));
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup covers checkpoint reset modes' => static function (TestRunner $t) use ($followupScenarios): void {
        $modes = array_values(array_unique(array_column($followupScenarios, 'followup_recovery_checkpoint_mode')));
        sort($modes);
        $t->same(['restart', 'truncate'], $modes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup covers both page sizes' => static function (TestRunner $t) use ($followupScenarios): void {
        $pageSizes = array_values(array_unique(array_column($followupScenarios, 'page_size')));
        sort($pageSizes);
        $t->same([512, 1024], $pageSizes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup covers json text and jsonb rows' => static function (TestRunner $t) use ($followupScenarios): void {
        $jsonModes = array_values(array_unique(array_column($followupScenarios, 'jsonb_mode')));
        sort($jsonModes);
        $t->same([false, true], $jsonModes);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup starts new WAL header only after truncate' => static function (TestRunner $t) use ($followupScenarios): void {
        $started = array_values(array_unique(array_column($followupScenarios, 'followup_recovery_checkpoint_followup_started_new_wal_header')));
        sort($started);
        $t->same([false, true], $started);
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup rejects zero scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupScenarios(0);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup rejects empty checkpoint base scenarios' => static function (TestRunner $t): void {
        try {
            SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupScenariosFromCheckpointScenarios([]);
        } catch (InvalidArgumentException) {
            $t->same('rejected', 'rejected');
            return;
        }

        $t->same('rejected', 'accepted');
    },
    'sqlite application wal rollback json checkpoint followup recovery checkpoint followup small batch remains deterministic' => static function (TestRunner $t): void {
        $smallBatch = SQLiteJsonImportRollbackWalPlan::dynamicPostCheckpointTailRecoveryCheckpointFollowupRecoveryCheckpointFollowupScenarios(4);
        $t->same([8101, 8102, 8103, 8104], array_column($smallBatch, 'tenant_id'));
        $t->same(['restart', 'truncate', 'restart', 'truncate'], array_column($smallBatch, 'followup_recovery_checkpoint_mode'));
        $t->same([false, true, false, true], array_column($smallBatch, 'followup_recovery_checkpoint_followup_started_new_wal_header'));
        $t->same([[1321, 2621, 2421], [1322, 2622, 2422], [1323, 2623, 2423], [1324, 2624, 2424]], array_column($smallBatch, 'expected_followup_recovery_checkpoint_followup_pages'));
        $t->same([3, 3, 3, 3], array_map(static fn (array $scenario): int => $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_plan']['wal_frame_count_after'], $smallBatch));
    },
];

foreach ($followupScenarios as $scenario) {
    $seed = (int) $scenario['seed'];
    $recoveryPlan = $scenario['tail_recovery_checkpoint_followup_recovery_plan'];
    $releasedCheckpoint = $scenario['tail_recovery_checkpoint_followup_recovery_released_checkpoint'];
    $followupPlan = $scenario['tail_recovery_checkpoint_followup_recovery_checkpoint_followup_plan'];
    $prefix = 'sqlite application wal rollback json checkpoint followup recovery checkpoint followup seed ' . $seed . ' ';

    $tests[$prefix . 'starts from released final recovery checkpoint bytes'] = static function (TestRunner $t) use ($scenario, $releasedCheckpoint): void {
        $t->same(false, $releasedCheckpoint['busy']);
        $t->same(true, $scenario['followup_recovery_checkpointed_pages_match']);
        $t->same($scenario['followup_recovery_checkpoint_followup_input_database_hash'], hash('sha256', (string) $releasedCheckpoint['database_bytes']));
    };
    $tests[$prefix . 'uses reset or truncated checkpoint wal header'] = static function (TestRunner $t) use ($scenario, $followupPlan): void {
        $t->same(0, $followupPlan['wal_frame_count_before']);
        $t->same(32, $scenario['followup_recovery_checkpoint_followup_wal_header_length']);
        $t->same($scenario['followup_recovery_checkpoint_mode'] === 'truncate', $scenario['followup_recovery_checkpoint_followup_started_new_wal_header']);
    };
    $tests[$prefix . 'commits a fresh three frame final followup transaction'] = static function (TestRunner $t) use ($followupPlan): void {
        $t->same('ready', $followupPlan['status']);
        $t->same(false, $followupPlan['rollback_required']);
        $t->same(3, $followupPlan['materialized_wal_frame_count']);
        $t->same(3, $followupPlan['wal_frame_count_after']);
    };
    $tests[$prefix . 'uses final checkpoint followup transaction and savepoint names'] = static function (TestRunner $t) use ($followupPlan, $seed): void {
        $t->same('application_followup_recovery_checkpoint_followup_json_import_' . $seed, $followupPlan['transaction']);
        $t->same('followup_recovery_checkpoint_followup_json_batch_' . $seed, $followupPlan['savepoint']);
    };
    $tests[$prefix . 'starts from released checkpoint database image'] = static function (TestRunner $t) use ($followupPlan, $releasedCheckpoint): void {
        $t->same((string) $releasedCheckpoint['database_bytes'], $followupPlan['database_bytes_before']);
    };
    $tests[$prefix . 'records final followup applied pages and tenant ids'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_pages'], array_column($followupPlan['import_plan']['applied'], 'page_number'));
        $t->same([$scenario['tenant_id'], $scenario['tenant_id'], $scenario['tenant_id']], array_column($followupPlan['import_plan']['applied'], 'tenant_id'));
    };
    $tests[$prefix . 'inserts one durable final followup row over checkpoint base'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['followup_recovery_checkpoint_followup_base_row_count'] + 1, count($followupPlan['import_plan']['final_rows']));
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_inserted_key'], $followupPlan['import_plan']['applied'][1]['key_name']);
        $rowsByKey = [];
        foreach ($followupPlan['import_plan']['final_rows'] as $row) {
            $rowsByKey[$row['key_name']] = $row;
        }
        $t->same($scenario['expected_followup_recovery_checkpoint_followup_inserted_id'], $rowsByKey[$scenario['expected_followup_recovery_checkpoint_followup_inserted_key']]['setting_id']);
    };
    $tests[$prefix . 'updates corrected recovery insert after final checkpoint'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['expected_followup_recovery_inserted_key'], $followupPlan['import_plan']['applied'][2]['key_name']);
        $value = $followupPlan['import_plan']['applied'][2]['key_value'];
        $decoded = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : [];
        $t->same(true, $decoded['after_followup_recovery_checkpoint_seen'] ?? null);
    };
    $tests[$prefix . 'keeps corrected rows and excludes failed tail rows'] = static function (TestRunner $t) use ($scenario): void {
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_inserted_key_retained']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_recovery_key_retained']);
        $t->same(true, $scenario['followup_recovery_checkpoint_followup_prior_followup_key_retained']);
        $t->same(false, $scenario['followup_recovery_checkpoint_followup_failed_tail_key_retained']);
    };
    $tests[$prefix . 'keeps jsonb mode on final followup catalog update'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $value = $followupPlan['import_plan']['applied'][0]['key_value'];
        $t->same($scenario['jsonb_mode'], $value instanceof SQLiteBlobValue);
    };
    $tests[$prefix . 'keeps inserted final followup row as canonical json text'] = static function (TestRunner $t) use ($followupPlan): void {
        $value = $followupPlan['import_plan']['applied'][1]['key_value'];
        $t->same(true, is_string($value));
        $t->same(['final_followup' => true], json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR));
    };
    $tests[$prefix . 'rollback preview begins at empty checkpoint wal generation'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $expectedPages = $scenario['expected_followup_recovery_checkpoint_followup_pages'];
        $discardedPages = $followupPlan['wal_rollback_to_savepoint']['discarded_page_numbers'];
        sort($expectedPages);
        sort($discardedPages);
        $t->same(0, $followupPlan['wal_rollback_to_savepoint']['rollback_to_frame']);
        $t->same($expectedPages, $discardedPages);
    };
    $tests[$prefix . 'final followup wal frame pages start at frame one'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        foreach ($scenario['expected_followup_recovery_checkpoint_followup_pages'] as $index => $pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frameHeader = unpack('Npage_number', substr($followupPlan['wal_bytes_after'], $frameOffset, 4));
            $t->same($pageNumber, (int) $frameHeader['page_number']);
        }
    };
    $tests[$prefix . 'final followup wal checksums start from reset checkpoint header'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $walBytes = (string) $followupPlan['wal_bytes_after'];
        $checksumSeed = SQLiteWal::checksumPair(substr($walBytes, 0, 24), false);
        foreach ($scenario['expected_followup_recovery_checkpoint_followup_pages'] as $index => $pageNumber) {
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
        foreach ($scenario['expected_followup_recovery_checkpoint_followup_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + ($index * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr($followupPlan['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }
        $t->same([0, 0, intdiv(strlen($followupPlan['database_bytes_after_import']), $pageSize)], $commits);
    };
    $tests[$prefix . 'extends database image for final followup inserted page'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same(true, intdiv(strlen($followupPlan['database_bytes_after_import']), (int) $scenario['page_size']) >= $scenario['expected_followup_recovery_checkpoint_followup_pages'][1]);
    };
    $tests[$prefix . 'records source hashes and dependencies'] = static function (TestRunner $t) use ($followupPlan, $scenario): void {
        $t->same($scenario['followup_recovery_checkpoint_followup_input_wal_hash'], hash('sha256', $scenario['followup_recovery_checkpoint_followup_wal_bytes']));
        $t->same($scenario['followup_recovery_checkpoint_followup_input_database_hash'], hash('sha256', (string) $followupPlan['database_bytes_before']));
        $t->same(true, in_array('sqlite-application-json-import-savepoint-current', $followupPlan['dependencies'], true));
        $t->same(true, in_array('sqlite-wal-current-batch-byte-truncation', $followupPlan['dependencies'], true));
    };
    $tests[$prefix . 'does not inherit failed statements from previous recovery chain'] = static function (TestRunner $t) use ($followupPlan, $recoveryPlan): void {
        $t->same('ready', $recoveryPlan['status']);
        $t->same([], $followupPlan['failed_statements']);
        $t->same([], $followupPlan['import_plan']['failed']);
    };
}

unset(
    $checkpointScenarios,
    $followupScenarios,
    $directFollowupScenarios,
    $scenario,
    $seed,
    $recoveryPlan,
    $followupPlan,
    $prefix
);

return $tests;
