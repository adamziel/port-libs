<?php

declare(strict_types=1);

use PortLibs\Syncthing\ActiveDownload;
use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\SentDownloadState;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$version = VersionVector::fromCounters([101 => 1700000900]);
$blocks = [];
for ($i = 0; $i < 12; $i++) {
    $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'media-block-' . $i));
}

$file = new FileInfo(
    name: 'wp-content/uploads/2026/hero.jpg',
    version: $version,
    rawBlockSize: BlockList::MIN_BLOCK_SIZE,
    blocks: $blocks,
);

$sent = new SentDownloadState();
$first = $sent->update('wordpress-media', [
    new ActiveDownload('wordpress-media', $file, [0, 1], availableUpdated: 1, created: 1),
], minBlocks: 10);
$delta = $sent->update('wordpress-media', [
    new ActiveDownload('wordpress-media', $file, [0, 1, 4], availableUpdated: 2, created: 1),
], minBlocks: 10);
$cleanup = $sent->update('wordpress-media', [], minBlocks: 10);

$progressFrame = BepWire::encodeDownloadProgressMessage(new DownloadProgress('wordpress-media', $delta));

echo json_encode([
    'folder' => 'wordpress-media',
    'firstAppend' => $first[0]->blockIndexes,
    'deltaAppend' => $delta[0]->blockIndexes,
    'deltaMessageType' => BepWire::decodeMessageFrame($progressFrame)['type'],
    'cleanupType' => $cleanup[0]->updateType,
    'cleanupName' => $cleanup[0]->name,
    'cleanupVersion' => $cleanup[0]->version->humanString(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
