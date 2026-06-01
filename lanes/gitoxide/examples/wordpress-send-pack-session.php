<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;
use PortLibs\Gitoxide\PushRefStatus;
use PortLibs\Gitoxide\ReceivePackAdvertisement;
use PortLibs\Gitoxide\SendPackSession;

$fixture = require __DIR__ . '/../fixtures/wordpress-send-pack-session.php';
$advertisement = ReceivePackAdvertisement::fromV1PacketLines($fixture['advertisementBytes']);
$response = SendPackSession::create($advertisement)->parseSidebandResponse($fixture['responseBytes']);
$pack = PackData::fromBytes($fixture['packBytes']);
$index = PackIndex::fromBytes($fixture['indexBytes']);
$commit = $pack->readObject($index, $fixture['newCommit']);

return [
    'remoteMainBefore' => $advertisement->objectFor('refs/heads/main'),
    'newCommit' => $fixture['newCommit'],
    'requestByteLength' => strlen($fixture['requestBytes']),
    'packObjectCount' => $pack->count(),
    'packChecksum' => $pack->verifyChecksum(),
    'indexChecksum' => $index->verifyChecksum(),
    'remoteAccepted' => $response->isSuccessful(),
    'acceptedRefs' => array_map(
        static fn (PushRefStatus $status): string => $status->effectiveRefName(),
        $response->refStatuses()
    ),
    'mainStatusObjects' => [
        'oldObject' => $response->refStatuses()[0]->oldObject,
        'newObject' => $response->refStatuses()[0]->newObject,
    ],
    'commitSummary' => trim(substr($commit->body, strpos($commit->body, "\n\n") + 2)),
];
