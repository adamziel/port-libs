<?php

declare(strict_types=1);

use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\TreeMerge;
use PortLibs\Gitoxide\TreeMergeResult;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = require dirname(__DIR__) . '/fixtures/wordpress-tree-merge.php';
$entryNames = static fn (Tree $tree): string => implode(',', array_map(static fn (TreeEntry $entry): string => $entry->filename, $tree->entries));
$treeAtPath = static function (Tree $root, string $path, callable $read): Tree {
    $current = $root;
    foreach (explode('/', $path) as $part) {
        $entry = $current->entryNamed($part, true);
        if ($entry === null) {
            throw new RuntimeException("Tree entry not found: {$path}");
        }
        $current = Tree::fromObject($read($entry->oid));
    }

    return $current;
};

$clean = TreeMerge::mergeFlat($fixture['clean']['base'], $fixture['clean']['ours'], $fixture['clean']['theirs']);
$conflict = TreeMerge::mergeFlat($fixture['conflict']['base'], $fixture['conflict']['ours'], $fixture['conflict']['theirs']);
$unrelated = $fixture['unrelatedHistories'];
$unrelatedMerge = TreeMerge::mergeRecursive(
    $unrelated['base'],
    $unrelated['ours'],
    $unrelated['theirs'],
    $unrelated['read'],
    $unrelated['write'],
);
$unrelatedDiff3 = TreeMerge::mergeRecursive(
    $unrelated['base'],
    $unrelated['ours'],
    $unrelated['theirs'],
    $unrelated['read'],
    $unrelated['write'],
    BlobMerge::STYLE_DIFF3,
);
$unrelatedOurs = $unrelatedMerge->resolveTreeConflicts(
    $unrelated['read'],
    $unrelated['write'],
    TreeMergeResult::RESOLVE_OURS,
    TreeMergeResult::RESOLVE_OURS,
);
$unrelatedPath = 'acme-bootstrap.php';
$unrelatedDiff3Body = $unrelated['read']($unrelatedDiff3->tree->entryNamed($unrelatedPath)?->oid ?? '')->body;
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
$renameAddDelete = $fixture['renameAddDelete'];
$renameAddDeleteMerge = TreeMerge::mergeRecursive(
    $renameAddDelete['base'],
    $renameAddDelete['ours'],
    $renameAddDelete['theirs'],
    $renameAddDelete['read'],
    $renameAddDelete['write'],
);
$renameAddDeleteResolved = $renameAddDeleteMerge->resolveTreeConflicts(
    $renameAddDelete['read'],
    $renameAddDelete['write'],
    TreeMergeResult::RESOLVE_ANCESTOR,
    TreeMergeResult::RESOLVE_ANCESTOR,
);
$renameAdd = $fixture['renameAdd'];
$renameAddMerge = TreeMerge::mergeRecursive(
    $renameAdd['base'],
    $renameAdd['ours'],
    $renameAdd['theirs'],
    $renameAdd['read'],
    $renameAdd['write'],
);
$renameAddEntry = $renameAddMerge->tree->entryNamed('review-widget.php');
$renameDeleteSameSide = $fixture['renameDeleteSameSide'];
$renameDeleteSameSideA = TreeMerge::mergeRecursive(
    $renameDeleteSameSide['base'],
    $renameDeleteSameSide['sideA'],
    $renameDeleteSameSide['sideA'],
    $renameDeleteSameSide['read'],
    $renameDeleteSameSide['write'],
);
$renameDeleteSameSideB = TreeMerge::mergeRecursive(
    $renameDeleteSameSide['base'],
    $renameDeleteSameSide['sideB'],
    $renameDeleteSameSide['sideB'],
    $renameDeleteSameSide['read'],
    $renameDeleteSameSide['write'],
);
$renameDeleteSameSideAPlugins = $treeAtPath($renameDeleteSameSideA->tree, 'wp-content/plugins', $renameDeleteSameSide['read']);
$renameDeleteSameSideBPlugins = $treeAtPath($renameDeleteSameSideB->tree, 'wp-content/plugins', $renameDeleteSameSide['read']);
$renameDeleteSameSideATools = $treeAtPath($renameDeleteSameSideA->tree, 'wp-content/plugins/acme-suite', $renameDeleteSameSide['read']);
$renameDeleteSameSideBTools = $treeAtPath($renameDeleteSameSideB->tree, 'wp-content/plugins/acme-tools', $renameDeleteSameSide['read']);
$sameRenameMode = $fixture['sameRenameMode'];
$sameRenameModeMerge = TreeMerge::mergeRecursive(
    $sameRenameMode['base'],
    $sameRenameMode['ours'],
    $sameRenameMode['theirs'],
    $sameRenameMode['read'],
    $sameRenameMode['write'],
);
$sameRenameModeReverseMerge = TreeMerge::mergeRecursive(
    $sameRenameMode['base'],
    $sameRenameMode['theirs'],
    $sameRenameMode['ours'],
    $sameRenameMode['read'],
    $sameRenameMode['write'],
);
$sameRenameModeTree = Tree::fromObject($sameRenameMode['read']($sameRenameModeMerge->tree->entryNamed('acme-suite', true)?->oid ?? ''));
$sameRenameModeReverseTree = Tree::fromObject($sameRenameMode['read']($sameRenameModeReverseMerge->tree->entryNamed('acme-suite', true)?->oid ?? ''));
$binaryAttr = $fixture['binaryAttr'];
$binaryAttrMerge = TreeMerge::mergeRecursive(
    $binaryAttr['base'],
    $binaryAttr['ours'],
    $binaryAttr['theirs'],
    $binaryAttr['read'],
    $binaryAttr['write'],
);
$binaryAttrContent = Tree::fromObject($binaryAttr['read']($binaryAttrMerge->tree->entryNamed('wp-content', true)?->oid ?? ''));
$binaryAttrUploads = Tree::fromObject($binaryAttr['read']($binaryAttrContent->entryNamed('uploads', true)?->oid ?? ''));
$binaryAttrHero = $binaryAttrUploads->entryNamed('hero.png');

echo 'clean=' . ($clean->isClean() ? 'yes' : 'no') . "\n";
echo 'entries=' . $entryNames($clean->tree) . "\n";
echo 'conflicts=' . count($conflict->conflicts) . "\n";
echo 'first-conflict=' . $conflict->conflicts[0]->path . ':' . $conflict->conflicts[0]->reason . "\n";
echo 'unrelated-conflicts=' . count($unrelatedMerge->conflicts) . "\n";
echo 'unrelated-diff3-base=' . (str_contains($unrelatedDiff3Body, '||||||| base/' . $unrelatedPath) ? 'yes' : 'no') . "\n";
echo 'unrelated-ours=' . $unrelated['read']($unrelatedOurs->tree->entryNamed($unrelatedPath)?->oid ?? '')->body;
echo 'virtual-base-conflicts=' . count($virtualBaseMerge->conflicts) . "\n";
echo 'virtual-base-ours=' . $virtualBase['read']($virtualBaseResolved->tree->entryNamed('renamed-content')?->oid ?? '')->body;
echo 'rename-add-delete-conflicts=' . count($renameAddDeleteMerge->conflicts) . "\n";
echo 'rename-add-delete-ancestor=' . $renameAddDelete['read']($renameAddDeleteResolved->tree->entryNamed('acme-review.php')?->oid ?? '')->body;
echo 'rename-add-conflicts=' . count($renameAddMerge->conflicts) . "\n";
echo 'rename-add-entry=' . ($renameAddEntry?->filename ?? '') . "\n";
echo 'rename-add-body=' . $renameAdd['read']($renameAddEntry?->oid ?? '')->body;
echo 'rename-delete-same-side-a-clean=' . ($renameDeleteSameSideA->isClean() ? 'yes' : 'no') . "\n";
echo 'rename-delete-same-side-a-plugins=' . $entryNames($renameDeleteSameSideAPlugins) . "\n";
echo 'rename-delete-same-side-a-tools=' . $entryNames($renameDeleteSameSideATools) . "\n";
echo 'rename-delete-same-side-b-clean=' . ($renameDeleteSameSideB->isClean() ? 'yes' : 'no') . "\n";
echo 'rename-delete-same-side-b-plugins=' . $entryNames($renameDeleteSameSideBPlugins) . "\n";
echo 'rename-delete-same-side-b-tools=' . $entryNames($renameDeleteSameSideBTools) . "\n";
echo 'same-rename-mode-conflicts=' . count($sameRenameModeMerge->conflicts) . "\n";
echo 'same-rename-mode-cli-mode=' . ($sameRenameModeTree->entryNamed('cli.php')?->mode ?? '') . "\n";
echo 'same-rename-mode-reverse-cli-mode=' . ($sameRenameModeReverseTree->entryNamed('cli.php')?->mode ?? '') . "\n";
echo 'binary-attr-conflicts=' . count($binaryAttrMerge->conflicts) . "\n";
echo 'binary-attr-hero=' . $binaryAttr['read']($binaryAttrHero?->oid ?? '')->body;
