<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\RemoteDownloadProgressTracker;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$version = VersionVector::fromCounters([101 => 1700003100]);
$blocks = [];
for ($i = 0; $i < 2; $i++) {
    $blocks[] = new Block($i * BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'wordpress-temp-disconnect-' . $i));
}

$file = new FileInfo(
    name: 'wp-content/uploads/2026/disconnected-editor.jpg',
    version: $version,
    size: 2 * BlockList::MIN_BLOCK_SIZE,
    rawBlockSize: BlockList::MIN_BLOCK_SIZE,
    blocks: $blocks,
);

$tracker = new RemoteDownloadProgressTracker([
    'wordpress-media' => ['editor-laptop-peer', 'cdn-peer'],
]);
$tracker->connectDevice('editor-laptop-peer');
$tracker->connectDevice('cdn-peer');

$advertised = $tracker->receiveDownloadProgress('editor-laptop-peer', new DownloadProgress('wordpress-media', [
    new FileDownloadProgressUpdate(
        updateType: FileDownloadProgressUpdate::TYPE_APPEND,
        name: $file->name,
        version: $version,
        blockIndexes: [1],
        blockSize: $file->blockSize(),
    ),
]));
$temporaryPlan = $tracker->planBlockRequest('wordpress-media', $file, $blocks[1], requestId: 8101);
if ($temporaryPlan === null) {
    throw new RuntimeException('Expected a temporary request while the editor peer is connected');
}

$tracker->disconnectDevice('editor-laptop-peer');
$afterDisconnect = $tracker->planBlockRequest('wordpress-media', $file, $blocks[1], ['editor-laptop-peer', 'cdn-peer'], requestId: 8102);
if ($afterDisconnect === null) {
    throw new RuntimeException('Expected the connected CDN peer to remain available');
}

echo json_encode([
    'advertised' => $advertised,
    'beforeDisconnect' => $temporaryPlan->toArray(),
    'connectedAfterDisconnect' => $tracker->connectedDeviceIds(),
    'editorTemporaryBlocksAfterDisconnect' => $tracker->remoteBlockCounts('editor-laptop-peer', 'wordpress-media'),
    'afterDisconnect' => $afterDisconnect->toArray(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
