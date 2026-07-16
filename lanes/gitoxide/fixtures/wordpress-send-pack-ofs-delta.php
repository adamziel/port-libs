<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PackBuilder;

$stableRows = '';
for ($i = 0; $i < 72; $i++) {
    $stableRows .= hash('sha1', 'wordpress-ofs-delta-post-row-' . $i) . "\n";
}

$baseBlob = new GitObject(
    'blob',
    "wp_posts export\n{$stableRows}post_status=draft\npost_modified=2026-05-21 10:00:00\n"
);
$updatedBlob = new GitObject(
    'blob',
    "wp_posts export\n{$stableRows}post_status=publish\npost_modified=2026-05-22 08:15:00\n"
);
$pack = PackBuilder::buildWithOffsetDeltas([$baseBlob, $updatedBlob]);
$entries = $pack->entries();

return [
    'baseBlob' => $baseBlob->oid(),
    'updatedBlob' => $updatedBlob->oid(),
    'objects' => [
        'baseBlob' => $baseBlob->oid(),
        'updatedBlob' => $updatedBlob->oid(),
    ],
    'packBytes' => $pack->packBytes(),
    'indexBytes' => $pack->indexBytes(),
    'packChecksum' => $pack->packChecksum(),
    'indexChecksum' => $pack->indexChecksum(),
    'packEntries' => $entries,
    'thin' => $pack->isThin(),
    'offsetDeltaEntries' => array_values(array_filter(
        $entries,
        static fn (array $entry): bool => ($entry['storage'] ?? 'whole') === 'ofs-delta'
    )),
    'wordpressUse' => 'A PHP deployment tool can compact related WordPress export blobs inside one receive-pack payload by writing OFS_DELTA entries against earlier in-pack bases.',
];
