<?php

declare(strict_types=1);

use PortLibs\Quadrable\Key;
use PortLibs\Quadrable\SparseTree;
use PortLibs\Quadrable\SyncRequest;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixturePath = dirname(__DIR__) . '/fixtures/wordpress-ordered-snapshot.json';
$records = json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

$remote = new SparseTree();
$changes = $remote->change();
foreach ($records as $record) {
    $changes->putKey(Key::fromInteger((int) $record['key']), (string) $record['value']);
}
$changes
    ->putKey(Key::fromInteger(3), 'wp_posts:1=Guarded proof fragment')
    ->apply();

$requests = [
    new SyncRequest(Key::null(), 0, 1, false),
    new SyncRequest(Key::null(), 1, 1, false),
    new SyncRequest(Key::max(), 1, 1, false),
];

$accepted = true;
$error = null;
try {
    $remote->handleSyncRequests($requests, 2048);
} catch (InvalidArgumentException $exception) {
    $accepted = false;
    $error = $exception->getMessage();
}

echo json_encode([
    'remoteRoot' => $remote->rootHash(),
    'requestCount' => count($requests),
    'accepted' => $accepted,
    'error' => $error,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
