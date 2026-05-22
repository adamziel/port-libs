<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;

$fixture = require __DIR__ . '/../fixtures/wordpress-send-pack-ofs-delta.php';
$pack = PackData::fromBytes($fixture['packBytes']);
$index = PackIndex::fromBytes($fixture['indexBytes']);
$updatedBlob = $pack->readObject($index, $fixture['updatedBlob']);
$offsetDelta = $fixture['offsetDeltaEntries'][0] ?? null;

return [
    'baseBlob' => $fixture['baseBlob'],
    'updatedBlob' => $fixture['updatedBlob'],
    'packObjectCount' => $pack->count(),
    'thin' => $fixture['thin'],
    'deltaObjectCount' => count($fixture['offsetDeltaEntries']),
    'offsetDeltaDistance' => $offsetDelta['baseDistance'] ?? null,
    'offsetDeltaBaseOffset' => $offsetDelta['baseOffset'] ?? null,
    'packChecksum' => $pack->verifyChecksum(),
    'indexChecksum' => $index->verifyChecksum(),
    'updatedBlobSummary' => trim(substr($updatedBlob->body, strrpos($updatedBlob->body, 'post_status='))),
    'wordpressUse' => $fixture['wordpressUse'],
];
