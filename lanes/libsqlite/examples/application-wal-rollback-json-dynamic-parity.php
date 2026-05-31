<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

$scenarios = SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios(4);
$deferredScenarios = SQLiteJsonImportRollbackWalPlan::dynamicDeferredFailureScenarios(4);
$summary = [
    'scenario' => 'application-wal-rollback-json-dynamic-parity',
    'scenarioCount' => count($scenarios),
    'deferredScenarioCount' => count($deferredScenarios),
    'statuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $scenarios),
    'deferredStatuses' => array_map(static fn (array $scenario): string => $scenario['plan']['status'], $deferredScenarios),
    'walFramesBefore' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_before'], $scenarios),
    'walFramesAfter' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $scenarios),
    'deferredWalFramesAfter' => array_map(static fn (array $scenario): int => $scenario['plan']['wal_frame_count_after'], $deferredScenarios),
    'restoredPages' => array_map(static fn (array $scenario): array => $scenario['plan']['rollback_to_savepoint']['restored_page_numbers'], $scenarios),
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['scenarioCount'] === 4);
    assert($summary['deferredScenarioCount'] === 4);
    assert($summary['statuses'] === array_fill(0, 4, 'rolled_back_current_json_batch'));
    assert($summary['deferredStatuses'] === array_fill(0, 4, 'partial_rollback'));
    assert($summary['walFramesAfter'] === array_fill(0, 4, 0));
    assert($summary['deferredWalFramesAfter'] === [5, 6, 7, 8]);
    assert($summary['restoredPages'][0] === [3, 11]);
    fwrite(STDOUT, "application-wal-rollback-json-dynamic-parity self-test passed\n");
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
