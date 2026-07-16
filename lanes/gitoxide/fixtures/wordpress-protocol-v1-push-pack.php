<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\PackBuilder;
use PortLibs\Gitoxide\ProtocolCapabilities;
use PortLibs\Gitoxide\PushCommand;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$blob = new GitObject('blob', "Post title: Native PHP pack builder\n\nPack bytes generated for a WordPress deployment push.\n");
$tree = (new Tree([
    new TreeEntry('100644', 'wp-content-export.txt', $blob->oid()),
]))->toObject();
$commit = new GitObject(
    'commit',
    "tree {$tree->oid()}\n"
    . "author WordPress <wordpress@example.test> 1700000000 +0000\n"
    . "committer WordPress <wordpress@example.test> 1700000000 +0000\n\n"
    . "Deploy WordPress content from native PHP pack builder\n"
);

$pack = PackBuilder::build([$commit, $tree, $blob]);
$capabilities = ProtocolCapabilities::fromV1Bytes(
    "\0report-status report-status-v2 side-band side-band-64k object-format=sha1 atomic push-options"
)['capabilities'];

$oldCommit = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
$command = PushCommand::create($capabilities, 'port-libs/wordpress');
$command->useAtomic();
$command->updateRef($oldCommit, $commit->oid(), 'refs/heads/main');
$command->createRef($commit->oid(), 'refs/tags/wp-release');
$command->addPushOption('ci.skip');

return [
    'oldCommit' => $oldCommit,
    'newCommit' => $commit->oid(),
    'objects' => [
        'commit' => $commit->oid(),
        'tree' => $tree->oid(),
        'blob' => $blob->oid(),
    ],
    'packBytes' => $pack->packBytes(),
    'indexBytes' => $pack->indexBytes(),
    'packChecksum' => $pack->packChecksum(),
    'packEntries' => $pack->entries(),
    'commandLines' => $command->commandLines(),
    'requestBytes' => $command->requestWithPack($pack),
    'wordpressUse' => 'A PHP deployment tool can generate pack bytes for a WordPress branch/tag receive-pack request without shelling out to git pack-objects.',
];
