<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\TrackedNodeStore;
use PortLibs\Quadrable\TrackedSparseTree;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixturePath = dirname(__DIR__) . '/fixtures/wordpress-ordered-snapshot.json';
$records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

$published = (new TrackedSparseTree())->checkout('published-snapshot');
$changes = $published->change();

foreach ($records as $record) {
    $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
}

$changes->apply();

$publishedHeadNodeId = $published->headNodeId();
$publishedRoot = $published->rootHash();
$postKey = Key::fromInteger(3);

$guarded = $published->checkout('published-snapshot')->withMemStoreWrites();
$guardedError = null;
try {
    $guarded->putKey($postKey, 'wp_posts:1=Preview write should be forked first');
} catch (RuntimeException $error) {
    $guardedError = $error->getMessage();
}

$preview = $guarded->fork();
$previewPostNodeId = 0;
$preview->change()
    ->putKey($postKey, 'wp_posts:1=Previewed from a detached memstore fork', $previewPostNodeId)
    ->putKey(Key::fromInteger(7), 'wp_posts:4=Unsaved named-head preview')
    ->apply();

$publishedAgain = $preview->checkout('published-snapshot');

echo json_encode([
    'guardedError' => $guardedError,
    'publishedHeadNodeId' => $publishedHeadNodeId,
    'previewHeadNodeId' => $preview->headNodeId(),
    'previewUsesMemStoreRange' => $preview->headNodeId() >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID,
    'previewPostNodeId' => $previewPostNodeId,
    'previewPostUsesMemStoreRange' => $previewPostNodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID,
    'publishedRoot' => $publishedRoot,
    'previewRoot' => $preview->rootHash(),
    'publishedPost' => $publishedAgain->getKey($postKey),
    'previewPost' => $preview->getKey($postKey),
    'publishedDraft' => $publishedAgain->getKey(Key::fromInteger(7)),
    'previewDraft' => $preview->getKey(Key::fromInteger(7)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
