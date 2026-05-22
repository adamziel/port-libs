<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ReceivePackClient;
use PortLibs\Gitoxide\SendPackSession;
use PortLibs\Gitoxide\StreamReceivePackTransport;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';

$oldCommit = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
$advertisementBytes = $packet("{$oldCommit} refs/heads/main\0report-status-v2 side-band-64k object-format=sha1 atomic push-options\n")
    . $flush;

$blob = new GitObject('blob', "Post title: Native PHP receive-pack transport\n\nStreamed request bytes for a WordPress deployment push.\n");
$tree = (new Tree([
    new TreeEntry('100644', 'wp-content-transport.txt', $blob->oid()),
]))->toObject();
$commit = new GitObject(
    'commit',
    "tree {$tree->oid()}\n"
    . "parent {$oldCommit}\n"
    . "author WordPress <wordpress@example.test> 1700000000 +0000\n"
    . "committer WordPress <wordpress@example.test> 1700000000 +0000\n\n"
    . "Deploy WordPress content through native PHP receive-pack transport\n"
);

$progress = 'Writing objects: 100% (3/3), done.';
$responseBytes = $packet("\x02{$progress}\n")
    . $packet("\x01" . $packet("unpack ok\n"))
    . $packet("\x01" . $packet("ok refs/heads/main\n"))
    . $packet("\x01" . $flush)
    . $flush;

$serverRead = fopen('php://temp', 'r+b');
$clientWrite = fopen('php://temp', 'r+b');
if ($serverRead === false || $clientWrite === false) {
    throw new RuntimeException('Unable to open receive-pack fixture streams');
}
fwrite($serverRead, $advertisementBytes . $responseBytes);
rewind($serverRead);

$client = new ReceivePackClient(new StreamReceivePackTransport($serverRead, $clientWrite), 'port-libs/wordpress');
$response = $client->run(static function (SendPackSession $session) use ($commit, $tree, $blob): mixed {
    $session->command()->useAtomic();
    $session->command()->addPushOption('ci.skip');
    $session->createOrUpdate('refs/heads/main', $commit->oid());

    return $session->buildRequest([$commit, $tree, $blob]);
});

rewind($clientWrite);
$requestBytes = (string) stream_get_contents($clientWrite);

return [
    'oldCommit' => $oldCommit,
    'newCommit' => $commit->oid(),
    'advertisementBytes' => $advertisementBytes,
    'requestBytes' => $requestBytes,
    'responseBytes' => $responseBytes,
    'responseSuccessful' => $response->isSuccessful(),
    'progressMessages' => $response->progressMessages(),
    'acceptedRefs' => array_map(
        static fn ($status): string => $status->effectiveRefName(),
        $response->refStatuses()
    ),
    'wordpressUse' => 'A PHP deployment tool can run a receive-pack handshake/request/response cycle over native stream resources before real SSH or HTTP adapters are introduced.',
];
