<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\IndexUpdate;
use PortLibs\Syncthing\ProtocolValidation;
use PortLibs\Syncthing\Request;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$bytes = 'wordpress media bytes';
$blockList = new BlockList();
$block = $blockList->fromBytes($bytes, 64)[0];
$uploadName = 'wp-content\\uploads\\2026\\Cafe' . "\u{0301}" . '.jpg';

$file = new FileInfo(
    name: $uploadName,
    modifiedS: 1700000300,
    version: VersionVector::fromCounters([101 => 1700000300]),
    size: strlen($bytes),
    blocksHash: $blockList->hashBlocks([$block]),
    type: FileInfo::TYPE_FILE,
    blocks: [new Block($block->offset, $block->size, $block->hashHex)],
);
$deleted = (new FileInfo(
    name: 'wp-content/uploads/2025/old-hero.jpg',
    modifiedS: 1700000000,
    version: VersionVector::fromCounters([101 => 1700000000]),
    size: 12,
    blocks: [new Block(0, 12, hash('sha256', 'old-hero.jpg'))],
))->withDeleted(101, 1700000301);

$indexUpdate = (new IndexUpdate('wordpress-media', [$file, $deleted], lastSequence: 22, prevSequence: 20))->normalizedForWire('\\');
$indexAccepted = true;
try {
    $indexUpdate->checkConsistency();
} catch (InvalidArgumentException) {
    $indexAccepted = false;
}

$traversalRequestAccepted = true;
try {
    ProtocolValidation::checkRequest(new Request(name: '../wp-config.php', size: 1024));
} catch (InvalidArgumentException) {
    $traversalRequestAccepted = false;
}

echo json_encode([
    'folder' => $indexUpdate->folder,
    'normalizedUpload' => $indexUpdate->files[0]->name,
    'indexFilesAccepted' => $indexAccepted,
    'maxRequestBytes' => ProtocolValidation::MAX_REQUEST_SIZE,
    'traversalRequestAccepted' => $traversalRequestAccepted,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
