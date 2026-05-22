<?php

declare(strict_types=1);

use PortLibs\Quadrable\DiffEntry;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\SparseTree;
use PortLibs\Quadrable\SyncCodec;
use PortLibs\Quadrable\SyncSession;
use PortLibs\Quadrable\TrackedNodeStore;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixturePath = dirname(__DIR__) . '/fixtures/wordpress-ordered-snapshot.json';
$records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

$buildTree = static function () use ($records): SparseTree {
    $tree = new SparseTree();
    $changes = $tree->change();

    foreach ($records as $record) {
        $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
    }

    $changes->apply();

    return $tree;
};

$local = $buildTree();
$remote = $buildTree();
$remote->change()
    ->putKey(Key::fromInteger(2), 'wp_options:home=https://shadow.example.test')
    ->putKey(Key::fromInteger(6), 'wp_posts:3=Shadow root node import')
    ->apply();

$session = new SyncSession($local, 1, 1);
$roundTrips = 0;
$shadowRootNodeIds = [];

while (true) {
    $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(128)));
    if ($requests === []) {
        break;
    }

    $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, 512)));
    $session->addResponses($requests, $responses);
    $shadowRootNodeIds[] = $session->shadowNodeId();
    $roundTrips++;
}

$shadow = $session->shadow();
$diffs = $local->diffTo($shadow);

echo json_encode([
    'remoteRoot' => $remote->rootHash(),
    'shadowRoot' => $shadow->rootHash(),
    'shadowRootNodeId' => $session->shadowNodeId(),
    'shadowRootNodeIdSource' => $session->shadowNodeId() >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID ? 'memStore-shadow' : 'persistent',
    'shadowRootNodeIdsSeen' => $shadowRootNodeIds,
    'roundTrips' => $roundTrips,
    'diffs' => array_map(static fn (DiffEntry $diff): array => [
        'type' => $diff->type,
        'key' => $diff->key()->toInteger(),
        'value' => $diff->value,
        'nodeId' => $diff->nodeId,
    ], $diffs),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
