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
    ->putKey(Key::fromInteger(3), 'wp_posts:1=Imported with sync node ids')
    ->deleteKey(Key::fromInteger(4))
    ->putKey(Key::fromInteger(6), 'wp_posts:3=Proof fragment node id parity')
    ->apply();

$session = new SyncSession($local, 1, 1);
$scanDiffs = [];
$roundTrips = 0;

while (true) {
    $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(
        128,
        static function (DiffEntry $diff) use (&$scanDiffs): void {
            $scanDiffs[] = $diff;
        }
    )));
    if ($requests === []) {
        break;
    }

    $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, 512)));
    $session->addResponses($requests, $responses);
    $roundTrips++;
}

$finalDiffs = $local->diffTo($session->shadow());

$formatDiffs = static fn (array $diffs): array => array_map(static fn (DiffEntry $diff): array => [
    'type' => $diff->type,
    'key' => $diff->key()->toInteger(),
    'value' => $diff->value,
    'nodeId' => $diff->nodeId,
    'nodeIdSource' => $diff->nodeId >= TrackedNodeStore::FIRST_MEMSTORE_NODE_ID ? 'imported-shadow' : 'local-snapshot',
], $diffs);

echo json_encode([
    'localRoot' => $local->rootHash(),
    'remoteRoot' => $remote->rootHash(),
    'syncedShadowRoot' => $session->shadow()->rootHash(),
    'roundTrips' => $roundTrips,
    'scanDiffs' => $formatDiffs($scanDiffs),
    'finalDiffs' => $formatDiffs($finalDiffs),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
