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

$siteUrlKey = Key::fromInteger(1);
$postKey = Key::fromInteger(3);
$trustedRoot = $tree->rootHash();

$siteUrlProof = $tree->exportRawProof([$siteUrlKey])->encode();
$postProof = $tree->exportRawProof([$postKey])->encode();

$partial = SparseTree::importProof(Proof::decode($siteUrlProof), $trustedRoot);
$partial->mergeProof(Proof::decode($postProof));

echo json_encode([
    'trustedRoot' => $trustedRoot,
    'mergedPartialRoot' => $partial->rootHash(),
    'firstProofBytes' => strlen($siteUrlProof),
    'secondProofBytes' => strlen($postProof),
    'siteUrl' => $partial->getKey($siteUrlKey),
    'post' => $partial->getKey($postKey),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
