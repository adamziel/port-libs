<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\FileInfoComparison;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$path = dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin';
$bytes = (string) file_get_contents($path);
$blockList = new BlockList();
$blocks = $blockList->fromBytes($bytes, 32);
$blocksHash = $blockList->hashBlocks($blocks);

$synced = new FileInfo(
    name: 'wp-content/uploads/2026/' . basename($path),
    modifiedS: 1700000200,
    version: VersionVector::fromCounters([101 => 4]),
    size: strlen($bytes),
    blocksHash: $blocksHash,
    permissions: 0644,
    rawBlockSize: 32,
    sequence: 10,
    blocks: $blocks,
);

$rescanned = new FileInfo(
    name: $synced->name,
    modifiedS: $synced->modifiedS,
    version: VersionVector::fromCounters([202 => 1]),
    size: $synced->size,
    blocksHash: $blocksHash,
    permissions: 0600,
    rawBlockSize: 64,
    sequence: 11,
    blocks: $blocks,
);

$editedBytes = substr_replace($bytes, 'WP', 8, 2);
$editedBlocks = $blockList->fromBytes($editedBytes, 32);
$edited = new FileInfo(
    name: $synced->name,
    modifiedS: $synced->modifiedS,
    size: strlen($editedBytes),
    blocksHash: $blockList->hashBlocks($editedBlocks),
    permissions: 0644,
    blocks: $editedBlocks,
);

echo json_encode([
    'file' => $synced->name,
    'scannerOnlyChangeEquivalent' => $synced->isEquivalent($rescanned, new FileInfoComparison(ignorePerms: true)),
    'contentEditEquivalent' => $synced->isEquivalent($edited),
    'blocksHash' => $blocksHash,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
