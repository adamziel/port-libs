<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\ProtocolValidation;
use PortLibs\Syncthing\Request;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$path = dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin';
$bytes = (string) file_get_contents($path);
$blockList = new BlockList();
$blocks = $blockList->fromBytes($bytes, 32);
$missing = $blocks[2];

$request = (new Request(
    id: 7,
    folder: 'wordpress-media',
    name: 'wp-content\\uploads\\2026\\' . basename($path),
    offset: $missing->offset,
    size: $missing->size,
    hashHex: $missing->hashHex,
    fromTemporary: true,
    blockNo: 2,
))->normalizedForWire('\\');

ProtocolValidation::checkRequest($request);
$frame = BepWire::encodeRequestMessage($request);
$decoded = BepWire::decodeRequestMessage($frame);

echo json_encode([
    'folder' => $decoded->folder,
    'wireName' => $decoded->name,
    'messageType' => BepWire::MESSAGE_TYPE_REQUEST,
    'blockNo' => $decoded->blockNo,
    'offset' => $decoded->offset,
    'size' => $decoded->size,
    'frameBytes' => strlen($frame),
    'framePrefixHex' => bin2hex(substr($frame, 0, 8)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
