<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PackBuildResult;
use PortLibs\Gitoxide\PackBuilder;

$stable = '';
for ($i = 0; $i < 56; $i++) {
    $stable .= hash('sha1', 'wordpress-delta-window-post-row-' . $i) . "\n";
}

$oldExport = new GitObject('blob', "wp_posts export\n{$stable}post_status=draft\nchecksum=old\n");
$scratch = new GitObject('blob', str_repeat("temporary media-regeneration scratch row\n", 10));
$newExport = new GitObject('blob', "wp_posts export\n{$stable}post_status=publish\nchecksum=new\n");

$findEntry = static function (PackBuildResult $result, string $oid): array {
    foreach ($result->entries() as $entry) {
        if ($entry['oid'] === $oid) {
            return $entry;
        }
    }

    throw new RuntimeException("Pack entry not found for {$oid}");
};

$unbounded = PackBuilder::buildWithOffsetDeltas([$oldExport, $scratch, $newExport]);
$bounded = PackBuilder::buildWithOffsetDeltas([$oldExport, $scratch, $newExport], 1);

return [
    'oldExport' => $oldExport->oid(),
    'scratch' => $scratch->oid(),
    'newExport' => $newExport->oid(),
    'unboundedPackBytes' => $unbounded->packBytes(),
    'unboundedIndexBytes' => $unbounded->indexBytes(),
    'unboundedPackChecksum' => $unbounded->packChecksum(),
    'unboundedTargetEntry' => $findEntry($unbounded, $newExport->oid()),
    'boundedWindow' => 1,
    'boundedPackBytes' => $bounded->packBytes(),
    'boundedIndexBytes' => $bounded->indexBytes(),
    'boundedPackChecksum' => $bounded->packChecksum(),
    'boundedHasDelta' => $bounded->hasDeltaEntries(),
    'boundedTargetEntry' => $findEntry($bounded, $newExport->oid()),
    'wordpressUse' => 'A PHP deployment pack builder can cap delta-base search work for large WordPress export batches while preserving valid whole-object fallback when a recent scratch blob is not a useful base.',
];
