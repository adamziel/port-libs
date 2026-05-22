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

$postKey = Key::fromInteger(3);
$oldRoot = $tree->rootHash();
$proofBytes = $tree->exportRawProof([$postKey])->encode();

$tree->putKey($postKey, 'wp_posts:1=Hello authenticated world');
$newRoot = $tree->rootHash();

$partial = SparseTree::importProof(Proof::decode($proofBytes), $oldRoot);
$partial->putKey($postKey, 'wp_posts:1=Hello authenticated world');

echo json_encode([
    'oldTrustedRoot' => $oldRoot,
    'newTrustedRoot' => $newRoot,
    'partialRootAfterUpdate' => $partial->rootHash(),
    'encodedProofBytes' => strlen($proofBytes),
    'updatedRecord' => $partial->getKey($postKey),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
