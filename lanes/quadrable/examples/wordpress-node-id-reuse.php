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

$siteUrlNodeId = 0;
$siteUrl = $snapshot->getKey(Key::fromInteger(1), $siteUrlNodeId);
$rebuilt = $snapshot->checkoutEmpty();
$reuseChanges = $rebuilt->change();
$reusedSiteUrlNodeId = 0;

for ($iterator = $snapshot->iterate(Key::null()); !$iterator->atEnd(); $iterator->next()) {
    $entry = $iterator->get();
    if ($entry === null) {
        break;
    }

    if ($entry->key()->toInteger() === 1) {
        $reuseChanges->putReuse($entry->key(), $entry->nodeId, $reusedSiteUrlNodeId);
    } else {
        $unusedNodeId = 0;
        $reuseChanges->putReuse($entry->key(), $entry->nodeId, $unusedNodeId);
    }
}

$reuseChanges->apply();

echo json_encode([
    'trustedRoot' => $snapshot->rootHash(),
    'rebuiltRoot' => $rebuilt->rootHash(),
    'originalHeadNodeId' => $snapshot->headNodeId(),
    'rebuiltHeadNodeId' => $rebuilt->headNodeId(),
    'siteUrlNodeId' => $siteUrlNodeId,
    'reusedSiteUrlNodeId' => $reusedSiteUrlNodeId,
    'siteUrl' => $siteUrl,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
