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

$trustedRoot = $tree->rootHash();
$wideProof = $tree->exportRawProof([
    Key::fromInteger(1),
    Key::fromInteger(2),
    Key::fromInteger(3),
    Key::fromInteger(4),
])->encode();

$partial = SparseTree::importProof(Proof::decode($wideProof), $trustedRoot);
$homeKey = Key::fromInteger(2);
$postKey = Key::fromInteger(3);
$subProof = $partial->exportRawProof([$homeKey, $postKey])->encode();
$delegated = SparseTree::importProof(Proof::decode($subProof), $trustedRoot);

$thumbnailAvailable = true;
try {
    $delegated->getKey(Key::fromInteger(4));
} catch (RuntimeException) {
    $thumbnailAvailable = false;
}

echo json_encode([
    'scenario' => 'delegate a narrower authenticated WordPress snapshot proof from a partial import',
    'trustedRoot' => $trustedRoot,
    'wideProofBytes' => strlen($wideProof),
    'subProofBytes' => strlen($subProof),
    'home' => $delegated->getKey($homeKey),
    'post' => $delegated->getKey($postKey),
    'thumbnailAvailable' => $thumbnailAvailable,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
