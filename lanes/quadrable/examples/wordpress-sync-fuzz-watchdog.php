<?php

declare(strict_types=1);

use PortLibs\Quadrable\SyncFuzzer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$trials = isset($argv[1]) ? max(0, (int) $argv[1]) : 3;
$seed = isset($argv[2]) ? (int) $argv[2] : 0;

$fuzzer = new SyncFuzzer(maxRoundTrips: 200);
$results = $fuzzer->run($trials, $seed);
$summary = SyncFuzzer::summarizeResults($results);

echo json_encode([
    'scenario' => 'optional Playground snapshot sync-fuzzer watchdog evidence',
    'seed' => $seed,
    'requestedTrials' => $trials,
    'upstreamFullProbeTrials' => 500,
    'fastSuiteRunsFullProbe' => false,
    'summary' => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
