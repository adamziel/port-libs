<?php

declare(strict_types=1);

use PortLibs\Quadrable\SyncFuzzer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fuzzer = new SyncFuzzer(maxRoundTrips: 200);
$results = $fuzzer->runWithPersistedTrackedSnapshots(2, 0);

echo json_encode([
    'scenario' => 'persisted Playground snapshot heads survive upstream-shaped sync fuzz',
    'seed' => 0,
    'trials' => count($results),
    'firstTrial' => [
        'records' => $results[0]['numElems'],
        'edits' => $results[0]['numAlterations'],
        'roundTrips' => $results[0]['roundTrips'],
        'diffs' => $results[0]['diffCount'],
        'snapshotBytes' => $results[0]['snapshotBytes'],
        'sameLocalHeadAfterReload' => $results[0]['trackedLocalHeadNodeId'] === $results[0]['restoredLocalHeadNodeId'],
        'sameRemoteHeadAfterReload' => $results[0]['trackedRemoteHeadNodeId'] === $results[0]['restoredRemoteHeadNodeId'],
        'sharedTrackedNodes' => $results[0]['trackedSharedNodeCount'],
    ],
    'maxRoundTrips' => max(array_column($results, 'roundTrips')),
    'totalRequests' => array_sum(array_column($results, 'requests')),
    'totalResponses' => array_sum(array_column($results, 'responses')),
    'roots' => array_column($results, 'rootHash'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
