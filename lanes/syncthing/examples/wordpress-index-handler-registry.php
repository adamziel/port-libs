<?php

declare(strict_types=1);

use PortLibs\Syncthing\Device;
use PortLibs\Syncthing\Folder;
use PortLibs\Syncthing\IndexHandlerRegistry;
use PortLibs\Syncthing\IndexHandlerStartInfo;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$remoteDevice = new Device(idHex: 'aa', name: 'Playground laptop', indexId: 900, maxSequence: 74);
$localDevice = new Device(idHex: 'bb', name: 'WP media origin', indexId: 77, maxSequence: 42);
$startInfo = new IndexHandlerStartInfo(local: $localDevice, remote: $remoteDevice);

$runner = new class {
    public int $scheduledPulls = 0;

    public function schedulePull(): void
    {
        $this->scheduledPulls++;
    }
};

$registry = new IndexHandlerRegistry(
    remoteDeviceIdHex: 'aa',
    localIndexId: 77,
    localCurrentSequence: 45,
);

$registry->addIndexInfo('wordpress-private-media', $startInfo);
$pendingBeforeResume = $registry->pendingFolders();

$folder = new Folder(
    id: 'wordpress-private-media',
    label: 'Private media exports',
    type: Folder::TYPE_RECEIVE_ENCRYPTED,
    devices: [$remoteDevice],
);
$handler = $registry->registerFolderState($folder, $runner);

$paused = new Folder(
    id: 'wordpress-private-media',
    label: 'Private media exports',
    type: Folder::TYPE_RECEIVE_ENCRYPTED,
    stopReason: Folder::STOP_REASON_PAUSED,
    devices: [$remoteDevice],
);
$registry->registerFolderState($paused);
$pausedFolders = $registry->runningFolders();
$registry->registerFolderState($folder, $runner);

echo json_encode([
    'pendingBeforeResume' => $pendingBeforeResume,
    'activeFoldersAfterResume' => $registry->runningFolders(),
    'pausedRunningFolders' => $pausedFolders,
    'startSequence' => $handler?->localPrevSequence(),
    'receiveEncrypted' => $handler?->folderIsReceiveEncrypted(),
    'scheduledPulls' => $runner->scheduledPulls,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
