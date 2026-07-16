<?php

declare(strict_types=1);

use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\BlockPullReorderer;
use PortLibs\Syncthing\DeviceId;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$localPlayground = wordpressBlockPullOrderDevice(3);
$desktopPeer = wordpressBlockPullOrderDevice(1);
$stagingPeer = wordpressBlockPullOrderDevice(2);
$blockSize = BlockList::MIN_BLOCK_SIZE;

$file = new FileInfo(
    name: 'wp-content/uploads/2026/large-gallery.zip',
    modifiedS: 1780000000,
    version: VersionVector::fromCounters([101 => 1780000000]),
    size: 6 * $blockSize,
    rawBlockSize: $blockSize,
    blocks: wordpressBlockPullOrderBlocks(6, $blockSize),
);

$ordered = BlockPullReorderer::standard(
    $file->blocks,
    $localPlayground,
    [$desktopPeer, $stagingPeer],
    static fn (array $chunkIndexes): array => $chunkIndexes,
);

echo json_encode([
    'file' => $file->name,
    'localDeviceRank' => 'last of three sorted device IDs',
    'standardOrder' => array_map(
        static fn (Block $block): array => [
            'blockNo' => intdiv($block->offset, $file->blockSize()),
            'offset' => $block->offset,
            'size' => $block->size,
        ],
        $ordered,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

function wordpressBlockPullOrderDevice(int $byte): DeviceId
{
    return DeviceId::fromBytes(str_repeat(chr($byte), DeviceId::LENGTH));
}

/**
 * @return list<Block>
 */
function wordpressBlockPullOrderBlocks(int $count, int $blockSize): array
{
    $blocks = [];
    for ($i = 0; $i < $count; $i++) {
        $blocks[] = new Block(
            offset: $i * $blockSize,
            size: $blockSize,
            hashHex: hash('sha256', 'wordpress-block-pull-order-' . $i),
        );
    }

    return $blocks;
}
