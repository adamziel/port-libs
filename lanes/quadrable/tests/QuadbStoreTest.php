<?php

declare(strict_types=1);

use PortLibs\Quadrable\Blake2s;
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
    'native quadb store honors noTrackKeys for export diff dump and full-key proofs' => static function (TestRunner $t): void {
        $privateDir = quadrableQuadbTempDir();
        $trackedDir = quadrableQuadbTempDir();

        try {
            $siteUrlUnknown = quadrableQuadbUnknownStringKey('wp_options:siteurl');
            $homeUnknown = quadrableQuadbUnknownStringKey('wp_options:home');
            $postUnknown = quadrableQuadbUnknownStringKey('wp_posts:1');

            $private = QuadbStore::init($privateDir, false);
            $private->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $t->same([
                $postUnknown . '|Published post',
                $homeUnknown . '|https://example.test',
                $siteUrlUnknown . '|https://example.test',
            ], quadrableQuadbSortedLines($private->exportLines('|')));

            $root = $private->tree()->rootHash();
            $proofBytes = $private->exportProofBytes(['wp_options:siteurl']);
            $partial = SparseTree::importProof(Proof::decode($proofBytes), $root);

            $t->same('https://example.test', $partial->get('wp_options:siteurl'));
            $t->throws(RuntimeException::class, static fn () => $private->exportProofBytes(
                ['wp_options:siteurl'],
                Proof::ENCODING_FULL_KEYS
            ));

            $private->fork('preview');
            $private->put('wp_posts:1', 'Preview edit');
            $t->same([
                '+' . $postUnknown . '|Preview edit',
                '-' . $postUnknown . '|Published post',
            ], quadrableQuadbSortedLines($private->diffLines('master', '|')));
            $t->contains('leaf: ' . $postUnknown . " = Preview edit\n", $private->dumpTreeText());
            $t->contains('leaf: ' . $postUnknown . " = Preview edit\n", QuadbStore::open($privateDir)->dumpTreeText());

            $tracked = QuadbStore::init($trackedDir);
            $tracked->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n",
                '|'
            );
            $t->same([
                'wp_options:home|https://example.test',
                'wp_options:siteurl|https://example.test',
            ], quadrableQuadbSortedLines($tracked->exportLines('|')));

            $masked = QuadbStore::open($trackedDir, false);
            $t->same([
                $homeUnknown . '|https://example.test',
                $siteUrlUnknown . '|https://example.test',
            ], quadrableQuadbSortedLines($masked->exportLines('|')));
            $t->throws(RuntimeException::class, static fn () => $masked->exportProofBytes(
                ['wp_options:siteurl'],
                Proof::ENCODING_FULL_KEYS
            ));

            $masked->put('wp_options:siteurl', 'https://private.example.test');
            $visibleAgain = QuadbStore::open($trackedDir);
            $t->same([
                $siteUrlUnknown . '|https://private.example.test',
                'wp_options:home|https://example.test',
            ], quadrableQuadbSortedLines($visibleAgain->exportLines('|')));
        } finally {
            quadrableQuadbRemoveDir($privateDir);
            quadrableQuadbRemoveDir($trackedDir);
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
    'native quadb store imports exports and proves composite integer hash keys' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $thumbnail = quadrableQuadbCompositeSuffix('_thumbnail_id');
            $editLock = quadrableQuadbCompositeSuffix('_edit_lock');
            $template = quadrableQuadbCompositeSuffix('_wp_page_template');
            $missing = quadrableQuadbCompositeSuffix('_missing_meta');

            $repo = QuadbStore::init($sourceDir);
            $t->same(3, $repo->importCompositeLines(
                "42|{$thumbnail}|wp_postmeta:42:_thumbnail_id=7\n"
                . "42|{$editLock}|wp_postmeta:42:_edit_lock=1716400000\n"
                . "42|{$template}|wp_postmeta:42:_wp_page_template=templates/full-width.html\n",
                '|'
            ));

            $root = $repo->tree()->rootHash();
            $t->same('wp_postmeta:42:_thumbnail_id=7', $repo->getCompositeKey(42, $thumbnail));
            $t->same('wp_postmeta:42:_edit_lock=1716400000', $repo->getCompositeKey(42, '0x' . strtoupper($editLock)));

            $reopened = QuadbStore::open($sourceDir);
            $t->same([
                "42|{$editLock}|wp_postmeta:42:_edit_lock=1716400000",
                "42|{$thumbnail}|wp_postmeta:42:_thumbnail_id=7",
                "42|{$template}|wp_postmeta:42:_wp_page_template=templates/full-width.html",
            ], quadrableQuadbSortedLines($reopened->exportCompositeLines('|')));

            $reopened->putCompositeKey(43, $thumbnail, 'wp_postmeta:43:_thumbnail_id=8');
            $t->same('wp_postmeta:43:_thumbnail_id=8', QuadbStore::open($sourceDir)->getCompositeKey(43, $thumbnail));
            $reopened->deleteCompositeKey(43, $thumbnail);
            $t->throws(RuntimeException::class, static fn () => $reopened->getCompositeKey(43, $thumbnail));

            $proofKeys = "42|{$thumbnail}\n42|{$missing}\n";
            $proofBytes = $reopened->exportCompositeProofBytesFromKeyLines($proofKeys, '|');
            $proofHex = $reopened->exportCompositeProofHexFromKeyLines($proofKeys, '|');
            $t->same($proofBytes, quadrableQuadbDecodeHexProof($proofHex));

            $target = QuadbStore::init($targetDir);
            $target->checkout('postmeta-proof');
            $t->same('', $target->importProofBytesOutputText($proofBytes, $root));
            $t->same('wp_postmeta:42:_thumbnail_id=7', $target->getCompositeKey(42, $thumbnail));
            $t->throws(RuntimeException::class, static fn () => $target->getCompositeKey(42, $missing));

            $t->throws(InvalidArgumentException::class, static fn () => $repo->importCompositeLines("42|bad|value\n", '|'));
            $t->throws(InvalidArgumentException::class, static fn () => $repo->importCompositeLines(((string) (Key::MAX_INTEGER + 1)) . "|{$thumbnail}|value\n", '|'));
            $t->throws(RuntimeException::class, static fn () => $repo->exportCompositeProofBytesFromKeyLines("42|{$thumbnail}|extra\n", '|'));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store exports stdin key proofs and imports binary proof input like quadb' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();
        $integerSourceDir = quadrableQuadbTempDir();
        $integerTargetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $keyInput = "wp_options:siteurl\nwp_posts:1\nwp_posts:404\n";
            $proofBytes = $source->exportProofBytesFromKeyLines($keyInput, Proof::ENCODING_FULL_KEYS);

            $t->same(
                $source->exportProofBytes([
                    'wp_options:siteurl',
                    'wp_posts:1',
                    'wp_posts:404',
                ], Proof::ENCODING_FULL_KEYS),
                $proofBytes
            );
            $t->same(
                $source->exportProofHex([
                    'wp_options:siteurl',
                    'wp_posts:1',
                    'wp_posts:404',
                ], Proof::ENCODING_FULL_KEYS),
                $source->exportProofHexFromKeyLines($keyInput, Proof::ENCODING_FULL_KEYS)
            );
            $t->same(Proof::ENCODING_FULL_KEYS, ord($proofBytes[0]));

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-binary-proof');
            $t->same('', $target->importProofBytesOutputText($proofBytes, $trustedRoot));
            $t->same('https://example.test', $target->get('wp_options:siteurl'));
            $t->same('Published post', $target->get('wp_posts:1'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_options:home'));
            $t->throws(RuntimeException::class, static fn () => $target->get('wp_posts:404'));

            $homeProofBytes = $source->exportProofBytesFromKeyLines("wp_options:home\n", Proof::ENCODING_FULL_KEYS);
            $t->same($trustedRoot, $target->mergeProofBytes($homeProofBytes));
            $t->same('https://example.test', $target->get('wp_options:home'));

            $integerSource = QuadbStore::init($integerSourceDir);
            $integerSource->importIntegerLines(
                "2,wp_options:home=https://example.test\n"
                . "4,wp_posts:1=Published post\n"
            );

            $integerRoot = $integerSource->tree()->rootHash();
            $integerKeyInput = "2\n4\n99";
            $integerProofBytes = $integerSource->exportIntegerProofBytesFromKeyLines($integerKeyInput);
            $t->same($integerSource->exportIntegerProofBytes([2, 4, 99]), $integerProofBytes);

            $integerTarget = QuadbStore::init($integerTargetDir);
            $integerTarget->checkout('wp-integer-binary-proof');
            $integerTarget->importProofBytes($integerProofBytes, $integerRoot);
            $t->same('wp_options:home=https://example.test', $integerTarget->getInteger(2));
            $t->same('wp_posts:1=Published post', $integerTarget->getInteger(4));
            $t->throws(RuntimeException::class, static fn () => $integerTarget->getInteger(99));
            $t->throws(InvalidArgumentException::class, static fn () => $integerSource->exportIntegerProofBytesFromKeyLines("2\nnot-an-int\n"));
            $t->throws(InvalidArgumentException::class, static fn () => $integerSource->exportIntegerProofBytesFromKeyLines(((string) PHP_INT_MAX) . "\n"));
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
            quadrableQuadbRemoveDir($integerSourceDir);
            quadrableQuadbRemoveDir($integerTargetDir);
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
    'native quadb store retains mergeProof import garbage until quadb gc' => static function (TestRunner $t): void {
        $sourceDir = quadrableQuadbTempDir();
        $targetDir = quadrableQuadbTempDir();

        try {
            $source = QuadbStore::init($sourceDir);
            $source->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n"
                . "wp_posts:2|Second post\n",
                '|'
            );

            $trustedRoot = $source->tree()->rootHash();
            $siteProofHex = $source->exportProofHex(['wp_options:siteurl'], Proof::ENCODING_FULL_KEYS);
            $postProofHex = $source->exportProofHex(['wp_posts:1'], Proof::ENCODING_FULL_KEYS);

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-merge-gc');
            $target->importProofHex($siteProofHex, $trustedRoot);
            $target->mergeProofHex($postProofHex);

            $before = $target->lmdbBucketSnapshot();
            $beforeNodeCount = count($before['quadrable_nodesLeaf']) + count($before['quadrable_nodesInterior']);
            $t->same('Published post', $target->get('wp_posts:1'));
            $t->true(
                quadrableQuadbRawBucketBytes($before) > $target->stats()['numBytes'],
                'mergeProof import should leave unreferenced projected LMDB nodes before gc'
            );

            $gc = quadrableQuadbParseGcText($target->garbageCollectText());
            $after = $target->lmdbBucketSnapshot();
            $afterNodeCount = count($after['quadrable_nodesLeaf']) + count($after['quadrable_nodesInterior']);

            $t->same($beforeNodeCount, $gc['total']);
            $t->true($gc['garbage'] > 0);
            $t->same($beforeNodeCount - $gc['garbage'], $afterNodeCount);
            $t->same($target->stats()['numBytes'], quadrableQuadbRawBucketBytes($after));
            $t->same('https://example.test', $target->get('wp_options:siteurl'));
            $t->same('Published post', $target->get('wp_posts:1'));

            $reopened = QuadbStore::open($targetDir);
            $t->same($after, $reopened->lmdbBucketSnapshot());
            $t->same("Collected 0/{$afterNodeCount} nodes\n", $reopened->garbageCollectText());
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
    'native quadb store shares imported proof storage across divergent proof-backed forks' => static function (TestRunner $t): void {
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
                'wp_options:home',
            ], Proof::ENCODING_FULL_KEYS);

            $source->put('wp_options:siteurl', 'https://preview.example.test');
            $updatedRoot = $source->tree()->rootHash();

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-base');
            $target->importProofHex($proofHex, $trustedRoot);
            $target->fork('wp-delegated-preview');
            $target->put('wp_options:siteurl', 'https://preview.example.test');

            $previewRoot = $target->status()['rootHash'];
            $t->same($updatedRoot, $previewRoot);
            $t->same('https://preview.example.test', $target->get('wp_options:siteurl'));
            $t->same('https://example.test', $target->get('wp_options:home'));

            $base = $target->checkout('wp-delegated-base');
            $t->true($base instanceof SparseTree);
            $t->same($trustedRoot, $target->status()['rootHash']);
            $t->same('https://example.test', $target->get('wp_options:siteurl'));
            $t->same('https://example.test', $target->get('wp_options:home'));

            $lmdb = $target->lmdbBucketSnapshot();
            $headNodeIds = [];
            foreach ($lmdb['quadrable_head'] as $head => $rawNodeId) {
                $headNodeIds[$head] = quadrableQuadbUnpackUint64Le($rawNodeId);
            }
            $keyCounts = array_count_values(array_values($lmdb['quadrable_key']));

            $t->true($headNodeIds['wp-delegated-base'] !== $headNodeIds['wp-delegated-preview']);
            $t->same(1, $keyCounts['wp_options:home'] ?? 0);
            $t->same(2, $keyCounts['wp_options:siteurl'] ?? 0);
            $t->same($lmdb, QuadbStore::open($targetDir)->lmdbBucketSnapshot());

            $reopened = QuadbStore::open($targetDir);
            $reopened->checkout('wp-delegated-preview');
            $t->same($previewRoot, $reopened->status()['rootHash']);
            $t->same('https://preview.example.test', $reopened->get('wp_options:siteurl'));
            $reopened->checkout('wp-delegated-base');
            $t->same($trustedRoot, $reopened->status()['rootHash']);
            $t->same('https://example.test', $reopened->get('wp_options:siteurl'));
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
    'native quadb store exposes full-head LMDB bucket layout like upstream storage' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $tree = $repo->tree();
            $headNodeId = $tree->headNodeId();
            $storeSnapshot = $repo->nodeStore()->exportSnapshot();
            $lmdb = $repo->lmdbBucketSnapshot();

            $t->same(['master'], array_keys($lmdb['quadrable_head']));
            $t->same($headNodeId, quadrableQuadbUnpackUint64Le($lmdb['quadrable_head']['master']));
            $t->same('master', $lmdb['quadrable_quadb_state']['currHead']);
            $t->same(count($storeSnapshot['leaves']), count($lmdb['quadrable_nodesLeaf']));
            $t->same(count($storeSnapshot['branches']), count($lmdb['quadrable_nodesInterior']));
            $t->same(count($storeSnapshot['leaves']), count($lmdb['quadrable_key']));
            $t->same($repo->stats()['numBytes'], quadrableQuadbRawBucketBytes($lmdb));

            $leafNodeId = array_key_first($lmdb['quadrable_nodesLeaf']);
            $leaf = $storeSnapshot['leaves'][$leafNodeId];
            $leafRaw = $lmdb['quadrable_nodesLeaf'][$leafNodeId];
            $t->same(72 + strlen($leaf['value']), strlen($leafRaw));
            $t->same(4, quadrableQuadbUnpackUint64Le(substr($leafRaw, 0, 8)));
            $t->same($leaf['hash'], bin2hex(substr($leafRaw, 8, 32)));
            $t->same($leaf['keyHash'], bin2hex(substr($leafRaw, 40, 32)));
            $t->same($leaf['value'], substr($leafRaw, 72));
            $t->true(in_array($lmdb['quadrable_key'][$leafNodeId], [
                'wp_options:siteurl',
                'wp_options:home',
                'wp_posts:1',
            ], true));

            $branchNodeId = array_key_first($lmdb['quadrable_nodesInterior']);
            $branch = $storeSnapshot['branches'][$branchNodeId];
            $branchRaw = $lmdb['quadrable_nodesInterior'][$branchNodeId];
            $branchWord = quadrableQuadbUnpackUint64Le(substr($branchRaw, 0, 8));
            $branchType = $branchWord % 16;
            $firstChild = intdiv($branchWord, 16);
            $t->same(48, strlen($branchRaw));
            $t->same($branch['hash'], bin2hex(substr($branchRaw, 8, 32)));

            if ($branch['rightNodeId'] === 0) {
                $t->same(1, $branchType);
                $t->same($branch['leftNodeId'], $firstChild);
                $t->same(0, quadrableQuadbUnpackUint64Le(substr($branchRaw, 40, 8)));
            } elseif ($branch['leftNodeId'] === 0) {
                $t->same(2, $branchType);
                $t->same($branch['rightNodeId'], $firstChild);
                $t->same(0, quadrableQuadbUnpackUint64Le(substr($branchRaw, 40, 8)));
            } else {
                $t->same(3, $branchType);
                $t->same($branch['leftNodeId'], $firstChild);
                $t->same($branch['rightNodeId'], quadrableQuadbUnpackUint64Le(substr($branchRaw, 40, 8)));
            }

            $detached = $repo->fork();
            $detachedBuckets = $repo->lmdbBucketSnapshot();
            $t->true(!isset($detachedBuckets['quadrable_quadb_state']['currHead']));
            $t->same($detached->headNodeId(), quadrableQuadbUnpackUint64Le($detachedBuckets['quadrable_quadb_state']['detachedHead']));
            $t->same($lmdb['quadrable_nodesLeaf'], $detachedBuckets['quadrable_nodesLeaf']);
            $t->same($lmdb['quadrable_nodesInterior'], $detachedBuckets['quadrable_nodesInterior']);
        } finally {
            quadrableQuadbRemoveDir($dir);
        }
    },
    'native quadb store exposes raw LMDB entry bytes for backup tooling' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();
        $proofDir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_options:home|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );

            $tree = $repo->tree();
            $lmdb = $repo->lmdbBucketSnapshot();
            $raw = $repo->lmdbRawEntrySnapshot();

            $t->same([
                [
                    'key' => 'master',
                    'value' => quadrableQuadbPackUint64Le($tree->headNodeId()),
                ],
            ], $raw['quadrable_head']);
            $t->same([
                [
                    'key' => 'currHead',
                    'value' => 'master',
                ],
            ], $raw['quadrable_quadb_state']);

            $leafNodeIds = array_keys($lmdb['quadrable_nodesLeaf']);
            $interiorNodeIds = array_keys($lmdb['quadrable_nodesInterior']);
            $trackedKeyNodeIds = array_keys($lmdb['quadrable_key']);

            $t->same($leafNodeIds, array_map(
                static fn (array $entry): int => quadrableQuadbUnpackUint64Le($entry['key']),
                $raw['quadrable_nodesLeaf']
            ));
            $t->same($interiorNodeIds, array_map(
                static fn (array $entry): int => quadrableQuadbUnpackUint64Le($entry['key']),
                $raw['quadrable_nodesInterior']
            ));
            $t->same($trackedKeyNodeIds, array_map(
                static fn (array $entry): int => quadrableQuadbUnpackUint64Le($entry['key']),
                $raw['quadrable_key']
            ));

            $firstLeafNodeId = $leafNodeIds[0];
            $leafEntriesByKeyHex = quadrableQuadbRawEntriesByKeyHex($raw['quadrable_nodesLeaf']);
            $trackedKeyEntriesByKeyHex = quadrableQuadbRawEntriesByKeyHex($raw['quadrable_key']);
            $firstLeafKeyHex = bin2hex(quadrableQuadbPackUint64Le($firstLeafNodeId));

            $t->same($lmdb['quadrable_nodesLeaf'][$firstLeafNodeId], $leafEntriesByKeyHex[$firstLeafKeyHex]);
            $t->same($lmdb['quadrable_key'][$firstLeafNodeId], $trackedKeyEntriesByKeyHex[$firstLeafKeyHex]);

            $detached = $repo->fork();
            $detachedRaw = $repo->lmdbRawEntrySnapshot();
            $t->same([
                [
                    'key' => 'detachedHead',
                    'value' => quadrableQuadbPackUint64Le($detached->headNodeId()),
                ],
            ], $detachedRaw['quadrable_quadb_state']);
            $t->same($raw['quadrable_nodesLeaf'], $detachedRaw['quadrable_nodesLeaf']);
            $t->same($raw['quadrable_nodesInterior'], $detachedRaw['quadrable_nodesInterior']);

            $trustedRoot = $repo->tree()->rootHash();
            $proofHex = $repo->exportProofHex([
                'wp_options:siteurl',
                'wp_posts:404',
            ], Proof::ENCODING_FULL_KEYS);

            $proofRepo = QuadbStore::init($proofDir);
            $proofRepo->checkout('wp-delegated-raw-backup');
            $proofRepo->importProofHex($proofHex, $trustedRoot);

            $proofLmdb = $proofRepo->lmdbBucketSnapshot();
            $proofRaw = $proofRepo->lmdbRawEntrySnapshot();
            $proofHeadNodeId = quadrableQuadbUnpackUint64Le($proofLmdb['quadrable_head']['wp-delegated-raw-backup']);
            $interiorEntriesByKeyHex = quadrableQuadbRawEntriesByKeyHex($proofRaw['quadrable_nodesInterior']);

            $t->same([
                [
                    'key' => 'currHead',
                    'value' => 'wp-delegated-raw-backup',
                ],
            ], $proofRaw['quadrable_quadb_state']);
            $t->same(quadrableQuadbPackUint64Le($proofHeadNodeId), $proofRaw['quadrable_head'][0]['value']);
            $t->true($proofHeadNodeId >= 288230376151711744);
            $t->same(
                $proofLmdb['quadrable_nodesInterior'][$proofHeadNodeId],
                $interiorEntriesByKeyHex[bin2hex(quadrableQuadbPackUint64Le($proofHeadNodeId))]
            );
            $t->same($proofRaw, QuadbStore::open($proofDir)->lmdbRawEntrySnapshot());
        } finally {
            quadrableQuadbRemoveDir($dir);
            quadrableQuadbRemoveDir($proofDir);
        }
    },
    'native quadb store preserves numeric head names as LMDB string keys' => static function (TestRunner $t): void {
        $dir = quadrableQuadbTempDir();
        $proofDir = quadrableQuadbTempDir();

        try {
            $repo = QuadbStore::init($dir);
            $repo->importLines(
                "wp_options:siteurl|https://example.test\n"
                . "wp_posts:1|Published post\n",
                '|'
            );
            $masterRoot = $repo->tree()->rootHash();
            $masterHeadNodeId = $repo->tree()->headNodeId();

            $repo->fork('20260523');
            $repo->put('wp_posts:1', 'Numeric preview edit');
            $previewRoot = $repo->tree()->rootHash();
            $previewHeadNodeId = $repo->tree()->headNodeId();
            $raw = $repo->lmdbRawEntrySnapshot();

            $t->same('20260523', $repo->currentHeadName());
            $t->same([
                [
                    'key' => 'currHead',
                    'value' => '20260523',
                ],
            ], $raw['quadrable_quadb_state']);
            $t->same(
                [
                    '20260523' => quadrableQuadbPackUint64Le($previewHeadNodeId),
                    'master' => quadrableQuadbPackUint64Le($masterHeadNodeId),
                ],
                quadrableQuadbRawStringEntriesByKey($raw['quadrable_head'])
            );

            $reopened = QuadbStore::open($dir);
            $t->same('20260523', $reopened->currentHeadName());
            $t->same('Numeric preview edit', $reopened->get('wp_posts:1'));
            $t->same([
                "=> 20260523 : 0x{$previewRoot} ({$previewHeadNodeId})",
                "   master : 0x{$masterRoot} ({$masterHeadNodeId})",
            ], quadrableQuadbOutputLines($reopened->headText()));

            $reopened->checkout('master');
            $sourceProofHex = $reopened->exportProofHex(
                ['wp_options:siteurl'],
                Proof::ENCODING_FULL_KEYS
            );
            $proofRepo = QuadbStore::init($proofDir);
            $proofRepo->checkout('404');
            $proofRepo->importProofHex($sourceProofHex, $masterRoot);

            $proofRaw = $proofRepo->lmdbRawEntrySnapshot();
            $t->same('404', $proofRepo->currentHeadName());
            $t->same('404', $proofRaw['quadrable_head'][0]['key']);
            $t->same([
                [
                    'key' => 'currHead',
                    'value' => '404',
                ],
            ], $proofRaw['quadrable_quadb_state']);

            $proofReopened = QuadbStore::open($proofDir);
            $t->same('404', $proofReopened->currentHeadName());
            $t->same('https://example.test', $proofReopened->get('wp_options:siteurl'));
            $t->contains("=> 404 : 0x{$masterRoot} (", $proofReopened->headText());
        } finally {
            quadrableQuadbRemoveDir($dir);
            quadrableQuadbRemoveDir($proofDir);
        }
    },
    'native quadb store exposes proof-backed LMDB bucket layout like upstream importProof' => static function (TestRunner $t): void {
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

            $target = QuadbStore::init($targetDir);
            $target->checkout('wp-delegated-layout');
            $target->importProofHex($proofHex, $trustedRoot);

            $status = $target->status();
            $lmdb = $target->lmdbBucketSnapshot();
            $projectedHeadNodeId = quadrableQuadbUnpackUint64Le($lmdb['quadrable_head']['wp-delegated-layout']);

            $t->same('wp-delegated-layout', $lmdb['quadrable_quadb_state']['currHead']);
            $t->same($target->stats()['numBytes'], quadrableQuadbRawBucketBytes($lmdb));
            $t->true($status['headNodeId'] >= 576460752303423488);
            $t->true($projectedHeadNodeId >= 288230376151711744);
            $t->true($projectedHeadNodeId < 576460752303423488);
            $t->contains('wp_options:siteurl', implode("\n", $lmdb['quadrable_key']));

            $leafTypes = [];
            foreach ($lmdb['quadrable_nodesLeaf'] as $nodeId => $raw) {
                $t->true($nodeId > 0);
                $t->true($nodeId < 288230376151711744);

                $type = quadrableQuadbUnpackUint64Le(substr($raw, 0, 8)) % 16;
                $leafTypes[] = $type;

                if ($type === 4) {
                    $t->true(strlen($raw) > 72);
                    $t->same(64, strlen(bin2hex(substr($raw, 8, 32))));
                    $t->same(64, strlen(bin2hex(substr($raw, 40, 32))));
                } elseif ($type === 6) {
                    $t->same(104, strlen($raw));
                    $t->same(64, strlen(bin2hex(substr($raw, 72, 32))));
                }
            }

            $interiorTypes = [];
            foreach ($lmdb['quadrable_nodesInterior'] as $nodeId => $raw) {
                $t->true($nodeId >= 288230376151711744);
                $t->true($nodeId < 576460752303423488);
                $t->same(48, strlen($raw));

                $word = quadrableQuadbUnpackUint64Le(substr($raw, 0, 8));
                $type = $word % 16;
                $interiorTypes[] = $type;

                if ($type === 5) {
                    $t->same(0, quadrableQuadbUnpackUint64Le(substr($raw, 40, 8)));
                } else {
                    $t->true(in_array($type, [1, 2, 3], true));
                }
            }

            $t->true(in_array(4, $leafTypes, true));
            $t->true(in_array(6, $leafTypes, true));
            $t->true(in_array(5, $interiorTypes, true));
            $t->true(count(array_intersect([1, 2, 3], $interiorTypes)) > 0);
            $t->same($lmdb, QuadbStore::open($targetDir)->lmdbBucketSnapshot());
        } finally {
            quadrableQuadbRemoveDir($sourceDir);
            quadrableQuadbRemoveDir($targetDir);
        }
    },
    'native quadb store projects independent proof imports in upstream LMDB allocation order' => static function (TestRunner $t): void {
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

            $target = QuadbStore::init($targetDir);
            $target->checkout('z-first-import');
            $target->importProofHex($proofHex, $trustedRoot);
            $target->checkout('a-second-import');
            $target->importProofHex($proofHex, $trustedRoot);
            $target->checkout('z-first-import');
            $target->fork('z-first-fork');

            $lmdb = $target->lmdbBucketSnapshot();
            $heads = [];
            foreach ($lmdb['quadrable_head'] as $head => $rawNodeId) {
                $heads[$head] = quadrableQuadbUnpackUint64Le($rawNodeId);
            }

            $t->true($heads['z-first-import'] < $heads['a-second-import']);
            $t->same($heads['z-first-import'], $heads['z-first-fork']);
            $t->true($heads['a-second-import'] !== $heads['z-first-import']);

            $leafIds = array_keys($lmdb['quadrable_nodesLeaf']);
            $interiorIds = array_keys($lmdb['quadrable_nodesInterior']);
            $t->same(range(min($leafIds), max($leafIds)), $leafIds);
            $t->same(range(min($interiorIds), max($interiorIds)), $interiorIds);
            $t->same($lmdb, QuadbStore::open($targetDir)->lmdbBucketSnapshot());
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

function quadrableQuadbUnknownStringKey(string $key): string
{
    return 'H(?)=0x' . substr((new HashTree())->keyHash($key), 0, 12) . '...';
}

function quadrableQuadbCompositeSuffix(string $label): string
{
    return bin2hex(substr(Blake2s::hash($label), -23));
}

function quadrableQuadbStoredNodeCount(QuadbStore $repo): int
{
    $snapshot = $repo->nodeStore()->exportSnapshot();

    return count($snapshot['leaves']) + count($snapshot['branches']);
}

function quadrableQuadbUnpackUint64Le(string $bytes): int
{
    if (strlen($bytes) !== 8) {
        throw new RuntimeException('expected exactly eight bytes');
    }

    $parts = unpack('Vlow/Vhigh', $bytes);
    if (!is_array($parts)) {
        throw new RuntimeException('unable to unpack uint64');
    }

    return $parts['low'] + ($parts['high'] * 4294967296);
}

function quadrableQuadbPackUint64Le(int $value): string
{
    if ($value < 0) {
        throw new InvalidArgumentException('uint64 value must be non-negative');
    }

    return pack('V2', $value % 4294967296, intdiv($value, 4294967296));
}

/**
 * @param list<array{key: string, value: string}> $entries
 *
 * @return array<string, string>
 */
function quadrableQuadbRawEntriesByKeyHex(array $entries): array
{
    $indexed = [];
    foreach ($entries as $entry) {
        $indexed[bin2hex($entry['key'])] = $entry['value'];
    }

    return $indexed;
}

/**
 * @param list<array{key: string, value: string}> $entries
 *
 * @return array<string, string>
 */
function quadrableQuadbRawStringEntriesByKey(array $entries): array
{
    $indexed = [];
    foreach ($entries as $entry) {
        $indexed[$entry['key']] = $entry['value'];
    }

    ksort($indexed, SORT_STRING);

    return $indexed;
}

/**
 * @param array{
 *     quadrable_nodesLeaf: array<int, string>,
 *     quadrable_nodesInterior: array<int, string>
 * } $lmdb
 */
function quadrableQuadbRawBucketBytes(array $lmdb): int
{
    $bytes = 0;
    foreach ($lmdb['quadrable_nodesLeaf'] as $raw) {
        $bytes += strlen($raw);
    }
    foreach ($lmdb['quadrable_nodesInterior'] as $raw) {
        $bytes += strlen($raw);
    }

    return $bytes;
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
