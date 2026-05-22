<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-data.php';
$pack = PackData::fromBytes($fixture['packBytes']);
$index = PackIndex::fromBytes($fixture['indexBytes']);
$commit = $pack->readObject($index, $fixture['objects'][0]['oid']);
$blob = $pack->readObject($index, $fixture['objects'][1]['oid']);

return [
    'version' => $pack->version(),
    'objects' => $pack->count(),
    'checksum' => $pack->verifyChecksum(),
    'commitOid' => $commit->oid(),
    'blobOid' => $blob->oid(),
    'blobPreview' => strtok($blob->body, "\n"),
];
