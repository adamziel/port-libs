<?php

declare(strict_types=1);

use PortLibs\Quadrable\DiffEntry;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\SparseTree;
use PortLibs\Quadrable\SyncCodec;
use PortLibs\Quadrable\SyncRequest;
use PortLibs\Quadrable\SyncSession;

return [
    'maps upstream sync request and response transport round trips' => static function (TestRunner $t): void {
        $requests = [
            new SyncRequest(Key::null(), 0, 4, false),
            new SyncRequest(Key::fromInteger(12), 7, 1, true),
        ];

        $encodedRequests = SyncCodec::encodeRequests($requests);
        $decodedRequests = SyncCodec::decodeRequests($encodedRequests);

        $t->same($encodedRequests, SyncCodec::encodeRequests($decodedRequests));
        $t->same(0, $decodedRequests[0]->startDepth);
        $t->same(4, $decodedRequests[0]->depthLimit);
        $t->true(!$decodedRequests[0]->expandLeaves);
        $t->same(7, $decodedRequests[1]->startDepth);
        $t->true($decodedRequests[1]->expandLeaves);

        $tree = new SparseTree();
        $tree->change()
            ->putKey(Key::fromInteger(1), 'one')
            ->putKey(Key::fromInteger(2), 'two')
            ->apply();

        $responses = [$tree->exportRawProof([Key::fromInteger(1)])];
        $encodedResponses = SyncCodec::encodeResponses($responses);
        $decodedResponses = SyncCodec::decodeResponses($encodedResponses);

        $t->same($encodedResponses, SyncCodec::encodeResponses($decodedResponses));
        $t->same($tree->rootHash(), SparseTree::importProof($decodedResponses[0], $tree->rootHash())->rootHash());
    },
    'maps upstream sync proof fragments through bounded witness expansion' => static function (TestRunner $t): void {
        $local = new SparseTree();
        $local->change()
            ->putKey(Key::fromInteger(1), 'one')
            ->putKey(Key::fromInteger(2), 'two')
            ->apply();

        $remote = new SparseTree();
        $remote->change()
            ->putKey(Key::fromInteger(1), 'one')
            ->putKey(Key::fromInteger(2), 'two updated')
            ->putKey(Key::fromInteger(3), str_repeat('three ', 8))
            ->apply();

        $session = new SyncSession($local, 1, 2);
        $roundTrips = 0;

        while (true) {
            $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(256)));
            if ($requests === []) {
                break;
            }

            $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, 512)));
            $session->addResponses($requests, $responses);

            $roundTrips++;
            $t->true($roundTrips < 20, 'sync should converge on a small sparse tree');
        }

        $shadow = $session->shadow();
        $t->same($remote->rootHash(), $shadow->rootHash());

        $diffs = $local->diffTo($shadow);
        $t->same([DiffEntry::CHANGED, DiffEntry::ADDED], array_map(static fn (DiffEntry $diff): string => $diff->type, $diffs));

        $reconstructed = new SparseTree();
        $reconstructed->change()
            ->putKey(Key::fromInteger(1), 'one')
            ->putKey(Key::fromInteger(2), 'two')
            ->apply();
        $reconstructed->applyDiffs($diffs);

        $t->same($remote->rootHash(), $reconstructed->rootHash());
    },
    'wordpress sync diffs reconstruct a changed authenticated snapshot' => static function (TestRunner $t): void {
        $local = quadrableSyncWordPressSnapshotTree();
        $remote = quadrableSyncWordPressSnapshotTree();

        $remote->change()
            ->putKey(Key::fromInteger(3), 'wp_posts:1=Hello synced world')
            ->deleteKey(Key::fromInteger(4))
            ->putKey(Key::fromInteger(6), 'wp_posts:2=' . str_repeat('Imported block ', 4))
            ->apply();

        $session = new SyncSession($local, 1, 2);
        for ($roundTrips = 0; $roundTrips < 20; $roundTrips++) {
            $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(512)));
            if ($requests === []) {
                break;
            }

            $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, 1024)));
            $session->addResponses($requests, $responses);
        }

        $shadow = $session->shadow();
        $t->same($remote->rootHash(), $shadow->rootHash());

        $diffsByKey = [];
        foreach ($local->diffTo($shadow) as $diff) {
            $diffsByKey[$diff->key()->toInteger()] = $diff;
        }

        $t->same(DiffEntry::CHANGED, $diffsByKey[3]->type);
        $t->same(DiffEntry::DELETED, $diffsByKey[4]->type);
        $t->same(DiffEntry::ADDED, $diffsByKey[6]->type);

        $reconstructed = quadrableSyncWordPressSnapshotTree();
        $reconstructed->applyDiffs(array_values($diffsByKey));

        $t->same($remote->rootHash(), $reconstructed->rootHash());
        $t->same('wp_posts:1=Hello synced world', $reconstructed->getKey(Key::fromInteger(3)));
        $t->same(null, $reconstructed->getKey(Key::fromInteger(4)));
        $t->same('wp_posts:2=' . str_repeat('Imported block ', 4), $reconstructed->getKey(Key::fromInteger(6)));
    },
];

function quadrableSyncWordPressSnapshotTree(): SparseTree
{
    $records = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/wordpress-ordered-snapshot.json'), true, flags: JSON_THROW_ON_ERROR);

    $tree = new SparseTree();
    $changes = $tree->change();
    foreach ($records as $record) {
        $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
    }
    $changes->apply();

    return $tree;
}
