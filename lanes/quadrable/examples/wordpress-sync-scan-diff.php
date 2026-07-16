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
    ->putKey(Key::fromInteger(2), 'wp_options:home=https://mirror.example.test')
    ->deleteKey(Key::fromInteger(5))
    ->putKey(Key::fromInteger(6), 'wp_posts:3=Scan callback import')
    ->apply();

$session = new SyncSession($local, 1, 2);
$scanDiffs = [];
$roundTrips = 0;

while (true) {
    $requests = SyncCodec::decodeRequests(SyncCodec::encodeRequests($session->getRequests(
        512,
        static function (DiffEntry $diff) use (&$scanDiffs): void {
            $scanDiffs[] = $diff;
        }
    )));
    if ($requests === []) {
        break;
    }

    $responses = SyncCodec::decodeResponses(SyncCodec::encodeResponses($remote->handleSyncRequests($requests, 1024)));
    $session->addResponses($requests, $responses);
    $roundTrips++;
}

$shadow = $session->shadow();
$finalDiffs = $local->diffTo($shadow);

$formatDiffs = static fn (array $diffs): array => array_map(static fn (DiffEntry $diff): array => [
    'type' => $diff->type,
    'key' => $diff->key()->toInteger(),
    'value' => $diff->value,
], $diffs);

echo json_encode([
    'localRoot' => $local->rootHash(),
    'remoteRoot' => $remote->rootHash(),
    'syncedShadowRoot' => $shadow->rootHash(),
    'roundTrips' => $roundTrips,
    'scanDiffs' => $formatDiffs($scanDiffs),
    'finalDiffs' => $formatDiffs($finalDiffs),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
