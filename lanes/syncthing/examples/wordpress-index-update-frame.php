<?php

declare(strict_types=1);

use PortLibs\Syncthing\BepWire;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$bytes = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin');
$blockList = new BlockList();
$blocks = $blockList->fromBytes($bytes, 32);
$file = new FileInfo(
    name: 'wp-content\\uploads\\2026\\' . basename(dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin'),
    modifiedS: 1700000700,
    version: VersionVector::fromCounters([101 => 1700000700]),
    size: strlen($bytes),
    blocksHash: $blockList->hashBlocks($blocks),
    permissions: 0644,
    rawBlockSize: 32,
    sequence: 52,
    blocks: array_map(
        static fn (Block $block): Block => new Block($block->offset, $block->size, $block->hashHex),
        $blocks,
    ),
    modifiedBy: 101,
);

$update = (new IndexUpdate('wordpress-media', [$file], lastSequence: 52, prevSequence: 48))->normalizedForWire('\\');
$frame = BepWire::encodeIndexUpdateMessage($update);
$decoded = BepWire::decodeIndexUpdateMessage($frame);
$decodedFile = $decoded->files[0];

echo json_encode([
    'folder' => $decoded->folder,
    'wireName' => $decodedFile->name,
    'messageType' => BepWire::MESSAGE_TYPE_INDEX_UPDATE,
    'lastSequence' => $decoded->lastSequence,
    'prevSequence' => $decoded->prevSequence,
    'fileSequence' => $decodedFile->sequence,
    'version' => $decodedFile->version->humanString(),
    'blocks' => count($decodedFile->blocks),
    'blocksHash' => $decodedFile->blocksHash,
    'frameBytes' => strlen($frame),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
