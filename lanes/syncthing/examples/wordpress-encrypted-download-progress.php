<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\DownloadProgress;
use PortLibs\Syncthing\EncryptedDownloadProgress;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\RemoteDownloadProgressTracker;
use PortLibs\Syncthing\VersionVector;
use PortLibs\Syncthing\WireProgressConnection;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$router = EncryptedDownloadProgress::fromPasswords([
    'wordpress-private-media' => 'wordpress media sync secret',
]);
$version = VersionVector::fromCounters([101 => 1700001900]);
$privateProgress = new DownloadProgress('wordpress-private-media', [
    new FileDownloadProgressUpdate(
        updateType: FileDownloadProgressUpdate::TYPE_APPEND,
        name: 'wp-content\\uploads\\private\\hero.jpg',
        version: $version,
        blockIndexes: [0, 1],
        blockSize: BlockList::MIN_BLOCK_SIZE,
    ),
]);
$publicProgress = new DownloadProgress('wordpress-public-media', [
    new FileDownloadProgressUpdate(
        updateType: FileDownloadProgressUpdate::TYPE_APPEND,
        name: 'wp-content\\uploads\\public\\hero.jpg',
        version: $version,
        blockIndexes: [3],
        blockSize: BlockList::MIN_BLOCK_SIZE,
    ),
]);

$frames = [];
$connection = new WireProgressConnection(
    'untrusted-peer',
    static function (string $deviceId, string $frame, DownloadProgress $progress) use (&$frames): void {
        $frames[] = [
            'device' => $deviceId,
            'frame' => $frame,
            'progress' => $progress,
        ];
    },
    directorySeparator: '\\',
);

$privateForwarded = $router->sendOutgoing($connection, $privateProgress);
$publicForwarded = $router->sendOutgoing($connection, $publicProgress);
$decodedPublic = BepWire::decodeDownloadProgressMessage($frames[0]['frame']);

$tracker = new RemoteDownloadProgressTracker([
    'wordpress-private-media' => ['untrusted-peer'],
    'wordpress-public-media' => ['trusted-peer'],
]);
$privateEvent = $router->receiveIncoming($tracker, 'untrusted-peer', $privateProgress);
$publicEvent = $router->receiveIncoming($tracker, 'trusted-peer', $publicProgress);

echo json_encode([
    'encryptedFolder' => 'wordpress-private-media',
    'privateProgressForwarded' => $privateForwarded,
    'privateRemoteEvent' => $privateEvent,
    'privateTemporaryBlocks' => $tracker->remoteBlockCounts('untrusted-peer', 'wordpress-private-media'),
    'publicProgressForwarded' => $publicForwarded,
    'framesSent' => count($frames),
    'publicWireFrame' => [
        'messageType' => BepWire::decodeMessageFrame($frames[0]['frame'])['type'],
        'folder' => $decodedPublic->folder,
        'name' => $decodedPublic->updates[0]->name,
        'blockIndexes' => $decodedPublic->updates[0]->blockIndexes,
    ],
    'publicRemoteEvent' => $publicEvent,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
