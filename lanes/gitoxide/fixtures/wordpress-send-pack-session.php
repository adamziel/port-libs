<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ReceivePackAdvertisement;
use PortLibs\Gitoxide\SendPackSession;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';

$oldCommit = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
$advertisementBytes = $packet("{$oldCommit} refs/heads/main\0report-status report-status-v2 side-band side-band-64k object-format=sha1 atomic push-options agent=git/2.50.1\n")
    . $flush;
$advertisement = ReceivePackAdvertisement::fromV1PacketLines($advertisementBytes);

$blob = new GitObject('blob', "Post title: Native PHP send-pack\n\nGenerated pack payload for WordPress deployment orchestration.\n");
$tree = (new Tree([
    new TreeEntry('100644', 'wp-content-send-pack.txt', $blob->oid()),
]))->toObject();
$commit = new GitObject(
    'commit',
    "tree {$tree->oid()}\n"
    . "parent {$oldCommit}\n"
    . "author WordPress <wordpress@example.test> 1700000000 +0000\n"
    . "committer WordPress <wordpress@example.test> 1700000000 +0000\n\n"
    . "Deploy WordPress content via native PHP send-pack session\n"
);

$session = SendPackSession::create($advertisement, 'port-libs/wordpress');
$session->command()->useAtomic();
$session->command()->addPushOption('ci.skip');
$session->createOrUpdate('refs/heads/main', $commit->oid());
$session->createOrUpdate('refs/tags/wp-release', $commit->oid());
$request = $session->buildRequest([$commit, $tree, $blob]);

$progress = 'Resolving deltas: 100% (0/0), completed with 0 local objects.';
$statusOldPrefix = str_repeat('d', 40);
$statusNewPrefix = str_repeat('e', 40);
$responseBytes = $packet("\x02{$progress}\n")
    . $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main accepted with hook object diagnostics\n"))
    . $packet("\x01" . $packet("option old-oid {$statusOldPrefix}feed\n"))
    . $packet("\x01" . $packet("option new-oid {$statusNewPrefix}cafe\n"))
    . $packet("\x01" . $packet("ok refs/tags/wp-release\n"))
    . $packet("\x01" . $flush)
    . $flush;

return [
    'oldCommit' => $oldCommit,
    'newCommit' => $commit->oid(),
    'advertisementBytes' => $advertisementBytes,
    'requestBytes' => $request->requestBytes(),
    'responseBytes' => $responseBytes,
    'packBytes' => $request->pack()?->packBytes(),
    'indexBytes' => $request->pack()?->indexBytes(),
    'commandLines' => $request->command()->commandLines(),
    'expectedRefs' => [
        'refs/heads/main',
        'refs/tags/wp-release',
    ],
    'expectedStatusObjects' => [
        'oldObject' => $statusOldPrefix,
        'newObject' => $statusNewPrefix,
    ],
    'wordpressUse' => 'A PHP deployment tool can parse receive-pack advertised refs, build a native pack, send a branch/tag update request, and parse the remote status response without invoking git.',
];
