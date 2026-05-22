<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockList;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$path = dirname(__DIR__) . '/fixtures/wordpress-media-upload.bin';
$bytes = (string) file_get_contents($path);
$blockList = new BlockList();
$blocks = $blockList->fromBytes($bytes, 32);
$alreadyStored = array_slice($blocks, 0, 2);

$resumeFrom = 0;
foreach ($alreadyStored as $block) {
    if (!$blockList->validateBytes(substr($bytes, $block->offset, $block->size), $block->hashHex)) {
        break;
    }
    $resumeFrom = $block->offset + $block->size;
}

echo json_encode([
    'source' => basename($path),
    'totalBytes' => strlen($bytes),
    'blockSize' => 32,
    'blocks' => count($blocks),
    'blocksHash' => $blockList->hashBlocks($blocks),
    'resumeFromByte' => $resumeFrom,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
