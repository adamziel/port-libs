<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixturePath = dirname(__DIR__) . '/fixtures/wordpress-ordered-snapshot.json';
$records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

$tree = new SparseTree();
$changes = $tree->change();

foreach ($records as $record) {
    $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
}

$changes->apply();

$proof = $tree->exportProofRange(Key::fromInteger(2), Key::fromInteger(4));
$encodedProof = $proof->encode();
$partial = SparseTree::importProof(Proof::decode($encodedProof), $tree->rootHash());

echo json_encode([
    'trustedRoot' => $tree->rootHash(),
    'encodedProofBytes' => strlen($encodedProof),
    'authenticatedRecords' => [
        2 => $partial->getKey(Key::fromInteger(2)),
        3 => $partial->getKey(Key::fromInteger(3)),
        4 => $partial->getKey(Key::fromInteger(4)),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
