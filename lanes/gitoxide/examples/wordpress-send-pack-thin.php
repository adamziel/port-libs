<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackData;

$fixture = require __DIR__ . '/../fixtures/wordpress-send-pack-thin.php';
$pack = PackData::fromBytes($fixture['packBytes']);

return [
    'oldCommit' => $fixture['oldCommit'],
    'newCommit' => $fixture['newCommit'],
    'packObjectCount' => $pack->count(),
    'thin' => $fixture['thin'],
    'deltaObjectCount' => count($fixture['deltaEntries']),
    'packChecksum' => $pack->verifyChecksum(),
    'commandLines' => $fixture['commandLines'],
    'requestByteLength' => strlen($fixture['requestBytes']),
];
