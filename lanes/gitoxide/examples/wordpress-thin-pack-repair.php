<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;

$fixture = require __DIR__ . '/../fixtures/wordpress-thin-pack-repair.php';
$thinPack = PackData::fromBytes($fixture['thinPackBytes']);
$thinIndex = PackIndex::fromBytes($fixture['thinIndexBytes']);
$repairedPack = PackData::fromBytes($fixture['repairedPackBytes']);
$repairedIndex = PackIndex::fromBytes($fixture['repairedIndexBytes']);
$updatedBlob = $repairedPack->readObject($repairedIndex, $fixture['updatedBlob']);

return [
    'baseBlob' => $fixture['baseBlob'],
    'updatedBlob' => $fixture['updatedBlob'],
    'thinObjectCount' => $thinPack->count(),
    'thin' => $fixture['thin'],
    'thinDeltaStorage' => $fixture['thinEntries'][0]['storage'] ?? null,
    'repairedObjectCount' => $repairedPack->count(),
    'repairedThin' => $fixture['repairedThin'],
    'repairedHasDelta' => $fixture['repairedHasDelta'],
    'repairedDeltaStorage' => $fixture['repairedEntries'][1]['storage'] ?? null,
    'thinPackChecksum' => $thinPack->verifyChecksum(),
    'thinIndexChecksum' => $thinIndex->verifyChecksum(),
    'repairedPackChecksum' => $repairedPack->verifyChecksum(),
    'repairedIndexChecksum' => $repairedIndex->verifyChecksum(),
    'updatedBlobSummary' => trim(substr($updatedBlob->body, strrpos($updatedBlob->body, 'post_status='))),
    'wordpressUse' => $fixture['wordpressUse'],
];
