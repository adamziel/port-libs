<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\TrackedNodeStore;
use PortLibs\Quadrable\TrackedSparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixturePath = dirname(__DIR__) . '/fixtures/wordpress-ordered-snapshot.json';
$records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

$base = new TrackedSparseTree();
$changes = $base->change();

foreach ($records as $record) {
    $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
}

$changes->apply();

$baseHeadNodeId = $base->headNodeId();
$baseRoot = $base->rootHash();
$postKey = Key::fromInteger(3);

$overlay = $base->withMemStoreWrites();
$draftPostNodeId = 0;
$overlay->change()
    ->putKey($postKey, 'wp_posts:1=Previewed in a volatile memstore overlay')
    ->putKey(Key::fromInteger(7), 'wp_posts:4=Unsaved playground preview', $draftPostNodeId)
    ->apply();

echo json_encode([
    'baseHeadNodeId' => $baseHeadNodeId,
    'overlayHeadNodeId' => $overlay->headNodeId(),
    'overlayUsesMemStoreRange' => $overlay->headNodeId() >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID,
    'draftPostNodeId' => $draftPostNodeId,
    'draftUsesMemStoreRange' => $draftPostNodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID,
    'baseRoot' => $baseRoot,
    'overlayRoot' => $overlay->rootHash(),
    'basePost' => $base->getKey($postKey),
    'overlayPost' => $overlay->getKey($postKey),
    'baseDraft' => $base->getKey(Key::fromInteger(7)),
    'overlayDraft' => $overlay->getKey(Key::fromInteger(7)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
