<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\ReceivePackAdvertisement;
use PortLibs\Gitoxide\SendPackSession;
use PortLibs\Gitoxide\Tree;
use PortLibs\Gitoxide\TreeEntry;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$stable = '';
for ($i = 0; $i < 64; $i++) {
    $stable .= hash('sha1', 'wordpress-thin-pack-post-row-' . $i) . "\n";
}

$oldBlob = new GitObject('blob', "wp_posts export\n{$stable}post_status=draft\npost_modified=2026-05-21 10:00:00\n");
$newBlob = new GitObject('blob', "wp_posts export\n{$stable}post_status=publish\npost_modified=2026-05-22 06:45:00\n");
$oldTree = (new Tree([
    new TreeEntry('100644', 'wp-content-export.txt', $oldBlob->oid()),
]))->toObject();
$newTree = (new Tree([
    new TreeEntry('100644', 'wp-content-export.txt', $newBlob->oid()),
]))->toObject();
$oldCommit = new GitObject(
    'commit',
    "tree {$oldTree->oid()}\n"
    . "author WordPress <wordpress@example.test> 1700000000 +0000\n"
    . "committer WordPress <wordpress@example.test> 1700000000 +0000\n\n"
    . "Draft WordPress export before publish\n"
);
$newCommit = new GitObject(
    'commit',
    "tree {$newTree->oid()}\n"
    . "parent {$oldCommit->oid()}\n"
    . "author WordPress <wordpress@example.test> 1700000000 +0000\n"
    . "committer WordPress <wordpress@example.test> 1700000100 +0000\n\n"
    . "Publish WordPress export with thin pack delta\n"
);

$advertisementBytes = $packet(
    $oldCommit->oid() . " refs/heads/main\0report-status side-band-64k object-format=sha1 atomic\n"
) . '0000';
$advertisement = ReceivePackAdvertisement::fromV1PacketLines($advertisementBytes);
$session = SendPackSession::create($advertisement, 'port-libs/wordpress');
$session->command()->useAtomic();
$session->createOrUpdate('refs/heads/main', $newCommit->oid());
$request = $session->buildThinRequest(
    [$newCommit, $newTree, $newBlob],
    [$oldCommit, $oldTree, $oldBlob]
);
$pack = $request->pack();

return [
    'advertisementBytes' => $advertisementBytes,
    'oldCommit' => $oldCommit->oid(),
    'newCommit' => $newCommit->oid(),
    'objects' => [
        'oldCommit' => $oldCommit->oid(),
        'newCommit' => $newCommit->oid(),
        'oldTree' => $oldTree->oid(),
        'newTree' => $newTree->oid(),
        'oldBlob' => $oldBlob->oid(),
        'newBlob' => $newBlob->oid(),
    ],
    'requestBytes' => $request->requestBytes(),
    'commandLines' => $session->command()->commandLines(),
    'packBytes' => $pack?->packBytes(),
    'indexBytes' => $pack?->indexBytes(),
    'packChecksum' => $pack?->packChecksum(),
    'packEntries' => $pack?->entries(),
    'thin' => $pack?->isThin(),
    'deltaEntries' => array_values(array_filter(
        $pack?->entries() ?? [],
        static fn (array $entry): bool => ($entry['storage'] ?? 'whole') === 'ref-delta'
    )),
    'wordpressUse' => 'A PHP deployment tool can omit WordPress objects already present on the remote while sending REF_DELTA payloads for changed content.',
];
