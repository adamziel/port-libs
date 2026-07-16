<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\SparseTree;

return [
    'maps upstream update proof leaf mutation and witness update rejection' => static function (TestRunner $t): void {
        $full = quadrableUpdateProofFixture();
        $proof = Proof::decode($full->exportProof(['353568684874'])->encode());
        $origRoot = $full->rootHash();

        $full->put('353568684874', 'A2');
        $expectedRoot = $full->rootHash();

        $partial = SparseTree::importProof($proof, $origRoot);
        $partial->put('353568684874', 'A2');

        $t->same($expectedRoot, $partial->rootHash());
        $t->same('A2', $partial->get('353568684874'));
        $t->throws(RuntimeException::class, static fn () => $partial->put('852771900452', 'B2'));
    },
    'maps upstream update proof multiple leaf updates and split leaf insert' => static function (TestRunner $t): void {
        $full = quadrableUpdateProofFixture();
        $proof = Proof::decode($full->exportProof(['852771900452', '877307249616'])->encode());
        $origRoot = $full->rootHash();

        $full->put('852771900452', 'B2');
        $full->put('877307249616', 'C2');

        $partial = SparseTree::importProof($proof, $origRoot);
        $partial->put('852771900452', 'B2');
        $partial->put('877307249616', 'C2');

        $t->same($full->rootHash(), $partial->rootHash());

        $splitFull = quadrableUpdateProofFixture();
        $splitProof = Proof::decode($splitFull->exportProof(['852771900452'])->encode());
        $splitOrigRoot = $splitFull->rootHash();
        // BLAKE2s prefix 1101 follows the proven B leaf path, matching upstream's split-leaf shape.
        $splitFull->put('candidate-10', 'E');

        $splitPartial = SparseTree::importProof($splitProof, $splitOrigRoot);
        $splitPartial->put('candidate-10', 'E');

        $t->same($splitFull->rootHash(), $splitPartial->rootHash());
        $t->same('E', $splitPartial->get('candidate-10'));
    },
    'maps upstream update proof witness leaf upgrade and split behavior' => static function (TestRunner $t): void {
        // BLAKE2s prefix 1100 reaches the A witness leaf without proving A as a full leaf.
        $proofKey = 'candidate-1';
        $witnessLeafKey = '353568684874';

        $sameFull = quadrableUpdateProofFixture();
        $sameProof = Proof::decode($sameFull->exportProof([$proofKey])->encode());
        $sameOrigRoot = $sameFull->rootHash();
        $samePartial = SparseTree::importProof($sameProof, $sameOrigRoot);

        $t->throws(RuntimeException::class, static fn () => $samePartial->get($witnessLeafKey));
        $samePartial->put($witnessLeafKey, 'A');
        $t->same($sameOrigRoot, $samePartial->rootHash());
        $t->same('A', $samePartial->get($witnessLeafKey));

        $updateFull = quadrableUpdateProofFixture();
        $updateProof = Proof::decode($updateFull->exportProof([$proofKey])->encode());
        $updateOrigRoot = $updateFull->rootHash();
        $updateFull->put($witnessLeafKey, 'A2');

        $updatePartial = SparseTree::importProof($updateProof, $updateOrigRoot);
        $updatePartial->put($witnessLeafKey, 'A2');
        $t->same($updateFull->rootHash(), $updatePartial->rootHash());

        $splitFull = quadrableUpdateProofFixture();
        $splitProof = Proof::decode($splitFull->exportProof([$proofKey])->encode());
        $splitOrigRoot = $splitFull->rootHash();
        $splitFull->put($proofKey, 'NEW');

        $splitPartial = SparseTree::importProof($splitProof, $splitOrigRoot);
        $splitPartial->put($proofKey, 'NEW');
        $t->same($splitFull->rootHash(), $splitPartial->rootHash());
        $t->same('NEW', $splitPartial->get($proofKey));
    },
    'maps upstream update proof deletion bubbling and witness bubble guard' => static function (TestRunner $t): void {
        $full = new SparseTree();
        $full->change()
            ->put('731156037546', '1')
            ->put('925458752084', '2')
            ->apply();
        $proof = Proof::decode($full->exportProof(['731156037546', '925458752084'])->encode());
        $origRoot = $full->rootHash();

        $full->delete('731156037546');

        $partial = SparseTree::importProof($proof, $origRoot);
        $partial->delete('731156037546');

        $t->same($full->rootHash(), $partial->rootHash());
        $t->same('2', $partial->get('925458752084'));

        $guardFull = new SparseTree();
        $guardFull->change()->put('a', '1')->put('b', '2')->apply();
        $guardProof = Proof::decode($guardFull->exportProof(['a'])->encode());
        $guardPartial = SparseTree::importProof($guardProof, $guardFull->rootHash());

        $t->throws(RuntimeException::class, static fn () => $guardPartial->delete('a'));
    },
    'wordpress proof-backed post update can be verified from a narrow raw-key proof' => static function (TestRunner $t): void {
        $fixturePath = __DIR__ . '/../fixtures/wordpress-ordered-snapshot.json';
        $records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
        $postKey = Key::fromInteger(3);

        $full = new SparseTree();
        $changes = $full->change();
        foreach ($records as $record) {
            $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
        }
        $changes->apply();

        $proof = Proof::decode($full->exportRawProof([$postKey])->encode());
        $origRoot = $full->rootHash();
        $full->putKey($postKey, 'wp_posts:1=Hello authenticated world');

        $partial = SparseTree::importProof($proof, $origRoot);
        $partial->putKey($postKey, 'wp_posts:1=Hello authenticated world');

        $t->same($full->rootHash(), $partial->rootHash());
        $t->same('wp_posts:1=Hello authenticated world', $partial->getKey($postKey));
    },
];

function quadrableUpdateProofFixture(): SparseTree
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
