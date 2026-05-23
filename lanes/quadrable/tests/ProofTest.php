<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\ProofCommand;
use PortLibs\Quadrable\ProofStrand;
use PortLibs\Quadrable\SparseTree;

return [
    'compact proof codec round trips upstream transport command shapes' => static function (TestRunner $t): void {
        $proof = new Proof([
            new ProofStrand(ProofStrand::LEAF, 1, str_repeat('11', 32), 'value'),
            new ProofStrand(ProofStrand::WITNESS_EMPTY, 1, str_repeat('00', 32)),
        ], [
            new ProofCommand(ProofCommand::MERGE, 0),
        ]);

        $encoded = $proof->encode();
        $decoded = Proof::decode($encoded);

        $t->same($encoded, $decoded->encode());
        $t->same(2, count($decoded->strands));
        $t->same(ProofStrand::LEAF, $decoded->strands[0]->type);
        $t->same('value', $decoded->strands[0]->value);
        $t->same(ProofCommand::MERGE, $decoded->commands[0]->operation);
    },
    'full key proof encoding preserves tracked string keys for partial enumeration' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $tree->change()
            ->put('wp_options:siteurl', 'https://example.test')
            ->put('wp_options:home', 'https://example.test')
            ->put('wp_posts:1', 'Hello world')
            ->apply();

        $root = $tree->rootHash();
        $proof = $tree->exportProof([
            'wp_options:siteurl',
            'wp_posts:1',
            'wp_posts:404',
        ]);
        $encoded = $proof->encode(Proof::ENCODING_FULL_KEYS);
        $decoded = Proof::decode($encoded);
        $partial = SparseTree::importProof($decoded, $root);

        $t->same(Proof::ENCODING_FULL_KEYS, ord($encoded[0]));
        $t->same($root, $partial->rootHash());
        $t->same('https://example.test', $partial->get('wp_options:siteurl'));
        $t->same('Hello world', $partial->get('wp_posts:1'));
        $t->same(null, $partial->get('wp_posts:404'));
        $t->throws(RuntimeException::class, static fn () => $partial->get('wp_options:home'));

        $entries = [];
        foreach ($partial->orderedEntries() as $entry) {
            $entries[$entry->stringKey() ?? $entry->keyHex()] = $entry->value();
        }
        ksort($entries, SORT_STRING);

        $t->same([
            'wp_options:siteurl' => 'https://example.test',
            'wp_posts:1' => 'Hello world',
        ], $entries);

        $raw = new SparseTree();
        $raw->putKey(Key::fromInteger(1), 'one');
        $t->throws(RuntimeException::class, static fn () => $raw->exportRawProof([Key::fromInteger(1)])->encode(Proof::ENCODING_FULL_KEYS));
    },
    'maps upstream basic proof export import and incomplete lookups' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $changes = $tree->change();
        for ($i = 0; $i < 100; $i++) {
            $s = (string) $i;
            $changes->put($s, $s . 'val');
        }
        $changes->put('long', str_repeat('A', 789));
        $changes->apply();

        $root = $tree->rootHash();
        $proof = Proof::decode($tree->exportProof(['99', '68', 'long', 'asdf'])->encode());
        $partial = SparseTree::importProof($proof, $root);

        $t->same($root, $partial->rootHash());
        $t->same('99val', $partial->get('99'));
        $t->same('68val', $partial->get('68'));
        $t->same(str_repeat('A', 789), $partial->get('long'));
        $t->same(null, $partial->get('asdf'));
        $t->throws(RuntimeException::class, static fn () => $partial->get('0'));
        $t->throws(RuntimeException::class, static fn () => SparseTree::importProof($proof, str_repeat('f', 64)));
    },
    'maps upstream big proof test over 1000 string queries' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $changes = $tree->change();
        for ($i = 0; $i < 1000; $i++) {
            $s = (string) $i;
            $changes->put($s, $s . 'val');
        }
        $changes->apply();

        $root = $tree->rootHash();
        $keys = [];
        for ($i = -500; $i < 500; $i++) {
            $keys[] = (string) $i;
        }

        $encoded = $tree->exportProof($keys)->encode();
        $proof = Proof::decode($encoded);
        $partial = SparseTree::importProof($proof, $root);
        $query = $partial->getMulti($keys);

        $t->same($encoded, $proof->encode());
        $t->same($root, $partial->rootHash());

        for ($i = -500; $i < 500; $i++) {
            $key = (string) $i;
            if ($i < 0) {
                $t->true(!$query[$key]['exists'], 'negative big-proof key should be proven absent: ' . $key);
            } else {
                $t->same($key . 'val', $query[$key]['value'], 'positive big-proof key should be present: ' . $key);
            }
        }

        $t->throws(RuntimeException::class, static fn () => $partial->get('500'));
    },
    'matches upstream C++ big proof transport byte oracle' => static function (TestRunner $t): void {
        $oraclePath = __DIR__ . '/../fixtures/upstream-big-proof-oracle.json';
        $oracle = json_decode((string) file_get_contents($oraclePath), true, flags: JSON_THROW_ON_ERROR);

        $tree = new SparseTree();
        $changes = $tree->change();
        for ($i = 0; $i < $oracle['entryCount']; $i++) {
            $s = (string) $i;
            $changes->put($s, $s . 'val');
        }
        $changes->apply();

        $keys = [];
        for ($i = $oracle['queryStartInclusive']; $i < $oracle['queryEndExclusive']; $i++) {
            $keys[] = (string) $i;
        }

        $encoded = $tree->exportProof($keys)->encode();
        $hex = '0x' . bin2hex($encoded);

        $t->same($oracle['encodedProofBytes'], strlen($encoded));
        $t->same($oracle['encodedProofBytesSha256'], hash('sha256', $encoded));
        $t->same($oracle['encodedProofHexTextSha256'], hash('sha256', $hex));
        $t->same($oracle['encodedProofHexPrefix'], substr($hex, 0, strlen($oracle['encodedProofHexPrefix'])));
        $t->same($oracle['encodedProofHexSuffix'], substr($hex, -strlen($oracle['encodedProofHexSuffix'])));

        $partial = SparseTree::importProof(Proof::decode($encoded), $tree->rootHash());
        $query = $partial->getMulti($keys);
        $t->same('0val', $query['0']['value']);
        $t->same('499val', $query['499']['value']);
        $t->true(!$query['-1']['exists']);
        $t->throws(RuntimeException::class, static fn () => $partial->get('500'));
    },
    'maps upstream shared empty witness proof for multiple absent keys' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $tree->change()
            ->put('735838777414', 'A')
            ->put('367300200150', 'B')
            ->apply();

        $proof = Proof::decode($tree->exportProof([
            '582086612140',
            '37481825503',
        ])->encode());
        $partial = SparseTree::importProof($proof, $tree->rootHash());

        $t->same(null, $partial->get('582086612140'));
        $t->same(null, $partial->get('37481825503'));
        $t->same(null, $partial->get('915377487270'));
        $t->throws(RuntimeException::class, static fn () => $partial->get('735838777414'));
        $t->throws(RuntimeException::class, static fn () => $partial->get('367300200150'));
    },
    'maps upstream no unnecessary empty witness proof shape' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $tree->change()
            ->put('983467173326', 'A')
            ->put('50728759955', 'B')
            ->apply();

        $proof = Proof::decode($tree->exportProof([
            '983467173326',
            '14864808866',
        ])->encode());

        $t->same(1, count($proof->strands));

        $partial = SparseTree::importProof($proof, $tree->rootHash());
        $t->same('A', $partial->get('983467173326'));
        $t->same(null, $partial->get('14864808866'));
        $t->throws(RuntimeException::class, static fn () => $partial->get('50728759955'));
    },
    'maps upstream integer proof sizing bound for sparse raw keys' => static function (TestRunner $t): void {
        for ($i = 1; $i <= 1e12; $i *= 10) {
            $tree = new SparseTree();
            $key = Key::fromInteger((int) $i);
            $tree->putKey($key, 'A');

            $proof = $tree->exportRawProof([$key]);

            $t->true(strlen($proof->encode()) <= 13, 'proof is too large for integer key ' . $i);
        }
    },
    'maps upstream range proofs for raw integer keys' => static function (TestRunner $t): void {
        $tree = new SparseTree();
        $changes = $tree->change();
        for ($i = 1; $i < 1000; $i++) {
            $changes->putKey(Key::fromInteger($i), (string) $i);
        }
        $changes->apply();

        $proof = Proof::decode($tree->exportProofRange(Key::fromInteger(50), Key::fromInteger(60))->encode());
        $partial = SparseTree::importProof($proof, $tree->rootHash());

        foreach ([50, 55, 60] as $i) {
            $t->same((string) $i, $partial->getKey(Key::fromInteger($i)));
        }

        foreach ([49, 61, 999, 1] as $i) {
            $t->throws(RuntimeException::class, static fn () => $partial->getKey(Key::fromInteger($i)));
        }
    },
    'maps upstream range sub proof export from an imported partial tree' => static function (TestRunner $t): void {
        $full = new SparseTree();
        $changes = $full->change();
        for ($i = 1; $i < 1000; $i++) {
            $changes->putKey(Key::fromInteger($i), (string) $i);
        }
        $changes->apply();

        $root = $full->rootHash();
        $wide = SparseTree::importProof(
            Proof::decode($full->exportProofRange(Key::fromInteger(50), Key::fromInteger(60))->encode()),
            $root
        );

        $subProof = Proof::decode($wide->exportProofRange(Key::fromInteger(52), Key::fromInteger(54))->encode());
        $narrow = SparseTree::importProof($subProof, $root);

        foreach ([52, 53, 54] as $i) {
            $t->same((string) $i, $narrow->getKey(Key::fromInteger($i)));
        }

        foreach ([51, 55, 60] as $i) {
            $t->throws(RuntimeException::class, static fn () => $narrow->getKey(Key::fromInteger($i)));
        }

        $singleKeyOnly = SparseTree::importProof(
            Proof::decode($full->exportRawProof([Key::fromInteger(53)])->encode()),
            $root
        );

        $t->throws(
            RuntimeException::class,
            static fn () => $singleKeyOnly->exportProofRange(Key::fromInteger(52), Key::fromInteger(54))
        );
    },
    'wordpress ordered snapshot can be authenticated as a compact range proof' => static function (TestRunner $t): void {
        $fixturePath = __DIR__ . '/../fixtures/wordpress-ordered-snapshot.json';
        $records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        $tree = new SparseTree();
        $changes = $tree->change();
        foreach ($records as $record) {
            $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
        }
        $changes->apply();

        $proof = Proof::decode($tree->exportProofRange(Key::fromInteger(2), Key::fromInteger(4))->encode());
        $partial = SparseTree::importProof($proof, $tree->rootHash());

        $t->same('wp_options:home=https://example.test', $partial->getKey(Key::fromInteger(2)));
        $t->same('wp_posts:1=Hello world', $partial->getKey(Key::fromInteger(3)));
        $t->same('wp_postmeta:1:_thumbnail_id=42', $partial->getKey(Key::fromInteger(4)));
        $t->throws(RuntimeException::class, static fn () => $partial->getKey(Key::fromInteger(1)));
        $t->throws(RuntimeException::class, static fn () => $partial->getKey(Key::fromInteger(5)));
    },
    'wordpress partial snapshot can delegate a narrower range proof' => static function (TestRunner $t): void {
        $fixturePath = __DIR__ . '/../fixtures/wordpress-ordered-snapshot.json';
        $records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        $full = new SparseTree();
        $changes = $full->change();
        foreach ($records as $record) {
            $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
        }
        $changes->apply();

        $root = $full->rootHash();
        $wide = SparseTree::importProof(
            Proof::decode($full->exportProofRange(Key::fromInteger(1), Key::fromInteger(5))->encode()),
            $root
        );

        $subProof = Proof::decode($wide->exportProofRange(Key::fromInteger(2), Key::fromInteger(3))->encode());
        $delegated = SparseTree::importProof($subProof, $root);

        $t->same($root, $delegated->rootHash());
        $t->same('wp_options:home=https://example.test', $delegated->getKey(Key::fromInteger(2)));
        $t->same('wp_posts:1=Hello world', $delegated->getKey(Key::fromInteger(3)));
        $t->throws(RuntimeException::class, static fn () => $delegated->getKey(Key::fromInteger(1)));
        $t->throws(RuntimeException::class, static fn () => $delegated->getKey(Key::fromInteger(4)));
    },
];
