<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tree = new SparseTree();
$changes = $tree->change();

for ($postId = 0; $postId < 1000; $postId++) {
    $changes->put((string) $postId, 'wp_posts:' . $postId . '=snapshot post ' . $postId);
}

$changes->apply();

$trustedRoot = $tree->rootHash();
$queryKeys = [];
for ($externalId = -500; $externalId < 500; $externalId++) {
    $queryKeys[] = (string) $externalId;
}

$encodedProof = $tree->exportProof($queryKeys)->encode();
$partial = SparseTree::importProof(Proof::decode($encodedProof), $trustedRoot);
$query = $partial->getMulti($queryKeys);

$present = 0;
$absent = 0;
foreach ($queryKeys as $key) {
    if ($query[$key]['exists']) {
        $present++;
    } else {
        $absent++;
    }
}

$nextPostAvailable = true;
try {
    $partial->get('500');
} catch (RuntimeException) {
    $nextPostAvailable = false;
}

echo json_encode([
    'scenario' => 'authenticate a 1000-key WordPress snapshot proof window with proven missing external IDs',
    'trustedRoot' => $trustedRoot,
    'encodedProofBytes' => strlen($encodedProof),
    'queriedKeys' => count($queryKeys),
    'authenticatedPresentPosts' => $present,
    'authenticatedMissingExternalIds' => $absent,
    'samplePost' => $query['42']['value'],
    'unprovenNextPostAvailable' => $nextPostAvailable,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
