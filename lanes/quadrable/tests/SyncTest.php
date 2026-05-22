<?php

declare(strict_types=1);

use PortLibs\Quadrable\DiffEntry;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\Mt19937;
use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\SparseTree;
use PortLibs\Quadrable\SyncCodec;
use PortLibs\Quadrable\SyncRequest;
use PortLibs\Quadrable\SyncSession;
use PortLibs\Quadrable\TrackedNodeStore;

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
    'maps upstream sync fragment request path ordering guard' => static function (TestRunner $t): void {
        $remote = new SparseTree();
        $remote->change()
            ->putKey(Key::fromInteger(1), 'one')
            ->putKey(Key::fromInteger(2), 'two')
            ->putKey(Key::fromInteger(128), 'one twenty eight')
            ->apply();

        $validRequests = [
            new SyncRequest(Key::null(), 0, 1, false),
            new SyncRequest(Key::max(), 1, 1, false),
        ];
        $t->same(2, count($remote->handleSyncRequests($validRequests, 4096)));

        $samePathNestedRequests = [
            new SyncRequest(Key::null(), 0, 1, false),
            new SyncRequest(Key::null(), 1, 1, false),
            new SyncRequest(Key::max(), 1, 1, false),
        ];
        $t->throws(InvalidArgumentException::class, static fn () => $remote->handleSyncRequests($samePathNestedRequests, 4096));

        $outOfOrderRequests = [
            new SyncRequest(Key::max(), 1, 1, false),
            new SyncRequest(Key::null(), 0, 1, false),
        ];
        $t->throws(InvalidArgumentException::class, static fn () => $remote->handleSyncRequests($outOfOrderRequests, 4096));
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
    'wordpress sync request guard rejects overlapping proof fragment paths' => static function (TestRunner $t): void {
        $remote = quadrableSyncWordPressSnapshotTree();
        $remote->change()
            ->putKey(Key::fromInteger(3), 'wp_posts:1=Guarded proof fragment')
            ->apply();

        $requests = [
            new SyncRequest(Key::null(), 0, 1, false),
            new SyncRequest(Key::null(), 1, 1, false),
            new SyncRequest(Key::max(), 1, 1, false),
        ];

        $t->throws(InvalidArgumentException::class, static fn () => $remote->handleSyncRequests($requests, 2048));
    },
    'wordpress sync scan callback matches final authenticated diff' => static function (TestRunner $t): void {
        $local = quadrableSyncWordPressSnapshotTree();
        $remote = quadrableSyncWordPressSnapshotTree();
        $remote->change()
            ->putKey(Key::fromInteger(2), 'wp_options:home=https://mirror.example.test')
            ->deleteKey(Key::fromInteger(5))
            ->putKey(Key::fromInteger(6), 'wp_posts:3=Scan callback import')
            ->apply();

        $session = new SyncSession($local, 1, 2);
        $scanDiffs = [];
        $converged = false;

        for ($roundTrips = 0; $roundTrips < 20; $roundTrips++) {
            $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(
                512,
                static function (DiffEntry $diff) use (&$scanDiffs): void {
                    $scanDiffs[] = $diff;
                }
            )));
            if ($requests === []) {
                $converged = true;
                break;
            }

            $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, 1024)));
            $session->addResponses($requests, $responses);
        }

        $t->true($converged, 'sync should converge before the round-trip limit');
        $shadow = $session->shadow();
        $finalDiffs = $local->diffTo($shadow);

        $t->same($remote->rootHash(), $shadow->rootHash());
        $t->same(quadrableSyncDiffSignature($finalDiffs), quadrableSyncDiffSignature($scanDiffs));

        $session->getRequests(512, static function (DiffEntry $diff) use (&$scanDiffs): void {
            $scanDiffs[] = $diff;
        });
        $t->same(quadrableSyncDiffSignature($finalDiffs), quadrableSyncDiffSignature($scanDiffs));
    },
    'sync proof fragments preserve upstream shaped imported diff node ids' => static function (TestRunner $t): void {
        $local = new SparseTree();
        $local->change()
            ->putKey(Key::fromInteger(1), 'one')
            ->putKey(Key::fromInteger(2), 'two')
            ->putKey(Key::fromInteger(4), 'four')
            ->apply();

        $remote = clone $local;
        $remote->change()
            ->putKey(Key::fromInteger(2), 'two updated')
            ->deleteKey(Key::fromInteger(4))
            ->putKey(Key::fromInteger(3), 'three')
            ->apply();

        $session = new SyncSession($local, 1, 1);
        $scanDiffs = [];
        $converged = false;

        for ($roundTrips = 0; $roundTrips < 50; $roundTrips++) {
            $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(
                128,
                static function (DiffEntry $diff) use (&$scanDiffs): void {
                    $scanDiffs[] = $diff;
                }
            )));
            if ($requests === []) {
                $converged = true;
                break;
            }

            $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, 512)));
            $session->addResponses($requests, $responses);
        }

        $t->true($converged, 'sync should converge before checking node id parity');

        $finalDiffs = $local->diffTo($session->shadow());
        $diffsByKey = [];
        foreach ($finalDiffs as $diff) {
            $diffsByKey[$diff->key()->toInteger()] = $diff;
        }

        $t->same(quadrableSyncNodeIdSignature($finalDiffs), quadrableSyncNodeIdSignature($scanDiffs));
        $t->true($diffsByKey[2]->nodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID, 'changed imported leaf should use memStore-range node id');
        $t->true($diffsByKey[3]->nodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID, 'added imported leaf should use memStore-range node id');
        $t->true($diffsByKey[4]->nodeId > 0 && $diffsByKey[4]->nodeId < TrackedNodeStore::FIRST_MEMSTORE_NODE_ID, 'deleted local leaf should keep a local node id');
    },
    'sync session exposes upstream shaped shadow root node ids' => static function (TestRunner $t): void {
        $local = new SparseTree();
        $local->change()
            ->putKey(Key::fromInteger(1), 'one')
            ->putKey(Key::fromInteger(2), 'two')
            ->putKey(Key::fromInteger(5), str_repeat('five ', 10))
            ->apply();

        $remote = clone $local;
        $remote->change()
            ->putKey(Key::fromInteger(2), 'two imported through shadow node')
            ->deleteKey(Key::fromInteger(5))
            ->putKey(Key::fromInteger(3), str_repeat('three ', 12))
            ->putKey(Key::fromInteger(8), str_repeat('eight ', 9))
            ->apply();

        $session = new SyncSession($local, 1, 1);
        $t->throws(RuntimeException::class, static fn () => $session->shadowNodeId());

        $shadowRootNodeIds = [];
        $converged = false;

        for ($roundTrips = 0; $roundTrips < 50; $roundTrips++) {
            $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(96)));
            if ($requests === []) {
                $converged = true;
                break;
            }

            $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, 256)));
            $session->addResponses($requests, $responses);
            $shadowRootNodeIds[] = $session->shadowNodeId();

            $t->same($session->shadow()->partialRootNodeId(), $session->shadowNodeId());
            foreach ($session->shadowNodeIds() as $nodeId) {
                $t->true($nodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID, 'imported shadow node id should be in the memStore range');
            }
        }

        $t->true($converged, 'sync should converge before checking shadow node ids');
        $t->true(count(array_unique($shadowRootNodeIds)) > 1, 'expanding proof fragments should produce fresh shadow root node ids');
        $t->same($remote->rootHash(), $session->shadow()->rootHash());
    },
    'deterministic upstream shaped sync fuzz converges with scan diff equivalence' => static function (TestRunner $t): void {
        $state = 0;

        for ($trial = 0; $trial < 12; $trial++) {
            $seed = new SparseTree();
            $changes = $seed->change();
            $numElems = 8 + quadrableSyncNext($state, 35);
            $maxElem = 75;

            for ($i = 0; $i < $numElems; $i++) {
                $n = quadrableSyncNext($state, $maxElem);
                $changes->putKey(Key::fromInteger($n), quadrableSyncValue($n, quadrableSyncNext($state, 20), quadrableSyncNext($state, 58)));
            }
            $changes->apply();

            $local = clone $seed;
            $remote = clone $seed;
            $alterations = 4 + quadrableSyncNext($state, 28);
            $remoteChanges = $remote->change();

            for ($i = 0; $i < $alterations; $i++) {
                $n = quadrableSyncNext($state, $maxElem);
                if (quadrableSyncNext($state, 2) === 0) {
                    $remoteChanges->putKey(Key::fromInteger($n), quadrableSyncValue($n, 100 + $trial, quadrableSyncNext($state, 64)));
                } else {
                    $remoteChanges->deleteKey(Key::fromInteger($n));
                }
            }
            $remoteChanges->apply();

            $session = new SyncSession($local, 1, 1);
            $scanDiffs = [];
            $converged = false;

            for ($roundTrips = 0; $roundTrips < 80; $roundTrips++) {
                $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(
                    64,
                    static function (DiffEntry $diff) use (&$scanDiffs): void {
                        $scanDiffs[] = $diff;
                    }
                )));
                if ($requests === []) {
                    $converged = true;
                    break;
                }

                $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, 192)));
                $session->addResponses($requests, $responses);
            }

            $t->true($converged, 'sync fuzz trial did not converge: ' . $trial);
            $shadow = $session->shadow();
            $finalDiffs = $local->diffTo($shadow);
            $reconstructed = clone $local;
            $reconstructed->applyDiffs($finalDiffs);

            $t->same($remote->rootHash(), $shadow->rootHash(), 'shadow root mismatch on trial ' . $trial);
            $t->same($remote->rootHash(), $reconstructed->rootHash(), 'reconstructed root mismatch on trial ' . $trial);
            $t->same(quadrableSyncDiffSignature($finalDiffs), quadrableSyncDiffSignature($scanDiffs), 'scan diff mismatch on trial ' . $trial);
            $t->same(quadrableSyncNodeIdSignature($finalDiffs), quadrableSyncNodeIdSignature($scanDiffs), 'scan diff node id mismatch on trial ' . $trial);
        }
    },
    'bounded upstream mt19937 sync fuzz converges with authenticated diff parity' => static function (TestRunner $t): void {
        $vector = new Mt19937(0);
        $t->same(2357136044, $vector->nextUint32());
        $t->same(2546248239, $vector->nextUint32());
        $t->same(3071714933, $vector->nextUint32());
        $t->same(3626093760, $vector->nextUint32());
        $t->same(2588848963, $vector->nextUint32());

        $dimensions = new Mt19937(0);
        $t->same(44, $dimensions->nextModulo(800));
        $t->same(39, $dimensions->nextModulo(200));

        $rng = new Mt19937(0);
        for ($trial = 0; $trial < 2; $trial++) {
            $seed = new SparseTree();
            $changes = $seed->change();
            $numElems = $rng->nextModulo(800);
            $maxElem = 1000;
            $numAlterations = $rng->nextModulo(200);

            for ($i = 0; $i < $numElems; $i++) {
                $number = $rng->nextModulo($maxElem);
                $changes->putKey(
                    Key::fromInteger($number),
                    (string) $number . str_repeat('A', $rng->nextModulo(60))
                );
            }
            $changes->apply();

            $local = clone $seed;
            $remote = clone $seed;
            $remoteChanges = $remote->change();

            for ($i = 0; $i < $numAlterations; $i++) {
                $number = $rng->nextModulo($maxElem);
                if ($rng->nextModulo(2) === 0) {
                    $remoteChanges->putKey(Key::fromInteger($number), (string) $number . ' new');
                } else {
                    $remoteChanges->deleteKey(Key::fromInteger($number));
                }
            }
            $remoteChanges->apply();

            $session = new SyncSession($local);
            $scanDiffs = [];
            $converged = false;

            for ($roundTrips = 0; $roundTrips < 200; $roundTrips++) {
                $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(
                    $rng->nextModulo(1000) + 100,
                    static function (DiffEntry $diff) use (&$scanDiffs): void {
                        $scanDiffs[] = $diff;
                    }
                )));
                if ($requests === []) {
                    $converged = true;
                    break;
                }

                $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, $rng->nextModulo(10000) + 2000)));
                $session->addResponses($requests, $responses);
            }

            $t->true($converged, 'upstream-shaped sync fuzz trial did not converge: ' . $trial);
            $shadow = $session->shadow();
            $finalDiffs = $local->diffTo($shadow);
            $reconstructed = clone $local;
            $reconstructed->applyDiffs($finalDiffs);

            $t->same($remote->rootHash(), $shadow->rootHash(), 'shadow root mismatch on upstream-shaped trial ' . $trial);
            $t->same($remote->rootHash(), $reconstructed->rootHash(), 'reconstructed root mismatch on upstream-shaped trial ' . $trial);
            $t->same(quadrableSyncDiffSignature($finalDiffs), quadrableSyncDiffSignature($scanDiffs), 'scan diff mismatch on upstream-shaped trial ' . $trial);
            $t->same(quadrableSyncNodeIdSignature($finalDiffs), quadrableSyncNodeIdSignature($scanDiffs), 'scan diff node id mismatch on upstream-shaped trial ' . $trial);
        }
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

/**
 * @param list<DiffEntry> $diffs
 *
 * @return list<string>
 */
function quadrableSyncDiffSignature(array $diffs): array
{
    $signature = array_map(
        static fn (DiffEntry $diff): string => $diff->type . ':' . $diff->keyHex() . ':' . $diff->value,
        $diffs
    );
    sort($signature, SORT_STRING);

    return $signature;
}

/**
 * @param list<DiffEntry> $diffs
 *
 * @return list<string>
 */
function quadrableSyncNodeIdSignature(array $diffs): array
{
    $signature = array_map(
        static fn (DiffEntry $diff): string => $diff->type . ':' . $diff->keyHex() . ':' . $diff->value . ':' . $diff->nodeId,
        $diffs
    );
    sort($signature, SORT_STRING);

    return $signature;
}

function quadrableSyncNext(int &$state, int $mod): int
{
    $state = ($state * 1103515245 + 12345) & 0x7fffffff;

    return $state % $mod;
}

function quadrableSyncValue(int $number, int $variant, int $extraLength): string
{
    return $number . ':' . $variant . ':' . str_repeat(chr(65 + (($number + $variant) % 26)), $extraLength);
}
