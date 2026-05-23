<?php

declare(strict_types=1);

use PortLibs\Syncthing\BlockDiff;
use PortLibs\Syncthing\Block;
use PortLibs\Syncthing\BlockList;
use PortLibs\Syncthing\FileInfo;
use PortLibs\Syncthing\PullWorkPlan;
use PortLibs\Syncthing\RemoteDownloadProgressTracker;
use PortLibs\Syncthing\VersionVector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$blockList = new BlockList();
$localBlocks = $blockList->fromBytes(
    str_repeat('a', BlockList::MIN_BLOCK_SIZE)
    . str_repeat('b', BlockList::MIN_BLOCK_SIZE)
    . str_repeat('c', BlockList::MIN_BLOCK_SIZE),
    BlockList::MIN_BLOCK_SIZE,
);
$targetBlocks = $blockList->fromBytes(
    str_repeat('a', BlockList::MIN_BLOCK_SIZE)
    . str_repeat('d', BlockList::MIN_BLOCK_SIZE)
    . str_repeat('c', BlockList::MIN_BLOCK_SIZE),
    BlockList::MIN_BLOCK_SIZE,
);

$diff = BlockDiff::between($localBlocks, $targetBlocks);
$temporaryBlocks = [
    new Block(0, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'stale first block')),
    new Block(BlockList::MIN_BLOCK_SIZE, BlockList::MIN_BLOCK_SIZE, hash('sha256', 'stale second block')),
    $targetBlocks[2],
];
$current = new FileInfo(
    name: 'wp-content/uploads/2026/hero.jpg',
    version: VersionVector::fromCounters([101 => 1]),
    size: 3 * BlockList::MIN_BLOCK_SIZE,
    rawBlockSize: BlockList::MIN_BLOCK_SIZE,
    blocks: $localBlocks,
);
$target = new FileInfo(
    name: 'wp-content/uploads/2026/hero.jpg',
    version: VersionVector::fromCounters([202 => 2]),
    size: 3 * BlockList::MIN_BLOCK_SIZE,
    rawBlockSize: BlockList::MIN_BLOCK_SIZE,
    blocks: $targetBlocks,
);
$pullWork = PullWorkPlan::forFile($target, $current, temporaryBlocks: $temporaryBlocks);

$tracker = new RemoteDownloadProgressTracker([
    'wordpress-media' => ['playground-peer'],
]);

$requests = [];
foreach ($pullWork->pullBlocks as $index => $block) {
    $plan = $tracker->planBlockRequest(
        'wordpress-media',
        $target,
        $block,
        completeDeviceIds: ['playground-peer'],
        requestId: 9100 + $index,
    );
    if ($plan !== null) {
        $requests[] = $plan->toArray();
    }
}

echo json_encode([
    'media' => $target->name,
    'reusedBlockRanges' => array_map(static fn ($block): array => [$block->offset, $block->size], $diff->have),
    'requestedBlockRanges' => array_map(static fn ($block): array => [$block->offset, $block->size], $diff->need),
    'pullWorkPlan' => $pullWork->toArray(),
    'requests' => $requests,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
