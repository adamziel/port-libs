<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

$scenarios = SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(4);
$preexistingWalScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalScenarios(4);
$tenantCollisionScenarios = SQLiteJsonImportRollbackWalPlan::dynamicTenantCollisionScenarios(4);
$insertedSettingScenarios = SQLiteJsonImportRollbackWalPlan::dynamicInsertedSettingRollbackScenarios(4);
$duplicateInsertedSettingScenarios = SQLiteJsonImportRollbackWalPlan::dynamicDuplicateInsertedSettingRollbackScenarios(4);
$malformedInsertedInitialValueScenarios = SQLiteJsonImportRollbackWalPlan::dynamicMalformedInsertedInitialValueScenarios(4);
$deferredScenarios = SQLiteJsonImportRollbackWalPlan::dynamicDeferredFailureScenarios(4);
$preexistingRetryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalRetryScenarios(4);
$missingWalTailScenarios = SQLiteJsonImportRollbackWalPlan::dynamicMissingWalTailScenarios(4);
$partialWalTailScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPartialWalTailScenarios(4);
$frameHeaderMismatchScenarios = SQLiteJsonImportRollbackWalPlan::dynamicFrameHeaderMismatchScenarios(4);
$frameChecksumMismatchScenarios = SQLiteJsonImportRollbackWalPlan::dynamicFrameChecksumMismatchScenarios(4);
$headerChecksumMismatchScenarios = SQLiteJsonImportRollbackWalPlan::dynamicHeaderChecksumMismatchScenarios(4);
$fullRunMaterializedWalScenarios = SQLiteJsonImportRollbackWalPlan::dynamicFullRunMaterializedWalScenarios(4);
$committedPrefixFailureScenarios = SQLiteJsonImportRollbackWalPlan::dynamicCommittedPrefixFailureScenarios(4);
$rollbackDisabledMaterializedWalScenarios = SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledMaterializedWalScenarios(4);
$summary = [
    'scenario' => 'application-wal-rollback-json-dynamic-parity',
    'scenarioCount' => count($scenarios),
    'preexistingWalScenarioCount' => count($preexistingWalScenarios),
    'tenantCollisionScenarioCount' => count($tenantCollisionScenarios),
    'insertedSettingScenarioCount' => count($insertedSettingScenarios),
    'duplicateInsertedSettingScenarioCount' => count($duplicateInsertedSettingScenarios),
    'malformedInsertedInitialValueScenarioCount' => count($malformedInsertedInitialValueScenarios),
    'deferredScenarioCount' => count($deferredScenarios),
    'preexistingRetryScenarioCount' => count($preexistingRetryScenarios),
    'missingWalTailScenarioCount' => count($missingWalTailScenarios),
    'partialWalTailScenarioCount' => count($partialWalTailScenarios),
    'frameHeaderMismatchScenarioCount' => count($frameHeaderMismatchScenarios),
    'frameChecksumMismatchScenarioCount' => count($frameChecksumMismatchScenarios),
    'headerChecksumMismatchScenarioCount' => count($headerChecksumMismatchScenarios),
    'fullRunMaterializedWalScenarioCount' => count($fullRunMaterializedWalScenarios),
    'committedPrefixFailureScenarioCount' => count($committedPrefixFailureScenarios),
    'rollbackDisabledMaterializedWalScenarioCount' => count($rollbackDisabledMaterializedWalScenarios),
    'statuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios),
    'preexistingWalStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $preexistingWalScenarios),
    'tenantCollisionStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $tenantCollisionScenarios),
    'insertedSettingStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $insertedSettingScenarios),
    'duplicateInsertedSettingStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $duplicateInsertedSettingScenarios),
    'malformedInsertedInitialValueStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $malformedInsertedInitialValueScenarios),
    'deferredStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $deferredScenarios),
    'preexistingRetryStatuses' => array_map(static fn (array $scenario): string => $scenario['retry_plan']['status'], $preexistingRetryScenarios),
    'walFramesBefore' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_before'], $scenarios),
    'walFramesAfter' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $scenarios),
    'preexistingWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $preexistingWalScenarios),
    'preexistingWalTruncateBytes' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_truncate_to_bytes'], $preexistingWalScenarios),
    'tenantCollisionStablePages' => array_map(static fn (array $scenario): int => $scenario['stable_page'], $tenantCollisionScenarios),
    'tenantCollisionRestoredPages' => array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $tenantCollisionScenarios),
    'insertedSettingIds' => array_map(static fn (array $scenario): array => $scenario['inserted_setting_ids'], $insertedSettingScenarios),
    'insertedSettingRestoredPages' => array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $insertedSettingScenarios),
    'insertedSettingWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $insertedSettingScenarios),
    'duplicateInsertedSettingIds' => array_map(static fn (array $scenario): int => $scenario['duplicate_setting_id'], $duplicateInsertedSettingScenarios),
    'duplicateInsertedSettingRestoredPages' => array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $duplicateInsertedSettingScenarios),
    'duplicateInsertedSettingErrors' => array_map(static fn (array $scenario): string => $scenario['plan']['import_plan']['failed'][0]['error'], $duplicateInsertedSettingScenarios),
    'malformedInsertedInitialValueIds' => array_map(static fn (array $scenario): int => $scenario['insert_setting_id'], $malformedInsertedInitialValueScenarios),
    'malformedInsertedInitialValueStatementRestoredPages' => array_map(static fn (array $scenario): array => $scenario['plan']['import_plan']['failed'][0]['rollback']['restored_page_numbers'], $malformedInsertedInitialValueScenarios),
    'malformedInsertedInitialValueFailedFrames' => array_map(static fn (array $scenario): array => array_column($scenario['plan']['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'), $malformedInsertedInitialValueScenarios),
    'deferredWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $deferredScenarios),
    'preexistingRetryFailedWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['failed_plan']['wal_frame_count_after'], $preexistingRetryScenarios),
    'preexistingRetryWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['retry_plan']['wal_frame_count_after'], $preexistingRetryScenarios),
    'preexistingRetryMaterializedChecksumPairs' => array_map(static function (array $scenario): array {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $frameOffset = 32 + ((int) $scenario['preexisting_frames'] * $frameSize);
        $frameHeader = unpack('Npage_number/Ncommit/Nsalt_1/Nsalt_2/Nchecksum_1/Nchecksum_2', substr((string) $scenario['materialized_retry_plan']['wal_bytes_after'], $frameOffset, 24));

        return [(int) $frameHeader['checksum_1'], (int) $frameHeader['checksum_2']];
    }, $preexistingRetryScenarios),
    'preexistingRetryMaterializedCommitMarkers' => array_map(static function (array $scenario): array {
        $pageSize = (int) $scenario['page_size'];
        $frameSize = 24 + $pageSize;
        $commits = [];
        foreach ($scenario['expected_retry_pages'] as $index => $_pageNumber) {
            $frameOffset = 32 + (((int) $scenario['preexisting_frames'] + $index) * $frameSize);
            $frameHeader = unpack('Npage_number/Ncommit', substr((string) $scenario['materialized_retry_plan']['wal_bytes_after'], $frameOffset, 8));
            $commits[] = (int) $frameHeader['commit'];
        }

        return $commits;
    }, $preexistingRetryScenarios),
    'fullRunFailedStatuses' => array_map(static fn (array $scenario): string => $scenario['failed_plan']['status'], $fullRunMaterializedWalScenarios),
    'fullRunRetryWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['retry_plan']['wal_frame_count_after'], $fullRunMaterializedWalScenarios),
    'fullRunFollowupWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['followup_plan']['wal_frame_count_after'], $fullRunMaterializedWalScenarios),
    'fullRunFollowupPages' => array_map(static fn (array $scenario): array => array_column($scenario['followup_plan']['import_plan']['applied'], 'page_number'), $fullRunMaterializedWalScenarios),
    'fullRunFollowupKeys' => array_map(static fn (array $scenario): string => $scenario['followup_plan']['import_plan']['applied'][1]['key_name'], $fullRunMaterializedWalScenarios),
    'committedPrefixFailureStatuses' => array_map(static fn (array $scenario): string => $scenario['tail_plan']['status'], $committedPrefixFailureScenarios),
    'committedPrefixFailureWalFramesBefore' => array_map(static fn (array $scenario): int => $scenario['tail_plan']['wal_frame_count_before'], $committedPrefixFailureScenarios),
    'committedPrefixFailureWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['tail_plan']['wal_frame_count_after'], $committedPrefixFailureScenarios),
    'committedPrefixFailureTailPages' => array_map(static fn (array $scenario): array => $scenario['tail_plan']['rollback_to_savepoint']['restored_page_numbers'], $committedPrefixFailureScenarios),
    'committedPrefixFailureFailedStatements' => array_map(static fn (array $scenario): array => $scenario['tail_plan']['failed_statements'], $committedPrefixFailureScenarios),
    'rollbackDisabledMaterializedWalStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $rollbackDisabledMaterializedWalScenarios),
    'rollbackDisabledMaterializedWalFramesBefore' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_before'], $rollbackDisabledMaterializedWalScenarios),
    'rollbackDisabledMaterializedWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $rollbackDisabledMaterializedWalScenarios),
    'rollbackDisabledMaterializedWalAppliedPages' => array_map(static fn (array $scenario): array => array_column($scenario['plan']['import_plan']['applied'], 'page_number'), $rollbackDisabledMaterializedWalScenarios),
    'rollbackDisabledMaterializedWalFailedStatements' => array_map(static fn (array $scenario): array => $scenario['plan']['failed_statements'], $rollbackDisabledMaterializedWalScenarios),
    'missingWalTailMessages' => array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $missingWalTailScenarios),
    'missingWalTailShortFrameCounts' => array_map(static fn (array $scenario): int => $scenario['short_frame_count'], $missingWalTailScenarios),
    'partialWalTailMessages' => array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $partialWalTailScenarios),
    'partialWalTailCompleteFrameCounts' => array_map(static fn (array $scenario): int => $scenario['complete_frame_count'], $partialWalTailScenarios),
    'partialWalTailPayloadBytes' => array_map(static fn (array $scenario): int => $scenario['partial_payload_bytes'], $partialWalTailScenarios),
    'frameHeaderMismatchMessages' => array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $frameHeaderMismatchScenarios),
    'frameHeaderMismatchTargetFrames' => array_map(static fn (array $scenario): int => $scenario['target_frame'], $frameHeaderMismatchScenarios),
    'frameHeaderMismatchCorruptions' => array_map(static fn (array $scenario): string => $scenario['corruption'], $frameHeaderMismatchScenarios),
    'frameChecksumMismatchMessages' => array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $frameChecksumMismatchScenarios),
    'frameChecksumMismatchTargetFrames' => array_map(static fn (array $scenario): int => $scenario['target_frame'], $frameChecksumMismatchScenarios),
    'frameChecksumMismatchOffsets' => array_map(static fn (array $scenario): int => $scenario['checksum_offset'], $frameChecksumMismatchScenarios),
    'headerChecksumMismatchMessages' => array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $headerChecksumMismatchScenarios),
    'headerChecksumMismatchOffsets' => array_map(static fn (array $scenario): int => $scenario['checksum_offset'], $headerChecksumMismatchScenarios),
    'restoredPages' => array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $scenarios),
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['scenarioCount'] === 4);
    assert($summary['preexistingWalScenarioCount'] === 4);
    assert($summary['tenantCollisionScenarioCount'] === 4);
    assert($summary['insertedSettingScenarioCount'] === 4);
    assert($summary['duplicateInsertedSettingScenarioCount'] === 4);
    assert($summary['malformedInsertedInitialValueScenarioCount'] === 4);
    assert($summary['deferredScenarioCount'] === 4);
    assert($summary['preexistingRetryScenarioCount'] === 4);
    assert($summary['missingWalTailScenarioCount'] === 4);
    assert($summary['partialWalTailScenarioCount'] === 4);
    assert($summary['frameHeaderMismatchScenarioCount'] === 4);
    assert($summary['frameChecksumMismatchScenarioCount'] === 4);
    assert($summary['headerChecksumMismatchScenarioCount'] === 4);
    assert($summary['fullRunMaterializedWalScenarioCount'] === 4);
    assert($summary['committedPrefixFailureScenarioCount'] === 4);
    assert($summary['rollbackDisabledMaterializedWalScenarioCount'] === 4);
    assert($summary['statuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['preexistingWalStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['tenantCollisionStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['insertedSettingStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['duplicateInsertedSettingStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['malformedInsertedInitialValueStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['deferredStatuses'] === array_fill(0, 4, 'partial_rollback'));
    assert($summary['preexistingRetryStatuses'] === array_fill(0, 4, 'ready'));
    assert($summary['walFramesAfter'] === array_fill(0, 4, 0));
    assert($summary['preexistingWalFramesAfter'] === [3, 4, 5, 2]);
    assert($summary['preexistingWalTruncateBytes'][0] === 1640);
    assert($summary['tenantCollisionStablePages'] === [81, 82, 83, 84]);
    assert($summary['tenantCollisionRestoredPages'][0] === [19]);
    assert($summary['insertedSettingIds'][0] === [5003, 5004]);
    assert($summary['insertedSettingRestoredPages'][0] === [25, 181, 231]);
    assert($summary['insertedSettingWalFramesAfter'] === array_fill(0, 4, 0));
    assert($summary['duplicateInsertedSettingIds'] === [6002, 12002, 18002, 24002]);
    assert($summary['duplicateInsertedSettingRestoredPages'][0] === [35]);
    assert($summary['duplicateInsertedSettingErrors'][0] === 'SQLite Application JSON import inserted setting_id already exists: 6002');
    assert($summary['malformedInsertedInitialValueIds'] === [7002, 14002, 21002, 28002]);
    assert($summary['malformedInsertedInitialValueStatementRestoredPages'][0] === [431]);
    assert($summary['malformedInsertedInitialValueFailedFrames'] === [[2], [2], [2], [2]]);
    assert($summary['deferredWalFramesAfter'] === [5, 6, 7, 8]);
    assert($summary['preexistingRetryFailedWalFramesAfter'] === [2, 3, 4, 5]);
    assert($summary['preexistingRetryWalFramesAfter'] === [2, 3, 4, 5]);
    assert($summary['preexistingRetryMaterializedChecksumPairs'][0] !== [0, 0]);
    assert($summary['preexistingRetryMaterializedCommitMarkers'][0] === [0, 0, 391]);
    assert($summary['fullRunFailedStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['fullRunRetryWalFramesAfter'] === [5, 6, 7, 4]);
    assert($summary['fullRunFollowupWalFramesAfter'] === [7, 8, 9, 6]);
    assert($summary['fullRunFollowupPages'][0] === [721, 1021]);
    assert($summary['fullRunFollowupKeys'][0] === 'full_run_final_payload_1');
    assert($summary['committedPrefixFailureStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['committedPrefixFailureWalFramesBefore'] === [8, 9, 10, 7]);
    assert($summary['committedPrefixFailureWalFramesAfter'] === [5, 6, 7, 4]);
    assert($summary['committedPrefixFailureTailPages'][0] === [721, 1221]);
    assert($summary['committedPrefixFailureFailedStatements'][0] === ['committed_prefix_malformed_tail_1']);
    assert($summary['rollbackDisabledMaterializedWalStatuses'] === array_fill(0, 4, 'partial_rollback'));
    assert($summary['rollbackDisabledMaterializedWalFramesBefore'] === [2, 3, 4, 1]);
    assert($summary['rollbackDisabledMaterializedWalFramesAfter'] === [4, 5, 6, 3]);
    assert($summary['rollbackDisabledMaterializedWalAppliedPages'][0] === [63, 1321]);
    assert($summary['rollbackDisabledMaterializedWalFailedStatements'][0] === ['disabled_rollback_broken_payload_1']);
    assert($summary['missingWalTailShortFrameCounts'] === [4, 6, 6, 4]);
    assert($summary['missingWalTailMessages'][0] === 'SQLite Application JSON import rollback WAL bytes are missing current batch frame(s): 5, 6');
    assert($summary['partialWalTailMessages'] === array_fill(0, 4, 'SQLite Application JSON import rollback WAL bytes have a partial frame tail'));
    assert($summary['partialWalTailCompleteFrameCounts'] === [4, 5, 6, 3]);
    assert($summary['partialWalTailPayloadBytes'] === [38, 75, 112, 149]);
    assert($summary['frameHeaderMismatchTargetFrames'] === [4, 5, 6, 3]);
    assert($summary['frameHeaderMismatchCorruptions'] === ['salt_mismatch', 'zero_page', 'salt_mismatch', 'zero_page']);
    assert($summary['frameHeaderMismatchMessages'][0] === 'SQLite Application JSON import rollback WAL frame 4 salt does not match the WAL header');
    assert($summary['frameHeaderMismatchMessages'][1] === 'SQLite Application JSON import rollback WAL frame 5 has an invalid page number');
    assert($summary['frameChecksumMismatchTargetFrames'] === [4, 5, 6, 3]);
    assert($summary['frameChecksumMismatchMessages'][0] === 'SQLite Application JSON import rollback WAL frame 4 checksum does not match the frame payload');
    assert($summary['frameChecksumMismatchMessages'][1] === 'SQLite Application JSON import rollback WAL frame 5 checksum does not match the frame payload');
    assert($summary['frameChecksumMismatchOffsets'][0] === 1664);
    assert($summary['headerChecksumMismatchMessages'] === array_fill(0, 4, 'SQLite Application JSON import rollback WAL header checksum does not match the header content'));
    assert($summary['headerChecksumMismatchOffsets'] === [28, 24, 28, 24]);
    assert($summary['restoredPages'][0] === [3, 11]);
    fwrite(STDOUT, "application-wal-rollback-json-dynamic-parity self-test passed\n");
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
