<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
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
];
