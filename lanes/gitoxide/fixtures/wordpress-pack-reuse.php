<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PackBuildResult;
use PortLibs\Gitoxide\PackBuilder;
use PortLibs\Gitoxide\PackData;
use PortLibs\Gitoxide\PackIndex;

$stableRows = '';
for ($i = 0; $i < 64; $i++) {
    $stableRows .= hash('sha1', 'wordpress-pack-reuse-post-row-' . $i) . "\n";
}

$oldExport = new GitObject('blob', "wp_posts export\n{$stableRows}post_status=draft\nchecksum=old\n");
$newExport = new GitObject('blob', "wp_posts export\n{$stableRows}post_status=publish\nchecksum=new\n");

$source = PackBuilder::buildWithOffsetDeltas([$oldExport, $newExport]);
$sourcePack = PackData::fromBytes($source->packBytes());
$sourceIndex = PackIndex::fromBytes($source->indexBytes());
$reused = PackBuilder::buildFromExistingPack($sourcePack, $sourceIndex, [$newExport->oid(), $oldExport->oid()]);
$thin = PackBuilder::buildFromExistingPack($sourcePack, $sourceIndex, [$newExport->oid()], true);

$findEntry = static function (PackBuildResult $result, string $oid): array {
    foreach ($result->entries() as $entry) {
        if ($entry['oid'] === $oid) {
            return $entry;
        }
    }

    throw new RuntimeException("Pack entry not found for {$oid}");
};

return [
    'oldExport' => $oldExport->oid(),
    'newExport' => $newExport->oid(),
    'sourcePackBytes' => $source->packBytes(),
    'sourceIndexBytes' => $source->indexBytes(),
    'sourceTargetEntry' => $findEntry($source, $newExport->oid()),
    'reusedPackBytes' => $reused->packBytes(),
    'reusedIndexBytes' => $reused->indexBytes(),
    'reusedPackChecksum' => $reused->packChecksum(),
    'reusedEntries' => $reused->entries(),
    'reusedTargetEntry' => $findEntry($reused, $newExport->oid()),
    'thinPackBytes' => $thin->packBytes(),
    'thinIndexBytes' => $thin->indexBytes(),
    'thinPackChecksum' => $thin->packChecksum(),
    'thinEntry' => $thin->entries()[0],
    'wordpressUse' => 'A PHP deployment tool can repack already-stored WordPress export objects by copying existing compressed entries and can intentionally emit a thin transit pack when the remote already has the base object.',
];
