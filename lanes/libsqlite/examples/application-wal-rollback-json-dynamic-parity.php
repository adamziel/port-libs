<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

$scenarios = SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(4);
$preexistingWalScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalScenarios(4);
$tenantCollisionScenarios = SQLiteJsonImportRollbackWalPlan::dynamicTenantCollisionScenarios(4);
$deferredScenarios = SQLiteJsonImportRollbackWalPlan::dynamicDeferredFailureScenarios(4);
$preexistingRetryScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPreexistingWalRetryScenarios(4);
$missingWalTailScenarios = SQLiteJsonImportRollbackWalPlan::dynamicMissingWalTailScenarios(4);
$partialWalTailScenarios = SQLiteJsonImportRollbackWalPlan::dynamicPartialWalTailScenarios(4);
$summary = [
    'scenario' => 'application-wal-rollback-json-dynamic-parity',
    'scenarioCount' => count($scenarios),
    'preexistingWalScenarioCount' => count($preexistingWalScenarios),
    'tenantCollisionScenarioCount' => count($tenantCollisionScenarios),
    'deferredScenarioCount' => count($deferredScenarios),
    'preexistingRetryScenarioCount' => count($preexistingRetryScenarios),
    'missingWalTailScenarioCount' => count($missingWalTailScenarios),
    'partialWalTailScenarioCount' => count($partialWalTailScenarios),
    'statuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios),
    'preexistingWalStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $preexistingWalScenarios),
    'tenantCollisionStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $tenantCollisionScenarios),
    'deferredStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $deferredScenarios),
    'preexistingRetryStatuses' => array_map(static fn (array $scenario): string => $scenario['retry_plan']['status'], $preexistingRetryScenarios),
    'walFramesBefore' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_before'], $scenarios),
    'walFramesAfter' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $scenarios),
    'preexistingWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $preexistingWalScenarios),
    'preexistingWalTruncateBytes' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_truncate_to_bytes'], $preexistingWalScenarios),
    'tenantCollisionStablePages' => array_map(static fn (array $scenario): int => $scenario['stable_page'], $tenantCollisionScenarios),
    'tenantCollisionRestoredPages' => array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $tenantCollisionScenarios),
    'deferredWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $deferredScenarios),
    'preexistingRetryFailedWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['failed_plan']['wal_frame_count_after'], $preexistingRetryScenarios),
    'preexistingRetryWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['retry_plan']['wal_frame_count_after'], $preexistingRetryScenarios),
    'missingWalTailMessages' => array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $missingWalTailScenarios),
    'missingWalTailShortFrameCounts' => array_map(static fn (array $scenario): int => $scenario['short_frame_count'], $missingWalTailScenarios),
    'partialWalTailMessages' => array_map(static fn (array $scenario): ?string => $scenario['exception_message'], $partialWalTailScenarios),
    'partialWalTailCompleteFrameCounts' => array_map(static fn (array $scenario): int => $scenario['complete_frame_count'], $partialWalTailScenarios),
    'partialWalTailPayloadBytes' => array_map(static fn (array $scenario): int => $scenario['partial_payload_bytes'], $partialWalTailScenarios),
    'restoredPages' => array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $scenarios),
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['scenarioCount'] === 4);
    assert($summary['preexistingWalScenarioCount'] === 4);
    assert($summary['tenantCollisionScenarioCount'] === 4);
    assert($summary['deferredScenarioCount'] === 4);
    assert($summary['preexistingRetryScenarioCount'] === 4);
    assert($summary['missingWalTailScenarioCount'] === 4);
    assert($summary['partialWalTailScenarioCount'] === 4);
    assert($summary['statuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['preexistingWalStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['tenantCollisionStatuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['deferredStatuses'] === array_fill(0, 4, 'partial_rollback'));
    assert($summary['preexistingRetryStatuses'] === array_fill(0, 4, 'ready'));
    assert($summary['walFramesAfter'] === array_fill(0, 4, 0));
    assert($summary['preexistingWalFramesAfter'] === [3, 4, 5, 2]);
    assert($summary['preexistingWalTruncateBytes'][0] === 1640);
    assert($summary['tenantCollisionStablePages'] === [81, 82, 83, 84]);
    assert($summary['tenantCollisionRestoredPages'][0] === [19]);
    assert($summary['deferredWalFramesAfter'] === [5, 6, 7, 8]);
    assert($summary['preexistingRetryFailedWalFramesAfter'] === [2, 3, 4, 5]);
    assert($summary['preexistingRetryWalFramesAfter'] === [2, 3, 4, 5]);
    assert($summary['missingWalTailShortFrameCounts'] === [4, 6, 6, 4]);
    assert($summary['missingWalTailMessages'][0] === 'SQLite Application JSON import rollback WAL bytes are missing current batch frame(s): 5, 6');
    assert($summary['partialWalTailMessages'] === array_fill(0, 4, 'SQLite Application JSON import rollback WAL bytes have a partial frame tail'));
    assert($summary['partialWalTailCompleteFrameCounts'] === [4, 5, 6, 3]);
    assert($summary['partialWalTailPayloadBytes'] === [38, 75, 112, 149]);
    assert($summary['restoredPages'][0] === [3, 11]);
    fwrite(STDOUT, "application-wal-rollback-json-dynamic-parity self-test passed\n");
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
