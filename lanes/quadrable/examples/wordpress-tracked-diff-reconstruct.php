<?php

declare(strict_types=1);

use PortLibs\Quadrable\DiffEntry;
use PortLibs\Quadrable\Key;
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

$local = $base->checkout($base->headNodeId());
$remote = $base->checkout($base->headNodeId());
$remote->change()
    ->putKey(Key::fromInteger(3), 'wp_posts:1=Diff-applied authenticated import')
    ->deleteKey(Key::fromInteger(4))
    ->putKey(Key::fromInteger(6), 'wp_posts:3=Imported from a tracked diff')
    ->apply();

$scanDiffs = [];
$finalDiffs = $local->diffTo($remote, static function (DiffEntry $diff) use (&$scanDiffs): void {
    $scanDiffs[] = $diff;
});

$reconstructed = $local->checkout($local->headNodeId());
$reconstructed->applyDiffs($finalDiffs);

$formatDiffs = static fn (array $diffs): array => array_map(static fn (DiffEntry $diff): array => [
    'type' => $diff->type,
    'key' => $diff->key()->toInteger(),
    'value' => $diff->value,
    'nodeId' => $diff->nodeId,
], $diffs);

echo json_encode([
    'localRoot' => $local->rootHash(),
    'remoteRoot' => $remote->rootHash(),
    'reconstructedRoot' => $reconstructed->rootHash(),
    'scanDiffs' => $formatDiffs($scanDiffs),
    'finalDiffs' => $formatDiffs($finalDiffs),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
