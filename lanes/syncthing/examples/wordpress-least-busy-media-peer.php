<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DeviceActivity;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\RemoteDownloadProgressTracker;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$version = VersionVector::fromCounters([101 => 1700001300]);
$blocks = [];
for ($i = 0; $i < 4; $i++) {
    $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'wordpress-least-busy-' . $i));
}

$file = new FileInfo(
    name: 'wp-content/uploads/2026/product-gallery.zip',
    version: $version,
    size: 4 * BlockList::MIN_BLOCK_SIZE,
    rawBlockSize: BlockList::MIN_BLOCK_SIZE,
    blocks: $blocks,
);

$activity = new DeviceActivity();
$tracker = new RemoteDownloadProgressTracker([
    'wordpress-media' => ['site-cdn-peer', 'editor-laptop-peer'],
], $activity);

$tracker->receiveDownloadProgress('editor-laptop-peer', new DownloadProgress('wordpress-media', [
    new FileDownloadProgressUpdate(
        updateType: FileDownloadProgressUpdate::TYPE_APPEND,
        name: $file->name,
        version: $version,
        blockIndexes: [1],
        blockSize: $file->blockSize(),
    ),
]));

$first = $tracker->planBlockRequest('wordpress-media', $file, $blocks[1], ['site-cdn-peer'], requestId: 9001);
if ($first === null) {
    throw new RuntimeException('Expected first media request plan');
}
$activity->using($first->availability);

$second = $tracker->planBlockRequest('wordpress-media', $file, $blocks[1], ['site-cdn-peer'], requestId: 9002);
if ($second === null) {
    throw new RuntimeException('Expected second media request plan');
}

echo json_encode([
    'firstPlan' => $first->toArray(),
    'secondPlan' => $second->toArray(),
    'activity' => [
        'site-cdn-peer' => $activity->usage('site-cdn-peer'),
        'editor-laptop-peer' => $activity->usage('editor-laptop-peer'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
