<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\TrackedNodeStore;
use PortLibs\Quadrable\TrackedSparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixturePath = dirname(__DIR__) . '/fixtures/wordpress-ordered-snapshot.json';
$records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

$store = new TrackedNodeStore();
$published = (new TrackedSparseTree($store))->checkout('published-snapshot');
$changes = $published->change();

foreach ($records as $record) {
    $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
}

$changes->apply();

$snapshotJson = json_encode($store->exportSnapshot(), JSON_THROW_ON_ERROR);
$restoredStore = TrackedNodeStore::fromSnapshot(json_decode($snapshotJson, true, flags: JSON_THROW_ON_ERROR));
$restored = (new TrackedSparseTree($restoredStore))->checkout('published-snapshot');

$postKey = Key::fromInteger(3);
$siteUrlNodeId = 0;
$restoredSiteUrlNodeId = 0;
$published->getKey(Key::fromInteger(1), $siteUrlNodeId);
$restored->getKey(Key::fromInteger(1), $restoredSiteUrlNodeId);

$preview = $restored->withMemStoreWrites()->fork();
$preview->putKey($postKey, 'wp_posts:1=Reloaded preview edit');

echo json_encode([
    'snapshotBytes' => strlen($snapshotJson),
    'publishedHeadNodeId' => $published->headNodeId(),
    'restoredHeadNodeId' => $restored->headNodeId(),
    'sameHeadNodeId' => $published->headNodeId() === $restored->headNodeId(),
    'sameRoot' => $published->rootHash() === $restored->rootHash(),
    'siteUrlNodeId' => $siteUrlNodeId,
    'restoredSiteUrlNodeId' => $restoredSiteUrlNodeId,
    'sameSiteUrlNodeId' => $siteUrlNodeId === $restoredSiteUrlNodeId,
    'publishedPost' => $restored->getKey($postKey),
    'previewPost' => $preview->getKey($postKey),
    'previewUsesMemStoreRange' => $preview->headNodeId() >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
