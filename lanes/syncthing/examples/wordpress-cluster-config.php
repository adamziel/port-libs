<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\ClusterConfig;
use PortLibs\Syncthing\Device;
use PortLibs\Syncthing\Folder;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$config = new ClusterConfig([
    new Folder(
        id: 'wordpress-media',
        label: 'WordPress Media',
        type: Folder::TYPE_SEND_RECEIVE,
        devices: [
            new Device(
                idHex: str_repeat('ab', 32),
                name: 'playground-importer',
                addresses: ['tcp://127.0.0.1:22000', 'dynamic'],
                compression: Device::COMPRESSION_METADATA,
                certName: 'playground.local',
                maxSequence: 22,
                indexId: 42,
            ),
        ],
    ),
], secondary: false);

$frame = BepWire::encodeClusterConfigMessage($config);
$decoded = BepWire::decodeClusterConfigMessage($frame);
$folder = $decoded->folders[0];
$device = $folder->devices[0];

echo json_encode([
    'folder' => $folder->id,
    'description' => $folder->description(),
    'deviceName' => $device->name,
    'addresses' => $device->addresses,
    'compression' => $device->compression,
    'maxSequence' => $device->maxSequence,
    'frameBytes' => strlen($frame),
    'messageType' => BepWire::MESSAGE_TYPE_CLUSTER_CONFIG,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

