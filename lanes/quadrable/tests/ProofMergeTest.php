<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\SparseTree;

return [
    'maps upstream mergeProof expansion between separately imported proofs' => static function (TestRunner $t): void {
        $full = new SparseTree();
        $changes = $full->change();
        for ($i = 0; $i < 100; $i++) {
            $s = (string) $i;
            $changes->put($s, $s . 'val');
        }
        $changes->apply();

        $root = $full->rootHash();
        $wideKeys = [];
        for ($i = -50; $i < 50; $i++) {
            $wideKeys[] = (string) $i;
        }

        $partial = SparseTree::importProof(
            Proof::decode($full->exportProof($wideKeys)->encode()),
            $root
        );

        $t->same('33val', $partial->get('33'));
        $t->throws(RuntimeException::class, static fn () => $partial->get('75'));

        $partial->mergeProof(Proof::decode($full->exportProof(['75'])->encode()));

        $t->same($root, $partial->rootHash());
        $t->same('33val', $partial->get('33'));
        $t->same('75val', $partial->get('75'));
    },
    'maps upstream sub proof export from an imported partial tree' => static function (TestRunner $t): void {
        $full = new SparseTree();
        $changes = $full->change();
        for ($i = 0; $i < 100; $i++) {
            $s = (string) $i;
            $changes->put($s, $s . 'val');
        }
        $changes->apply();

        $root = $full->rootHash();
        $wideKeys = [];
        for ($i = -50; $i < 50; $i++) {
            $wideKeys[] = (string) $i;
        }

        $partial = SparseTree::importProof(
            Proof::decode($full->exportProof($wideKeys)->encode()),
            $root
        );

        $t->same('33val', $partial->get('33'));

        $narrowKeys = [];
        for ($i = -10; $i < 10; $i++) {
            $narrowKeys[] = (string) $i;
        }
        $subProof = Proof::decode($partial->exportProof($narrowKeys)->encode());
        $narrow = SparseTree::importProof($subProof, $root);
        $query = $narrow->getMulti($narrowKeys);

        for ($i = -10; $i < 10; $i++) {
            $key = (string) $i;
            if ($i < 0) {
                $t->true(!$query[$key]['exists'], 'negative sub-proof key should be proven absent: ' . $key);
            } else {
                $t->true($query[$key]['exists'], 'positive sub-proof key should be present: ' . $key);
                $t->same($key . 'val', $query[$key]['value']);
            }
        }

        $t->throws(RuntimeException::class, static fn () => $narrow->get('33'));
    },
    'maps upstream mergeProof witness leaf upgrade' => static function (TestRunner $t): void {
        $full = quadrableMergeProofFixture();
        $root = $full->rootHash();
        $probeKey = 'candidate-8';
        $witnessLeafKey = '353568684874';

        $partial = SparseTree::importProof(
            Proof::decode($full->exportProof([$probeKey])->encode()),
            $root
        );

        $t->throws(RuntimeException::class, static fn () => $partial->get($witnessLeafKey));

        $partial->mergeProof(Proof::decode($full->exportProof([$witnessLeafKey])->encode()));

        $t->same($root, $partial->rootHash());
        $t->same('A', $partial->get($witnessLeafKey));
        $t->same(null, $partial->get($probeKey));
    },
    'mergeProof rejects proofs from a different root' => static function (TestRunner $t): void {
        $full = quadrableMergeProofFixture();
        $partial = SparseTree::importProof(
            Proof::decode($full->exportProof(['353568684874'])->encode()),
            $full->rootHash()
        );

        $full->put('353568684874', 'A2');

        $t->throws(RuntimeException::class, static fn () => $partial->mergeProof(
            Proof::decode($full->exportProof(['852771900452'])->encode())
        ));
    },
    'wordpress snapshot proofs can be merged for authenticated partial reads' => static function (TestRunner $t): void {
        $fixturePath = __DIR__ . '/../fixtures/wordpress-ordered-snapshot.json';
        $records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        $full = new SparseTree();
        $changes = $full->change();
        foreach ($records as $record) {
            $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
        }
        $changes->apply();

        $root = $full->rootHash();
        $siteUrlKey = Key::fromInteger(1);
        $postKey = Key::fromInteger(3);

        $partial = SparseTree::importProof(
            Proof::decode($full->exportRawProof([$siteUrlKey])->encode()),
            $root
        );

        $t->same('wp_options:siteurl=https://example.test', $partial->getKey($siteUrlKey));
        $t->throws(RuntimeException::class, static fn () => $partial->getKey($postKey));

        $partial->mergeProof(Proof::decode($full->exportRawProof([$postKey])->encode()));

        $t->same($root, $partial->rootHash());
        $t->same('wp_options:siteurl=https://example.test', $partial->getKey($siteUrlKey));
        $t->same('wp_posts:1=Hello world', $partial->getKey($postKey));
    },
    'wordpress partial snapshot can delegate a narrower sub proof' => static function (TestRunner $t): void {
        $fixturePath = __DIR__ . '/../fixtures/wordpress-ordered-snapshot.json';
        $records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        $full = new SparseTree();
        $changes = $full->change();
        foreach ($records as $record) {
            $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
        }
        $changes->apply();

        $root = $full->rootHash();
        $wideProof = Proof::decode($full->exportRawProof([
            Key::fromInteger(1),
            Key::fromInteger(2),
            Key::fromInteger(3),
            Key::fromInteger(4),
        ])->encode());
        $partial = SparseTree::importProof($wideProof, $root);

        $homeKey = Key::fromInteger(2);
        $postKey = Key::fromInteger(3);
        $subProof = Proof::decode($partial->exportRawProof([$homeKey, $postKey])->encode());
        $delegated = SparseTree::importProof($subProof, $root);

        $t->same($root, $delegated->rootHash());
        $t->same('wp_options:home=https://example.test', $delegated->getKey($homeKey));
        $t->same('wp_posts:1=Hello world', $delegated->getKey($postKey));
        $t->throws(RuntimeException::class, static fn () => $delegated->getKey(Key::fromInteger(4)));
    },
];

function quadrableMergeProofFixture(): SparseTree
{
    $tree = new SparseTree();
    $tree->change()
        ->put('353568684874', 'A')
        ->put('852771900452', 'B')
        ->put('877307249616', 'C')
        ->put('640237942109', 'D')
        ->apply();

    return $tree;
}
