<?php

declare(strict_types=1);

use PortLibs\Quadrable\SyncFuzzer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fuzzer = new SyncFuzzer();
$results = $fuzzer->run(3, 0);

echo json_encode([
    'scenario' => 'deterministic Playground snapshot sync fuzz with upstream-shaped budgets',
    'seed' => 0,
    'trials' => count($results),
    'firstTrial' => [
        'records' => $results[0]['numElems'],
        'edits' => $results[0]['numAlterations'],
        'roundTrips' => $results[0]['roundTrips'],
        'diffs' => $results[0]['diffCount'],
    ],
    'maxRoundTrips' => max(array_column($results, 'roundTrips')),
    'totalRequests' => array_sum(array_column($results, 'requests')),
    'totalResponses' => array_sum(array_column($results, 'responses')),
    'roots' => array_column($results, 'rootHash'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
