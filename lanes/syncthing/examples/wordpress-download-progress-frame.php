<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DeviceDownloadState;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$version = VersionVector::fromCounters([101 => 1700000800]);
$append = new FileDownloadProgressUpdate(
    updateType: FileDownloadProgressUpdate::TYPE_APPEND,
    name: 'wp-content\\uploads\\2026\\hero.jpg',
    version: $version,
    blockIndexes: [0, 1, 4],
    blockSize: BlockList::MIN_BLOCK_SIZE,
);
$progress = (new DownloadProgress('wordpress-media', [$append]))->normalizedForWire('\\');
$frame = BepWire::encodeDownloadProgressMessage($progress);
$decoded = BepWire::decodeDownloadProgressMessage($frame);

$downloadState = new DeviceDownloadState();
$downloadState->update($decoded->folder, $decoded->updates);
$forget = new FileDownloadProgressUpdate(
    updateType: FileDownloadProgressUpdate::TYPE_FORGET,
    name: $decoded->updates[0]->name,
    version: $version,
);

echo json_encode([
    'folder' => $decoded->folder,
    'wireName' => $decoded->updates[0]->name,
    'messageType' => BepWire::decodeMessageFrame($frame)['type'],
    'appendIndexes' => $decoded->updates[0]->blockIndexes,
    'blockSize' => $decoded->updates[0]->blockSize,
    'hasTemporaryBlockOne' => $downloadState->has('wordpress-media', 'wp-content/uploads/2026/hero.jpg', $version, 1),
    'bytesAdvertised' => $downloadState->bytesDownloaded('wordpress-media'),
    'forgetFrameBytes' => strlen(BepWire::encodeDownloadProgressMessage(new DownloadProgress('wordpress-media', [$forget]))),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
