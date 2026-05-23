<?php

declare(strict_types=1);

use PortLibs\Quadrable\Proof;
use PortLibs\Quadrable\SparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$oracle = json_decode(
    (string) file_get_contents(__DIR__ . '/../fixtures/upstream-big-proof-oracle.json'),
    true,
    flags: JSON_THROW_ON_ERROR
);

$tree = new SparseTree();
$changes = $tree->change();

for ($postId = 0; $postId < $oracle['entryCount']; $postId++) {
    $encodedPostRow = (string) $postId . 'val';
    $changes->put((string) $postId, $encodedPostRow);
}

$changes->apply();

$queryKeys = [];
for ($externalId = $oracle['queryStartInclusive']; $externalId < $oracle['queryEndExclusive']; $externalId++) {
    $queryKeys[] = (string) $externalId;
}

$proofBytes = $tree->exportProof($queryKeys)->encode();
$proofHex = '0x' . bin2hex($proofBytes);
$partial = SparseTree::importProof(Proof::decode($proofBytes), $tree->rootHash());
$query = $partial->getMulti($queryKeys);

echo json_encode([
    'scenario' => 'verify a large delegated WordPress post-id proof window against the upstream C++ byte oracle',
    'upstreamCommit' => $oracle['upstream']['commit'],
    'queriedPostIds' => count($queryKeys),
    'provedExistingPostIds' => count(array_filter($query, static fn (array $result): bool => $result['exists'])),
    'provedMissingExternalIds' => count(array_filter($query, static fn (array $result): bool => !$result['exists'])),
    'encodedProofBytes' => strlen($proofBytes),
    'matchesUpstreamOracleBytes' => hash('sha256', $proofBytes) === $oracle['encodedProofBytesSha256']
        && hash('sha256', $proofHex) === $oracle['encodedProofHexTextSha256'],
    'sampleDelegatedPostRow' => $partial->get('42'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
