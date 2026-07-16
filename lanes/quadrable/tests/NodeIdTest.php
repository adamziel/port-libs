<?php

declare(strict_types=1);

use PortLibs\Quadrable\DiffEntry;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\TrackedNodeStore;
use PortLibs\Quadrable\TrackedSparseTree;

return [
    'maps upstream saved node id outputs for puts deletes and duplicates' => static function (TestRunner $t): void {
        $tree = new TrackedSparseTree();
        $nodeId = 999999;

        $tree->change()
            ->put('A', '1', $nodeId)
            ->apply();

        $t->true($nodeId !== 0);
        $t->same($nodeId, $tree->headNodeId());
        $t->same('1', $tree->leafValueForNodeId($nodeId));

        $nodeId = 999999;
        $headBeforeDuplicate = $tree->headNodeId();
        $tree->change()
            ->put('A', '1', $nodeId)
            ->apply();

        $t->same(0, $nodeId);
        $t->same($headBeforeDuplicate, $tree->headNodeId());

        $nodeId = 999999;
        $tree->change()
            ->put('A', '2', $nodeId)
            ->apply();

        $t->true($nodeId !== 0);
        $t->same($nodeId, $tree->headNodeId());
        $t->same('2', $tree->leafValueForNodeId($nodeId));

        $originalHead = $tree->headNodeId();
        $nodeId1 = 55555;
        $nodeId2 = 44444;
        $nodeId3 = 33333;
        $nodeIdDel1 = 8888;
        $nodeIdDel2 = 7777;

        $tree->change()
            ->delete('A', $nodeIdDel1)
            ->put('B', '2', $nodeId1)
            ->put('D', '4', $nodeId2)
            ->put('C', '3')
            ->put('E', '5', $nodeId3)
            ->delete('NONE', $nodeIdDel2)
            ->apply();

        $t->same('2', $tree->leafValueForNodeId($nodeId1));
        $t->same('4', $tree->leafValueForNodeId($nodeId2));
        $t->same('5', $tree->leafValueForNodeId($nodeId3));
        $t->same($originalHead, $nodeIdDel1);
        $t->same(0, $nodeIdDel2);

        $nodeIdDuplicate = 999999;
        $tree->change()
            ->put('B', '2', $nodeIdDuplicate)
            ->apply();

        $t->same(0, $nodeIdDuplicate);
    },
    'maps upstream leaf reuse across a fresh checkout' => static function (TestRunner $t): void {
        $original = new TrackedSparseTree();
        $changes = $original->change();
        for ($i = 0; $i < 10; $i++) {
            $changes->putKey(Key::fromInteger($i), 'N = ' . $i);
        }
        $changes->apply();

        $originalHeadNodeId = $original->headNodeId();
        $originalRoot = $original->rootHash();
        $sampleLeafNodeId = 99999999;
        $t->same('N = 6', $original->getKey(Key::fromInteger(6), $sampleLeafNodeId));

        $rebuilt = $original->checkoutEmpty();
        $reuseChanges = $rebuilt->change();
        $newSampleNodeId = 0;
        $iterator = $original->iterate(Key::null());

        while (!$iterator->atEnd()) {
            $entry = $iterator->get();
            $t->true($entry !== null);
            if ($entry->key()->toInteger() === 6) {
                $reuseChanges->putReuse($entry->key(), $entry->nodeId, $newSampleNodeId);
            } else {
                $unusedNodeId = 0;
                $reuseChanges->putReuse($entry->key(), $entry->nodeId, $unusedNodeId);
            }
            $iterator->next();
        }

        $reuseChanges->apply();

        $t->same($originalRoot, $rebuilt->rootHash());
        $t->true($rebuilt->headNodeId() !== $originalHeadNodeId);
        $t->same($sampleLeafNodeId, $newSampleNodeId);
        $t->same('N = 6', $rebuilt->getKey(Key::fromInteger(6)));
    },
    'maps upstream fork checkout by saved branch node id' => static function (TestRunner $t): void {
        $tree = new TrackedSparseTree();
        $tree->change()
            ->put('a', 'A')
            ->put('b', 'B')
            ->put('c', 'C')
            ->put('d', 'D')
            ->apply();

        $originalHeadNodeId = $tree->headNodeId();
        $originalRoot = $tree->rootHash();
        $t->true($originalHeadNodeId >= TrackedNodeStore::FIRST_INTERIOR_NODE_ID);

        $fork = $tree->checkout($originalHeadNodeId);
        $fork->put('e', 'E');
        $newHeadNodeId = $fork->headNodeId();

        $t->true($newHeadNodeId !== $originalHeadNodeId);
        $t->same('A', $fork->get('a'));
        $t->same('E', $fork->get('e'));

        $oldCheckout = $fork->checkout($originalHeadNodeId);
        $t->same($originalRoot, $oldCheckout->rootHash());
        $t->same('A', $oldCheckout->get('a'));
        $t->same(null, $oldCheckout->get('e'));

        $newCheckout = $fork->checkout($newHeadNodeId);
        $t->same($fork->rootHash(), $newCheckout->rootHash());
        $t->same('E', $newCheckout->get('e'));
    },
    'maps upstream memStore basic with high detached node ids' => static function (TestRunner $t): void {
        $tree = TrackedSparseTree::memoryOnly();
        $nodeIdA = 0;
        $nodeIdB = 0;

        $tree->change()
            ->put('A', 'res1', $nodeIdA)
            ->put('B', 'res2', $nodeIdB)
            ->apply();

        $t->true($tree->writesToMemStore());
        $t->true($tree->headNodeId() >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID);
        $t->true($nodeIdA >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID);
        $t->true($nodeIdB >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID);
        $t->same('res1', $tree->get('A'));
        $t->same('res2', $tree->get('B'));

        $stats = $tree->stats();
        $t->same(2, $stats['numLeafNodes']);
    },
    'maps upstream memStore overlay fork from an existing tracked head' => static function (TestRunner $t): void {
        $base = new TrackedSparseTree();
        $nodeIdA = 0;
        $nodeIdB = 0;
        $base->change()
            ->put('A', 'res1', $nodeIdA)
            ->put('B', 'res2', $nodeIdB)
            ->apply();

        $baseHeadNodeId = $base->headNodeId();
        $baseRoot = $base->rootHash();

        $overlay = $base->withMemStoreWrites();
        $nodeIdC = 0;
        $overlay->put('C', 'res3', $nodeIdC);

        $t->true($nodeIdA < TrackedNodeStore::FIRST_MEMSTORE_NODE_ID);
        $t->true($nodeIdB < TrackedNodeStore::FIRST_MEMSTORE_NODE_ID);
        $t->true($nodeIdC >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID);
        $t->true($overlay->headNodeId() >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID);
        $t->same($baseHeadNodeId, $base->headNodeId());
        $t->same($baseRoot, $base->rootHash());
        $t->same(null, $base->get('C'));
        $t->same('res1', $overlay->get('A'));
        $t->same('res2', $overlay->get('B'));
        $t->same('res3', $overlay->get('C'));

        $stats = $overlay->stats();
        $t->same(3, $stats['numLeafNodes']);
    },
    'maps upstream memStore named head guard and explicit fork escape' => static function (TestRunner $t): void {
        $db = (new TrackedSparseTree())->checkout('memStore-test');
        $db->change()
            ->put('A', 'res1')
            ->put('B', 'res2')
            ->apply();

        $origNode = $db->headNodeId();
        $origRoot = $db->rootHash();
        $t->true(!$db->isDetachedHead());
        $t->same('memStore-test', $db->headName());

        $guarded = $db->checkout('memStore-test')->withMemStoreWrites();
        $t->throws(RuntimeException::class, static fn () => $guarded->change()->put('C', 'res3')->apply());
        $t->same($origNode, $guarded->headNodeId());
        $t->same($origRoot, $guarded->rootHash());
        $t->same(null, $guarded->get('C'));

        $fork = $guarded->fork();
        $nodeIdC = 0;
        $fork->put('C', 'res3', $nodeIdC);

        $t->true($fork->isDetachedHead());
        $t->throws(RuntimeException::class, static fn () => $fork->headName());
        $t->true($fork->headNodeId() >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID);
        $t->true($nodeIdC >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID);
        $t->same('res1', $fork->get('A'));
        $t->same('res2', $fork->get('B'));
        $t->same('res3', $fork->get('C'));
        $t->same(3, $fork->stats()['numLeafNodes']);

        $namedCheckout = $fork->checkout('memStore-test');
        $t->same($origNode, $namedCheckout->headNodeId());
        $t->same($origRoot, $namedCheckout->rootHash());
        $t->same(null, $namedCheckout->get('C'));
        $t->same(2, $namedCheckout->stats()['numLeafNodes']);
    },
    'copy-on-write updates preserve unchanged branch node ids' => static function (TestRunner $t): void {
        $tree = new TrackedSparseTree();
        $tree->change()
            ->putKey(Key::fromHex('00' . str_repeat('00', 31)), 'left-0')
            ->putKey(Key::fromHex('40' . str_repeat('00', 31)), 'left-1')
            ->putKey(Key::fromHex('80' . str_repeat('00', 31)), 'right-0')
            ->putKey(Key::fromHex('c0' . str_repeat('00', 31)), 'right-1')
            ->apply();

        $originalHeadNodeId = $tree->headNodeId();
        $originalChildren = $tree->branchChildren($originalHeadNodeId);
        $rightBranchNodeId = $originalChildren['rightNodeId'];
        $leftBranchNodeId = $originalChildren['leftNodeId'];

        $tree->putKey(Key::fromHex('00' . str_repeat('00', 31)), 'left-0-updated');

        $newChildren = $tree->branchChildren($tree->headNodeId());
        $t->true($tree->headNodeId() !== $originalHeadNodeId);
        $t->true($newChildren['leftNodeId'] !== $leftBranchNodeId);
        $t->same($rightBranchNodeId, $newChildren['rightNodeId']);
        $t->same($tree->nodeHash($rightBranchNodeId), $tree->nodeHash($newChildren['rightNodeId']));
        $t->same('right-0', $tree->getKey(Key::fromHex('80' . str_repeat('00', 31))));
    },
    'persisted tracked node store restores named heads and branch node ids' => static function (TestRunner $t): void {
        $store = new TrackedNodeStore();
        $published = (new TrackedSparseTree($store))->checkout('published');
        $published->change()
            ->putKey(Key::fromHex('00' . str_repeat('00', 31)), 'left-0')
            ->putKey(Key::fromHex('40' . str_repeat('00', 31)), 'left-1')
            ->putKey(Key::fromHex('80' . str_repeat('00', 31)), 'right-0')
            ->putKey(Key::fromHex('c0' . str_repeat('00', 31)), 'right-1')
            ->apply();

        $publishedHeadNodeId = $published->headNodeId();
        $publishedRoot = $published->rootHash();
        $publishedChildren = $published->branchChildren($publishedHeadNodeId);
        $rightBranchNodeId = $publishedChildren['rightNodeId'];

        $restoredStore = TrackedNodeStore::fromSnapshot($store->exportSnapshot());
        $restored = (new TrackedSparseTree($restoredStore))->checkout('published');

        $t->same($publishedHeadNodeId, $restored->headNodeId());
        $t->same($publishedRoot, $restored->rootHash());
        $t->same($publishedChildren, $restored->branchChildren($restored->headNodeId()));
        $t->same('left-0', $restored->getKey(Key::fromHex('00' . str_repeat('00', 31))));
        $t->same('right-1', $restored->getKey(Key::fromHex('c0' . str_repeat('00', 31))));

        $restored->putKey(Key::fromHex('00' . str_repeat('00', 31)), 'left-0-updated');
        $updatedChildren = $restored->branchChildren($restored->headNodeId());

        $t->true($restored->headNodeId() !== $publishedHeadNodeId);
        $t->same($rightBranchNodeId, $updatedChildren['rightNodeId']);
        $t->same('right-0', $restored->getKey(Key::fromHex('80' . str_repeat('00', 31))));

        $badSnapshot = $store->exportSnapshot();
        $badSnapshot['branches'][$publishedHeadNodeId]['rightNodeId'] = 123456789;
        $t->throws(InvalidArgumentException::class, static fn () => TrackedNodeStore::fromSnapshot($badSnapshot));
    },
    'tracked diffs emit upstream sync leaf node ids for changed added and deleted leaves' => static function (TestRunner $t): void {
        $local = new TrackedSparseTree();
        $local->change()
            ->putKey(Key::fromInteger(1), 'one')
            ->putKey(Key::fromInteger(2), 'two')
            ->putKey(Key::fromInteger(3), 'three')
            ->apply();

        $oldTwoNodeId = 0;
        $oldThreeNodeId = 0;
        $t->same('two', $local->getKey(Key::fromInteger(2), $oldTwoNodeId));
        $t->same('three', $local->getKey(Key::fromInteger(3), $oldThreeNodeId));

        $remote = $local->checkout($local->headNodeId());
        $newTwoNodeId = 0;
        $deletedThreeNodeId = 0;
        $newFourNodeId = 0;
        $remote->change()
            ->putKey(Key::fromInteger(2), 'two updated', $newTwoNodeId)
            ->deleteKey(Key::fromInteger(3), $deletedThreeNodeId)
            ->putKey(Key::fromInteger(4), 'four', $newFourNodeId)
            ->apply();

        $scanDiffs = [];
        $diffs = $local->diffTo($remote, static function (DiffEntry $diff) use (&$scanDiffs): void {
            $scanDiffs[] = $diff;
        });
        $diffsByKey = quadrableTrackedDiffsByInteger($diffs);

        $t->same(quadrableTrackedDiffSignature($diffs), quadrableTrackedDiffSignature($scanDiffs));
        $t->same(DiffEntry::CHANGED, $diffsByKey[2]->type);
        $t->same(DiffEntry::DELETED, $diffsByKey[3]->type);
        $t->same(DiffEntry::ADDED, $diffsByKey[4]->type);
        $t->same($newTwoNodeId, $diffsByKey[2]->nodeId);
        $t->same($oldThreeNodeId, $diffsByKey[3]->nodeId);
        $t->same($deletedThreeNodeId, $diffsByKey[3]->nodeId);
        $t->same($newFourNodeId, $diffsByKey[4]->nodeId);
        $t->true($oldTwoNodeId !== $newTwoNodeId);
    },
    'tracked upstream-shaped sync diff reconstructs randomized forks with node id parity' => static function (TestRunner $t): void {
        $state = 0;

        for ($trial = 0; $trial < 64; $trial++) {
            $seed = new TrackedSparseTree();
            $changes = $seed->change();
            $numElems = 12 + quadrableTrackedSyncNext($state, 140);
            $maxElem = 260;

            for ($i = 0; $i < $numElems; $i++) {
                $n = quadrableTrackedSyncNext($state, $maxElem);
                $changes->putKey(
                    Key::fromInteger($n),
                    quadrableTrackedSyncValue($n, quadrableTrackedSyncNext($state, 25), quadrableTrackedSyncNext($state, 45))
                );
            }
            $changes->apply();

            $local = $seed->checkout($seed->headNodeId());
            $remote = $seed->checkout($seed->headNodeId());
            $remoteChanges = $remote->change();
            $alterations = 4 + quadrableTrackedSyncNext($state, 50);

            for ($i = 0; $i < $alterations; $i++) {
                $n = quadrableTrackedSyncNext($state, $maxElem);
                if (quadrableTrackedSyncNext($state, 2) === 0) {
                    $remoteChanges->putKey(
                        Key::fromInteger($n),
                        quadrableTrackedSyncValue($n, 1000 + $trial, quadrableTrackedSyncNext($state, 50))
                    );
                } else {
                    $remoteChanges->deleteKey(Key::fromInteger($n));
                }
            }
            $remoteChanges->apply();

            $scanDiffs = [];
            $finalDiffs = $local->diffTo($remote, static function (DiffEntry $diff) use (&$scanDiffs): void {
                $scanDiffs[] = $diff;
            });

            $reconstructed = $local->checkout($local->headNodeId());
            $reconstructed->applyDiffs($finalDiffs);

            $t->same($remote->rootHash(), $reconstructed->rootHash(), 'reconstructed root mismatch on trial ' . $trial);
            $t->same(
                quadrableTrackedDiffSignature($finalDiffs),
                quadrableTrackedDiffSignature($scanDiffs),
                'scan/final node id mismatch on trial ' . $trial
            );
        }
    },
    'wordpress tracked snapshot can reuse leaves during a compact rebuild' => static function (TestRunner $t): void {
        $records = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/wordpress-ordered-snapshot.json'), true, flags: JSON_THROW_ON_ERROR);

        $snapshot = new TrackedSparseTree();
        $changes = $snapshot->change();
        foreach ($records as $record) {
            $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
        }
        $changes->apply();

        $trustedRoot = $snapshot->rootHash();
        $siteUrlNodeId = 0;
        $t->same('wp_options:siteurl=https://example.test', $snapshot->getKey(Key::fromInteger(1), $siteUrlNodeId));

        $rebuilt = $snapshot->checkoutEmpty();
        $reuseChanges = $rebuilt->change();
        $reusedSiteUrlNodeId = 0;

        for ($iterator = $snapshot->iterate(Key::null()); !$iterator->atEnd(); $iterator->next()) {
            $entry = $iterator->get();
            $t->true($entry !== null);
            if ($entry->key()->toInteger() === 1) {
                $reuseChanges->putReuse($entry->key(), $entry->nodeId, $reusedSiteUrlNodeId);
            } else {
                $unusedNodeId = 0;
                $reuseChanges->putReuse($entry->key(), $entry->nodeId, $unusedNodeId);
            }
        }

        $reuseChanges->apply();

        $t->same($trustedRoot, $rebuilt->rootHash());
        $t->same($siteUrlNodeId, $reusedSiteUrlNodeId);
        $t->same('wp_options:siteurl=https://example.test', $rebuilt->getKey(Key::fromInteger(1)));
    },
    'wordpress tracked snapshot forks can restore old and updated branch heads' => static function (TestRunner $t): void {
        $records = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/wordpress-ordered-snapshot.json'), true, flags: JSON_THROW_ON_ERROR);

        $snapshot = new TrackedSparseTree();
        $changes = $snapshot->change();
        foreach ($records as $record) {
            $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
        }
        $changes->apply();

        $oldHeadNodeId = $snapshot->headNodeId();
        $oldRoot = $snapshot->rootHash();
        $postKey = Key::fromInteger(3);

        $updated = $snapshot->checkout($oldHeadNodeId);
        $updated->putKey($postKey, 'wp_posts:1=Forked authenticated update');

        $restoredOld = $updated->checkout($oldHeadNodeId);
        $restoredNew = $updated->checkout($updated->headNodeId());

        $t->same($oldRoot, $restoredOld->rootHash());
        $t->same('wp_posts:1=Hello world', $restoredOld->getKey($postKey));
        $t->same('wp_posts:1=Forked authenticated update', $restoredNew->getKey($postKey));
        $t->true($restoredNew->rootHash() !== $oldRoot);
    },
    'wordpress tracked diff scan and final diff report identical node ids' => static function (TestRunner $t): void {
        $local = quadrableTrackedWordPressSnapshot();
        $remote = $local->checkout($local->headNodeId());

        $oldMetaNodeId = 0;
        $t->same('wp_postmeta:1:_thumbnail_id=42', $local->getKey(Key::fromInteger(4), $oldMetaNodeId));

        $updatedPostNodeId = 0;
        $deletedMetaNodeId = 0;
        $addedPostNodeId = 0;
        $remote->change()
            ->putKey(Key::fromInteger(3), 'wp_posts:1=Tracked node-id import')
            ->deleteKey(Key::fromInteger(4), $deletedMetaNodeId)
            ->putKey(Key::fromInteger(6), 'wp_posts:3=Node id scan diff', $addedPostNodeId)
            ->apply();

        $t->same('wp_posts:1=Tracked node-id import', $remote->getKey(Key::fromInteger(3), $updatedPostNodeId));

        $scanDiffs = [];
        $finalDiffs = $local->diffTo($remote, static function (DiffEntry $diff) use (&$scanDiffs): void {
            $scanDiffs[] = $diff;
        });
        $diffsByKey = quadrableTrackedDiffsByInteger($finalDiffs);

        $t->same(quadrableTrackedDiffSignature($finalDiffs), quadrableTrackedDiffSignature($scanDiffs));
        $t->same($updatedPostNodeId, $diffsByKey[3]->nodeId);
        $t->same($oldMetaNodeId, $diffsByKey[4]->nodeId);
        $t->same($deletedMetaNodeId, $diffsByKey[4]->nodeId);
        $t->same($addedPostNodeId, $diffsByKey[6]->nodeId);
        $t->same(DiffEntry::DELETED, $diffsByKey[4]->type);
        $t->true($remote->rootHash() !== $local->rootHash());
    },
    'wordpress published tracked snapshot reloads from persisted node store JSON' => static function (TestRunner $t): void {
        $records = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/wordpress-ordered-snapshot.json'), true, flags: JSON_THROW_ON_ERROR);
        $store = new TrackedNodeStore();
        $published = (new TrackedSparseTree($store))->checkout('wp-published');
        $changes = $published->change();

        foreach ($records as $record) {
            $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
        }
        $changes->apply();

        $siteUrlNodeId = 0;
        $t->same('wp_options:siteurl=https://example.test', $published->getKey(Key::fromInteger(1), $siteUrlNodeId));

        $encodedSnapshot = json_encode($store->exportSnapshot(), JSON_THROW_ON_ERROR);
        $restoredStore = TrackedNodeStore::fromSnapshot(json_decode($encodedSnapshot, true, flags: JSON_THROW_ON_ERROR));
        $restored = (new TrackedSparseTree($restoredStore))->checkout('wp-published');
        $restoredSiteUrlNodeId = 0;

        $t->same($published->headNodeId(), $restored->headNodeId());
        $t->same($published->rootHash(), $restored->rootHash());
        $t->same('wp_options:siteurl=https://example.test', $restored->getKey(Key::fromInteger(1), $restoredSiteUrlNodeId));
        $t->same($siteUrlNodeId, $restoredSiteUrlNodeId);
        $t->same('wp_posts:1=Hello world', $restored->getKey(Key::fromInteger(3)));

        $preview = $restored->withMemStoreWrites()->fork();
        $preview->putKey(Key::fromInteger(3), 'wp_posts:1=Reloaded preview edit');

        $t->true($preview->headNodeId() >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID);
        $t->same('wp_posts:1=Hello world', $restored->getKey(Key::fromInteger(3)));
        $t->same('wp_posts:1=Reloaded preview edit', $preview->getKey(Key::fromInteger(3)));
    },
];

function quadrableTrackedWordPressSnapshot(): TrackedSparseTree
{
    $records = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/wordpress-ordered-snapshot.json'), true, flags: JSON_THROW_ON_ERROR);

    $snapshot = new TrackedSparseTree();
    $changes = $snapshot->change();
    foreach ($records as $record) {
        $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
    }
    $changes->apply();

    return $snapshot;
}

/**
 * @param list<DiffEntry> $diffs
 *
 * @return array<int, DiffEntry>
 */
function quadrableTrackedDiffsByInteger(array $diffs): array
{
    $byKey = [];
    foreach ($diffs as $diff) {
        $byKey[$diff->key()->toInteger()] = $diff;
    }
    ksort($byKey, SORT_NUMERIC);

    return $byKey;
}

/**
 * @param list<DiffEntry> $diffs
 *
 * @return list<string>
 */
function quadrableTrackedDiffSignature(array $diffs): array
{
    $signature = array_map(
        static fn (DiffEntry $diff): string => $diff->type . ':' . $diff->key()->toInteger() . ':' . $diff->value . ':' . $diff->nodeId,
        $diffs
    );
    sort($signature, SORT_STRING);

    return $signature;
}

function quadrableTrackedSyncNext(int &$state, int $mod): int
{
    $state = ($state * 1103515245 + 12345) & 0x7fffffff;

    return $state % $mod;
}

function quadrableTrackedSyncValue(int $number, int $variant, int $extraLength): string
{
    return $number . ':' . $variant . ':' . str_repeat(chr(65 + (($number + $variant) % 26)), $extraLength);
}
