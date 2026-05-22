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
        label: str_repeat('WordPress Media ', 16),
        devices: [
            new Device(
                idHex: str_repeat('cd', 32),
                name: 'playground-importer',
                addresses: array_fill(0, 12, 'tcp://playground.local:22000'),
                compression: Device::COMPRESSION_METADATA,
                maxSequence: 128,
            ),
        ],
    ),
]);

$payload = BepWire::encodeClusterConfigPayload($config);
$frame = BepWire::encodeClusterConfigMessage($config, Device::COMPRESSION_METADATA);
$message = BepWire::decodeMessageFrame($frame);
$decoded = BepWire::decodeClusterConfigMessage($frame);
$headerLength = unpack('n', substr($frame, 0, 2))[1];
$wireMessageLength = unpack('N', substr($frame, 2 + $headerLength, 4))[1];

echo json_encode([
    'folder' => $decoded->folders[0]->id,
    'messageType' => $message['type'],
    'compression' => $message['compression'] === BepWire::MESSAGE_COMPRESSION_LZ4 ? 'lz4' : 'none',
    'uncompressedPayloadBytes' => strlen($payload),
    'wireMessageBytes' => $wireMessageLength,
    'addressCount' => count($decoded->folders[0]->devices[0]->addresses),
    'maxSequence' => $decoded->folders[0]->devices[0]->maxSequence,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
