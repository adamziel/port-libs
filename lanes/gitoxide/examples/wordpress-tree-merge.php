<?php

declare(strict_types=1);

use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\TreeMerge;
use PortLibs\Gitoxide\TreeMergeResult;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = require dirname(__DIR__) . '/fixtures/wordpress-tree-merge.php';

$clean = TreeMerge::mergeFlat($fixture['clean']['base'], $fixture['clean']['ours'], $fixture['clean']['theirs']);
$conflict = TreeMerge::mergeFlat($fixture['conflict']['base'], $fixture['conflict']['ours'], $fixture['conflict']['theirs']);
$virtualBase = $fixture['virtualBase'];
$virtualBaseMerge = TreeMerge::mergeRecursiveWithVirtualBase(
    $virtualBase['mergeBaseAncestor'],
    $virtualBase['mergeBases'],
    $virtualBase['ours'],
    $virtualBase['theirs'],
    $virtualBase['read'],
    $virtualBase['write'],
);
$virtualBaseResolved = $virtualBaseMerge->resolveTreeConflicts(
    $virtualBase['read'],
    $virtualBase['write'],
    TreeMergeResult::RESOLVE_OURS,
    TreeMergeResult::RESOLVE_OURS,
);

echo 'clean=' . ($clean->isClean() ? 'yes' : 'no') . "\n";
echo 'entries=' . implode(',', array_map(static fn (TreeEntry $entry): string => $entry->filename, $clean->tree->entries)) . "\n";
echo 'conflicts=' . count($conflict->conflicts) . "\n";
echo 'first-conflict=' . $conflict->conflicts[0]->path . ':' . $conflict->conflicts[0]->reason . "\n";
echo 'virtual-base-conflicts=' . count($virtualBaseMerge->conflicts) . "\n";
echo 'virtual-base-ours=' . $virtualBase['read']($virtualBaseResolved->tree->entryNamed('renamed-content')?->oid ?? '')->body;
