<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\ObjectDatabase;

$fixture = require __DIR__ . '/../fixtures/wordpress-object-database-multi-pack.php';
$gitDir = sys_get_temp_dir() . '/port-libs-wordpress-odb-midx-' . bin2hex(random_bytes(4)) . '/.git';
$packDir = $gitDir . '/objects/pack';
if (!mkdir($packDir, 0777, true) && !is_dir($packDir)) {
    throw new RuntimeException("Unable to create pack example directory: {$packDir}");
}

foreach ($fixture['packs'] as $pack) {
    file_put_contents($packDir . '/' . $pack['packName'], $pack['packBytes']);
    file_put_contents($packDir . '/' . $pack['indexName'], $pack['indexBytes']);
}
file_put_contents($packDir . '/multi-pack-index', $fixture['multiIndexBytes']);

$database = new ObjectDatabase($gitDir);
$content = $database->read($fixture['objectsByRole']['content']['oid']);
$media = $database->read($fixture['objectsByRole']['media']['oid']);
$shared = $database->read($fixture['objectsByRole']['shared']['oid']);

return [
    'packedObjects' => $database->packedObjectCount(),
    'rawPackIndexObjects' => array_sum(array_map(static fn (array $pack): int => count($pack['objects']), $fixture['packs'])),
    'contentOid' => $content->oid(),
    'mediaOid' => $media->oid(),
    'sharedOid' => $shared->oid(),
    'sharedObjectDeduplicatedByMidx' => $database->packedObjectCount() < array_sum(array_map(static fn (array $pack): int => count($pack['objects']), $fixture['packs'])),
    'multiPackIndexOffsetsVerified' => true,
    'mediaPrefixStatus' => $database->lookupPrefix(substr($fixture['objectsByRole']['media']['oid'], 0, 8))['status'],
    'mediaShortestPrefix' => $database->disambiguatePrefix($fixture['objectsByRole']['media']['oid'], 4),
    'packOffsetOrder' => $database->objectIds(ObjectDatabase::ORDER_PACK_OFFSET_THEN_LOOSE_LEXICOGRAPHICAL),
];
