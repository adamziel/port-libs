<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\ObjectDatabase;

$fixture = require __DIR__ . '/../fixtures/wordpress-object-database-multi-pack-sha256.php';
$gitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-midx-sha256-' . bin2hex(random_bytes(4)) . '/.git';
$packDir = $gitDir . '/objects/pack';
if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
    throw new RuntimeException("Unable to create SHA-256 pack example directory: {$packDir}");
}

foreach ($fixture['packs'] as $pack) {
    file_put_contents($packDir . '/' . $pack['packName'], $pack['packBytes']);
    file_put_contents($packDir . '/' . $pack['indexName'], $pack['indexBytes']);
}
file_put_contents($packDir . '/multi-pack-index', $fixture['multiIndexBytes']);

$database = new ObjectDatabase($gitDir, objectHash: 'sha256');
$content = $fixture['objectsByRole']['content'];
$media = $fixture['objectsByRole']['media'];
$contentObject = $database->read($content['oid']);
$mediaPrefix = $database->lookupPrefix(substr($media['oid'], 0, 12), true);

return [
    'objectHash' => $fixture['objectHash'],
    'packedObjects' => $database->packedObjectCount(),
    'multiPackIndexChecksumLength' => strlen($fixture['multiIndexChecksum']),
    'contentOidLength' => strlen($contentObject->oid('sha256')),
    'contentReadable' => $contentObject->body === $content['body'],
    'mediaPrefixStatus' => $mediaPrefix['status'],
    'mediaPrefixCandidates' => $mediaPrefix['candidates'],
    'mediaShortestPrefix' => $database->disambiguatePrefix(strtoupper($media['oid']), 4),
    'mediaHeader' => $database->readHeader(strtoupper($media['oid'])),
];
