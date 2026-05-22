<?php

declare(strict_types=1);

use PortLibs\Quadrable\DiffEntry;
use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\SparseTree;
use PortLibs\Quadrable\SyncCodec;
use PortLibs\Quadrable\SyncSession;

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
    ->putKey(Key::fromInteger(3), 'wp_posts:1=Hello synced world')
    ->deleteKey(Key::fromInteger(4))
    ->putKey(Key::fromInteger(6), 'wp_posts:2=' . str_repeat('Imported block ', 4))
    ->apply();

$session = new SyncSession($local, 1, 2);
$roundTrips = 0;

while (true) {
    $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(512)));
    if ($requests === []) {
        break;
    }

    $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, 1024)));
    $session->addResponses($requests, $responses);
    $roundTrips++;
}

$shadow = $session->shadow();
$diffs = $local->diffTo($shadow);
$reconstructed = $buildTree();
$reconstructed->applyDiffs($diffs);

echo json_encode([
    'localRoot' => $local->rootHash(),
    'remoteRoot' => $remote->rootHash(),
    'syncedShadowRoot' => $shadow->rootHash(),
    'reconstructedRoot' => $reconstructed->rootHash(),
    'roundTrips' => $roundTrips,
    'diffs' => array_map(static fn (DiffEntry $diff): array => [
        'type' => $diff->type,
        'key' => $diff->key()->toInteger(),
        'value' => $diff->value,
    ], $diffs),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
