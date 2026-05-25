<?php

declare(strict_types=1);

use PortLibs\Quadrable\SyncFuzzer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$trials = isset($argv[1]) ? max(0, (int) $argv[1]) : 3;
$seed = isset($argv[2]) ? (int) $argv[2] : 0;

$fuzzer = new SyncFuzzer(maxRoundTrips: 200);
$results = $fuzzer->run($trials, $seed);
$persistedResults = $fuzzer->runWithPersistedTrackedSnapshots($trials, $seed);
$inMemorySummary = SyncFuzzer::summarizeResults($results);
$persistedSummary = SyncFuzzer::summarizeResults($persistedResults);
$inMemoryBudget = [
    'maxRoundTrips' => max(1, $inMemorySummary['maxRoundTrips']),
    'totalRequests' => max(1, $inMemorySummary['totalRequests']),
    'totalResponses' => max(1, $inMemorySummary['totalResponses']),
];
$persistedBudget = [
    'maxRoundTrips' => max(1, $persistedSummary['maxRoundTrips']),
    'totalRequests' => max(1, $persistedSummary['totalRequests']),
    'totalResponses' => max(1, $persistedSummary['totalResponses']),
    'maxSnapshotBytes' => max(1, $persistedSummary['maxSnapshotBytes']),
    'maxTrackedSharedNodes' => max(1, $persistedSummary['maxTrackedSharedNodes']),
];

echo json_encode([
    'scenario' => 'optional Playground snapshot sync-fuzzer watchdog evidence',
    'seed' => $seed,
    'requestedTrials' => $trials,
    'upstreamFullProbeTrials' => 500,
    'fastSuiteRunsFullProbe' => false,
    'inMemoryReport' => SyncFuzzer::watchdogReport($results, $inMemoryBudget),
    'persistedTrackedReport' => SyncFuzzer::watchdogReport($persistedResults, $persistedBudget),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
