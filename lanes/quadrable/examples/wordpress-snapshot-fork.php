<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\TrackedSparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixturePath = dirname(__DIR__) . '/fixtures/wordpress-ordered-snapshot.json';
$records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

$snapshot = new TrackedSparseTree();
$changes = $snapshot->change();

foreach ($records as $record) {
    $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
}

$changes->apply();

$oldHeadNodeId = $snapshot->headNodeId();
$oldRoot = $snapshot->rootHash();
$postKey = Key::fromInteger(3);

$updated = $snapshot->checkout($oldHeadNodeId);
$updated->putKey($postKey, 'wp_posts:1=Forked authenticated update');

$oldCheckout = $updated->checkout($oldHeadNodeId);
$newCheckout = $updated->checkout($updated->headNodeId());

echo json_encode([
    'oldHeadNodeId' => $oldHeadNodeId,
    'newHeadNodeId' => $updated->headNodeId(),
    'oldRoot' => $oldRoot,
    'newRoot' => $updated->rootHash(),
    'oldPost' => $oldCheckout->getKey($postKey),
    'newPost' => $newCheckout->getKey($postKey),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
