<?php

declare(strict_types=1);

use PortLibs\Quadrable\HashTree;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\QuadbStore;
use PortLibs\Quadrable\SparseTree;

return [
    'native quadb store reopens the current named head and integer import export lines' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $t->same('master', $repo->currentHeadName());
            $t->same(2, $repo->importIntegerLines("1,wp_options:siteurl=https://example.test\n3,wp_posts:1=Hello world\n"));

            $master = $repo->tree();
            $masterRoot = $master->rootHash();
            $masterHeadNodeId = $master->headNodeId();

            $reopened = QuadbStore::open($dir);
            $t->same('master', $reopened->currentHeadName());
            $t->same($masterRoot, $reopened->tree()->rootHash());
            $t->same($masterHeadNodeId, $reopened->tree()->headNodeId());
            $t->same([
                '1,wp_options:siteurl=https://example.test',
                '3,wp_posts:1=Hello world',
            ], quadrableQuadbSortedLines($reopened->exportIntegerLines()));

            $preview = $reopened->checkout('wp-preview');
            $t->same(HashTree::EMPTY_HASH, $preview->rootHash());
            $t->same(2, $reopened->importIntegerLines("3,wp_posts:1=Preview edit\n4,wp_postmeta:1:_thumbnail_id=42\n"));

            $again = QuadbStore::open($dir);
            $t->same('wp-preview', $again->currentHeadName());
            $t->same('wp_posts:1=Preview edit', $again->tree()->getKey(Key::fromInteger(3)));
            $t->same('wp_postmeta:1:_thumbnail_id=42', $again->tree()->getKey(Key::fromInteger(4)));

            $restoredMaster = $again->checkout('master');
            $t->same($masterRoot, $restoredMaster->rootHash());
            $t->same($masterHeadNodeId, $restoredMaster->headNodeId());
            $t->same('wp_posts:1=Hello world', $restoredMaster->getKey(Key::fromInteger(3)));
            $t->same(null, $restoredMaster->getKey(Key::fromInteger(4)));

            $t->throws(RuntimeException::class, static fn () => $again->importIntegerLines("missing separator\n"));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store forks named heads across reopen without copying unchanged leaves' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importIntegerLines("1,wp_options:siteurl=https://example.test\n2,wp_options:home=https://example.test\n3,wp_posts:1=Hello world\n");

            $master = $repo->tree();
            $masterSiteUrlNodeId = 0;
            $t->same('wp_options:siteurl=https://example.test', $master->getKey(Key::fromInteger(1), $masterSiteUrlNodeId));
            $masterHeadNodeId = $master->headNodeId();
            $masterRoot = $master->rootHash();

            $snapshot = $repo->fork('wp-snapshot');
            $t->same($masterHeadNodeId, $snapshot->headNodeId());
            $t->same($masterRoot, $snapshot->rootHash());

            $repo->importIntegerLines("3,wp_posts:1=Published update\n5,wp_posts:2=New page\n");
            $updatedSnapshot = $repo->tree();
            $snapshotSiteUrlNodeId = 0;
            $t->same('wp_options:siteurl=https://example.test', $updatedSnapshot->getKey(Key::fromInteger(1), $snapshotSiteUrlNodeId));
            $t->same($masterSiteUrlNodeId, $snapshotSiteUrlNodeId);

            $reopened = QuadbStore::open($dir);
            $t->same('wp-snapshot', $reopened->currentHeadName());
            $t->same('wp_posts:1=Published update', $reopened->tree()->getKey(Key::fromInteger(3)));
            $t->same('wp_posts:2=New page', $reopened->tree()->getKey(Key::fromInteger(5)));

            $restoredMaster = $reopened->checkout('master');
            $t->same($masterRoot, $restoredMaster->rootHash());
            $t->same('wp_posts:1=Hello world', $restoredMaster->getKey(Key::fromInteger(3)));
            $t->same(null, $restoredMaster->getKey(Key::fromInteger(5)));

            $releaseCopy = $reopened->fork('wp-release-copy', 'master');
            $t->same($masterHeadNodeId, $releaseCopy->headNodeId());
            $t->same($masterRoot, $releaseCopy->rootHash());
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store persists detached fork state across reopen' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importIntegerLines("1,wp_options:siteurl=https://example.test\n3,wp_posts:1=Hello world\n");
            $masterRoot = $repo->tree()->rootHash();

            $detached = $repo->fork();
            $t->true($repo->isDetachedHead());
            $t->same(null, $repo->currentHeadName());
            $t->same($masterRoot, $detached->rootHash());

            $detached->putKey(Key::fromInteger(3), 'wp_posts:1=Detached preview edit');
            $detached->putKey(Key::fromInteger(4), 'wp_postmeta:1:_thumbnail_id=42');
            $repo->save($detached);

            $reopened = QuadbStore::open($dir);
            $t->true($reopened->isDetachedHead());
            $t->same(null, $reopened->currentHeadName());
            $t->same('wp_posts:1=Detached preview edit', $reopened->tree()->getKey(Key::fromInteger(3)));
            $t->same('wp_postmeta:1:_thumbnail_id=42', $reopened->tree()->getKey(Key::fromInteger(4)));

            $master = $reopened->checkout('master');
            $t->same($masterRoot, $master->rootHash());
            $t->same('wp_posts:1=Hello world', $master->getKey(Key::fromInteger(3)));
            $t->same(null, $master->getKey(Key::fromInteger(4)));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store emits and applies tracked string-key patch lines' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $t->same(3, $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Hello world\n",
                '|'
            ));

            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-preview');
            $repo->put('wp_posts:1', 'Preview edit');
            $repo->put('wp_posts:2', 'New page');
            $repo->delete('wp_options:home');

            $previewRoot = $repo->tree()->rootHash();
            $patch = $repo->diffLines('master', '|');
            $t->same([
                '+wp_posts:1|Preview edit',
                '+wp_posts:2|New page',
                '-wp_options:home|https://example.test',
                '-wp_posts:1|Hello world',
            ], quadrableQuadbSortedLines($patch));

            $reopened = QuadbStore::open($dir);
            $t->same($patch, $reopened->diffLines('master', '|'));

            $replica = $reopened->fork('wp-replica', 'master');
            $t->same($masterRoot, $replica->rootHash());
            $t->same($masterHeadNodeId, $replica->headNodeId());

            $t->same(4, $reopened->applyPatchLines("# preview patch\n" . $patch, '|'));
            $t->same($previewRoot, $reopened->tree()->rootHash());
            $t->same('Preview edit', $reopened->get('wp_posts:1'));
            $t->same('New page', $reopened->get('wp_posts:2'));
            $t->throws(RuntimeException::class, static fn () => $reopened->get('wp_options:home'));

            $t->same([
                'wp_options:siteurl|https://example.test',
                'wp_posts:1|Preview edit',
                'wp_posts:2|New page',
            ], quadrableQuadbSortedLines($reopened->exportLines('|')));

            $t->throws(RuntimeException::class, static fn () => $reopened->applyPatchLines("\n", '|'));
            $t->throws(RuntimeException::class, static fn () => $reopened->applyPatchLines("~wp_posts:1|bad\n", '|'));
            $t->throws(RuntimeException::class, static fn () => $reopened->applyPatchLines("+wp_posts:1 missing separator\n", '|'));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store exports hex full-key proofs like quadb exportProof' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $root = $repo->tree()->rootHash();
            $proofHex = $repo->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:1',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);

            $proofBytes = quadrableQuadbDecodeHexProof($proofHex);
            $proof = Proof::decode($proofBytes);
            $partial = SparseTree::importProof($proof, $root);

            $t->same(Proof::ENCODING_FULL_KEYS, ord($proofBytes[0]));
            $t->true(str_starts_with($proofHex, '0x'));
            $t->true(str_ends_with($proofHex, "\n"));
            $t->same($root, $partial->rootHash());
            $t->same('https://example.test', $partial->get('wp_options:siteurl'));
            $t->same('Published post', $partial->get('wp_posts:1'));
            $t->same(null, $partial->get('wp_posts:404'));
            $t->throws(RuntimeException::class, static fn () => $partial->get('wp_options:home'));

            $entries = [];
            foreach ($partial->orderedEntries() as $entry) {
                $entries[$entry->stringKey() ?? $entry->keyHex()] = $entry->value();
            }
            ksort($entries, SORT_STRING);

            $t->same([
                'wp_options:siteurl' => 'https://example.test',
                'wp_posts:1' => 'Published post',
            ], $entries);
            $t->same($proofHex, QuadbStore::open($dir)->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:1',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store exports raw integer proofs like quadb exportProof int' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importIntegerLines(
                "1,wp_options:siteurl=https://example.test\n"
                . "2,wp_options:home=https://example.test\n"
                . "4,wp_posts:1=Published post\n"
            );

            $root = $repo->tree()->rootHash();
            $proofHex = $repo->exportIntegerProofHex([2, 4, 99]);
            $proof = Proof::decode(quadrableQuadbDecodeHexProof($proofHex));
            $partial = SparseTree::importProof($proof, $root);

            $t->same($root, $partial->rootHash());
            $t->same('wp_options:home=https://example.test', $partial->getKey(Key::fromInteger(2)));
            $t->same('wp_posts:1=Published post', $partial->getKey(Key::fromInteger(4)));
            $t->same(null, $partial->getKey(Key::fromInteger(99)));
            $t->throws(RuntimeException::class, static fn () => $partial->getKey(Key::fromInteger(1)));
            $t->throws(RuntimeException::class, static fn () => $repo->exportIntegerProofHex([2], Proof::ENCODING_FULL_KEYS));
            $t->throws(InvalidArgumentException::class, static fn () => $repo->exportIntegerProof([2, '3']));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store dumps proofs and reports unauthenticated proof imports like quadb' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();
        $trustedDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $keys = [
                'wp_options:siteurl',
                'wp_posts:404',
            ];
            $dump = $source->exportProofDumpText($keys);
            $fullKeyProofHex = $source->exportProofHex($keys, Proof::ENCODING_FULL_KEYS);

            $t->same($source->exportProof($keys)->dumpText(), $dump);
            $t->contains('ITEMS (', $dump);
            $t->contains('  ITEM 0: 0x', $dump);
            $t->contains("    Leaf  depth=", $dump);
            $t->contains("    Key: wp_options:siteurl\n", $dump);
            $t->contains("    Val: https://example.test\n", $dump);
            $t->contains("    WitnessLeaf  depth=", $dump);
            $t->contains('CMDS (', $dump);

            $target = QuadbStore::init($targetDir);
            $t->same($source->exportProof($keys)->dumpText(), $target->importProofHexDumpText($fullKeyProofHex));
            $t->same(HashTree::EMPTY_HASH, $target->tree()->rootHash());

            $t->same(
                "Imported UNAUTHENTICATED proof. Root: 0x{$trustedRoot}\n",
                $target->importProofHexOutputText($fullKeyProofHex)
            );
            $t->same($trustedRoot, $target->status()['rootHash']);
            $t->same('https://example.test', $target->get('wp_options:siteurl'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_options:home'));
            $t->same('https://example.test', QuadbStore::open($targetDir)->get('wp_options:siteurl'));

            $trusted = QuadbStore::init($trustedDir);
            $trusted->checkout('wp-trusted-partial');
            $t->same('', $trusted->importProofHexOutputText($fullKeyProofHex, '0x' . $trustedRoot));
            $t->same($trustedRoot, $trusted->status()['rootHash']);
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
            quadrableQuadbRemoveDir($trustedDir);
        }
    },
    'native quadb store updates raw integer proof-backed heads' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importIntegerLines(
                "1,wp_options:siteurl=https://example.test\n"
                . "2,wp_options:home=https://example.test\n"
                . "3,wp_posts:1=Published post\n"
            );

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportIntegerProofHex([2, 4]);

            $source->importIntegerLines(
                "2,wp_options:home=https://preview.example.test\n"
                . "4,wp_postmeta:1:_thumbnail_id=42\n"
            );
            $updatedRoot = $source->tree()->rootHash();
            $updatedSiteProofHex = $source->exportIntegerProofHex([1]);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-integer-delegated');
            $target->importProofHex($proofHex, $trustedRoot);
            $t->same(2, $target->importIntegerLines(
                "2,wp_options:home=https://preview.example.test\n"
                . "4,wp_postmeta:1:_thumbnail_id=42\n"
            ));
            $t->same($updatedRoot, $target->status()['rootHash']);
            $t->same('wp_options:home=https://preview.example.test', $target->getInteger(2));
            $t->same('wp_postmeta:1:_thumbnail_id=42', $target->getInteger(4));
            $t->throws(RuntimeException::class, static fn () => $target->getInteger(1));

            $reopened = QuadbStore::open($targetDir);
            $t->same($updatedRoot, $reopened->status()['rootHash']);
            $t->same('wp_postmeta:1:_thumbnail_id=42', $reopened->getKey(Key::fromInteger(4)));
            $t->same($updatedRoot, $reopened->mergeProofHex($updatedSiteProofHex));
            $t->same('wp_options:siteurl=https://example.test', $reopened->getInteger(1));

            $source->deleteInteger(4);
            $reopened->deleteInteger(4);
            $t->same($source->status()['rootHash'], $reopened->status()['rootHash']);
            $t->throws(RuntimeException::class, static fn () => $reopened->getInteger(4));

            $delegatedProofHex = $reopened->exportIntegerProofHex([1, 2, 4]);
            $delegated = SparseTree::importProof(
                Proof::decode(quadrableQuadbDecodeHexProof($delegatedProofHex)),
                $reopened->status()['rootHash']
            );
            $t->same('wp_options:siteurl=https://example.test', $delegated->getKey(Key::fromInteger(1)));
            $t->same('wp_options:home=https://preview.example.test', $delegated->getKey(Key::fromInteger(2)));
            $t->same(null, $delegated->getKey(Key::fromInteger(4)));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store imports and merges proof-backed heads across reopen' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $optionProofHex = $source->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);
            $postProofHex = $source->exportProofHex([
                'wp_posts:1',
            ], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated');
            $t->same($trustedRoot, $target->importProofHex($optionProofHex, '0x' . $trustedRoot));
            $t->same('https://example.test', $target->get('wp_options:siteurl'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_posts:1'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_posts:404'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_options:home'));
            $t->throws(RuntimeException::class, static fn () => $target->exportLines('|'));

            $status = $target->status();
            $t->same(false, $status['detached']);
            $t->same('wp-delegated', $status['head']);
            $t->same($trustedRoot, $status['rootHash']);
            $t->true($status['headNodeId'] >= 576460752303423488);
            $t->contains("=> wp-delegated : 0x{$trustedRoot} (", $target->headText());

            $reopened = QuadbStore::open($targetDir);
            $t->same('https://example.test', $reopened->get('wp_options:siteurl'));
            $t->same($trustedRoot, $reopened->mergeProofHex($postProofHex));
            $t->same('Published post', $reopened->get('wp_posts:1'));
            $t->same($trustedRoot, QuadbStore::open($targetDir)->status()['rootHash']);

            $delegatedProofHex = $reopened->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:1',
            ], Proof::ENCODING_FULL_KEYS);
            $delegated = SparseTree::importProof(
                Proof::decode(quadrableQuadbDecodeHexProof($delegatedProofHex)),
                $trustedRoot
            );

            $t->same('https://example.test', $delegated->get('wp_options:siteurl'));
            $t->same('Published post', $delegated->get('wp_posts:1'));

            $source->put('wp_posts:1', 'Changed after proof');
            $wrongRootProofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);
            $t->throws(RuntimeException::class, static fn () => $reopened->mergeProofHex($wrongRootProofHex));
            $t->same('Published post', QuadbStore::open($targetDir)->get('wp_posts:1'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store persists proof-backed partial-head writes across reopen' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);
            $staleHomeProofHex = $source->exportProofHex(['wp_options:home'], Proof::ENCODING_FULL_KEYS);

            $source->put('wp_options:siteurl', 'https://preview.example.test');
            $updatedRoot = $source->tree()->rootHash();
            $updatedHomeProofHex = $source->exportProofHex(['wp_options:home'], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated');
            $target->importProofHex($proofHex, $trustedRoot);
            $target->put('wp_options:siteurl', 'https://preview.example.test');

            $t->same($updatedRoot, $target->status()['rootHash']);
            $t->same('https://preview.example.test', $target->get('wp_options:siteurl'));
            $t->throws(RuntimeException::class, static fn () => $target->put('wp_posts:1', 'Unproved edit'));
            $t->same($updatedRoot, $target->status()['rootHash']);
            $t->throws(RuntimeException::class, static fn () => $target->mergeProofHex($staleHomeProofHex));
            $t->same($updatedRoot, $target->mergeProofHex($updatedHomeProofHex));
            $t->same('https://example.test', $target->get('wp_options:home'));

            $reopened = QuadbStore::open($targetDir);
            $t->same($updatedRoot, $reopened->status()['rootHash']);
            $t->same('https://preview.example.test', $reopened->get('wp_options:siteurl'));
            $t->same('https://example.test', $reopened->get('wp_options:home'));

            $delegatedProofHex = $reopened->exportProofHex([
                'wp_options:siteurl',
                'wp_options:home',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);
            $delegated = SparseTree::importProof(
                Proof::decode(quadrableQuadbDecodeHexProof($delegatedProofHex)),
                $updatedRoot
            );
            $t->same('https://preview.example.test', $delegated->get('wp_options:siteurl'));
            $t->same('https://example.test', $delegated->get('wp_options:home'));
            $t->same(null, $delegated->get('wp_posts:404'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store merges updated-root proofs after persisted proof-backed writes' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $siteUrlProofHex = $source->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
            $stalePostProofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

            $source->put('wp_options:siteurl', 'https://preview.example.test');
            $updatedRoot = $source->tree()->rootHash();
            $updatedPostProofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-edit');
            $target->importProofHex($siteUrlProofHex, $trustedRoot);
            $target->put('wp_options:siteurl', 'https://preview.example.test');

            $reopened = QuadbStore::open($targetDir);
            $t->same($updatedRoot, $reopened->status()['rootHash']);
            $t->throws(RuntimeException::class, static fn () => $reopened->get('wp_posts:1'));
            $t->throws(RuntimeException::class, static fn () => $reopened->mergeProofHex($stalePostProofHex));
            $t->same($updatedRoot, $reopened->status()['rootHash']);

            $t->same($updatedRoot, $reopened->mergeProofHex($updatedPostProofHex));
            $t->same('Published post', $reopened->get('wp_posts:1'));
            $t->same('Published post', QuadbStore::open($targetDir)->get('wp_posts:1'));

            $delegatedProofHex = $reopened->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:1',
            ], Proof::ENCODING_FULL_KEYS);
            $delegated = SparseTree::importProof(
                Proof::decode(quadrableQuadbDecodeHexProof($delegatedProofHex)),
                $updatedRoot
            );

            $t->same('https://preview.example.test', $delegated->get('wp_options:siteurl'));
            $t->same('Published post', $delegated->get('wp_posts:1'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store deletes and forks proof-backed partial heads' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "731156037546|one\n"
                . "925458752084|two\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex([
                '731156037546',
                '925458752084',
            ], Proof::ENCODING_FULL_KEYS);

            $source->delete('731156037546');
            $deleteRoot = $source->tree()->rootHash();

            $target = QuadbStore::init($targetDir);
            $target->checkout('upstream-partial');
            $target->importProofHex($proofHex, $trustedRoot);

            $forked = $target->fork('wp-preview-partial');
            $t->true($forked instanceof SparseTree);
            $target->put('925458752084', 'two-preview');
            $previewRoot = $target->status()['rootHash'];

            $original = $target->checkout('upstream-partial');
            $t->true($original instanceof SparseTree);
            $t->same($trustedRoot, $target->status()['rootHash']);
            $t->same('two', $target->get('925458752084'));

            $target->delete('731156037546');
            $t->same($deleteRoot, $target->status()['rootHash']);
            $t->throws(RuntimeException::class, static fn () => $target->get('731156037546'));
            $t->same('two', $target->get('925458752084'));

            $reopened = QuadbStore::open($targetDir);
            $preview = $reopened->checkout('wp-preview-partial');
            $t->true($preview instanceof SparseTree);
            $t->same($previewRoot, $preview->rootHash());
            $t->same('two-preview', $reopened->get('925458752084'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store rejects proof imports unless the current head is empty' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines("wp_posts:1|Published post\n", '|');
            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->importLines("wp_options:siteurl|https://example.test\n", '|');
            $t->throws(RuntimeException::class, static fn () => $target->importProofHex($proofHex, $trustedRoot));

            $target->checkout('wp-partial');
            $t->throws(RuntimeException::class, static fn () => $target->importProofHex($proofHex, str_repeat('f', 64)));
            $t->same(HashTree::EMPTY_HASH, $target->status()['rootHash']);
            $t->throws(InvalidArgumentException::class, static fn () => $target->importProofHex('0xabc', $trustedRoot));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store formats root status and sorted head output' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $t->same('0x' . HashTree::EMPTY_HASH . "\n", $repo->rootText());
            $t->same("Head: master\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", $repo->statusText());
            $t->same('', $repo->headText());

            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-release');
            $repo->put('wp_posts:2', 'Released page');
            $releaseRoot = $repo->tree()->rootHash();
            $releaseHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-preview', 'master');
            $repo->put('wp_posts:1', 'Preview edit');
            $previewRoot = $repo->tree()->rootHash();
            $previewHeadNodeId = $repo->tree()->headNodeId();

            $t->same('0x' . $previewRoot . "\n", $repo->rootText());
            $t->same("Head: wp-preview\nRoot: 0x{$previewRoot} ({$previewHeadNodeId})\n", $repo->statusText());
            $t->same([
                "=> wp-preview : 0x{$previewRoot} ({$previewHeadNodeId})",
                "   wp-release : 0x{$releaseRoot} ({$releaseHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $reopened = QuadbStore::open($dir);
            $t->same($repo->headText(), $reopened->headText());
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store formats stats output like quadb stats' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($sourceDir);
            $t->same([
                'numNodes:        0',
                'numLeafNodes:    0',
                'numBranchNodes:  0',
                'numWitnessNodes: 0',
                'maxDepth:        0',
                'numBytes:        0',
            ], quadrableQuadbOutputLines($repo->statsText()));

            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $stats = $repo->stats();
            $t->same(3, $stats['numLeafNodes']);
            $t->same(0, $stats['numWitnessNodes']);
            $t->same($stats['numLeafNodes'] + $stats['numBranchNodes'], $stats['numNodes']);
            $t->same(
                (72 * 3)
                    + strlen('https://example.test')
                    + strlen('https://example.test')
                    + strlen('Published post')
                    + (48 * $stats['numBranchNodes']),
                $stats['numBytes']
            );
            $t->same(quadrableQuadbStatsLines($stats), quadrableQuadbOutputLines($repo->statsText()));
            $t->same($repo->statsText(), QuadbStore::open($sourceDir)->statsText());

            $trustedRoot = $repo->tree()->rootHash();
            $proofHex = $repo->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-stats');
            $target->importProofHex($proofHex, $trustedRoot);

            $partialStats = $target->stats();
            $t->same(1, $partialStats['numLeafNodes']);
            $t->true($partialStats['numBranchNodes'] > 0);
            $t->true($partialStats['numWitnessNodes'] > 0);
            $t->same(
                $partialStats['numLeafNodes'] + $partialStats['numBranchNodes'] + $partialStats['numWitnessNodes'],
                $partialStats['numNodes']
            );
            $t->same(quadrableQuadbStatsLines($partialStats), quadrableQuadbOutputLines($target->statsText()));
            $t->same($target->statsText(), QuadbStore::open($targetDir)->statsText());
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store dumps full and proof-backed trees like quadb dumpTree' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();
        $integerDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $t->same(
                "-----------------\n"
                . '0x00000000... (0) empty' . "\n"
                . "-----------------\n",
                $source->dumpTreeText()
            );

            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $t->same(
                "-----------------\n"
                . "0x34dfb816... (288230376151711745) branch:\n"
                . "  0x3025435e... (288230376151711744) branch:\n"
                . "    0xa4da3a8b... (1) leaf: wp_posts:1 = Published post\n"
                . "    0xa1e166a4... (2) leaf: wp_options:home = https://example.test\n"
                . "  0x2c115121... (3) leaf: wp_options:siteurl = https://example.test\n"
                . "-----------------\n",
                $source->dumpTreeText()
            );
            $t->same($source->dumpTreeText(), QuadbStore::open($sourceDir)->dumpTreeText());

            $trustedRoot = $source->tree()->rootHash();
            $proofHex = $source->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-dump');
            $target->importProofHex($proofHex, $trustedRoot);
            $partialDump = $target->dumpTreeText();

            $t->contains("0x34dfb816... (576460752303423492) branch:\n", $partialDump);
            $t->contains("witness\n", $partialDump);
            $t->contains(
                "witness leaf: 0x7b52fb0f1f4a77fb1dc7cb8188132a04f7b57e0b54f41cbdd20df89c098ef985 hash(val) = 0x0a62a7127118b2347eea44eb95cd06211ded305b934d459bf64f3ac9db5038d1\n",
                $partialDump
            );
            $t->contains("leaf: wp_options:siteurl = https://example.test\n", $partialDump);
            $t->same($partialDump, QuadbStore::open($targetDir)->dumpTreeText());

            $integer = QuadbStore::init($integerDir);
            $integer->importIntegerLines(
                "1,wp_options:siteurl=https://example.test\n"
                . "3,wp_posts:1=Hello\n"
            );
            $integerDump = $integer->dumpTreeText();

            $t->contains('leaf: H(?)=0x020000000000... = wp_options:siteurl=https://example.test', $integerDump);
            $t->contains('leaf: H(?)=0x050000000000... = wp_posts:1=Hello', $integerDump);
            $t->contains("0x00000000... (0) empty\n", $integerDump);
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
            quadrableQuadbRemoveDir($integerDir);
        }
    },
    'native quadb store removes named and current heads like quadb head rm' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-preview');
            $repo->put('wp_posts:1', 'Preview edit');
            $previewRoot = $repo->tree()->rootHash();
            $previewHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('wp-throwaway', 'master');
            $repo->put('wp_posts:2', 'Throwaway preview');
            $repo->removeHead('wp-throwaway');
            $repo->checkout('wp-preview');

            $t->same([
                "=> wp-preview : 0x{$previewRoot} ({$previewHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $repo->removeHead();
            $t->same('wp-preview', $repo->currentHeadName());
            $t->same(HashTree::EMPTY_HASH, $repo->tree()->rootHash());
            $t->same("Head: wp-preview\nRoot: 0x" . HashTree::EMPTY_HASH . " (0)\n", $repo->statusText());
            $t->same([
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $repo->put('wp_posts:3', 'Recreated preview head');
            $recreatedRoot = $repo->tree()->rootHash();
            $recreatedHeadNodeId = $repo->tree()->headNodeId();

            $t->same([
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
                "=> wp-preview : 0x{$recreatedRoot} ({$recreatedHeadNodeId})",
            ], quadrableQuadbOutputLines(QuadbStore::open($dir)->headText()));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store head rm resets detached head to an empty tree' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines("wp_posts:1|Published post\n", '|');
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $detached = $repo->fork();
            $t->true($repo->isDetachedHead());
            $t->same($masterRoot, $detached->rootHash());
            $t->same([
                "D> [detached] : 0x{$masterRoot} ({$masterHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($repo->headText()));

            $repo->removeHead();
            $t->true($repo->isDetachedHead());
            $t->same(HashTree::EMPTY_HASH, $repo->tree()->rootHash());
            $t->same([
                'D> [detached] : 0x' . HashTree::EMPTY_HASH . ' (0)',
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines(QuadbStore::open($dir)->headText()));
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store garbage collects discarded full heads like quadb gc' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();
        $detachedDir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();

            $repo->fork('preview-discard');
            $repo->put('wp_posts:1', 'Discarded preview edit');

            $repo->fork('preview-approved', 'master');
            $repo->put('wp_posts:2', 'Approved page');
            $approvedRoot = $repo->tree()->rootHash();

            $repo->removeHead('preview-discard');
            $storedBeforeGc = quadrableQuadbStoredNodeCount($repo);
            $gc = quadrableQuadbParseGcText($repo->garbageCollectText());
            $storedAfterGc = quadrableQuadbStoredNodeCount($repo);

            $t->same($storedBeforeGc, $gc['total']);
            $t->true($gc['garbage'] > 0);
            $t->same($storedBeforeGc - $gc['garbage'], $storedAfterGc);
            $t->same('preview-approved', $repo->currentHeadName());
            $t->same($approvedRoot, $repo->tree()->rootHash());
            $t->same('Approved page', $repo->get('wp_posts:2'));
            $t->same($masterRoot, $repo->checkout('master')->rootHash());
            $t->same('Published post', $repo->get('wp_posts:1'));

            $reopened = QuadbStore::open($dir);
            $t->same("Collected 0/{$storedAfterGc} nodes\n", $reopened->garbageCollectText());
            $t->same($storedAfterGc, quadrableQuadbStoredNodeCount($reopened));

            $detachedRepo = QuadbStore::init($detachedDir);
            $detachedRepo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $detached = $detachedRepo->fork();
            $detached->put('wp_posts:1', 'Detached preview edit');
            $detached->put('wp_posts:2', 'Detached only page');
            $detachedRepo->save($detached);
            $detachedRepo->removeHead('master');

            $detachedBeforeGc = quadrableQuadbStoredNodeCount($detachedRepo);
            $detachedGc = quadrableQuadbParseGcText($detachedRepo->garbageCollectText());
            $detachedAfterGc = quadrableQuadbStoredNodeCount($detachedRepo);

            $t->same($detachedBeforeGc, $detachedGc['total']);
            $t->true($detachedGc['garbage'] > 0);
            $t->same($detachedBeforeGc - $detachedGc['garbage'], $detachedAfterGc);
            $t->true($detachedRepo->isDetachedHead());
            $t->same('Detached preview edit', $detachedRepo->get('wp_posts:1'));
            $t->same('Detached only page', $detachedRepo->get('wp_posts:2'));
            $t->contains('D> [detached] : ', $detachedRepo->headText());
            $t->same("Collected 0/{$detachedAfterGc} nodes\n", QuadbStore::open($detachedDir)->garbageCollectText());
        } finally {
            quadrableQuadbRemoveDir($dir);
            quadrableQuadbRemoveDir($detachedDir);
        }
    },
];

function quadrableQuadbTempDir(): string
{
    return sys_get_temp_dir() . '/quadrable-quadb-' . bin2hex(random_bytes(6));
}

function quadrableQuadbRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($dir);
}

/**
 * @return list<string>
 */
function quadrableQuadbSortedLines(string $lines): array
{
    $trimmed = trim($lines);
    if ($trimmed === '') {
        return [];
    }

    $output = explode("\n", $trimmed);
    sort($output, SORT_STRING);

    return $output;
}

/**
 * @return list<string>
 */
function quadrableQuadbOutputLines(string $lines): array
{
    $trimmed = rtrim($lines, "\r\n");
    if ($trimmed === '') {
        return [];
    }

    return explode("\n", $trimmed);
}

function quadrableQuadbDecodeHexProof(string $proofHex): string
{
    $trimmed = trim($proofHex);
    if (!str_starts_with($trimmed, '0x')) {
        throw new InvalidArgumentException('expected 0x-prefixed proof');
    }

    $decoded = hex2bin(substr($trimmed, 2));
    if ($decoded === false) {
        throw new InvalidArgumentException('expected hexadecimal proof');
    }

    return $decoded;
}

function quadrableQuadbStoredNodeCount(QuadbStore $repo): int
{
    $snapshot = $repo->nodeStore()->exportSnapshot();

    return count($snapshot['leaves']) + count($snapshot['branches']);
}

/**
 * @param array{numNodes: int, numLeafNodes: int, numBranchNodes: int, numWitnessNodes: int, maxDepth: int, numBytes: int} $stats
 *
 * @return list<string>
 */
function quadrableQuadbStatsLines(array $stats): array
{
    return [
        'numNodes:        ' . $stats['numNodes'],
        'numLeafNodes:    ' . $stats['numLeafNodes'],
        'numBranchNodes:  ' . $stats['numBranchNodes'],
        'numWitnessNodes: ' . $stats['numWitnessNodes'],
        'maxDepth:        ' . $stats['maxDepth'],
        'numBytes:        ' . $stats['numBytes'],
    ];
}

/**
 * @return array{garbage: int, total: int}
 */
function quadrableQuadbParseGcText(string $text): array
{
    if (!preg_match('/^Collected ([0-9]+)\/([0-9]+) nodes\n$/', $text, $matches)) {
        throw new RuntimeException('unexpected garbage collection output: ' . $text);
    }

    return [
        'garbage' => (int) $matches[1],
        'total' => (int) $matches[2],
    ];
}
