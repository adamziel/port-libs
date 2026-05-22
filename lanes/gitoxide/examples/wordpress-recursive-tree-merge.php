<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;
use PortLibs\Gitoxide\TreeMerge;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$objects = [];
$write = static function (GitObject $object) use (&$objects): string {
    $oid = $object->oid();
    $objects[$oid] = $object;

    return $oid;
};
$read = static function (string $oid) use (&$objects): GitObject {
    if (!isset($objects[$oid])) {
        throw new RuntimeException("Object not found: {$oid}");
    }

    return $objects[$oid];
};
$blob = static fn (string $name, string $content): TreeEntry => new TreeEntry('100644', $name, $write(new GitObject('blob', $content)));
$tree = static fn (string $name, Tree $tree): TreeEntry => new TreeEntry('40000', $name, $write($tree->toObject()));
$wpContent = static fn (TreeEntry $metadata, TreeEntry $theme): Tree => new Tree([
    $metadata,
    $tree('themes', new Tree([
        $tree('acme', new Tree([$theme])),
    ])),
]);

$base = new Tree([
    $tree('wp-content', $wpContent(
        $blob('post.meta', "title: Demo\nslug: demo\nstatus: draft\n"),
        $blob('theme.json', "{\n  \"color\": \"base\"\n}\n"),
    )),
]);
$ours = new Tree([
    $tree('wp-content', $wpContent(
        $blob('post.meta', "title: Demo Import\nslug: demo\nstatus: draft\n"),
        $blob('theme.json', "{\n  \"color\": \"blue\"\n}\n"),
    )),
]);
$theirs = new Tree([
    $tree('wp-content', $wpContent(
        $blob('post.meta', "title: Demo\nslug: demo\nstatus: publish\n"),
        $blob('theme.json', "{\n  \"color\": \"green\"\n}\n"),
    )),
]);

$result = TreeMerge::mergeRecursive($base, $ours, $theirs, $read, $write, BlobMerge::STYLE_DIFF3);
$contentTree = Tree::fromObject($read($result->tree->entryNamed('wp-content', true)?->oid ?? ''));
$metadata = $read($contentTree->entryNamed('post.meta')?->oid ?? '');

echo json_encode([
    'clean' => $result->isClean(),
    'conflicts' => array_map(
        static fn ($conflict): array => ['path' => $conflict->path, 'reason' => $conflict->reason],
        $result->conflicts,
    ),
    'mergedMetadata' => $metadata->body,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
