<?php

declare(strict_types=1);

use PortLibs\Syncthing\Device;
use PortLibs\Syncthing\DeviceDownloadState;
use PortLibs\Syncthing\FileDownloadProgressUpdate;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\IndexHandlerRegistry;
use PortLibs\Syncthing\IndexHandlerStartInfo;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$remoteDevice = new Device(idHex: 'aa', name: 'Playground laptop', indexId: 900, maxSequence: 51);
$localDevice = new Device(idHex: 'bb', name: 'WP media origin', indexId: 77, maxSequence: 20);
$version = VersionVector::fromCounters([202 => 52]);

$downloads = new DeviceDownloadState();
$downloads->update('wordpress-media', [
    new FileDownloadProgressUpdate(
        updateType: FileDownloadProgressUpdate::TYPE_APPEND,
        name: 'wp-content/uploads/2026/hero.jpg',
        version: $version,
        blockIndexes: [0, 1],
        blockSize: 1024 * 1024,
    ),
]);

$events = [];
$runner = new class {
    public int $scheduledPulls = 0;

    public function schedulePull(): void
    {
        $this->scheduledPulls++;
    }
};

$registry = new IndexHandlerRegistry(
    remoteDeviceIdHex: $remoteDevice->idHex,
    localIndexId: 77,
    localCurrentSequence: 20,
    downloads: $downloads,
    eventLogger: static function (string $type, array $data) use (&$events): void {
        $events[] = [$type, $data];
    },
);

$registry->addIndexInfo(
    'wordpress-media',
    new IndexHandlerStartInfo(local: $localDevice, remote: $remoteDevice),
);
$registry->registerFolderState(new Folder(
    id: 'wordpress-media',
    label: 'Shared media uploads',
    devices: [$remoteDevice],
), $runner);

$result = $registry->receiveIndex(
    folder: 'wordpress-media',
    files: [
        new FileInfo(
            name: 'wp-content/uploads/2026/hero.jpg',
            version: $version,
            size: 2 * 1024 * 1024,
            sequence: 52,
        ),
    ],
    update: false,
    operation: 'Index',
    lastSequence: 52,
);

echo json_encode([
    'remoteSequence' => $result->sequence,
    'temporaryBlocksAfterIndex' => $downloads->getBlockCounts('wordpress-media'),
    'events' => $events,
    'scheduledPulls' => $runner->scheduledPulls,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
