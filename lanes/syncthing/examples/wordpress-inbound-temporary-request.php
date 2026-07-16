<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\RemoteDownloadProgressTracker;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$version = VersionVector::fromCounters([101 => 1700001200]);
$blocks = [];
for ($i = 0; $i < 6; $i++) {
    $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'wordpress-inbound-temp-' . $i));
}

$file = new FileInfo(
    name: 'wp-content/uploads/2026/hero.jpg',
    version: $version,
    size: 6 * BlockList::MIN_BLOCK_SIZE,
    rawBlockSize: BlockList::MIN_BLOCK_SIZE,
    blocks: $blocks,
);

$tracker = new RemoteDownloadProgressTracker([
    'wordpress-media' => ['playground-importer'],
]);

$event = $tracker->receiveDownloadProgress('playground-importer', new DownloadProgress('wordpress-media', [
    new FileDownloadProgressUpdate(
        updateType: FileDownloadProgressUpdate::TYPE_APPEND,
        name: $file->name,
        version: $version,
        blockIndexes: [3],
        blockSize: $file->blockSize(),
    ),
]));

$plan = $tracker->planBlockRequest('wordpress-media', $file, $blocks[3], requestId: 7003);
if ($plan === null) {
    throw new RuntimeException('Expected a temporary request plan');
}

$frame = BepWire::encodeRequestMessage($plan->request);
$decoded = BepWire::decodeRequestMessage($frame);

echo json_encode([
    'remoteEvent' => $event,
    'selectedDevice' => $plan->deviceId,
    'request' => [
        'messageType' => BepWire::decodeMessageFrame($frame)['type'],
        'name' => $decoded->name,
        'blockNo' => $decoded->blockNo,
        'offset' => $decoded->offset,
        'fromTemporary' => $decoded->fromTemporary,
    ],
    'remoteBytesAdvertised' => $tracker->bytesDownloaded('playground-importer', 'wordpress-media'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
