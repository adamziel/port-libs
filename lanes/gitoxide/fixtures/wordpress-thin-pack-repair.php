<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PackBuilder;
use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;

$stableRows = '';
for ($i = 0; $i < 80; $i++) {
    $stableRows .= hash('sha1', 'wordpress-thin-repair-post-row-' . $i) . "\n";
}

$baseBlob = new GitObject(
    'blob',
    "wp_posts export\n{$stableRows}post_status=draft\npost_modified=2026-05-21 10:00:00\n"
);
$updatedBlob = new GitObject(
    'blob',
    "wp_posts export\n{$stableRows}post_status=publish\npost_modified=2026-05-22 12:30:00\n"
);

$thin = PackBuilder::buildWithRefDeltas([$updatedBlob], [$baseBlob]);
$thinPack = PackData::fromBytes($thin->packBytes());
$thinIndex = PackIndex::fromBytes($thin->indexBytes());
$resolvedBlob = $thinPack->readObjectWithExternalBases($thinIndex, $updatedBlob->oid(), [$baseBlob->oid() => $baseBlob]);
$repaired = $thinPack->repairThinPack($thinIndex, [$baseBlob->oid() => $baseBlob]);

return [
    'baseBlob' => $baseBlob->oid(),
    'updatedBlob' => $updatedBlob->oid(),
    'thinPackBytes' => $thin->packBytes(),
    'thinIndexBytes' => $thin->indexBytes(),
    'thinEntries' => $thin->entries(),
    'thin' => $thin->isThin(),
    'resolvedBody' => $resolvedBlob->body,
    'repairedPackBytes' => $repaired->packBytes(),
    'repairedIndexBytes' => $repaired->indexBytes(),
    'repairedEntries' => $repaired->entries(),
    'repairedThin' => $repaired->isThin(),
    'repairedHasDelta' => $repaired->hasDeltaEntries(),
    'wordpressUse' => 'A PHP deployment receiver can resolve a thin WordPress content pack against an existing base blob and rewrite it into a complete local pack before storing it.',
];
