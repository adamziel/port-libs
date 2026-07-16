<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;

$fixture = require __DIR__ . '/../fixtures/wordpress-protocol-v1-push-pack.php';
$pack = PackData::fromBytes($fixture['packBytes']);
$index = PackIndex::fromBytes($fixture['indexBytes']);
$commit = $pack->readObject($index, $fixture['newCommit']);

return [
    'newCommit' => $fixture['newCommit'],
    'packObjectCount' => $pack->count(),
    'packChecksum' => $pack->verifyChecksum(),
    'indexChecksum' => $index->verifyChecksum(),
    'commandLines' => $fixture['commandLines'],
    'requestByteLength' => strlen($fixture['requestBytes']),
    'commitSummary' => trim(substr($commit->body, strpos($commit->body, "\n\n") + 2)),
];
