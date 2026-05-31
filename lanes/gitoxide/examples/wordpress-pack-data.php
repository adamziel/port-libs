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
$deltaBlob = $pack->readObject($index, $fixture['objects'][2]['oid']);

$strictDeclaredSizeGuard = false;
try {
    $malformedBlobPack = 'PACK' . pack('N2', 2, 1)
        . chr((3 << 4) | 1)
        . gzcompress('AB')
        . str_repeat("\0", 20);
    PackData::fromBytes($malformedBlobPack)->entryAtOffset(12);
} catch (RuntimeException) {
    $strictDeclaredSizeGuard = true;
}

return [
    'version' => $pack->version(),
    'objects' => $pack->count(),
    'checksum' => $pack->verifyChecksum(),
    'commitOid' => $commit->oid(),
    'blobOid' => $blob->oid(),
    'deltaBlobOid' => $deltaBlob->oid(),
    'blobPreview' => strtok($blob->body, "\n"),
    'deltaBlobHasPackedEdit' => str_contains($deltaBlob->body, 'reconstructed packed edit'),
    'strictDeclaredSizeGuard' => $strictDeclaredSizeGuard,
];
